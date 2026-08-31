<?php
namespace lib\Mail;

/*
 * HTTP API 发信。
 *
 * Resend / Brevo / SendGrid 三家的接口都是「一个 API Key + 一段 JSON」，
 * 差别只在地址、鉴权头和字段名，所以共用这一个驱动，用 $provider 区分。
 *
 * 相比 SMTP 的好处：走 443 端口，不受国内云服务器封禁 25/465 端口的影响。
 */
class Api implements ISender
{
	const TIMEOUT = 15;

	private $provider;
	private $key;
	private $from;
	private $from_name;
	private $response = '';

	public function __construct($provider, $conf)
	{
		$this->provider = $provider;
		$this->key = isset($conf['key']) ? trim($conf['key']) : '';
		$this->from = isset($conf['from']) ? trim($conf['from']) : '';
		$this->from_name = isset($conf['from_name']) ? trim($conf['from_name']) : '';
	}

	public function name()
	{
		$names = ['resend' => 'Resend', 'brevo' => 'Brevo', 'sendgrid' => 'SendGrid'];
		return isset($names[$this->provider]) ? $names[$this->provider] : $this->provider;
	}

	public function isReady()
	{
		return $this->key !== '' && $this->from !== '' && isset($this->endpoint()['url']);
	}

	public function send($to, $subject, $html, $text)
	{
		if (!$this->isReady()) {
			return ['ok' => false, 'msg' => $this->name() . ' 参数没有配置完整'];
		}
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return ['ok' => false, 'msg' => '收件地址格式不正确'];
		}

		$cfg = $this->endpoint();
		$body = $this->buildBody($to, $subject, $html, $text);
		$res = $this->request($cfg['url'], $cfg['headers'], json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->response = $res['body'];

		if ($res['errno']) {
			return ['ok' => false, 'msg' => '请求 ' . $this->name() . ' 接口失败：' . $res['error']];
		}
		//三家成功时的状态码不一样：Resend 200、Brevo 201、SendGrid 202
		if ($res['status'] >= 200 && $res['status'] < 300) {
			return ['ok' => true, 'msg' => '发送成功'];
		}
		//失败时把对方返回的原文带上，一般会直接写明是 key 不对还是发件域名没验证
		$detail = trim(preg_replace('/\s+/', ' ', strip_tags($res['body'])));
		if (strlen($detail) > 200) $detail = substr($detail, 0, 200) . '…';
		return ['ok' => false, 'msg' => $this->name() . ' 返回 HTTP ' . $res['status'] . '：' . ($detail !== '' ? $detail : '无内容')];
	}

	public function lastResponse()
	{
		return $this->response;
	}

	private function endpoint()
	{
		if ($this->provider === 'resend') {
			return [
				'url' => 'https://api.resend.com/emails',
				'headers' => ['Authorization: Bearer ' . $this->key, 'Content-Type: application/json'],
			];
		}
		if ($this->provider === 'brevo') {
			return [
				'url' => 'https://api.brevo.com/v3/smtp/email',
				'headers' => ['api-key: ' . $this->key, 'Content-Type: application/json', 'Accept: application/json'],
			];
		}
		if ($this->provider === 'sendgrid') {
			return [
				'url' => 'https://api.sendgrid.com/v3/mail/send',
				'headers' => ['Authorization: Bearer ' . $this->key, 'Content-Type: application/json'],
			];
		}
		return [];
	}

	private function buildBody($to, $subject, $html, $text)
	{
		$from = $this->from_name !== '' ? $this->from_name . ' <' . $this->from . '>' : $this->from;

		if ($this->provider === 'resend') {
			return ['from' => $from, 'to' => [$to], 'subject' => $subject, 'html' => $html, 'text' => $text];
		}
		if ($this->provider === 'brevo') {
			return [
				'sender' => ['email' => $this->from, 'name' => $this->from_name !== '' ? $this->from_name : $this->from],
				'to' => [['email' => $to]],
				'subject' => $subject,
				'htmlContent' => $html,
				'textContent' => $text,
			];
		}
		//sendgrid
		return [
			'personalizations' => [['to' => [['email' => $to]]]],
			'from' => ['email' => $this->from, 'name' => $this->from_name !== '' ? $this->from_name : $this->from],
			'subject' => $subject,
			'content' => [
				['type' => 'text/plain', 'value' => $text],
				['type' => 'text/html', 'value' => $html],
			],
		];
	}

	/*
	 * 这里不用 get_curl()：需要自定义请求头，而且发信涉及 API Key，证书必须校验
	 */
	private function request($url, $headers, $json)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		$body = curl_exec($ch);
		$result = [
			'body' => $body === false ? '' : $body,
			'status' => intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)),
			'errno' => curl_errno($ch),
			'error' => curl_error($ch),
		];
		curl_close($ch);
		if ($result['errno'] && $result['error'] === '') {
			$result['error'] = '连接失败或超时';
		}
		return $result;
	}
}
