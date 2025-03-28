<?php

namespace yii\wechat\actions;

use Yii;
use yii\wechat\WechatException;
use yii\base\Action;
use yii\di\Instance;
use yii\web\Cookie;
use yii\wechat\Wechat;

class OAuthReturnAction extends Action
{
	public function init()
	{
		parent::init();
	}

	public function run()
	{
		$res = Yii::$app->wechat->getOauthInfoFromCode(Yii::$app->request->get('code'));
		if (empty($res['openid'])) {
			throw new WechatException('获取openid失败');
		}
		$cookie = Yii::$app->response->cookies;
		$cookie->add(new Cookie([
			'name' => Wechat::OAUTH_COOKIE_NAME,
			'value' => $res,
			'expire' => time() + Wechat::OAUTH_TOKEN_TTL,
		]));
		$redirect_url = Yii::$app->request->get('state');
		if (empty($redirect_url)) {
			return $this->controller->goBack();
		}
		return $this->redirect($redirect_url);
	}
}
