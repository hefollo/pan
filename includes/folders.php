<?php
/*
 * 用户文件夹（虚拟目录），相关决定 DEC-20260928-001。
 *
 * 文件夹只是数据库里的归属关系：pre_folder 记目录树，pre_file.folder_id 记文件在哪一层（0 = 根目录）。
 * 物理文件照旧按内容 hash 平铺在存储里、多条记录共用一份（秒传），所以：
 *   - 移动、改名只改数据库，外链（down.php/{token}）不变；
 *   - 复制是新建一条指向同一 hash 的记录，不多占存储，删除时的引用计数照旧成立；
 *   - 对全部存储驱动都一样，IStorage 不需要任何目录操作。
 *
 * 归属者：登录用户按 uid；游客没有账号，按会话里的随机标识 gkey。
 * 和游客上传的文件（$_SESSION['fileids']）一样只活在这个浏览器会话里，登录时一起转给账号。
 */

define('FOLDER_NAME_MAX', 60);     //文件夹名最多多少个字
define('FOLDER_DEPTH_MAX', 10);    //最多几层
define('FOLDER_COUNT_MAX', 500);   //每个归属者最多多少个文件夹
define('FOLDER_BATCH_MAX', 200);   //一次移动/复制最多处理多少个文件

function get_folder_mode(){
	global $conf;
	$mode = isset($conf['folder_mode']) ? strtolower(trim((string)$conf['folder_mode'])) : 'login';
	return in_array($mode, ['all', 'login', 'uid'], true) ? $mode : 'login';
}

//总开关 + 开放范围，和在线编辑的 can_use_online_edit() 同一个思路
function can_use_folders(){
	global $conf, $islogin2, $uid;
	if(empty($conf['folder_open'])) return false;
	$mode = get_folder_mode();
	if($mode === 'all') return true;
	if(empty($islogin2)) return false;
	if($mode === 'login') return true;
	return in_array(intval($uid), parse_uid_list(isset($conf['folder_uids']) ? $conf['folder_uids'] : ''), true);
}

/*
 * 当前访客作为文件夹归属者的身份。
 * 游客在第一次建文件夹时才生成 gkey（$create=true），之前返回空 gkey，名下自然一个文件夹都没有。
 * uid=0 且 gkey='' 的组合在表里不会存在（游客的 gkey 必不为空，登录用户的 uid 必不为 0）。
 */
function folder_owner($create = false){
	global $islogin2, $uid;
	if(!empty($islogin2)) return ['uid'=>intval($uid), 'gkey'=>''];
	$key = isset($_SESSION['folder_gkey']) ? (string)$_SESSION['folder_gkey'] : '';
	if(!preg_match('/^[0-9a-f]{32}$/', $key)){
		if(!$create) return ['uid'=>0, 'gkey'=>''];
		$key = bin2hex(random_bytes(16));
		$_SESSION['folder_gkey'] = $key;
	}
	return ['uid'=>0, 'gkey'=>$key];
}

function folder_owner_empty($owner){
	return intval($owner['uid']) <= 0 && $owner['gkey'] === '';
}

/*
 * 归属者的全部文件夹，按 id 索引。数量有上限（FOLDER_COUNT_MAX），
 * 整棵树一次读出来在内存里算路径、层级、子树，比递归查库省事也不怕漏。
 */
function folder_all($owner){
	global $DB;
	if(folder_owner_empty($owner)) return [];
	$rows = $DB->getAll("SELECT id,parent_id,name,addtime FROM pre_folder WHERE uid=:uid AND gkey=:gkey ORDER BY name ASC, id ASC",
		[':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey']]);
	$all = [];
	foreach((array)$rows as $r){
		$r['id'] = intval($r['id']);
		$r['parent_id'] = intval($r['parent_id']);
		$all[$r['id']] = $r;
	}
	return $all;
}

function folder_get($id, $owner){
	global $DB;
	$id = intval($id);
	if($id <= 0 || folder_owner_empty($owner)) return null;
	$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid AND gkey=:gkey LIMIT 1",
		[':id'=>$id, ':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey']]);
	return $row ? $row : null;
}

//parent_id => [子文件夹 id...]
function folder_kids_index($all){
	$kids = [];
	foreach($all as $f) $kids[$f['parent_id']][] = $f['id'];
	return $kids;
}

