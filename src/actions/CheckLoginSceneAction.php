<?php

namespace yii\wechat\actions;

use Yii;
use yii\base\Action;
use yii\caching\Cache;
use yii\di\Instance;
use yii\redis\Connection;
use yii\web\Response;
class CheckLoginSceneAction extends Action
{
	public $cache = 'cache';
	public function init()
	{
		parent::init();
		$this->cache = Instance::ensure($this->cache, Cache::class);
		if (!empty($this->redis)) {
			$this->redis = Instance::ensure($this->redis, Connection::class);
		}
	}
	public function run()
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		$scene = Yii::$app->request->get('scene');
		$data = LoginQrTicketAction::check($scene, $this->cache);
		if (!$data) {
			return [
				'code' => 1,
				'message' => 'The Qr code has expired',
			];
		}
		if ($data['status'] === 'scanned') {
			$identityClass = Yii::$app->user->identityClass;
			$identity = $identityClass::findIdentity($data['user_id']);
			if (!$identity) {
				return [
					'code' => 2,
					'message' => 'The user does not exist',
				];
			}
			Yii::$app->user->login($identity);
		}
		return [
			'code' => 0,
			'message' => 'success',
			'data' => [
				'status' => $data['status'],
			]
		];
	}
}
