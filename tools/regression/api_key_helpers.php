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
	public $users = [7=>['uid'=>7, 'enable'=>1, 'level_id'=>0, 'expiretime'=>null]];
	public $levels = [
		['id'=>1, 'type'=>1, 'api'=>0],
		['id'=>2, 'type'=>2, 'api'=>0],
		['id'=>3, 'type'=>3, 'api'=>0], // 管理员即使功能位为0仍有权限。
		['id'=>4, 'type'=>0, 'api'=>1],
	];
	public function getAll($sql){ return $this->levels; }
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
		if(strpos($sql, 'UPDATE pre_api_key SET enable') !== false){
			$this->keys[intval($params[':id'])]['enable'] = intval($params[':enable']);
			return true;
		}
		if(strpos($sql, 'DELETE FROM pre_api_key') !== false){
			unset($this->keys[intval($params[':id'])]);
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
			if(isset($params[':id'])){
				$row = $this->keys[intval($params[':id'])] ?? false;
				return $row && $row['uid'] === intval($params[':uid']) ? $row : false;
			}
			foreach($this->keys as $row)if(hash_equals($row['key_hash'], $params[':hash']))return $row;
			return false;
		}
		return isset($this->users[intval($params[':uid'])]) ? $this->users[intval($params[':uid'])] : false;
	}
	public function lastInsertId(){ return $this->last_id; }
	public function error(){ return 'memory error'; }
}

$conf = ['api_open'=>1, 'api_key_limit'=>2, 'api_key_expire_days'=>30];
$DB = new ApiKeyMemoryDB;
$islogin2 = 1;
$userrow = $DB->users[7];
$denied = create_user_api_key(7, '不应创建');
api_check(empty($denied['ok']) && !$DB->keys, '未开放API的普通用户不能创建，数据库无写入');
$DB->users[7]['level_id'] = 4;
$userrow = $DB->users[7];
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

// 用真实用户等级检查到期、管理员、撤销权限及目标账号，避免只模拟布尔权限。
$DB->keys = [];
$DB->users[7]['expiretime'] = date('Y-m-d H:i:s', time() - 1);
api_check(empty(create_user_api_key(7, '到期会员')['ok']) && !$DB->keys, '会员到期回普通用户后禁止创建');
$DB->users[7]['expiretime'] = null;
$DB->users[7]['level_id'] = 3;
api_check(!empty(create_user_api_key(7, '管理员')['ok']), '管理员可创建');
$DB->users[8] = ['uid'=>8, 'enable'=>1, 'level_id'=>0, 'expiretime'=>null];
api_check(empty(create_user_api_key(8, '目标普通用户')['ok']), '按目标用户权限判断，不借用当前管理员权限');
api_check(empty(create_user_api_key(99, '不存在')['ok']), '不存在的账号不能创建');
$DB->users[7]['enable'] = 0;
api_check(empty(create_user_api_key(7, '停用账号')['ok']), '停用账号不能创建');
$DB->users[7]['enable'] = 1;

// 执行真实个人中心act分支，替换仅负责exit的JSON输出，不加载站点或数据库。
class ApiJsonResult extends RuntimeException {
	public $result;
	public function __construct($result){ $this->result = $result; }
}
function uc_json($code, $msg, $extra = []){
	throw new ApiJsonResult(array_merge(['code'=>$code, 'msg'=>$msg], $extra));
}
$source = file_get_contents(dirname(__DIR__, 2).'/user.php');
$start = strpos($source, "case 'createApiKey':");
$end = strpos($source, '//批量删除', $start);
if($start === false || $end === false)throw new RuntimeException('API branches not found');
$branches = substr($source, $start, $end - $start);
function api_action($act, $post){
	global $DB, $conf, $uid, $branches;
	$_POST = $post;
	try { eval('switch ($act) {'.$branches.'}'); }
	catch(ApiJsonResult $e){ return $e->result; }
	throw new RuntimeException('No API response');
}
$uid = 7;
$DB->keys = [];
$DB->users[7]['level_id'] = 4;
$userrow = $DB->users[7];
$result = api_action('createApiKey', ['name'=>'接口创建']);
api_check($result['code'] === 0 && count($DB->keys) === 1, '有API权限可通过真实创建接口');
$key_id = intval($result['id']);
$token = $result['key'];
$DB->users[7]['level_id'] = 0;
$userrow = $DB->users[7];
$result = api_action('createApiKey', ['name'=>'撤权后创建']);
api_check($result['code'] === -1 && count($DB->keys) === 1, '撤销API权限后接口禁止创建且不写入');
api_check(!level_can('api'), '撤销权限后旧密钥所属用户被上传接口等级校验拒绝');
$result = api_action('toggleApiKey', ['id'=>$key_id]);
api_check($result['code'] === 0 && $DB->keys[$key_id]['enable'] === 0, '无API权限仍可停用旧密钥');
$result = api_action('toggleApiKey', ['id'=>$key_id]);
api_check($result['code'] === -1 && $DB->keys[$key_id]['enable'] === 0, '无API权限不能重新启用旧密钥');
$DB->users[7]['level_id'] = 4;
$userrow = $DB->users[7];
$result = api_action('toggleApiKey', ['id'=>$key_id]);
api_check($result['code'] === 0 && $DB->keys[$key_id]['enable'] === 1, '恢复权限后可启用旧密钥');
$conf['api_open'] = 0;
$result = api_action('createApiKey', ['name'=>'站点关闭']);
api_check($result['code'] === -1 && count($DB->keys) === 1, '站点总开关关闭后禁止创建');
$conf['api_open'] = 1;
$DB->users[7]['level_id'] = 0;
$userrow = $DB->users[7];
$result = api_action('deleteApiKey', ['id'=>$key_id]);
api_check($result['code'] === 0 && !$DB->keys, '无API权限仍可删除已有密钥');

// 真实页签选择逻辑：手动输入tab=api也不能绕过入口权限。
$start = strpos($source, '$tabs = [');
$end = strpos($source, '//头像底下', $start);
if($start === false || $end === false)throw new RuntimeException('Tab logic not found');
$tab_source = substr($source, $start, $end - $start);
$_GET = ['tab'=>'api'];
eval($tab_source);
api_check(!isset($tabs['api']) && $tab === 'overview', '无权限隐藏API页签且直接访问回概览');
$DB->users[7]['level_id'] = 4;
$userrow = $DB->users[7];
eval($tab_source);
api_check(isset($tabs['api']) && $tab === 'api', '有权限保留API页签');
$conf['api_open'] = 0;
eval($tab_source);
api_check(!isset($tabs['api']) && $tab === 'overview', '总开关关闭隐藏API页签');

echo "OK {$checks} checks\n";
