<?php

namespace yii\wechat\utils;

/**
 * UnionID与OpenID映射关系
 */
interface UnionIDMapper
{
	/**
	 * 获取UnionID
	 * @param string $openid
	 * @return string|bool
	 */
	public function getUnionID(string $openid): string|bool;
	/**
	 * 获取OpenID
	 * @param string $unionid
	 * @param string $appid
	 * @return string|bool
	 */
	public function getOpenID(string $unionid, string $appid): string|bool;
	/**
	 * 关联UnionID与OpenID
	 * @param string $openid
	 * @param string $unionid
	 * @param string $appid
	 * @return void
	 */
	public function attachUnionID(string $openid, string $unionid, string $appid): void;
}
