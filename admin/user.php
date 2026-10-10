<?php
include("../includes/common.php");
$title='用户管理';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
.img-circle{margin-right: 7px;}
</style>
<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">用户信息修改</h4>
			</div>
			<div class="modal-body">
			<div class="alert alert-info">平时只需要选会员等级和到期时间，能做什么按等级走。到期时间为空表示永久；到期后自动回到普通用户。「管理员」等级不受任何限制，只能在这里手动给。</div>
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="action" id="action"/>
					<input type="hidden" name="uid" id="uid"/>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">会员等级</label>
						<div class="col-sm-10">
							<select id="level_id" name="level_id" class="form-control">
								<option value="0">普通用户</option>
<?php foreach(level_all() as $lv){ if(intval($lv['type']) === 1 || intval($lv['type']) === 2)continue;?>
								<option value="<?php echo intval($lv['id'])?>"><?php echo htmlspecialchars($lv['name'], ENT_QUOTES, 'UTF-8')?><?php echo level_is_admin($lv) ? '（不受任何限制）' : ''?></option>
<?php }?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">有效天数</label>
						<div class="col-sm-10">
							<input type="number" class="form-control" id="expire_days" name="expire_days" min="0" step="1" placeholder="填写后按当前时间重新计算到期时间">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">到期时间</label>
						<div class="col-sm-10">
							<input type="datetime-local" class="form-control" id="expiretime" name="expiretime">
							<p class="help-block">有效天数优先；不填有效天数时，可手动设置到期时间。清空表示永久有效。</p>
						</div>
					</div>
					<details class="user-adjust" id="adjustBox">
						<summary>单独调整（只给这一个用户改额度，一般不用动）</summary>
						<p class="help-block">留空或填 -1 表示按会员等级走；填了的在到期时间之内优先于等级。用户购买或升级等级时这三项会被清掉。</p>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">上传大小</label>
							<div class="col-sm-10">
								<div class="input-group">
									<input type="number" class="form-control" id="upload_size" name="upload_size" min="-1" step="1" placeholder="-1 按等级，0 不限制">
									<span class="input-group-addon">MB</span>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">每日数量</label>
							<div class="col-sm-10">
								<input type="number" class="form-control" id="upload_limit" name="upload_limit" min="-1" step="1" placeholder="-1 按等级，0 不限制">
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">下载限速</label>
							<div class="col-sm-10">
								<div class="input-group">
									<input type="number" class="form-control" id="down_speed" name="down_speed" min="-1" step="1" placeholder="他上传的文件被下载的速度：-1 按等级，0 不限速；1024 KB/s = 1 MB/s">
									<span class="input-group-addon">KB/s</span>
								</div>
							</div>
						</div>
					</details>
					<details class="user-adjust" id="addonBox">
						<summary>附加包（加量包、在线编辑包，各有自己的到期时间）</summary>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">加量额度</label>
							<div class="col-sm-10">
								<input type="number" class="form-control" id="bonus_limit" name="bonus_limit" min="0" step="1" placeholder="每天多传几个，加在等级的每日数量之上"/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">加量到期</label>
							<div class="col-sm-10">
								<input type="datetime-local" class="form-control" id="bonus_expire" name="bonus_expire">
								<p class="help-block">有加量额度时清空表示永久。</p>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">在线编辑</label>
							<div class="col-sm-10">
								<select id="online_edit" name="online_edit" class="form-control"><option value="0">0_未开通</option><option value="1">1_已开通</option></select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label no-padding-right">编辑到期</label>
							<div class="col-sm-10">
								<input type="datetime-local" class="form-control" id="edit_expire" name="edit_expire">
								<p class="help-block">已开通时清空表示永久。会员等级自带在线编辑的用户不需要这一项。</p>
							</div>
						</div>
					</details>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary" id="store" onclick="save()">保存</button>
			</div>
		</div>
	</div>
</div>
  <div class="container">
    <div class="admin-page-wide">
	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
	        <div class="form-group">
          <label>搜索</label>
		  <select name="type" class="form-control"><option value="1">UID</option><option value="2">第三方账号UID</option><option value="3">昵称</option><option value="4">登录IP</option></select>
		    </div>
			<div class="form-group" id="searchword">
			<input type="text" class="form-control" name="kw" placeholder="搜索内容">
			</div>
			<div class="form-group">
			<select id="dstatus" name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">正常状态</option><option value="1">封禁状态</option></select>
		    </div>
			<div class="form-group">
				<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
				<a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-repeat"></i> 重置</a>
			</div>
		</form>
		<table id="listTable">
	  	</table>
    </div>
  </div>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/bootstrap-table.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<style>
