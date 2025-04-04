<?php

namespace yii\wechat\utils;

use Yii;
use yii\base\BaseObject;
use yii\di\Instance;
use yii\redis\Connection;
class RedisCondition extends BaseObject implements Condition
{
	public $redis = 'redis';
	private $_redis;
	public function init()
	{
		parent::init();
		$this->_redis = Instance::ensure($this->redis, Connection::class);
	}
	public function signal(string $key, $data)
	{
		Yii::debug("signal: $key, " . json_encode($data), __METHOD__);
		$this->_redis->lpush($key, $data);
	}

	public function wait(string $key)
	{
		$timeout = ini_get('max_execution_time') * 0.8;
		Yii::debug("waiting $key for $timeout seconds", __METHOD__);
		$result = $this->_redis->brpop($key, $timeout);
		Yii::debug("result: " . json_encode($result), __METHOD__);
		if ($result) {
			return $result[1];
		}
		return false;
	}
	public function __sleep()
	{
		return ['redis'];
	}
	public function __wakeup()
	{
		$this->init();
	}
}

