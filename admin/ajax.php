<?php
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'getcount':
	$thtime=date("Y-m-d").' 00:00:00';
	$lastday=date("Y-m-d",strtotime("-1 day")).' 00:00:00';
	$count1=$DB->getColumn("SELECT count(*) from pre_file");
	$count2=$DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$thtime'");
	$count3=$DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$lastday' AND addtime<'$thtime'");
	$count4=$DB->getColumn("SELECT count(*) from pre_user");

	$result=["code"=>0,"count1"=>$count1,"count2"=>$count2,"count3"=>$count3,"count4"=>$count4];
	exit(json_encode($result));
break;
case 'set':
	if(isset($_POST['green_label_porn'])){
		$_POST['green_label_porn'] = implode(',',$_POST['green_label_porn']);
	}
	if(isset($_POST['green_label_terrorism'])){
		$_POST['green_label_terrorism'] = implode(',',$_POST['green_label_terrorism']);
	}
	foreach($_POST as $k=>$v){
		saveSetting($k, $v);
	}
	exit('{"code":0,"msg":"succ"}');
break;
case 'iptype':
	$result = [
	['name'=>'0_X_FORWARDED_FOR', 'ip'=>real_ip(0), 'city'=>get_ip_city(real_ip(0))],
	['name'=>'1_X_REAL_IP', 'ip'=>real_ip(1), 'city'=>get_ip_city(real_ip(1))],
	['name'=>'2_REMOTE_ADDR', 'ip'=>real_ip(2), 'city'=>get_ip_city(real_ip(2))]
	];
	exit(json_encode($result));
break;
case 'userList':
	$sql=" 1=1";
	$type_arr = ['qq'=>'QQ','wx'=>'微信'];
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `enable`={$dstatus}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$sql.=" AND `uid`='{$kw}'";
		}elseif($type == 2){
			$sql.=" AND `openid`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `nickname` LIKE '%{$kw}%'";
		}elseif($type == 4){
			$sql.=" AND `loginip`='{$kw}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_user WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_user WHERE{$sql} order by uid desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		$row['type'] = $type_arr[$row['type']];
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'setUserEnable':
	$uid=intval($_POST['uid']);
	$enable=intval($_POST['enable']);
	$sql = "UPDATE pre_user SET enable='$enable' WHERE uid='$uid'";
	if($DB->exec($sql)!==false)exit('{"code":0,"msg":"修改用户成功！"}');
	else exit('{"code":-1,"msg":"修改用户失败['.$DB->error().']"}');
break;
case 'saveUserInfo':
	$uid=intval($_POST['uid']);
	$level=intval($_POST['level']);
	$upload_size = (isset($_POST['upload_size']) && $_POST['upload_size'] !== '') ? intval($_POST['upload_size']) : -1;
	$upload_limit = (isset($_POST['upload_limit']) && $_POST['upload_limit'] !== '') ? intval($_POST['upload_limit']) : -1;
	$expiretime = null;
	if(isset($_POST['expire_days']) && $_POST['expire_days'] !== ''){
		$expire_days = intval($_POST['expire_days']);
		if($expire_days > 0) $expiretime = date('Y-m-d H:i:s', strtotime('+'.$expire_days.' days'));
	}
	if($expiretime === null && isset($_POST['expiretime']) && trim($_POST['expiretime']) !== ''){
		$expiretime_input = str_replace('T', ' ', trim($_POST['expiretime']));
		$expire_timestamp = strtotime($expiretime_input);
		if($expire_timestamp !== false) $expiretime = date('Y-m-d H:i:s', $expire_timestamp);
	}
	if($upload_size < -1)$upload_size = -1;
	if($upload_limit < -1)$upload_limit = -1;
	$sql = "UPDATE pre_user SET level=:level, upload_size=:upload_size, upload_limit=:upload_limit, expiretime=:expiretime WHERE uid=:uid";
	if($DB->exec($sql, [':level'=>$level, ':upload_size'=>$upload_size, ':upload_limit'=>$upload_limit, ':expiretime'=>$expiretime, ':uid'=>$uid])!==false)exit('{"code":0,"msg":"修改用户成功！"}');
	else exit('{"code":-1,"msg":"修改用户失败['.$DB->error().']"}');
break;
case 'delUser':
	$uid=intval($_POST['uid']);
	$row=$DB->getRow("select * from pre_user where uid='$uid' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前用户不存在！"}');
	$sql = "DELETE FROM pre_user WHERE uid='$uid'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;
case 'sponsorList':
	$sql=" 1=1";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND `name` LIKE '%{$kw}%'";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_sponsor WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_sponsor WHERE{$sql} order by id desc limit $offset,$limit");
	exit(json_encode(['total'=>$total, 'rows'=>$list], JSON_UNESCAPED_UNICODE));
