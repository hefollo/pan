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
		//回调函数名会被原样拼进JS里，只允许合法的标识符，否则退回默认名
		$callback = (isset($_POST['callback']) && is_string($_POST['callback']))?$_POST['callback']:'callback';
		if(!preg_match('/^[A-Za-z_$][A-Za-z0-9_$.]{0,63}$/', $callback))$callback = 'callback';
		@header('Content-Type: application/javascript; charset=UTF-8');
		exit($callback.'('.json_encode($arr).')');
	}else{
		@header('Content-Type: text/html; charset=UTF-8');
		if($arr['code']==0){
			//backurl 完全由调用方控制，会被写进 form action，必须限定为http(s)并做HTML转义
			$backurl = (isset($_POST['backurl']) && is_string($_POST['backurl']))?trim($_POST['backurl']):(isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'');
			if(!preg_match('#^https?://#i', $backurl))$backurl = '';
			$backurl = htmlspecialchars($backurl, ENT_QUOTES, 'UTF-8');
			$safe_downurl = htmlspecialchars((string)$arr['downurl'], ENT_QUOTES, 'UTF-8');
			$safe_type = htmlspecialchars((string)$arr['type'], ENT_QUOTES, 'UTF-8');
			$safe_name = htmlspecialchars((string)$arr['name'], ENT_QUOTES, 'UTF-8');
echo '<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
<meta name="viewport" content="width=device-width">
<title>文件上传页面</title>
</head>
<body>
<form action="'.$backurl.'" method="post">
<input name="file" type="hidden" value="'.$safe_downurl.'" />
<input name="type" type="hidden" value="'.$safe_type.'" />
<input name="name" type="hidden" value="'.$safe_name.'" />
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
	//配置了白名单就必须能取到合法的来源域名，取不到一律拒绝，不能放行
	$referers = array_filter(array_map('trim', explode('|',$conf['api_referer'])));
	$referer = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'';
	$url_arr = $referer?parse_url($referer):false;
	$referer_host = (is_array($url_arr) && !empty($url_arr['host']))?strtolower($url_arr['host']):'';
	if(!$referer_host || !in_array($referer_host, array_map('strtolower', $referers), true))showresult(['code'=>-4, 'msg'=>'来源地址不正确']);
}


//上传API和ajax.php走的是同一套配额，强制登录/大小/每日数量三道限制都要照样执行
if($conf['forcelogin']==1 && !$islogin2)showresult(['code'=>-1, 'msg'=>'请先登录']);

if(!isset($_FILES['file']))showresult(['code'=>-1, 'msg'=>'请选择文件']);
if($_FILES['file']['error'] !== UPLOAD_ERR_OK)showresult(['code'=>-1, 'msg'=>'文件上传失败，可能超出服务器限制', 'error'=>'upload']);
if(!is_uploaded_file($_FILES['file']['tmp_name']))showresult(['code'=>-1, 'msg'=>'非法的上传请求']);
//ENT_QUOTES 不能省：PHP 8.1 以下默认不转义单引号，文件名会被拼进播放器的 JS 字符串
$name=trim(htmlspecialchars($_FILES['file']['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
$size=intval($_FILES['file']['size']);
$hide = $_POST['show']==1?0:1;
$ispwd = intval($_POST['ispwd']);
$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')):null;
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
$limit_size = get_effective_upload_size_limit();
if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
	showresult(['code'=>-1, 'msg'=>'上传文件大小限制'.$limit_size.'MB']);
}
$upload_limit = get_effective_upload_count_limit();
if($upload_limit > 0){
	//本接口建的记录 uid 恒为0，只能按IP统计当天数量
	$thisday = date("Y-m-d 00:00:00");
	$ipcount = $DB->getColumn("SELECT count(*) from pre_file WHERE ip=:ip AND addtime>=:day", [':ip'=>$clientip, ':day'=>$thisday]);
	if($ipcount >= $upload_limit){
		showresult(['code'=>-1, 'msg'=>'你今天上传文件的数量已超过限制']);
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
	//view.php 现在会校验密码，预览地址要跟下载地址一样把密码带上
	if(is_view($ext)){
		$result['viewurl'] = $siteurl.'view.php/'.$record['token'].'.'.$ext;
		if(!empty($pwd))$result['viewurl'] .= '&'.$pwd;
	}
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
if(is_view($ext)){
	$result['viewurl'] = $siteurl.'view.php/'.$token.'.'.$ext;
	if(!empty($pwd))$result['viewurl'] .= '&'.$pwd;
}
showresult($result);
