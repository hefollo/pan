<?php
include("../includes/common.php");
$title='违规公示管理';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">公示信息</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="id" id="id"/>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">文件名</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="name" id="name" placeholder="原始文件名，公示页会自动打码">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">文件类型</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="type" id="type" placeholder="例如：jpg">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">是否公示</label>
						<div class="col-sm-9">
							<select class="form-control" name="is_show" id="is_show">
								<option value="1">公示中</option>
								<option value="0">不公示</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">备注</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="remark" id="remark" placeholder="选填，会显示在公示页">
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
		<div class="alert alert-info">在文件管理里<b>封禁</b>文件会自动生成公示记录，<b>解封</b>会自动撤下公示；图片自动检测命中的记录默认<b>不公示</b>，请人工确认后再放出。公示页只展示脱敏后的文件名和IP。</div>
	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
	        <div class="form-group">
          <label>搜索</label>
		    </div>
			<div class="form-group" id="searchword">
			<input type="text" class="form-control" name="kw" placeholder="按文件名/IP/哈希搜索">
			</div>
			<div class="form-group">
				<select class="form-control" name="is_show">
					<option value="-1">全部状态</option>
					<option value="1">公示中</option>
					<option value="0">不公示</option>
				</select>
			</div>
			<div class="form-group">
				<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
				<a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-repeat"></i> 重置</a>
			</div>
			<div class="form-group pull-right">
				<a href="../violation.php" target="_blank" class="btn btn-default"><i class="fa fa-external-link"></i> 查看公示页</a>
				<button type="button" class="btn btn-warning" onclick="importBlocked()"><i class="fa fa-download"></i> 导入已封禁文件</button>
				<button type="button" class="btn btn-success" onclick="addViolation()"><i class="fa fa-plus"></i> 手动补录</button>
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
<script>
window.violationRows = {};
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 15;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax.php?act=violationList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'id',
				title: 'ID',
				width: 70,
				formatter: function(value, row, index) {
					return '<b>'+value+'</b>';
				}
			},
			{
				field: 'name',
				title: '文件名/公示显示',
				formatter: function(value, row, index) {
					return escapeHtml(value) + '<br/><span class="text-muted">' + escapeHtml(row.mask_name) + '</span>';
				}
			},
			{
				field: 'type',
				title: '类型/大小',
				formatter: function(value, row, index) {
					return (value ? escapeHtml(value) : '未知') + '<br/>' + row.size_text;
				}
			},
			{
				field: 'source',
				title: '来源',
				formatter: function(value, row, index) {
					if(value == 'green')return '自动检测';
					if(value == 'manual')return '手动补录';
					return '后台处理';
				}
			},
			{
				field: 'ip',
				title: '上传IP'
			},
			{
				field: 'addtime',
				title: '记录时间'
			},
			{
				field: 'is_show',
				title: '公示状态',
				formatter: function(value, row, index) {
					if(value == 1){
						return '<a href="javascript:setShow('+row.id+',0)" class="btn btn-xs btn-success">公示中</a>';
					}
					return '<a href="javascript:setShow('+row.id+',1)" class="btn btn-xs btn-default">不公示</a>';
				}
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					window.violationRows[row.id] = row;
					return '<a href="javascript:editViolation('+row.id+')" class="btn btn-xs btn-info">编辑</a>&nbsp;<a href="javascript:delViolation('+row.id+')" class="btn btn-xs btn-danger">删除</a>';
				}
			},
		],
	})
})

function escapeHtml(str){
	return $('<div>').text(str == null ? '' : str).html();
}

function addViolation(){
	$("#modal-store").modal('show');
	$("#form-store")[0].reset();
	$("#form-store #id").val('');
	$("#form-store #is_show").val('1');
}

function editViolation(id){
	var row = window.violationRows[id] || {};
	$("#modal-store").modal('show');
	$("#form-store #id").val(id);
	$("#form-store #name").val(row.name);
	$("#form-store #type").val(row.type);
	$("#form-store #is_show").val(row.is_show);
	$("#form-store #remark").val(row.remark);
}

function save(){
	if($("#name").val()==''){
		layer.alert('文件名不能为空！');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=saveViolationInfo',
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

function importBlocked() {
	var confirmobj = layer.confirm('把文件管理里当前所有【已封禁】但还没有公示记录的文件补录进来，补录后立即公示。可以重复执行，不会产生重复记录。', {
	  btn: ['开始导入','取消'], icon: 3
	}, function(){
	  layer.close(confirmobj);
	  var ii = layer.load(2, {shade:[0.1,'#fff']});
	  $.ajax({
		type : 'POST',
		url : 'ajax.php?act=importBlockedFiles',
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg, {icon:1}, function(){
					searchSubmit();
					layer.closeAll();
				});
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}

function setShow(id, is_show) {
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=setViolationShow',
		data : {id: id, is_show: is_show},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
}

function delViolation(id) {
	var confirmobj = layer.confirm('确定要删除这条公示记录吗？删除后无法恢复，如果只是想撤下公示请点"公示中"切换状态。', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax.php?act=delViolation',
		data : {id: id},
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
