<?php

namespace yii\wechat\mutex;

use yii\base\Exception;

class LockException extends Exception
{
	public function __construct(string $key, int $code = 0, \Throwable $previous = null)
	{
		parent::__construct("Failed to acquire mutex: $key", $code, $previous);
	}
}