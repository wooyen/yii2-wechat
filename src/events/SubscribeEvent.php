<?php
namespace yii\wechat\events;

use yii\wechat\Wechat;

class SubscribeEvent extends WechatEvent
{
	use SceneTrait;
	public function eventName(): string
	{
		return Wechat::EVENT_SUBSCRIBE;
	}
}
