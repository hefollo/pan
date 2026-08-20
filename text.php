<?php
$nosession = true;
$nosecu = true;
include("./includes/common.php");

$hash = isset($_GET['hash']) ? trim($_GET['hash']) : '';
$pwd = isset($_GET['pwd']) ? trim($_GET['pwd']) : null;

if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){
	exit('404 Not Found');
}

$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `token`=:token limit 1", [':token'=>$hash]);
if(!$row)exit('404 Not Found');
if($row['block']>=1)exit('File is blocked!');
if(!is_editable_file_type($row['type']))exit('This file type cannot be viewed as text.');

if($row['pwd']!=null && $row['pwd']!=$pwd){ ?>
	<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
	<title>请输入密码查看文件</title>
	<script type="text/javascript">
	var pwd=prompt("请输入密码","")
	if (pwd!=null && pwd!="")
	{
		window.location.href="./text.php?hash=<?php echo $hash?>&pwd="+encodeURIComponent(pwd)
	}
	</script>
	请刷新页面，或[ <a href="javascript:history.back();">返回上一页</a> ]
<?php
	exit;
}

if(!$stor->exists($row['hash']))exit('File Not Found');

$content = get_storage_content($row['hash']);
if($content === false)exit('Read file failed.');
$decoded = decode_editable_content($content);
if($decoded['code'] != 0)exit($decoded['msg']);

$DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$row['id']}'");

@header('Content-Type: text/plain; charset=UTF-8');
@header('X-Content-Type-Options: nosniff');
echo $decoded['content'];
