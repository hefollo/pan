<?php
namespace lib;

/*
 * 支付宝当面付（扫码支付）
 *
 * 只用到两个接口：
 *   alipay.trade.precreate  下单并拿到二维码内容
 *   alipay.trade.query      查询订单支付状态（前端轮询用）
 *
 * 签名走 RSA2（SHA256WithRSA），用 openssl 直接做，不依赖官方 SDK。
 * 商户应用私钥用于签名，支付宝公钥用于验签返回内容。
 */
class Alipay
{
	const GATEWAY = 'https://openapi.alipay.com/gateway.do';

	private $appid;
	private $private_key;
	private $public_key;
	private $errmsg = '';

	public function __construct($appid, $private_key, $public_key)
	{
		$this->appid = trim((string)$appid);
		$this->private_key = $this->formatKey($private_key, 'PRIVATE');
		$this->public_key = $this->formatKey($public_key, 'PUBLIC');
	}

	public function errmsg()
	{
		return $this->errmsg;
	}

	/*
	 * 后台粘贴进来的密钥常见三种形态：单行纯 base64、已经带 BEGIN/END 头尾、
	 * 或者带了多余空白。统一整理成 PEM，省得因为格式问题签名失败还看不出原因。
	 */
	private function formatKey($key, $type)
	{
		$key = trim((string)$key);
		if ($key === '') return '';
		if (strpos($key, '-----BEGIN') !== false) return $key;
		$key = preg_replace('/\s+/', '', $key);
		$label = $type === 'PRIVATE' ? 'RSA PRIVATE KEY' : 'PUBLIC KEY';
		return "-----BEGIN {$label}-----\n" . chunk_split($key, 64, "\n") . "-----END {$label}-----\n";
	}

	public function isReady()
	{
		return $this->appid !== '' && $this->private_key !== '' && $this->public_key !== '';
	}

	/*
	 * 生成收款二维码
	 * 返回 ['code'=>0, 'qr_code'=>'https://qr.alipay.com/xxx'] 或 ['code'=>-1,'msg'=>...]
	 */
	public function precreate($trade_no, $amount, $subject)
	{
		$biz = [
			'out_trade_no' => $trade_no,
			'total_amount' => number_format((float)$amount, 2, '.', ''),
			'subject' => $subject,
		];
		$res = $this->request('alipay.trade.precreate', $biz);
		if ($res['code'] != 0) return $res;
		$data = $res['data'];
		if (!isset($data['qr_code']) || $data['qr_code'] === '') {
			return ['code' => -1, 'msg' => '支付宝未返回二维码内容'];
		}
		return ['code' => 0, 'qr_code' => $data['qr_code']];
	}

	/*
	 * 查询订单
	 * 返回 ['code'=>0, 'paid'=>true/false, 'trade_no'=>支付宝交易号]
	 * 订单还没被扫码时支付宝返回 ACQ.TRADE_NOT_EXIST，这不算错误，按“未支付”处理
	 */
	public function query($trade_no)
	{
		$res = $this->request('alipay.trade.query', ['out_trade_no' => $trade_no]);
		if ($res['code'] != 0) {
			if (isset($res['sub_code']) && $res['sub_code'] === 'ACQ.TRADE_NOT_EXIST') {
				//还没扫码支付时支付宝就是这个错，属于正常情况，当作未支付
				return ['code' => 0, 'paid' => false, 'trade_no' => '', 'out_trade_no' => $trade_no];
			}
			return $res;
		}
		$status = isset($res['data']['trade_status']) ? $res['data']['trade_status'] : '';
		$paid = in_array($status, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true);
		return [
			'code' => 0,
			'paid' => $paid,
			'status' => $status,
			'trade_no' => isset($res['data']['trade_no']) ? $res['data']['trade_no'] : '',
			//商户订单号原样带回去，调用方要拿它跟本地订单对一遍，确认这份响应说的就是这一笔
			'out_trade_no' => isset($res['data']['out_trade_no']) ? $res['data']['out_trade_no'] : '',
			'amount' => isset($res['data']['total_amount']) ? $res['data']['total_amount'] : '',
		];
	}

