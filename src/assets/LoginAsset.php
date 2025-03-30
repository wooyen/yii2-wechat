<?php

namespace yii\wechat\assets;

use yii\web\AssetBundle;

class LoginAsset extends AssetBundle
{
	public $sourcePath = '@wechat/assets';
	public $js = [
		'js/login.js',
	];
}
