<?php
/* 存储空间是当前文件占用，不是流量。空间包逐单计时，读取时排除到期包，无需定时任务。 */
function level_space_mb($level){
	if(level_is_admin($level))return 0;
	$value = isset($level['space']) ? intval($level['space']) : 0;
	if($value >= 0)return $value;
	$normal = level_builtin(2);
	return max(0, intval(isset($normal['space']) ? $normal['space'] : 0));
}

function space_current_uid(){
	global $islogin2, $uid;
	return !empty($islogin2) ? intval($uid) : 0;
}

function space_error($fallback = '存储空间检查失败，请稍后重试'){
	return !empty($GLOBALS['_space_error']) ? $GLOBALS['_space_error'] : $fallback;
}

function space_info($uid = null, $ipkey = null){
	global $DB;
	if($uid === null)$uid = space_current_uid();
	$uid = max(0, intval($uid));
	if($ipkey === null)$ipkey = client_ip_key();
	$user = $uid > 0 ? $DB->getRow('SELECT * FROM pre_user WHERE uid=:uid LIMIT 1', [':uid'=>$uid]) : null;
	if($uid > 0 && !$user)return false;
	$base = level_space_mb($uid > 0 ? user_level($user) : level_builtin(1)) * 1048576;
	$where = $uid > 0 ? 'uid=:uid' : 'uid=0 AND ipkey=:ipkey';
	$args = $uid > 0 ? [':uid'=>$uid] : [':ipkey'=>$ipkey];
	//复制、私密、冻结、待审文件都算占用；实际删除记录后才释放。
	$used = $DB->getColumn('SELECT COALESCE(SUM(size),0) FROM pre_file WHERE '.$where, $args);
	if($used === false || $used === null)return false;
	$pack = ['bytes'=>0, 'expiretime'=>null];
	if($uid > 0){
		$pack = $DB->getRow('SELECT COALESCE(SUM(bytes),0) AS bytes, MIN(expiretime) AS expiretime FROM pre_space_pack WHERE uid=:uid AND expiretime>NOW()', [':uid'=>$uid]);
		if(!$pack)return false;
	}
	$total = $base + max(0, (int)$pack['bytes']);
	return ['used'=>max(0, (int)$used), 'base'=>$base, 'pack'=>max(0, (int)$pack['bytes']), 'quota'=>$total,
		'unlimited'=>$base === 0, 'left'=>$base === 0 ? null : max(0, $total - (int)$used), 'next_expire'=>$pack['expiretime']];
}

function space_text($uid = null){
	$info = space_info($uid);
	if(!$info)return '暂时无法读取';
	$text = size_format($info['used']).' / '.($info['unlimited'] ? '不限' : size_format($info['quota']));
	if(!$info['unlimited'] && $info['used'] > $info['quota'])$text .= '（已超额）';
	return $text;
}

function space_pack_list($uid){
	global $DB;
	$rows = $DB->getAll('SELECT bytes, addtime, expiretime FROM pre_space_pack WHERE uid=:uid AND expiretime>NOW() ORDER BY expiretime,id', [':uid'=>intval($uid)]);
	$out = [];
	if(is_array($rows))foreach($rows as $row)$out[] = ['size'=>size_format($row['bytes']), 'expire'=>$row['expiretime']];
	return $out;
}

function space_pack_view($uid, $text){
	$out = [];
	foreach(space_pack_list($uid) as $pack){
		$out[] = ['text_html'=>theme_text_html($text, ['size'=>htmlspecialchars($pack['size'], ENT_QUOTES, 'UTF-8'), 'expire'=>htmlspecialchars($pack['expire'], ENT_QUOTES, 'UTF-8')])];
	}
	return $out;
}

