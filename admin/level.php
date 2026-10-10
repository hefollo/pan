<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '会员等级设置';

/*
 * 会员等级的增删改走本页自己的 POST（带来源校验），和「购买套餐设置」是同一种做法。
 * 一个等级就是一张权限表，谁是什么等级在「用户管理」里看和改，卖哪个等级在「购买套餐设置」里定。
 * 内置的三个等级不能删：游客、普通用户可以改权限；管理员不受任何限制，没有可改的东西。
 */

//下载速度在库里一律存 KB/s，表单上按 KB/s 或 MB/s 填；-1（跟随站点设置）和 0（不限速）原样显示
function level_speed_input($kbps){
	$kbps = intval($kbps);
	if($kbps >= 1024 && $kbps % 1024 === 0)return [strval($kbps / 1024), 'MB'];
	return [strval($kbps), 'KB'];
}

//表单里的数值项：负数一律当 -1（跟随站点设置），0 不限，其余取整
function level_form_int($key){
	$value = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
	if($value === '' || !is_numeric($value))return -1;
	$value = intval($value);
	return $value < 0 ? -1 : min(2147483647, $value);
}

function level_value_text($value, $unit, $site_text){
	$value = intval($value);
	if($value < 0)return '跟随站点设置（现为 '.$site_text.'）';
	if($value === 0)return '不限';
	return $value.' '.$unit;
}

