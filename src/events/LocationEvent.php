<?php
namespace yii\wechat\events;

use yii\wechat\Wechat;

class LocationEvent extends WechatEvent
{
	public $latitude;
	public $longitude;
	public $precision;
	public function eventName(): string
	{
		return Wechat::EVENT_LOCATION;
	}
}
