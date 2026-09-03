<?php
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    die('require PHP >= 7.1 !');
}
include("./includes/common.php");

$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;

//老的 ?m=mine 链接（书签、外部引用）继续可用：已登录的转去个人中心，
//那边才有重命名/删除/公开私密这些管理操作；游客没有账号，留在这里看浏览器缓存记录
if(isset($_GET['m']) && $_GET['m']=='mine' && $islogin2){
    header('Location: ./user.php?tab=files');
    exit;
}
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

include_once SYSTEM_ROOT.'layout_blocks.php';
//类型筛选（数据控制台风/深色工作台风的筛选标签）；$sql_base 不带类型条件，给标签上的计数用
$sql_base = $sql;
$ft = (isset($_GET['ft']) && is_string($_GET['ft']) && array_key_exists($_GET['ft'], layout_type_filters())) ? $_GET['ft'] : '';
if($ft !== ''){
    $sql .= layout_type_filter_sql($ft);
    $link .= '&ft='.urlencode($ft);
}

include_once SYSTEM_ROOT.'script_manager.php';
include SYSTEM_ROOT.'header.php';
?>
<?php echo mpimg_render_notice_html($conf);?>
<?php echo mpimg_render_ads_html($conf);?>
<?php
//上传门户风的首屏大上传区：只有这套外观会输出，其它外观保持原来的纯列表首页
if($site_theme === 'portal' && !$kw && (!isset($_GET['m']) || $_GET['m'] !== 'mine')){
    $hero_size = get_effective_upload_size_limit();
    $hero_size_text = $hero_size > 0 ? ('单个文件最大 '.$hero_size.' MB，支持任意格式') : '不限制文件大小，支持任意格式';
?>
<div class="portal-hero">
  <div class="portal-hero-inner">
    <div class="portal-hero-copy">
      <span class="portal-kicker">快速 · 安全 · 长期可用</span>
      <h1>把文件放上来，<br>链接带去任何地方。</h1>
      <p>上传图片、视频、文档或压缩包，即刻生成可分享的外链。无需安装客户端，打开浏览器就能用。</p>
      <div class="portal-trust">
        <span><i class="fa fa-check" aria-hidden="true"></i> 支持批量上传</span>
        <span><i class="fa fa-check" aria-hidden="true"></i> 自动生成外链</span>
        <span><i class="fa fa-check" aria-hidden="true"></i> 多种存储可选</span>
      </div>
    </div>
    <a class="portal-drop" href="./upload.php">
      <span class="portal-drop-icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>
      <strong>把文件拖到这里上传</strong>
      <small><?php echo htmlspecialchars($hero_size_text, ENT_QUOTES, 'UTF-8')?></small>
      <span class="portal-drop-btn"><i class="fa fa-upload" aria-hidden="true"></i> 选择本地文件</span>
    </a>
  </div>
</div>
<?php }?>
<?php
//布局型外观的额外结构：统计卡、类型筛选、右侧预览，只在对应外观下输出
$layout_key = (isset($layout_themes) && in_array($site_theme, $layout_themes, true)) ? $site_theme : '';
$layout_is_mine = isset($_GET['m']) && $_GET['m'] === 'mine';
$layout_counts = null;
if($layout_key === 'console' || $layout_key === 'workspace' || $layout_key === 'cockpit'){
    $layout_counts = layout_type_counts($DB, $sql_base);
}
//渐变仪表盘风的问候栏、额度卡、统计卡和右侧栏都要用这两个数，先算一次传下去
$cockpit_today = 0;
if($layout_key === 'cockpit'){
    $cockpit_today = layout_today_total($DB, $sql_base);
}
$layout_base_query = '';
if($layout_is_mine) $layout_base_query .= 'm=mine';
if($kw) $layout_base_query .= ($layout_base_query === '' ? '' : '&').'kw='.urlencode($kw);
?>
<div class="container">
<?php if($layout_key === 'cockpit'){echo layout_render_cockpit_head($DB, $layout_counts['']);}?>
<?php if($layout_key === 'workspace' || $layout_key === 'cockpit'){?><div class="layout-shell"><?php }?>
<?php if($layout_key === 'cockpit'){?><div class="cockpit-main">
<?php echo layout_render_cockpit_quota($DB, layout_storage_used($DB, $sql_base), $layout_counts[''], $cockpit_today);?>
<?php echo layout_render_stats($layout_counts, $cockpit_today);?>
<?php }?>
    <div class="well bs-component">
<?php if($layout_key === 'workspace'){?>
        <div class="layout-crumb"><i class="fa fa-folder-o" aria-hidden="true"></i> <span>工作空间</span> <i class="fa fa-angle-right" aria-hidden="true"></i> <strong><?php echo $layout_is_mine ? '我的文件' : '全部文件'?></strong></div>
<?php }?>
        <h2><?php echo $htext?>
        <?php if($conf['filesearch']==1){?><span class="searchbox">
            <form class="form-inline" action="./" method="GET">
                <?php if(isset($_GET['m']) && is_string($_GET['m'])){?><input name="m" type="hidden" value="<?php echo htmlspecialchars($_GET['m'], ENT_QUOTES, 'UTF-8')?>"><?php }?>
				<input name="kw" class="form-control" type="search" placeholder="请输入搜索关键字" value="<?php echo htmlspecialchars((string)$kw, ENT_QUOTES, 'UTF-8')?>" required="">
				<button class="btn btn-default btn-raised btn-sm" type="submit"><i class="fa fa-search" aria-hidden="true"></i> 搜索</button>
			</form>
        </span><?php }?><?php if($layout_key === 'mac'){echo layout_render_mac_viewtoggle();}?><?php if($layout_key === 'console' || $layout_key === 'workspace' || $layout_key === 'mac'){?><a class="layout-cta" href="./upload.php"><i class="fa fa-plus" aria-hidden="true"></i> <span><?php echo $layout_key === 'mac' ? '上传文件' : '上传新文件'?></span></a><?php }?></h2>