break;
case 'saveSponsorInfo':
	$id = intval($_POST['id']);
	$name = trim(htmlspecialchars($_POST['name']));
	$platform = trim(htmlspecialchars($_POST['platform']));
	$amount = trim(htmlspecialchars($_POST['amount']));
	$sponsor_time = trim(htmlspecialchars($_POST['sponsor_time']));
	$platform_allow = ['微信','QQ钱包','支付宝'];
	if(empty($name) || empty($amount) || empty($sponsor_time))exit('{"code":-1,"msg":"昵称、赞助金额、赞助时间均不能为空"}');
	if(!in_array($platform, $platform_allow, true))exit('{"code":-1,"msg":"赞助平台只能是微信、QQ钱包或支付宝"}');
	if($id > 0){
		$sql = "UPDATE `pre_sponsor` SET `name`=:name,`platform`=:platform,`amount`=:amount,`sponsor_time`=:sponsor_time WHERE `id`=:id";
		$data = [':name'=>$name, ':platform'=>$platform, ':amount'=>$amount, ':sponsor_time'=>$sponsor_time, ':id'=>$id];
	}else{
		$sql = "INSERT INTO `pre_sponsor` (`name`,`platform`,`amount`,`sponsor_time`,`addtime`) VALUES (:name,:platform,:amount,:sponsor_time,NOW())";
		$data = [':name'=>$name, ':platform'=>$platform, ':amount'=>$amount, ':sponsor_time'=>$sponsor_time];
	}
	if($DB->exec($sql, $data)!==false)exit('{"code":0,"msg":"保存成功！"}');
	else exit('{"code":-1,"msg":"保存失败['.$DB->error().']"}');
break;
case 'delSponsor':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_sponsor where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"该赞助记录不存在！"}');
	$sql = "DELETE FROM pre_sponsor WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除成功！"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
case 'violationList':
	$sql=" 1=1";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND (`name` LIKE '%{$kw}%' OR `ip` LIKE '%{$kw}%' OR `hash`='{$kw}')";
	}
	if(isset($_POST['is_show']) && $_POST['is_show']>-1) {
		$is_show = intval($_POST['is_show']);
		$sql.=" AND `is_show`={$is_show}";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_violation WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_violation WHERE{$sql} order by id desc limit $offset,$limit");
	if(!$list)$list = [];
	foreach($list as &$row){
		$row['size_text'] = size_format($row['size']);
		$row['mask_name'] = violation_mask_name($row['name']);
	}
	unset($row);
	exit(json_encode(['total'=>$total, 'rows'=>$list], JSON_UNESCAPED_UNICODE));
break;
case 'saveViolationInfo':
	$id = intval($_POST['id']);
	$name = trim(htmlspecialchars($_POST['name']));
	$type = trim(htmlspecialchars($_POST['type']));
	$remark = trim(htmlspecialchars($_POST['remark']));
	$is_show = intval($_POST['is_show']) == 1 ? 1 : 0;
	if(empty($name))exit('{"code":-1,"msg":"文件名称不能为空"}');
	if($id > 0){
		$sql = "UPDATE `pre_violation` SET `name`=:name,`type`=:type,`remark`=:remark,`is_show`=:is_show WHERE `id`=:id";
		$data = [':name'=>$name, ':type'=>$type, ':remark'=>$remark, ':is_show'=>$is_show, ':id'=>$id];
	}else{
		//手工补录的公示记录没有对应的文件，file_id 留 0，不参与按文件去重
		$sql = "INSERT INTO `pre_violation` (`file_id`,`name`,`type`,`source`,`remark`,`is_show`,`addtime`) VALUES (0,:name,:type,'manual',:remark,:is_show,NOW())";
		$data = [':name'=>$name, ':type'=>$type, ':remark'=>$remark, ':is_show'=>$is_show];
	}
	if($DB->exec($sql, $data)!==false)exit('{"code":0,"msg":"保存成功！"}');
	else exit('{"code":-1,"msg":"保存失败['.$DB->error().']"}');
break;
case 'importBlockedFiles':
	//把启用公示功能之前就已经封禁的老文件补录进来，已有记录的会被 LEFT JOIN 排除，可以重复执行
	$list = $DB->getAll("SELECT f.* FROM pre_file f LEFT JOIN pre_violation v ON v.`file_id`=f.`id` WHERE f.`block`=1 AND v.`id` IS NULL");
	if(!$list)exit('{"code":0,"msg":"没有需要补录的封禁文件"}');
	$i=0;
	foreach($list as $row){
		if(add_violation_log($row))$i++;
	}
	exit(json_encode(['code'=>0, 'msg'=>'成功补录'.$i.'条封禁记录'], JSON_UNESCAPED_UNICODE));
break;
case 'setViolationShow':
	$id=intval($_POST['id']);
	$is_show=intval($_POST['is_show']) == 1 ? 1 : 0;
	$sql = "UPDATE `pre_violation` SET `is_show`=:is_show WHERE `id`=:id";
	if($DB->exec($sql, [':is_show'=>$is_show, ':id'=>$id])!==false)exit('{"code":0,"msg":"修改成功！"}');
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'delViolation':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_violation where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"该公示记录不存在！"}');
	$sql = "DELETE FROM pre_violation WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除成功！"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
