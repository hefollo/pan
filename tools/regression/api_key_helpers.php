<?php
// 上传 API 密钥基础逻辑的离线回归，不连接数据库或启动网站。
if(PHP_SAPI !== 'cli')exit;
define('SYS_KEY', 'offline-regression-secret');
require dirname(__DIR__, 2).'/includes/functions.php';

$checks = 0;
function api_check($condition, $message){
	global $checks;
	if(!$condition)throw new RuntimeException($message);
	$checks++;
	echo "PASS {$message}\n";
}

$error = '';
$normalized = normalize_api_ip_list('127.0.0.1, 10.0.0.0/8 ::1', $error);
api_check($error === '', '合法 IP 白名单可通过校验');
api_check($normalized === '127.0.0.1|10.0.0.0/8|::1', 'IP 白名单被统一存储');
api_check(api_ip_allowed('10.2.3.4', $normalized), 'IPv4 CIDR 可匹配');
api_check(!api_ip_allowed('192.168.1.1', $normalized), '白名单外地址被拒绝');
api_check(api_ip_allowed('2001:0db8:0:0:0:0:0:1', '2001:db8::1'), 'IPv6 压缩和展开写法可匹配');

$invalid = normalize_api_ip_list('10.0.0.0/33', $error);
api_check($invalid === '' && $error !== '', '非法 CIDR 被拒绝');

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer pan_example';
api_check(api_request_token() === 'pan_example', 'Authorization Bearer 可读取');
unset($_SERVER['HTTP_AUTHORIZATION']);
$_SERVER['HTTP_X_API_KEY'] = 'pan_fallback';
api_check(api_request_token() === 'pan_fallback', 'X-API-Key 可作为兼容请求头');

api_check(api_key_hash('same-key') === api_key_hash('same-key'), '密钥摘要稳定');
api_check(api_key_hash('same-key') !== api_key_hash('other-key'), '不同密钥摘要不同');

class ApiKeyMemoryDB {
	public $keys = [];
	public $users = [7=>['uid'=>7, 'enable'=>1, 'level'=>0, 'expiretime'=>null]];
	private $last_id = 0;
	public function getColumn($sql, $params){
		if(strpos($sql, 'count(*)') !== false)return count(array_filter($this->keys, function($row) use ($params){ return $row['uid'] === intval($params[':uid']); }));
		return false;
	}
	public function exec($sql, $params){
		if(strpos($sql, 'INSERT INTO pre_api_key') !== false){
			$this->last_id++;
			$this->keys[$this->last_id] = ['id'=>$this->last_id, 'uid'=>intval($params[':uid']), 'name'=>$params[':name'], 'key_prefix'=>$params[':prefix'], 'key_hash'=>$params[':hash'], 'enable'=>1, 'allow_ip'=>$params[':allow_ip'], 'expiretime'=>$params[':expiretime'], 'lasttime'=>null, 'lastip'=>null];
			return true;
		}
		if(strpos($sql, 'UPDATE pre_api_key SET lasttime') !== false){
			$this->keys[intval($params[':id'])]['lasttime'] = date('Y-m-d H:i:s');
			$this->keys[intval($params[':id'])]['lastip'] = $params[':ip'];
			return true;
		}
		return false;
	}
	public function getRow($sql, $params){
		if(strpos($sql, 'FROM pre_api_key') !== false){
			foreach($this->keys as $row)if(hash_equals($row['key_hash'], $params[':hash']))return $row;
			return false;
		}
		return isset($this->users[intval($params[':uid'])]) ? $this->users[intval($params[':uid'])] : false;
	}
	public function lastInsertId(){ return $this->last_id; }
	public function error(){ return 'memory error'; }
}

$conf = ['api_key_limit'=>2, 'api_key_expire_days'=>30];
$DB = new ApiKeyMemoryDB;
$created = create_user_api_key(7, '测试客户端', '10.0.0.0/8', 30);
api_check(!empty($created['ok']) && preg_match('/^pan_[a-f0-9]{64}$/', $created['key']), '创建的密钥格式正确');
api_check($DB->keys[1]['key_hash'] !== $created['key'] && strlen($DB->keys[1]['key_hash']) === 64, '数据库只保存密钥摘要');
$authenticated = authenticate_user_api_key($created['key'], '10.2.3.4');
api_check(!empty($authenticated['ok']) && intval($authenticated['user']['uid']) === 7, '合法密钥绑定到所属用户');
api_check(empty(authenticate_user_api_key($created['key'], '192.168.1.1')['ok']), '密钥 IP 白名单生效');
$DB->keys[1]['enable'] = 0;
api_check(empty(authenticate_user_api_key($created['key'], '10.2.3.4')['ok']), '停用密钥立即失效');
$DB->keys[1]['enable'] = 1;
$DB->keys[1]['expiretime'] = date('Y-m-d H:i:s', time() - 1);
api_check(empty(authenticate_user_api_key($created['key'], '10.2.3.4')['ok']), '过期密钥立即失效');

echo "OK {$checks} checks\n";
