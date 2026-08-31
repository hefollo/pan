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
		$state = $_SESSION['uploads'][$hash];
		if(isset($state['time']) && $state['time'] + UPLOAD_STATE_TTL < time()){
			clear_upload_state($hash);
			return null;
		}
		return $state;
	}
	if(isset($_SESSION['upload']) && is_array($_SESSION['upload']) && isset($_SESSION['upload']['hash']) && $_SESSION['upload']['hash'] === $hash){
		return $_SESSION['upload'];
	}
	return null;
}

//同时保留的上传状态上限；超过就丢掉最旧的，避免会话被刷爆
define('UPLOAD_STATE_MAX', 20);
//上传状态的有效期，超时的直接当作不存在
define('UPLOAD_STATE_TTL', 6 * 3600);

function set_upload_state($hash, $state){
	if(!isset($_SESSION['uploads']) || !is_array($_SESSION['uploads'])){
		$_SESSION['uploads'] = [];
	}
	$state['time'] = time();
	$_SESSION['uploads'][$hash] = $state;
	//先清掉过期的，再按先进先出砍掉超出上限的部分
	foreach($_SESSION['uploads'] as $k=>$v){
		if(!is_array($v) || !isset($v['time']) || $v['time'] + UPLOAD_STATE_TTL < time()){
			unset($_SESSION['uploads'][$k]);
		}
	}
	while(count($_SESSION['uploads']) > UPLOAD_STATE_MAX){
		array_shift($_SESSION['uploads']);
	}
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

/*
 * 内容已经写进存储、但没能建成数据库记录时，存储里会留下一个没人引用的对象。
 * 只有确认没有任何记录引用这个 hash 才删，避免误删别人秒传共用的同一份内容。
 */
function cleanup_orphan_object($hash){
	global $DB, $stor;
	$used = intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$hash]));
	if($used > 0)return false;
	return $stor->delete($hash);
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
	//覆盖上传：把已有记录的内容换成新文件，对外链接（token）保持不变
	$replace_id = isset($_POST['replace_id']) ? intval($_POST['replace_id']) : 0;
	$replace_row = null;
	if($replace_id > 0){
		$replace_row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$replace_id]);
		if(!$replace_row)exit('{"code":-1,"msg":"要覆盖的文件不存在"}');
		if(!can_manage_file($replace_row))exit('{"code":-1,"msg":"无权覆盖该文件"}');
		if($replace_row['block']==1)exit('{"code":-1,"msg":"文件已被冻结，无法覆盖"}');
	}
	$limit_size = get_effective_upload_size_limit();
	if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
		exit('{"code":-1,"msg":"上传文件大小限制'.$limit_size.'MB"}');
	}
	/*
	 * 频率限制：原来只有"每天多少个"，脚本几秒钟就能刷几百条。
	 * 这里按分钟卡一道，登录用户按 uid、游客按 ipkey（伪造不了的来源，IPv6 归并到 /64）。
	 */
	if(!$replace_row){
		$per_minute = isset($conf['upload_per_minute']) ? intval($conf['upload_per_minute']) : 10;
		if($per_minute > 0){
			$since = date("Y-m-d H:i:s", time() - 60);
			if($islogin2){
				$mincount = $DB->getColumn("SELECT count(*) from pre_file WHERE uid=:uid AND addtime>=:t", [':uid'=>intval($uid), ':t'=>$since]);
			}else{
				$mincount = $DB->getColumn("SELECT count(*) from pre_file WHERE ipkey=:k AND addtime>=:t", [':k'=>client_ip_key(), ':t'=>$since]);
			}
			if(intval($mincount) >= $per_minute){
				exit('{"code":-1,"msg":"上传太频繁了，请稍后再试"}');
			}
		}
	}
	$upload_limit = get_effective_upload_count_limit();
	if(!$replace_row && $upload_limit>0){
		$thisday = date("Y-m-d 00:00:00");
		if($islogin2){
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."'");
		}else{
			//按 ipkey 统计：伪造 X-Forwarded-For 换不掉这个值，IPv6 也不会一人一个额度
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE ipkey=:k AND addtime>=:t", [':k'=>client_ip_key(), ':t'=>$thisday]);
		}
		if($ipcount >= $upload_limit){
			exit('{"code":-1,"msg":"你今天上传文件的数量已超过限制"}');
		}
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row && $replace_row){
		//覆盖的新内容站内已经有了，物理文件不用再传，直接把记录的内容换掉
		if(!replace_file_record($replace_row, $name, $hash, $size, $ext, $uid, $clientip))exit('{"code":-1,"msg":"替换失败'.$DB->error().'","error":"database"}');
		$result = ['code'=>1, 'msg'=>'替换成功，链接保持不变', 'exists'=>1, 'hash'=>$hash, 'token'=>$replace_row['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$replace_row['id']];
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}
	if($row){
		//秒传：跳过物理上传，但要为这次上传建独立记录，上传者才能在“我的文件”里看到并拥有自己的链接
		$record = create_file_record_from_existing($row, $name, $size, $ext, $hide, $pwd, $uid, $clientip);
		if(!$record)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
		$_SESSION['fileids'][] = $record['id'];
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'token'=>$record['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$record['id']];
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
			'pwd' => $pwd,
			'replace_id' => $replace_id
		]);
		$result = ['code'=>0, 'third'=>true, 'hash'=>$hash, 'url'=>$param['url'], 'post'=>$param['post']];
		exit(json_encode($result));
	}else{
		$chunksize = 32 * 1024 * 1024; //分块上传，每块大小
		$chunks = ceil($size / $chunksize);
		set_upload_state($hash, [
			'chunksize' => $chunksize,
			'chunks' => $chunks,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd,
			'replace_id' => $replace_id
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
	//分块序号必须落在本次上传声明的范围内，否则可以往临时目录里随意写文件
	if($chunk < 1 || $chunk > $chunks)exit('{"code":-1,"msg":"分块序号不合法"}');
	//单个分块不能超过约定的分块大小，否则声明一个小文件也能塞进大量数据
	$chunksize = isset($upload_state['chunksize']) ? intval($upload_state['chunksize']) : 0;
	if($chunksize > 0 && intval($_FILES['file']['size']) > $chunksize){
		exit('{"code":-1,"msg":"分块大小超出限制"}');
	}
	$ext = $upload_state['ext'];
	$declared_size = intval($upload_state['size']);
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
			//必须先校验再写入存储：写完再校验的话，校验不通过就会在存储里留下删不掉的孤儿对象
			if($real_size != $declared_size || $real_hash != $hash){
				@unlink($savePathTemp);
				clear_upload_state($hash);
				if($mergeLock){
					flock($mergeLock, LOCK_UN);
					fclose($mergeLock);
					@unlink($mergeLockFile);
				}
				exit($real_size != $declared_size ? '{"code":-1,"msg":"文件大小校验失败"}' : '{"code":-1,"msg":"文件MD5校验失败"}');
			}
			$result = $stor->savefile($hash, $savePathTemp, minetype($ext));
			upload_debug_step($debug, 'storage_save_ms');
			//合并出来的临时文件用完就删，之前无论成功失败都会留在临时目录里
			@unlink($savePathTemp);
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
		//同样先校验再落盘；PHP 会自己清掉没被 move 走的上传临时文件
		if($real_size != $declared_size || $real_hash != $hash){
			clear_upload_state($hash);
			exit($real_size != $declared_size ? '{"code":-1,"msg":"文件大小校验失败"}' : '{"code":-1,"msg":"文件MD5校验失败"}');
		}
		$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
		upload_debug_step($debug, 'storage_upload_ms');
		if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	//大小和 MD5 在写入存储之前已经校验过了
	$size = $declared_size;
	upload_debug_step($debug, 'validate_ms');

	$name = $upload_state['name'];
	$hide = $upload_state['hide'];
	$pwd = $upload_state['pwd'];

	//覆盖上传：新内容已经写进存储，把原记录指过去即可，token（对外链接）保持不变
	$replace_id = isset($upload_state['replace_id']) ? intval($upload_state['replace_id']) : 0;
	if($replace_id > 0){
		$replace_row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$replace_id]);
		//下面几种情况内容已经写进存储但不会建立引用，要把孤儿对象清掉
		if(!$replace_row){cleanup_orphan_object($hash);exit('{"code":-1,"msg":"要覆盖的文件不存在"}');}
		if(!can_manage_file($replace_row)){cleanup_orphan_object($hash);exit('{"code":-1,"msg":"无权覆盖该文件"}');}
		if($replace_row['block']==1){cleanup_orphan_object($hash);exit('{"code":-1,"msg":"文件已被冻结，无法覆盖"}');}
		clear_upload_state($hash);
		if(!replace_file_record($replace_row, $name, $hash, $size, $ext, $uid, $clientip)){
			cleanup_orphan_object($hash);
			exit('{"code":-1,"msg":"替换失败'.$DB->error().'","error":"database"}');
		}
		$result = ['code'=>1, 'msg'=>'替换成功，链接保持不变', 'exists'=>0, 'hash'=>$hash, 'token'=>$replace_row['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$replace_row['id']];
		$result['debug_timing'] = upload_debug_finish($debug);
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	upload_debug_step($debug, 'duplicate_query_ms');
	if($row){
		clear_upload_state($hash);
		//秒传：跳过物理上传，但要为这次上传建独立记录，上传者才能在“我的文件”里看到并拥有自己的链接
		$record = create_file_record_from_existing($row, $name, $size, $ext, $hide, $pwd, $uid, $clientip);
		if(!$record)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
		$_SESSION['fileids'][] = $record['id'];
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'token'=>$record['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$record['id']];
		$result['debug_timing'] = upload_debug_finish($debug);
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}

	//统一走 create_file_record，它负责生成访问用的 token，并顺带做图片检测和违规留档
	$record = create_file_record($name, $hash, $size, $ext, $hide, $pwd, $uid, $clientip);
	if(!$record){
		cleanup_orphan_object($hash);
		exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	}
	$id = $record['id'];
	//图片检测/视频审核已并入 create_file_record，这一步的耗时含建记录和审核
	upload_debug_step($debug, 'db_insert_ms');

	$_SESSION['fileids'][] = $id;
	clear_upload_state($hash);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'token'=>$record['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
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

	//覆盖上传：新内容已经写进存储，把原记录指过去即可，token（对外链接）保持不变
	$replace_id = isset($upload_state['replace_id']) ? intval($upload_state['replace_id']) : 0;
	if($replace_id > 0){
		$replace_row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$replace_id]);
		//下面几种情况内容已经写进存储但不会建立引用，要把孤儿对象清掉
		if(!$replace_row){cleanup_orphan_object($hash);exit('{"code":-1,"msg":"要覆盖的文件不存在"}');}
		if(!can_manage_file($replace_row)){cleanup_orphan_object($hash);exit('{"code":-1,"msg":"无权覆盖该文件"}');}
		if($replace_row['block']==1){cleanup_orphan_object($hash);exit('{"code":-1,"msg":"文件已被冻结，无法覆盖"}');}
		clear_upload_state($hash);
		if(!replace_file_record($replace_row, $name, $hash, $size, $ext, $uid, $clientip)){
			cleanup_orphan_object($hash);
			exit('{"code":-1,"msg":"替换失败'.$DB->error().'","error":"database"}');
		}
		$result = ['code'=>1, 'msg'=>'替换成功，链接保持不变', 'exists'=>0, 'hash'=>$hash, 'token'=>$replace_row['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$replace_row['id']];
		$result['debug_timing'] = upload_debug_finish($debug);
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	upload_debug_step($debug, 'duplicate_query_ms');
	if($row){
		clear_upload_state($hash);
		//秒传：跳过物理上传，但要为这次上传建独立记录，上传者才能在“我的文件”里看到并拥有自己的链接
		$record = create_file_record_from_existing($row, $name, $size, $ext, $hide, $pwd, $uid, $clientip);
		if(!$record)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
		$_SESSION['fileids'][] = $record['id'];
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'token'=>$record['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$record['id']];
		$result['debug_timing'] = upload_debug_finish($debug);
		set_upload_csrf_token($result);
		exit(json_encode($result));
	}

	//统一走 create_file_record，它负责生成访问用的 token，并顺带做图片检测和违规留档
	$record = create_file_record($name, $hash, $size, $ext, $hide, $pwd, $uid, $clientip);
	if(!$record){
		cleanup_orphan_object($hash);
		exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	}
	$id = $record['id'];
	//图片检测/视频审核已并入 create_file_record，这一步的耗时含建记录和审核
	upload_debug_step($debug, 'db_insert_ms');

	$_SESSION['fileids'][] = $id;
	clear_upload_state($hash);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'token'=>$record['token'], 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	$result['debug_timing'] = upload_debug_finish($debug);
	set_upload_csrf_token($result);
	exit(json_encode($result));
break;

case 'deleteFile':
	//前台传过来的是文件链接上的 token，不是内容哈希，两者都是32位十六进制，用错字段查不到记录
	$token = isset($_POST['hash'])?trim($_POST['hash']):exit('{"code":-1,"msg":"no hash"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $token))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `token`=:token", [':token'=>$token]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	if($islogin2 && $row['uid']!=$uid || !$islogin2 && (!isset($_SESSION['fileids']) || !in_array($row['id'], $_SESSION['fileids'])))exit('{"code":-1,"msg":"无权限"}');
	if($row['block']==1)exit('{"code":-1,"msg":"文件已被冻结，无法删除"}');
	if(!$islogin2 && strtotime($row['addtime'])<strtotime("-7 days"))exit('{"code":-1,"msg":"无法删除7天前的文件"}');
	//同一份内容可能被多条记录共享，只有最后一条引用被删掉时才清理物理文件
	delete_file_blob_if_orphaned($row['hash'], $row['id']);
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

	//内容按新哈希另存：同一份内容可能被多条记录共享（秒传），就地覆盖旧哈希会把别人的文件一起改掉
	$old_hash = $row['hash'];
	$hash = md5($content);
	if(!save_storage_content($hash, $content, $row['type'])){
		exit(json_encode(['code'=>-1, 'msg'=>'保存文件内容失败', 'errmsg'=>$stor->errmsg()], JSON_UNESCAPED_UNICODE));
	}

	$sql = "UPDATE `pre_file` SET `size`=:size,`hash`=:hash,`lasttime`=NOW() WHERE `id`=:id";
	if(!$DB->exec($sql, [':size'=>$size, ':hash'=>$hash, ':id'=>$row['id']])){
		exit('{"code":-1,"msg":"保存数据库失败['.$DB->error().']"}');
	}
	if($old_hash !== $hash)delete_file_blob_if_orphaned($old_hash, $row['id']);
	//在线编辑同样是内容替换，一并纳入后台“覆盖记录”审计
	add_replace_log($row, ['name'=>$row['name'], 'type'=>$row['type'], 'size'=>$size, 'hash'=>$hash], $uid, $clientip, 'edit');

	exit(json_encode(['code'=>0, 'msg'=>'保存成功', 'hash'=>$hash, 'size'=>size_format($size)], JSON_UNESCAPED_UNICODE));
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
