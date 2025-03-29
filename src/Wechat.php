<?php

namespace yii\wechat;

use JsonException;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\di\Instance;
use yii\helpers\Json;
use yii\mutex\Mutex;
use yii\web\Response;
use yii\wechat\mutex\SimpleLocker;

class Wechat extends Component
{
	public const OAUTH_TYPE_BASE = 'snsapi_base';
	public const OAUTH_TYPE_USERINFO = 'snsapi_userinfo';
	public const EVENT_ACCESS_TOKEN_REFRESHED = 'access_token_refreshed';
	public const EVENT_MESSAGE = 'wechat.message';
	public const EVENT_SUBSCRIBE = 'wechat.subscribe';
	public const EVENT_UNSUBSCRIBE = 'wechat.unsubscribe';
	public const EVENT_SCAN = 'wechat.scan';
	public const EVENT_LOCATION = 'wechat.location';
	public const EVENT_CLICK = 'wechat.click';
	public const EVENT_VIEW = 'wechat.view';
	public const EVENT_OAUTH = 'wechat.oauth';
	public const MESSAGE_TEXT = 'text';
	public const MESSAGE_IMAGE = 'image';
	public const MESSAGE_VOICE = 'voice';
	public const MESSAGE_VIDEO = 'video';
	public const MESSAGE_SHORTVIDEO = 'shortvideo';
	public const MESSAGE_LOCATION = 'location';
	public const MESSAGE_LINK = 'link';
	private const OAUTH_COOKIE_NAME = '__oauth_info';
	private const OAUTH_TOKEN_CACHE_KEY_BY_OPENID = '__oauth_token_by_openid';
	private const OAUTH_TOKEN_CACHE_KEY_BY_UNIONID = '__oauth_token_by_unionid';
	private const OAUTH_TOKEN_TTL = 30 * 24 * 60 * 60;
	public const ENDPOINTS = [
		'https://api.weixin.qq.com',
		'https://api2.weixin.qq.com',
	];
	private $_access_token_cache_key;
	private $_jsapi_ticket_cache_key;
	private $_access_token_lock_key;
	public $appId;
	public $appSecret;
	public $msgToken;
	public $msgAesKey;
	public $httpProxy;
	public $httpProxyAuth;
	public $refreshTokenTimeBuffer = 300;
	public $cache = 'cache';
	public $mutex = 'mutex';
	public function init()
	{
		parent::init();
		if (empty($this->appId) || empty($this->appSecret)) {
			throw new InvalidConfigException('appId and appSecret are required');
		}
		if ($this->refreshTokenTimeBuffer <= 0) {
			Yii::warning('The time buffer for refreshing access token can not be negative, using default value 300', __METHOD__);
			$this->refreshTokenTimeBuffer = 300;
		}
		$this->cache = Instance::ensure($this->cache, Cache::class);
		$this->mutex = Instance::ensure($this->mutex, Mutex::class);
		$this->_access_token_cache_key = md5(serialize([__CLASS__, $this->appId, 'access_token']));
		$this->_jsapi_ticket_cache_key = md5(serialize([__CLASS__, $this->appId, 'jsapi_ticket']));
		$this->_access_token_lock_key = md5(serialize([__CLASS__, $this->appId, 'access_token_lock']));
	}
	public function isWechat(): bool
	{
		return strpos(Yii::$app->request->userAgent, 'MicroMessenge') !== false;
	}
	public function authRequired(string $returnUrl = null, string $type = self::OAUTH_TYPE_USERINFO, string $state = '')
	{
		if (empty($returnUrl)) {
			$returnUrl = Yii::$app->request->absoluteUrl;
		}
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize';
		$url .= '?appid=' . urlencode($this->appid);
		$url .= '&redirect_uri=' . urlencode($returnUrl);
		$url .= '&response_type=code&scope=' . urlencode($type);
		$url .= '&state=' . urlencode($state);
		$url .= '#wechat_redirect';
		Yii::trace("Redirecting to: $url", __METHOD__);
		Yii::$app->response->redirect($url)->send();
	}
	public function getOauthInfoFromCode(string $code): array
	{
		$res = $this->curl('/sns/oauth2/access_token', [
			'appid' => $this->appid,
			'secret' => $this->appSecret,
			'code' => $code,
			'grant_type' => 'authorization_code',
		], false);
		$openid = $res['openid'];
		$unionid = $res['unionid'];
		$res['access_token_expire'] = time() + $res['expires_in'];
		$res['refresh_token_expire'] = time() + self::OAUTH_TOKEN_TTL;
		unset($res['expires_in']);
		$key = [__CLASS__, $this->appId, self::OAUTH_TOKEN_CACHE_KEY_BY_OPENID, $openid];
		$this->cache->set($key, $res, self::OAUTH_TOKEN_TTL);
		if (!empty($unionid)) {
			$key = [__CLASS__, $this->appId, self::OAUTH_TOKEN_CACHE_KEY_BY_UNIONID, $unionid];
			$this->cache->set($key, $res, self::OAUTH_TOKEN_TTL);
		}
		return $res;
	}
	public function getAccessToken(): string
	{
		$res = $this->cache->get($this->_access_token_cache_key);
		if ($res === false || $res['expired_at'] < time()) {
			return $this->updateAccessToken();
		}
		if ($res['expired_at'] - time() < $this->refreshTokenTimeBuffer) {
			Yii::$app->response->on(Response::EVENT_AFTER_SEND, function () {
				$this->updateAccessToken();
			});
		}
		return $res['token'];
	}
	public function updateAccessToken(): string
	{
		$locker = new SimpleLocker($this->mutex, $this->_access_token_lock_key);
		$res = $this->cache->get($this->_access_token_cache_key);
		if ($res !== false && $res['expired_at'] - time() > $this->refreshTokenTimeBuffer * 2) {
			return $res['token'];
		}
		$res = $this->curl('/cgi-bin/token', [
			'grant_type' => 'client_credential',
			'appid' => $this->appId,
			'secret' => $this->appSecret,
		], false);
		$result = [
			'token' => $res['access_token'],
			'expired_at' => time() + $res['expires_in'],
		];
		$this->cache->set($this->_access_token_cache_key, $result, $res['expires_in'] - 10);
		return $result['token'];
	}
	public function refreshAccessToken(string $refresh_key): array
	{
		$res = $this->curl('/sns/oauth2/refresh_token', [
			'appid' => $this->appid,
			'refresh_token' => $refresh_key,
			'grant_type' => 'refresh_token',
		], false);
		return [$res['access_token'], time() + $res['expires_in']];
	}
	public function jsSignPackage()
	{
		$ticket = $this->getjsapiTicket();
		if ($ticket == false) {
			return false;
		}
		$arr = [
			'jsapi_ticket' => $ticket,
			'timestamp' => time(),
			'noncestr' => Yii::$app->security->generateRandomString(32),
			'url' => Yii::$app->request->absoluteUrl,
		];
		ksort($arr);
		$signStr = implode('&', array_map(function ($k, $v) {
			return "$k=$v";
		}, array_keys($arr), $arr));
		$arr['signature'] = sha1($signStr);
		$arr['appId'] = $this->appid;
		$arr['nonceStr'] = $arr['noncestr'];
		unset($arr['jsapi_ticket']);
		unset($arr['noncestr']);
		return $arr;
	}
	public function getJsapiTicket()
	{
		$ticket = $this->cache->get($this->_jsapi_ticket_cache_key);
		if ($ticket === false) {
			$res = $this->curl('/cgi-bin/ticket/getticket', [
				'type' => 'jsapi',
			]);
			$ticket = $res['ticket'];
			Yii::trace("ticket from wechat server: $ticket", __METHOD__);
			$this->cache->set($this->_jsapi_ticket_cache_key, $ticket, $res['expires_in'] - 10);
		}
		return $ticket;
	}
	public function createMenu(array $menu)
	{
		return $this->curlPostJson('/cgi-bin/menu/create', $menu);
	}
	public function getMenu()
	{
		return $this->curl('/cgi-bin/menu/get');
	}
	public function deleteMenu()
	{
		return $this->curl('/cgi-bin/menu/delete');
	}
	public function getMenuConfig()
	{
		return $this->curl('/cgi-bin/get_current_selfmenu_info');
	}
	public function createTag(string $name)
	{
		$this->curlPostJson('/cgi-bin/tags/create', [
			'tag' => [
				'name' => $name,
			],
		]);
	}
	public function getTags()
	{
		$ret = $this->curl('/cgi-bin/tags/get');
		return $ret['tags'];
	}
	public function updateTag(int $id, string $name)
	{
		$this->curlPostJson('/cgi-bin/tags/update', [
			'tag' => [
				'id' => $id,
				'name' => $name,
			],
		]);
	}
	public function deleteTag(int $id)
	{
		$this->curlPostJson('/cgi-bin/tags/delete', [
			'tag' => [
				'id' => $id,
			],
		]);
	}
	public function getUsersByTag(int $id, &$last)
	{
		$ret = $this->curlPostJson('/cgi-bin/user/tag/get', [
			'tagid' => $id,
			'next_openid' => $last,
		]);
		$last = $ret['next_openid'];
		return $ret['data']['openid'];
	}
	public function tagUsers(array $users, int $tagid)
	{
		$this->curlPostJson('/cgi-bin/tags/members/batchtagging', [
			'openid_list' => $users,
			'tagid' => $tagid,
		]);
	}
	public function untagUsers(array $users, int $tagid)
	{
		$this->curlPostJson('/cgi-bin/tags/members/batchuntagging', [
			'openid_list' => $users,
			'tagid' => $tagid,
		]);
	}
	public function getTagsOfUser(string $user)
	{
		$ret = $this->curlPostJson('/cgi-bin/tags/getidlist', [
			'openid' => $user,
		]);
		return $ret['tagid_list'];
	}
	public function createQrCode($scene, int $expire = 0)
	{
		if (is_int($scene)) {
			$key = 'scene_id';
			$right = 'SCENE';
		} else {
			$key = 'scene_str';
			$right = 'STR_SCENE';
		}
		if ($expire > 0) {
			$left = 'QR_';
		} else {
			$left = 'QR_LIMIT_';
		}
		$data = [
			'action_name' => $left . $right,
			'action_info' => [
				'scene' => [
					$key => $scene,
				],
			],
		];
		if ($expire > 0) {
			$data['expire_seconds'] = $expire;
		}
		$ret = $this->curlPostJson('/cgi-bin/qrcode/create', $data);
		$ret['img_url'] = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ret['ticket']);
		return $ret;
	}
	public function listMaterial(string $type, int $offset = 0, int $count = 20)
	{
		return $this->curlPostJson('/cgi-bin/material/batchget_material', [
			'type' => $type,
			'offset' => $offset,
			'count' => $count,
		]);
	}
	public function getPermanentMaterial(string $id)
	{
		return $this->curlPostJson('/cgi-bin/material/get_material', [
			'media_id' => $id,
		]);
	}
	public function addCSR(string $account, string $nick, string $passwd)
	{
		return $this->curlPostJson('/customservice/kfaccount/add', [
			'kf_account' => $account,
			'nickname' => $nick,
			'password' => $passwd,
		]);
	}
	public function listCustomerServicer()
	{
		return $this->curl('/cgi-bin/customservice/getkflist');
	}
	public function sendMessage(string $openid, string $type, array $content)
	{
		return $this->curlPostJson('/cgi-bin/message/custom/send', [
			'touser' => $openid,
			'msgtype' => $type,
			$type => $content,
		]);
	}
	private function curlPostJson(string $api, $data, array $params = null, bool $withToken = true)
	{
		return $this->curl($api, $params, $withToken, function ($ch) use ($data) {
			$body = Json::encode($data);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Content-Length: ' . strlen($body),
			]);
		});
	}
	private function curl(string $api, array $params = null, $withToken = true, $extra = null)
	{
		if ($withToken) {
			$params['access_token'] = $this->getAccessToken();
		}
		if (!empty($params)) {
			$api .= '?' . implode('&', array_map(fn($k, $v) => urlencode($k) . '=' . urlencode($v), array_keys($params), $params));
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if (!empty($this->http_proxy)) {
			curl_setopt($ch, CURLOPT_PROXY, $this->http_proxy);
			if (!empty($this->http_proxy_auth)) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->http_proxy_auth);
			}
		}
		if (is_callable($extra)) {
			$extra($ch);
		}
		foreach (self::ENDPOINTS as $k => $ep) {
			$url = "{$ep}{$api}";
			curl_setopt($ch, CURLOPT_URL, $url);
			Yii::trace("curl request: {$url}", __METHOD__);
			$result = curl_exec($ch);
			if ($result === false) {
				Yii::error("Failed to access wechat server $ep: " . curl_error($ch), __METHOD__);
				continue;
			}
			Yii::trace("curl returns: {$result}", __METHOD__);
			$responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			if ($responseCode >= 400) {
				Yii::error("Response code from wechat server is {$responseCode}.", __METHOD__);
				continue;
			}
			$res = Json::decode($result, true);
			if ($res === null) {
				Yii::error("The response from wechat server is not in correct format: {$result}", __METHOD__);
				throw new ServerResponseException($result);
			}
			if (!empty($res['errcode'])) {
				Yii::error("Wechat returns error: [{$res['errcode']}] {$res['errmsg']}.", __METHOD__);
				throw new ServerReturnError($res['errcode'], $res['errmsg']);
			}
			return $res;
		}
		Yii::error('Failed to access wechat server', __METHOD__);
		throw new ServerNotAvailableError();
	}
}