<?php echo render_permission_bar($DB, 'list');?>
<?php if($layout_key === 'console'){?>
        <p class="layout-page-sub">管理、预览并分享你上传的所有内容。</p>
        <?php echo layout_render_stats($layout_counts, layout_today_total($DB, $sql_base));?>
<?php }elseif($layout_key === 'portal'){?>
        <p class="layout-page-sub">浏览大家刚刚分享的文件，点文件名即可查看或下载。</p>
<?php }elseif($layout_key === 'cockpit'){?>
        <p class="layout-page-sub">按上传时间排序，点文件名即可查看、下载或复制外链。</p>
<?php }elseif($layout_key === 'mac'){
        //macOS 窗口风：列表上方放一块拖拽提示区。搜索/筛选状态下不显示，
        //免得把用户刚查出来的结果顶到屏幕外面去
        if(!$kw && $ft === ''){echo layout_render_mac_drop();}
}?>
<?php if(($layout_key === 'console' || $layout_key === 'workspace' || $layout_key === 'cockpit') && $layout_counts){?>
        <?php echo layout_render_filters($layout_counts, $ft, $layout_base_query);?>
<?php }?>
        <?php if(isset($_GET['m']) && $_GET['m']=='mine'){?>
        <input type="file" id="replaceFileInput" style="display:none">
        <?php }?>
        <div class="table-responsive">
       <table class="table table-striped table-hover filelist filelist-main">
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
	//设了访问密码的文件，在文件名后面挂个锁，列表里一眼能看出来。
	//不用 fa-fw，免得被各外观给类型图标定的颜色带跑
	$lock_icon = !empty($res['pwd']) ? ' <i class="fa fa-lock filelist-lock" title="该文件需要密码才能查看" aria-hidden="true"></i>' : '';
	$row_ip = preg_replace('/\d+$/','*',$res['ip']);
	//深色工作台风的预览面板要直接显示图片/视频/文本：没有设密码、没被封禁、且是可在线预览的
	//类型时才给出预览地址，其余情况面板里还是显示文件类型图标
	$layout_text_max = defined('LAYOUT_TEXT_PREVIEW_MAX') ? LAYOUT_TEXT_PREVIEW_MAX : 256 * 1024;
	$preview_url = '';
	$preview_kind = '';
	if(empty($res['pwd']) && intval($res['block']) === 0){
		if(is_view($res['type'])){
			$preview_kind = get_view_type($res['type']);
			if($preview_kind === 'image' || $preview_kind === 'video' || $preview_kind === 'audio'){
				$preview_url = './view.php/'.$res['token'].'.'.$res['type'].'?preview=1';
			}else{
				$preview_kind = '';
			}
		//常量定义在 layout_blocks.php 里，万一只传了部分文件也要能正常降级，不能静默失效
		}elseif(is_editable_file_type($res['type']) && intval($res['size']) <= $layout_text_max){
			//txt/json/js 这类文本文件走 text.php 取内容；太大的不自动拉，免得点一下列表就下几 MB
			$preview_kind = 'text';
			$preview_url = './text.php?hash='.$res['token'].'&preview=1';
		}
	}
	//data-* 给布局型外观用：深色工作台风的右侧预览面板直接读这几个值
	$row_attr = ' data-group="'.layout_type_group($res['type']).'"'
		.' data-preview="'.htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8').'"'
		.' data-preview-kind="'.htmlspecialchars($preview_kind, ENT_QUOTES, 'UTF-8').'"'
		.' data-name="'.htmlspecialchars($res['name'], ENT_QUOTES, 'UTF-8').'"'
		.' data-size="'.htmlspecialchars(size_format($res['size']), ENT_QUOTES, 'UTF-8').'"'
		.' data-type="'.htmlspecialchars($type_text, ENT_QUOTES, 'UTF-8').'"'
		.' data-time="'.htmlspecialchars($res['addtime'], ENT_QUOTES, 'UTF-8').'"'
		.' data-ip="'.htmlspecialchars($row_ip, ENT_QUOTES, 'UTF-8').'"'
		.' data-down="'.htmlspecialchars($fileurl, ENT_QUOTES, 'UTF-8').'"'
		.' data-view="'.htmlspecialchars($viewurl, ENT_QUOTES, 'UTF-8').'"'
		.' data-icon="'.type_to_icon($res['type']).'"'
		.' data-lock="'.(!empty($res['pwd']) ? '1' : '').'"';
echo '<tr'.$row_attr.'><td><b>'.$i++.'</b></td><td class="filelist-actions-cell">'.$actions.'</td><td><i class="fa '.type_to_icon($res['type']).' fa-fw"></i>'.$res['name'].$lock_icon.'</td><td>'.size_format($res['size']).'</td><td><span class="file-type-badge">'.htmlspecialchars($type_text).'</span></td><td>'.$res['addtime'].'</td><td>'.$row_ip.'</td></tr>';
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
<?php if($layout_key === 'workspace'){echo layout_render_preview();?></div><?php }?>
<?php if($layout_key === 'cockpit'){?></div><?php echo layout_render_cockpit_side($DB, $layout_counts, $sql_base);?></div><?php }?>
<?php include SYSTEM_ROOT.'footer.php';?>
<?php if($layout_key === 'workspace'){?>
<script src="./assets/js/layout-workspace.js?v=<?php echo VERSION?>"></script>
<?php }?>
<?php if($layout_key === 'mac'){?>
<script src="./assets/js/layout-mac.js?v=<?php echo VERSION?>"></script>
<?php }?>
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
