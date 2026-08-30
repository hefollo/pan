<?php
$nosession=true;
$nosecu=true;
include("./includes/common.php");

$urlarr=explode('/',$_SERVER['PATH_INFO']);
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
