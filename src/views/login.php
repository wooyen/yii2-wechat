<?php
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var string $qrTicketUrl */
/** @var string $statusCheckUrl */

$this->title = '登录';
$this->registerJsVar('returnUrl', $returnUrl);
$this->registerJsVar('qrTicketUrl', $qrTicketUrl);
$this->registerJsVar('statusCheckUrl', $statusCheckUrl);
?>
<div class="site-login">
	<div class="row justify-content-center">
		<div class="col-lg-5">
			<div class="card">
				<div class="card-body">
					<h4 class="text-center mb-4"><?= Html::encode($this->title) ?></h4>
					<div class="text-center">
						<div id="qrcode-container" class="mb-3">
							<img id="qrcode" class="img-fluid" style="max-width: 200px;">
						</div>
						<div id="login-status" class="text-muted">
							<p>请使用微信扫码登录</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
	function updateQrCode() {
		$.get(qrTicketUrl, function (response) {
			if (response.code === 0) {
				url = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' + encodeURIComponent(response.data.ticket);
				$('#qrcode').attr('src', url);
				checkLoginStatus(response.data.scene, response.data.expire_at);
			} else {
				$('#login-status').html('<p class="text-danger">获取二维码失败，请刷新页面重试</p>');
			}
		});
	}
	function checkLoginStatus(scen, expire_at) {
		if (expire_at * 1000 < Date.now()) {
			$('#login-status').html('<p class="text-warning">二维码已过期，请刷新页面重试</p>');
			return;
		}
		$.get(statusCheckUrl, { scene: scene }, function (response) {
			if (response.code === 0) {
				if (response.data.status === 'success') {
					$('#login-status').html('<p class="text-success">登录成功，正在跳转...</p>');
					window.location.href = returnUrl;
				} else if (response.data.status === 'expired') {
					$('#login-status').html('<p class="text-warning">二维码已过期，请刷新页面重试</p>');
				} else if (response.data.status === 'scanning') {
					checkLoginStatus(scene, expire_at);
				} else {
					$('#login-status').html('<p class="text-warning">登录失败，请刷新页面重试</p>');
				}
			}
		}
}
</script>
<?php
$js = <<<JS
updateQrCode();
JS;
$this->registerJs($js);
?>