//某个文件夹连同它下面所有层的 id（含自己）。万一数据里有环，按已访问集合截断
function folder_subtree_ids($all, $id){
	$kids = folder_kids_index($all);
	$ids = [$id => true];
	$queue = [$id];
	while($queue){
		$cur = array_shift($queue);
		if(empty($kids[$cur])) continue;
		foreach($kids[$cur] as $k){
			if(isset($ids[$k])) continue;
			$ids[$k] = true;
			$queue[] = $k;
		}
	}
	return array_keys($ids);
}

//从根到这个文件夹的路径（不含根目录本身），给面包屑用；遇到断链或环就停
function folder_path($all, $id){
	$path = [];
	$seen = [];
	$id = intval($id);
	while($id > 0 && isset($all[$id]) && !isset($seen[$id])){
		$seen[$id] = true;
		array_unshift($path, $all[$id]);
		$id = $all[$id]['parent_id'];
	}
	return $path;
}

//所在层数：根目录是 0，根目录下的文件夹是 1
function folder_depth($all, $id){
	return count(folder_path($all, $id));
}

//子树高度：只有自己算 1 层
function folder_height($all, $id){
	$kids = folder_kids_index($all);
	$height = 0;
	$level = [$id];
	$seen = [];
	while($level){
		$height++;
		$next = [];
		foreach($level as $cur){
			$seen[$cur] = true;
			if(empty($kids[$cur])) continue;
			foreach($kids[$cur] as $k){
				if(!isset($seen[$k])) $next[] = $k;
			}
		}
		$level = $next;
	}
	return $height;
}

/*
 * 文件夹名清洗。和文件名不同，文件夹名按原文入库、输出时再转义：
 * 它只出现在归属者自己的页面上，统一在输出口 htmlspecialchars，比入库转义好核对。
 * 路径分隔符和 Windows 非法字符一样去掉，免得看起来像路径。
 */
function folder_clean_name($name){
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'], '', (string)$name);
	$name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name);
	return trim((string)$name);
}

