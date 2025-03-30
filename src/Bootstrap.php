<?php
use yii\base\BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
	public function bootstrap($app)
	{
		Yii::setAlias('@wechat', dirname(__DIR__));
	}
}
