<?php

namespace yii\wechat\filters;

use Yii;
use yii\wechat\Wechat;
use yii\wechat\events\OAuthEvent;
use yii\base\ActionFilter;
use yii\base\Exception;
use yii\di\Instance;
use yii\web\Cookie;
class OAuthFilter extends ActionFilter
{
	private const OAUTH_COOKIE_NAME = '__wechat_oauth_info';
	private const OAUTH_CSRF_KEY = '__wechat_oauth_csrf';
	public const OAUTH_TYPE_BASE = 'snsapi_base';
	public const OAUTH_TYPE_USERINFO = 'snsapi_userinfo';
	public const EVENT_OAUTH = 'wechat.oauth';
	public $wechat = 'wechat';
	public $oauthType = self::OAUTH_TYPE_BASE;
	public $enableCsrfCheck = true;
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
	}
	public function beforeAction($action)
	{
		if (!$this->wechat->isWechat()) {
			return parent::beforeAction($action);
		}
		$userInfo = Yii::$app->request->cookies->getValue(self::OAUTH_COOKIE_NAME);
		if (!empty($userInfo)) {
			$event = new OAuthEvent($userInfo);
			$this->wechat->trigger(self::EVENT_OAUTH, $event);
			return $event->continue && parent::beforeAction($action);
		}
		$code = Yii::$app->request->get('code');
		if (empty($code)) {
			$state = '';
			if ($this->enableCsrfCheck) {
				$state = Yii::$app->security->generateRandomString(32);
				Yii::$app->session->set(self::OAUTH_CSRF_KEY, $state);
			}
			$this->wechat->authRequired($this->oauthType, $state);
			return false;
		}
		if ($this->enableCsrfCheck) {
			$state = Yii::$app->session->get(self::OAUTH_CSRF_KEY);
			if (empty($state) || $state !== Yii::$app->request->get('state')) {
				throw new Exception('CSRF 检查失败');
			}
			Yii::$app->session->remove(self::OAUTH_CSRF_KEY);
		}
		$userInfo = $this->wechat->getOauthInfoFromCode($code);
		if (empty($userInfo) || !array_key_exists('openid', $userInfo)) {
			throw new Exception('获取用户信息失败');
		}
		$cookie = Yii::$app->response->cookies;
		$cookie->add(new Cookie([
			'name' => self::OAUTH_COOKIE_NAME,
			'value' => $userInfo,
			'expire' => $userInfo['refresh_token_expire'],
		]));
		$event = new OAuthEvent($userInfo);
		$this->wechat->trigger(self::EVENT_OAUTH, $event);
		return $event->continue && parent::beforeAction($action);
	}
}