	private function request($method, $biz)
	{
		if (!$this->isReady()) {
			return ['code' => -1, 'msg' => '支付宝当面付参数没有配置完整'];
		}
		$params = [
			'app_id' => $this->appid,
			'method' => $method,
			'format' => 'JSON',
			'charset' => 'utf-8',
			'sign_type' => 'RSA2',
			'timestamp' => date('Y-m-d H:i:s'),
			'version' => '1.0',
			'biz_content' => json_encode($biz, JSON_UNESCAPED_UNICODE),
		];
		$params['sign'] = $this->sign($params);
		if ($params['sign'] === false) {
			return ['code' => -1, 'msg' => '签名失败，请检查商户应用私钥是否正确'];
		}

		//最后一个 true：支付请求必须校验证书，不接受冒充支付宝的中间人
		$response = get_curl(self::GATEWAY, http_build_query($params), 0, 0, 0, 0, 0, 20, true);
		if ($response === false || $response === '') {
			return ['code' => -1, 'msg' => '请求支付宝接口失败或超时（若服务器缺少 CA 根证书，证书校验也会失败，需配置 php.ini 的 curl.cainfo）'];
		}
		//返回的是 GBK/UTF-8 的 JSON，节点名形如 alipay_trade_precreate_response
		$json = json_decode($response, true);
		if (!is_array($json)) {
			return ['code' => -1, 'msg' => '支付宝返回内容无法解析'];
		}
		$node = str_replace('.', '_', $method) . '_response';
		if (!isset($json[$node])) {
			return ['code' => -1, 'msg' => '支付宝返回内容异常'];
		}
		$data = $json[$node];

		//先验签，再看业务返回码；验签失败说明内容可能被篡改，一律拒绝
		if (!$this->verify($response, $node, isset($json['sign']) ? $json['sign'] : '')) {
			return ['code' => -1, 'msg' => '支付宝返回内容验签失败，请检查支付宝公钥是否正确'];
		}
		if (!isset($data['code']) || $data['code'] != '10000') {
			return [
				'code' => -1,
				'msg' => (isset($data['sub_msg']) ? $data['sub_msg'] : (isset($data['msg']) ? $data['msg'] : '支付宝接口返回错误')),
				'sub_code' => isset($data['sub_code']) ? $data['sub_code'] : '',
			];
		}
		return ['code' => 0, 'data' => $data];
	}

	private function sign($params)
	{
		unset($params['sign']);
		ksort($params);
		$pairs = [];
		foreach ($params as $k => $v) {
			if ($v === '' || $v === null) continue;
			$pairs[] = $k . '=' . $v;
		}
		$content = implode('&', $pairs);
		$res = openssl_pkey_get_private($this->private_key);
		if (!$res) {
			$this->errmsg = '商户应用私钥格式不正确';
			return false;
		}
		$sign = '';
		$ok = openssl_sign($content, $sign, $res, OPENSSL_ALGO_SHA256);
		if (PHP_MAJOR_VERSION < 8) openssl_free_key($res);
		if (!$ok) {
			$this->errmsg = '签名失败';
			return false;
		}
		return base64_encode($sign);
	}

	/*
	 * 验签要用原始 JSON 里该节点的字符串（不能用 json_encode 再拼一遍，
	 * 转义和顺序都可能对不上），所以这里直接从原文里截取。
	 */
	private function verify($response, $node, $sign)
	{
		if ($sign === '') return false;
		$start = strpos($response, '"' . $node . '"');
		if ($start === false) return false;
		$start = strpos($response, '{', $start);
		$end = strrpos($response, '"sign"');
		if ($start === false || $end === false || $end <= $start) return false;
		$content = substr($response, $start, $end - $start);
		$content = rtrim(trim($content), ',');
		$res = openssl_pkey_get_public($this->public_key);
		if (!$res) return false;
		$ok = openssl_verify($content, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
		if (PHP_MAJOR_VERSION < 8) openssl_free_key($res);
		return $ok === 1;
	}
}
