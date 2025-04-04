<?php

namespace yii\wechat\actions;

use Yii;
use yii\base\Action;
use yii\base\Module;
use yii\di\Instance;
use yii\helpers\Url;
use yii\wechat\filters\OAuthFilter;
use yii\wechat\Wechat;

class WechatLoginAction extends Action
{
	public $wechat = 'wechat';
	public $checkDelay = 1000;
	public $qrTicketUrl;
	public $statusUrl;
	public $oauthCallback;
	public $view = '@wechat/views/login';
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
		Yii::$app->on(Module::EVENT_BEFORE_ACTION, function ($event) {
			Yii::debug("WechatLoginAction: init: beforeAction", __METHOD__);
			$event->sender->controller->attachBehavior('wechatOauth', [
				'class' => OAuthFilter::class,
				'wechat' => $this->wechat,
			]);
			if (is_callable($this->oauthCallback)) {
				$event->sender->controller->on(OAuthFilter::EVENT_OAUTH, $this->oauthCallback);
			}
		});
	}
	public function run()
	{
		return $this->controller->render($this->view, [
			'qrTicketUrl' => Url::toRoute($this->qrTicketUrl),
			'statusUrl' => Url::toRoute($this->statusUrl),
			'backUrl' => Yii::$app->user->getReturnUrl(),
			'checkDelay' => $this->checkDelay,
		]);
	}
}
