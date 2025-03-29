<?php
namespace yii\wechat\events;

use yii\wechat\Wechat;

abstract class MessageEvent extends WechatEvent
{
	public $msgId;
	public $msgDataId;
	public $idx;
	public function eventName(): string
	{
		return Wechat::EVENT_MESSAGE;
	}
}
