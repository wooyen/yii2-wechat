<?php
namespace yii\wechat\events;

use yii\base\Event;
use yii\wechat\UnknownEventException;
use yii\wechat\UnknownMessageException;
use yii\wechat\Wechat;

abstract class WechatEvent extends Event
{
	public $fromUserName;
	public $toUserName;
	public $createTime;
	public $response;
	protected static function normalizeConfig($config)
	{
		return array_reduce(array_keys($config), function ($acc, $key) use ($config) {
			$new_key = strtolower(substr($key, 0, 1)) . substr($key, 1);
			$acc[$new_key] = $config[$key];
			return $acc;
		}, []);
	}
	public static function create($config)
	{
		$config = self::normalizeConfig($config);
		if ($config['msgType'] != 'event') {
			return self::createMessageEvent($config);
		}
		switch ($config['event']) {
			case 'subscribe':
				if (!empty($config['eventKey'])) {
					$config['eventKey'] = substr($config['eventKey'], 8);
				}
				return new SubscribeEvent($config);
			case 'unsubscribe':
				return new UnsubscribeEvent($config);
			case 'SCAN':
				return new ScanEvent($config);
			case 'LOCATION':
				return new LocationEvent($config);
			case 'CLICK':
				return new ClickEvent($config);
			case 'VIEW':
				return new ViewEvent($config);
			default:
				throw new UnknownEventException($config['event']);
		}
	}

	private static function createMessageEvent($config)
	{
		switch ($config['msgType']) {
			case Wechat::MESSAGE_TEXT:
				return new TextMsgEvent($config);
			case Wechat::MESSAGE_IMAGE:
				return new ImageMsgEvent($config);
			case Wechat::MESSAGE_VOICE:
				return new VoiceMsgEvent($config);
			case Wechat::MESSAGE_VIDEO:
				return new VideoMsgEvent($config);
			case Wechat::MESSAGE_SHORTVIDEO:
				return new ShortVideoMsgEvent($config);
			case Wechat::MESSAGE_LOCATION:
				return new LocationMsgEvent($config);
			case Wechat::MESSAGE_LINK:
				return new LinkMsgEvent($config);
			default:
				throw new UnknownMessageException($config['MsgType']);
		}
	}

	public function setResponseContent($content, $force = true)
	{
		if (!$force && !empty($this->response)) {
			return false;
		}
		$this->response = [
			'MsgType' => Wechat::MESSAGE_TEXT,
			'Content' => $content,
		];
		return true;
	}

	public function setResponseImage($mediaId, $force = true)
	{
		if (!$force && !empty($this->response)) {
			return false;
		}
		$this->response = [
			'MsgType' => Wechat::MESSAGE_IMAGE,
			'Image' => [
				'MediaId' => $mediaId,
			],
		];
		return true;
	}

	public function setResponseVoice($mediaId, $force = true)
	{
		if (!$force && !empty($this->response)) {
			return false;
		}
		$this->response = [
			'MsgType' => Wechat::MESSAGE_VOICE,
			'Voice' => [
				'MediaId' => $mediaId,
			],
		];
		return true;
	}

	public function setResponseVideo($mediaId, $title, $desc, $force = true)
	{
		if (!$force && !empty($this->response)) {
			return false;
		}
		$this->response = [
			'MsgType' => Wechat::MESSAGE_VIDEO,
			'Video' => [
				'MediaId' => $mediaId,
				'Title' => $title,
				'Description' => $desc,
			],
		];
		return true;
	}

	public function setResponseMusic($title, $desc, $url, $hqUrl, $thumbId, $force = true)
	{
		if (!$force && !empty($this->response)) {
			return false;
		}
		$this->response = [
			'MsgType' => 'music',
			'Music' => [
				'Title' => $title,
				'Description' => $desc,
				'MusicUrl' => $url,
				'HQMusicUrl' => $hqUrl,
				'ThumbMediaId' => $thumbId,
			],
		];
		return true;
	}

	public function setResponseArticles($articles, $force = true)
	{
		if (!$force && !empty($this->response)) {
			return false;
		}
		$this->response = [
			'MsgType' => 'news',
			'ArticleCount' => count($articles),
			'Articles' => $articles,
		];
		return true;
	}
}
