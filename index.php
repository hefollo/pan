<?php
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    die('require PHP >= 7.1 !');
}
include("./includes/common.php");

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

if(isset($_GET['m']) && $_GET['m']=='mine'){
    $title = '我的文件 - ' . $conf['title'];
    $htext = '我上传的文件';
    if($islogin2){
        $sql = " uid='{$uid}'";
    }else{
        if($conf['userlogin']==1){
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录，<a href="login.php">登录</a>后可永久保留记录）</span>';
        }else{
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录）</span>';
        }
        if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
            $ids = array_reverse($_SESSION['fileids']);
            if(count($ids) > 60){
                $ids = array_splice($ids, 0, 60);
            }
            $ids = implode(',',$ids);
            $sql = " id IN ($ids)";
        }else{
            $sql = " 1=2";
        }
    }
    $link = '&m=mine';
}else{
    $title = $conf['title'];
    $htext = '文件列表';
    $sql = " hide=0";
    $link = '';
}
//搜索词要分三种用途保存：入SQL的转义版、进URL的编码版、进HTML的实体版，混用会出漏洞
$kw = (isset($_GET['kw']) && is_string($_GET['kw']))?trim(strip_tags($_GET['kw'])):null;
if($conf['filesearch']==1 && $kw){
    $kw_sql = daddslashes($kw);
    $sql.=" AND name LIKE '%{$kw_sql}%'";
    $link .= '&kw='.urlencode($kw);
}

include_once SYSTEM_ROOT.'script_manager.php';
include SYSTEM_ROOT.'header.php';
?>
<?php echo mpimg_render_notice_html($conf);?>
<?php echo mpimg_render_ads_html($conf);?>
<div class="container">
    <div class="well bs-component">
        <h2><?php echo $htext?>
        <?php if($conf['filesearch']==1){?><span class="searchbox">
            <form class="form-inline" action="./" method="GET">
                <?php if(isset($_GET['m']) && is_string($_GET['m'])){?><input name="m" type="hidden" value="<?php echo htmlspecialchars($_GET['m'], ENT_QUOTES, 'UTF-8')?>"><?php }?>
				<input name="kw" class="form-control" type="search" placeholder="请输入搜索关键字" value="<?php echo htmlspecialchars((string)$kw, ENT_QUOTES, 'UTF-8')?>" required="">
				<button class="btn btn-default btn-raised btn-sm" type="submit"><i class="fa fa-search" aria-hidden="true"></i> 搜索</button>
			</form>
        </span><?php }?></h2>
        <?php if(isset($_GET['m']) && $_GET['m']=='mine'){?>
        <input type="file" id="replaceFileInput" style="display:none">
        <?php }?>
        <div class="table-responsive">
       <table class="table table-striped table-hover filelist">
            <thead>
                <tr>
                    <th>#</th>
                    <th>操作</th>
                    <th>文件名</th>
                    <th>文件大小</th>
                    <th>文件格式</th>
                    <th>上传时间</th>
                    <th>上传者IP</th>
                </tr>
            </thead>
            <tbody>
<?php
$numrows=$DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
$pagesize=15;
$pages=ceil($numrows/$pagesize);
$page=isset($_GET['page'])?intval($_GET['page']):1;
$offset=$pagesize*($page - 1);

