<?php
namespace yii\wechat;

use Yii;

class ServerNotAvailableError extends Exception
{
	public function __construct()
	{
		parent::__construct(Yii::t('wechat', 'Failed to access wechat server'));
	}
}
