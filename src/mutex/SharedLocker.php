<?php

namespace wechat\mutex;

use yii\wechat\mutex\SharedMutex;
use yii\wechat\mutex\UnsupportedException;

class SharedLocker
{
	public function __construct(
		private SharedMutex $mutex,
		private string $key,
	) {
		try {
			$this->mutex->acquireShared($this->key);
		} catch (UnsupportedException $e) {
			$this->mutex->acquire($this->key);
		}
	}


}
