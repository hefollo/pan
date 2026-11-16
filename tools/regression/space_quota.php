<?php
/* 真实 MySQL 回归。仅在新建临时库上运行：PAN_SPACE_TEST_CONFIG 指向测试配置 JSON，库名必须以 pan_space_test_ 开头。 */
if(PHP_SAPI !== 'cli')exit;
$file = getenv('PAN_SPACE_TEST_CONFIG');
$cfg = $file ? json_decode(file_get_contents($file), true) : null;
if(!$cfg || !preg_match('/^pan_space_test_[a-z0-9_]+$/D', $cfg['dbname']))exit("需要独立 pan_space_test_ 测试库配置\n");
$root = dirname(__DIR__, 2);
define('SYSTEM_ROOT', $root.'/includes/');
define('ROOT', rtrim(sys_get_temp_dir(), '/\\').'/pan-space-regression/');
define('SYS_KEY', 'space-regression-only');
if(!is_dir(ROOT))mkdir(ROOT);
date_default_timezone_set('Asia/Shanghai');
require SYSTEM_ROOT.'autoloader.php';
Autoloader::register();
require SYSTEM_ROOT.'functions.php';
$DB = new \lib\PdoHelper($cfg);
$conf = ['storage'=>'local', 'filepath'=>'', 'type_image'=>'jpg|png', 'type_video'=>'mp4', 'green_check'=>0, 'videoreview'=>0];
$checks = 0;
function space_assert($condition, $message){
	global $checks;
	if(!$condition)throw new RuntimeException($message);
	$checks++; echo "PASS $message\n";
}
function space_test_file($uid, $bytes, $label){
	$content = substr(str_repeat($label, (int)ceil($bytes / strlen($label))), 0, $bytes);
	$hash = md5($content);
	$record = create_file_record($label.'.txt', $hash, $bytes, 'txt', 1, null, $uid, '192.0.2.20', false);
	if($record){
		if(!is_dir(ROOT.'file'))mkdir(ROOT.'file');
		file_put_contents(ROOT.'file/'.$hash, $content);
	}
	return $record;
}
function space_test_replace($row, $name, $hash, $bytes, $ext, $uid, $ip){
	file_put_contents(ROOT.'file/'.$hash, str_repeat('r', $bytes));
	$ok = replace_file_record($row, $name, $hash, $bytes, $ext, $uid, $ip);
	if(!$ok)delete_file_blob_if_orphaned($hash, null, 'local');
	return $ok;
}
function space_test_row($record){
	global $DB;
	return $DB->getRow('SELECT * FROM pre_file WHERE id=:id', [':id'=>$record['id']]);
}
function space_test_order($uid, $mb, $days){
	global $DB;
	$DB->exec("INSERT INTO pre_order (trade_no,uid,plan_id,plan_name,price,pay_type,kind,space,days,status,addtime) VALUES (:no,:uid,0,'space regression',2,'alipay','space',:mb,:days,0,NOW())",
		[':no'=>bin2hex(random_bytes(12)), ':uid'=>$uid, ':mb'=>$mb, ':days'=>$days]);
	return $DB->getRow('SELECT * FROM pre_order WHERE id=:id', [':id'=>$DB->lastInsertId()]);
}
//并发子进程不重新建数据，各自通过真实创建入口竞争同一账号的余额。
if(isset($argv[1]) && $argv[1] === 'worker'){
	$ok = space_test_file(intval($argv[2]), 700000, $argv[3]);
	echo $ok ? 'CREATED' : 'DENIED'; exit;
}
space_assert((int)$DB->getColumn('SELECT COUNT(*) FROM pre_user') === 0, '测试库没有业务用户');
$DB->exec("UPDATE pre_level SET space=1 WHERE type IN (1,2)");
$DB->exec("UPDATE pre_level SET space=-1 WHERE type=0");
level_all(true);
$DB->exec("INSERT INTO pre_user (uid,type,openid,nickname,level_id,addtime,lasttime) VALUES (91001,'mail','space-a','A',0,NOW(),NOW()),(91002,'mail','space-b','B',4,NOW(),NOW()),(91003,'mail','space-admin','Admin',3,NOW(),NOW()),(91004,'mail','space-race','Race',0,NOW(),NOW())");
$uid = 91001; $islogin2 = true; $userrow = $DB->getRow('SELECT * FROM pre_user WHERE uid=91001');
space_assert(level_space_mb(level_get(4)) === 1, '自建等级继承普通用户空间');
space_assert(space_info(91003)['unlimited'], '管理员不限空间');
$first = space_test_file($uid, 700000, 'first');
space_assert((bool)$first, '创建有限额内文件');
space_assert(space_info($uid)['used'] === 700000, '私密文件计入空间');
space_assert(!space_test_file($uid, 400000, 'too-large'), '最终创建入口拒绝超额');
space_assert(strpos(space_error(), '存储空间不足') !== false, '空间不足有明确提示');
$row = space_test_row($first);
space_assert(!create_file_record_from_existing($row, 'fake-size.txt', 1, 'txt', 0, null, $uid, '192.0.2.20'), '秒传忽略伪造小尺寸，按已有真实大小拒绝');
$exact = space_test_file($uid, 348576, 'boundary');
space_assert((bool)$exact && space_info($uid)['left'] === 0, '恰好填满容量可成功');
space_assert(!space_test_file($uid, 1, 'one-byte'), '多1字节被拒绝');
space_assert(delete_file_record(space_test_row($exact)), '删除成功');
space_assert(space_info($uid)['left'] === 348576, '删除立即释放空间');
$DB->exec('UPDATE pre_file SET block=1 WHERE id=:id', [':id'=>$first['id']]);
space_assert(space_info($uid)['used'] === 700000, '冻结文件仍占用空间');
$DB->exec('UPDATE pre_file SET block=0 WHERE id=:id', [':id'=>$first['id']]);
$row = space_test_row($first);
space_assert(space_test_replace($row, 'smaller.txt', md5('smaller'), 500000, 'txt', $uid, '192.0.2.20'), '替换只计算大小差额');
space_assert(space_info($uid)['used'] === 500000, '替换缩小释放差额');
space_assert(!space_test_replace(space_test_row($first), 'too-big.txt', md5('big'), 1048577, 'txt', $uid, '192.0.2.20'), '超额替换失败');
space_assert(space_info($uid)['used'] === 500000, '失败替换不改变占用');
$plan = ['space'=>2, 'days'=>30, 'price'=>5, 'upload_limit'=>-1, 'upload_size'=>-1, 'down_speed'=>-1, 'level_id'=>0];
space_assert(plan_kind($plan) === 'space' && plan_quote($userrow, $plan)['ok'], '空间包可独立购买');
space_assert(!plan_quote($userrow, array_merge($plan,['days'=>0]))['ok'], '空间包禁止0天永久');
space_assert(!plan_quote($userrow, array_merge($plan,['days'=>-1]))['ok'], '空间包禁止负天数');
space_assert(!plan_quote($DB->getRow('SELECT * FROM pre_user WHERE uid=91003'),$plan)['ok'], '管理员不出售空间包');
$order = space_test_order($uid, 2, 30);
space_assert((bool)$order && finish_order($order, 'test-payment'), '空间包经真实订单事务发放');
$pack = $DB->getRow('SELECT * FROM pre_space_pack WHERE order_id=:id', [':id'=>$order['id']]);
space_assert(space_info($uid)['quota'] === 3*1048576, '等级基础容量与有效包叠加');
space_assert(abs(strtotime($pack['expiretime']) - time() - 30*86400) < 5, '期限从发放时起计算');
space_assert(finish_order($order, 'duplicate'), '重复支付回调幂等成功');
space_assert(grant_plan_to_user($uid,$order), '后台补发同一订单幂等');
space_assert((int)$DB->getColumn('SELECT COUNT(*) FROM pre_space_pack') === 1, '重复回调/补发不多发');
space_assert($DB->getColumn('SELECT expiretime FROM pre_space_pack WHERE order_id=:id', [':id'=>$order['id']]) === $pack['expiretime'], '重复回调不延长期限');
$order2 = space_test_order($uid, 1, 7);
space_assert(finish_order($order2, 'second'), '重复购买生成独立空间包');
space_assert(space_info($uid)['quota'] === 4*1048576 && count(space_pack_list($uid)) === 2, '两笔有效容量叠加且均有明细');
space_assert(strtotime(space_info($uid)['next_expire']) < strtotime($pack['expiretime']), '不同天数各自到期');
$more = space_test_file($uid, 2200000, 'with-pack');
space_assert((bool)$more, '购买空间后可继续上传');
$DB->exec('UPDATE pre_space_pack SET expiretime=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE uid=91001');
space_assert(space_info($uid)['quota'] === 1048576 && space_info($uid)['used'] === 2700000, '到期立即回收额度但不删除文件');
space_assert(!space_test_file($uid, 1, 'expired'), '到期超额拒绝新增');
space_assert(space_test_replace(space_test_row($more), 'same.txt', md5('same'), 2200000, 'txt', $uid, '192.0.2.20'), '超额仍可等大替换');
space_assert(space_test_replace(space_test_row($more), 'smaller2.txt', md5('smaller2'), 200000, 'txt', $uid, '192.0.2.20'), '超额仍可缩小');
space_assert(count(space_pack_list($uid)) === 0, '到期包不显示为有效');
$DB->exec("UPDATE pre_level SET space=5 WHERE id=4"); level_all(true);
$DB->exec("UPDATE pre_user SET expiretime=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE uid=91002");
space_assert(space_info(91002)['base'] === 1048576, '会员到期回到普通用户容量');
$guest = space_test_file(0, 800000, 'guest');
space_assert((bool)$guest, '游客按IP额度可创建');
space_assert(!space_test_file(0, 300000, 'guest-full'), '同IP游客共用额度');
space_assert(space_check(800000,0,client_ip_key('192.0.2.21')), '其他IP游客额度独立');
space_assert(!space_claim_guest_files($uid,[$guest['id']]), '登录认领不能绕过账号空间');
space_assert((int)space_test_row($guest)['uid'] === 0, '认领失败保留游客文件');
space_assert(space_claim_guest_files(91002,[$guest['id']]), '有空间的账号正常认领');
space_assert((int)space_test_row($guest)['uid'] === 91002, '认领后占用归账号');
$cmd = [PHP_BINARY, __FILE__, 'worker', '91004'];
$children=[];
for($i=0;$i<2;$i++){
	$pipes=[];
	$process=proc_open(array_merge($cmd,['race-'.$i]), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
	if(!is_resource($process))throw new RuntimeException('无法启动并发子进程');
	fclose($pipes[0]); $children[]=[$process,$pipes];
}
$created=0;
foreach($children as $child){
	$out=stream_get_contents($child[1][1]); $err=stream_get_contents($child[1][2]);
	fclose($child[1][1]); fclose($child[1][2]);
	space_assert(proc_close($child[0]) === 0 && $err === '', '并发子进程正常退出');
	if(strpos($out,'CREATED') !== false)$created++;
}
space_assert($created === 1 && space_info(91004)['used'] === 700000, '两个并发上传仅一个成功，不超卖容量');
echo "OK $checks checks\n";
