<?php
namespace yii\wechat;

use Yii;
use yii\base\BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
	public function bootstrap($app)
	{
		Yii::setAlias('@wechat', __DIR__);
	}
}
