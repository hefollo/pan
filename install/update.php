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

$version = 0;
if($rs = $db->query("SELECT v FROM pre_config WHERE k='version'")){
	$version = $rs->fetchColumn();
}

if($version<1009){
	$sqls = file_get_contents('update.sql');
	$sqls=explode(';', $sqls);
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1018')";
	if(!$db->query("SELECT v FROM pre_config WHERE k='syskey'")->fetchColumn()){
		$sqls[]="REPLACE INTO `pre_config` VALUES ('syskey', '".random(32)."')";
	}
}elseif($version<1010){
	//1010：新增购买套餐（pre_plan）与订单（pre_order）两张表和支付宝当面付配置项
	//update_1010.sql 的建表语句已经带上了后面版本加的字段，不用再执行 ALTER
	$sqls = explode(';', file_get_contents('update_1010.sql'));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1011){
	//1011：套餐支持“每日数量在现有基础上增加”，套餐表和订单表各加一个 limit_mode 字段
	//1012：套餐加分类字段，购买页按分类分区展示
	$sqls = array_merge(explode(';', file_get_contents('update_1011.sql')), explode(';', file_get_contents('update_1012.sql')), explode(';', file_get_contents('update_1013.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1012){
	//1012：套餐加分类字段
	//1013：订单记下支付方式，新增易支付
	$sqls = array_merge(explode(';', file_get_contents('update_1012.sql')), explode(';', file_get_contents('update_1013.sql')), explode(';', file_get_contents('update_1014.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1013){
	//1013：订单加 pay_type 字段，新增易支付配置
	//1014：商品名称可自定义、易支付参数编码可选
	$sqls = array_merge(explode(';', file_get_contents('update_1013.sql')), explode(';', file_get_contents('update_1014.sql')), explode(';', file_get_contents('update_1015.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1014){
	//1014：商品名称可自定义、易支付参数编码可选
	//1015：加量包额度单独记录，不再被时长套餐覆盖
	$sqls = array_merge(explode(';', file_get_contents('update_1014.sql')), explode(';', file_get_contents('update_1015.sql')), explode(';', file_get_contents('update_1016.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1015){
	//1015：用户表加 bonus_limit
	//1016：邮箱注册——用户表加 password、验证码表 pre_mailcode
	$sqls = array_merge(explode(';', file_get_contents('update_1015.sql')), explode(';', file_get_contents('update_1016.sql')), explode(';', file_get_contents('update_1017.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1016){
	//1016：邮箱注册所需的表和字段
	//1017：文件表加限流维度 ipkey，ip 字段加宽到能存 IPv6
	$sqls = array_merge(explode(';', file_get_contents('update_1016.sql')), explode(';', file_get_contents('update_1017.sql')), explode(';', file_get_contents('update_1018.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1017){
	//1017：文件表加限流维度 ipkey
	//1018：验证码表记下发送结果，后台可以查发信记录
	$sqls = array_merge(explode(';', file_get_contents('update_1017.sql')), explode(';', file_get_contents('update_1018.sql')));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}elseif($version<1018){
	//1018：验证码表加发送结果字段
	$sqls = explode(';', file_get_contents('update_1018.sql'));
	$sqls[] = "REPLACE INTO `pre_config` VALUES ('version', '1018')";
}else{
	exit('你的网站已经升级到最新版本了');
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