$msg = '';
$msgtype = 'success';
if($islogin == 1 && isset($_POST['do'])){
	if(!checkRefererHost())exit('来源校验失败');
	$do = $_POST['do'];
	if($do === 'level_save'){
		$id = intval($_POST['id']);
		$old = $id > 0 ? level_get($id) : null;
		$name = mb_substr(trim(isset($_POST['name']) ? $_POST['name'] : ''), 0, 32, 'UTF-8');
		//下载速度：负数 = -1 跟随站点设置，0 不限速，其余按单位换成整数 KB/s，最少 1 KB/s
		$speed_raw = isset($_POST['down_speed']) ? trim((string)$_POST['down_speed']) : '';
		$speed_num = is_numeric($speed_raw) ? floatval($speed_raw) : -1;
		if(!is_finite($speed_num) || $speed_num < 0){
			$down_speed = -1;
		}elseif($speed_num == 0){
			$down_speed = 0;
		}else{
			$unit = (isset($_POST['down_speed_unit']) && strtoupper($_POST['down_speed_unit']) === 'MB') ? 1024 : 1;
			$down_speed = max(1, min(2147483647, intval(round($speed_num * $unit))));
		}
		//写库的值一律用字符串：DB->insert / update 把 == '' 的值写成 NULL，整数 0 在老版本 PHP 上也会中
		$data = [
			'upload_limit' => strval(level_form_int('upload_limit')),
			'upload_size' => strval(level_form_int('upload_size')),
			'down_speed' => strval($down_speed),
			'online_edit' => !empty($_POST['online_edit']) ? '1' : '0',
			'folder' => !empty($_POST['folder']) ? '1' : '0',
			'api' => !empty($_POST['api']) ? '1' : '0',
			'storage_all' => !empty($_POST['storage_all']) ? '1' : '0',
			'no_review' => !empty($_POST['no_review']) ? '1' : '0',
			'remark' => mb_substr(trim(isset($_POST['remark']) ? $_POST['remark'] : ''), 0, 200, 'UTF-8'),
		];
		if($id > 0 && !$old){
			$msg = '要修改的等级不存在'; $msgtype = 'danger';
		}elseif($old && level_is_admin($old)){
			$msg = '管理员等级不受任何限制，没有可以修改的权限'; $msgtype = 'danger';
		}elseif((!$old || level_is_custom($old)) && $name === ''){
			$msg = '等级名称不能为空'; $msgtype = 'danger';
		}else{
			//自己建的等级才能改名称和顺序；顺序限制在普通用户（10）和管理员（100000）之间
			if(!$old || level_is_custom($old)){
				$dup = false;
				foreach(level_all() as $one){
					if($one['name'] === $name && intval($one['id']) !== $id)$dup = true;
				}
				if($dup){
					$msg = '已经有一个叫「'.$name.'」的等级了'; $msgtype = 'danger';
				}else{
					$data['name'] = $name;
					$data['sort'] = strval(max(11, min(99999, intval($_POST['sort']))));
				}
			}
			if($msgtype !== 'danger'){
				if($old){
					$ok = $DB->update('level', $data, ['id'=>$id]) !== false;
					$msg = '等级已更新';
				}else{
					$data['type'] = '0';
					$data['addtime'] = 'NOW()';
					$ok = $DB->insert('level', $data) !== false;
					$msg = '等级已添加，到「购买套餐设置」里添加卖这个等级的套餐';
				}
				if(!$ok){
					$msg = '等级保存失败：'.$DB->error(); $msgtype = 'danger';
				}
			}
		}
	}elseif($do === 'level_delete'){
		$id = intval($_POST['id']);
		$old = level_get($id);
		if(!$old || !level_is_custom($old)){
			$msg = '内置等级不能删除'; $msgtype = 'danger';
		}else{
			$users = intval($DB->getColumn("SELECT count(*) FROM pre_user WHERE level_id=:id", [':id'=>$id]));
			$plans = intval($DB->getColumn("SELECT count(*) FROM pre_plan WHERE level_id=:id", [':id'=>$id]));
			if($users > 0 || $plans > 0){
				$msg = '还有 '.$users.' 个用户是这个等级、'.$plans.' 个套餐在卖它，先把用户改成别的等级、把套餐删掉再删等级';
				$msgtype = 'danger';
			}else{
				$DB->exec("DELETE FROM pre_level WHERE id=:id AND type=0", [':id'=>$id]);
				$msg = '等级已删除';
			}
		}
	}elseif($do === 'level_seed'){
		//同名的等级和套餐都跳过，不会覆盖已经改过的
		list($added_levels, $added_plans) = seed_default_levels_and_plans();
		$msg = ($added_levels + $added_plans) > 0
			? ('已导入 '.$added_levels.' 个推荐等级、'.$added_plans.' 个推荐套餐，名称、权限和价格都可以直接改')
			: '推荐的等级和套餐都已经存在了，没有重复导入';
	}
	level_all(true);
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$levels = level_all(true);
$edit = isset($_GET['edit']) ? level_get(intval($_GET['edit'])) : null;
if($edit && level_is_admin($edit))$edit = null;
//每个等级有多少用户（不看是否过期）、有多少在售套餐
$user_counts = [];
$rows = $DB->getAll("SELECT level_id, count(*) AS num FROM pre_user GROUP BY level_id");
if(is_array($rows))foreach($rows as $row)$user_counts[intval($row['level_id'])] = intval($row['num']);
$plan_counts = [];
$rows = $DB->getAll("SELECT level_id, count(*) AS num FROM pre_plan WHERE enable=1 AND level_id>0 GROUP BY level_id");
if(is_array($rows))foreach($rows as $row)$plan_counts[intval($row['level_id'])] = intval($row['num']);
$custom_count = 0;
foreach($levels as $one){ if(level_is_custom($one))$custom_count++; }

$site_limit = (isset($conf['upload_limit']) && intval($conf['upload_limit']) > 0) ? intval($conf['upload_limit']).' 个/天' : '不限';
$site_size = (isset($conf['upload_size']) && intval($conf['upload_size']) > 0) ? size_mb_text($conf['upload_size']) : '不限';
$type_names = [1=>'游客', 2=>'普通用户', 3=>'管理员'];
$yes = '<i class="fa fa-check text-success"></i>';
$no = '<span class="text-muted">-</span>';
list($speed_val, $speed_unit) = level_speed_input($edit ? $edit['down_speed'] : -1);
$edit_builtin = $edit && !level_is_custom($edit);
?>
<div class="container">
<div class="admin-page-wide">
<?php if($msg){?>
<div class="alert alert-<?php echo $msgtype?>"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div>
<?php }?>
<?php if($custom_count === 0){?>
<div class="alert alert-warning">
  还没有可以卖的会员等级。可以点右下方的「一键导入推荐等级和套餐」直接生成六个等级和配套的套餐（之后随便改），也可以在下面自己添加。
</div>
<?php }?>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">会员等级
  <form method="post" class="pull-right" style="margin-top:-4px" onsubmit="return confirm('会添加一组推荐的会员等级和套餐（同名的自动跳过），导入后可以随意修改，确定吗？')">
    <input type="hidden" name="do" value="level_seed"/>
    <button type="submit" class="btn btn-xs btn-default">一键导入推荐等级和套餐</button>
  </form>
</h3></div>
<div class="table-responsive">
<table class="table table-striped table-hover">
  <thead><tr><th>等级</th><th>每日上传</th><th>单文件大小</th><th>文件下载速度</th><th>在线编辑</th><th>文件夹</th><th>上传API</th><th>全部存储</th><th>免审核</th><th>用户数</th><th>在售套餐</th><th>操作</th></tr></thead>
  <tbody>
<?php foreach($levels as $lv){
	$lid = intval($lv['id']);
	$type = intval($lv['type']);
	$admin = $type === 3;
	$tier_speed = speed_text(download_tier_speed_kbps($type === 1 ? 0 : 1));
	if($type === 1){
		$users_text = '-';
	}elseif($type === 2){
		$users_text = (isset($user_counts[0]) ? $user_counts[0] : 0) + (isset($user_counts[$lid]) ? $user_counts[$lid] : 0);
	}else{
		$users_text = isset($user_counts[$lid]) ? $user_counts[$lid] : 0;
	}
?>
    <tr class="level-row" data-id="<?php echo $lid?>">
      <td><b><?php echo htmlspecialchars($lv['name'], ENT_QUOTES, 'UTF-8')?></b><?php if($type > 0){?> <span class="label label-default">内置</span><?php }?>
        <?php if(!empty($lv['remark'])){?><br/><small class="text-muted"><?php echo htmlspecialchars($lv['remark'], ENT_QUOTES, 'UTF-8')?></small><?php }?></td>
<?php if($admin){?>
      <td colspan="8"><span class="text-muted">不受任何限制：数量、大小、速度都不限，全部功能可用，上传免审核、不做内容检测、不限上传频率</span></td>
<?php }else{?>
      <td><?php echo htmlspecialchars(level_value_text($lv['upload_limit'], '个/天', $site_limit))?></td>
      <td><?php echo intval($lv['upload_size']) > 0 ? htmlspecialchars(size_mb_text($lv['upload_size'])) : htmlspecialchars(level_value_text($lv['upload_size'], 'MB', $site_size))?></td>
      <td><?php echo intval($lv['down_speed']) < 0 ? '跟随站点设置（现为 '.htmlspecialchars($tier_speed).'）' : htmlspecialchars(speed_text($lv['down_speed']))?></td>
      <td><?php echo intval($lv['online_edit']) === 1 ? $yes : $no?></td>
      <td><?php echo intval($lv['folder']) === 1 ? $yes : $no?></td>
      <td><?php echo intval($lv['api']) === 1 ? $yes : $no?></td>
      <td><?php echo $type === 1 ? $no : (intval($lv['storage_all']) === 1 ? $yes : $no)?></td>
      <td><?php echo intval($lv['no_review']) === 1 ? $yes : $no?></td>
<?php }?>
      <td><?php echo $users_text?></td>
      <td><?php echo $type > 0 ? '<span class="text-muted">不出售</span>' : (isset($plan_counts[$lid]) ? $plan_counts[$lid].' 个' : '<span class="text-muted">没有</span>')?></td>
      <td>
<?php if(!$admin){?>
        <a class="btn btn-xs btn-primary level-edit" href="./level.php?edit=<?php echo $lid?>"
           data-id="<?php echo $lid?>" data-type="<?php echo $type?>"
           data-name="<?php echo htmlspecialchars($lv['name'], ENT_QUOTES, 'UTF-8')?>"
           data-sort="<?php echo intval($lv['sort'])?>"
           data-upload-limit="<?php echo intval($lv['upload_limit'])?>"
           data-upload-size="<?php echo intval($lv['upload_size'])?>"
           data-down-speed="<?php echo intval($lv['down_speed'])?>"
           data-online-edit="<?php echo intval($lv['online_edit'])?>"
           data-folder="<?php echo intval($lv['folder'])?>"
           data-api="<?php echo intval($lv['api'])?>"
           data-storage-all="<?php echo intval($lv['storage_all'])?>"
           data-no-review="<?php echo intval($lv['no_review'])?>"
           data-remark="<?php echo htmlspecialchars((string)$lv['remark'], ENT_QUOTES, 'UTF-8')?>">编辑</a>
<?php } if($type === 0){?>
        <form method="post" style="display:inline" onsubmit="return confirm('确定删除这个等级吗？')">
          <input type="hidden" name="do" value="level_delete"/><input type="hidden" name="id" value="<?php echo $lid?>"/>
          <button type="submit" class="btn btn-xs btn-danger">删除</button>
        </form>
<?php }?>
      </td>
    </tr>
<?php }?>
  </tbody>
</table>
</div>
<div class="panel-footer">
  等级从上到下由低到高。用户买了更高的等级可以补差价升级；会员到期后回到「普通用户」。
  「管理员」等级只能在 <a href="./user.php">用户管理</a> 里手动给某个账号，不能出售，它只是前台账号的权限，不等于能登录后台。
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title" id="levelFormTitle"><?php echo $edit ? '编辑等级' : '添加等级'?></h3></div>
<div class="panel-body">
  <form method="post" role="form" class="level-form" id="levelForm">
	<input type="hidden" name="do" value="level_save"/>
	<input type="hidden" name="id" id="levelId" value="<?php echo $edit ? intval($edit['id']) : 0?>"/>
	<div class="row">
	  <div class="col-sm-4 form-group">
		<label>等级名称</label>
		<input type="text" name="name" value="<?php echo $edit ? htmlspecialchars($edit['name'], ENT_QUOTES, 'UTF-8') : ''?>" class="form-control" placeholder="例如：标准会员" maxlength="32"<?php echo $edit_builtin ? ' readonly' : ''?>/>
		<span class="hint">显示在购买页和个人中心；内置等级不能改名</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>高低顺序</label>
		<input type="number" name="sort" value="<?php echo $edit ? intval($edit['sort']) : 100?>" class="form-control" min="11" max="99999" step="1"<?php echo $edit_builtin ? ' readonly' : ''?>/>
		<span class="hint">数字大的等级更高，决定谁能升级到谁；建议隔开填（100、200、300）方便以后往中间加</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>等级说明</label>
		<input type="text" name="remark" value="<?php echo $edit ? htmlspecialchars((string)$edit['remark'], ENT_QUOTES, 'UTF-8') : ''?>" class="form-control" placeholder="选填" maxlength="200"/>
		<span class="hint">只在这个页面显示，给自己看的备注</span>
	  </div>
	</div>
	<div class="row">
	  <div class="col-sm-4 form-group">
		<label>每日上传数量</label>
		<input type="number" name="upload_limit" value="<?php echo $edit ? intval($edit['upload_limit']) : -1?>" class="form-control" min="-1" step="1"/>
		<span class="hint">-1 跟随站点设置（现为 <?php echo htmlspecialchars($site_limit)?>）/ 0 不限 / N 每天 N 个</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>单文件大小（MB）</label>
		<input type="number" name="upload_size" value="<?php echo $edit ? intval($edit['upload_size']) : -1?>" class="form-control" min="-1" step="1"/>
		<span class="hint">-1 跟随站点设置（现为 <?php echo htmlspecialchars($site_size)?>）/ 0 不限 / N 最大 N MB，1 GB 填 1024</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>下载速度</label>
		<div class="row level-inline">
		  <div class="col-xs-7">
			<input type="number" name="down_speed" value="<?php echo htmlspecialchars($speed_val, ENT_QUOTES, 'UTF-8')?>" class="form-control" min="-1" step="0.1"/>
		  </div>
		  <div class="col-xs-5">
			<select class="form-control" name="down_speed_unit">
			  <option value="KB"<?php echo $speed_unit === 'KB' ? ' selected' : ''?>>KB/s</option>
			  <option value="MB"<?php echo $speed_unit === 'MB' ? ' selected' : ''?>>MB/s</option>
			</select>
		  </div>
		</div>
		<span class="hint">-1 跟随站点设置（「存储类型设置」里的速度）/ 0 不限速 / N 每秒 N。限速跟着上传的人走：这个等级的用户上传的文件，任何人下载都是这个速度</span>
	  </div>
	</div>
	<div class="form-group level-flags">
	  <label>可用功能</label>
	  <div>
		<label class="checkbox-inline"><input type="checkbox" name="online_edit" value="1"<?php echo ($edit && intval($edit['online_edit']) === 1) ? ' checked' : ''?>/> 在线编辑</label>
		<label class="checkbox-inline"><input type="checkbox" name="folder" value="1"<?php echo ($edit && intval($edit['folder']) === 1) ? ' checked' : ''?>/> 用户文件夹</label>
		<label class="checkbox-inline"><input type="checkbox" name="api" value="1"<?php echo ($edit && intval($edit['api']) === 1) ? ' checked' : ''?>/> 上传 API</label>
		<label class="checkbox-inline"><input type="checkbox" name="storage_all" value="1"<?php echo ($edit && intval($edit['storage_all']) === 1) ? ' checked' : ''?>/> 可用全部存储</label>
		<label class="checkbox-inline"><input type="checkbox" name="no_review" value="1"<?php echo ($edit && intval($edit['no_review']) === 1) ? ' checked' : ''?>/> 上传免审核</label>
	  </div>
	  <span class="hint">
		用户文件夹、上传 API 还要各自的总开关开着才用得上（「文件上传设置」「上传API设置」）；给「游客」勾上传 API 等于允许不带密钥匿名上传。<br/>
		可用全部存储：能用「存储类型设置」的多存储里标了「仅限会员」的存储。<br/>
		上传免审核：不做视频人工审核、不受禁止上传的类型和文件名限制，<b>内容检测照常做</b>；只建议给信得过的等级。
	  </span>
	</div>
	<div class="level-actions">
	  <button type="submit" class="btn btn-primary" id="levelSubmit"><?php echo $edit ? '保存修改' : '添加等级'?></button>
	  <a class="btn btn-default" href="./level.php" id="levelCancel"<?php echo $edit ? '' : ' style="display:none"'?>>取消编辑</a>
	  <span class="level-editing" id="levelEditing"<?php echo $edit ? '' : ' style="display:none"'?>>正在编辑「<b id="levelEditingName"><?php echo $edit ? htmlspecialchars($edit['name'], ENT_QUOTES, 'UTF-8') : ''?></b>」</span>
	</div>
  </form>
</div>
</div>
</div>
</div>

<style>
.level-form .form-group{margin-bottom:10px}
.level-form label{display:block;margin-bottom:4px;font-weight:700}
.level-form .checkbox-inline{display:inline-block;font-weight:400;margin-right:6px}
.level-form .hint{display:block;margin-top:4px;min-height:32px;color:#8a94a6;font-size:12px;line-height:16px}
.level-form .level-inline{margin-left:-5px;margin-right:-5px}
.level-form .level-inline>div{padding-left:5px;padding-right:5px}
.level-form .level-actions{padding-top:4px;border-top:1px solid #eee;margin-top:6px}
.level-form .level-actions .btn{margin-top:10px}
.level-form .level-editing{margin-left:10px;color:#8a94a6;font-size:13px}
.level-row.level-row-active>td{background:#eef3ff !important}
</style>
<script>
/*
 * 点「编辑」把这一行的数据填进下面的表单（数据随列表一起输出，不用再请求）。
 * 保存、删除、导入都是普通的表单提交，整页刷新后回到本页。
 */
(function(){
	var form = document.getElementById('levelForm');
	if(!form || typeof jQuery === 'undefined')return;
	var $ = jQuery;
	function field(name){ return form.querySelector('[name="'+name+'"]'); }
	function fill(d){
		document.getElementById('levelId').value = d.id;
		field('name').value = d.name;
		field('sort').value = d.sort;
		field('remark').value = d.remark;
		field('upload_limit').value = d.uploadLimit;
		field('upload_size').value = d.uploadSize;
		//库里存的是 KB/s，整 MB 的换成 MB/s 显示，和服务端 level_speed_input() 同一个规则
		var speed = parseInt(d.downSpeed, 10);
		if(isNaN(speed))speed = -1;
		if(speed >= 1024 && speed % 1024 === 0){
			field('down_speed').value = speed / 1024;
			field('down_speed_unit').value = 'MB';
		}else{
			field('down_speed').value = speed;
			field('down_speed_unit').value = 'KB';
		}
		field('online_edit').checked = String(d.onlineEdit) === '1';
		field('folder').checked = String(d.folder) === '1';
		field('api').checked = String(d.api) === '1';
		field('storage_all').checked = String(d.storageAll) === '1';
		field('no_review').checked = String(d.noReview) === '1';
		//内置等级只能改权限，名称和顺序锁住
		var builtin = parseInt(d.type, 10) > 0;
		field('name').readOnly = builtin;
		field('sort').readOnly = builtin;
	}
	function state(editing, name){
		document.getElementById('levelFormTitle').innerHTML = editing ? '编辑等级' : '添加等级';
		document.getElementById('levelSubmit').innerHTML = editing ? '保存修改' : '添加等级';
		document.getElementById('levelCancel').style.display = editing ? '' : 'none';
		document.getElementById('levelEditing').style.display = editing ? '' : 'none';
		$('#levelEditingName').text(name || '');
	}
	$(document).on('click.adminDynamicPage', '.level-edit', function(e){
		e.preventDefault();
		var a = this;
		fill({
			id: a.getAttribute('data-id'), type: a.getAttribute('data-type'), name: a.getAttribute('data-name'),
			sort: a.getAttribute('data-sort'), remark: a.getAttribute('data-remark'),
			uploadLimit: a.getAttribute('data-upload-limit'), uploadSize: a.getAttribute('data-upload-size'),
			downSpeed: a.getAttribute('data-down-speed'), onlineEdit: a.getAttribute('data-online-edit'),
			folder: a.getAttribute('data-folder'), api: a.getAttribute('data-api'),
			storageAll: a.getAttribute('data-storage-all'), noReview: a.getAttribute('data-no-review')
		});
		state(true, a.getAttribute('data-name'));
		$('.level-row').removeClass('level-row-active');
		$(a).closest('.level-row').addClass('level-row-active');
		try{ form.scrollIntoView({behavior:'smooth', block:'center'}); }catch(err){ form.scrollIntoView(); }
	});
	var cancel = document.getElementById('levelCancel');
	if(cancel)cancel.onclick = function(e){
		e.preventDefault();
		fill({id:0, type:0, name:'', sort:100, remark:'', uploadLimit:-1, uploadSize:-1, downSpeed:-1, onlineEdit:0, folder:0, api:0, storageAll:0, noReview:0});
		state(false, '');
		$('.level-row').removeClass('level-row-active');
		return false;
	};
	var m = location.search.match(/[?&]edit=(\d+)/);
	if(m)$('.level-row[data-id="'+m[1]+'"]').addClass('level-row-active');
})();
</script>
</body>
</html>
