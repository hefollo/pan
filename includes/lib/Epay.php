<?php
namespace lib;

/*
 * 易支付（彩虹易支付等同类接口）
 *
 * 走的是最通用的两个接口，几乎所有易支付站点都支持：
 *   submit.php  页面跳转支付，用户跳过去扫码/付款
 *   api.php?act=order  按商户订单号查订单状态，我们靠它来确认是否已支付
 *
 * 另外也支持异步通知（notify_url）：通知能到就立刻发货，到不了就靠前端轮询兜底，
 * 所以站点在 CDN 或防火墙后面同样能用。
 *
 * 签名规则：参数按键名 ASCII 升序，去掉 sign、sign_type 和空值，
 * 拼成 a=b&c=d 后直接接上商户密钥，取 md5 小写。
 */
class Epay
{
	private $apiurl;
	private $pid;
	private $key;

	public function __construct($apiurl, $pid, $key)
	{
		$apiurl = trim($apiurl);
		if ($apiurl !== '' && substr($apiurl, -1) !== '/') $apiurl .= '/';
		$this->apiurl = $apiurl;
		$this->pid = trim($pid);
		$this->key = trim($key);
	}

	public function isReady()
	{
		return $this->apiurl !== '' && $this->pid !== '' && $this->key !== ''
			&& preg_match('/^https?:\/\//i', $this->apiurl);
	}

	/*
	 * 计算签名
	 */
	public function sign($params)
	{
		unset($params['sign'], $params['sign_type']);
		ksort($params);
		$parts = [];
		foreach ($params as $k => $v) {
			if ($v === '' || $v === null || is_array($v)) continue;
			$parts[] = $k . '=' . $v;
		}
		return md5(implode('&', $parts) . $this->key);
	}

	/*
	 * 校验回调参数的签名
	 */
	public function verify($params)
	{
		if (empty($params['sign'])) return false;
		return hash_equals($this->sign($params), strtolower($params['sign']));
	}

	/*
	 * 页面跳转支付的地址。$type 传 alipay/wxpay/qqpay 等，留空则由易支付站点让用户自己选
	 */
	public function payUrl($trade_no, $amount, $subject, $notify_url, $return_url, $type = '', $sitename = '', $charset = 'UTF-8')
	{
		if (!$this->isReady()) return false;
		//有些易支付站点是 GBK 的，收到 UTF-8 的中文会存成乱码，这时候按 GBK 发过去
		if (strtoupper($charset) === 'GBK') {
			$subject = @mb_convert_encoding($subject, 'GBK', 'UTF-8');
			$sitename = @mb_convert_encoding($sitename, 'GBK', 'UTF-8');
		}
		$params = [
			'pid' => $this->pid,
			'type' => $type,
			'out_trade_no' => $trade_no,
			'notify_url' => $notify_url,
			'return_url' => $return_url,
			'name' => $subject,
			'money' => number_format($amount, 2, '.', ''),
			'sitename' => $sitename,
		];
		$params['sign'] = $this->sign($params);
		$params['sign_type'] = 'MD5';
		return $this->apiurl . 'submit.php?' . http_build_query($params);
	}

	/*
	 * 查订单：返回 ['code'=>0,'paid'=>bool,'trade_no'=>易支付订单号]
	 */
	public function query($trade_no)
	{
		if (!$this->isReady()) {
			return ['code' => -1, 'msg' => '易支付参数没有配置完整'];
		}
		$url = $this->apiurl . 'api.php?' . http_build_query([
			'act' => 'order',
			'pid' => $this->pid,
			'key' => $this->key,
			'out_trade_no' => $trade_no,
		]);
		//https 的接口地址会校验证书；这也是强烈建议易支付接口用 https 的原因：
		//查单响应本身没有签名，走明文 http 的话，能插到中间的人可以随便伪造“已支付”
		$response = get_curl($url, 0, 0, 0, 0, 0, 0, 20, true);
		if ($response === false || $response === '') {
			return ['code' => -1, 'msg' => '请求易支付接口失败或超时（若接口是 https 而服务器缺少 CA 根证书，证书校验也会失败）'];
		}
		$json = json_decode($response, true);
		if (!is_array($json)) {
			return ['code' => -1, 'msg' => '易支付返回内容无法解析，请检查接口地址是否正确'];
		}
		if (!isset($json['code']) || intval($json['code']) !== 1) {
			return ['code' => -1, 'msg' => isset($json['msg']) ? $json['msg'] : '易支付接口返回错误'];
		}
		//响应里的商户订单号必须就是我们问的那一笔，对不上直接当查询失败
		$echo_no = isset($json['out_trade_no']) ? trim($json['out_trade_no']) : '';
		if($echo_no !== '' && $echo_no !== trim($trade_no)){
			return ['code' => -1, 'msg' => '易支付返回的订单号与查询的订单不一致'];
		}
		return [
			'code' => 0,
			'paid' => isset($json['status']) && intval($json['status']) === 1,
			'trade_no' => isset($json['trade_no']) ? $json['trade_no'] : '',
			'out_trade_no' => $echo_no !== '' ? $echo_no : $trade_no,
			'money' => isset($json['money']) ? $json['money'] : '',
		];
	}
}
