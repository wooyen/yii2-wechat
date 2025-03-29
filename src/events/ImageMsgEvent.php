<?php
namespace yii\wechat\events;

class ImageMsgEvent extends MessageEvent
{
	public $picUrl;
	public $mediaId;
}
