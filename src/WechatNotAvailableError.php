<?php
namespace yii\wechat;

use Yii;

class WechatNotAvailableError extends WechatException
{
	public function __construct()
	{
		parent::__construct(Yii::t('wechat', 'Failed to access wechat server'));
	}
}
