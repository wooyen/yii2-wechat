<?php
namespace yii\wechat\events;

use yii\wechat\Wechat;

class UnsubscribeEvent extends WechatEvent
{
	public function eventName(): string
	{
		return Wechat::EVENT_UNSUBSCRIBE;
	}
}
