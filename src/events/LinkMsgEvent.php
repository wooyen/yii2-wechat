<?php
namespace yii\wechat\events;

class LinkMsgEvent extends MessageEvent
{
	public $title;
	public $description;
	public $url;
}
