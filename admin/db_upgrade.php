<?php
/*
 * 后台一键升级数据库（DEC-20261003-001 的补充）。
 *
 * 在线更新写完新文件后 DB_VERSION 变大，includes/common.php 的版本门禁会拦住整个后台，
 * 连「程序更新日志」页也进不来。所以这里先把 $install 设上跳过门禁，再自己核对管理员登录态，
 * 然后给 install/update.php 一个「只用一次、5 分钟内有效」的免密标记，跳过去执行升级，
 * 升级完回到后台的程序更新日志页。没登录后台的，照旧去 install/update.php 输账号密码。
 *
 * 只接受 POST + 页面令牌 + 同源 Referer：门禁页和在线更新结果里的按钮都是表单提交。
 */
define('IN_ADMIN', true);
$install = true;
include("../includes/common.php");
include_once SYSTEM_ROOT.'online_update.php';

header('Content-Type: text/html; charset=UTF-8');
$update_url = site_root_url().'install/update.php';

function db_upgrade_stop($msg, $update_url){
	exit('<p style="font:14px/1.8 system-ui;padding:24px">'.$msg
		.'<br>也可以直接打开 <a href="'.htmlspecialchars($update_url, ENT_QUOTES, 'UTF-8').'">install/update.php</a>，输入管理员账号密码完成升级。</p>');
}

if($islogin != 1)db_upgrade_stop('后台登录已失效，不能免密升级。', $update_url);
if($_SERVER['REQUEST_METHOD'] !== 'POST' || !checkRefererHost() || !oupd_check_token(isset($_POST['token']) ? $_POST['token'] : '')){
	db_upgrade_stop('请求无效（令牌或来源校验没通过），请回到后台重新点按钮。', $update_url);
}

$_SESSION['update_auth_once'] = time();
//升级完回到这里。install/update.php 在 /install/ 下，用相对它的路径，只认 ../后台目录/update.php 这一种形状
$_SESSION['update_back'] = '../'.oupd_admin_dir().'/update.php';
if(function_exists('session_write_close'))session_write_close();
header('Location: '.$update_url);
exit;
