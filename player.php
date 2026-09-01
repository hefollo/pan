<?php
include("./includes/common.php");

$hash = isset($_GET['hash'])?trim($_GET['hash']):exit();
$pwd = isset($_GET['pwd'])?trim($_GET['pwd']):null;
$row = $DB->getRow("SELECT * FROM pre_file WHERE token=:token", [':token'=>$hash]);
if(!$row)exit('404 Not Found');
if($row['block']!=0)exit('File is blocked!');
//加密文件同样要过密码这一关：本页原来完全不校验，等于绕过 file.php 的密码框
if(!check_file_pwd($row, $pwd))exit('请输入正确的访问密码');
$name = $row['name'];
$type = $row['type'];
$viewurl_all = $siteurl.'view.php/'.$row['token'].'.'.$type;
//view.php 现在会校验密码，播放地址要把密码带上，否则加密文件的播放器拉不到内容
if(!empty($row['pwd']))$viewurl_all .= '&'.$row['pwd'];

$view_type = get_view_type($type);

if($view_type == 'audio'){
    $title = '音乐播放器 - '.$conf['title'];
}elseif($view_type == 'video'){
    $title = '视频播放器 - '.$conf['title'];
}else{
    exit('NO player');
}

@header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?php echo $title ?></title>
  <link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.css">
  <link href="./assets/css/ckplayer.css" rel="stylesheet">
  <script src="https://s4.zstatic.net/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<style type="text/css">
body{margin:0;}
</style>
</head>
<body>
<div id="preview" align="center">
<?php
if($view_type == 'audio'){
  echo '<div id="aplayer"></div>';
}elseif($view_type == 'video'){
  echo '<div class="videoplayer" style="width:100%"></div>';
}else{
  exit;
}
?>
</div>
<?php if($view_type == 'audio'){?>
<script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.js"></script>
<script type="text/javascript">
var ap = new APlayer({
  container: document.getElementById('aplayer'),
  loop: 'none',
  theme: '#b2dae6',
  audio: [{
      title: <?php echo json_encode($name, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?: '""'?>,
      author: 'none',
      url: '<?php echo $viewurl_all?>',
      cover: './assets/img/music.png',
  }]
});
</script>
<?php }elseif($view_type == 'video'){?>
<script type="text/javascript" src="./assets/js/ckplayer.min.js"></script>
<?php if($type=='m3u8'){$plug='hls.js';?><script src="https://s4.zstatic.net/ajax/libs/hls.js/1.2.4/hls.min.js"></script><?php }?>
<?php if($type=='flv'||$type=='f4v'){$plug='flv.js';?><script src="https://s4.zstatic.net/ajax/libs/flv.js/1.6.2/flv.min.js"></script><?php }?>
<script type="text/javascript">
  $(".videoplayer").height($(window).height());
  var videoObject = {
    container: '.videoplayer',
    plug:'<?php echo $plug?>',
    video:'<?php echo $viewurl_all?>',
    webFull:true,
  };
  var player=new ckplayer(videoObject);
</script>
<?php }?>
</body>
</html>