<?php

namespace yii\wechat\actions;

use yii\base\Action;
use yii\di\Instance;
use yii\helpers\Url;
use yii\wechat\filters\OAuthFilter;
use yii\wechat\Wechat;

class WechatLoginAction extends Action
{
	public $wechat = 'wechat';
	public $checkDelay = 1000;
	public $oauthUrl;
	public $backUrl;
	public $qrTicketUrl;
	public $statusUrl;
	public function init()
	{
		parent::init();
		$this->wechat = Instance::ensure($this->wechat, Wechat::class);
	}
	public function beforeAction($action)
	{
		$this->controller->attachBehavior('wechatOauth', [
			'class' => OAuthFilter::class,
			'wechat' => $this->wechat,
			'oauthUrl' => $this->oauthUrl,
			'backUrl' => $this->backUrl,
		]);
		$this->wechat->on(Wechat::EVENT_OAUTH, [$this, 'onOAuth']);
		return parent::beforeAction($action);
	}
	public function run()
	{
		return $this->controller->render('@wechat/views/login', [
			'qrTicketUrl' => Url::toRoute($this->qrTicketUrl),
			'statusUrl' => Url::toRoute($this->statusUrl),
			'backUrl' => Url::toRoute($this->backUrl),
			'checkDelay' => $this->checkDelay,
		]);
	}
}
