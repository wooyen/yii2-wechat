<?php
namespace yii\wechat\events;

class LocationMsgEvent extends MessageEvent
{
	public $location_X;
	public $location_Y;
	public $scale;
	public $label;
}
