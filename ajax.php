<?php
$nosecu = true;
include("./includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

function set_upload_csrf_token(&$result){
	$result['csrf_token'] = $_SESSION['csrf_token'];
	return $result;
}

function get_upload_state($hash){
	if(isset($_SESSION['uploads'][$hash]) && is_array($_SESSION['uploads'][$hash])){
		return $_SESSION['uploads'][$hash];
	}
	if(isset($_SESSION['upload']) && is_array($_SESSION['upload']) && isset($_SESSION['upload']['hash']) && $_SESSION['upload']['hash'] === $hash){
		return $_SESSION['upload'];
	}
	return null;
}

function set_upload_state($hash, $state){
	if(!isset($_SESSION['uploads']) || !is_array($_SESSION['uploads'])){
		$_SESSION['uploads'] = [];
	}
	$_SESSION['uploads'][$hash] = $state;
	$_SESSION['upload'] = $state;
}

function clear_upload_state($hash){
	if(isset($_SESSION['uploads'][$hash])){
		unset($_SESSION['uploads'][$hash]);
	}
	if(isset($_SESSION['upload']) && is_array($_SESSION['upload']) && isset($_SESSION['upload']['hash']) && $_SESSION['upload']['hash'] === $hash){
		unset($_SESSION['upload']);
	}
}

function upload_all_parts_exist($hash, $chunks){
	$tempFilePre = sys_get_temp_dir() . '/' . $hash . '.part';
	for($index = 1; $index <= $chunks; $index++){
		if(!file_exists($tempFilePre.$index)){
			return false;
		}
	}
	return true;
}

function upload_debug_start(){
	$now = microtime(true);
	return ['start'=>$now, 'last'=>$now, 'steps'=>[]];
}

function upload_debug_step(&$debug, $name){
	if(!$debug)return;
	$now = microtime(true);
	$debug['steps'][$name] = round(($now - $debug['last']) * 1000, 2);
	$debug['last'] = $now;
}

function upload_debug_finish($debug){
	if(!$debug)return null;
	return [
		'steps' => $debug['steps'],
		'total_ms' => round((microtime(true) - $debug['start']) * 1000, 2)
	];
}

if($islogin2 && $userrow['level']>0 && is_user_permission_active()){
	$conf['videoreview']=0;
	$conf['type_block']=null;
	$conf['name_block']=null;
}

switch($act){
case 'pre_upload':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$name = trim(htmlspecialchars($_POST['name']));
	$hash = trim($_POST['hash']);
	$size = intval($_POST['size']);
	$hide = $_POST['show']==1?0:1;
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name);
	if(empty($name))exit('{"code":-1,"msg":"文件名不能为空"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	if($ispwd==1 && !empty($pwd)){
		if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"文件密码只能为字母和数字"}');
		}
	}
	$ext=get_file_ext($name);
	if($conf['type_block']){
		$type_block = explode('|',$conf['type_block']);
		if(in_array($ext,$type_block)){
			exit('{"code":-1,"msg":"文件上传失败，不支持上传该格式文件","error":"block"}');
		}
	}
	if($conf['name_block']){
		$name_block = explode('|',$conf['name_block']);
		foreach($name_block as $row){
			if(strpos($name,$row)!==false){
				exit('{"code":-1,"msg":"文件上传失败","error":"block"}');
			}
		}
	}
	$limit_size = get_effective_upload_size_limit();
	if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
		exit('{"code":-1,"msg":"上传文件大小限制'.$limit_size.'MB"}');
	}
	$upload_limit = get_effective_upload_count_limit();
	if($upload_limit>0){
		$thisday = date("Y-m-d 00:00:00");
		if($islogin2){
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."'");
		}else{
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."'");
		}
		if($ipcount >= $upload_limit){
			exit('{"code":-1,"msg":"你今天上传文件的数量已超过限制"}');
		}
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}

	if(\lib\StorHelper::is_cloud() && $conf['uploadfile_type'] == 1){
		$param = $stor->getUploadParam($hash, $name, $limit_size * 1024 * 1024);
		if(!$param)exit('{"code":-1,"msg":"获取上传参数失败","errmsg":"'.$stor->errmsg().'"}');
		set_upload_state($hash, [
			'chunks' => 1,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd
		]);
		$result = ['code'=>0, 'third'=>true, 'hash'=>$hash, 'url'=>$param['url'], 'post'=>$param['post']];
		exit(json_encode($result));
	}else{
		$chunksize = 32 * 1024 * 1024; //分块上传，每块大小
		$chunks = ceil($size / $chunksize);
		set_upload_state($hash, [
			'chunks' => $chunks,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd
		]);
		$result = ['code'=>0, 'third'=>false, 'hash'=>$hash, 'chunksize'=>$chunksize, 'chunks'=>$chunks];
		exit(json_encode($result));
	}
