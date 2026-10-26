<?php
include("./includes/common.php");

$title = '在线编辑 - '.$conf['title'];
$is_file = true;

$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$hash = isset($_GET['hash']) ? trim($_GET['hash']) : null;

if($id > 0){
	$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id", [':id'=>$id]);
}elseif($hash && preg_match('/^[0-9a-z]{32}$/i', $hash)){
	$row = $DB->getRow("SELECT * FROM pre_file WHERE token=:token", [':token'=>$hash]);
}else{
	sysmsg('参数错误');
}

if(!$row)sysmsg('文件不存在');
if(!can_manage_file($row))sysmsg('无权限编辑该文件');
if($row['block']==1)sysmsg('文件已被冻结，无法编辑');
if(!is_editable_file_type($row['type']))sysmsg('该文件格式不支持在线编辑');

if(!can_use_online_edit()){
	//在线编辑是付费功能、并且现在买得到时，直接指到购买页，别只丢一句“无权使用”
	if($islogin2 && online_edit_purchasable()){
		sysmsg('在线编辑需要开通后才能使用，购买带「在线编辑」的套餐即可。', '需要开通在线编辑', [
			['text'=>'去购买', 'href'=>'./buy.php', 'primary'=>true],
			['text'=>'返回上一页', 'href'=>'javascript:history.back(-1)'],
		]);
	}
	sysmsg('当前账号无权使用在线编辑功能');
}

$max_size = get_editable_file_max_size();
if($row['size'] > $max_size){
	sysmsg('在线编辑文件大小不能超过 '.size_format($max_size));
}

$content = get_storage_content($row['hash'], $row['storage']);
if($content === false)sysmsg('读取文件内容失败');
$decoded = decode_editable_content($content);
if($decoded['code'] != 0)sysmsg($decoded['msg']);
$content = $decoded['content'];

$file_name = htmlspecialchars($row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$file_content = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$file_encoding = htmlspecialchars($decoded['encoding'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$view_url = 'file.php?hash='.$row['token'];

include SYSTEM_ROOT.'header.php';
/*
 * 页面内容是布局 edit（DEC-20261025-003）：系统默认在 includes/layouts/edit.json，卡片在 includes/blocks/edit/。
 * 保存脚本由这里登记（编辑框的 id 是它要用的）。
 */
theme_defer('edit.scripts', theme_tpl_render('blocks/edit/scripts', ['max_bytes'=>intval($max_size), 'max'=>size_format($max_size)], $site_theme), 10);
echo theme_render_layout('edit', [
	'row' => $row,
	'file_name' => $file_name,
	'file_content' => $file_content,
	'file_encoding' => $file_encoding,
	'converted' => $decoded['converted'],
	'max_size' => $max_size,
	'view_url' => $view_url,
	'csrf_token' => $csrf_token,
], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
echo theme_deferred_html();
?></body>
</html>
