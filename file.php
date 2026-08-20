<?php
include("./includes/common.php");

$title = '文件查看 - '.$conf['title'];
$is_file=true;
include SYSTEM_ROOT.'header.php';

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

$hash = isset($_GET['hash'])?$_GET['hash']:exit("<script language='javascript'>window.location.href='./';</script>");
$pwd = isset($_GET['pwd'])?$_GET['pwd']:null;
$row = $DB->getRow("SELECT * FROM pre_file WHERE token=:token", [':token'=>$hash]);
if(!$row)exit("<script language='javascript'>alert('文件不存在');window.location.href='./';</script>");
$name = $row['name'];
$type = $row['type'];

$downurl = 'down.php/'.$row['token'].'.'.$type;
if(!empty($row['pwd']))$downurl .= '&'.$row['pwd'];
$viewurl = 'view.php/'.$row['token'].'.'.$type;
$texturl = 'text.php?hash='.$row['token'];
if(!empty($pwd))$texturl .= '&pwd='.rawurlencode($pwd);

$downurl_all = $siteurl.$downurl;
$viewurl_all = $siteurl.$viewurl;
$texturl_all = $siteurl.$texturl;

$thisurl = $siteurl.'file.php?hash='.$row['token'];
if(!empty($pwd))$thisurl .= '&pwd='.$pwd;
$file_reward_enable = isset($conf['file_reward_enable']) ? ((int)$conf['file_reward_enable'] === 1) : true;
$file_reward_title = isset($conf['file_reward_title']) && $conf['file_reward_title'] !== '' ? $conf['file_reward_title'] : '&#25195;&#30721;&#39046;&#32418;&#21253;';
$file_reward_image = isset($conf['file_reward_image']) && $conf['file_reward_image'] !== '' ? $conf['file_reward_image'] : 'includes/sponsor/images/zhifubaohb.jpg';

$is_mine = can_manage_file($row);
$is_editable = can_edit_file_online($row);
$is_text_viewable = is_editable_file_type($type);

$view_type = get_view_type($type);

