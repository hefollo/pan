<?php
$nosession=true;
$nosecu=true;
include("./includes/common.php");

$url = '';
$pwd = null;
$urlarr=explode('/',isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'');
if (($length = count($urlarr)) > 1) {
$url = $urlarr[$length-1];
}
$extension=explode('&',$url);
if (($length = count($extension)) > 1) {
$pwd = $extension[$length-1];
$url = $extension[0];
}

if(strpos($url,".")){
    $token=substr($url,0,strpos($url,"."));
}else{
    $token=$url;
}

$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `token`=:token limit 1", [':token'=>$token]);
if(!$row) exit;
if($row['block']>=1){
    header("Content-type: ".minetype('gif'));
    readfile(ROOT.'assets/img/block.gif');
    exit;
}
//本页原来解析了密码却从来没校验过，等于给加密文件开了后门：
//把分享链接里的 down.php 换成 view.php 就能直接看到图片/音视频。
//密码不对时和被封禁一样只返回占位图，不泄露文件是否存在以外的任何信息
if(!check_file_pwd($row, $pwd)){
    header("Content-type: ".minetype('gif'));
    readfile(ROOT.'assets/img/block.gif');
    exit;
}

if ($stor->exists($row['hash'])) {
    if(is_view($row['type']))
    {
        //列表页右侧的预览面板会自动加载选中文件，带 preview 参数的请求不计入下载次数，
        //否则每打开一次列表就会给第一个文件刷一次计数
        if(!isset($_GET['preview'])){
            $DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$row['id']}'");
        }

        file_output($row['hash'], $row['type'], $row['size'], $row['name'], true, isset($_GET['greencheck']));
    }
}
