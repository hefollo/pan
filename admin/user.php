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
			<div class="alert alert-info">上传大小限制、每日上传数量留空或填 -1 表示继承全站设置；填 0 表示不限制。到期时间为空表示永久有效；到期后自动按普通用户权限生效。</div>
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="action" id="action"/>
					<input type="hidden" name="uid" id="uid"/>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">用户权限</label>
						<div class="col-sm-10">
							<select id="level" name="level" class="form-control"><option value="0">0_普通</option><option value="1">1_高级</option></select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">上传大小</label>
						<div class="col-sm-10">
							<div class="input-group">
								<input type="number" class="form-control" id="upload_size" name="upload_size" min="-1" step="1" placeholder="-1 继承全站，0 不限制">
								<span class="input-group-addon">MB</span>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">每日数量</label>
						<div class="col-sm-10">
							<input type="number" class="form-control" id="upload_limit" name="upload_limit" min="-1" step="1" placeholder="-1 继承全站，0 不限制">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">加量额度</label>
						<div class="col-sm-10">
							<input type="number" class="form-control" id="bonus_limit" name="bonus_limit" min="0" step="1" placeholder="加量包累计的每日额度，会加在上面的每日数量之上"/>
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
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary" id="store" onclick="save()">保存</button>
			</div>
		</div>
	</div>
</div>
  <div class="container" style="padding-top:70px;">
    <div class="col-xs-12 center-block" style="float: none;">
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
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.min.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/bootstrap-table.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<style>
.user-avatar{position:relative;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;margin-right:9px;border-radius:50%;background:#3867f4;color:#fff;font-size:15px;font-weight:700;vertical-align:middle;overflow:hidden;user-select:none}
.user-avatar img{position:absolute;left:0;top:0;width:100%;height:100%;border-radius:50%;object-fit:cover}
.user-nick{vertical-align:middle}
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
	const defaultPageSize = 15;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

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
				field: 'level',
				title: '权限',
				formatter: function(value, row, index) {
					window.userRows[row.uid] = row;
					var expired = isPermissionExpired(row.expiretime);
					if(value == '1'){
						return '<a href="javascript:setLevel('+row.uid+')" style="color:orange" title="修改用户权限">高级'+(expired?'(已过期)':'')+'</a>';
					}else{
						return '<a href="javascript:setLevel('+row.uid+')" style="color:blue" title="修改用户权限">普通'+(expired?'(已过期)':'')+'</a>';
					}
				}
			},
			{
				field: 'upload_limit',
				title: '上传限制',
				formatter: function(value, row, index) {
					window.userRows[row.uid] = row;
					var bonus = parseInt(row.bonus_limit || 0, 10);
					return '大小：'+formatLimitValue(row.upload_size, 'MB')+'<br/>数量：'+formatLimitValue(row.upload_limit, '个/天')
						+ (bonus > 0 ? '（加量 +'+bonus+'）' : '')+'<br/>到期：'+formatExpireTime(row.expiretime);
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
					return '<a href="javascript:setLevel('+row.uid+')" class="btn btn-xs btn-primary">权限</a>&nbsp;<a href="./file.php?uid='+row.uid+'" class="btn btn-xs btn-info" target="_blank">文件</a>&nbsp;<a href="javascript:delUser('+row.uid+')" class="btn btn-xs btn-danger">删除</a></td></tr>';
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
	if(value < 0) return '继承全站';
	if(value == 0) return '不限制';
	return value + unit;
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
	$("#form-store #level").val(parseLimitValue(row.level));
	$("#form-store #upload_size").val(parseLimitValue(row.upload_size));
	$("#form-store #upload_limit").val(parseLimitValue(row.upload_limit));
	$("#form-store #bonus_limit").val(row.bonus_limit ? row.bonus_limit : 0);
	$("#form-store #expire_days").val('');
	$("#form-store #expiretime").val(toDatetimeLocal(row.expiretime));
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
