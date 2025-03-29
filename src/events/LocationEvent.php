<?php
namespace yii\wechat\events;

class LocationEvent extends WechatEvent
{
	public $latitude;
	public $longitude;
	public $precision;
}
