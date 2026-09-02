<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require '../config.php';

@header('Content-Type: text/html; charset=UTF-8');

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

try{
	$db=new PDO("mysql:host=".$dbconfig['host'].";dbname=".$dbconfig['dbname'].";port=".$dbconfig['port'],$dbconfig['user'],$dbconfig['pwd']);
}catch(Exception $e){
	exit('链接数据库失败:'.$e->getMessage());
}
date_default_timezone_set("PRC");
$date = date("Y-m-d");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$db->exec("set sql_mode = ''");
$db->exec("set names utf8");

/*
 * 升级入口必须验证管理员身份，本页会执行建表/改表语句，不能对公网裸奔。
 *
 * 这里没法复用后台登录态：升级没完成时 includes/common.php 会拦掉包括后台登录页在内的所有页面，
 * 而且 admin_token 的作用域只在 /admin/ 下，根本发不到 /install/。所以单独校验一次账号密码。
 */
session_start();
function update_conf($db, $k){
	$st = $db->prepare("SELECT v FROM pre_config WHERE k=:k LIMIT 1");
	if(!$st || !$st->execute([':k'=>$k]))return '';
	return (string)$st->fetchColumn();
}
$admin_user = update_conf($db, 'admin_user');
$admin_pwd  = update_conf($db, 'admin_pwd');
$auth_err = '';
if(empty($_SESSION['update_auth'])){
	$fail = isset($_SESSION['update_fail']) ? intval($_SESSION['update_fail']) : 0;
	if(isset($_POST['user']) && isset($_POST['pass'])){
		if($fail >= 5){
			$auth_err = '错误次数过多，请关掉浏览器重新打开再试';
		}elseif($admin_user === '' || $admin_pwd === ''){
			$auth_err = '数据库里没有管理员账号，无法校验身份';
		}elseif(hash_equals($admin_user, (string)$_POST['user']) && hash_equals($admin_pwd, (string)$_POST['pass'])){
			$_SESSION['update_auth'] = 1;
			$_SESSION['update_fail'] = 0;
		}else{
			$_SESSION['update_fail'] = $fail + 1;
			$auth_err = '管理员账号或密码不正确';
		}
	}
}
if(empty($_SESSION['update_auth'])){
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		.'<title>网站升级</title></head><body style="font-family:system-ui,-apple-system,\'Microsoft YaHei\',sans-serif;background:#f5f6f8;margin:0">'
		.'<div style="max-width:360px;margin:12vh auto;padding:28px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.08)">'
		.'<h3 style="margin:0 0 6px">网站数据库升级</h3>'
		.'<p style="color:#888;font-size:13px;margin:0 0 18px">请先验证管理员身份</p>'
		.($auth_err ? '<p style="color:#d33;font-size:13px;margin:0 0 12px">'.htmlspecialchars($auth_err, ENT_QUOTES, 'UTF-8').'</p>' : '')
		.'<form method="post">'
		.'<input name="user" placeholder="管理员账号" autocomplete="username" style="width:100%;box-sizing:border-box;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:6px">'
		.'<input name="pass" type="password" placeholder="管理员密码" autocomplete="current-password" style="width:100%;box-sizing:border-box;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:6px">'
		.'<button type="submit" style="width:100%;padding:10px;border:0;border-radius:6px;background:#2563eb;color:#fff;font-size:15px;cursor:pointer">开始升级</button>'
		.'</form></div></body></html>';
	exit;
}

$version = 0;
if($rs = $db->query("SELECT v FROM pre_config WHERE k='version'")){
	$version = $rs->fetchColumn();
}

if($version<1009){
	$sqls = file_get_contents('update.sql');
	$sqls=explode(';', $sqls);
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1019')";
	if(!$db->query("SELECT v FROM pre_config WHERE k='syskey'")->fetchColumn()){
		$sqls[]="REPLACE INTO `pre_config` VALUES ('syskey', '".bin2hex(random_bytes(16))."')";
	}
}elseif($version<1010){
	//1010：新增购买套餐（pre_plan）与订单（pre_order）两张表和支付宝当面付配置项
	//update_1010.sql 的建表语句已经带上了后面版本加的字段，不用再执行 ALTER
	$sqls = explode(';', file_get_contents('update_1010.sql'));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1011){
	//1011：套餐支持“每日数量在现有基础上增加”，套餐表和订单表各加一个 limit_mode 字段
	//1012：套餐加分类字段，购买页按分类分区展示
	$sqls = array_merge(explode(';', file_get_contents('update_1011.sql')), explode(';', file_get_contents('update_1012.sql')), explode(';', file_get_contents('update_1013.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1012){
	//1012：套餐加分类字段
	//1013：订单记下支付方式，新增易支付
	$sqls = array_merge(explode(';', file_get_contents('update_1012.sql')), explode(';', file_get_contents('update_1013.sql')), explode(';', file_get_contents('update_1014.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1013){
	//1013：订单加 pay_type 字段，新增易支付配置
	//1014：商品名称可自定义、易支付参数编码可选
	$sqls = array_merge(explode(';', file_get_contents('update_1013.sql')), explode(';', file_get_contents('update_1014.sql')), explode(';', file_get_contents('update_1015.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1014){
	//1014：商品名称可自定义、易支付参数编码可选
	//1015：加量包额度单独记录，不再被时长套餐覆盖
	$sqls = array_merge(explode(';', file_get_contents('update_1014.sql')), explode(';', file_get_contents('update_1015.sql')), explode(';', file_get_contents('update_1016.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1015){
	//1015：用户表加 bonus_limit
	//1016：邮箱注册——用户表加 password、验证码表 pre_mailcode
	$sqls = array_merge(explode(';', file_get_contents('update_1015.sql')), explode(';', file_get_contents('update_1016.sql')), explode(';', file_get_contents('update_1017.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1016){
	//1016：邮箱注册所需的表和字段
	//1017：文件表加限流维度 ipkey，ip 字段加宽到能存 IPv6
	$sqls = array_merge(explode(';', file_get_contents('update_1016.sql')), explode(';', file_get_contents('update_1017.sql')), explode(';', file_get_contents('update_1018.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1017){
	//1017：文件表加限流维度 ipkey
	//1018：验证码表记下发送结果，后台可以查发信记录
	$sqls = array_merge(explode(';', file_get_contents('update_1017.sql')), explode(';', file_get_contents('update_1018.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1018){
	//1018：验证码表加发送结果字段
	$sqls = explode(';', file_get_contents('update_1018.sql'));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}elseif($version<1019){
	//1019：新增图片检测记录表（建表语句在下面统一补）
	$sqls = [];
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1019')";
}else{
	exit('你的网站已经升级到最新版本了');
}

/*
 * 1019 的建表语句独立于所有历史结构，用的又是 CREATE TABLE IF NOT EXISTS，
 * 所以不用往上面每个 elseif 里各塞一遍，凡是还没到 1019 的统一在这里补。
 */
if($version < 1019){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1019.sql')));
}

$success=0;$error=0;$errorMsg=null;
foreach ($sqls as $value) {
	$value=trim($value);
	if(empty($value))continue;
	if($db->exec($value)===false){
		$error++;
		$dberror=$db->errorInfo();
		$errorMsg.=$dberror[2]."<br>";
	}else{
		$success++;
	}
}
echo '成功执行SQL语句'.$success.'条！<br/>';
if($errorMsg){
//echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
}
exit("<script language='javascript'>alert('网站数据库升级完成！');window.location.href='../';</script>");
?>
