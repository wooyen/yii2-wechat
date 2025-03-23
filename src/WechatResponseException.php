<?php
namespace yii\wechat;

use Yii;

class WechatResponseException extends WechatException
{

	private $response;

	public function __construct(string $response)
	{
		parent::__construct(Yii::t('wechat', 'The response from wechat server is not in correct format: {response}.', ['response' => $response]));
		$this->response = $response;
	}

	public function getResponse(): string
	{
		return $this->response;
	}
}
