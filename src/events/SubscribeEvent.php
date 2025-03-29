<?php
namespace yii\wechat\events;

class SubscribeEvent extends WechatEvent
{
	use SceneTrait;
	public function eventName()
	{
		return 'wechat.subscribe';
	}
}
