var lastCheck = 0;
function updateQrCode() {
	$.get(qrTicketUrl, function (response) {
		if (response.code === 0) {
			url = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' + encodeURIComponent(response.data.ticket);
			$('.wechat-login-qrcode').attr('src', url);
			checkLoginStatus(response.data.scene, response.data.expire_at);
		} else {
			$('.wechat-login-status').html('<p class="text-danger">获取二维码失败，请刷新页面重试</p>');
		}
	});
}
function checkLoginStatus(scene, expire_at) {
	let lastCheck = Date.now();
	if (expire_at * 1000 < lastCheck) {
		$('.wechat-login-status').html('<p class="text-warning">二维码已过期，请刷新页面重试</p>');
		return;
	}
	$.get(statusUrl, { scene: scene }, function (response) {
		if (response.code === 0) {
			if (response.data.status === 'scanned') {
				$('.wechat-login-status').html('<p class="text-success">登录成功，正在跳转...</p>');
				window.location.href = backUrl;
			} else if (response.data.status === 'expired') {
				$('.wechat-login-status').html('<p class="text-warning">二维码已过期，请刷新页面重试</p>');
			} else if (response.data.status === 'waiting') {
				let delay = lastCheck + checkDelay - Date.now();
				if (delay > 0) {
					setTimeout(function () {
						checkLoginStatus(scene, expire_at);
					}, delay);
				} else {
					checkLoginStatus(scene, expire_at);
				}
			} else {
				$('.wechat-login-status').html('<p class="text-warning">登录失败，请刷新页面重试</p>');
			}
		}
	});
}
