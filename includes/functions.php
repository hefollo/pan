<?php
/*
 * 之前没有设置任何超时：一旦对方接口（快捷登录接口、IP归属地接口）不通或者很慢，
 * curl 会一直挂着占住 PHP 进程，用户多点几次就把进程池占满，整站跟着卡死。
 * 这几个调用都是取一小段 JSON，给一个默认上限足够用；确实需要更久的调用可以自己传 $timeout。
 */
function get_curl($url, $post=0, $referer=0, $cookie=0, $header=0, $ua=0, $nobaody=0, $timeout=15, $ssl_verify=false)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, max(5, intval($timeout)));
	/*
	 * 默认不校验证书是这套程序一直以来的行为（有些存储/登录接口用的是自签证书），
	 * 但支付相关的请求必须校验：不校验的话，能插到中间的人可以冒充支付宝，
	 * 把一份真实的“已支付”响应重放给我们，签名是真的，验签照样过。
	 */
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl_verify ? true : false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $ssl_verify ? 2 : false);
	$httpheader[] = "Accept: */*";
	$httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
	$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
	$httpheader[] = "Connection: close";
	curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
	if ($post) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	if ($header) {
		curl_setopt($ch, CURLOPT_HEADER, true);
	}
	if ($cookie) {
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	if($referer){
		curl_setopt($ch, CURLOPT_REFERER, $referer);
	}
	if ($ua) {
		curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	}
	else {
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
	}
	if ($nobaody) {
		curl_setopt($ch, CURLOPT_NOBODY, 1);
	}
	curl_setopt($ch, CURLOPT_ENCODING, "gzip");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$ret = curl_exec($ch);
	curl_close($ch);
	return $ret;
}
/*
 * 取访问者 IP。$type 对应后台「用户IP地址设置」：
 *   0 X-Forwarded-For（老行为，头可以伪造，只适合前面是自己可控的反代）
 *   1 X-Real-IP
 *   2 REMOTE_ADDR（直连时最准）
 *   3 Cloudflare：只在请求确实来自 CF 的 IP 段时才采信 CF-Connecting-IP，
 *     否则一律用 REMOTE_ADDR —— 套了 CF 的站点应该用这个
 *
 * 四种方式都支持 IPv6：原来只匹配 IPv4，CF 转发过来的 IPv6 用户会识别不出来，
 * 退回 REMOTE_ADDR 变成 CF 边缘节点的地址，所有 IPv6 用户被算成同一个人。
 */
function real_ip($type=0){
	$remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

	if($type >= 3)return trusted_client_ip();
	if($type >= 2)return $remote !== '' ? $remote : '0.0.0.0';

	if($type <= 0){
		//X-Forwarded-For 是「客户端, 代理1, 代理2」的链，最左边那个才是访问者
		if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
			foreach(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $item){
				$item = trim($item);
				//去掉 IPv6 的方括号写法和端口号
				if(substr($item, 0, 1) === '[' && strpos($item, ']') !== false){
					$item = substr($item, 1, strpos($item, ']') - 1);
				}
				if(filter_var($item, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
					return $item;
				}
			}
		}
		if(!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
			return $_SERVER['HTTP_CLIENT_IP'];
		}
	}
	if(!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)){
		return $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
	if(!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)){
		return $_SERVER['HTTP_X_REAL_IP'];
	}
	return $remote !== '' ? $remote : '0.0.0.0';
}

/*
 * 限流用的统计维度。
 *
 * IPv4 用完整地址；IPv6 用 /64 前缀 —— 一个家庭宽带会分到一整个 /64，
 * 里面每台设备、甚至每次连接的地址都不一样，按完整地址限流等于没限。
 * 结果是规范化过的字符串，可以直接存库和比较。
 */
function client_ip_key($ip = null){
	if($ip === null){
		global $clientip;
		$ip = $clientip;
	}
	$bin = @inet_pton($ip);
	if($bin === false)return (string)$ip;
	if(strlen($bin) === 4)return $ip;              //IPv4 原样
	//IPv6：后 64 位清零，取 /64 前缀
	$prefix = substr($bin, 0, 8).str_repeat(chr(0), 8);
	$text = @inet_ntop($prefix);
	return $text === false ? $ip : $text.'/64';
}

function get_ip_city($ip)
{
	$url = 'http://whois.pconline.com.cn/ipJson.jsp?json=true&ip=';
	$city = get_curl($url . $ip);
	$city = mb_convert_encoding($city, "UTF-8", "GB2312");
	$city = json_decode($city, true);
	if ($city['city']) {
		$location = $city['pro'].$city['city'];
	} else {
		$location = $city['pro'];
	}
	if($location){
		return $location;
	}else{
		return false;
	}
}
function daddslashes($string) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = daddslashes($val);
		}
	} else {
		$string = addslashes($string);
	}
	return $string;
}

function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}

function dstrpos($string, $arr) {
	if(empty($string)) return false;
	foreach((array)$arr as $v) {
		if(strpos($string, $v) !== false) {
			return true;
		}
	}
	return false;
}

function checkmobile() {
	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
	$ualist = array('android', 'midp', 'nokia', 'mobile', 'iphone', 'ipod', 'blackberry', 'windows phone');
	if((dstrpos($useragent, $ualist) || strexists($_SERVER['HTTP_ACCEPT'], "VND.WAP") || strexists($_SERVER['HTTP_VIA'],"wap")))
		return true;
	else
		return false;
}
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key);
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);
	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);
	$result = '';
	$box = range(0, 255);
	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}
	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}
	if($operation == 'DECODE') {
		if(((int)substr($result, 0, 10) == 0 || (int)substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}
}

function random($length, $numeric = 0) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	$hash = '';
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed[mt_rand(0, $max)];
	}
	return $hash;
}
function showmsg($content = '未知的异常',$type = 4,$back = false)
{
switch($type)
{
case 1:
	$panel="success";
break;
case 2:
	$panel="info";
break;
case 3:
	$panel="warning";
break;
case 4:
	$panel="danger";
break;
}

echo '<div class="panel panel-'.$panel.'">
	  <div class="panel-heading">
		<h3 class="panel-title">提示信息</h3>
		</div>
		<div class="panel-body">';
echo $content;

if ($back) {
	echo '<hr/><a href="'.$back.'"><< 返回上一页</a>';
}
else
	echo '<hr/><a href="javascript:history.back(-1)"><< 返回上一页</a>';

echo '</div>
	</div>';
	exit;
}
function sysmsg($msg = '未知的异常',$title = '站点提示信息') {
	?>  
	<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" lang="zh-CN">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo $title?></title>
		<style type="text/css">
html{background:#eee}body{background:#fff;color:#333;font-family:"微软雅黑","Microsoft YaHei",sans-serif;margin:2em auto;padding:1em 2em;max-width:700px;-webkit-box-shadow:10px 10px 10px rgba(0,0,0,.13);box-shadow:10px 10px 10px rgba(0,0,0,.13);opacity:.8}h1{border-bottom:1px solid #dadada;clear:both;color:#666;font:24px "微软雅黑","Microsoft YaHei",sans-serif;margin:30px 0 0 0;padding:0;padding-bottom:7px}#error-page{margin-top:50px}h3{text-align:center}#error-page p{font-size:9px;line-height:1.5;margin:25px 0 20px}#error-page code{font-family:Consolas,Monaco,monospace}ul li{margin-bottom:10px;font-size:9px}a{color:#21759B;text-decoration:none;margin-top:-10px}a:hover{color:#D54E21}.button{background:#f7f7f7;border:1px solid #ccc;color:#555;display:inline-block;text-decoration:none;font-size:9px;line-height:26px;height:28px;margin:0;padding:0 10px 1px;cursor:pointer;-webkit-border-radius:3px;-webkit-appearance:none;border-radius:3px;white-space:nowrap;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;-webkit-box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);vertical-align:top}.button.button-large{height:29px;line-height:28px;padding:0 12px}.button:focus,.button:hover{background:#fafafa;border-color:#999;color:#222}.button:focus{-webkit-box-shadow:1px 1px 1px rgba(0,0,0,.2);box-shadow:1px 1px 1px rgba(0,0,0,.2)}.button:active{background:#eee;border-color:#999;color:#333;-webkit-box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5);box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5)}table{table-layout:auto;border:1px solid #333;empty-cells:show;border-collapse:collapse}th{padding:4px;border:1px solid #333;overflow:hidden;color:#333;background:#eee}td{padding:4px;border:1px solid #333;overflow:hidden;color:#333}
		</style>
	</head>
	<body id="error-page">
		<?php echo '<h3>'.$title.'</h3>';
		echo $msg; ?>
	</body>
	</html>
	<?php
	exit;
}

if(!function_exists("is_https")){
	function is_https() {
		if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443){
			return true;
		}elseif(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')){
			return true;
		}elseif(isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){
			return true;
		}elseif(isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_EWS_CUSTOME_SCHEME']) && $_SERVER['HTTP_EWS_CUSTOME_SCHEME'] == 'https'){
			return true;
		}
		return false;
	}
}

function checkRefererHost(){
	if(!$_SERVER['HTTP_REFERER'])return false;
	$url_arr = parse_url($_SERVER['HTTP_REFERER']);
	$http_host = $_SERVER['HTTP_HOST'];
	if(strpos($http_host,':'))$http_host = substr($http_host, 0, strpos($http_host, ':'));
	return $url_arr['host'] === $http_host;
}

function checkIfActive($string) {
	$array=explode(',',$string);
	$php_self=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],'/')+1,strrpos($_SERVER['REQUEST_URI'],'.')-strrpos($_SERVER['REQUEST_URI'],'/')-1);
	if (in_array($php_self,$array)){
		return 'active';
	}elseif (isset($_GET['m']) && in_array($_GET['m'],$array)){
		return 'active';
	}else
		return null;
}

function getAllSetting() {
	global $DB;
	$conf = array();
	$result = $DB->getAll("SELECT * FROM pre_config");
	foreach($result as $row){
		if($row['k']=='cache') continue;
		$conf[ $row['k'] ] = $row['v'];
	}
	return $conf;
}
function getSetting($k){
	global $DB;
	return $DB->getColumn("SELECT v FROM pre_config WHERE k=:k LIMIT 1", [':k'=>$k]);
}
/* ===== 购买套餐 / 支付宝当面付 ===== */

/*
 * 购买功能是否可用：开关打开 + 支付参数配置完整 + 至少有一个上架套餐
 */
/*
 * 登录态里的会话校验串。
 *
 * 邮箱账号要把密码哈希也算进去：这样改完密码，其它设备上的登录态立刻失效。
 * 快捷登录的账号没有密码，保持原来的算法不变，老 cookie 不会掉线。
 */
function user_session_hash($row){
	global $password_hash;
	$pwd = (isset($row['type']) && $row['type'] === 'mail' && !empty($row['password'])) ? $row['password'] : '';
	return md5($row['type'].$row['openid'].$pwd.$password_hash);
}

/*
 * 邮箱归一化：统一小写去空格。
 * 不做 Gmail 那种去点号/去 +别名 的处理——那会让用户觉得"我明明填的是另一个邮箱"，
 * 而且对 QQ、163 这些国内邮箱本来就不适用。
 */
function normalize_email($email){
	return strtolower(trim((string)$email));
}

/*
 * 邮箱注册是否可用：开关打开 + 至少有一个发信通道配好了
 */
function is_mail_reg_open(){
	global $conf;
	if(empty($conf['mail_reg_open']))return false;
	if(empty($conf['userlogin']))return false;
	return is_mail_ready();
}

/*
 * 邮箱域名是否在黑名单里（后台按行或逗号填一次性邮箱域名）
 */
function mail_domain_denied($email){
	global $conf;
	$deny = isset($conf['mail_domain_deny']) ? trim($conf['mail_domain_deny']) : '';
	if($deny === '')return false;
	$at = strrpos($email, '@');
	if($at === false)return true;
	$domain = substr($email, $at + 1);
	foreach(preg_split('/[\s,，;；]+/', $deny) as $item){
		$item = strtolower(trim($item));
		if($item !== '' && $item === $domain)return true;
	}
	return false;
}

/*
 * 发一封验证码邮件。
 *
 * 频率限制有三道：同邮箱的发送间隔、同邮箱每天上限、同 IP 每天上限。
 * 不做限制的话，别人可以拿这个接口当免费发信机去骚扰任意邮箱。
 *
 * 返回 ['code'=>0|-1, 'msg'=>...]
 */
