<?php
include("../includes/common.php");
$title='赞助管理';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
//收款码地址，从未配置过就显示 includes/sponsor/images/ 里自带的图
$sponsor_imgs = [
	'sponsor_img_weixin' => ['label'=>'微信收款码', 'default'=>'images/weixin.png'],
	'sponsor_img_qq'     => ['label'=>'QQ钱包收款码', 'default'=>'images/qq.png'],
	'sponsor_img_alipay' => ['label'=>'支付宝收款码', 'default'=>'images/zhifubao.png'],
];
foreach($sponsor_imgs as $k=>&$v){
	$v['value'] = isset($conf[$k]) ? $conf[$k] : $v['default'];
}
unset($v);
?>
<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">赞助信息</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="id" id="id"/>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">昵称</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="name" id="name" placeholder="例如：*陈">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">赞助平台</label>
						<div class="col-sm-10">
							<select class="form-control" name="platform" id="platform">
								<option value="微信">微信</option>
								<option value="QQ钱包">QQ钱包</option>
								<option value="支付宝">支付宝</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">赞助金额</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="amount" id="amount" placeholder="例如：10元">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">赞助时间</label>
						<div class="col-sm-10">
							<input type="date" class="form-control" name="sponsor_time" id="sponsor_time">
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
  <div class="container">
    <div class="admin-page-wide">
		<div class="panel panel-default">
			<div class="panel-heading"><h3 class="panel-title">赞助页收款码设置</h3></div>
			<div class="panel-body">
				<form onsubmit="return saveSponsorSetting(this)" method="post" class="form-horizontal" role="form">
<?php foreach($sponsor_imgs as $k=>$v){?>
					<div class="form-group">
						<label class="col-sm-2 control-label"><?php echo $v['label']?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="<?php echo $k?>" value="<?php echo htmlspecialchars($v['value'], ENT_QUOTES, 'UTF-8')?>" placeholder="图片地址，留空则该收款方式不显示">
						</div>
					</div>
<?php }?>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<span class="help-block" style="margin-top:0">可填完整网址（如 https://你的域名/xxx.png），也可填相对 includes/sponsor/ 目录的路径（如 images/weixin.png）。留空则赞助页上不显示这一种收款方式。</span>
							<button type="submit" class="btn btn-primary">保存设置</button>
							<a href="../includes/sponsor/" target="_blank" class="btn btn-default">查看赞助页</a>
						</div>
					</div>
				</form>
			</div>
		</div>
	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
	        <div class="form-group">
          <label>搜索</label>
		    </div>
			<div class="form-group" id="searchword">
			<input type="text" class="form-control" name="kw" placeholder="按昵称搜索">
			</div>
			<div class="form-group">
				<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
				<a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-repeat"></i> 重置</a>
			</div>
			<div class="form-group pull-right">
				<button type="button" class="btn btn-success" onclick="addSponsor()"><i class="fa fa-plus"></i> 新增赞助记录</button>
			</div>
		</form>
		<table id="listTable">
	  	</table>
    </div>
  </div>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/bootstrap-table.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
window.sponsorRows = {};
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 15;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax.php?act=sponsorList',
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
				title: '昵称'
			},
			{
				field: 'platform',
				title: '赞助平台'
			},
			{
				field: 'amount',
				title: '赞助金额'
			},
			{
				field: 'sponsor_time',
				title: '赞助时间'
			},
			{
				field: 'addtime',
				title: '录入时间'
			},
			{
				field: 'status',
				title: '操作',
				formatter: function(value, row, index) {
					window.sponsorRows[row.id] = row;
					return '<a href="javascript:editSponsor('+row.id+')" class="btn btn-xs btn-info">编辑</a>&nbsp;<a href="javascript:delSponsor('+row.id+')" class="btn btn-xs btn-danger">删除</a>';
				}
			},
		],
	})
})

//走后台通用的设置保存接口，POST 上去的字段名就是配置项名
function saveSponsorSetting(obj){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {icon:1, closeBtn:false}, function(){ window.location.reload(); });
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
	return false;
}

function addSponsor(){
	$("#modal-store").modal('show');
	$("#form-store")[0].reset();
	$("#form-store #id").val('');
	var today = new Date();
	var pad = function(n){ return n < 10 ? '0'+n : ''+n; };
	$("#form-store #sponsor_time").val(today.getFullYear()+'-'+pad(today.getMonth()+1)+'-'+pad(today.getDate()));
}

function editSponsor(id){
	var row = window.sponsorRows[id] || {};
	$("#modal-store").modal('show');
	$("#form-store #id").val(id);
	$("#form-store #name").val(row.name);
	$("#form-store #platform").val(row.platform);
	$("#form-store #amount").val(row.amount);
	//日期输入框只认 YYYY-MM-DD，老数据如果是"YYYY/MM/DD"格式，转换一下再填入
	$("#form-store #sponsor_time").val(String(row.sponsor_time || '').replace(/\//g, '-'));
}

function save(){
	if($("#name").val()=='' || $("#amount").val()=='' || $("#sponsor_time").val()==''){
		layer.alert('请确保各项不能为空！');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=saveSponsorInfo',
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

function delSponsor(id) {
	var confirmobj = layer.confirm('确定要删除这条赞助记录吗？', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax.php?act=delSponsor',
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
