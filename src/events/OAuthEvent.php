<?php
namespace yii\wechat\events;

use yii\base\Event;

class OAuthEvent extends Event
{
	public const EVENT_NAME = 'wechat_oauth';
	public $openid;
	public $unionid;
	public $scope;
	public $is_snapshotuser;
	public $access_token;
	public $refresh_token;
	public $access_token_expire;
	public $refresh_token_expire;
}
