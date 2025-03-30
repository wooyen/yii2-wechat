<?php

namespace yii\wechat\utils;

use yii\base\BaseObject;

/**
 * 空UnionID映射器
 */
class DummyUnionIDMapper extends BaseObject implements UnionIDMapper
{
	public function getUnionID(string $openid): string
	{
		return false;
	}
	public function getOpenID(string $unionid, string $appid): string
	{
		return false;
	}
	public function attachUnionID(string $openid, string $unionid, string $appid): void
	{
	}

}