if($view_type == 'image'){
  $filetype = 1;
  $title = '<i class="fa fa-picture-o"></i> 图片查看器';
  $htmlcode = htmlspecialchars('<img src="'.$viewurl_all.'"/>');
  $ubbcode = '[img]'.$viewurl_all.'[/img]';
  $linktitle = '图片链接';
}elseif($view_type == 'audio'){
  $filetype = 2;
  $title = '<i class="fa fa-music"></i> 音乐播放器';
  $htmlcode = htmlspecialchars('<audio id="bgmMusic" src="'.$viewurl_all.'" autoplay="autoplay" loop="loop" preload="auto"></audio>');
  $htmlcode2 = htmlspecialchars('<iframe src="'.$siteurl.'player.php?hash='.$hash.'" width="407" scrolling="no"frameborder="0"height="70"></iframe>');
  $ubbcode = '[audio=X]'.$viewurl_all.'[/audio]';
  $linktitle = '音乐链接';
}elseif($view_type == 'video'){
  $filetype = 3;
  $title = '<i class="fa fa-video-camera"></i> 视频播放器';
  $htmlcode = htmlspecialchars('<video id="movies" src="'.$viewurl_all.'" autobuffer="true" controls="" width="100
  %"></video>');
  $htmlcode2 = htmlspecialchars('<iframe src="'.$siteurl.'player.php?hash='.$hash.'" width="800" height="500" scrolling="no" frameborder="0"></iframe>');
  $ubbcode = '[movie=320*180]'.$viewurl_all.'[/movie]';
  $linktitle = '视频链接';
}else{
  $filetype = 0;
  $title = '<i class="fa fa-file"></i> 文件查看';
  $htmlcode = htmlspecialchars('<a href="'.$downurl_all.'" target="_blank">'.$name.'</a>');
  $ubbcode = '[url='.$downurl_all.']'.$name.'[/url]';
  if($view_type == 'office'){
    $office_url = 'https://view.officeapps.live.com/op/view.aspx?src='.rawurlencode($downurl_all);
  }
}
?>
<div class="container">
    <div class="row">
<?php
if($row['pwd']!=null && $row['pwd']!=$pwd){ ?>
  <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
  <title>请输入密码下载文件</title>
  <script type="text/javascript">
  var pwd=prompt("请输入密码","")
  if (pwd!=null && pwd!="")
  {
      window.location.href="./file.php?hash=<?php echo $row['token']?>&pwd="+pwd
  }
  </script>
  请刷新页面，或 [ <a href="javascript:history.back();">返回上一页</a> ]
<?php
  exit;
}

?>
      <div class="col-sm-9">
<div class="panel panel-primary">
<div class="panel-heading">
<h3 class="panel-title"><?php echo $title?></h3>
</div>
<div class="panel-body" align="center">
<?php
if($filetype==1){
  echo '<div class="image_view"><a href="'.$viewurl.'" title="点击查看原图"><img alt="loading" src="'.$viewurl.'" class="image"></a></div>';
}elseif($filetype==2){
  echo '<div class="view"><div id="aplayer"></div></div>';
}elseif($filetype==3 && $row['block']==0){
  echo '<div class="videoplayer"></div>';
}elseif($filetype==3){
  echo '<div class="view">
  <div class="elseview">
  <div class="tubiao"><i class="fa '.type_to_icon($type).'"></i> </div>
</div>
<div class="elsetext"><p>'.$name.'</p><p>视频文件需审核通过后才能在线播放和下载，请等待审核通过。</p></div>
</div>';
}else{
  echo '<div class="view">
  <div class="elseview">
  <div class="tubiao"><i class="fa '.type_to_icon($type).'"></i> </div>
</div>
<div class="elsetext"><p>'.$name.' ('.size_format($row['size']).')</p>
<a href="'.$downurl.'" class="btn btn-raised btn-primary btn-lg"><i class="fa fa-download" aria-hidden="true"></i> 下载文件<div class="ripple-container"></div></a>'.($is_text_viewable?'&nbsp;<a href="'.$texturl.'" class="btn btn-raised btn-info btn-lg" target="_blank"><i class="fa fa-eye" aria-hidden="true"></i> 查看内容<div class="ripple-container"></div></a>':'').($view_type=='office'?'&nbsp;<a href="'.$office_url.'" class="btn btn-raised btn-info btn-lg" target="_blank"><i class="fa fa-eye" aria-hidden="true"></i> 在线预览<div class="ripple-container"></div></a>':'').'
</div>
</div>';
}
?>
</div>
</div>
      <div class="panel panel-default">
          <div class="panel-body" style="padding: 0px;">
              <ul class="nav nav-tabs" style="margin-bottom: 15px;">
                  <li class="active"><a href="#link" data-toggle="tab"><i class="fa fa-link" aria-hidden="true"></i> 文件外链</a>
                  </li>
                  <li><a href="#code" data-toggle="tab"><i class="fa fa-code" aria-hidden="true"></i> 代码调用</a>
                  </li>
                  <li><a href="#info" data-toggle="tab"><i class="fa fa-info-circle" aria-hidden="true"></i> 文件详情</a>
                  </li>
                  <li class="<?php echo $is_mine?'':'hide';?>"><a href="#manager" data-toggle="tab"><i class="fa fa-cog" aria-hidden="true"></i> 管理</a>
                  </li>
              </ul>
              <div id="myTabContent" class="tab-content" style="padding: 19px;">
                  <div class="tab-pane fade active in" id="link">
                    <div class="form-group row <?php echo $filetype==0?'hide':'';?>">
                      <label for="link1" class="col-md-2 control-label"><?php echo $linktitle?>：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="link1" readonly="readonly" value="<?php echo $viewurl_all?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $viewurl_all?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="link2" class="col-md-2 control-label">下载链接：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="link2" readonly="readonly" value="<?php echo $downurl_all?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $downurl_all?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row <?php echo $is_text_viewable?'':'hide';?>">
                      <label for="link3" class="col-md-2 control-label">查看链接：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="link3" readonly="readonly" value="<?php echo $texturl_all?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $texturl_all?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="code">
                    <div class="form-group row <?php echo $filetype<2?'hide':'';?>">
                      <label for="code1" class="col-md-2 control-label">播放器代码：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="code1" readonly="readonly" value="<?php echo $htmlcode2?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $htmlcode2?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="code2" class="col-md-2 control-label">HTML代码：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="code2" readonly="readonly" value="<?php echo $htmlcode?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $htmlcode?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="code3" class="col-md-2 control-label">UBB代码：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="code3" readonly="readonly" value="<?php echo $ubbcode?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $ubbcode?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="info">
                      <div class="row" align="center">
                          <table class="table table-bordered fileinfo-table">
                              <tr>
                                  <th width="97">上传者IP：</th><td width="100"><?php echo preg_replace('/\d+$/','*',$row['ip'])?></td>
                                  <th width="100">上传时间：</th><td width="168"><?php echo $row['addtime']?></td>
                              </tr>
                              <tr>
                                  <th>下载次数：</th><td><?php echo $row['count']?></td>
                                  <th>文件大小：</th><td><?php echo size_format($row['size']).' ('.$row['size'].' 字节)'?></td>
                              </tr>
                          </table>
                      </div>
                  </div>
                  <div class="tab-pane fade" id="manager">
                      <div class="row" align="center">
                          <div class="col-md-12">
                            <input type="hidden" id="hash" name="hash" value="<?php echo $hash?>">
                            <input type="hidden" id="file_id" value="<?php echo intval($row['id'])?>">
                            <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrf_token?>">
                            <input type="file" id="replaceFileInput" style="display:none">
                            <?php if($is_editable){?>
                            <a href="edit.php?id=<?php echo intval($row['id'])?>" class="btn btn-raised btn-info"><i class="fa fa-pencil-square-o" aria-hidden="true"></i> 在线编辑</a>
                            <?php }?>
                            <button type="button" onclick="replace_upload()" class="btn btn-raised btn-warning"><i class="fa fa-refresh" aria-hidden="true"></i> 重新上传替换（链接不变）</button>
                            <button onclick="delete_confirm()" class="btn btn-raised btn-danger"><i class="fa fa-close" aria-hidden="true"></i> 删除文件</button>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
      </div>
      <div class="col-sm-3">
<div class="panel panel-info">
<div class="panel-heading">
<h3 class="panel-title"><i class="fa fa-exclamation-circle"></i> 提示</h3>
</div>
<div class="panel-body">
<?php echo $conf['gg_file']?>
</div>
</div>
<?php if($file_reward_enable && !empty($file_reward_image)){ ?>
<div class="panel panel-default hidden-xs">
<div class="panel-heading">
<h3 class="panel-title"><i class="fa fa-qrcode"></i> <?php echo $file_reward_title?></h3>
</div>
<div class="panel-body text-center">
<!--<img alt="浜岀淮鐮? src="//api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=<?php echo urlencode($thisurl);?>">-->
<img alt="<?php echo htmlspecialchars(strip_tags($file_reward_title), ENT_QUOTES, 'UTF-8');?>" src="<?php echo htmlspecialchars($file_reward_image, ENT_QUOTES, 'UTF-8');?>" style="width: 200px; height: 325px; max-width: 100%; object-fit: contain; border-radius: 8px;">
</div>
</div>
<?php } ?>
      </div>
    </div>
  </div>
<?php include SYSTEM_ROOT.'footer.php';?>
<?php if($filetype==2){?>
<script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.js"></script>
<script type="text/javascript">
var ap = new APlayer({
  container: document.getElementById('aplayer'),
  loop: 'none',
  theme: '#b2dae6',
  audio: [{
      title: '<?php echo $name?>',
      author: 'none',
      url: '<?php echo $viewurl_all?>',
      cover: './assets/img/music.png',
  }]
});
</script>
<?php }elseif($filetype==3 && $row['block']==0){?>
<script type="text/javascript" src="assets/js/ckplayer.min.js"></script>
<?php if($type=='m3u8'){$plug='hls.js';?><script src="https://s4.zstatic.net/ajax/libs/hls.js/1.2.4/hls.min.js"></script><?php }?>
<?php if($type=='flv'||$type=='f4v'){$plug='flv.js';?><script src="https://s4.zstatic.net/ajax/libs/flv.js/1.6.2/flv.min.js"></script><?php }?>
<script type="text/javascript">
  var videoObject = {
    container: '.videoplayer',
    plug:'<?php echo $plug?>',
    video:'<?php echo $viewurl_all?>',
    webFull:true,
  };
  var player=new ckplayer(videoObject);
</script>
<?php }?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/2.3/skin/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/clipboard.js/1.7.1/clipboard.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
function replace_upload(){
  $("#replaceFileInput").val('');
  $("#replaceFileInput").trigger('click');
}
$("#replaceFileInput").on('change', function(){
  var file = this.files && this.files[0];
  if(!file) return;
  var fileId = $("#file_id").val();
  var ii = layer.load(2, {shade:[0.2,'#fff']});

  //页面可能已经打开一段时间，会话里的csrf_token可能已被同一浏览器其它页面刷新过，先取一次最新值再提交
  $.getJSON('ajax.php?act=csrf_token', function(tokenData){
    var csrf_token = (tokenData && tokenData.csrf_token) ? tokenData.csrf_token : $("#csrf_token").val();
    replace_startUpload(file, fileId, csrf_token, ii);
  }).fail(function(){
    replace_startUpload(file, fileId, $("#csrf_token").val(), ii);
  });
});
function replace_startUpload(file, fileId, csrf_token, ii){
  replace_getFileHash(file).then(function(hash){
    $.ajax({
      type: 'POST',
      url: 'ajax.php?act=pre_upload',
      data: {
        csrf_token: csrf_token,
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
        if(data.code == 1){
          layer.close(ii);
          layer.alert(data.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
        }else if(data.code == 0){
          replace_uploadBody(data, file, csrf_token, ii);
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
function replace_uploadBody(preResult, file, csrf_token, ii){
  if(preResult.third){
    var data = new FormData();
    for(var key in preResult.post){ data.append(key, preResult.post[key]); }
    data.append('file', file);
    $.ajax({
      type: 'POST', url: preResult.url, data: data, processData: false, contentType: false, dataType: 'html',
      success: function(){ replace_completeUpload(preResult.hash, csrf_token, ii); },
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
    data.append('csrf_token', csrf_token);
    $.ajax({
      type: 'POST', url: 'ajax.php?act=upload_part', data: data, processData: false, contentType: false, dataType: 'json',
      success: function(res){
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
function replace_completeUpload(hash, csrf_token, ii){
  $.ajax({
    type: 'POST', url: 'ajax.php?act=complete_upload', data: {hash: hash, csrf_token: csrf_token}, dataType: 'json',
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
function delete_confirm(){
  var hash = $("#hash").val();
  var csrf_token = $("#csrf_token").val();
  var confirmobj = layer.confirm('删除文件后不可恢复，确定删除吗？', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
    var ii = layer.load(2);
	  $.ajax({
      type : 'POST',
      url : 'ajax.php?act=deleteFile',
      data : {hash:hash, csrf_token:csrf_token},
      dataType : 'json',
      success : function(data) {
        layer.close(ii);
        if(data.code == 0){
          layer.alert('删除成功', {icon:1}, function(){window.location.href="./";});
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
$(document).ready(function(){
  var clipboard = new Clipboard('.copy-btn');
  clipboard.on('success', function (e) {
    layer.msg('复制成功', {icon: 1});
  });
  clipboard.on('error', function (e) {
    layer.msg('复制失败，请长按链接后手动复制', {icon: 2});
  });
})
</script>
</body>
</html>
