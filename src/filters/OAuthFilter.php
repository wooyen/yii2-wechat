<?php

namespace yii\wechat\filters;

use Yii;
use yii\wechat\Wechat;
use yii\wechat\events\OAuthEvent;
use yii\base\ActionFilter;
use yii\di\Instance;

class OAuthFilter extends ActionFilter
{
	public $wechat = 'wechat';
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
	}
	public function beforeAction($action)
	{
		if ($this->wechat->isWechat()) {
			$userInfo = Yii::$app->request->cookies->getValue($this->wechat->getOauthCookieName());
			if (!empty($userInfo)) {
				$this->trigger(OAuthEvent::EVENT_NAME, new OAuthEvent($userInfo['openid'], $userInfo['unionid']));
				return parent::beforeAction($action);
			}
			$this->wechat->authRequired();
		}
		return parent::beforeAction($action);
	}
}
