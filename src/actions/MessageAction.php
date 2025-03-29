<?php
namespace yii\wechat\actions;

use SimpleXMLElement;
use Yii;
use yii\base\Action;
use yii\di\Instance;
use yii\web\Response;
use yii\wechat\MessageCryptoException;
use yii\wechat\MessageParamException;
use yii\wechat\SignatureException;
use yii\wechat\Wechat;
use yii\wechat\events\WechatEvent;

class MessageAction extends Action
{
	public $wechat = 'wechat';
	public $tolrence = 30;
	public $acceptor;
	private $appid;
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
		if (!empty($this->acceptor) && !is_array($this->acceptor)) {
			$this->acceptor = [$this->acceptor];
		}
	}
	public function run()
	{
		$request = Yii::$app->request;
		Yii::debug($request->rawBody, __METHOD__);
		$ts = $request->get('timestamp');
		$nonce = $request->get('nonce');
		$signature = $request->get('signature');
		$this->verifyToken($ts, $nonce, $signature);
		if ($request->isGet) {
			return $request->get('echostr');
		}
		$to = $request->post('ToUserName');
		if (!empty($this->acceptor) && !in_array($to, $this->acceptor)) {
			Yii::warning("We do not listen the message for '$to', skip", __METHOD__);
			return 'success';
		}
		$config = $request->post();
		$plain = array_key_exists('FromUserName', $config);
		if (!$plain) {
			if (empty($config['Encrypt'])) {
				Yii::info("Neither FromUserName nor Encrypt is found in post body", __METHOD__);
				throw new MessageParamException("Neither FromUserName nor Encrypt is found in post body");
			}
			$signature = $request->get('msg_signature');
			$config = $this->decrypt($config['Encrypt'], $nonce, $ts, $signature);
		}
		$event = WechatEvent::create($config);
		$this->trigger($event->eventName(), $event);
		if (empty($event->response)) {
			return 'success';
		}
		$this->response->format = Response::FORMAT_XML;
		$res = array_merge([
			'ToUserName' => $event->from,
			'FromUserName' => $event->to,
			'CreateTime' => time(),
		], $event->response);
		Yii::trace($res, __METHOD__);
		if ($plain) {
			return $res;
		}
		$encrypted = $this->encrypt($res);
		if ($encrypted === false) {
			return $res;
		}
		return $encrypted;
	}

	private function verifyToken(int $ts, string $nonce, string $signature): void
	{
		$now = time();
		if ($this->tolrence > 0 && abs($now - $ts) > $this->tolrence) {
			$now = date('Y-m-d H:i:s', $now);
			$ts = date('Y-m-d H:i:s', $ts);
			Yii::error("timestamp error: current [$now], remote [$ts]", __METHOD__);
			throw new SignatureException("timestamp error");
		}
		if ($this->calcSHA1($nonce, $ts) !== $signature) {
			Yii::error('signature error', __METHOD__);
			throw new SignatureException("signature mismatch");
		}
	}

	private function calcSHA1(string $nonce, int $timestamp, ?string $data = null): string
	{
		$arr = [$nonce, $timestamp, $this->wechat->msg_token];
		if ($data !== null) {
			$arr[] = $data;
		}
		sort($arr, SORT_STRING);
		return sha1(implode($arr));
	}

	private function decrypt(string $encrypted, string $nonce, int $ts, string $msg_signature): array
	{
		if ($this->calcSHA1($nonce, $ts, $encrypted) != $msg_signature) {
			Yii::error("Encrypted message signature mismatch", __METHOD__);
			throw new SignatureException("Encrypted message signature mismatch");
		}
		$key = base64_decode($this->wechat->msg_aes_key);
		$iv = substr($key, 0, 16);
		$decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_ZERO_PADDING, $iv);
		if ($decrypted === false) {
			Yii::error("decrypt data failed: $encrypted", __METHOD__);
			throw new MessageCryptoException("decrypt data failed");
		}
		$decrypted = substr($decrypted, 0, -ord(substr($decrypted, -1)));
		$len = unpack('N', substr($decrypted, 16, 4))[1];
		$content = substr($decrypted, 20, $len);
		$result = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
		if ($result === false) {
			Yii::error("load xml data from decrypted data failed: $content", __METHOD__);
			Yii::error(libxml_get_errors(), __METHOD__);
			throw new MessageParamException("load xml data from decrypted data failed");
		}
		$this->appid = substr($decrypted, 20 + $len);
		return json_decode(json_encode($result), true);
	}

	private function encrypt(array $input): array
	{
		$xml = new SimpleXMLElement('<xml></xml>');
		$this->arr2xml($input, $xml);
		$xmlStr = preg_replace('/^<\?xml [^\?]*\?{1}>\s*/', '', $xml->asXML());
		$text = $this->randomString(16) . pack('N', strlen($xmlStr)) . $xmlStr . $this->appid;
		$key = base64_decode($this->wechat->msg_aes_key);
		$iv = substr($key, 0, 16);
		$paddingLength = 32 - strlen($text) % 32;
		$text .= str_repeat(chr($paddingLength), $paddingLength);
		$encrypted = openssl_encrypt($text, 'aes-256-cbc', $key, OPENSSL_ZERO_PADDING, $iv);
		if ($encrypted === false) {
			Yii::error("Encrypt data failed: $text", __METHOD__);
			throw new MessageCryptoException("encrypt data failed");
		}
		$res = [
			'Encrypt' => $encrypted,
			'TimeStamp' => time(),
			'Nonce' => $this->randomString(16),
		];
		$res['MsgSignature'] = $this->calcSHA1($res['Nonce'], $res['TimeStamp'], $encrypted);
		return $res;
	}

	private function arr2xml(array $arr, SimpleXMLElement &$xml): void
	{
		foreach ($arr as $k => $v) {
			if (is_numeric($k)) {
				$k = 'item';
			}
			if (is_array($v)) {
				$subnode = $xml->addChild($k);
				$this->arr2xml($v, $subnode);
			} else {
				$xml->addchild($k, htmlspecialchars($v));
			}
		}
	}

	private function randomString(int $len): string
	{
		return substr(base64_encode(openssl_random_pseudo_bytes(ceil($len * 0.75))), 0, $len);
	}
}

