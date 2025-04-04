<?php

namespace yii\wechat\actions;

use Closure;
use Yii;
use yii\base\Action;
use yii\caching\Cache;
use yii\di\Instance;
use yii\web\Response;
use yii\wechat\Wechat;

class LoginQrTicketAction extends Action
{
	public const CACHE_KEY_PREFIX = 'wechat_scene_login_';
	public const WAITING_PIPE_KEY_PREFIX = 'wechat_scene_login_waiting_pipe_';
	public const CACHE_TTL_AFTER_SCAN = 300;
	public $wechat = 'wechat';
	public $cache = 'cache';
	public $expireTime = 60;
	public $condition;
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
		$cacheKey = self::CACHE_KEY_PREFIX . $scene;
		$this->cache->set($cacheKey, [
			'status' => 'waiting',  // waiting, scanned, success
			'condition' => $this->condition,
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
	public static function scaned(string $scene, $user_id, Cache $cache)
	{
		$cacheKey = self::CACHE_KEY_PREFIX . $scene;
		Yii::debug('scaned: ' . $cacheKey, __METHOD__);
		$data = $cache->get($cacheKey);
		if ($data === false) {
			return false;
		}
		Yii::debug($data, __METHOD__);
		if ($data['status'] !== 'waiting') {
			return false;
		}
		$data['status'] = 'scanned';
		$data['user_id'] = $user_id;
		$cache->set($cacheKey, $data, self::CACHE_TTL_AFTER_SCAN);
		Yii::debug("save scanned data in $cacheKey: " . json_encode($data), __METHOD__);
		if ($data['condition'] && $data['condition'] instanceof LoginSceneCondition) {
			$data['condition']->signal(self::WAITING_PIPE_KEY_PREFIX . $scene, $user_id);
		}
		return true;
	}
	public static function check(string $scene, Cache $cache, bool $waitCb = true)
	{
		$cacheKey = self::CACHE_KEY_PREFIX . $scene;
		for ($i = 0; $i < 2; ++$i) {
			$data = $cache->get($cacheKey);
			Yii::debug("check $cacheKey: " . json_encode($data), __METHOD__);
			if ($data === false) {
				return false;
			}
			$cond = $data['condition'] ?? null;
			unset($data['condition']);
			if ($data['status'] === 'scanned') {
				$cache->delete($cacheKey);
				return $data;
			}
			if ($i > 0 || !$cond || $cond instanceof LoginSceneCondition) {
				return $data;
			}
			Yii::debug("wait $cacheKey", __METHOD__);
			$cond->wait(self::WAITING_PIPE_KEY_PREFIX . $scene);
		}
		return $data;
	}
}
