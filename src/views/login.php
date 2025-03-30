<?php
use yii\helpers\Html;
use yii\wechat\assets\LoginAsset;

/** @var \yii\web\View $this */
/** @var string $qrTicketUrl */
/** @var string $statusUrl */
/** @var string $backUrl */
/** @var int $checkDelay */
LoginAsset::register($this);
$this->title = '登录';
$this->registerJsVar('qrTicketUrl', $qrTicketUrl);
$this->registerJsVar('statusUrl', $statusUrl);
$this->registerJsVar('backUrl', $backUrl);
$this->registerJsVar('checkDelay', $checkDelay);
$js = <<<JS
updateQrCode();
JS;
$this->registerJs($js);
?>
<div class="site-login">
	<div class="row justify-content-center">
		<div class="col-lg-5">
			<div class="card">
				<div class="card-body">
					<h4 class="text-center mb-4"><?= Html::encode($this->title) ?></h4>
					<div class="text-center">
						<div id="qrcode-container" class="mb-3">
							<img class="wechat-login-qrcode img-fluid" style="max-width: 200px;">
						</div>
						<div class="wechat-login-status text-muted">
							<p>请使用微信扫码登录</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>