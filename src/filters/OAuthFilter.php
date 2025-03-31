<?php

namespace yii\wechat\filters;

use Yii;
use yii\wechat\Wechat;
use yii\wechat\events\OAuthEvent;
use yii\base\ActionFilter;
use yii\di\Instance;
use yii\helpers\Url;
class OAuthFilter extends ActionFilter
{
	public $wechat = 'wechat';
	public $oauthType = Wechat::OAUTH_TYPE_BASE;
	public $oauthUrl;
	public $backUrl;
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
		if (empty($this->oauthUrl)) {
			$this->oauthUrl = Yii::$app->request->absoluteUrl;
		}
	}
	public function beforeAction($action)
	{
		if ($this->wechat->isWechat()) {
			$userInfo = Yii::$app->request->cookies->getValue(Wechat::OAUTH_COOKIE_NAME);
			if (!empty($userInfo)) {
				$this->wechat->trigger(Wechat::EVENT_OAUTH, new OAuthEvent($userInfo));
				return parent::beforeAction($action);
			}
			$this->wechat->authRequired(Url::to($this->oauthUrl, true), $this->oauthType, Url::to($this->backUrl, true));
		}
		return parent::beforeAction($action);
	}
}