//正增长才检查余额：降级或包过期后仍能删除、缩小、等大替换，不自动删除已有文件。
function space_check($size, $uid, $ipkey, $old_id = 0){
	global $DB;
	$GLOBALS['_space_error'] = '';
	$size = intval($size);
	if($size < 0){$GLOBALS['_space_error'] = '文件大小无效'; return false;}
	$old_size = 0;
	if($old_id > 0){
		$old = $DB->getRow('SELECT uid, ipkey, size FROM pre_file WHERE id=:id LIMIT 1', [':id'=>intval($old_id)]);
		if(!$old || intval($old['uid']) !== intval($uid) || (!$uid && $old['ipkey'] !== $ipkey))return false;
		$old_size = max(0, (int)$old['size']);
		if($size <= $old_size)return true;
	}
	$info = space_info($uid, $ipkey);
	if(!$info)return false;
	if($info['unlimited'] || $size - $old_size <= $info['left'])return true;
	$GLOBALS['_space_error'] = '存储空间不足：已用 '.size_format($info['used']).'，总容量 '.size_format($info['quota']).'。请删除文件、升级会员或购买空间包';
	return false;
}

//必须在已有的内容锁之后拿容量锁；只包住容量复查与写入，避免并发上传/复制各自看到同一份余额。
function space_write($uid, $ipkey, $size, $old_id, $write){
	global $DB;
	$GLOBALS['_space_error'] = '';
	$key = intval($uid) > 0 ? 'u:'.intval($uid) : 'ip:'.$ipkey;
	$name = $DB->getColumn("SELECT CONCAT('pan:space:', MD5(CONCAT(DATABASE(), ':', :key)))", [':key'=>$key]);
	if(!is_string($name) || strpos($name, 'pan:space:') !== 0)return false;
	$locked = $DB->getColumn('SELECT GET_LOCK(:name, 10)', [':name'=>$name]);
	if($locked !== 1 && $locked !== '1'){$GLOBALS['_space_error'] = '空间正在更新，请稍后重试'; return false;}
	try{
		if(!space_check($size, $uid, $ipkey, $old_id))return false;
		return $write();
	}finally{
		$DB->getColumn('SELECT RELEASE_LOCK(:name)', [':name'=>$name]);
	}
}

function space_grant_pack($uid, $order){
	global $DB;
	$mb = isset($order['space']) ? intval($order['space']) : 0;
	$days = intval($order['days']);
	$id = isset($order['id']) ? intval($order['id']) : 0;
	if($uid <= 0 || $id <= 0 || $mb <= 0 || $mb > 2147483647 || $days <= 0 || $days > 36500)return false;
	//订单唯一键保证补发与重复回调都不再加容量，也不延长原包的期限。
	return $DB->exec('INSERT INTO pre_space_pack (uid,order_id,bytes,addtime,expiretime) VALUES (:uid,:oid,:bytes,NOW(),DATE_ADD(NOW(), INTERVAL :days DAY)) ON DUPLICATE KEY UPDATE order_id=order_id',
		[':uid'=>intval($uid), ':oid'=>$id, ':bytes'=>$mb * 1048576, ':days'=>$days]) !== false;
}

//登录认领也会增加账号空间；整批放得下才转移，放不下的游客文件保持原状。
function space_claim_guest_files($uid, $ids){
	global $DB;
	$ids = array_filter(array_unique(array_map('intval', (array)$ids)), function($id){return $id > 0;});
	if(!$ids)return true;
	$in = implode(',', $ids);
	$files = $DB->getAll('SELECT hash FROM pre_file WHERE uid=0 AND id IN ('.$in.')');
	if(!is_array($files) || !lock_file_blobs(array_column($files, 'hash')))return false;
	$bytes = $DB->getColumn('SELECT COALESCE(SUM(size),0) FROM pre_file WHERE uid=0 AND id IN ('.$in.')');
	if($bytes === false || $bytes === null)return false;
	return space_write($uid, '', (int)$bytes, 0, function() use ($DB, $uid, $in){
		return $DB->exec('UPDATE pre_file SET uid=:uid WHERE uid=0 AND id IN ('.$in.')', [':uid'=>intval($uid)]) !== false;
	});
}