function send_mail_code($email, $purpose, $uid = 0){
	global $DB, $conf;
	$email = normalize_email($email);
	if(!filter_var($email, FILTER_VALIDATE_EMAIL))return ['code'=>-1, 'msg'=>'邮箱格式不正确'];

	//限流用的是伪造不了的来源 IP，不是后台配置的那个展示用 IP
	$ip = trusted_client_ip();
	$interval = isset($conf['mail_send_interval']) ? max(0, intval($conf['mail_send_interval'])) : 60;
	$daily = isset($conf['mail_daily_limit']) ? intval($conf['mail_daily_limit']) : 10;
	$ip_daily = isset($conf['mail_ip_daily_limit']) ? intval($conf['mail_ip_daily_limit']) : 20;
	$hour_limit = isset($conf['mail_hour_limit']) ? intval($conf['mail_hour_limit']) : 50;
	$site_daily = isset($conf['mail_site_daily']) ? intval($conf['mail_site_daily']) : 200;

	if($interval > 0){
		$last = $DB->getColumn("SELECT addtime FROM pre_mailcode WHERE email=:e ORDER BY id DESC LIMIT 1", [':e'=>$email]);
		if($last && strtotime($last) + $interval > time()){
			$wait = strtotime($last) + $interval - time();
			return ['code'=>-1, 'msg'=>'验证码刚发过，请 '.$wait.' 秒后再试'];
		}
	}
	if($daily > 0){
		$num = intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE email=:e AND addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)", [':e'=>$email]));
		if($num >= $daily)return ['code'=>-1, 'msg'=>'该邮箱今天的验证码次数已用完，请明天再试'];
	}
	if($ip_daily > 0){
		$num = intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE ip=:ip AND addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)", [':ip'=>$ip]));
		if($num >= $ip_daily)return ['code'=>-1, 'msg'=>'当前网络今天的发送次数已用完，请明天再试'];
	}
	/*
	 * 站点级总量上限：就算有人换着邮箱、换着网络来刷，也烧不掉整个发信额度。
	 * 这是最后一道保险，正常站点根本碰不到这个数。
	 */
	if($hour_limit > 0){
		$num = intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE addtime > DATE_SUB(NOW(), INTERVAL 1 HOUR)"));
		if($num >= $hour_limit)return ['code'=>-1, 'msg'=>'当前发信量已达上限，请稍后再试'];
	}
	if($site_daily > 0){
		$num = intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)"));
		if($num >= $site_daily)return ['code'=>-1, 'msg'=>'今天的发信量已达上限，请明天再试'];
	}

	$expire = isset($conf['mail_code_expire']) ? max(1, intval($conf['mail_code_expire'])) : 10;
	$code = str_pad(strval(random_int(0, 999999)), 6, '0', STR_PAD_LEFT);

	//同一用途的旧验证码先作废，避免用户拿着上一封的码来用
	$DB->exec("UPDATE pre_mailcode SET used=1, status=3 WHERE email=:e AND purpose=:p AND used=0", [':e'=>$email, ':p'=>$purpose]);

	$titles = ['register'=>'注册账号', 'reset'=>'找回密码', 'changemail'=>'更换邮箱'];
	$what = isset($titles[$purpose]) ? $titles[$purpose] : '验证邮箱';
	$site = isset($conf['title']) ? $conf['title'] : '本站';
	$subject = '【'.$site.'】'.$what.'验证码：'.$code;
	$html = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;font-size:14px;color:#333;line-height:1.8">'
		.'<p>你正在'.htmlspecialchars($site, ENT_QUOTES, 'UTF-8').'进行<b>'.$what.'</b>操作，验证码是：</p>'
		.'<p style="margin:18px 0;font-size:30px;font-weight:700;letter-spacing:6px;color:#2563eb">'.$code.'</p>'
		.'<p>验证码 '.$expire.' 分钟内有效，请勿转发给他人。</p>'
		.'<p style="color:#888;font-size:12px;margin-top:20px">如果这不是你本人的操作，忽略这封邮件即可，你的账号不会有任何变化。</p>'
		.'</div>';

	/*
	 * 记录必须在发送之前落库：否则发送失败的请求不计入任何限制，
	 * 同一个邮箱可以无限次重试，每次都真去连一次 SMTP，直接把进程和邮件服务器压死。
	 */
	$id = $DB->insert('mailcode', [
		'email' => $email,
		'code' => $code,
		'purpose' => $purpose,
		'uid' => intval($uid),
		'ip' => $ip,
		'used' => 0,
		'trycount' => 0,
		'addtime' => 'NOW()',
		'expiretime' => date('Y-m-d H:i:s', time() + $expire * 60),
	]);

	$mailer = mailer();
	$res = $mailer->send($email, $subject, $html);
	if(empty($res['ok'])){
		//没发出去的码直接作废，免得它挡住用户下一次重新获取；失败原因存进记录供后台排查
		if($id)$DB->exec("UPDATE pre_mailcode SET used=1, status=2, errmsg=:e WHERE id=:id",
			[':e'=>mb_substr((string)$res['msg'], 0, 250, 'UTF-8'), ':id'=>$id]);
		//完整的失败详情（含 SMTP 会话）另外写文件，那里面可能带服务器信息，不进数据库
		log_mail_error($email, $purpose, $res['msg'], $mailer->attempts());
		return ['code'=>-1, 'msg'=>'验证码发送失败，请稍后重试或联系站长'];
	}
	if($id)$DB->exec("UPDATE pre_mailcode SET sender=:s WHERE id=:id",
		[':s'=>isset($res['sender']) ? mb_substr($res['sender'], 0, 20, 'UTF-8') : '', ':id'=>$id]);
	//顺手清掉三天前的记录，这张表只是临时用的，不用留着
	//这张表现在兼作发信记录，保留 30 天再清，不然还没来得及查就被删了
	if(mt_rand(1, 20) === 1){
		$DB->exec("DELETE FROM pre_mailcode WHERE addtime < DATE_SUB(NOW(), INTERVAL 30 DAY)");
	}
	return ['code'=>0, 'msg'=>'验证码已发送到 '.$email.'，'.$expire.' 分钟内有效'];
}

/*
 * 校验验证码。校验通过会把它标记成已用，不能重复使用。
 * 输错累计 5 次直接作废，防止拿 6 位数字硬撞。
 */
function verify_mail_code($email, $code, $purpose){
	global $DB;
	$email = normalize_email($email);
	$code = trim((string)$code);
	if(!preg_match('/^[0-9]{6}$/', $code))return ['code'=>-1, 'msg'=>'验证码格式不正确'];

	$row = $DB->getRow("SELECT * FROM pre_mailcode WHERE email=:e AND purpose=:p AND used=0 ORDER BY id DESC LIMIT 1",
		[':e'=>$email, ':p'=>$purpose]);
	if(!$row)return ['code'=>-1, 'msg'=>'请先获取验证码'];
	if(strtotime($row['expiretime']) < time()){
		$DB->exec("UPDATE pre_mailcode SET used=1, status=3 WHERE id=:id", [':id'=>$row['id']]);
		return ['code'=>-1, 'msg'=>'验证码已过期，请重新获取'];
	}
	if(intval($row['trycount']) >= 5){
		$DB->exec("UPDATE pre_mailcode SET used=1, status=3 WHERE id=:id", [':id'=>$row['id']]);
		return ['code'=>-1, 'msg'=>'验证码错误次数过多，请重新获取'];
	}
	if(!hash_equals($row['code'], $code)){
		$DB->exec("UPDATE pre_mailcode SET trycount=trycount+1 WHERE id=:id", [':id'=>$row['id']]);
		return ['code'=>-1, 'msg'=>'验证码不正确'];
	}
	//用掉就作废
	$DB->exec("UPDATE pre_mailcode SET used=1, status=1 WHERE id=:id", [':id'=>$row['id']]);
	return ['code'=>0, 'msg'=>'验证通过', 'row'=>$row];
}

/*
 * 发信失败的详情写到临时目录，方便站长排查；前台只看到一句笼统的提示
 */
function log_mail_error($email, $purpose, $msg, $attempts){
	$file = sys_get_temp_dir().'/pan_mail_error.log';
	$text = date('Y-m-d H:i:s')."\t".$purpose."\t".$email."\t".$msg."\n";
	foreach((array)$attempts as $a){
		$text .= "    ".$a['name'].': '.$a['msg']."\n";
	}
	@file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
}

/*
 * 密码规则：6-32 位，必须同时有字母和数字。
 * 太松的话撞库一撞一个准，太严又会被用户骂，这个程度比较平衡。
 */
function check_password_rule($pwd){
	$len = strlen($pwd);
	if($len < 6 || $len > 32)return '密码长度需要 6-32 位';
	if(!preg_match('/[a-zA-Z]/', $pwd) || !preg_match('/[0-9]/', $pwd))return '密码需要同时包含字母和数字';
	return '';
}

/*
 * 签发登录态：写 cookie，并把这次会话里游客上传的文件归到账号名下。
 * 快捷登录和邮箱登录都走这里，保证两边行为一致。
 */
function user_login_session($uid, $row){
	global $DB;
	$uid = intval($uid);
	if(isset($_SESSION['fileids']) && count($_SESSION['fileids']) > 0){
		$ids = array_reverse($_SESSION['fileids']);
		if(count($ids) > 60)$ids = array_splice($ids, 0, 60);
		$ids = implode(',', array_map('intval', $ids));
		if($ids !== '')$DB->exec("UPDATE pre_file SET uid='{$uid}' WHERE id IN ({$ids}) AND uid=0");
	}
	$expiretime = time() + 2592000;
	$token = authcode("{$uid}\t".user_session_hash($row)."\t{$expiretime}", 'ENCODE', SYS_KEY);
	set_auth_cookie("user_token", $token, $expiretime, '/');
	unset($_SESSION['user_block']);
	return true;
}

/*
 * 出一道算术题，答案存在会话里。
 *
 * 只用加减乘、结果控制在 0~20，中文界面下一眼就能算出来；
 * 不画图，所以没有 GD 扩展的服务器也能用。
 */
function make_captcha(){
	$ops = ['+', '-', '×'];
	$op = $ops[array_rand($ops)];
	if($op === '+'){
		$a = random_int(1, 10); $b = random_int(1, 9); $answer = $a + $b;
	}elseif($op === '-'){
		//保证不出现负数，免得用户纠结
		$a = random_int(2, 18); $b = random_int(1, $a - 1); $answer = $a - $b;
	}else{
		$a = random_int(2, 6); $b = random_int(2, 5); $answer = $a * $b;
	}
	$_SESSION['captcha_answer'] = $answer;
	$_SESSION['captcha_time'] = time();
	return $a.' '.$op.' '.$b.' = ?';
}

/*
 * 校验算术题。
 *
 * 不管对错都换一道新题：答案只有 0~30 这么些可能，
 * 不换的话拿同一道题挨个试很快就能蒙对。
 * 连续答错太多次就锁一会儿，防止脚本一边换题一边硬猜。
 */
function check_captcha($input){
	$answer = isset($_SESSION['captcha_answer']) ? $_SESSION['captcha_answer'] : null;
	$time = isset($_SESSION['captcha_time']) ? intval($_SESSION['captcha_time']) : 0;
	//先记下错误次数，验证通过再清零
	$fails = isset($_SESSION['captcha_fail']) ? intval($_SESSION['captcha_fail']) : 0;
	$fail_time = isset($_SESSION['captcha_fail_time']) ? intval($_SESSION['captcha_fail_time']) : 0;
	if($fails >= 10 && $fail_time + 600 > time()){
		return '答错次数过多，请 10 分钟后再试';
	}

	if($answer === null || $time + 600 < time()){
		make_captcha();
		return '计算题已过期，请重新计算';
	}
	$input = trim((string)$input);
	if($input === '' || !preg_match('/^-?[0-9]{1,3}$/', $input)){
		make_captcha();
		return '请填写计算题的答案';
	}
	if(intval($input) !== intval($answer)){
		$_SESSION['captcha_fail'] = $fails + 1;
		$_SESSION['captcha_fail_time'] = time();
		make_captcha();
		return '计算题答案不正确';
	}
	//用过就作废
	unset($_SESSION['captcha_answer'], $_SESSION['captcha_time']);
	$_SESSION['captcha_fail'] = 0;
	return '';
}

/*
 * 算术验证码开关，默认开着
 */
function is_captcha_open(){
	global $conf;
	return !isset($conf['mail_captcha_open']) || intval($conf['mail_captcha_open']) === 1;
}

/*
 * Cloudflare 的官方 IP 段。判断请求是不是真的从 CF 转发过来的，
 * 段有变化时可以照着 cloudflare.com/ips-v4 和 ips-v6 更新这里。
 */
