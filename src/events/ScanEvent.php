<?php
namespace yii\wechat\events;

class ScanEvent extends WechatEvent
{
	use SceneTrait;
	public function eventName()
	{
		return 'wechat.scan';
	}
}