case 'replaceList':
	$sql=" 1=1";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND (`new_name` LIKE '%{$kw}%' OR `old_name` LIKE '%{$kw}%' OR `ip` LIKE '%{$kw}%' OR `token`='{$kw}')";
	}
	if(isset($_POST['checked']) && $_POST['checked']>-1) {
		$checked = intval($_POST['checked']);
		$sql.=" AND `checked`={$checked}";
	}
	if(isset($_POST['source']) && !empty($_POST['source']) && $_POST['source']!='-1') {
		$source = trim(daddslashes($_POST['source']));
		$sql.=" AND `source`='{$source}'";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_replace_log WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_replace_log WHERE{$sql} order by id desc limit $offset,$limit");
	if(!$list)$list = [];
	foreach($list as &$row){
		$row['old_size_text'] = size_format($row['old_size']);
		$row['new_size_text'] = size_format($row['new_size']);
		//文件可能已经被删掉了，这时只保留日志，不给查看和封禁入口
		$file = $DB->getRow("SELECT `id`,`block`,`token`,`type`,`pwd` FROM pre_file WHERE `id`=:id LIMIT 1", [':id'=>$row['file_id']]);
		if($file){
			$row['file_exists'] = 1;
			$row['block'] = intval($file['block']);
			$row['pageurl'] = '../file.php?hash='.$file['token'].(!empty($file['pwd'])?'&pwd='.$file['pwd']:'');
			$row['viewurl'] = '../view.php/'.$file['token'].'.'.($file['type']?$file['type']:'file');
			$row['is_image'] = is_view($file['type']) ? 1 : 0;
		}else{
			$row['file_exists'] = 0;
			$row['block'] = -1;
			$row['pageurl'] = '';
			$row['viewurl'] = '';
			$row['is_image'] = 0;
		}
	}
	unset($row);
	exit(json_encode(['total'=>$total, 'rows'=>$list], JSON_UNESCAPED_UNICODE));
break;
case 'setReplaceChecked':
	$id=intval($_POST['id']);
	$checked=intval($_POST['checked']) == 1 ? 1 : 0;
	if($DB->exec("UPDATE `pre_replace_log` SET `checked`=:checked WHERE `id`=:id", [':checked'=>$checked, ':id'=>$id])!==false)exit('{"code":0,"msg":"修改成功！"}');
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'checkAllReplace':
	if($DB->exec("UPDATE `pre_replace_log` SET `checked`=1 WHERE `checked`=0")!==false)exit('{"code":0,"msg":"已全部标记为已复查"}');
	else exit('{"code":-1,"msg":"操作失败['.$DB->error().']"}');
break;
case 'delReplaceLog':
	$id=intval($_POST['id']);
	if($DB->exec("DELETE FROM pre_replace_log WHERE id=:id", [':id'=>$id]))exit('{"code":0,"msg":"删除成功！"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