function cloudflare_ranges(){
	return [
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
		'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
		'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
		'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	];
}

/*
 * 判断 IP 是否落在某个网段里，IPv4 和 IPv6 都支持（按二进制比较前缀位）
 */
function ip_in_cidr($ip, $cidr){
	$parts = explode('/', $cidr);
	if(count($parts) !== 2)return false;
	$net = @inet_pton($parts[0]);
	$addr = @inet_pton($ip);
	//两边协议族要一致，长度不同说明一个是 v4 一个是 v6
	if($net === false || $addr === false || strlen($net) !== strlen($addr))return false;
	$bits = intval($parts[1]);
	$bytes = intdiv($bits, 8);
	$rest = $bits % 8;
	if($bytes > 0 && strncmp($net, $addr, $bytes) !== 0)return false;
	if($rest === 0)return true;
	$mask = chr(0xff << (8 - $rest) & 0xff);
	return (isset($addr[$bytes]) && isset($net[$bytes]))
		&& (($addr[$bytes] & $mask) === ($net[$bytes] & $mask));
}

/*
 * 限流专用的"可信 IP"。
 *
 * 站点的 $clientip 走的是后台配置的 real_ip()，它会无条件相信 X-Forwarded-For
 * 这类请求头——展示用没问题，但拿来做限流就等于没做：伪造一个头就是一个新 IP。
 * 这里只在"请求确实来自 Cloudflare 的 IP 段"时才采信 CF-Connecting-IP，
 * 其余一律用 REMOTE_ADDR，这个值是 TCP 连接的对端地址，伪造不了。
 */
function trusted_client_ip(){
	$remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	if($remote === '')return '0.0.0.0';
	if(!empty($_SERVER['HTTP_CF_CONNECTING_IP'])){
		foreach(cloudflare_ranges() as $cidr){
			if(ip_in_cidr($remote, $cidr)){
				$cf = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
				if(filter_var($cf, FILTER_VALIDATE_IP))return $cf;
				break;
			}
		}
	}
	return $remote;
}

/*
 * 发信器。后台勾选的通道会按固定顺序依次尝试，前一个失败自动切下一个。
 */
function mailer(){
	return new \lib\Mailer();
}

/*
 * 站点有没有配好发信：勾了通道并且参数齐全。
 * 邮箱注册、找回密码这些功能都要先过这一关，没配好就不该放出入口。
 */
function is_mail_ready(){
	static $ready = null;
	if($ready === null)$ready = mailer()->isReady();
	return $ready;
}

/*
 * 当前可用的支付方式。两种可以只开一个，也可以同时开着让用户自己选：
 *   alipay 支付宝当面付：本站直接生成二维码，用户扫码付
 *   epay   易支付：跳到易支付站点的收银台，回来后靠查单确认
 */
function pay_methods(){
	global $conf;
	$list = [];
	if(!empty($conf['alipay_open']) && !empty($conf['alipay_appid']) && !empty($conf['alipay_private_key']) && !empty($conf['alipay_public_key'])){
		$list['alipay'] = ['name'=>'支付宝当面付', 'desc'=>'页面内扫码支付'];
	}
	if(!empty($conf['epay_open']) && !empty($conf['epay_apiurl']) && !empty($conf['epay_pid']) && !empty($conf['epay_key'])){
		$list['epay'] = ['name'=>'易支付', 'desc'=>'跳转到收银台支付'];
	}
	return $list;
}

function is_pay_method($method){
	$list = pay_methods();
	return isset($list[$method]);
}

function is_buy_open(){
	global $conf, $DB;
	if(empty($conf['userlogin']))return false;
	if(!pay_methods())return false;
	return intval($DB->getColumn("SELECT count(*) FROM pre_plan WHERE enable=1")) > 0;
}

function alipay_client(){
	global $conf;
	return new \lib\Alipay($conf['alipay_appid'], $conf['alipay_private_key'], $conf['alipay_public_key']);
}

/*
 * 易支付开放的支付通道。后台勾选哪几个就返回哪几个，一个都没勾时按三个全开处理，
 * 免得因为漏勾导致前台没有任何可选项。
 */
function epay_channels(){
	global $conf;
	$all = ['alipay'=>'支付宝', 'wxpay'=>'微信支付', 'qqpay'=>'QQ钱包'];
	$raw = isset($conf['epay_type']) ? trim($conf['epay_type']) : '';
	$list = [];
	foreach(explode(',', $raw) as $k){
		$k = trim($k);
		if(isset($all[$k]))$list[$k] = $all[$k];
	}
	return $list ? $list : $all;
}

function epay_client(){
	global $conf;
	return new \lib\Epay($conf['epay_apiurl'], $conf['epay_pid'], $conf['epay_key']);
}

/*
 * 付款时显示给用户、也发给支付渠道的商品名称。
 * 默认用“赞助”这种中性名字：站点标题里带「网盘」「外链」之类的词，
 * 有些易支付站点会按违禁商品直接拦下来。
 */
function pay_subject(){
	global $conf;
	$subject = isset($conf['pay_subject']) ? trim($conf['pay_subject']) : '';
	return $subject === '' ? '赞助' : $subject;
}

/*
 * 订单列表里显示的支付方式名字
 */
function pay_method_name($method){
	if($method === 'epay')return '易支付';
	if($method === 'alipay')return '支付宝当面付';
	return $method === '' ? '-' : $method;
}

/*
 * 内置的推荐套餐。后台「一键导入推荐套餐」用的就是这份，价格和额度都可以导入后再改。
 * 覆盖了三种典型玩法：按时长的包月卡、在现有额度上叠加的加量包、一次买断的永久会员。
 */
function default_plans(){
	return [
		['name'=>'体验周卡', 'category'=>'包月套餐', 'price'=>'1.00', 'upload_limit'=>50, 'limit_mode'=>'set', 'upload_size'=>100, 'days'=>7, 'remark'=>'先试试水，随时可续', 'sort'=>10],
		['name'=>'入门月卡', 'category'=>'包月套餐', 'price'=>'4.90', 'upload_limit'=>100, 'limit_mode'=>'set', 'upload_size'=>300, 'days'=>30, 'remark'=>'轻度使用，一个月够了', 'sort'=>11],
		['name'=>'标准月卡', 'category'=>'包月套餐', 'price'=>'9.90', 'upload_limit'=>200, 'limit_mode'=>'set', 'upload_size'=>500, 'days'=>30, 'remark'=>'日常够用，最受欢迎', 'sort'=>12],
		['name'=>'超值季卡', 'category'=>'包月套餐', 'price'=>'25.00', 'upload_limit'=>500, 'limit_mode'=>'set', 'upload_size'=>1024, 'days'=>90, 'remark'=>'三个月，折合每月更便宜', 'sort'=>13],
		['name'=>'半年卡', 'category'=>'包月套餐', 'price'=>'48.00', 'upload_limit'=>800, 'limit_mode'=>'set', 'upload_size'=>1536, 'days'=>180, 'remark'=>'半年长期，额度翻倍', 'sort'=>14],
		['name'=>'至尊年卡', 'category'=>'包月套餐', 'price'=>'88.00', 'upload_limit'=>0, 'limit_mode'=>'set', 'upload_size'=>2048, 'days'=>365, 'remark'=>'整年不限数量，省心', 'sort'=>15],
		['name'=>'加量包 +50', 'category'=>'加量包', 'price'=>'3.00', 'upload_limit'=>50, 'limit_mode'=>'add', 'upload_size'=>-1, 'days'=>30, 'remark'=>'30 天内每天多传 50 个', 'sort'=>20],
		['name'=>'加量包 +100', 'category'=>'加量包', 'price'=>'5.00', 'upload_limit'=>100, 'limit_mode'=>'add', 'upload_size'=>-1, 'days'=>30, 'remark'=>'30 天内每天多传 100 个', 'sort'=>21],
		['name'=>'加量包 +200', 'category'=>'加量包', 'price'=>'8.00', 'upload_limit'=>200, 'limit_mode'=>'add', 'upload_size'=>-1, 'days'=>30, 'remark'=>'30 天内每天多传 200 个', 'sort'=>22],
		['name'=>'加量包 +500', 'category'=>'加量包', 'price'=>'18.00', 'upload_limit'=>500, 'limit_mode'=>'add', 'upload_size'=>-1, 'days'=>30, 'remark'=>'30 天内每天多传 500 个', 'sort'=>23],
		['name'=>'加量包 +1000', 'category'=>'加量包', 'price'=>'30.00', 'upload_limit'=>1000, 'limit_mode'=>'add', 'upload_size'=>-1, 'days'=>30, 'remark'=>'30 天内每天多传 1000 个', 'sort'=>24],
		['name'=>'加量包 +2000', 'category'=>'加量包', 'price'=>'50.00', 'upload_limit'=>2000, 'limit_mode'=>'add', 'upload_size'=>-1, 'days'=>30, 'remark'=>'30 天内每天多传 2000 个，量大更划算', 'sort'=>25],
		['name'=>'大文件包 512MB', 'category'=>'单文件加强', 'price'=>'2.00', 'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>512, 'days'=>30, 'remark'=>'单文件上限提到 512MB，不动每日数量', 'sort'=>30],
		['name'=>'大文件包 1GB', 'category'=>'单文件加强', 'price'=>'3.00', 'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>1024, 'days'=>30, 'remark'=>'单文件上限提到 1GB，不动每日数量', 'sort'=>31],
		['name'=>'大文件包 2GB', 'category'=>'单文件加强', 'price'=>'5.00', 'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>2048, 'days'=>30, 'remark'=>'单文件上限提到 2GB，不动每日数量', 'sort'=>32],
		['name'=>'大文件包 5GB', 'category'=>'单文件加强', 'price'=>'12.00', 'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>5120, 'days'=>30, 'remark'=>'单文件上限提到 5GB，不动每日数量', 'sort'=>33],
		['name'=>'大文件包 10GB', 'category'=>'单文件加强', 'price'=>'20.00', 'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>10240, 'days'=>30, 'remark'=>'单文件上限提到 10GB，不动每日数量', 'sort'=>34],
		['name'=>'大文件包 20GB', 'category'=>'单文件加强', 'price'=>'35.00', 'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>20480, 'days'=>30, 'remark'=>'单文件上限提到 20GB，适合视频素材', 'sort'=>35],
		['name'=>'永久入门版', 'category'=>'永久会员', 'price'=>'68.00', 'upload_limit'=>100, 'limit_mode'=>'set', 'upload_size'=>1024, 'days'=>0, 'remark'=>'一次买断，每天 100 个', 'sort'=>40],
		['name'=>'永久基础版', 'category'=>'永久会员', 'price'=>'98.00', 'upload_limit'=>300, 'limit_mode'=>'set', 'upload_size'=>2048, 'days'=>0, 'remark'=>'一次买断，每天 300 个', 'sort'=>41],
		['name'=>'永久标准版', 'category'=>'永久会员', 'price'=>'138.00', 'upload_limit'=>600, 'limit_mode'=>'set', 'upload_size'=>3072, 'days'=>0, 'remark'=>'一次买断，每天 600 个', 'sort'=>42],
		['name'=>'永久会员', 'category'=>'永久会员', 'price'=>'198.00', 'upload_limit'=>0, 'limit_mode'=>'set', 'upload_size'=>5120, 'days'=>0, 'remark'=>'一次买断，不限每日数量', 'sort'=>43],
		['name'=>'永久尊享版', 'category'=>'永久会员', 'price'=>'298.00', 'upload_limit'=>0, 'limit_mode'=>'set', 'upload_size'=>10240, 'days'=>0, 'remark'=>'不限数量，单文件 10GB', 'sort'=>44],
		['name'=>'永久旗舰版', 'category'=>'永久会员', 'price'=>'498.00', 'upload_limit'=>0, 'limit_mode'=>'set', 'upload_size'=>0, 'days'=>0, 'remark'=>'数量和大小都不限，一步到位', 'sort'=>45],
	];
}

/*
 * 上架套餐列表，按 sort 再按价格排
 */
function plan_list($only_enabled = true){
	global $DB;
	$where = $only_enabled ? ' WHERE enable=1' : '';
	$rows = $DB->getAll("SELECT * FROM pre_plan{$where} ORDER BY sort ASC, price ASC, id ASC");
	return is_array($rows) ? $rows : [];
}

function plan_get($id){
	global $DB;
	$id = intval($id);
	if($id <= 0)return false;
	return $DB->getRow("SELECT * FROM pre_plan WHERE id=:id LIMIT 1", [':id'=>$id]);
}

/*
 * 把 -1 / 0 / N 这三种取值翻译成人话，套餐卡片和后台列表共用
 */
function plan_limit_text($value, $unit){
	$value = intval($value);
	if($value < 0)return '跟随全站设置';
	if($value === 0)return '不限制';
	return $value.' '.$unit;
}

/*
 * 购买页上的文案一律显示具体数字，不显示“跟随全站设置”这种只有后台才看得懂的说法
 */
function limit_number_text($value, $unit){
	$value = intval($value);
	return $value === 0 ? '不限制' : $value.' '.$unit;
}

/*
 * 买完之后每天能传多少：
 *   套餐填 -1（跟随全站） -> 换成全站设置的数量
 *   增加型套餐           -> 用户当前生效的数量再加上套餐的数量
 * 未登录时按全站设置估算，登录后卡片上就是这个用户真实会得到的数字
 */
function plan_result_limit_text($plan){
	global $conf, $islogin2, $userrow;
	$mode = isset($plan['limit_mode']) ? $plan['limit_mode'] : 'set';
	$value = intval($plan['upload_limit']);
	$site = isset($conf['upload_limit']) ? intval($conf['upload_limit']) : 0;

	if($mode === 'add'){
		$text = '+'.max(0, $value).' 个/天';
		$base = $islogin2 ? get_effective_upload_count_limit() : $site;
		//基础额度本身就是不限的话，加多少都没意义，这里不写“买后多少”，
		//卡片下面的提示会直接说“已经覆盖，买了不会有提升”
		if($base > 0)$text .= '（买后 '.($base + max(0, $value)).' 个/天）';
		return $text;
	}

	//套餐不改动每日数量，就写“不变”，不能把用户自己现有的额度写成套餐的卖点
	if($value < 0)return '不变';

	$result = $value;
	if($result > 0 && $islogin2 && is_user_permission_active() && !empty($userrow['bonus_limit'])){
		//买时长套餐时，已经买到手的加量额度会继续保留
		$result += max(0, intval($userrow['bonus_limit']));
	}
	return limit_number_text($result, '个/天');
}

/*
 * 买完之后单文件能传多大，-1 同样换算成全站设置的值
 */
function plan_result_size_text($plan){
	$value = intval($plan['upload_size']);
	//同样：套餐不动这一项就写“不变”
	if($value < 0)return '不变';
	return limit_number_text($value, 'MB');
}

/*
 * 购买页按分类把套餐分组，分类的先后顺序按套餐排序里第一次出现的顺序来，
 * 没填分类的归到最后一组，这样老站点不填分类也能照常显示（全都没填时购买页不显示分组标题）
 */
function plan_group_list($plans, $default_name = '其他套餐'){
	$groups = [];
	$rest = [];
	foreach($plans as $plan){
		$cat = isset($plan['category']) ? trim($plan['category']) : '';
		if($cat === ''){
			$rest[] = $plan;
			continue;
		}
		if(!isset($groups[$cat]))$groups[$cat] = [];
		$groups[$cat][] = $plan;
	}
	if($rest)$groups[$default_name] = $rest;
	return $groups;
}

function plan_days_text($days){
	$days = intval($days);
	return $days > 0 ? ('有效期 '.$days.' 天') : '永久有效';
}

/*
 * 发放套餐权限。
 * 规则（后台可见的说明也是这套）：
 *   - 权限数值按新买的套餐设置；
 *   - 有效期叠加：现有权限还没到期就从到期时间往后加，否则从当前时间算起；
 *   - 买到永久套餐直接变永久；
 *   - 已经是永久付费权限的用户再买限时套餐，只换权限数值，不会被改成有期限（不降级）。
 */
function grant_plan_to_user($uid, $order){
	global $DB, $conf;
	$uid = intval($uid);
	$user = $DB->getRow("SELECT * FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>$uid]);
	if(!$user)return false;

	$days = intval($order['days']);
	//没有到期时间、但已经有付费权限的，算永久权限
	$has_forever = empty($user['expiretime']) && (intval($user['upload_limit']) >= 0 || intval($user['upload_size']) >= 0 || intval($user['level']) > 0);

	if($days <= 0 || $has_forever){
		$expiretime = null;
	}else{
		$base = time();
		if(!empty($user['expiretime'])){
			$current = strtotime($user['expiretime']);
			if($current > $base)$base = $current;
		}
		$expiretime = date('Y-m-d H:i:s', $base + $days * 86400);
	}

	$data = [
		'upload_limit' => resolve_plan_upload_limit($user, $order),
		'bonus_limit' => resolve_plan_bonus_limit($user, $order),
		'upload_size' => resolve_plan_upload_size($user, $order),
	];
	/*
	 * 已经是永久权限的用户再买时长套餐，时间上没什么可加的（还是永久），
	 * 这时候要是按套餐值覆盖，等于花钱把自己降级了，而且降完还是永久的，很难挽回。
	 * 所以对永久用户一律取更优值。
	 */
	if($has_forever){
		$data['upload_limit'] = better_permission($user['upload_limit'], $data['upload_limit'], isset($conf['upload_limit']) ? $conf['upload_limit'] : 0);
		$data['upload_size'] = better_permission($user['upload_size'], $data['upload_size'], isset($conf['upload_size']) ? $conf['upload_size'] : 0);
	}
	//DB->update 会把空字符串写成 NULL，正好用来表示永久
	$data['expiretime'] = $expiretime === null ? '' : $expiretime;
	//侧栏“我的权限”卡有 120 秒会话缓存，这里清掉，买完刷新就能看到新的权限
	unset($_SESSION['layout_plan']);
	$ok = $DB->update('user', $data, ['uid'=>$uid]);
	if($ok === false && isset($data['bonus_limit'])){
		//站点还没执行 install/update.php 的话没有 bonus_limit 这一列，
		//这时候宁可少发加量额度，也不能因为一个字段就把整笔权限卡住不发
		unset($data['bonus_limit']);
		$ok = $DB->update('user', $data, ['uid'=>$uid]);
	}
	return $ok !== false;
}

/*
 * 算出这一单发放后的“每日上传数量”。
 *
 * 套餐有两种发放方式：
 *   set 设为   —— 直接把每日数量设成套餐里的值（老套餐没有这个字段，默认就是它）
 *   add 增加   —— 在用户当前生效的数量上加，买两次就是加两次
 *
 * add 的基数取“当前真正生效的数量”：
 *   - 权限还有效且用户自己有具体数值   -> 用用户的数值
 *   - 权限已过期，或用户是 -1 跟随全站 -> 用全站设置的数量，避免把过期的旧数字继续往上加
 *   - 基数是 0（不限）时保持不限，加多少都没意义
 */
function resolve_plan_upload_limit($user, $order){
	$mode = isset($order['limit_mode']) ? $order['limit_mode'] : 'set';
	$value = intval($order['upload_limit']);
	$active = empty($user['expiretime']) || strtotime($user['expiretime']) > time();
	//增加型套餐记在 bonus_limit 上（见 resolve_plan_bonus_limit），不动这里的基础数量
	if($mode === 'add')return $active ? intval($user['upload_limit']) : -1;
	//填 -1 表示这个套餐不改动每日数量（比如只提升单文件大小的套餐），保持用户现在的值；
	//权限已经过期的话就没什么好保持的了，回到跟随全站
	if($value < 0)return ($active && isset($user['upload_limit'])) ? intval($user['upload_limit']) : -1;
	return $value;
}

/*
 * 算出这一单发放后的“加量额度”。
 *
 * 加量包买的数量单独记在 bonus_limit 上，实际每日数量 = 基础数量 + 加量额度，
 * 这样后面再买月卡、年卡也只会换掉基础数量，加量包永远不会被覆盖掉。
 * 权限过期后加量额度不再计入（见 get_effective_upload_count_limit），
 * 过期之后重新买套餐时也从 0 重新开始，不会把很久以前的加量翻出来。
 */
function resolve_plan_bonus_limit($user, $order){
	$mode = isset($order['limit_mode']) ? $order['limit_mode'] : 'set';
	$active = empty($user['expiretime']) || strtotime($user['expiretime']) > time();
	$current = $active ? intval(isset($user['bonus_limit']) ? $user['bonus_limit'] : 0) : 0;
	if($mode !== 'add')return $current;
	return $current + max(0, intval($order['upload_limit']));
}

/*
 * 比较两个权限值哪个更强。0 表示不限，最强；-1 表示跟随全站，按全站的值算；其余按数值大小。
 */
function permission_weight($value, $site_value){
	$value = intval($value);
	if($value === 0)return PHP_INT_MAX;
	if($value < 0)return intval($site_value);
	return $value;
}

/*
 * 取更优的那个权限值（用于永久用户，避免买了低档套餐反而被降级）
 */
function better_permission($current, $new_value, $site_value){
	return permission_weight($new_value, $site_value) >= permission_weight($current, $site_value) ? $new_value : $current;
}

/*
 * 算出这一单发放后的“单文件大小”。
 * 套餐填 -1 表示这个套餐不改动单文件大小，保持用户现在的设置——
 * 加量包这种只加数量的套餐就该这样，不然会把月卡给的大额度覆盖回默认值。
 */
function resolve_plan_upload_size($user, $order){
	$value = intval($order['upload_size']);
	if($value >= 0)return $value;
	$active = empty($user['expiretime']) || strtotime($user['expiretime']) > time();
	return ($active && isset($user['upload_size'])) ? intval($user['upload_size']) : -1;
}

/*
 * 套餐卡片和后台列表上显示的每日数量文案
 */
function plan_limit_display($plan){
	$mode = isset($plan['limit_mode']) ? $plan['limit_mode'] : 'set';
	$value = intval($plan['upload_limit']);
	if($mode === 'add')return '在现有基础上 +'.max(0, $value).' 个/天';
	return plan_limit_text($value, '个/天');
}

/*
 * 这一单买下去会带来哪些变化，购买页用它提示，下单接口用它挡住“买了也没用”的订单。
 * 返回 ['limit'=>新数量, 'size'=>新大小, 'days'=>是否会延长时间, 'changed'=>是否有任何变化,
 *       'lower'=>会被降下来的项目]
 */
function plan_effect($user, $plan){
	global $conf;
	$order = [
		'upload_limit' => intval($plan['upload_limit']),
		'limit_mode' => isset($plan['limit_mode']) ? $plan['limit_mode'] : 'set',
		'upload_size' => intval($plan['upload_size']),
		'days' => intval($plan['days']),
	];
	$site_limit = isset($conf['upload_limit']) ? intval($conf['upload_limit']) : 0;
	$site_size = isset($conf['upload_size']) ? intval($conf['upload_size']) : 0;
	$active = empty($user['expiretime']) || strtotime($user['expiretime']) > time();
	$has_forever = empty($user['expiretime']) && (intval($user['upload_limit']) >= 0 || intval($user['upload_size']) >= 0 || intval($user['level']) > 0);

	//现在实际能用到的额度：-1 要换算成全站的值，加量额度要算进去
	$now_base = ($active && intval($user['upload_limit']) >= 0) ? intval($user['upload_limit']) : $site_limit;
	$now_bonus = $active ? max(0, intval(isset($user['bonus_limit']) ? $user['bonus_limit'] : 0)) : 0;
	$now_limit = $now_base === 0 ? 0 : $now_base + $now_bonus;
	$now_size = ($active && intval($user['upload_size']) >= 0) ? intval($user['upload_size']) : $site_size;

	//买完之后实际能用到的额度
	$new_limit = resolve_plan_upload_limit($user, $order);
	$new_size = resolve_plan_upload_size($user, $order);
	if($has_forever){
		$new_limit = better_permission($user['upload_limit'], $new_limit, $site_limit);
		$new_size = better_permission($user['upload_size'], $new_size, $site_size);
	}
	$new_bonus = resolve_plan_bonus_limit($user, $order);
	$after_base = $new_limit >= 0 ? $new_limit : $site_limit;
	$after_limit = $after_base === 0 ? 0 : $after_base + max(0, $new_bonus);
	$after_size = $new_size >= 0 ? $new_size : $site_size;

	$improved = permission_weight($after_limit, $site_limit) > permission_weight($now_limit, $site_limit)
		|| permission_weight($after_size, $site_size) > permission_weight($now_size, $site_size);
	/*
	 * 时长上的收益只对“当前就是限时权限”的用户成立：续期或换成永久都算。
	 * 本来就没有到期时间的用户（新用户、跟随全站的用户、已经永久的用户），
	 * 凭空多一个到期时间不是收益，不能靠它把“买了也没用”的单放过去。
	 */
	$time_gain = !empty($user['expiretime']);

	$lower = [];
	if(permission_weight($after_limit, $site_limit) < permission_weight($now_limit, $site_limit))$lower[] = '每日上传数量';
	if(permission_weight($after_size, $site_size) < permission_weight($now_size, $site_size))$lower[] = '单文件大小';

	return [
		'limit' => $after_limit,
		'size' => $after_size,
		'days' => $time_gain,
		'changed' => $improved || $time_gain,
		'lower' => $lower,
	];
}

/*
 * 确认收款并发货。用带条件的更新做幂等，只有真正把状态改过来的那一次才发权限，
 * 所以前端轮询、异步通知、同步跳转三条路同时到达也不会重复发放。
 *
 * 条件是 status<>1 而不是 status=0：订单可能因为用户换了套餐而被关掉（status=2），
 * 但人家在旧二维码上把钱付了，这种也必须照常发货，不能收了钱不认账。
 */
function finish_order($order, $pay_trade_no){
	global $DB;
	$stmt = $DB->query("UPDATE pre_order SET status=1, paytime=NOW(), alipay_no=:no WHERE id=:id AND status<>1",
		[':no'=>$pay_trade_no, ':id'=>intval($order['id'])]);
	$affected = $stmt ? $stmt->rowCount() : 0;
	if($affected < 1)return true;      //别人已经处理过了，不重复发
	return grant_plan_to_user($order['uid'], $order);
}

/*
 * 查一笔订单在支付渠道那边是否已经付款，付了就发货
 */
function check_order_paid($order){
	//1013 之前的老订单没有这个字段，一律按当面付处理
	$pay_type = isset($order['pay_type']) ? $order['pay_type'] : 'alipay';
	if($pay_type === 'epay'){
		$res = epay_client()->query($order['trade_no']);
	}else{
		$res = alipay_client()->query($order['trade_no']);
	}
	if($res['code'] != 0)return ['code'=>-1, 'msg'=>$res['msg']];
	if(empty($res['paid']))return ['code'=>0, 'paid'=>0];

	/*
	 * 说“已支付”还不够，必须确认它说的就是这一笔：
	 *   - 响应里的商户订单号要等于我们问的那个（不绑这一条，别人重放一份真实的已支付
	 *     响应就能把任意未支付订单变成已支付）
	 *   - 到账金额不能少于订单金额，渠道没返回金额的一律不自动发货，留给后台人工确认
	 */
	$echo_no = isset($res['out_trade_no']) ? trim($res['out_trade_no']) : '';
	if($echo_no === '' || $echo_no !== trim($order['trade_no'])){
		return ['code'=>-1, 'msg'=>'支付渠道返回的订单号与本地订单对不上，已停止发放，请联系站长'];
	}
	$amount = isset($res['amount']) ? $res['amount'] : (isset($res['money']) ? $res['money'] : '');
	if($amount === '' || $amount === null){
		return ['code'=>-1, 'msg'=>'支付渠道没有返回金额，无法核对，请联系站长'];
	}
	if(round(floatval($amount), 2) + 0.001 < round(floatval($order['price']), 2)){
		return ['code'=>-1, 'msg'=>'到账金额与订单金额不一致，已停止发放，请联系站长'];
	}
	finish_order($order, isset($res['trade_no']) ? $res['trade_no'] : '');
	return ['code'=>0, 'paid'=>1];
}

/*
 * 兜底：把这个用户最近还没入账的订单拿去支付渠道问一遍，付过的补发。
 *
 * 当面付没有异步通知，用户扫码付完直接关掉页面的话就没人轮询了，订单会一直挂在待支付。
 * 打开购买页和重新下单时各查一次，这种漏单基本就补回来了。
 */
function rescue_pending_orders($uid, $max = 2){
	global $DB;
	$uid = intval($uid);
	$rows = $DB->getAll("SELECT * FROM pre_order WHERE uid=".$uid." AND status<>1
		AND addtime > DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY id DESC LIMIT ".intval($max));
	if(!is_array($rows) || !$rows)return 0;
	$paid = 0;
	foreach($rows as $row){
		$res = check_order_paid($row);
		if($res['code'] == 0 && !empty($res['paid']))$paid++;
	}
	return $paid;
}

/*
 * 商户订单号：日期 + 用户 + 随机，26 位以内，支付宝要求同一 appid 下唯一
 */
function build_trade_no($uid){
	return date('YmdHis').str_pad(intval($uid) % 100000, 5, '0', STR_PAD_LEFT).mt_rand(1000, 9999);
}

/*
 * 默认外观：新装站点、以及配置里没有外观或存了个不认识的值时都用它。
 * 想换默认外观只改这一处，其余地方一律调这个函数。
 */
function default_site_theme(){
	return 'console';
}

/*
 * 站点外观的全部可选值。header.php / admin/head.php 等处各自还有一份同样的列表，
 * 新增外观时记得一起改。
 */
function site_theme_keys(){
	return ['cloud', 'night', 'neon', 'aurora', 'onefour', 'celadon', 'lilac', 'paper',
		'blush', 'sky', 'mint', 'sunset', 'abyss', 'emerald', 'sakura',
		'dashboard', 'console', 'portal', 'workspace'];
}

/*
 * 结构型（布局型）外观，body 上要额外挂 layout-theme
 */
function layout_theme_keys(){
	return ['dashboard', 'console', 'portal', 'workspace'];
}

/*
 * 把当前外观写进静态的 404.html。
 * 404.html 是纯静态文件，读不到数据库里的外观配置，所以在后台保存外观时
 * 直接改掉它的 <body class="...">，错误页就能跟着当前外观走。
 * 文件不存在或没有写权限时静默跳过，页面仍会用它自带的默认配色显示。
 */
function sync_404_theme($theme){
	if(!in_array($theme, site_theme_keys(), true))return false;
	$file = ROOT.'404.html';
	if(!is_file($file) || !is_writable($file))return false;
	$html = @file_get_contents($file);
	//确认是本程序的 404 页再改，避免误伤用户自己换上去的页面
	if($html === false || strpos($html, 'errorpage-card') === false)return false;
	$class = 'theme-'.$theme.(in_array($theme, layout_theme_keys(), true) ? ' layout-theme' : '');
	$count = 0;
	//打上 data-theme-synced 标记：页面里的兜底脚本看到它就不再用浏览器里存的旧外观覆盖
	$new = preg_replace('/<body class="[^"]*"[^>]*>/', '<body class="'.$class.'" data-theme-synced="1">', $html, 1, $count);
	if(!$count || $new === null)return false;
	if($new === $html)return true;
	return @file_put_contents($file, $new, LOCK_EX) !== false;
}

/*
 * 后台 ajax.php?act=set 允许写入的配置键白名单。
 * 这个接口原来是 foreach($_POST) 全部落库，任何能在后台页面里发请求的脚本
 * （比如以前那个第三方 JSONP）都能顺手改掉 admin_pwd、存储密钥等敏感项。
 * 管理员账号和密码走 set_account.php 自己的表单（要验旧密码），不从这里改。
 */
function admin_setting_keys(){
	return [
		'aliyun_ak', 'aliyun_sk', 'api_open', 'api_referer',
		'apiurl', 'blackip', 'description', 'downfile_domain',
		'downfile_protocol', 'downfile_type', 'filepath', 'filesearch',
		'forcelogin', 'gg_file', 'gonggao', 'green_check',
		'green_check_porn', 'green_check_region', 'green_check_terrorism', 'green_label_porn',
		'green_self_api', 'green_self_token', 'green_self_block', 'green_self_review',
		'green_self_timeout',
		'green_label_terrorism', 'ip_type', 'keywords', 'login_apiurl',
		'login_appid', 'login_appkey', 'login_qq', 'login_wx',
		'name_block', 'obs_ak', 'obs_bucket', 'obs_endpoint',
		'obs_sk', 'online_edit_mode', 'online_edit_uids', 'oss_ak',
		'oss_bucket', 'oss_endpoint', 'oss_sk', 'qcloud_bucket',
		'qcloud_green_id', 'qcloud_green_key', 'qcloud_id', 'qcloud_key',
		'qcloud_region', 'qiniu_ak', 'qiniu_bucket', 'qiniu_domain',
		'qiniu_sk', 's3_ak', 's3_bucket', 's3_endpoint',
		's3_path_style', 's3_prefix', 's3_region', 's3_sk',
		'webdav_url', 'webdav_user', 'webdav_pass', 'webdav_path',
		//onedrive_refresh_token / access_token 由授权流程自己写，不从表单进来
		'onedrive_type', 'onedrive_client_id', 'onedrive_client_secret', 'onedrive_path',
		'site_theme', 'storage', 'storagename', 'title',
		'tongji', 'type_audio', 'type_block', 'type_image',
		'type_video', 'upload_limit', 'upload_per_minute', 'upload_size', 'uploadfile_type',
		'upyun_name', 'upyun_pwd', 'upyun_user', 'userlogin',
		'videoreview', 'violation_notice', 'violation_open',
		'alipay_open', 'alipay_appid', 'alipay_public_key', 'alipay_private_key',
		'epay_open', 'epay_apiurl', 'epay_pid', 'epay_key', 'epay_type', 'epay_charset',
		'pay_subject',
		'buy_notice',
		//邮件发信：没有默认通道，勾选哪个用哪个，一个都不勾就是关闭
		'mail_from', 'mail_from_name',
		'mail_smtp_open', 'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_secure',
		'mail_smtp_user', 'mail_smtp_pass',
		'mail_resend_open', 'mail_resend_key',
		'mail_brevo_open', 'mail_brevo_key',
		'mail_sendgrid_open', 'mail_sendgrid_key',
		'mail_reg_open', 'mail_code_expire', 'mail_send_interval',
		'mail_daily_limit', 'mail_ip_daily_limit', 'mail_domain_deny',
		'mail_hour_limit', 'mail_site_daily', 'mail_captcha_open',
	];
}

function is_admin_setting_key($k){
	return in_array($k, admin_setting_keys(), true);
}

function saveSetting($k, $v){
	global $DB;
	return $DB->exec("REPLACE INTO pre_config SET v=:v,k=:k", [':v'=>$v, ':k'=>$k]);
}

function size_format($size)
{
	if ($size<1024) {
		$size.=' B';
	} else {
		$size/=1024;
		if ($size<1024) {
			$size=round($size, 2).' KB';
		} else {
			$size/=1024;
			if ($size<1024) {
				$size=round($size, 2).' MB';
			} else {
				$size/=1024;
				if ($size<1024) {
					$size=round($size, 2).' GB';
				}
			}
		}
	}
	return $size;
}

function is_user_permission_active($row = null){
	global $islogin2, $userrow;
	if($row === null) $row = $userrow;
	if(empty($islogin2) || empty($row)) return false;
	if(empty($row['expiretime'])) return true;
	return strtotime($row['expiretime']) > time();
}

function get_effective_upload_size_limit(){
	global $conf, $islogin2, $userrow;
	if(!empty($islogin2) && is_user_permission_active() && isset($userrow['upload_size']) && $userrow['upload_size'] !== null && intval($userrow['upload_size']) >= 0){
		return intval($userrow['upload_size']);
	}
	return isset($conf['upload_size']) ? intval($conf['upload_size']) : 0;
}

function get_effective_upload_count_limit(){
	global $conf, $islogin2, $userrow;
	$active = !empty($islogin2) && is_user_permission_active();
	//加量包买的额度加在基础数量上；基础是“不限”时加多少都没意义
	$bonus = ($active && isset($userrow['bonus_limit'])) ? max(0, intval($userrow['bonus_limit'])) : 0;
	if($active && isset($userrow['upload_limit']) && $userrow['upload_limit'] !== null && intval($userrow['upload_limit']) >= 0){
		$base = intval($userrow['upload_limit']);
		return $base === 0 ? 0 : $base + $bonus;
	}
	if($active && isset($userrow['level']) && intval($userrow['level']) > 0){
		return 0;
	}
	$base = isset($conf['upload_limit']) ? intval($conf['upload_limit']) : 0;
	return $base === 0 ? 0 : $base + $bonus;
}

function minetype($type){
	$mime = array (
	//applications
	'ai'  => 'application/postscript',
	'eps'  => 'application/postscript',
	'exe'  => 'application/octet-stream',
	'doc'  => 'application/vnd.ms-word',
	'xls'  => 'application/vnd.ms-excel',
	'ppt'  => 'application/vnd.ms-powerpoint',
	'pps'  => 'application/vnd.ms-powerpoint',
	'pdf'  => 'application/pdf',
	'xml'  => 'application/xml',
	'odt'  => 'application/vnd.oasis.opendocument.text',
	'swf'  => 'application/x-shockwave-flash',
	// archives
	'gz'  => 'application/x-gzip',
	'tgz'  => 'application/x-gzip',
	'bz'  => 'application/x-bzip2',
	'bz2'  => 'application/x-bzip2',
	'tbz'  => 'application/x-bzip2',
	'zip'  => 'application/zip',
	'rar'  => 'application/x-rar',
	'tar'  => 'application/x-tar',
	'7z'  => 'application/x-7z-compressed',
	// texts
	'txt'  => 'text/plain',
	'php'  => 'text/x-php',
	'html' => 'text/html',
	'htm'  => 'text/html',
	'js'  => 'text/javascript',
	'css'  => 'text/css',
	'rtf'  => 'text/rtf',
	'rtfd' => 'text/rtfd',
	'py'  => 'text/x-python',
	'java' => 'text/x-java-source',
	'rb'  => 'text/x-ruby',
	'sh'  => 'text/x-shellscript',
	'pl'  => 'text/x-perl',
	'sql'  => 'text/x-sql',
	// images
	'bmp'  => 'image/x-ms-bmp',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'gif'  => 'image/gif',
	'png'  => 'image/png',
	'tif'  => 'image/tiff',
	'tiff' => 'image/tiff',
	'tga'  => 'image/x-targa',
	'ico'  => 'image/x-icon',
	'svg'  => 'image/x-svgz',
	'svgz'  => 'image/x-svgz',
	'webp'  => 'image/webp',
	'psd'  => 'image/vnd.adobe.photoshop',
	'heic' => 'image/x-heic',
	'exif' => 'image/jpeg',
	//audio
	'mp3'  => 'audio/mpeg',
	'mid'  => 'audio/midi',
	'ogg'  => 'audio/ogg',
	'mp4a' => 'audio/mp4',
	'm4a' => 'audio/m4a',
	'wav'  => 'audio/wav',
	'wma'  => 'audio/x-ms-wma',
	// video
	'avi'  => 'video/x-msvideo',
	'dv'  => 'video/x-dv',
	'mp4'  => 'video/mp4',
	'f4v'  => 'video/x-flv',
	'mpeg' => 'video/mpeg',
	'mpg'  => 'video/mpeg',
	'mov'  => 'video/quicktime',
	'wmv'  => 'video/x-ms-wmv',
	'flv'  => 'video/x-flv',
	'mkv'  => 'video/x-matroska',
	'ts'  => 'video/x-flv',
	'3gp'  => 'video/3gpp',
	'3gpp'  => 'video/3gpp',
	'webm'  => 'video/webm',
	);
	return isset($mime[$type]) ? $mime[$type] : 'application/octet-stream';
}

function type_to_icon($type){
	global $conf;
	$type_image = explode('|',$conf['type_image']);
	$type_audio = explode('|',$conf['type_audio']);
	$type_video = explode('|',$conf['type_video']);
	$type_image = array_merge($type_image, ['png','jpg','jpeg','gif','bmp','webp','ico','svg','svgz','tif','tiff','heic','psd','exif','pcx','tga','fpx','cdr','pcd','eps','ai','wmf','raw','ufo','jpc','jp2','jpx','xbm','wbmp','avif']);
	$type_audio = array_merge($type_audio, ['mp3','wav','wma','ogg','m4a','flac','ape','aac','ra','cda','midi','mid','aif','au','voc']);
	$type_video = array_merge($type_video, ['mp4','webm','flv','f4v','mov','3gp','3gpp','avi','mpg','mpeg','wmv','mkv','ts','dat','asf','rm','rmvb','ram','divx','vob','qt','fli','flc','mod','m2t','swf','mts','m2ts','mpe','div','lavf','m3u8','m4v','ogm','ogv']);
	$type_text = ['txt','text','log','md','yaml','yml','conf','config','ini'];
	$type_code = ['c','cpp','cxx','rc','php','py','cs','h','htm','html','css','less','js','hdml','dtd','wml','xml','vbs','vb','rtx','xsd','dpr','sql','java','go','jsp','asp','aspx','asa','asax','pl','bat','cmd','rb','reg','sh','json','lua','r','mm','mak','swift','tpl'];
	$type_archive = ['zip','7z','rar','tgz','gz','xz','tar','jar','iso','z','zipx','cab','bz2','arj','lz','lzh'];
	$type_word = ['doc','docx','xps','rtf','wps','odt'];
	$type_excel = ['xls','xlsx','ods'];
	$type_pdf = ['pdf'];
	$type_powerpoint = ['ppt','pptx','pptm'];
	$type_android = ['apk'];
	$type_apple = ['ipa','dmg'];
	$type_windows = ['exe','appx','msi'];
	$type_linux = ['deb','rpm'];
	if(in_array($type, $type_image)){
		return 'fa-file-image-o';
	}elseif(in_array($type, $type_audio)){
		return 'fa-file-audio-o';
	}elseif(in_array($type, $type_video)){
		return 'fa-file-video-o';
	}elseif(in_array($type, $type_text)){
		return 'fa-file-text-o';
	}elseif(in_array($type, $type_code)){
		return 'fa-file-code-o';
	}elseif(in_array($type, $type_archive)){
		return 'fa-file-archive-o';
	}elseif(in_array($type, $type_word)){
		return 'fa-file-word-o';
	}elseif(in_array($type, $type_excel)){
		return 'fa-file-excel-o';
	}elseif(in_array($type, $type_pdf)){
		return 'fa-file-pdf-o';
	}elseif(in_array($type, $type_powerpoint)){
		return 'fa-file-powerpoint-o';
	}elseif(in_array($type, $type_android)){
		return 'fa-android';
	}elseif(in_array($type, $type_apple)){
		return 'fa-apple';
	}elseif(in_array($type, $type_windows)){
		return 'fa-windows';
	}elseif(in_array($type, $type_linux)){
		return 'fa-linux';
	}else{
		return 'fa-file-o';
	}
}

/*
 * 浏览器会当成"可执行文档"解析的类型：SVG 内部允许写 <script>，HTML/XHTML/XML 更不用说。
 * 这些内容只要以 inline 的形式挂在本站域名下，就是一个现成的存储型 XSS，
 * 所以无论后台把它们配成什么，file_output() 都只按附件下载处理。
 */
function is_scriptable_file_type($type){
	return in_array(strtolower(trim((string)$type)), ['svg','svgz','html','htm','xhtml','xht','shtml','xml','mhtml','mht','swf'], true);
}

function is_view($type){
	global $conf;
	$type_image = explode('|',$conf['type_image']);
	$type_audio = explode('|',$conf['type_audio']);
	$type_video = explode('|',$conf['type_video']);
	if (in_array($type, $type_image) || in_array($type, $type_audio) || in_array($type, $type_video)) {
		return true;
	}
	return false;
}

function get_view_type($type){
	global $conf;
	$type_image = explode('|',$conf['type_image']);
	$type_audio = explode('|',$conf['type_audio']);
	$type_video = explode('|',$conf['type_video']);
	$type_office = ['doc','docx','xps','rtf','wps','xls','xlsx','ppt','pptx'];
	if (in_array($type, $type_image)) {
		return 'image';
	}elseif (in_array($type, $type_audio)) {
		return 'audio';
	}elseif (in_array($type, $type_video)) {
		return 'video';
	}elseif (in_array($type, $type_office)) {
		return 'office';
	}
	return false;
}

function get_editable_file_types(){
	return ['txt','text','log','md','markdown','json','js','mjs','css','less','scss','html','htm','xml','svg','csv','ini','conf','config','yaml','yml','sql','vue','ts','tsx','jsx'];
}

function is_editable_file_type($type){
	$type = strtolower(trim((string)$type));
	return $type !== '' && in_array($type, get_editable_file_types(), true);
}

function get_online_edit_mode(){
	global $conf;
	$mode = isset($conf['online_edit_mode']) ? strtolower(trim((string)$conf['online_edit_mode'])) : 'all';
	return in_array($mode, ['all', 'login', 'uid'], true) ? $mode : 'all';
}

function get_online_edit_uid_whitelist(){
	global $conf;
	$value = isset($conf['online_edit_uids']) ? trim((string)$conf['online_edit_uids']) : '';
	if($value === '') return [];
	$value = str_replace(['，', '|'], [',', ','], $value);
	$items = preg_split('/[\s,]+/', $value);
	$uids = [];
	foreach((array)$items as $item){
		$item = trim($item);
		if($item === '' || !ctype_digit($item)) continue;
		$uids[] = intval($item);
	}
	return array_values(array_unique($uids));
}

function can_use_online_edit(){
	global $islogin2, $uid;
	$mode = get_online_edit_mode();
	if($mode === 'all'){
		return true;
	}
	if(empty($islogin2)){
		return false;
	}
	if($mode === 'login'){
		return true;
	}
	return in_array(intval($uid), get_online_edit_uid_whitelist(), true);
}

function can_edit_file_online($row){
	if(!$row) return false;
	if(!is_editable_file_type(isset($row['type']) ? $row['type'] : null)) return false;
	if(!can_manage_file($row)) return false;
	return can_use_online_edit();
}

function get_editable_file_max_size(){
	return 2 * 1024 * 1024;
}

function can_manage_file($row){
	global $islogin2, $uid;
	if(!$row)return false;
	if(!empty($islogin2)){
		return intval($row['uid']) === intval($uid);
	}
	return isset($_SESSION['fileids']) && in_array($row['id'], $_SESSION['fileids']) && strtotime($row['addtime']) > strtotime("-7 days");
}

/*
 * 文件访问密码校验。所有对外输出文件内容的入口都必须走这里，别再各写各的。
 *
 * 一定要用 hash_equals 做二进制比较：'=='/'!=' 会把两个纯数字串按数值比，
 * 密码允许字母数字，'0e12345' 这种是合法的科学计数法数字串，输 '0' 就能对上。
 */
function check_file_pwd($row, $pwd){
	if(!is_array($row))return false;
	if(!isset($row['pwd']) || $row['pwd'] === null || $row['pwd'] === '')return true;
	return hash_equals((string)$row['pwd'], (string)$pwd);
}

/*
 * 签发/清除登录态 Cookie。
 *
 * HttpOnly 必须开：admin_token / user_token 有效期 30 天，被 JS 读到就等于账号被永久接管，
 * 站内任何一处 XSS 都会直接升级成后台失陷。SameSite=Lax 再挡一道跨站携带。
 * setcookie 的数组写法要 PHP 7.3+，本程序最低支持 7.1，低版本走 path 拼接的兼容写法。
 */
function set_auth_cookie($name, $value, $expire, $path = '/'){
	$secure = is_https();
	if(PHP_VERSION_ID >= 70300){
		return setcookie($name, $value, [
			'expires'  => $expire,
			'path'     => $path,
			'httponly' => true,
			'secure'   => $secure,
			'samesite' => 'Lax',
		]);
	}
	//7.3 以下没有 samesite 参数，只能把它拼在 path 后面，浏览器照样能识别
	return setcookie($name, $value, $expire, $path.'; samesite=Lax', '', $secure, true);
}

function storage_content_to_string($content){
	if($content === false)return false;
	if(is_resource($content)){
		return stream_get_contents($content);
	}
	if(is_object($content)){
		if(method_exists($content, 'getContents')){
			return $content->getContents();
		}
		if(method_exists($content, '__toString')){
			return (string)$content;
		}
	}
	return (string)$content;
}

function get_storage_content($hash){
	global $stor;
	return storage_content_to_string($stor->get($hash));
}

function is_utf8_editable_content($content){
	if(strpos($content, "\0") !== false)return false;
	if(function_exists('mb_check_encoding')){
		return mb_check_encoding($content, 'UTF-8');
	}
	return preg_match('//u', $content) === 1;
}

function convert_content_to_utf8($content, $encoding){
	if(strtoupper($encoding) === 'UTF-8')return $content;
	if(function_exists('mb_convert_encoding')){
		try {
			$result = @mb_convert_encoding($content, 'UTF-8', $encoding);
		} catch(\Throwable $e) {
			$result = false;
		}
		return $result === false ? false : $result;
	}
	if(function_exists('iconv')){
		$result = @iconv($encoding, 'UTF-8', $content);
		return $result === false ? false : $result;
	}
	return false;
}

function is_content_encoding($content, $encoding){
	if(function_exists('mb_check_encoding')){
		try {
			return @mb_check_encoding($content, $encoding);
		} catch(\Throwable $e) {
			return false;
		}
	}
	if(function_exists('iconv')){
		$result = @iconv($encoding, 'UTF-8', $content);
		return $result !== false;
	}
	return false;
}

function decode_editable_content($content){
	if(strpos($content, "\0") !== false){
		return ['code'=>-1, 'msg'=>'该文件像是二进制文件，不能在线编辑'];
	}
	if(is_utf8_editable_content($content)){
		return ['code'=>0, 'content'=>$content, 'encoding'=>'UTF-8', 'converted'=>false];
	}

	$encodings = ['GB18030', 'GBK', 'GB2312', 'BIG5', 'BIG5-HKSCS'];
	foreach($encodings as $encoding){
		if(!is_content_encoding($content, $encoding))continue;
		$converted = convert_content_to_utf8($content, $encoding);
		if($converted !== false && is_utf8_editable_content($converted)){
			return ['code'=>0, 'content'=>$converted, 'encoding'=>$encoding, 'converted'=>true];
		}
	}

	return ['code'=>-1, 'msg'=>'无法识别文件编码，请先转换为 UTF-8 后再编辑'];
}

function save_storage_content($hash, $content, $type){
	global $stor;
	$tmpfile = tempnam(sys_get_temp_dir(), 'edit_');
	if($tmpfile === false)return false;
	if(file_put_contents($tmpfile, $content) === false){
		@unlink($tmpfile);
		return false;
	}
	$result = $stor->savefile($hash, $tmpfile, minetype($type));
	if(file_exists($tmpfile)){
		@unlink($tmpfile);
	}
	return $result;
}


/*
 * 图片违规检测的分发入口。
 * 返回 '' 通过 / 'block' 直接封禁 / 'review' 转人工复核（block=2，前台同样不可下载）。
 * 云接口只有命中和不命中两种结果，所以只会返回 '' 或 'block'；自建模型误判率高得多，
 * 多留一档 review 交给人工看，别一刀切封掉。
 * '' 是假值，原先 if(checkImage(...)) 那种写法依然成立。
 */
function checkImage($hash, $ext, $ctx = []){
	global $conf,$siteurl;
	$apiurl = $conf['apiurl']?$conf['apiurl']:$siteurl;
	$fileurl = $apiurl.'view.php/'.$hash.'.'.$ext.'?greencheck=1';
	$started = microtime(true);
	$engine = '';
	$score = 0;
	$detail = '';
	$verdict = '';

	if($conf['green_check'] == 1){
		$engine = 'aliyun';
		$verdict = checkImage_aliyun($fileurl) ? 'block' : '';
	}elseif($conf['green_check'] == 2){
		$engine = 'qcloud';
		$verdict = checkImage_qcloud($fileurl) ? 'block' : '';
	}elseif($conf['green_check'] == 3){
		$engine = 'self';
		$res = checkImage_self($hash, $fileurl);
		$verdict = $res['verdict'];
		$score = $res['score'];
		$detail = $res['detail'];
	}else{
		return '';
	}

	//检测记录：后台「图片检测记录」那一页读的就是这张表。
	//放行的也记，不然没法回头判断阈值定得高了还是低了
	add_green_log([
		'engine' => $engine,
		'score' => $score,
		'detail' => $detail,
		'verdict' => $verdict === '' ? 'pass' : $verdict,
		'ms' => intval((microtime(true) - $started) * 1000),
		'hash' => $hash,
		'type' => $ext,
	] + $ctx);

	return $verdict;
}

/*
 * 写一条检测记录。表是 1019 版新增的，老站点没跑升级也不能因此报错，
 * 所以这里失败了就静默跳过——记录丢了是小事，挡住上传是大事。
 */
function add_green_log($row){
	global $DB, $clientip;
	/*
	 * 这里不能用 $DB->insert()：它把「值等于空」的字段写成 NULL，而 PHP 7 里
	 * 0 == '' 成立，score / ms / uid 这几个为 0 的列就会被写成 NULL，
	 * 撞上 NOT NULL 直接插入失败。所以老老实实自己拼 SQL 绑参数。
	 */
	$sql = "INSERT INTO pre_greenlog (`file_id`,`name`,`type`,`hash`,`engine`,`score`,`detail`,`verdict`,`ms`,`uid`,`ip`,`addtime`)"
		." VALUES (:file_id,:name,:type,:hash,:engine,:score,:detail,:verdict,:ms,:uid,:ip,:addtime)";
	return @$DB->exec($sql, [
		':file_id' => isset($row['id']) ? intval($row['id']) : 0,
		':name' => isset($row['name']) ? mb_substr((string)$row['name'], 0, 250, 'UTF-8') : '',
		':type' => isset($row['type']) ? (string)$row['type'] : '',
		':hash' => isset($row['hash']) ? (string)$row['hash'] : '',
		':engine' => isset($row['engine']) ? (string)$row['engine'] : '',
		':score' => isset($row['score']) ? round(floatval($row['score']), 4) : 0,
		':detail' => isset($row['detail']) ? mb_substr((string)$row['detail'], 0, 250, 'UTF-8') : '',
		':verdict' => isset($row['verdict']) ? (string)$row['verdict'] : 'pass',
		':ms' => isset($row['ms']) ? intval($row['ms']) : 0,
		':uid' => isset($row['uid']) ? intval($row['uid']) : 0,
		':ip' => isset($row['ip']) ? (string)$row['ip'] : (isset($clientip) ? $clientip : ''),
		':addtime' => date('Y-m-d H:i:s'),
	]);
}

/*
 * 自建检测服务：把图片交给本机跑的模型服务打分（tools/nsfw/server.py）。
 * 本地存储直接给绝对路径，省掉一次 HTTP 回环；云存储只能给 URL 让服务自己去抓。
 * 阈值以后台设置为准，随请求一起发过去，改完不用重启检测服务。
 *
 * 服务连不上、超时、返回看不懂，一律当没命中放行——不能因为检测服务挂了就把正常
 * 上传卡死，宁可漏检也不能误伤，出了什么事日志里有记录。
 */
function checkImage_self($hash, $fileurl){
	global $conf, $stor;
	if(!function_exists('curl_init')){
		writeLog('self green check: 服务器没开启 curl 扩展');
		return ['verdict'=>'', 'score'=>0, 'detail'=>'服务器没开启 curl'];
	}
	$api = isset($conf['green_self_api']) && trim($conf['green_self_api']) !== '' ? trim($conf['green_self_api']) : 'http://127.0.0.1:9012/check';
	$block = isset($conf['green_self_block']) && $conf['green_self_block'] !== '' ? floatval($conf['green_self_block']) : 0.85;
	$review = isset($conf['green_self_review']) && $conf['green_self_review'] !== '' ? floatval($conf['green_self_review']) : 0.60;
	$timeout = isset($conf['green_self_timeout']) && intval($conf['green_self_timeout']) > 0 ? intval($conf['green_self_timeout']) : 5;

	$payload = ['block'=>$block, 'review'=>$review];
	//本地存储能拿到真实路径，让检测服务直接读盘，比再绕一次 view.php 快得多
	$path = ($conf['storage'] === 'local' && is_object($stor) && method_exists($stor, 'filepath')) ? $stor->filepath($hash) : '';
	if($path !== '' && is_file($path)){
		$payload['path'] = $path;
	}else{
		$payload['url'] = $fileurl;
	}

	$headers = ['Content-Type: application/json'];
	if(!empty($conf['green_self_token']))$headers[] = 'X-Auth-Token: '.$conf['green_self_token'];

	$ch = curl_init($api);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	$body = curl_exec($ch);
	$status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
	$error = curl_error($ch);
	curl_close($ch);

	if($body === false || $status !== 200){
		writeLog('self green check 失败（HTTP '.$status.'）：'.($error ? $error : substr((string)$body, 0, 200)));
		return ['verdict'=>'', 'score'=>0, 'detail'=>'检测失败 HTTP '.$status];
	}
	$json = json_decode($body, true);
	if(!is_array($json) || empty($json['ok']) || !isset($json['score'])){
		writeLog('self green check 返回异常：'.substr((string)$body, 0, 200));
		return ['verdict'=>'', 'score'=>0, 'detail'=>'返回内容无法识别'];
	}
	$score = floatval($json['score']);
	//每个模型各打了多少分，记进日志方便回头判断是哪个模型误判
	$parts = [];
	if(isset($json['detail']) && is_array($json['detail'])){
		foreach($json['detail'] as $name => $info){
			if(!is_array($info))continue;
			if(isset($info['nsfw'])){
				$parts[] = $name.'='.round(floatval($info['nsfw']), 4);
			}elseif(isset($info['explicit'])){
				$parts[] = $name.'=explicit '.round(floatval($info['explicit']), 4).'/questionable '.round(floatval(isset($info['questionable'])?$info['questionable']:0), 4);
			}elseif(isset($info['error'])){
				$parts[] = $name.'=出错';
			}
		}
	}
	$verdict = '';
	if($score >= $block){
		$verdict = 'block';
	}elseif($score >= $review){
		$verdict = 'review';
	}
	return ['verdict'=>$verdict, 'score'=>$score, 'detail'=>implode('  ', $parts)];
}
function checkImage_aliyun($fileurl){
	global $conf;
	$scenes = [];
	if ($conf['green_check_porn']==1) {
		$scenes[] = 'porn';
		$label_porn = explode(',', $conf['green_label_porn']);
	}
	if ($conf['green_check_terrorism']==1) {
		$scenes[] = 'terrorism';
		$label_terrorism = explode(',', $conf['green_label_terrorism']);
	}
	if(count($scenes)==0)return false;

	$client = new \lib\AliyunGreen($conf['aliyun_ak'], $conf['aliyun_sk'], $conf['green_check_region']);
	$task1 = array('dataId' => uniqid(), 'url' => $fileurl);
	$request = array("tasks" => array($task1), "scenes" => $scenes);

try {
	$response = $client->doCheck($request);
	//print_r($response);
	if(200 == $response->code){
		$taskResults = $response->data;
		foreach ($taskResults as $taskResult) {
			if(200 == $taskResult->code){
				$sceneResults = $taskResult->results;
				foreach ($sceneResults as $sceneResult) {
					$scene = $sceneResult->scene;
					$label = $sceneResult->label;
					$suggestion = $sceneResult->suggestion;
					if($scene == 'porn' && (in_array($label, $label_porn) || $suggestion == 'block')){
						return true;
					}elseif($scene == 'terrorism' && (in_array($label, $label_terrorism) || $suggestion == 'block')){
						return true;
					}
				}
			}else{
				writeLog("task process fail:" . $taskResult->code . ' ' . $taskResult->msg);
			}
		}
	}else{
		writeLog("detect not success. code:" . $response->code . ' ' . $response->msg);
	}
} catch (Exception $e) {
	print_r($e);
}
return false;
}
function checkImage_qcloud($fileurl){
	global $conf;
	$client = new \lib\QcloudGreen($conf['qcloud_green_id'], $conf['qcloud_green_key'], $conf['green_check_region']);
	$result = $client->ImageModeration($fileurl);
	if(isset($result['Suggestion'])){
		if($result['Suggestion'] == 'Block'){
			return true;
		}
	}else{
		writeLog('detect not success.['.$result['Error']['Code'].']'.$result['Error']['Message']);
	}
	return false;
}
function writeLog($text) {
	file_put_contents ( SYSTEM_ROOT."log.txt", date ( "Y-m-d H:i:s" ) . "  " . $text . "\r\n", FILE_APPEND );
}

/**
 * 后台登录限速
 * 原来只有 $_SESSION['pass_error'] 一个计数器，攻击者不带Cookie请求就能重置，等于没有限制。
 * 这里把失败次数按IP记在服务端文件里，跟客户端会话无关。
 */
function login_throttle_file(){
	return sys_get_temp_dir().'/pan_loginfail_'.substr(md5(SYS_KEY.'|loginthrottle'), 0, 16).'.json';
}

function login_throttle_load(){
	$file = login_throttle_file();
	if(!is_file($file))return [];
	$raw = @file_get_contents($file);
	if($raw === false || $raw === '')return [];
	$data = json_decode($raw, true);
	return is_array($data) ? $data : [];
}

function login_throttle_save($data){
	@file_put_contents(login_throttle_file(), json_encode($data), LOCK_EX);
}

//返回还需要锁定的秒数，0表示可以继续尝试
function login_throttle_locked($ip, $max = 5, $lock = 900){
	$key = md5((string)$ip);
	$data = login_throttle_load();
	if(!isset($data[$key]) || !is_array($data[$key]))return 0;
	$count = isset($data[$key]['count']) ? intval($data[$key]['count']) : 0;
	$last = isset($data[$key]['last']) ? intval($data[$key]['last']) : 0;
	if($count < $max)return 0;
	$remain = $last + $lock - time();
	return $remain > 0 ? $remain : 0;
}

function login_throttle_fail($ip, $lock = 900){
	$key = md5((string)$ip);
	$now = time();
	$data = login_throttle_load();
	//顺手清掉已过期的记录：文件不会无限增长，锁定期满的IP计数也自然归零
	foreach($data as $k => $v){
		if(!is_array($v) || (isset($v['last']) && intval($v['last']) + $lock < $now))unset($data[$k]);
	}
	$count = isset($data[$key]['count']) ? intval($data[$key]['count']) : 0;
	$data[$key] = ['count' => $count + 1, 'last' => $now];
	login_throttle_save($data);
}

function login_throttle_reset($ip){
	$key = md5((string)$ip);
	$data = login_throttle_load();
	if(isset($data[$key])){
		unset($data[$key]);
		login_throttle_save($data);
	}
}

function generate_file_token(){
	global $DB;
	do{
		$token = bin2hex(random_bytes(16));
	}while($DB->getColumn("SELECT id FROM pre_file WHERE token=:token", [':token'=>$token]));
	return $token;
}

//仅当没有其它记录仍引用该hash对应的物理文件时才删除，避免误删被去重共享的存储文件
function delete_file_blob_if_orphaned($hash, $exclude_id = null){
	global $DB, $stor;
	$params = [':hash'=>$hash];
	$sql = "SELECT id FROM pre_file WHERE hash=:hash";
	if($exclude_id){
		$sql .= " AND id!=:id";
		$params[':id'] = $exclude_id;
	}
	if($DB->getColumn($sql." LIMIT 1", $params)){
		return;
	}
	$stor->delete($hash);
}

//覆盖上传审计：记下这次覆盖的前后内容，管理员在后台“覆盖记录”里复查有没有换成违规内容
function add_replace_log($old, $new, $uid, $ip, $source = 'replace'){
	global $DB;
	if(!is_array($old) || empty($old['id']))return false;
	$data = [
		':file_id' => intval($old['id']),
		':token' => isset($old['token']) ? $old['token'] : '',
		':old_name' => isset($old['name']) ? $old['name'] : '',
		':old_type' => isset($old['type']) ? $old['type'] : '',
		':old_size' => isset($old['size']) ? intval($old['size']) : 0,
		':old_hash' => isset($old['hash']) ? $old['hash'] : '',
		':new_name' => isset($new['name']) ? $new['name'] : '',
		':new_type' => isset($new['type']) ? $new['type'] : '',
		':new_size' => isset($new['size']) ? intval($new['size']) : 0,
		':new_hash' => isset($new['hash']) ? $new['hash'] : '',
		':uid' => intval($uid),
		':ip' => $ip,
		':source' => $source,
	];
	return $DB->exec("INSERT INTO `pre_replace_log` (`file_id`,`token`,`old_name`,`old_type`,`old_size`,`old_hash`,`new_name`,`new_type`,`new_size`,`new_hash`,`uid`,`ip`,`source`,`checked`,`addtime`) VALUES (:file_id,:token,:old_name,:old_type,:old_size,:old_hash,:new_name,:new_type,:new_size,:new_hash,:uid,:ip,:source,0,NOW())", $data) !== false;
}

//覆盖上传：把已有记录的内容换成新文件，token（也就是对外链接）保持不变。
//换了内容必须重新过审并留审计记录，否则可以先传一个正常文件、事后覆盖成违规内容绕过检测。
function replace_file_record($old, $name, $hash, $size, $ext, $uid, $ip, $source = 'replace'){
	global $DB, $conf;
	if(!is_array($old) || empty($old['id']))return false;
	$id = intval($old['id']);
	$old_hash = isset($old['hash']) ? $old['hash'] : '';
	$ok = $DB->exec("UPDATE `pre_file` SET `name`=:name,`type`=:type,`size`=:size,`hash`=:hash,`block`=0,`lasttime`=NOW() WHERE `id`=:id LIMIT 1", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':id'=>$id]);
	if($ok === false)return false;

	//旧内容如果没有别的记录再引用，把物理文件清掉，别留垃圾
	if($old_hash !== '' && $old_hash !== $hash)delete_file_blob_if_orphaned($old_hash, $id);

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		$verdict = checkImage($hash, $ext, ['id'=>$id, 'name'=>$name, 'uid'=>$uid, 'ip'=>$ip]);
		if($verdict === 'block'){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`=:id LIMIT 1", [':id'=>$id]);
			add_violation_log(['id'=>$id, 'name'=>$name, 'type'=>$ext, 'size'=>$size, 'hash'=>$hash, 'ip'=>$ip, 'uid'=>$uid], 'green', '覆盖上传后图片自动检测命中', 0);
		}elseif($verdict === 'review'){
			//分数落在中间档：先按待审处理，前台下不下来，等后台人工看过再放行
			$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`=:id LIMIT 1", [':id'=>$id]);
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`=:id LIMIT 1", [':id'=>$id]);
	}

	add_replace_log($old, ['name'=>$name, 'type'=>$ext, 'size'=>$size, 'hash'=>$hash], $uid, $ip, $source);
	return true;
}

//秒传：内容已经在存储里了，物理文件不用再传，但仍要为这次上传建一条独立记录，
//否则上传者在“我的文件”里看不到，文件只挂在最早那个上传者名下。
//另外已被封禁的内容要继承封禁状态，不然换个人重传一遍就能绕过封禁。
function create_file_record_from_existing($existing, $name, $size, $ext, $hide, $pwd, $uid, $ip){
	global $DB;
	$record = create_file_record($name, $existing['hash'], $size, $ext, $hide, $pwd, $uid, $ip, false);
	if(!$record)return false;
	$block = isset($existing['block']) ? intval($existing['block']) : 0;
	if($block >= 1){
		$DB->exec("UPDATE `pre_file` SET `block`=:block WHERE `id`=:id LIMIT 1", [':block'=>$block, ':id'=>$record['id']]);
	}
	return $record;
}

//违规公示留档：文件被封禁时记一份快照，原文件记录被删掉后公示仍然保留
function add_violation_log($file, $source = 'admin', $remark = null, $is_show = 1){
	global $DB;
	if(!is_array($file) || empty($file['id']))return false;
	$data = [
		':file_id' => intval($file['id']),
		':name' => isset($file['name']) ? $file['name'] : '',
		':type' => isset($file['type']) ? $file['type'] : '',
		':size' => isset($file['size']) ? intval($file['size']) : 0,
		':hash' => isset($file['hash']) ? $file['hash'] : '',
		':ip' => isset($file['ip']) ? $file['ip'] : '',
		':uid' => isset($file['uid']) ? intval($file['uid']) : 0,
		':source' => $source,
		':remark' => $remark,
		':is_show' => intval($is_show) == 1 ? 1 : 0,
	];
	//同一个文件重复封禁时只更新原记录，公示页不会出现重复条目
	$exists = $DB->getColumn("SELECT `id` FROM pre_violation WHERE `file_id`=:file_id LIMIT 1", [':file_id'=>$data[':file_id']]);
	if($exists){
		unset($data[':file_id']);
		$data[':id'] = $exists;
		return $DB->exec("UPDATE `pre_violation` SET `name`=:name,`type`=:type,`size`=:size,`hash`=:hash,`ip`=:ip,`uid`=:uid,`source`=:source,`remark`=:remark,`is_show`=:is_show WHERE `id`=:id", $data) !== false;
	}
	return $DB->exec("INSERT INTO `pre_violation` (`file_id`,`name`,`type`,`size`,`hash`,`ip`,`uid`,`source`,`remark`,`is_show`,`addtime`) VALUES (:file_id,:name,:type,:size,:hash,:ip,:uid,:source,:remark,:is_show,NOW())", $data) !== false;
}

//文件解封说明是误判，撤下公示但保留记录，方便后台复查
function revoke_violation_log($file_id){
	global $DB;
	$file_id = intval($file_id);
	if(!$file_id)return false;
	return $DB->exec("UPDATE `pre_violation` SET `is_show`=0 WHERE `file_id`=:file_id", [':file_id'=>$file_id]) !== false;
}

//公示页脱敏：文件名只保留首尾若干字符和扩展名
function violation_mask_name($name){
	$name = (string)$name;
	if($name === '')return '未命名文件';
	$ext = '';
	$pos = strrpos($name, '.');
	if($pos !== false && $pos > 0){
		$ext = substr($name, $pos);
		$name = substr($name, 0, $pos);
	}
	$len = mb_strlen($name, 'UTF-8');
	if($len <= 1){
		$base = '*';
	}elseif($len <= 4){
		$base = mb_substr($name, 0, 1, 'UTF-8').'***';
	}else{
		$base = mb_substr($name, 0, 2, 'UTF-8').'***'.mb_substr($name, -2, 2, 'UTF-8');
	}
	return $base.$ext;
}

//公示页脱敏：IP只保留前两段
function violation_mask_ip($ip){
	$ip = (string)$ip;
	if($ip === '')return '未知';
	if(strpos($ip, ':') !== false){
		$parts = explode(':', $ip);
		return $parts[0].':'.(isset($parts[1]) ? $parts[1] : '').':*:*';
	}
	$parts = explode('.', $ip);
	if(count($parts) == 4)return $parts[0].'.'.$parts[1].'.*.*';
	return '***';
}

//插入一条新的文件记录（每次上传都会生成独立的记录和链接，即使内容与已有文件相同也不会互相覆盖）
//$review 传 false 用于秒传：内容此前已经审核过，审核状态由调用方继承，不必再花一次云端检测的钱和时间
function create_file_record($name, $hash, $size, $ext, $hide, $pwd, $uid, $ip, $review = true){
	global $DB, $conf;
	$token = generate_file_token();
	//ipkey 是限流用的维度（IPv6 归并到 /64），和展示用的 ip 分开存
	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`token`,`addtime`,`ip`,`ipkey`,`hide`,`pwd`,`uid`) values (:name,:type,:size,:hash,:token,NOW(),:ip,:ipkey,:hide,:pwd,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':token'=>$token, ':ip'=>$ip, ':ipkey'=>client_ip_key($ip), ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($uid?$uid:0)]);
	if(!$sds)return false;
	$id = $DB->lastInsertId();
	if(!$review)return ['id'=>$id, 'token'=>$token];

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		$verdict = checkImage($hash, $ext, ['id'=>$id, 'name'=>$name, 'uid'=>($uid?$uid:0), 'ip'=>$ip]);
		if($verdict === 'block'){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
			//机器判定可能误伤，先留档但不公示，等后台在违规公示管理里确认后再放出
			add_violation_log(['id'=>$id, 'name'=>$name, 'type'=>$ext, 'size'=>$size, 'hash'=>$hash, 'ip'=>$ip, 'uid'=>($uid?$uid:0)], 'green', '图片自动检测命中', 0);
		}elseif($verdict === 'review'){
			//分数落在中间档：先按待审处理，前台下不下来，等后台人工看过再放行
			$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}

	return ['id'=>$id, 'token'=>$token];
}

function get_file_ext($name){
	$extension=explode('.',$name);
	if (($length = count($extension)) > 1) {
		$ext = strtolower($extension[$length - 1]);
	}
	if(strlen($ext)>6)$ext='';
	return $ext;
}

function file_part_merge($hash, $chunks){
	$tmp_dir = sys_get_temp_dir();
	$savePathTemp = $tmp_dir . '/' . $hash. '.parttmp';
	$tempFilePre = $tmp_dir . '/' . $hash. '.part';
	if(file_exists($savePathTemp)){
		unlink($savePathTemp);
	}
	if(!$out = fopen($savePathTemp, "wb")){
		exit('{"code":-1,"msg":"文件合并失败，临时文件夹无写入权限"}');
	}
	for( $index = 1; $index <= $chunks; $index++ ) {
		$chunk_file = $tempFilePre.$index;
		if (!$fp_in = @fopen($chunk_file,"rb")){
			fclose($out);
			unlink($savePathTemp);
			exit('{"code":-1,"msg":"文件合并失败，第'.$index.'分块读取失败"}');
		}
		while (!feof($fp_in)) {
			fwrite($out, fread($fp_in,1024*200));
		}
		fclose($fp_in);
		unlink($chunk_file);
	}
	fclose($out);
	return $savePathTemp;
}

function get_file_range($size){
	if(isset($_SERVER['HTTP_RANGE']) && !empty($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d+)-(\d*)$/i', $_SERVER['HTTP_RANGE'], $match)){
		$start = intval($match[1]);
		$end = intval($match[2]);
		if($start < 0) $start = 0;
		if($end == 0) $end = $size - 1;
		if($end >= $size) $end = $size - 1;
		if($end < $start || $start >= $size) return false;
		return [$start, $end];
	}
	return false;
}

function file_output($hash, $type, $size, $name, $is_view = false, $is_admin = false){
	global $conf, $stor;

	@set_time_limit(0);
	$size = intval($size);
	if($is_admin){
		header("Pragma: no-cache");
    	header("Cache-Control: no-store, no-cache, must-revalidate");
	}else{
		//链接可能通过“替换文件”指向新内容，不能无条件长期缓存，改为用hash做校验的强制revalidate，
		//这样同一个文件重复访问依然能省流量（304），但替换后能立刻拿到新内容
		$etag = '"'.$hash.'"';
		header("ETag: $etag");
		header("Cache-Control: no-cache");
		$client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
		if($client_etag !== '' && $client_etag === $etag){
			header("HTTP/1.1 304 Not Modified");
			exit;
		}
	}

	$filename = '"'.$name.'"; filename*=utf-8\'\''.rawurlencode($name);

	//不给浏览器猜类型的机会：猜错一次就是本站域名下的存储型 XSS
	header("X-Content-Type-Options: nosniff");
	//SVG 里可以写 <script>，HTML/XML 同理。这类内容一律不能带 inline 送出去，
	//哪怕后台把 svg 加进了"可预览类型"，也只能当附件下载
	if($is_view && is_scriptable_file_type($type))$is_view = false;

	if(\lib\StorHelper::is_direct_down() && $conf['downfile_type'] == 1){
		$redirect = $stor->getDownUrl($hash, $name, $is_view ? minetype($type) : null);
		if($redirect){
			header("Location: ".$redirect);
		}else{
			ob_clean();
			exit('Error:'.$stor->errmsg());
		}
	}else{
		if($is_view){
			header("Content-Type: ".minetype($type));
			header("Content-Disposition: inline; filename={$filename}");
		}else{
			header("Content-Description: File Transfer");
        	header("Content-Type: application/force-download");
        	header("Content-Disposition: attachment; filename={$filename}");
		}

		$range = false;
		if(\lib\StorHelper::is_range()){
			header("Accept-Ranges: bytes");
			$range = get_file_range($size);
			//视频等媒体常用Range分段拖动播放；若客户端带着旧版本的If-Range校验值，
			//说明它手里缓存的是替换前的分段，必须忽略Range改发完整最新内容，否则会把新旧文件的片段拼在一起播放
			if($range && !$is_admin && isset($_SERVER['HTTP_IF_RANGE']) && trim($_SERVER['HTTP_IF_RANGE']) !== $etag){
				$range = false;
			}
		}

		if($range){
			header("HTTP/1.1 206 Partial Content");
			header("Content-Length: ".($range[1] - $range[0] + 1));
			header("Content-Range: bytes {$range[0]}-{$range[1]}/{$size}");
			$stor->downfile($hash, $range);
		}else{
			header("Content-Length: {$size}");
			$stor->downfile($hash, $conf['storage']=='local'?[0, $size-1]:false);
		}
	}
}
