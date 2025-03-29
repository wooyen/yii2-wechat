<?php
namespace yii\wechat\events;

class ClickEvent extends WechatEvent
{
	public $eventKey;
	public function eventName()
	{
		return 'wechat.click';
	}
}
