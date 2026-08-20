<?php
$nosession = true;
$nosecu = true;
include("./includes/common.php");

function showresult($arr, $format='json'){
	$format = isset($_POST['format'])?$_POST['format']:'json';
	if($format == 'json'){
		@header('Content-Type: application/json; charset=UTF-8');
		exit(json_encode($arr));
	}elseif($format == 'jsonp'){
		$callback = isset($_POST['callback'])?$_POST['callback']:'callback';
		@header('Content-Type: application/javascript; charset=UTF-8');
		exit($callback.'('.json_encode($arr).')');
	}else{
		@header('Content-Type: text/html; charset=UTF-8');
		if($arr['code']==0){
			$backurl = isset($_POST['backurl'])?$_POST['backurl']:$_SERVER['HTTP_REFERER'];
echo '<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
<meta name="viewport" content="width=device-width">
<title>文件上传页面</title>
</head>
<body>
<form action="'.$backurl.'" method="post">
<input name="file" type="hidden" value="'.$arr['downurl'].'" />
<input name="type" type="hidden" value="'.$arr['type'].'" />
<input name="name" type="hidden" value="'.$arr['name'].'" />
<input name="submit" type="submit" value="下一步" />
</form>
</body></html>';
exit;
		}else{
			sysmsg($arr['msg']);
		}
	}
}

if(!$conf['api_open'])showresult(['code'=>-4, 'msg'=>'当前站点未开启上传API']);

if(!empty($conf['api_referer'])){
	$referers = explode('|',$conf['api_referer']);
	$url_arr = parse_url($_SERVER['HTTP_REFERER']);
	if(!in_array($url_arr['host'], $url_arr))showresult(['code'=>-4, 'msg'=>'来源地址不正确']);
}


if(!isset($_FILES['file']))showresult(['code'=>-1, 'msg'=>'请选择文件']);
$name=trim(htmlspecialchars($_FILES['file']['name']));
$size=intval($_FILES['file']['size']);
$hide = $_POST['show']==1?0:1;
$ispwd = intval($_POST['ispwd']);
$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
$name = str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name);
if(empty($name))showresult(['code'=>-1, 'msg'=>'文件名不能为空']);
if($ispwd==1 && !empty($pwd)){
	if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
		showresult(['code'=>-1, 'msg'=>'文件密码只能为字母和数字']);
	}
}
$ext=get_file_ext($name);
if($conf['type_block']){
	$type_block = explode('|',$conf['type_block']);
	if(in_array($ext,$type_block)){
		showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'block']);
	}
}
if($conf['name_block']){
	$name_block = explode('|',$conf['name_block']);
	foreach($name_block as $row){
		if(strpos($name,$row)!==false){
			showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'block']);
		}
	}
}
$hash = md5_file($_FILES['file']['tmp_name']);
$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
if($row){
	unset($_SESSION['csrf_token']);
	//秒传：跳过物理上传，但仍建独立记录，这次上传有自己的链接和文件名，不会挂到别人名下
	$record = create_file_record_from_existing($row, $name, $size, $ext, $hide, $pwd, 0, $clientip);
	if(!$record)showresult(['code'=>-1, 'msg'=>'上传失败'.$DB->error(), 'error'=>'database']);
	//下载和预览地址都按 token 解析，不能再用内容哈希拼链接
	$downurl = $siteurl.'down.php/'.$record['token'].'.'.$ext;
	if(!empty($pwd))$downurl .= '&'.$pwd;
	$result = ['code'=>0, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'token'=>$record['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$record['id'], 'downurl'=>$downurl];
	if(is_view($ext))$result['viewurl'] = $siteurl.'view.php/'.$record['token'].'.'.$ext;
	showresult($result);
}
$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
if(!$result)showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'stor']);
//统一走 create_file_record，它负责生成访问用的 token，并顺带做图片检测和违规留档
$record = create_file_record($name, $hash, $size, $ext, $hide, $pwd, 0, $clientip);
if(!$record)showresult(['code'=>-1, 'msg'=>'上传失败'.$DB->error(), 'error'=>'database']);
$id = $record['id'];
$token = $record['token'];

$downurl = $siteurl.'down.php/'.$token.'.'.$ext;
if(!empty($pwd))$downurl .= '&'.$pwd;
$result = ['code'=>0, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'token'=>$token, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id, 'downurl'=>$downurl];
if(is_view($ext))$result['viewurl'] = $siteurl.'view.php/'.$token.'.'.$ext;
showresult($result);
