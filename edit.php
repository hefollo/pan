<?php
include("./includes/common.php");

$title = '在线编辑 - '.$conf['title'];
$is_file = true;

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$hash = isset($_GET['hash']) ? trim($_GET['hash']) : null;

if($id > 0){
	$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id", [':id'=>$id]);
}elseif($hash && preg_match('/^[0-9a-z]{32}$/i', $hash)){
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
}else{
	sysmsg('参数错误');
}

if(!$row)sysmsg('文件不存在');
if(!can_manage_file($row))sysmsg('无权限编辑该文件');
if($row['block']==1)sysmsg('文件已被冻结，无法编辑');
if(!is_editable_file_type($row['type']))sysmsg('该文件格式不支持在线编辑');

if(!can_use_online_edit())sysmsg('当前账号无权使用在线编辑功能');

$max_size = get_editable_file_max_size();
if($row['size'] > $max_size){
	sysmsg('在线编辑文件大小不能超过 '.size_format($max_size));
}

$content = get_storage_content($row['hash']);
if($content === false)sysmsg('读取文件内容失败');
$decoded = decode_editable_content($content);
if($decoded['code'] != 0)sysmsg($decoded['msg']);
$content = $decoded['content'];

$file_name = htmlspecialchars($row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$file_content = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$file_encoding = htmlspecialchars($decoded['encoding'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$view_url = 'file.php?hash='.$row['hash'];

include SYSTEM_ROOT.'header.php';
?>
<style>
.editor-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	margin-bottom: 12px;
}
.editor-meta {
	color: #777;
	font-size: 13px;
}
#file_content {
	min-height: 560px;
	resize: vertical;
	font-family: Consolas, Monaco, "Courier New", monospace;
	font-size: 14px;
	line-height: 1.55;
	white-space: pre;
	tab-size: 4;
}
@media (max-width: 767px) {
	#file_content {
		min-height: 420px;
		font-size: 13px;
	}
}
</style>
<div class="container">
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title"><i class="fa fa-pencil-square-o"></i> 在线编辑：<?php echo $file_name?></h3>
		</div>
		<div class="panel-body">
			<div class="editor-toolbar">
				<div class="editor-meta">
					格式：<?php echo htmlspecialchars($row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>　
					大小：<span id="file_size_text"><?php echo size_format($row['size'])?></span>　
					编码：<span id="file_encoding_text"><?php echo $file_encoding?></span>　
					上限：<?php echo size_format($max_size)?>
					<?php if($decoded['converted']){?>　<span class="text-warning">保存后将转为 UTF-8</span><?php }?>
				</div>
				<div>
					<button type="button" id="save_editor" class="btn btn-raised btn-primary">
						<i class="fa fa-save"></i> 保存
					</button>
					<a id="view_file_link" href="<?php echo $view_url?>" class="btn btn-raised btn-default">
						<i class="fa fa-eye"></i> 查看文件
					</a>
					<a href="./?m=mine" class="btn btn-raised btn-default">
						<i class="fa fa-folder-open"></i> 我的文件
					</a>
				</div>
			</div>
			<input type="hidden" id="file_id" value="<?php echo intval($row['id'])?>">
			<input type="hidden" id="csrf_token" value="<?php echo $csrf_token?>">
			<textarea id="file_content" class="form-control" spellcheck="false"><?php echo $file_content?></textarea>
		</div>
	</div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function getEditorBytes(text) {
	return new Blob([text]).size;
}
function saveEditor() {
	var content = $("#file_content").val();
	var maxSize = <?php echo intval($max_size)?>;
	if (getEditorBytes(content) > maxSize) {
		layer.alert("在线编辑文件大小不能超过 <?php echo size_format($max_size)?>", {icon: 2});
		return;
	}
	var ii = layer.load(2);
	$.ajax({
		type: "POST",
		url: "ajax.php?act=saveFileContent",
		data: {
			id: $("#file_id").val(),
			csrf_token: $("#csrf_token").val(),
			content: content
		},
		dataType: "json",
		success: function(data) {
			layer.close(ii);
			if (data.code === 0) {
				$("#file_size_text").text(data.size);
				$("#view_file_link").attr("href", "file.php?hash=" + data.hash);
				layer.msg("保存成功", {icon: 1});
			} else {
				layer.alert(data.msg || "保存失败", {icon: 2});
			}
		},
		error: function() {
			layer.close(ii);
			layer.msg("服务器错误", {icon: 2});
		}
	});
}
$("#save_editor").on("click", saveEditor);
$("#file_content").on("keydown", function(e) {
	if ((e.ctrlKey || e.metaKey) && (e.key === "s" || e.keyCode === 83)) {
		e.preventDefault();
		saveEditor();
	}
});
</script>
</body>
</html>
