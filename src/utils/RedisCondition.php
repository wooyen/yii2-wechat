<?php

namespace yii\wechat\utils;

use Yii;
use yii\base\BaseObject;
class RedisCondition extends BaseObject implements Condition
{
	public $redis = 'redis';
	public function signal(string $key, $data)
	{
		$redis = Yii::$app->get($this->redis);
		$redis->lpush($key, $data);
	}

	public function wait(string $key)
	{
		$redis = Yii::$app->get($this->redis);
		$timeout = ini_get('max_execution_time') * 0.8;
		$result = $redis->brpop($key, $timeout);
		if ($result) {
			return $result[1];
		}
		return false;
	}
}

