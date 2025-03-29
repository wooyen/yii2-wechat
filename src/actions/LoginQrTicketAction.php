<?php

namespace yii\wechat\actions;

use Yii;
use yii\base\Action;
use yii\di\Instance;
use yii\web\Response;
use yii\wechat\Wechat;

class LoginQrTicketAction extends Action
{
	public const LOGIN_SCENE_CACHE_KEY_PREFIX = 'wechat_scene_login_';
	public $wechat = 'wechat';
	public $cache = 'cache';
	public $expireTime = 60;
	public function init()
	{
		parent::init();
		if ($this->expireTime < 10) {
			$this->expireTime = 10;
		} elseif ($this->expireTime > 300) {
			$this->expireTime = 300;
		}
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
		$this->cache = Instance::ensure($this->cache, Cache::class);
	}
	public function run()
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		$scene = Yii::$app->security->generateRandomString(32);
		$cacheKey = self::LOGIN_SCENE_CACHE_KEY_PREFIX . $scene;
		$this->cache->set($cacheKey, [
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
