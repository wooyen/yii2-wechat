<?php
namespace yii\wechat\events;

class BaseVideoMsgEvent extends MessageEvent
{
	public $mediaId;
	public $thumbMediaId;
}
