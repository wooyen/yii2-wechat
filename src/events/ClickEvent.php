<?php
namespace yii\wechat\events;

use yii\wechat\Wechat;

class ClickEvent extends WechatEvent
{
	public $eventKey;
	public function eventName(): string
	{
		return Wechat::EVENT_CLICK;
	}
}
