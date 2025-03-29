<?php
namespace yii\wechat\events;

use yii\wechat\Wechat;

class ScanEvent extends WechatEvent
{
	use SceneTrait;
	public function eventName(): string
	{
		return Wechat::EVENT_SCAN;
	}
}
