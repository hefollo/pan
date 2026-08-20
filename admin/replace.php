<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='覆盖记录';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$uncheck = $DB->getColumn("SELECT count(*) FROM pre_replace_log WHERE `checked`=0");
?>
  <div class="container-fluid" style="padding-top:70px;">
    <div class="col-xs-12 center-block" style="float: none;">
		<div class="alert alert-warning">
			用户覆盖上传（用新文件替换已有文件，对外链接保持不变）和在线编辑都会记录在这里。
			覆盖会让同一个链接的内容悄悄变掉，所以要盯着有没有人先传正常文件、事后换成违规内容。
			<b>当前有 <?php echo intval($uncheck)?> 条未复查。</b>
		</div>
	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
	        <div class="form-group">
				<label>搜索</label>
			</div>
			<div class="form-group" id="searchword">
				<input type="text" class="form-control" name="kw" placeholder="按文件名/IP/token搜索">
			</div>
			<div class="form-group">
				<select class="form-control" name="checked">
					<option value="0">未复查</option>
					<option value="-1">全部</option>
					<option value="1">已复查</option>
				</select>
			</div>
			<div class="form-group">
				<select class="form-control" name="source">
					<option value="-1">全部方式</option>
					<option value="replace">覆盖上传</option>
					<option value="edit">在线编辑</option>
				</select>
			</div>
			<div class="form-group">
				<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
				<a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-repeat"></i> 重置</a>
			</div>
			<div class="form-group pull-right">
				<button type="button" class="btn btn-default" onclick="checkAll()"><i class="fa fa-check-square-o"></i> 全部标记已复查</button>
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
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 15;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax.php?act=replaceList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'id',
				title: 'ID',
				width: 60,
				formatter: function(value, row, index) {
					return '<b>'+value+'</b>';
				}
			},
			{
				field: 'source',
				title: '方式',
				width: 90,
				formatter: function(value, row, index) {
					return value == 'edit' ? '<span class="label label-info">在线编辑</span>' : '<span class="label label-primary">覆盖上传</span>';
				}
			},
			{
				field: 'old_name',
				title: '覆盖前',
				formatter: function(value, row, index) {
					return escapeHtml(value) + '<br/><span class="text-muted">' + row.old_size_text + ' · ' + escapeHtml(row.old_type || '未知') + '</span>';
				}
			},
			{
				field: 'new_name',
				title: '覆盖后（当前内容）',
				formatter: function(value, row, index) {
					var html = '<b>' + escapeHtml(value) + '</b><br/><span class="text-muted">' + row.new_size_text + ' · ' + escapeHtml(row.new_type || '未知') + '</span>';
					if(row.file_exists == 1 && row.is_image == 1){
						html += '<br/><a href="javascript:void(0)" class="js-preview" data-src="'+row.viewurl+'">预览新内容</a>';
					}
					return html;
				}
			},
			{
				field: 'ip',
				title: '操作者',
				formatter: function(value, row, index) {
					var who = row.uid > 0 ? 'UID '+row.uid : '游客';
					return who + '<br/><a href="https://m.ip138.com/iplookup.asp?ip='+value+'" target="_blank" rel="noreferrer">'+escapeHtml(value)+'</a>';
				}
			},
			{
				field: 'addtime',
				title: '覆盖时间',
				width: 150
			},
			{
				field: 'block',
				title: '文件状态',
				width: 90,
				formatter: function(value, row, index) {
					if(row.file_exists == 0)return '<span class="text-muted">已删除</span>';
					if(value == 2)return '<span class="label label-warning">待审</span>';
					if(value == 1)return '<span class="label label-danger">已封禁</span>';
					return '<span class="label label-success">正常</span>';
				}
			},
			{
				field: 'checked',
				title: '复查',
				width: 80,
				formatter: function(value, row, index) {
					if(value == 1)return '<a href="javascript:setChecked('+row.id+',0)" class="btn btn-xs btn-success">已复查</a>';
					return '<a href="javascript:setChecked('+row.id+',1)" class="btn btn-xs btn-warning">未复查</a>';
				}
			},
			{
				field: 'status',
				title: '操作',
				width: 220,
				formatter: function(value, row, index) {
					var html = '';
					if(row.file_exists == 1){
						html += '<a href="'+row.pageurl+'" target="_blank" class="btn btn-xs btn-info">查看</a> ';
						if(row.block == 1){
							html += '<a href="javascript:setBlock('+row.file_id+',0)" class="btn btn-xs btn-default">解封</a> ';
						}else{
							html += '<a href="javascript:setBlock('+row.file_id+',1)" class="btn btn-xs btn-danger">封禁</a> ';
						}
						html += '<a href="javascript:delFile('+row.file_id+')" class="btn btn-xs btn-danger">删文件</a> ';
					}
					html += '<a href="javascript:delLog('+row.id+')" class="btn btn-xs btn-default">删记录</a>';
					return html;
				}
			},
		],
	})
})