function folder_name_key($name){
	return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

function folder_name_taken($all, $parent_id, $name, $exclude_id = 0){
	$key = folder_name_key($name);
	foreach($all as $f){
		if($f['parent_id'] === intval($parent_id) && $f['id'] !== intval($exclude_id) && folder_name_key($f['name']) === $key) return true;
	}
	return false;
}

//移动、复制时目标位置有同名文件夹就自动加序号，和系统资源管理器的习惯一致
function folder_unique_name($all, $parent_id, $name, $exclude_id = 0){
	if(!folder_name_taken($all, $parent_id, $name, $exclude_id)) return $name;
	for($i = 2; $i < 1000; $i++){
		$try = $name.' ('.$i.')';
		if(!folder_name_taken($all, $parent_id, $try, $exclude_id)) return $try;
	}
	return $name.' ('.substr(md5(uniqid('', true)), 0, 6).')';
}

//校验用户输入的文件夹名，返回 [错误信息, 清洗后的名字]
function folder_check_name($raw){
	$name = folder_clean_name($raw);
	if($name === '') return ['文件夹名不能为空', ''];
	$len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
	if($len > FOLDER_NAME_MAX) return ['文件夹名不能超过 '.FOLDER_NAME_MAX.' 个字', ''];
	return ['', $name];
}

//接口传进来的 id 列表：数组或逗号分隔都认，去重、去掉非正数、截断到上限
function folder_int_ids($raw, $max){
	if(!is_array($raw)) $raw = explode(',', (string)$raw);
	$ids = [];
	foreach($raw as $v){
		$v = intval($v);
		if($v > 0) $ids[$v] = true;
	}
	return array_slice(array_keys($ids), 0, $max);
}

/*
 * 文件夹操作能动哪些文件：登录用户只认自己名下的；
 * 游客沿用 can_manage_file()（本会话上传、七天内），和游客删除、覆盖同一口径。
 */
function folder_can_touch_file($row){
	global $islogin2, $uid;
	if(!$row) return false;
	if(!empty($islogin2)) return intval($row['uid']) === intval($uid);
	return intval($row['uid']) === 0 && can_manage_file($row);
}

//游客「我的文件」能看到的记录：会话里最近 60 条，和 index.php ?m=mine 同一口径
function folder_guest_file_ids(){
	if(empty($_SESSION['fileids']) || !is_array($_SESSION['fileids'])) return [];
	$ids = [];
	foreach(array_reverse($_SESSION['fileids']) as $v){
		$v = intval($v);
		if($v > 0) $ids[$v] = true;
	}
	return array_slice(array_keys($ids), 0, 60);
}

//当前访客「我的文件」的范围条件（前面带空格，直接拼在 WHERE 后面）
function folder_file_scope_sql(){
	global $islogin2, $uid;
	if(!empty($islogin2)) return " uid=".intval($uid);
	$ids = folder_guest_file_ids();
	return $ids ? " id IN (".implode(',', $ids).")" : " 1=2";
}

/* ---------------- 上传与登录 ---------------- */

/*
 * 上传时选的目标文件夹。pre_upload 调一次，校验通过的 id 存进上传状态，
 * 后面分片、直传完成都按状态里的值放，不再信表单。
 * 功能关着、文件夹不存在或不是自己的，一律回根目录，不因为这个让上传失败。
 */
function folder_upload_target($raw){
	$id = intval($raw);
	if($id <= 0 || !can_use_folders()) return 0;
	return folder_get($id, folder_owner(false)) ? $id : 0;
}

//新建的上传记录放进目标文件夹。覆盖上传不调这个，文件保持原位置
function folder_place_file($file_id, $folder_id){
	global $DB;
	$folder_id = intval($folder_id);
	$file_id = intval($file_id);
	if($folder_id <= 0 || $file_id <= 0) return;
	$DB->exec("UPDATE pre_file SET folder_id=:f WHERE id=:id LIMIT 1", [':f'=>$folder_id, ':id'=>$file_id]);
}

//游客登录：本会话建的文件夹转到账号名下。文件由 user_login_session() 按会话记录转，folder_id 跟着走
function folder_transfer_guest($uid){
	global $DB;
	$key = isset($_SESSION['folder_gkey']) ? (string)$_SESSION['folder_gkey'] : '';
	if(!preg_match('/^[0-9a-f]{32}$/', $key) || intval($uid) <= 0) return;
	$DB->exec("UPDATE pre_folder SET uid=:uid, gkey='' WHERE uid=0 AND gkey=:k", [':uid'=>intval($uid), ':k'=>$key]);
	unset($_SESSION['folder_gkey']);
}

/*
 * 游客会话一丢，他建的文件夹就没人能再打开了。游客建新文件夹时顺手清一小批 30 天前的，
 * 里面的文件放回根目录（那些文件早已过了游客七天的管理期，放回去只是让 folder_id 不悬空）。
 */
function folder_gc_guest(){
	global $DB;
	if(mt_rand(1, 50) !== 1) return;
	$rows = $DB->getAll("SELECT id FROM pre_folder WHERE uid=0 AND addtime<:t LIMIT 200", [':t'=>date('Y-m-d H:i:s', time() - 30 * 86400)]);
	$ids = [];
	foreach((array)$rows as $r) $ids[] = intval($r['id']);
	if(!$ids) return;
	$in = implode(',', $ids);
	if($DB->exec("UPDATE pre_file SET folder_id=0 WHERE folder_id IN ({$in})") === false) return;
	$DB->exec("DELETE FROM pre_folder WHERE id IN ({$in}) AND uid=0");
}

/* ---------------- 文件夹操作（ajax.php 调用，返回给前端的数组） ---------------- */

function folder_result($code, $msg, $extra = []){
	return array_merge(['code'=>$code, 'msg'=>$msg], $extra);
}

function folder_create($parent_id, $raw_name){
	global $DB;
	$owner = folder_owner(true);
	$all = folder_all($owner);
	$parent_id = intval($parent_id);
	if($parent_id > 0 && !isset($all[$parent_id])) return folder_result(-1, '上级文件夹不存在，请刷新页面');
	list($err, $name) = folder_check_name($raw_name);
	if($err !== '') return folder_result(-1, $err);
	if(count($all) >= FOLDER_COUNT_MAX) return folder_result(-1, '最多只能建 '.FOLDER_COUNT_MAX.' 个文件夹');
	if(folder_depth($all, $parent_id) + 1 > FOLDER_DEPTH_MAX) return folder_result(-1, '文件夹最多 '.FOLDER_DEPTH_MAX.' 层');
	if(folder_name_taken($all, $parent_id, $name)) return folder_result(-1, '这里已经有同名的文件夹了');
	$ok = $DB->exec("INSERT INTO pre_folder (uid,gkey,parent_id,name,addtime) VALUES (:uid,:gkey,:p,:name,NOW())",
		[':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey'], ':p'=>$parent_id, ':name'=>$name]);
	if($ok === false) return folder_result(-1, '新建失败['.$DB->error().']');
	$id = intval($DB->lastInsertId());
	if(intval($owner['uid']) === 0) folder_gc_guest();
	return folder_result(0, '已新建文件夹', ['id'=>$id, 'name'=>$name]);
}

