<?php

namespace yii\wechat\actions;

use Yii;
use yii\base\Action;
use yii\web\Response;

class LoginQrTicketAction extends Action
{
	public $keyPrefix = 'wx_scene_login_';
	public $expireTime = 60;
	public function run()
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		$scene = Yii::$app->security->generateRandomString(32);
		$cacheKey = $this->keyPrefix . $scene;
		Yii::$app->cache->set($cacheKey, [
			'status' => 'waiting',  // waiting, scanned, success
			'created_at' => time(),
		], $this->expireTime);
		$ret = Yii::$app->wechat->createQrCode($scene, $this->expireTime);
		return [
			'code' => 0,
			'message' => 'Create QR code success',
			'data' => [
				'scene' => $scene,
				'ticket' => $ret['ticket'],
				'expire_at' => time() + $ret['expire_seconds'],
			],
		];
	}
}