$(document).on('click', '.js-preview', function(){
	showimage($(this).data('src'));
});

//按图片实际尺寸等比缩放弹层，和文件管理里的看大图保持一致；用 iframe 打开会留一大片空白
function showimage(resourcesUrl){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	var img = new Image();
	img.onload = function () {
		var max_height = $(window).height() - 200;
		var max_width = $(window).width() - 100;
		var rate = Math.min(max_height / img.height, max_width / img.width, 1);
		var imgHeight = img.height * rate;
		var imgWidth = img.width * rate;
		var imgHtml = '<div id="showimg" style="width:'+imgWidth+'px; height:'+imgHeight+'px;"></div>';
		img.style = 'width:100%';
		layer.close(ii);
		layer.open({
			type:1,
			shade: 0.6,
			title: false,
			area: ['auto', 'auto'],
			shadeClose: true,
			content: imgHtml,
			success: function(){
				$("#showimg").append(img)
			}
		});
	}
	img.onerror = function(){ layer.close(ii); layer.msg('图片加载错误'); }
	img.src = resourcesUrl;
}

function escapeHtml(str){
	return $('<div>').text(str == null ? '' : str).html();
}

//封禁/解封复用文件管理那套接口，封禁后会自动生成违规公示记录
function setBlock(id, status){
	$.ajax({
		type : 'GET',
		url : 'ajax_file.php?act=setBlock&id='+id+'&status='+status,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.msg(status == 1 ? '已封禁' : '已解封', {icon:1});
				searchSubmit();
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(){ layer.msg('服务器错误'); }
	});
}

function delFile(id){
	var c = layer.confirm('确定要删除这个文件吗？删除后链接立即失效，不可恢复。', {btn:['确定','取消'], icon:0}, function(){
		layer.close(c);
		$.ajax({
			type : 'GET',
			url : 'ajax_file.php?act=delFile&id='+id,
			dataType : 'json',
			success : function(data) {
				if(data.code == 0){
					layer.msg('已删除', {icon:1});
					searchSubmit();
				}else{
					layer.alert(data.msg, {icon:2});
				}
			},
			error:function(){ layer.msg('服务器错误'); }
		});
	}, function(){ layer.close(c); });
}

function setChecked(id, checked){
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=setReplaceChecked',
		data : {id: id, checked: checked},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0)searchSubmit();
			else layer.alert(data.msg, {icon:2});
		},
		error:function(){ layer.msg('服务器错误'); }
	});
}

function checkAll(){
	var c = layer.confirm('把所有未复查的记录都标记为已复查？', {btn:['确定','取消'], icon:3}, function(){
		layer.close(c);
		$.ajax({
			type : 'POST',
			url : 'ajax.php?act=checkAllReplace',
			dataType : 'json',
			success : function(data) {
				if(data.code == 0){
					layer.alert(data.msg, {icon:1}, function(){ window.location.reload(); });
				}else{
					layer.alert(data.msg, {icon:2});
				}
			},
			error:function(){ layer.msg('服务器错误'); }
		});
	}, function(){ layer.close(c); });
}

function delLog(id){
	var c = layer.confirm('只删除这条覆盖记录，不影响文件本身。确定吗？', {btn:['确定','取消'], icon:0}, function(){
		layer.close(c);
		$.ajax({
			type : 'POST',
			url : 'ajax.php?act=delReplaceLog',
			data : {id: id},
			dataType : 'json',
			success : function(data) {
				if(data.code == 0)searchSubmit();
				else layer.alert(data.msg, {icon:2});
			},
			error:function(){ layer.msg('服务器错误'); }
		});
	}, function(){ layer.close(c); });
}
</script>
