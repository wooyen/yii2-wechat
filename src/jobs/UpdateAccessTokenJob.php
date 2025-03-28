<?php

namespace yii\wechat\jobs;

use Yii;
use yii\base\BaseObject;
use yii\queue\Job;
use yii\wechat\Wechat;

class UpdateAccessTokenJob extends BaseObject implements Job
{
	private Wechat $wechat;
	public function __construct(Wechat $wechat, $config = [])
	{
		$this->wechat = $wechat;
		parent::__construct($config);
	}
	public function execute($queue): void
	{
		$this->wechat->updateAccessToken();
	}
}