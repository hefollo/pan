<?php
/**
 * 把一个主题打成 zip 下载（DEC-20261025-003）。上传的主题导出原包（含后台改过的布局）；
 * 内置外观导出成继承它的子主题骨架，拿来当写新主题的起点。见 includes/theme_package.php。
 */
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
if(!checkRefererHost())exit('来源校验失败');
$id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$m = theme_get($id);
if(!$m)sysmsg('主题不存在');
$file = theme_package_export($id);
if(!is_string($file) || !is_file($file))sysmsg(is_string($file) ? $file : '导出失败');
$name = ($m['_source'] === 'builtin' ? substr($id, 0, 30).'-custom' : $id).'.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.filesize($file));
header('Cache-Control: private, no-store');
readfile($file);
@unlink($file);