.user-avatar{position:relative;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;margin-right:9px;border-radius:50%;background:#3867f4;color:#fff;font-size:15px;font-weight:700;vertical-align:middle;overflow:hidden;user-select:none}
.user-avatar img{position:absolute;left:0;top:0;width:100%;height:100%;border-radius:50%;object-fit:cover}
.user-nick{vertical-align:middle}
.user-adjust{margin:0 0 12px;padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px}
.user-adjust summary{cursor:pointer;font-weight:700;color:#5b6478;outline:none}
.user-adjust[open] summary{margin-bottom:10px}
</style>
<script>
window.userRows = {};

//昵称等字段是直接拼进 HTML 的，而快捷登录带回来的昵称并没有转义过（login.php 里只 trim），
//不转义等于后台列表存在存储型 XSS，这里统一处理
function escapeHtml(str){
	return String(str == null ? '' : str).replace(/[&<>"']/g, function(c){
		return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
	});
}

/*
 * 头像：邮箱注册的账号没有第三方头像，原来直接输出 <img src=""> 会显示成一个坏图。
 * 现在底下永远画一个带首字的圆形色块，有头像时图片盖在上面；
 * 图片加载失败（QQ/微信头像失效也常见）就把 img 去掉，露出下面的字母头像。
 */
function userAvatar(row){
	var colors = ['#3867f4','#16a369','#f07b2f','#9a6ae8','#ef5a55','#0ea5e9','#d69e2e','#14b8a6'];
	var uid = parseInt(row.uid, 10) || 0;
	var nick = String(row.nickname == null ? '' : row.nickname).replace(/^\s+/, '');
	var letter = nick ? nick.charAt(0).toUpperCase() : '?';
	var html = '<span class="user-avatar" style="background:' + colors[uid % colors.length] + '">' + escapeHtml(letter);
	if(row.faceimg){
		html += '<img src="' + escapeHtml(row.faceimg) + '" alt="" onerror="this.parentNode.removeChild(this)">';
	}
	return html + '</span>';
}
$(document).ready(function(){
	updateToolbar();
	var defaultPageSize = 15;
	var pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	var pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax.php?act=userList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'uid',
				title: 'UID',
				formatter: function(value, row, index) {
					return '<b>'+value+'</b>';
				}
			},
			{
				field: 'openid',
				title: '头像&昵称',
				formatter: function(value, row, index) {
					return userAvatar(row) + '<span class="user-nick">' + escapeHtml(row.nickname) + '</span>';
				}
			},
			{
				field: 'openid',
				title: '登录方式/第三方账号UID',
				formatter: function(value, row, index) {
					return '<b>'+row.type+'</b><br/>'+value;
				}
			},
			{
				field: 'regip',
				title: '注册IP/登录IP',
				formatter: function(value, row, index) {
					return '<a href="https://m.ip138.com/iplookup.asp?ip='+value+'" target="_blank" rel="noreferrer">'+value+'</a><br/><a href="https://m.ip138.com/iplookup.asp?ip='+row.loginip+'" target="_blank" rel="noreferrer">'+row.loginip+'</a>';
				}
			},
			{
				field: 'addtime',
				title: '注册时间/最后登录',
				formatter: function(value, row, index) {
					return value+'<br/>'+row.lasttime;
				}
			},
			{
				field: 'level_id',
				title: '会员等级',
				formatter: function(value, row, index) {
					window.userRows[row.uid] = row;
					//过了期的按普通用户算，把原来的等级和「已过期」标出来
					var expired = isPermissionExpired(row.expiretime);
					var name = escapeHtml(row.level_name || '普通用户');
					var color = row.level_admin == 1 ? '#d9534f' : (parseInt(value, 10) > 0 ? 'orange' : 'blue');
					var html = '<a href="javascript:setLevel('+row.uid+')" style="color:'+color+'" title="修改会员等级">'+name+(parseInt(value, 10) > 0 && expired ? '(已过期)' : '')+'</a>';
					if(parseInt(value, 10) > 0) html += '<br/><small>'+formatExpireTime(row.expiretime)+'</small>';
					return html;
				}
			},
			{
				field: 'upload_limit',
				title: '单独调整 / 附加包',
				formatter: function(value, row, index) {
					window.userRows[row.uid] = row;
					var lines = [];
					//三项都没调的不显示，免得每一行都是一串「按等级」
					if(parseLimitValue(row.upload_size) >= 0) lines.push('大小：'+formatLimitValue(row.upload_size, 'MB'));
					if(parseLimitValue(row.upload_limit) >= 0) lines.push('数量：'+formatLimitValue(row.upload_limit, '个/天'));
					if(parseLimitValue(row.down_speed) >= 0) lines.push('下载：'+formatSpeedValue(row.down_speed));
					if(lines.length && parseInt(row.level_id, 10) <= 0) lines.push('调整到期：'+formatExpireTime(row.expiretime));
					var bonus = parseInt(row.bonus_limit || 0, 10);
					if(bonus > 0) lines.push('加量 +'+bonus+'：'+formatExpireTime(row.bonus_expire));
					if(parseInt(row.online_edit, 10) === 1) lines.push('在线编辑：'+formatExpireTime(row.edit_expire));
					return lines.length ? lines.join('<br/>') : '<span class="text-muted">-</span>';
				}
			},
			{
				field: 'enable',
				title: '状态',
				formatter: function(value, row, index) {
					if(value == '1'){
						return '<a href="javascript:setEnable('+row.uid+',0)" class="btn btn-xs btn-success">正常</a>';
					}else{
						return '<a href="javascript:setEnable('+row.uid+',1)" class="btn btn-xs btn-danger">封禁</a>';
					}
				}
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					window.userRows[row.uid] = row;
					return '<a href="javascript:setLevel('+row.uid+')" class="btn btn-xs btn-primary">等级</a>&nbsp;<a href="./file.php?uid='+row.uid+'" class="btn btn-xs btn-info" target="_blank">文件</a>'+(row.avatar_custom == 1 ? '&nbsp;<a href="javascript:resetAvatar('+row.uid+')" class="btn btn-xs btn-warning">清除头像</a>' : '')+'&nbsp;<a href="javascript:delUser('+row.uid+')" class="btn btn-xs btn-danger">删除</a></td></tr>';
				}
			},
		],
	})
})