function folder_rename($id, $raw_name){
	global $DB;
	$owner = folder_owner(false);
	$all = folder_all($owner);
	$id = intval($id);
	if(!isset($all[$id])) return folder_result(-1, '文件夹不存在，请刷新页面');
	list($err, $name) = folder_check_name($raw_name);
	if($err !== '') return folder_result(-1, $err);
	if($name === $all[$id]['name']) return folder_result(0, '名称没有变化', ['name'=>$name]);
	if(folder_name_taken($all, $all[$id]['parent_id'], $name, $id)) return folder_result(-1, '这里已经有同名的文件夹了');
	$ok = $DB->exec("UPDATE pre_folder SET name=:name WHERE id=:id AND uid=:uid AND gkey=:gkey",
		[':name'=>$name, ':id'=>$id, ':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey']]);
	if($ok === false) return folder_result(-1, '重命名失败['.$DB->error().']');
	return folder_result(0, '重命名成功', ['name'=>$name]);
}

/*
 * 删除文件夹：连同所有子文件夹一起删，里面的文件（含子文件夹里的）全部挪回根目录，不删文件。
 * 先挪文件再删文件夹：中途失败最多留下空文件夹，不会有文件指向不存在的文件夹。
 */
function folder_delete($ids){
	global $DB;
	$owner = folder_owner(false);
	$all = folder_all($owner);
	$del = [];
	foreach(folder_int_ids($ids, FOLDER_COUNT_MAX) as $id){
		if(!isset($all[$id])) continue;
		foreach(folder_subtree_ids($all, $id) as $sub) $del[$sub] = true;
	}
	$del = array_keys($del);
	if(!$del) return folder_result(-1, '文件夹不存在，请刷新页面');
	$in = implode(',', array_map('intval', $del));
	$files = intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE folder_id IN ({$in})"));
	if($DB->exec("UPDATE pre_file SET folder_id=0 WHERE folder_id IN ({$in})") === false){
		return folder_result(-1, '删除失败['.$DB->error().']');
	}
	if($DB->exec("DELETE FROM pre_folder WHERE id IN ({$in}) AND uid=:uid AND gkey=:gkey", [':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey']]) === false){
		return folder_result(-1, '删除失败['.$DB->error().']');
	}
	$msg = '已删除 '.count($del).' 个文件夹';
	if($files > 0) $msg .= '，其中的 '.$files.' 个文件已移到根目录';
	return folder_result(0, $msg, ['folders'=>count($del), 'files'=>$files]);
}

/*
 * 校验要移动/复制的文件夹：都得是自己的；目标不能是它们自己或它们的子文件夹；层数不能超。
 * 返回错误信息，空串表示通过。
 */
function folder_check_transfer($all, $folder_ids, $target){
	$target_depth = folder_depth($all, $target);
	foreach($folder_ids as $fid){
		if(!isset($all[$fid])) return '要操作的文件夹不存在，请刷新页面';
		if(in_array($target, folder_subtree_ids($all, $fid), true)) return '不能把文件夹放进它自己或它的子文件夹里';
		if($target_depth + folder_height($all, $fid) > FOLDER_DEPTH_MAX) return '文件夹最多 '.FOLDER_DEPTH_MAX.' 层，放过去会超出';
	}
	return '';
}

function folder_move($file_ids, $folder_ids, $target){
	global $DB;
	$owner = folder_owner(false);
	$all = folder_all($owner);
	$target = intval($target);
	if($target > 0 && !isset($all[$target])) return folder_result(-1, '目标文件夹不存在，请刷新页面');
	$file_ids = folder_int_ids($file_ids, FOLDER_BATCH_MAX + 1);
	$folder_ids = folder_int_ids($folder_ids, FOLDER_COUNT_MAX);
	if(!$file_ids && !$folder_ids) return folder_result(-1, '请先选择要移动的文件或文件夹');
	if(count($file_ids) > FOLDER_BATCH_MAX) return folder_result(-1, '一次最多移动 '.FOLDER_BATCH_MAX.' 个文件');
	$err = folder_check_transfer($all, $folder_ids, $target);
	if($err !== '') return folder_result(-1, $err);

	$moved_folders = 0;
	foreach($folder_ids as $fid){
		if($all[$fid]['parent_id'] === $target) continue;
		$name = folder_unique_name($all, $target, $all[$fid]['name'], $fid);
		$ok = $DB->exec("UPDATE pre_folder SET parent_id=:p, name=:name WHERE id=:id AND uid=:uid AND gkey=:gkey",
			[':p'=>$target, ':name'=>$name, ':id'=>$fid, ':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey']]);
		if($ok === false) return folder_result(-1, '移动失败['.$DB->error().']');
		//后面的同名判断要看到刚挪过去的这个
		$all[$fid]['parent_id'] = $target;
		$all[$fid]['name'] = $name;
		$moved_folders++;
	}

	$moved = 0; $skip = 0;
	foreach($file_ids as $id){
		$row = $DB->getRow("SELECT id,uid,addtime,folder_id FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$id]);
		if(!folder_can_touch_file($row)){ $skip++; continue; }
		if(intval($row['folder_id']) === $target) continue;
		if($DB->exec("UPDATE pre_file SET folder_id=:f WHERE id=:id LIMIT 1", [':f'=>$target, ':id'=>$id]) === false){ $skip++; continue; }
		$moved++;
	}
	$parts = [];
	if($moved_folders) $parts[] = $moved_folders.' 个文件夹';
	if($moved) $parts[] = $moved.' 个文件';
	$msg = $parts ? '已移动 '.implode('、', $parts) : '没有需要移动的内容';
	if($skip) $msg .= '，'.$skip.' 个文件无权操作已跳过';
	return folder_result(0, $msg, ['folders'=>$moved_folders, 'files'=>$moved, 'skip'=>$skip]);
}

