<?php
namespace yii\wechat\events;

abstract class BaseVideoMsgEvent extends MessageEvent
{
	public $mediaId;
	public $thumbMediaId;
}