$rs=$DB->query("SELECT * FROM pre_file WHERE{$sql} ORDER BY id DESC LIMIT $offset,$pagesize");
$i=1;
while($res = $rs->fetch())
{
	$fileurl = './down.php/'.$res['token'].'.'.($res['type']?$res['type']:'file');
	$viewurl = './file.php?hash='.$res['token'];
	$actions = '<div class="file-actions"><a class="file-action file-action-down" href="'.$fileurl.'" title="下载"><i class="fa fa-download" aria-hidden="true"></i> <span class="file-action-label">下载</span></a><a class="file-action file-action-view" href="'.$viewurl.'" title="查看"><i class="fa fa-eye" aria-hidden="true"></i> <span class="file-action-label">查看</span></a>';
	if(isset($_GET['m']) && $_GET['m']=='mine' && can_edit_file_online($res)){
		$actions .= '<a class="file-action file-action-edit" href="./edit.php?id='.$res['id'].'" title="编辑"><i class="fa fa-pencil" aria-hidden="true"></i> <span class="file-action-label">编辑</span></a>';
	}
	if(isset($_GET['m']) && $_GET['m']=='mine' && can_manage_file($res)){
		$actions .= '<a class="file-action file-action-replace" href="javascript:void(0)" onclick="replace_upload_click('.intval($res['id']).')" title="覆盖"><i class="fa fa-refresh" aria-hidden="true"></i> <span class="file-action-label">覆盖</span></a>';
	}
	$actions .= '</div>';
	$type_text = $res['type']?$res['type']:'未知';
echo '<tr><td><b>'.$i++.'</b></td><td class="filelist-actions-cell">'.$actions.'</td><td><i class="fa '.type_to_icon($res['type']).' fa-fw"></i>'.$res['name'].'</td><td>'.size_format($res['size']).'</td><td><span class="file-type-badge">'.htmlspecialchars($type_text).'</span></td><td>'.$res['addtime'].'</td><td>'.preg_replace('/\d+$/','*',$res['ip']).'</b></td></tr>';
}
if($numrows == 0) echo '<tr><td colspan="7" align="center">还没上传过任何文件</td></tr>';
?>
            </tbody>
        </table>
        </div>
        <div class="filelist-footer">
        <div class="filelist-summary">共有 <?php echo $numrows?> 个文件&nbsp;&nbsp;当前第 <?php echo $page?> 页，共 <?php echo $pages?> 页</div>
        <nav class="filelist-pager">
  <ul class="pagination pagination-sm">
<?php
$first=1;
$prev=$page-1;
$next=$page+1;
$last=$pages;
if ($page>1)
{
echo '<li><a href="index.php?page='.$first.$link.'">首页</a></li>';
echo '<li><a href="index.php?page='.$prev.$link.'">&laquo;</a></li>';
} else {
echo '<li class="disabled"><a>首页</a></li>';
echo '<li class="disabled"><a>&laquo;</a></li>';
}
$pagegroup = 10;
$start = $page < $pagegroup ? 1 : floor($page / $pagegroup) * $pagegroup;
$end = min($start + $pagegroup, $pages);
for ($i=$start;$i<=$end;$i++){
	if($i == $page){
		echo '<li class="disabled"><a>'.$i.'</a></li>';
	}else{
		echo '<li><a href="index.php?page='.$i.$link.'">'.$i.'</a></li>';
	}
}
echo '';
if ($page<$pages)
{
echo '<li><a href="index.php?page='.$next.$link.'">&raquo;</a></li>';
echo '<li><a href="index.php?page='.$last.$link.'">尾页</a></li>';
} else {
echo '<li class="disabled"><a>&raquo;</a></li>';
echo '<li class="disabled"><a>尾页</a></li>';
}
?>
  </ul>
</nav>
</div>
    </div>
