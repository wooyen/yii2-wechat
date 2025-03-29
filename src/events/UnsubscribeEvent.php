<?php
namespace yii\wechat\events;

class UnsubscribeEvent extends WechatEvent
{
	public function eventName()
	{
		return 'wechat.unsubscribe';
	}
}