break;

case 'upload_part':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择文件"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$chunk = intval($_POST['chunk']);
	$hash = trim($_POST['hash']);
	$upload_state = get_upload_state($hash);
	if(!$upload_state || !isset($upload_state['hash']) || $upload_state['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$chunks = intval($upload_state['chunks']);
	$ext = $upload_state['ext'];
	$debug = upload_debug_start();
	if($chunks > 1){
		$tempFile = sys_get_temp_dir() . '/' . $hash. '.part'.$chunk;
		if(!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)){
			exit('{"code":-1,"msg":"文件第'.$chunk.'分块上传失败"}');
		}
		upload_debug_step($debug, 'move_uploaded_file_ms');
		if(upload_all_parts_exist($hash, $chunks)){
			$mergeLockFile = sys_get_temp_dir() . '/' . $hash . '.merge.lock';
			$mergeLock = @fopen($mergeLockFile, 'c');
			if($mergeLock){
				flock($mergeLock, LOCK_EX);
			}
			if(!upload_all_parts_exist($hash, $chunks)){
				if($mergeLock){
					flock($mergeLock, LOCK_UN);
					fclose($mergeLock);
				}
				$result = ['code'=>0, 'chunk'=>$chunk];
				exit(json_encode($result));
			}
			$savePathTemp = file_part_merge($hash, $chunks);
			upload_debug_step($debug, 'merge_parts_ms');
			$real_hash = md5_file($savePathTemp);
			upload_debug_step($debug, 'md5_check_ms');
			$real_size = filesize($savePathTemp);
			upload_debug_step($debug, 'filesize_ms');
			$result = $stor->savefile($hash, $savePathTemp, minetype($ext));
			upload_debug_step($debug, 'storage_save_ms');
			if($mergeLock){
				flock($mergeLock, LOCK_UN);
				fclose($mergeLock);
				@unlink($mergeLockFile);
			}
			if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
		}else{
			$result = ['code'=>0, 'chunk'=>$chunk];
			exit(json_encode($result));
		}
	}else{
		$real_hash = md5_file($_FILES['file']['tmp_name']);
		upload_debug_step($debug, 'md5_check_ms');
		$real_size = filesize($_FILES['file']['tmp_name']);
		upload_debug_step($debug, 'filesize_ms');
		$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
		upload_debug_step($debug, 'storage_upload_ms');
		if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$size = $upload_state['size'];
	if($real_size != $size){
		exit('{"code":-1,"msg":"文件大小校验失败"}');
	}
	if($real_hash != $hash){
		exit('{"code":-1,"msg":"文件MD5校验失败"}');
	}
	upload_debug_step($debug, 'validate_ms');

	$name = $upload_state['name'];
	$hide = $upload_state['hide'];
	$pwd = $upload_state['pwd'];

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	upload_debug_step($debug, 'duplicate_query_ms');
	if($row){
		clear_upload_state($hash);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		$result['debug_timing'] = upload_debug_finish($debug);
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($uid?$uid:0)]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();
	upload_debug_step($debug, 'db_insert_ms');

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	upload_debug_step($debug, 'image_review_ms');
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}
	upload_debug_step($debug, 'video_review_ms');
	
	$_SESSION['fileids'][] = $id;
	clear_upload_state($hash);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	$result['debug_timing'] = upload_debug_finish($debug);
	set_upload_csrf_token($result);
	exit(json_encode($result));
break;

case 'complete_upload':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$hash = trim($_POST['hash']);
	$upload_state = get_upload_state($hash);
	if(!$upload_state || !isset($upload_state['hash']) || $upload_state['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	
	if(!$stor->exists($hash)){
		exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}
	$debug = upload_debug_start();
	upload_debug_step($debug, 'cloud_exists_check_ms');

	$name = $upload_state['name'];
	$size = $upload_state['size'];
	$ext = $upload_state['ext'];
	$hide = $upload_state['hide'];
	$pwd = $upload_state['pwd'];

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	upload_debug_step($debug, 'duplicate_query_ms');
	if($row){
		clear_upload_state($hash);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		$result['debug_timing'] = upload_debug_finish($debug);
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($uid?$uid:0)]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();
	upload_debug_step($debug, 'db_insert_ms');

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	upload_debug_step($debug, 'image_review_ms');
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}
	upload_debug_step($debug, 'video_review_ms');
	
	$_SESSION['fileids'][] = $id;
	clear_upload_state($hash);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	$result['debug_timing'] = upload_debug_finish($debug);
	set_upload_csrf_token($result);
	exit(json_encode($result));
break;

case 'deleteFile':
	$hash = isset($_POST['hash'])?trim($_POST['hash']):exit('{"code":-1,"msg":"no hash"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash", [':hash'=>$hash]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	if($islogin2 && $row['uid']!=$uid || !$islogin2 && (!isset($_SESSION['fileids']) || !in_array($row['id'], $_SESSION['fileids'])))exit('{"code":-1,"msg":"无权限"}');
	if($row['block']==1)exit('{"code":-1,"msg":"文件已被冻结，无法删除"}');
	if(!$islogin2 && strtotime($row['addtime'])<strtotime("-7 days"))exit('{"code":-1,"msg":"无法删除7天前的文件"}');
	$result = $stor->delete($row['hash']);
	$sql = "DELETE FROM pre_file WHERE id=:id";
	if($DB->exec($sql, [':id'=>$row['id']]))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;

case 'saveFileContent':
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	if(!array_key_exists('content', $_POST))exit('{"code":-1,"msg":"内容不能为空"}');
	$content = (string)$_POST['content'];
	$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `id`=:id", [':id'=>$id]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	if(!can_manage_file($row))exit('{"code":-1,"msg":"无权限"}');
	if($row['block']==1)exit('{"code":-1,"msg":"文件已被冻结，无法编辑"}');
	if(!is_editable_file_type($row['type']))exit('{"code":-1,"msg":"该文件格式不支持在线编辑"}');
	if(!can_use_online_edit())exit(json_encode(['code'=>-1, 'msg'=>'当前账号无权使用在线编辑功能'], JSON_UNESCAPED_UNICODE));
	$max_size = get_editable_file_max_size();
	$size = strlen($content);
	if($size > $max_size){
		exit(json_encode(['code'=>-1, 'msg'=>'在线编辑文件大小不能超过'.size_format($max_size)], JSON_UNESCAPED_UNICODE));
	}
	if(!is_utf8_editable_content($content))exit('{"code":-1,"msg":"仅支持编辑 UTF-8 文本文件"}');

	$hash = $row['hash'];
	if(!save_storage_content($hash, $content, $row['type'])){
		exit(json_encode(['code'=>-1, 'msg'=>'保存文件内容失败', 'errmsg'=>$stor->errmsg()], JSON_UNESCAPED_UNICODE));
	}

	$sql = "UPDATE `pre_file` SET `size`=:size,`lasttime`=NOW() WHERE `id`=:id";
	if(!$DB->exec($sql, [':size'=>$size, ':id'=>$row['id']])){
		exit('{"code":-1,"msg":"保存数据库失败['.$DB->error().']"}');
	}

	exit(json_encode(['code'=>0, 'msg'=>'保存成功', 'hash'=>$hash, 'size'=>size_format($size)], JSON_UNESCAPED_UNICODE));
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