<?php include SYSTEM_ROOT.'footer.php';?>
<?php if(isset($_GET['m']) && $_GET['m']=='mine'){?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
var replace_csrf_token = '<?php echo $csrf_token?>';
var replace_target_id = 0;
function replace_upload_click(id){
  replace_target_id = id;
  $("#replaceFileInput").val('');
  $("#replaceFileInput").trigger('click');
}
$("#replaceFileInput").on('change', function(){
  var file = this.files && this.files[0];
  if(!file || !replace_target_id) return;
  var fileId = replace_target_id;
  var ii = layer.load(2, {shade:[0.2,'#fff']});

  //本页面可能已经打开一段时间，会话里的csrf_token可能已被同一浏览器其它页面刷新过，先取一次最新值再提交
  $.getJSON('ajax.php?act=csrf_token', function(tokenData){
    if(tokenData && tokenData.csrf_token) replace_csrf_token = tokenData.csrf_token;
    replace_startUpload(file, fileId, ii);
  }).fail(function(){
    replace_startUpload(file, fileId, ii);
  });
});
function replace_startUpload(file, fileId, ii){
  replace_getFileHash(file).then(function(hash){
    $.ajax({
      type: 'POST',
      url: 'ajax.php?act=pre_upload',
      data: {
        csrf_token: replace_csrf_token,
        name: file.name,
        hash: hash,
        size: file.size,
        show: '1',
        ispwd: '0',
        pwd: '',
        replace_id: fileId
      },
      dataType: 'json',
      success: function(data){
        if(data.csrf_token) replace_csrf_token = data.csrf_token;
        if(data.code == 1){
          layer.close(ii);
          layer.alert(data.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
        }else if(data.code == 0){
          replace_uploadBody(data, file, ii);
        }else{
          layer.close(ii);
          layer.alert(data.msg || '替换失败', {icon:2});
        }
      },
      error: function(){
        layer.close(ii);
        layer.msg('服务器错误');
      }
    });
  });
}
function replace_getFileHash(file){
  return new Promise(function(resolve){
    var fileReader = new FileReader(),
        blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice,
        chunkSize = 2097152,
        chunks = Math.ceil(file.size / chunkSize),
        currentChunk = 0,
        spark = new SparkMD5();
    if(chunks === 0){
      resolve(SparkMD5.hashBinary(''));
      return;
    }
    loadNext();
    fileReader.onload = function(e){
      spark.appendBinary(e.target.result);
      currentChunk++;
      if(currentChunk < chunks){
        loadNext();
      }else{
        resolve(spark.end());
      }
    };
    function loadNext(){
      var start = currentChunk * chunkSize,
          end = start + chunkSize >= file.size ? file.size : start + chunkSize;
      fileReader.readAsBinaryString(blobSlice.call(file, start, end));
    }
  });
}
function replace_uploadBody(preResult, file, ii){
  if(preResult.third){
    var data = new FormData();
    for(var key in preResult.post){ data.append(key, preResult.post[key]); }
    data.append('file', file);
    $.ajax({
      type: 'POST', url: preResult.url, data: data, processData: false, contentType: false, dataType: 'html',
      success: function(){ replace_completeUpload(preResult.hash, ii); },
      error: function(){ layer.close(ii); layer.msg('上传失败，请稍后再试'); }
    });
    return;
  }
  var chunks = preResult.chunks, chunkSize = preResult.chunksize;
  var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice;
  function uploadChunk(chunk){
    var start = (chunk - 1) * chunkSize;
    var end = start + chunkSize > file.size ? file.size : start + chunkSize;
    var blob = blobSlice.call(file, start, end);
    var data = new FormData();
    data.append('file', blob);
    data.append('hash', preResult.hash);
    data.append('chunk', chunk);
    data.append('csrf_token', replace_csrf_token);
    $.ajax({
      type: 'POST', url: 'ajax.php?act=upload_part', data: data, processData: false, contentType: false, dataType: 'json',
      success: function(res){
        if(res.csrf_token) replace_csrf_token = res.csrf_token;
        if(res.code == -1){
          layer.close(ii);
          layer.alert(res.msg || '替换失败', {icon:2});
          return;
        }
        if(chunk < chunks){
          uploadChunk(chunk + 1);
        }else if(res.code == 1){
          layer.close(ii);
          layer.alert(res.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
        }
      },
      error: function(){ layer.close(ii); layer.msg('上传失败，请稍后再试'); }
    });
  }
  uploadChunk(1);
}
function replace_completeUpload(hash, ii){
  $.ajax({
    type: 'POST', url: 'ajax.php?act=complete_upload', data: {hash: hash, csrf_token: replace_csrf_token}, dataType: 'json',
    success: function(res){
      layer.close(ii);
      if(res.code == 1){
        layer.alert(res.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
      }else{
        layer.alert(res.msg || '替换失败', {icon:2});
      }
    },
    error: function(){ layer.close(ii); layer.msg('服务器错误'); }
  });
}
</script>
<?php }?>
<?php if(!empty($conf['gonggao'])){?>
<link href="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.css" rel="stylesheet">
<script src="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script>
$(function() {
    if(!$.cookie('gonggao')){
        $.snackbar({content: "<?php echo $conf['gonggao']?>", timeout: 10000});
        var cookietime = new Date(); 
        cookietime.setTime(cookietime.getTime() + (60*60*1000));
        $.cookie('gonggao', false, { expires: cookietime });
    }
});
</script>
<?php }?>
</body>
</html>
