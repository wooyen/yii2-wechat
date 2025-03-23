<?php
namespace yii\wechat;

use Yii;

class WechatUnknowEventException extends WechatException
{

	public $type;

	public function __construct(string $type)
	{
		parent::__construct(Yii::t('wechat', 'Unknown event type {type}.', ['type' => $type]));
		$this->type = $type;
	}
}
