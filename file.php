<?php
include("./includes/common.php");

$title = '文件查看 - '.$conf['title'];
$is_file=true;
include SYSTEM_ROOT.'header.php';

$csrf_token = bin2hex(random_bytes(16));
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
//view.php 现在会校验密码了，预览地址和下载地址一样要把密码带上
if(!empty($row['pwd']))$viewurl .= '&'.$row['pwd'];
$playerurl = 'player.php?hash='.$row['token'];
if(!empty($row['pwd']))$playerurl .= '&pwd='.rawurlencode($row['pwd']);
$texturl = 'text.php?hash='.$row['token'];
if(!empty($pwd))$texturl .= '&pwd='.rawurlencode($pwd);

$downurl_all = $siteurl.$downurl;
$viewurl_all = $siteurl.$viewurl;
$texturl_all = $siteurl.$texturl;

$thisurl = $siteurl.'file.php?hash='.$row['token'];
if(!empty($pwd))$thisurl .= '&pwd='.$pwd;

$is_mine = can_manage_file($row);
//冻结、内容检测待人工复核的文件不给删，按钮直接不显示，后端 ajax.php 那边也照样拦
$delete_locked = file_delete_locked_reason($row);
//没有在线编辑权限但买得到时入口也显示，点进去会提示去购买
$is_editable = can_show_online_edit_entry($row);
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
  $htmlcode2 = htmlspecialchars('<iframe src="'.$siteurl.$playerurl.'" width="407" scrolling="no"frameborder="0"height="70"></iframe>');
  $ubbcode = '[audio=X]'.$viewurl_all.'[/audio]';
  $linktitle = '音乐链接';
}elseif($view_type == 'video'){
  $filetype = 3;
  $title = '<i class="fa fa-video-camera"></i> 视频播放器';
  $htmlcode = htmlspecialchars('<video id="movies" src="'.$viewurl_all.'" autobuffer="true" controls="" width="100
  %"></video>');
  $htmlcode2 = htmlspecialchars('<iframe src="'.$siteurl.$playerurl.'" width="800" height="500" scrolling="no" frameborder="0"></iframe>');
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
//设了访问密码又没给对：只弹个输入框，不出页面内容，也没有页脚（和以前一样）
if(!check_file_pwd($row, $pwd)){ ?>
<div class="container">
    <div class="row">
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
/*
 * 页面内容是布局 file（DEC-20261025-003）：系统默认在 includes/layouts/file.json，
 * 卡片在 includes/blocks/file/（预览、外链与管理、提示）和 common/（打赏码）。
 */
echo theme_render_layout('file', [
  'row' => $row,
  'hash' => $hash,
  'name' => $name,
  'type' => $type,
  'title' => $title,
  'filetype' => $filetype,
  'downurl' => $downurl,
  'viewurl' => $viewurl,
  'texturl' => $texturl,
  'downurl_all' => $downurl_all,
  'viewurl_all' => $viewurl_all,
  'texturl_all' => $texturl_all,
  'thisurl' => $thisurl,
  'office_url' => isset($office_url) ? $office_url : '',
  'htmlcode' => $htmlcode,
  'htmlcode2' => isset($htmlcode2) ? $htmlcode2 : '',
  'ubbcode' => $ubbcode,
  'linktitle' => isset($linktitle) ? $linktitle : '',
  'is_mine' => $is_mine,
  'is_editable' => $is_editable,
  'text_viewable' => $is_text_viewable,
  'delete_locked' => $delete_locked,
  'csrf_token' => $csrf_token,
], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
//卡片登记的页面末尾脚本（音频 / 视频播放器、复制链接、覆盖上传、删除）
echo theme_deferred_html();
?></body>
</html>