function setEnable(uid,enable) {
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=setUserEnable',
		data: {uid:uid, enable:enable},
		dataType : 'json',
		success : function(data) {
			searchSubmit();
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
}

function parseLimitValue(value){
	value = parseInt(value);
	return isNaN(value) ? -1 : value;
}

function formatLimitValue(value, unit){
	value = parseLimitValue(value);
	if(value < 0) return '按等级';
	if(value == 0) return '不限制';
	return value + unit;
}

//下载限速按 KB/s 存，-1 按会员等级；满 1 MB/s 的换成 MB/s 显示，和 PHP 的 speed_text() 一致
function formatSpeedValue(value){
	value = parseLimitValue(value);
	if(value < 0) return '按等级';
	if(value == 0) return '不限速';
	if(value >= 1024){
		var mb = value / 1024;
		return (Math.floor(mb) === mb ? mb : Math.round(mb * 10) / 10) + ' MB/s';
	}
	return value + ' KB/s';
}

function isPermissionExpired(expiretime){
	if(!expiretime) return false;
	return new Date(String(expiretime).replace(/-/g, '/')).getTime() <= new Date().getTime();
}

function formatExpireTime(expiretime){
	if(!expiretime) return '永久';
	return expiretime + (isPermissionExpired(expiretime) ? '（已过期）' : '');
}

function toDatetimeLocal(expiretime){
	if(!expiretime) return '';
	return String(expiretime).replace(' ', 'T').substring(0, 16);
}

function setLevel(uid){
	var row = window.userRows[uid] || {};
	$("#modal-store").modal('show');
	$("#action").val("edit");
	$("#form-store #uid").val(uid);
	var levelId = parseInt(row.level_id, 10) || 0;
	//等级被删掉的用户下拉里找不到对应项，按普通用户显示
	$("#form-store #level_id").val($("#form-store #level_id option[value='"+levelId+"']").length ? levelId : 0);
	$("#form-store #upload_size").val(parseLimitValue(row.upload_size));
	$("#form-store #upload_limit").val(parseLimitValue(row.upload_limit));
	$("#form-store #down_speed").val(parseLimitValue(row.down_speed));
	$("#form-store #expire_days").val('');
	$("#form-store #expiretime").val(toDatetimeLocal(row.expiretime));
	$("#form-store #bonus_limit").val(row.bonus_limit ? row.bonus_limit : 0);
	$("#form-store #bonus_expire").val(toDatetimeLocal(row.bonus_expire));
	$("#form-store #online_edit").val(parseInt(row.online_edit, 10) === 1 ? '1' : '0');
	$("#form-store #edit_expire").val(toDatetimeLocal(row.edit_expire));
	//调过数值、买过附加包的，对应的折叠块直接展开，省得点开才发现有东西
	var adjusted = parseLimitValue(row.upload_size) >= 0 || parseLimitValue(row.upload_limit) >= 0 || parseLimitValue(row.down_speed) >= 0;
	document.getElementById('adjustBox').open = adjusted;
	document.getElementById('addonBox').open = parseInt(row.bonus_limit || 0, 10) > 0 || parseInt(row.online_edit, 10) === 1;
}

function save(){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=saveUserInfo',
		data : $("#form-store").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					$("#modal-store").modal('hide');
					searchSubmit();
					layer.closeAll();
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
}

//清掉用户自己上传的头像（只有传过头像的用户才有这个按钮），清完显示回首字头像
function resetAvatar(uid) {
	var confirmobj = layer.confirm('确定清除这个用户自己上传的头像吗？', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
	  layer.close(confirmobj);
	  $.ajax({
		type : 'POST',
		url : 'ajax.php?act=resetUserAvatar',
		data : {uid: uid},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
				layer.msg('头像已清除', {icon:1});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}

function delUser(uid) {
	var confirmobj = layer.confirm('你确定要删除此用户吗？', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax.php?act=delUser',
		data : {uid: uid},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
				layer.alert('删除成功', {icon:1});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}

</script>
