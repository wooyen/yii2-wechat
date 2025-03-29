<?php
namespace yii\wechat\events;

class VoiceMsgEvent extends MessageEvent
{
	public $format;
	public $mediaId;
	public $mediaId16K;
}
