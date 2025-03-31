<?php

namespace yii\wechat\actions;

use Yii;
use yii\wechat\Exception;
use yii\base\Action;
use yii\di\Instance;
use yii\web\Cookie;
use yii\wechat\Wechat;
use yii\wechat\events\OAuthEvent;

class OauthAction extends Action
{
	public $wechat = 'wechat';
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
	}

	public function run()
	{
		$res = $this->wechat->getOauthInfoFromCode(Yii::$app->request->get('code'));
		if (empty($res['openid'])) {
			throw new Exception('获取openid失败');
		}
		$cookie = Yii::$app->response->cookies;
		$cookie->add(new Cookie([
			'name' => Wechat::OAUTH_COOKIE_NAME,
			'value' => $res,
			'expire' => $res['refresh_token_expire'],
		]));
		$this->wechat->trigger(Wechat::EVENT_OAUTH, new OAuthEvent($res));
		$redirect_url = Yii::$app->request->get('state');
		if (empty($redirect_url)) {
			return $this->controller->goBack();
		}
		return $this->redirect($redirect_url);
	}
}