/*
 * 复制出来的新文件名：目标位置已有同名文件时，按系统资源管理器的习惯加「 - 副本」。
 * 文件名在库里是转义过的形式，这里只在扩展名前面插纯文本，不影响转义。
 * $taken 是目标位置已有的文件名（小写）集合，按引用传，连续复制时后一个能看到前一个。
 */
function folder_copy_name($name, &$taken){
	$key = folder_name_key($name);
	if(!isset($taken[$key])){ $taken[$key] = true; return $name; }
	$dot = strrpos($name, '.');
	$base = $dot > 0 ? substr($name, 0, $dot) : $name;
	$ext = $dot > 0 ? substr($name, $dot) : '';
	for($i = 1; $i < 100; $i++){
		$try = $base.' - 副本'.($i > 1 ? ' ('.$i.')' : '').$ext;
		$k = folder_name_key($try);
		if(!isset($taken[$k])){ $taken[$k] = true; return $try; }
	}
	$try = $base.' - 副本 '.substr(md5(uniqid('', true)), 0, 6).$ext;
	$taken[folder_name_key($try)] = true;
	return $try;
}

//某个文件夹里（当前访客范围内）已有的文件名，给复制起名用
function folder_file_names($folder_id){
	global $DB;
	$taken = [];
	$rows = $DB->getAll("SELECT name FROM pre_file WHERE".folder_file_scope_sql()." AND folder_id=:f", [':f'=>intval($folder_id)]);
	foreach((array)$rows as $r) $taken[folder_name_key($r['name'])] = true;
	return $taken;
}

/*
 * 复制一条文件记录到某个文件夹。走的是秒传那条路（create_file_record_from_existing）：
 * 新记录指向同一份内容，有自己的 token 和外链；copied=1，不计入每日上传数。
 * 被冻结或待人工审核的文件不复制——复制出来也是冻结的，没有意义，还能被拿来刷记录。
 */
function folder_copy_file($row, $folder_id, $name){
	global $DB, $islogin2, $uid, $clientip;
	if(intval($row['block']) !== 0) return false;
	$record = create_file_record_from_existing($row, $name, $row['size'], $row['type'], $row['hide'], $row['pwd'], !empty($islogin2) ? intval($uid) : 0, $clientip);
	if(!$record) return false;
	$DB->exec("UPDATE pre_file SET folder_id=:f, copied=1 WHERE id=:id LIMIT 1", [':f'=>intval($folder_id), ':id'=>intval($record['id'])]);
	//标成复制之后「今日上传」要把它去掉，建记录时清过的那次不算数
	layout_cache_bump();
	//游客靠会话记录认领文件，不记进去复制出来的这条就成了没人能管的孤儿
	if(empty($islogin2)) $_SESSION['fileids'][] = intval($record['id']);
	return $record;
}

