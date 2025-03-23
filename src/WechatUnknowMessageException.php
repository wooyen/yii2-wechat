<?php
namespace yii\wechat;

use Yii;

class WechatUnknowMessageException extends WechatException
{

	public $type;

	public function __construct(string $type)
	{
		parent::__construct(Yii::t('wechat', 'Unknown message type {type}.', ['type' => $type]));
		$this->type = $type;
	}
}
