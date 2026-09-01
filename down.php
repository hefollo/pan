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
if(!$row)exit('404 Not Found');
if($row['block']>=1)exit('File is blocked!');

if(!check_file_pwd($row, $pwd)){ ?>
    <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
    <title>请输入密码下载文件</title>
    <script type="text/javascript">
    var pwd=prompt("请输入密码","")
    if (pwd!=null && pwd!="")
    {
        window.location.href='<?php echo $siteurl.'down.php/'.$row['token']?>&'+encodeURIComponent(pwd)
    }
    </script>
    请刷新页面，或[ <a href="javascript:history.back();">返回上一页</a> ]
<?php
    exit;
}

if($stor->exists($row['hash']))
{
    $DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$row['id']}'");

    file_output($row['hash'], $row['type'], $row['size'], $row['name']);
}
else{
    exit('File Not Found');
}