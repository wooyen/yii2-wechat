<?php

namespace yii\wechat\utils;

interface Condition
{
	public function signal(string $key, $data);
	public function wait(string $key);
}
