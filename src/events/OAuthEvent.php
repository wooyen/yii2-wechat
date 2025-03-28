<?php
namespace yii\wechat\events;

use yii\base\Event;

class OAuthEvent extends Event
{
	public const EVENT_NAME = 'wechat_oauth';
	public $openid;
	public $unionid;
	public function __construct($openid, $unionid)
	{
		$this->openid = $openid;
		$this->unionid = $unionid;
	}
}