function folder_copy($file_ids, $folder_ids, $target){
	global $DB;
	$owner = folder_owner(false);
	$all = folder_all($owner);
	$target = intval($target);
	if($target > 0 && !isset($all[$target])) return folder_result(-1, '目标文件夹不存在，请刷新页面');
	$file_ids = folder_int_ids($file_ids, FOLDER_BATCH_MAX + 1);
	$folder_ids = folder_int_ids($folder_ids, FOLDER_COUNT_MAX);
	if(!$file_ids && !$folder_ids) return folder_result(-1, '请先选择要复制的文件或文件夹');
	$err = folder_check_transfer($all, $folder_ids, $target);
	if($err !== '') return folder_result(-1, $err);

	//选中的文件夹里如果还套着同样被选中的文件夹，只复制外层那个，免得同一份内容复制两遍
	$top = [];
	foreach($folder_ids as $fid){
		$inside = false;
		foreach($folder_ids as $other){
			if($other !== $fid && in_array($fid, folder_subtree_ids($all, $other), true)){ $inside = true; break; }
		}
		if(!$inside) $top[] = $fid;
	}
	$new_count = 0;
	$sub_files = [];
	foreach($top as $fid){
		$sub = folder_subtree_ids($all, $fid);
		$new_count += count($sub);
		$in = implode(',', array_map('intval', $sub));
		$rows = $DB->getAll("SELECT * FROM pre_file WHERE".folder_file_scope_sql()." AND folder_id IN ({$in}) ORDER BY id ASC");
		foreach((array)$rows as $r) $sub_files[] = $r;
	}
	if(count($all) + $new_count > FOLDER_COUNT_MAX) return folder_result(-1, '最多只能有 '.FOLDER_COUNT_MAX.' 个文件夹，复制后会超出');
	if(count($file_ids) + count($sub_files) > FOLDER_BATCH_MAX) return folder_result(-1, '一次最多复制 '.FOLDER_BATCH_MAX.' 个文件，请分几次操作');

	//先建文件夹：旧 id => 新 id，按层从上往下建，子文件夹才能挂到新的父文件夹上
	$map = [];
	$kids = folder_kids_index($all);
	foreach($top as $fid){
		$name = folder_unique_name($all, $target, $all[$fid]['name']);
		$queue = [[$fid, $target, $name]];
		while($queue){
			list($old, $parent, $fname) = array_shift($queue);
			$ok = $DB->exec("INSERT INTO pre_folder (uid,gkey,parent_id,name,addtime) VALUES (:uid,:gkey,:p,:name,NOW())",
				[':uid'=>intval($owner['uid']), ':gkey'=>$owner['gkey'], ':p'=>$parent, ':name'=>$fname]);
			if($ok === false) return folder_result(-1, '复制失败['.$DB->error().']');
			$new = intval($DB->lastInsertId());
			$map[$old] = $new;
			$all[$new] = ['id'=>$new, 'parent_id'=>$parent, 'name'=>$fname, 'addtime'=>date('Y-m-d H:i:s')];
			if(!empty($kids[$old])){
				foreach($kids[$old] as $k){
					if(!isset($map[$k])) $queue[] = [$k, $new, $all[$k]['name']];
				}
			}
		}
	}

	$copied = 0; $skip = 0;
	$taken_by_folder = [];
	//文件夹里的文件：放进对应的新文件夹，新文件夹是空的，名字不会撞
	foreach($sub_files as $r){
		if(!folder_can_touch_file($r) || !isset($map[intval($r['folder_id'])])){ $skip++; continue; }
		$dest = $map[intval($r['folder_id'])];
		if(!isset($taken_by_folder[$dest])) $taken_by_folder[$dest] = [];
		if(folder_copy_file($r, $dest, folder_copy_name($r['name'], $taken_by_folder[$dest]))) $copied++;
		else $skip++;
	}
	//单独选中的文件：放进目标文件夹，和已有文件同名时加「 - 副本」
	if($file_ids){
		$taken = folder_file_names($target);
		foreach($file_ids as $id){
			$r = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$id]);
			if(!folder_can_touch_file($r)){ $skip++; continue; }
			if(folder_copy_file($r, $target, folder_copy_name($r['name'], $taken))) $copied++;
			else $skip++;
		}
	}
	$parts = [];
	if($map) $parts[] = count($map).' 个文件夹';
	$parts[] = $copied.' 个文件';
	$msg = '已复制 '.implode('、', $parts);
	if($skip) $msg .= '，'.$skip.' 个文件无权操作、已冻结或待审核，已跳过';
	return folder_result(0, $msg, ['folders'=>count($map), 'files'=>$copied, 'skip'=>$skip]);
}

