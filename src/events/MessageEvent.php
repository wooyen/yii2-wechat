<?php
namespace yii\wechat\events;

abstract class MessageEvent extends WechatEvent
{
	public $msgId;
	public $msgDataId;
	public $idx;
	public function eventName()
	{
		return 'wechat.message';
	}
}
