<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'fileList':
	$sql=" 1=1";
	if(isset($_POST['uid']) && !empty($_POST['uid'])) {
		$uid = intval($_POST['uid']);
		$sql.=" AND `uid`='$uid'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `block`={$dstatus}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$sql.=" AND `name` LIKE '%{$kw}%'";
		}elseif($type == 2){
			$sql.=" AND `hash`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `type`='{$kw}'";
		}elseif($type == 4){
			$sql.=" AND `ip`='{$kw}'";
		}
	}
	if($_POST['orderby'] == 1){
		$orderby = 'count desc';
	}else{
		$orderby = 'id desc';
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_file WHERE{$sql} order by {$orderby} limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		$row['icon'] = type_to_icon($row['type']);
		$row['view'] = is_view($row['type']);
		$row['view_type'] = get_view_type($row['type']);
		$row['size'] = size_format($row['size']);

		$pwd_ext2='';
		if(!empty($row['pwd'])){
			$pwd_ext2='&pwd='.$row['pwd'];
		}
		$row['fileurl'] = './down.php/'.$row['token'].'.'.($row['type']?$row['type']:'file');
		$row['viewurl'] = './view.php/'.$row['token'].'.'.($row['type']?$row['type']:'file');
		$row['thumburl'] = $row['viewurl'].'?thumb=1';
		$row['pageurl'] = '../file.php?hash='.$row['token'].$pwd_ext2;

		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'setBlock':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	/*
	 * 文件只有这三种状态，别的一律不认：
	 *   0 正常　1 封禁（前台不可下载，进违规公示）　2 待审核（前台同样不可下载，但不公示）
	 * 原来是 intval 之后直接拼进 SQL，传个 5 进来 block 就真成了 5，
	 * 前台那些 block>=1 / block==2 的判断会跟着乱套。
	 */
	$status_name = [0=>'正常', 1=>'封禁', 2=>'待审核'];
	if(!isset($status_name[$status]))exit('{"code":-1,"msg":"状态值不合法"}');
	$row=$DB->getRow("select * from pre_file where id=:id limit 1", [':id'=>$id]);
	if(!$row)exit('{"code":-1,"msg":"当前文件不存在！"}');
	if($DB->exec("UPDATE pre_file SET `block`=:block WHERE id=:id", [':block'=>$status, ':id'=>$id])!==false){
		//只有「封禁」是确认违规，才留公示；改回正常或退回待审核都不是确认状态，
		//把公示撤下来（is_show=0，记录本身保留，方便回头复查）
		if($status == 1)add_violation_log($row);
		else revoke_violation_log($id);
		exit(json_encode(['code'=>0, 'msg'=>'已改为'.$status_name[$status]]));
	}
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'delFile':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前文件不存在！"}');
	//只有已封禁的文件才留公示，正常文件的日常清理不该被公示出去
	if($row['block'] == 1)add_violation_log($row);
	delete_file_blob_if_orphaned($row['hash'], $row['id']);
	$sql = "DELETE FROM pre_file WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;
case 'operation':
	$status=intval($_POST['status']);
	$checkbox=isset($_POST['checkbox'])?$_POST['checkbox']:null;
	if(!$checkbox || !is_array($checkbox))exit('{"code":-1,"msg":"未选中文件"}');
	$i=0;
	if($status == 2)$opname = '解封';
	elseif($status == 1)$opname = '封禁';
	else $opname = '删除';
	foreach($checkbox as $id){
		//选中的id直接来自表单，必须转成整数再进SQL
		$id = intval($id);
		if($id <= 0)continue;
		if($status == 0){
			$row=$DB->getRow("select * from pre_file where id=:id limit 1", [':id'=>$id]);
			if($row){
				//只有已封禁的文件才留公示，正常文件的日常清理不该被公示出去
				if($row['block'] == 1)add_violation_log($row);
				delete_file_blob_if_orphaned($row['hash'], $id);
			}
			$DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$id]);
		}elseif($status == 1){
			$row=$DB->getRow("select * from pre_file where id=:id limit 1", [':id'=>$id]);
			$DB->exec("UPDATE pre_file SET `block`=1 WHERE id=:id", [':id'=>$id]);
			if($row)add_violation_log($row);
		}elseif($status == 2){
			$DB->exec("UPDATE pre_file SET `block`=0 WHERE id=:id", [':id'=>$id]);
			revoke_violation_log($id);
		}
		$i++;
	}
	exit('{"code":0,"msg":"成功'.$opname.$i.'个文件"}');
break;
case 'getFileInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前文件不存在！"}');
	$row['code'] = 0;
	$row['size2'] = size_format($row['size']);
	exit(json_encode($row));
break;
case 'saveFileInfo':
	$id = intval($_POST['id']);
	//和前台上传一致：显式带 ENT_QUOTES，PHP 8.1 以下默认不转义单引号
	$name = trim(htmlspecialchars($_POST['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	$type = trim(htmlspecialchars($_POST['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	$hide = intval($_POST['hide']);
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')):null;
	if(empty($name))exit('{"code":-1,"msg":"文件名称不能为空"}');
	if($ispwd==1 && !empty($pwd)){
        if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"下载密码只能为字母和数字"}');
        }
	}
	$data = [':id'=>$id, ':name'=>$name, ':type'=>$type, ':hide'=>$hide, ':pwd'=>$pwd];
	$sql = "UPDATE `pre_file` SET `name`=:name,`type`=:type,`hide`=:hide,`pwd`=:pwd";
	if(isset($_POST['uid']) && $_POST['uid']!==''){
		if(!preg_match('/^\d+$/', $_POST['uid']))exit('{"code":-1,"msg":"用户ID只能填写非负整数"}');
		$uid = intval($_POST['uid']);
		if($uid > 0 && !$DB->getColumn("SELECT uid FROM pre_user WHERE uid=:uid", [':uid'=>$uid])){
			exit('{"code":-1,"msg":"指定的用户ID不存在"}');
		}
		$sql .= ",`uid`=:uid";
		$data[':uid'] = $uid;
	}
	$sql .= " WHERE `id`=:id";
	if($DB->exec($sql, $data)!==false)exit('{"code":0,"msg":"修改文件信息成功！"}');
	else exit('{"code":-1,"msg":"修改文件信息失败['.$DB->error().']"}');
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