/*
 * 文件改名。个人中心的 rename 和游客「我的文件」的改名都走这里，规则只有一份：
 * 清洗必须和 ajax.php 的 pre_upload 完全一致：先 htmlspecialchars（带 ENT_QUOTES，文件名会被拼进
 * 播放器的 JS 字符串），再去掉路径分隔符和 Windows 非法字符。库里存的就是转义后的形式，
 * 列表页是直接 echo 出来的，这里漏了同样的处理就成了存储型 XSS；
 * 后台配置的违禁文件名照样拦；扩展名跟着原文件的 type 走，外链是 down.php/{token}.{type}，
 * 让用户把 a.png 改成 a.jpg 只会造成"下载下来打不开"的困惑。
 * 返回 [错误信息, 新文件名]，错误信息为空串表示成功。
 */
function file_rename_record($row, $raw_name){
	global $DB, $conf;
	if(intval($row['block']) === 1) return ['文件已被冻结，无法重命名', ''];
	$name = trim(htmlspecialchars((string)$raw_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'], '', $name);
	//控制字符在下载时会被拼进 Content-Disposition 头，必须清掉
	$name = trim(preg_replace('/[\x00-\x1f\x7f]/', '', $name));
	if($name === '') return ['文件名不能为空', ''];
	if(mb_strlen($name, 'UTF-8') > 120) return ['文件名不能超过 120 个字', ''];
	if(!empty($conf['name_block'])){
		foreach(explode('|', $conf['name_block']) as $bad){
			if($bad !== '' && strpos($name, $bad) !== false) return ['文件名包含不允许的内容', ''];
		}
	}
	$ext = $row['type'] ? strtolower($row['type']) : '';
	if($ext !== ''){
		$cur = strtolower(get_file_ext($name));
		if($cur !== $ext) $name = preg_replace('/\.[^.]*$/', '', $name).'.'.$ext;
	}
	if($DB->exec("UPDATE pre_file SET name=:name WHERE id=:id LIMIT 1", [':name'=>$name, ':id'=>intval($row['id'])]) === false){
		return ['重命名失败['.$DB->error().']', ''];
	}
	//首页「最近上传」里显示着文件名
	layout_cache_bump();
	return ['', $name];
}

/* ---------------- 页面渲染（个人中心 user.php 和游客 index.php?m=mine 共用） ---------------- */

function folder_h($s){
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

//当前所在文件夹：参数里给的不存在或不是自己的，回到根目录
function folder_current($all){
	$id = isset($_GET['folder']) ? intval($_GET['folder']) : 0;
	return ($id > 0 && isset($all[$id])) ? $id : 0;
}

/*
 * 列表上方的工具条：面包屑 + 新建文件夹 / 粘贴 / 上传到这里。
 * $url 是不带 folder 参数的列表地址（结尾可以直接接 &folder=）。
 * 面包屑上的上级目录都是拖放目标（data-fd-drop），当前所在的那一级不是。
 */
function folder_render_bar($all, $current, $url, $searching, $upload_url = ''){
	$html = '<div class="fd-bar">'
		.'<nav class="fd-crumb" aria-label="文件夹路径">';
	$path = folder_path($all, $current);
	$root_attr = ($current > 0 || $searching) ? ' href="'.folder_h($url).'" data-fd-drop="0"' : '';
	$html .= '<a class="fd-crumb-item'.($current === 0 && !$searching ? ' is-current' : '').'"'.$root_attr.'><i class="fa fa-hdd-o" aria-hidden="true"></i> 我的文件</a>';
	if($searching){
		$html .= '<i class="fa fa-angle-right fd-crumb-sep" aria-hidden="true"></i><span class="fd-crumb-item is-current">搜索 / 筛选结果（全部文件夹）</span>';
	}else{
		$last = count($path) - 1;
		foreach($path as $i => $f){
			$html .= '<i class="fa fa-angle-right fd-crumb-sep" aria-hidden="true"></i>';
			if($i === $last){
				$html .= '<span class="fd-crumb-item is-current">'.folder_h($f['name']).'</span>';
			}else{
				$html .= '<a class="fd-crumb-item" href="'.folder_h($url.'&folder='.$f['id']).'" data-fd-drop="'.$f['id'].'">'.folder_h($f['name']).'</a>';
			}
		}
	}
	$html .= '</nav><div class="fd-bar-acts">';
	if(!$searching){
		$html .= '<button type="button" class="uc-btn" id="fdPaste" hidden><i class="fa fa-clipboard" aria-hidden="true"></i> 粘贴<span id="fdPasteNum"></span></button>'
			.'<button type="button" class="uc-btn" id="fdNew"><i class="fa fa-folder-o" aria-hidden="true"></i> 新建文件夹</button>';
		if($upload_url !== '') $html .= '<a class="uc-btn uc-btn-primary" href="'.folder_h($upload_url).'"><i class="fa fa-upload" aria-hidden="true"></i> 上传到这里</a>';
	}
	$html .= '</div></div>';
	return $html;
}

/*
 * 当前层的子文件夹行，列数和文件表格一致（勾选 / 名称 / 大小 / 状态 / 时间 / 操作）。
 * 「大小」一栏写里面有几项：直接子文件夹数 + 直接文件数（按当前访客范围统计）。
 */
function folder_render_rows($all, $current, $url){
	global $DB;
	$rows = [];
	foreach($all as $f){
		if($f['parent_id'] === $current) $rows[] = $f;
	}
	if(!$rows) return '';
	$ids = [];
	foreach($rows as $f) $ids[] = $f['id'];
	$file_num = [];
	$rs = $DB->getAll("SELECT folder_id, count(*) AS num FROM pre_file WHERE".folder_file_scope_sql()." AND folder_id IN (".implode(',', $ids).") GROUP BY folder_id");
	foreach((array)$rs as $r) $file_num[intval($r['folder_id'])] = intval($r['num']);
	$kids = folder_kids_index($all);
	$html = '';
	foreach($rows as $f){
		$n = (isset($kids[$f['id']]) ? count($kids[$f['id']]) : 0) + (isset($file_num[$f['id']]) ? $file_num[$f['id']] : 0);
		$link = $url.'&folder='.$f['id'];
		$html .= '<tr class="fd-row" data-folder-id="'.$f['id'].'" data-name="'.folder_h($f['name']).'">'
			.'<td class="uc-col-check"><input type="checkbox" class="fd-check" title="选择文件夹"></td>'
			.'<td class="uc-col-name"><a class="fd-link" href="'.folder_h($link).'"><i class="fa fa-folder fa-fw fd-icon" aria-hidden="true"></i><span class="uc-name">'.folder_h($f['name']).'</span></a></td>'
			.'<td class="uc-col-size"><span class="fd-count">'.($n > 0 ? $n.' 项' : '空').'</span></td>'
			.'<td class="uc-col-state"><span class="uc-badge">文件夹</span></td>'
			.'<td class="uc-col-time">'.folder_h($f['addtime']).'</td>'
			.'<td class="uc-col-act"><div class="uc-acts">'
				.'<a class="uc-act" href="'.folder_h($link).'" title="打开"><i class="fa fa-folder-open-o" aria-hidden="true"></i></a>'
				.'<button type="button" class="uc-act" data-fd="rename" title="重命名"><i class="fa fa-pencil" aria-hidden="true"></i></button>'
				.'<button type="button" class="uc-act uc-act-danger" data-fd="delete" title="删除文件夹（里面的文件移到根目录）"><i class="fa fa-trash" aria-hidden="true"></i></button>'
			.'</div></td></tr>';
	}
	return $html;
}

//搜索结果里标出文件在哪个文件夹，点了直接过去
function folder_render_loc($all, $folder_id, $url){
	$folder_id = intval($folder_id);
	if($folder_id <= 0 || !isset($all[$folder_id])) return '';
	$names = [];
	foreach(folder_path($all, $folder_id) as $f) $names[] = $f['name'];
	return '<a class="fd-loc" href="'.folder_h($url.'&folder='.$folder_id).'" title="打开所在文件夹"><i class="fa fa-folder-o" aria-hidden="true"></i> '.folder_h(implode(' / ', $names)).'</a>';
}

//批量操作条上的剪切 / 复制 / 移动到
function folder_render_batch_buttons(){
	return '<button type="button" class="uc-btn" id="fdCut"><i class="fa fa-scissors" aria-hidden="true"></i> 剪切</button>'
		.'<button type="button" class="uc-btn" id="fdCopy"><i class="fa fa-copy" aria-hidden="true"></i> 复制</button>'
		.'<button type="button" class="uc-btn" id="fdMoveTo"><i class="fa fa-share" aria-hidden="true"></i> 移动或复制到…</button>';
}

//前端配置 + 脚本。$file_check 是页面上文件行勾选框的选择器
function folder_render_script($current, $csrf, $searching, $file_check){
	global $islogin2, $uid;
	$cfg = [
		'current' => intval($current),
		'csrf' => (string)$csrf,
		'owner' => !empty($islogin2) ? 'u'.intval($uid) : 'g',
		'searching' => (bool)$searching,
		'list' => '.uc-filelist',
		'fileCheck' => $file_check,
		'nameMax' => FOLDER_NAME_MAX,
	];
	return '<script>var PAN_FOLDER = '.json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';</script>'
		."\n".'<script src="./assets/js/folders.js?v='.VERSION.'"></script>';
}
