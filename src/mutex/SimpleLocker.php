<?php

namespace yii\wechat\mutex;

use yii\mutex\Mutex;

class SimpleLocker
{
	public function __construct(
		private Mutex $mutex,
		private string $key,
	) {
		if (!$this->mutex->acquire($this->key)) {
			throw new LockFail($this->key);
		}
	}
	public function __destruct()
	{
		$this->mutex->release($this->key);
	}
}