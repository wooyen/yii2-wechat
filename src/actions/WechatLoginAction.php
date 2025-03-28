<?php

namespace common\wechat\actions;

use Yii;
use yii\base\Action;
use yii\di\Instance;
use common\wechat\events\OAuthEvent;
use yii\wechat\filters\OAuthFilter;
use yii\wechat\Wechat;

class WechatLoginAction extends Action
{
	public $wechat = 'wechat';
	public $qrTicketUrl = '/site/login-qr';
	public $statusCheckUrl = '/site/login-status';
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
		]);
		//$this->controller->on(OAuthEvent::EVENT_NAME, [$this, 'onOAuth']);
		return parent::beforeAction($action);
	}
	public function run()
	{
		return $this->controller->render('@wechat/views/login', [
			'qrTicketUrl' => $this->qrTicketUrl,
			'statusCheckUrl' => $this->statusCheckUrl,
		]);
	}
}
