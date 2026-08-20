<?php
function get_curl($url, $post=0, $referer=0, $cookie=0, $header=0, $ua=0, $nobaody=0)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$httpheader[] = "Accept: */*";
	$httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
	$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
	$httpheader[] = "Connection: close";
	curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
	if ($post) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	if ($header) {
		curl_setopt($ch, CURLOPT_HEADER, true);
	}
	if ($cookie) {
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	if($referer){
		curl_setopt($ch, CURLOPT_REFERER, $referer);
	}
	if ($ua) {
		curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	}
	else {
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
	}
	if ($nobaody) {
		curl_setopt($ch, CURLOPT_NOBODY, 1);
	}
	curl_setopt($ch, CURLOPT_ENCODING, "gzip");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$ret = curl_exec($ch);
	curl_close($ch);
	return $ret;
}
function real_ip($type=0){
$ip = $_SERVER['REMOTE_ADDR'];
if($type<=0 && isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
	foreach ($matches[0] AS $xip) {
		if (filter_var($xip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$ip = $xip;
			break;
		}
	}
} elseif ($type<=0 && isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
	$ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif ($type<=1 && isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif ($type<=1 && isset($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
	$ip = $_SERVER['HTTP_X_REAL_IP'];
}
return $ip;
}
function get_ip_city($ip)
{
	$url = 'http://whois.pconline.com.cn/ipJson.jsp?json=true&ip=';
	$city = get_curl($url . $ip);
	$city = mb_convert_encoding($city, "UTF-8", "GB2312");
	$city = json_decode($city, true);
	if ($city['city']) {
		$location = $city['pro'].$city['city'];
	} else {
		$location = $city['pro'];
	}
	if($location){
		return $location;
	}else{
		return false;
	}
}
function daddslashes($string) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = daddslashes($val);
		}
	} else {
		$string = addslashes($string);
	}
	return $string;
}

function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}

function dstrpos($string, $arr) {
	if(empty($string)) return false;
	foreach((array)$arr as $v) {
		if(strpos($string, $v) !== false) {
			return true;
		}
	}
	return false;
}

function checkmobile() {
	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
	$ualist = array('android', 'midp', 'nokia', 'mobile', 'iphone', 'ipod', 'blackberry', 'windows phone');
	if((dstrpos($useragent, $ualist) || strexists($_SERVER['HTTP_ACCEPT'], "VND.WAP") || strexists($_SERVER['HTTP_VIA'],"wap")))
		return true;
	else
		return false;
}
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key);
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);
	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);
	$result = '';
	$box = range(0, 255);
	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}
	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}
	if($operation == 'DECODE') {
		if(((int)substr($result, 0, 10) == 0 || (int)substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}
}

function random($length, $numeric = 0) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	$hash = '';
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed[mt_rand(0, $max)];
	}
	return $hash;
}
function showmsg($content = '未知的异常',$type = 4,$back = false)
{
switch($type)
{
case 1:
	$panel="success";
break;
case 2:
	$panel="info";
break;
case 3:
	$panel="warning";
break;
case 4:
	$panel="danger";
break;
}

echo '<div class="panel panel-'.$panel.'">
	  <div class="panel-heading">
		<h3 class="panel-title">提示信息</h3>
		</div>
		<div class="panel-body">';
echo $content;

if ($back) {
	echo '<hr/><a href="'.$back.'"><< 返回上一页</a>';
}
else
	echo '<hr/><a href="javascript:history.back(-1)"><< 返回上一页</a>';

echo '</div>
	</div>';
	exit;
}
function sysmsg($msg = '未知的异常',$title = '站点提示信息') {
	?>  
	<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" lang="zh-CN">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo $title?></title>
		<style type="text/css">
html{background:#eee}body{background:#fff;color:#333;font-family:"微软雅黑","Microsoft YaHei",sans-serif;margin:2em auto;padding:1em 2em;max-width:700px;-webkit-box-shadow:10px 10px 10px rgba(0,0,0,.13);box-shadow:10px 10px 10px rgba(0,0,0,.13);opacity:.8}h1{border-bottom:1px solid #dadada;clear:both;color:#666;font:24px "微软雅黑","Microsoft YaHei",sans-serif;margin:30px 0 0 0;padding:0;padding-bottom:7px}#error-page{margin-top:50px}h3{text-align:center}#error-page p{font-size:9px;line-height:1.5;margin:25px 0 20px}#error-page code{font-family:Consolas,Monaco,monospace}ul li{margin-bottom:10px;font-size:9px}a{color:#21759B;text-decoration:none;margin-top:-10px}a:hover{color:#D54E21}.button{background:#f7f7f7;border:1px solid #ccc;color:#555;display:inline-block;text-decoration:none;font-size:9px;line-height:26px;height:28px;margin:0;padding:0 10px 1px;cursor:pointer;-webkit-border-radius:3px;-webkit-appearance:none;border-radius:3px;white-space:nowrap;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;-webkit-box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);vertical-align:top}.button.button-large{height:29px;line-height:28px;padding:0 12px}.button:focus,.button:hover{background:#fafafa;border-color:#999;color:#222}.button:focus{-webkit-box-shadow:1px 1px 1px rgba(0,0,0,.2);box-shadow:1px 1px 1px rgba(0,0,0,.2)}.button:active{background:#eee;border-color:#999;color:#333;-webkit-box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5);box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5)}table{table-layout:auto;border:1px solid #333;empty-cells:show;border-collapse:collapse}th{padding:4px;border:1px solid #333;overflow:hidden;color:#333;background:#eee}td{padding:4px;border:1px solid #333;overflow:hidden;color:#333}
		</style>
	</head>
	<body id="error-page">
		<?php echo '<h3>'.$title.'</h3>';
		echo $msg; ?>
	</body>
	</html>
	<?php
	exit;
}

if(!function_exists("is_https")){
	function is_https() {
		if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443){
			return true;
		}elseif(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')){
			return true;
		}elseif(isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){
			return true;
		}elseif(isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_EWS_CUSTOME_SCHEME']) && $_SERVER['HTTP_EWS_CUSTOME_SCHEME'] == 'https'){
			return true;
		}
		return false;
	}
}

function checkRefererHost(){
	if(!$_SERVER['HTTP_REFERER'])return false;
	$url_arr = parse_url($_SERVER['HTTP_REFERER']);
	$http_host = $_SERVER['HTTP_HOST'];
	if(strpos($http_host,':'))$http_host = substr($http_host, 0, strpos($http_host, ':'));
	return $url_arr['host'] === $http_host;
}

function checkIfActive($string) {
	$array=explode(',',$string);
	$php_self=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],'/')+1,strrpos($_SERVER['REQUEST_URI'],'.')-strrpos($_SERVER['REQUEST_URI'],'/')-1);
	if (in_array($php_self,$array)){
		return 'active';
	}elseif (isset($_GET['m']) && in_array($_GET['m'],$array)){
		return 'active';
	}else
		return null;
}

function getAllSetting() {
	global $DB;
	$conf = array();
	$result = $DB->getAll("SELECT * FROM pre_config");
	foreach($result as $row){
		if($row['k']=='cache') continue;
		$conf[ $row['k'] ] = $row['v'];
	}
	return $conf;
}
function getSetting($k){
	global $DB;
	return $DB->getColumn("SELECT v FROM pre_config WHERE k=:k LIMIT 1", [':k'=>$k]);
}
function saveSetting($k, $v){
	global $DB;
	return $DB->exec("REPLACE INTO pre_config SET v=:v,k=:k", [':v'=>$v, ':k'=>$k]);
}

function size_format($size)
{
	if ($size<1024) {
		$size.=' B';
	} else {
		$size/=1024;
		if ($size<1024) {
			$size=round($size, 2).' KB';
		} else {
			$size/=1024;
			if ($size<1024) {
				$size=round($size, 2).' MB';
			} else {
				$size/=1024;
				if ($size<1024) {
					$size=round($size, 2).' GB';
				}
			}
		}
	}
	return $size;
}

function is_user_permission_active($row = null){
	global $islogin2, $userrow;
	if($row === null) $row = $userrow;
	if(empty($islogin2) || empty($row)) return false;
	if(empty($row['expiretime'])) return true;
	return strtotime($row['expiretime']) > time();
}

function get_effective_upload_size_limit(){
	global $conf, $islogin2, $userrow;
	if(!empty($islogin2) && is_user_permission_active() && isset($userrow['upload_size']) && $userrow['upload_size'] !== null && intval($userrow['upload_size']) >= 0){
		return intval($userrow['upload_size']);
	}
	return isset($conf['upload_size']) ? intval($conf['upload_size']) : 0;
}

function get_effective_upload_count_limit(){
	global $conf, $islogin2, $userrow;
	if(!empty($islogin2) && is_user_permission_active() && isset($userrow['upload_limit']) && $userrow['upload_limit'] !== null && intval($userrow['upload_limit']) >= 0){
		return intval($userrow['upload_limit']);
	}
	if(!empty($islogin2) && is_user_permission_active() && isset($userrow['level']) && intval($userrow['level']) > 0){
		return 0;
	}
	return isset($conf['upload_limit']) ? intval($conf['upload_limit']) : 0;
}

function minetype($type){
	$mime = array (
	//applications
	'ai'  => 'application/postscript',
	'eps'  => 'application/postscript',
	'exe'  => 'application/octet-stream',
	'doc'  => 'application/vnd.ms-word',
	'xls'  => 'application/vnd.ms-excel',
	'ppt'  => 'application/vnd.ms-powerpoint',
	'pps'  => 'application/vnd.ms-powerpoint',
	'pdf'  => 'application/pdf',
	'xml'  => 'application/xml',
	'odt'  => 'application/vnd.oasis.opendocument.text',
	'swf'  => 'application/x-shockwave-flash',
	// archives
	'gz'  => 'application/x-gzip',
	'tgz'  => 'application/x-gzip',
	'bz'  => 'application/x-bzip2',
	'bz2'  => 'application/x-bzip2',
	'tbz'  => 'application/x-bzip2',
	'zip'  => 'application/zip',
	'rar'  => 'application/x-rar',
	'tar'  => 'application/x-tar',
	'7z'  => 'application/x-7z-compressed',
	// texts
	'txt'  => 'text/plain',
	'php'  => 'text/x-php',
	'html' => 'text/html',
	'htm'  => 'text/html',
	'js'  => 'text/javascript',
	'css'  => 'text/css',
	'rtf'  => 'text/rtf',
	'rtfd' => 'text/rtfd',
	'py'  => 'text/x-python',
	'java' => 'text/x-java-source',
	'rb'  => 'text/x-ruby',
	'sh'  => 'text/x-shellscript',
	'pl'  => 'text/x-perl',
	'sql'  => 'text/x-sql',
	// images
	'bmp'  => 'image/x-ms-bmp',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'gif'  => 'image/gif',
	'png'  => 'image/png',
	'tif'  => 'image/tiff',
	'tiff' => 'image/tiff',
	'tga'  => 'image/x-targa',
	'ico'  => 'image/x-icon',
	'svg'  => 'image/x-svgz',
	'svgz'  => 'image/x-svgz',
	'webp'  => 'image/webp',
	'psd'  => 'image/vnd.adobe.photoshop',
	'heic' => 'image/x-heic',
	'exif' => 'image/jpeg',
	//audio
	'mp3'  => 'audio/mpeg',
	'mid'  => 'audio/midi',
	'ogg'  => 'audio/ogg',
	'mp4a' => 'audio/mp4',
	'm4a' => 'audio/m4a',
	'wav'  => 'audio/wav',
	'wma'  => 'audio/x-ms-wma',
	// video
	'avi'  => 'video/x-msvideo',
	'dv'  => 'video/x-dv',
	'mp4'  => 'video/mp4',
	'f4v'  => 'video/x-flv',
	'mpeg' => 'video/mpeg',
	'mpg'  => 'video/mpeg',
	'mov'  => 'video/quicktime',
	'wmv'  => 'video/x-ms-wmv',
	'flv'  => 'video/x-flv',
	'mkv'  => 'video/x-matroska',
	'ts'  => 'video/x-flv',
	'3gp'  => 'video/3gpp',
	'3gpp'  => 'video/3gpp',
	'webm'  => 'video/webm',
	);
	return isset($mime[$type]) ? $mime[$type] : 'application/octet-stream';
}

function type_to_icon($type){
	global $conf;
	$type_image = explode('|',$conf['type_image']);
	$type_audio = explode('|',$conf['type_audio']);
	$type_video = explode('|',$conf['type_video']);
	$type_image = array_merge($type_image, ['png','jpg','jpeg','gif','bmp','webp','ico','svg','svgz','tif','tiff','heic','psd','exif','pcx','tga','fpx','cdr','pcd','eps','ai','wmf','raw','ufo','jpc','jp2','jpx','xbm','wbmp','avif']);
	$type_audio = array_merge($type_audio, ['mp3','wav','wma','ogg','m4a','flac','ape','aac','ra','cda','midi','mid','aif','au','voc']);
	$type_video = array_merge($type_video, ['mp4','webm','flv','f4v','mov','3gp','3gpp','avi','mpg','mpeg','wmv','mkv','ts','dat','asf','rm','rmvb','ram','divx','vob','qt','fli','flc','mod','m2t','swf','mts','m2ts','mpe','div','lavf','m3u8','m4v','ogm','ogv']);
	$type_text = ['txt','text','log','md','yaml','yml','conf','config','ini'];
	$type_code = ['c','cpp','cxx','rc','php','py','cs','h','htm','html','css','less','js','hdml','dtd','wml','xml','vbs','vb','rtx','xsd','dpr','sql','java','go','jsp','asp','aspx','asa','asax','pl','bat','cmd','rb','reg','sh','json','lua','r','mm','mak','swift','tpl'];
	$type_archive = ['zip','7z','rar','tgz','gz','xz','tar','jar','iso','z','zipx','cab','bz2','arj','lz','lzh'];
	$type_word = ['doc','docx','xps','rtf','wps','odt'];
	$type_excel = ['xls','xlsx','ods'];
	$type_pdf = ['pdf'];
	$type_powerpoint = ['ppt','pptx','pptm'];
	$type_android = ['apk'];
	$type_apple = ['ipa','dmg'];
	$type_windows = ['exe','appx','msi'];
	$type_linux = ['deb','rpm'];
	if(in_array($type, $type_image)){
		return 'fa-file-image-o';
	}elseif(in_array($type, $type_audio)){
		return 'fa-file-audio-o';
	}elseif(in_array($type, $type_video)){
		return 'fa-file-video-o';
	}elseif(in_array($type, $type_text)){
		return 'fa-file-text-o';
	}elseif(in_array($type, $type_code)){
		return 'fa-file-code-o';
	}elseif(in_array($type, $type_archive)){
		return 'fa-file-archive-o';
	}elseif(in_array($type, $type_word)){
		return 'fa-file-word-o';
	}elseif(in_array($type, $type_excel)){
		return 'fa-file-excel-o';
	}elseif(in_array($type, $type_pdf)){
		return 'fa-file-pdf-o';
	}elseif(in_array($type, $type_powerpoint)){
		return 'fa-file-powerpoint-o';
	}elseif(in_array($type, $type_android)){
		return 'fa-android';
	}elseif(in_array($type, $type_apple)){
		return 'fa-apple';
	}elseif(in_array($type, $type_windows)){
		return 'fa-windows';
	}elseif(in_array($type, $type_linux)){
		return 'fa-linux';
	}else{
		return 'fa-file-o';
	}
}

function is_view($type){
	global $conf;
	$type_image = explode('|',$conf['type_image']);
	$type_audio = explode('|',$conf['type_audio']);
	$type_video = explode('|',$conf['type_video']);
	if (in_array($type, $type_image) || in_array($type, $type_audio) || in_array($type, $type_video)) {
		return true;
	}
	return false;
}

function get_view_type($type){
	global $conf;
	$type_image = explode('|',$conf['type_image']);
	$type_audio = explode('|',$conf['type_audio']);
	$type_video = explode('|',$conf['type_video']);
	$type_office = ['doc','docx','xps','rtf','wps','xls','xlsx','ppt','pptx'];
	if (in_array($type, $type_image)) {
		return 'image';
	}elseif (in_array($type, $type_audio)) {
		return 'audio';
	}elseif (in_array($type, $type_video)) {
		return 'video';
	}elseif (in_array($type, $type_office)) {
		return 'office';
	}
	return false;
}

function get_editable_file_types(){
	return ['txt','text','log','md','markdown','json','js','mjs','css','less','scss','html','htm','xml','svg','csv','ini','conf','config','yaml','yml','sql','vue','ts','tsx','jsx'];
}

function is_editable_file_type($type){
	$type = strtolower(trim((string)$type));
	return $type !== '' && in_array($type, get_editable_file_types(), true);
}

function get_online_edit_mode(){
	global $conf;
	$mode = isset($conf['online_edit_mode']) ? strtolower(trim((string)$conf['online_edit_mode'])) : 'all';
	return in_array($mode, ['all', 'login', 'uid'], true) ? $mode : 'all';
}

function get_online_edit_uid_whitelist(){
	global $conf;
	$value = isset($conf['online_edit_uids']) ? trim((string)$conf['online_edit_uids']) : '';
	if($value === '') return [];
	$value = str_replace(['，', '|'], [',', ','], $value);
	$items = preg_split('/[\s,]+/', $value);
	$uids = [];
	foreach((array)$items as $item){
		$item = trim($item);
		if($item === '' || !ctype_digit($item)) continue;
		$uids[] = intval($item);
	}
	return array_values(array_unique($uids));
}

function can_use_online_edit(){
	global $islogin2, $uid;
	$mode = get_online_edit_mode();
	if($mode === 'all'){
		return true;
	}
	if(empty($islogin2)){
		return false;
	}
	if($mode === 'login'){
		return true;
	}
	return in_array(intval($uid), get_online_edit_uid_whitelist(), true);
}

function can_edit_file_online($row){
	if(!$row) return false;
	if(!is_editable_file_type(isset($row['type']) ? $row['type'] : null)) return false;
	if(!can_manage_file($row)) return false;
	return can_use_online_edit();
}

function get_editable_file_max_size(){
	return 2 * 1024 * 1024;
}

function can_manage_file($row){
	global $islogin2, $uid;
	if(!$row)return false;
	if(!empty($islogin2)){
		return intval($row['uid']) === intval($uid);
	}
	return isset($_SESSION['fileids']) && in_array($row['id'], $_SESSION['fileids']) && strtotime($row['addtime']) > strtotime("-7 days");
}

function storage_content_to_string($content){
	if($content === false)return false;
	if(is_resource($content)){
		return stream_get_contents($content);
	}
	if(is_object($content)){
		if(method_exists($content, 'getContents')){
			return $content->getContents();
		}
		if(method_exists($content, '__toString')){
			return (string)$content;
		}
	}
	return (string)$content;
}

function get_storage_content($hash){
	global $stor;
	return storage_content_to_string($stor->get($hash));
}

function is_utf8_editable_content($content){
	if(strpos($content, "\0") !== false)return false;
	if(function_exists('mb_check_encoding')){
		return mb_check_encoding($content, 'UTF-8');
	}
	return preg_match('//u', $content) === 1;
}

function convert_content_to_utf8($content, $encoding){
	if(strtoupper($encoding) === 'UTF-8')return $content;
	if(function_exists('mb_convert_encoding')){
		try {
			$result = @mb_convert_encoding($content, 'UTF-8', $encoding);
		} catch(\Throwable $e) {
			$result = false;
		}
		return $result === false ? false : $result;
	}
	if(function_exists('iconv')){
		$result = @iconv($encoding, 'UTF-8', $content);
		return $result === false ? false : $result;
	}
	return false;
}

function is_content_encoding($content, $encoding){
	if(function_exists('mb_check_encoding')){
		try {
			return @mb_check_encoding($content, $encoding);
		} catch(\Throwable $e) {
			return false;
		}
	}
	if(function_exists('iconv')){
		$result = @iconv($encoding, 'UTF-8', $content);
		return $result !== false;
	}
	return false;
}

function decode_editable_content($content){
	if(strpos($content, "\0") !== false){
		return ['code'=>-1, 'msg'=>'该文件像是二进制文件，不能在线编辑'];
	}
	if(is_utf8_editable_content($content)){
		return ['code'=>0, 'content'=>$content, 'encoding'=>'UTF-8', 'converted'=>false];
	}

	$encodings = ['GB18030', 'GBK', 'GB2312', 'BIG5', 'BIG5-HKSCS'];
	foreach($encodings as $encoding){
		if(!is_content_encoding($content, $encoding))continue;
		$converted = convert_content_to_utf8($content, $encoding);
		if($converted !== false && is_utf8_editable_content($converted)){
			return ['code'=>0, 'content'=>$converted, 'encoding'=>$encoding, 'converted'=>true];
		}
	}

	return ['code'=>-1, 'msg'=>'无法识别文件编码，请先转换为 UTF-8 后再编辑'];
}

function save_storage_content($hash, $content, $type){
	global $stor;
	$tmpfile = tempnam(sys_get_temp_dir(), 'edit_');
	if($tmpfile === false)return false;
	if(file_put_contents($tmpfile, $content) === false){
		@unlink($tmpfile);
		return false;
	}
	$result = $stor->savefile($hash, $tmpfile, minetype($type));
	if(file_exists($tmpfile)){
		@unlink($tmpfile);
	}
	return $result;
}


function checkImage($hash, $ext){
	global $conf,$siteurl;
	$apiurl = $conf['apiurl']?$conf['apiurl']:$siteurl;
	$fileurl = $apiurl.'view.php/'.$hash.'.'.$ext.'?greencheck=1';
	if($conf['green_check'] == 1){
		return checkImage_aliyun($fileurl);
	}elseif($conf['green_check'] == 2){
		return checkImage_qcloud($fileurl);
	}
	return false;
}
function checkImage_aliyun($fileurl){
	global $conf;
	$scenes = [];
	if ($conf['green_check_porn']==1) {
		$scenes[] = 'porn';
		$label_porn = explode(',', $conf['green_label_porn']);
	}
	if ($conf['green_check_terrorism']==1) {
		$scenes[] = 'terrorism';
		$label_terrorism = explode(',', $conf['green_label_terrorism']);
	}
	if(count($scenes)==0)return false;

	$client = new \lib\AliyunGreen($conf['aliyun_ak'], $conf['aliyun_sk'], $conf['green_check_region']);
	$task1 = array('dataId' => uniqid(), 'url' => $fileurl);
	$request = array("tasks" => array($task1), "scenes" => $scenes);

try {
	$response = $client->doCheck($request);
	//print_r($response);
	if(200 == $response->code){
		$taskResults = $response->data;
		foreach ($taskResults as $taskResult) {
			if(200 == $taskResult->code){
				$sceneResults = $taskResult->results;
				foreach ($sceneResults as $sceneResult) {
					$scene = $sceneResult->scene;
					$label = $sceneResult->label;
					$suggestion = $sceneResult->suggestion;
					if($scene == 'porn' && (in_array($label, $label_porn) || $suggestion == 'block')){
						return true;
					}elseif($scene == 'terrorism' && (in_array($label, $label_terrorism) || $suggestion == 'block')){
						return true;
					}
				}
			}else{
				writeLog("task process fail:" . $taskResult->code . ' ' . $taskResult->msg);
			}
		}
	}else{
		writeLog("detect not success. code:" . $response->code . ' ' . $response->msg);
	}
} catch (Exception $e) {
	print_r($e);
}
return false;
}
function checkImage_qcloud($fileurl){
	global $conf;
	$client = new \lib\QcloudGreen($conf['qcloud_green_id'], $conf['qcloud_green_key'], $conf['green_check_region']);
	$result = $client->ImageModeration($fileurl);
	if(isset($result['Suggestion'])){
		if($result['Suggestion'] == 'Block'){
			return true;
		}
	}else{
		writeLog('detect not success.['.$result['Error']['Code'].']'.$result['Error']['Message']);
	}
	return false;
}
function writeLog($text) {
	file_put_contents ( SYSTEM_ROOT."log.txt", date ( "Y-m-d H:i:s" ) . "  " . $text . "\r\n", FILE_APPEND );
}

/**
 * 后台登录限速
 * 原来只有 $_SESSION['pass_error'] 一个计数器，攻击者不带Cookie请求就能重置，等于没有限制。
 * 这里把失败次数按IP记在服务端文件里，跟客户端会话无关。
 */
function login_throttle_file(){
	return sys_get_temp_dir().'/pan_loginfail_'.substr(md5(SYS_KEY.'|loginthrottle'), 0, 16).'.json';
}

function login_throttle_load(){
	$file = login_throttle_file();
	if(!is_file($file))return [];
	$raw = @file_get_contents($file);
	if($raw === false || $raw === '')return [];
	$data = json_decode($raw, true);
	return is_array($data) ? $data : [];
}

function login_throttle_save($data){
	@file_put_contents(login_throttle_file(), json_encode($data), LOCK_EX);
}

//返回还需要锁定的秒数，0表示可以继续尝试
function login_throttle_locked($ip, $max = 5, $lock = 900){
	$key = md5((string)$ip);
	$data = login_throttle_load();
	if(!isset($data[$key]) || !is_array($data[$key]))return 0;
	$count = isset($data[$key]['count']) ? intval($data[$key]['count']) : 0;
	$last = isset($data[$key]['last']) ? intval($data[$key]['last']) : 0;
	if($count < $max)return 0;
	$remain = $last + $lock - time();
	return $remain > 0 ? $remain : 0;
}

function login_throttle_fail($ip, $lock = 900){
	$key = md5((string)$ip);
	$now = time();
	$data = login_throttle_load();
	//顺手清掉已过期的记录：文件不会无限增长，锁定期满的IP计数也自然归零
	foreach($data as $k => $v){
		if(!is_array($v) || (isset($v['last']) && intval($v['last']) + $lock < $now))unset($data[$k]);
	}
	$count = isset($data[$key]['count']) ? intval($data[$key]['count']) : 0;
	$data[$key] = ['count' => $count + 1, 'last' => $now];
	login_throttle_save($data);
}

function login_throttle_reset($ip){
	$key = md5((string)$ip);
	$data = login_throttle_load();
	if(isset($data[$key])){
		unset($data[$key]);
		login_throttle_save($data);
	}
}

function generate_file_token(){
	global $DB;
	do{
		$token = bin2hex(random_bytes(16));
	}while($DB->getColumn("SELECT id FROM pre_file WHERE token=:token", [':token'=>$token]));
	return $token;
}

//仅当没有其它记录仍引用该hash对应的物理文件时才删除，避免误删被去重共享的存储文件
function delete_file_blob_if_orphaned($hash, $exclude_id = null){
	global $DB, $stor;
	$params = [':hash'=>$hash];
	$sql = "SELECT id FROM pre_file WHERE hash=:hash";
	if($exclude_id){
		$sql .= " AND id!=:id";
		$params[':id'] = $exclude_id;
	}
	if($DB->getColumn($sql." LIMIT 1", $params)){
		return;
	}
	$stor->delete($hash);
}

//覆盖上传审计：记下这次覆盖的前后内容，管理员在后台“覆盖记录”里复查有没有换成违规内容
function add_replace_log($old, $new, $uid, $ip, $source = 'replace'){
	global $DB;
	if(!is_array($old) || empty($old['id']))return false;
	$data = [
		':file_id' => intval($old['id']),
		':token' => isset($old['token']) ? $old['token'] : '',
		':old_name' => isset($old['name']) ? $old['name'] : '',
		':old_type' => isset($old['type']) ? $old['type'] : '',
		':old_size' => isset($old['size']) ? intval($old['size']) : 0,
		':old_hash' => isset($old['hash']) ? $old['hash'] : '',
		':new_name' => isset($new['name']) ? $new['name'] : '',
		':new_type' => isset($new['type']) ? $new['type'] : '',
		':new_size' => isset($new['size']) ? intval($new['size']) : 0,
		':new_hash' => isset($new['hash']) ? $new['hash'] : '',
		':uid' => intval($uid),
		':ip' => $ip,
		':source' => $source,
	];
	return $DB->exec("INSERT INTO `pre_replace_log` (`file_id`,`token`,`old_name`,`old_type`,`old_size`,`old_hash`,`new_name`,`new_type`,`new_size`,`new_hash`,`uid`,`ip`,`source`,`checked`,`addtime`) VALUES (:file_id,:token,:old_name,:old_type,:old_size,:old_hash,:new_name,:new_type,:new_size,:new_hash,:uid,:ip,:source,0,NOW())", $data) !== false;
}

//覆盖上传：把已有记录的内容换成新文件，token（也就是对外链接）保持不变。
//换了内容必须重新过审并留审计记录，否则可以先传一个正常文件、事后覆盖成违规内容绕过检测。
function replace_file_record($old, $name, $hash, $size, $ext, $uid, $ip, $source = 'replace'){
	global $DB, $conf;
	if(!is_array($old) || empty($old['id']))return false;
	$id = intval($old['id']);
	$old_hash = isset($old['hash']) ? $old['hash'] : '';
	$ok = $DB->exec("UPDATE `pre_file` SET `name`=:name,`type`=:type,`size`=:size,`hash`=:hash,`block`=0,`lasttime`=NOW() WHERE `id`=:id LIMIT 1", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':id'=>$id]);
	if($ok === false)return false;

	//旧内容如果没有别的记录再引用，把物理文件清掉，别留垃圾
	if($old_hash !== '' && $old_hash !== $hash)delete_file_blob_if_orphaned($old_hash, $id);

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`=:id LIMIT 1", [':id'=>$id]);
			add_violation_log(['id'=>$id, 'name'=>$name, 'type'=>$ext, 'size'=>$size, 'hash'=>$hash, 'ip'=>$ip, 'uid'=>$uid], 'green', '覆盖上传后图片自动检测命中', 0);
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`=:id LIMIT 1", [':id'=>$id]);
	}

	add_replace_log($old, ['name'=>$name, 'type'=>$ext, 'size'=>$size, 'hash'=>$hash], $uid, $ip, $source);
	return true;
}

//秒传：内容已经在存储里了，物理文件不用再传，但仍要为这次上传建一条独立记录，
//否则上传者在“我的文件”里看不到，文件只挂在最早那个上传者名下。
//另外已被封禁的内容要继承封禁状态，不然换个人重传一遍就能绕过封禁。
function create_file_record_from_existing($existing, $name, $size, $ext, $hide, $pwd, $uid, $ip){
	global $DB;
	$record = create_file_record($name, $existing['hash'], $size, $ext, $hide, $pwd, $uid, $ip, false);
	if(!$record)return false;
	$block = isset($existing['block']) ? intval($existing['block']) : 0;
	if($block >= 1){
		$DB->exec("UPDATE `pre_file` SET `block`=:block WHERE `id`=:id LIMIT 1", [':block'=>$block, ':id'=>$record['id']]);
	}
	return $record;
}

//违规公示留档：文件被封禁时记一份快照，原文件记录被删掉后公示仍然保留
function add_violation_log($file, $source = 'admin', $remark = null, $is_show = 1){
	global $DB;
	if(!is_array($file) || empty($file['id']))return false;
	$data = [
		':file_id' => intval($file['id']),
		':name' => isset($file['name']) ? $file['name'] : '',
		':type' => isset($file['type']) ? $file['type'] : '',
		':size' => isset($file['size']) ? intval($file['size']) : 0,
		':hash' => isset($file['hash']) ? $file['hash'] : '',
		':ip' => isset($file['ip']) ? $file['ip'] : '',
		':uid' => isset($file['uid']) ? intval($file['uid']) : 0,
		':source' => $source,
		':remark' => $remark,
		':is_show' => intval($is_show) == 1 ? 1 : 0,
	];
	//同一个文件重复封禁时只更新原记录，公示页不会出现重复条目
	$exists = $DB->getColumn("SELECT `id` FROM pre_violation WHERE `file_id`=:file_id LIMIT 1", [':file_id'=>$data[':file_id']]);
	if($exists){
		unset($data[':file_id']);
		$data[':id'] = $exists;
		return $DB->exec("UPDATE `pre_violation` SET `name`=:name,`type`=:type,`size`=:size,`hash`=:hash,`ip`=:ip,`uid`=:uid,`source`=:source,`remark`=:remark,`is_show`=:is_show WHERE `id`=:id", $data) !== false;
	}
	return $DB->exec("INSERT INTO `pre_violation` (`file_id`,`name`,`type`,`size`,`hash`,`ip`,`uid`,`source`,`remark`,`is_show`,`addtime`) VALUES (:file_id,:name,:type,:size,:hash,:ip,:uid,:source,:remark,:is_show,NOW())", $data) !== false;
}

//文件解封说明是误判，撤下公示但保留记录，方便后台复查
function revoke_violation_log($file_id){
	global $DB;
	$file_id = intval($file_id);
	if(!$file_id)return false;
	return $DB->exec("UPDATE `pre_violation` SET `is_show`=0 WHERE `file_id`=:file_id", [':file_id'=>$file_id]) !== false;
}

//公示页脱敏：文件名只保留首尾若干字符和扩展名
function violation_mask_name($name){
	$name = (string)$name;
	if($name === '')return '未命名文件';
	$ext = '';
	$pos = strrpos($name, '.');
	if($pos !== false && $pos > 0){
		$ext = substr($name, $pos);
		$name = substr($name, 0, $pos);
	}
	$len = mb_strlen($name, 'UTF-8');
	if($len <= 1){
		$base = '*';
	}elseif($len <= 4){
		$base = mb_substr($name, 0, 1, 'UTF-8').'***';
	}else{
		$base = mb_substr($name, 0, 2, 'UTF-8').'***'.mb_substr($name, -2, 2, 'UTF-8');
	}
	return $base.$ext;
}

//公示页脱敏：IP只保留前两段
function violation_mask_ip($ip){
	$ip = (string)$ip;
	if($ip === '')return '未知';
	if(strpos($ip, ':') !== false){
		$parts = explode(':', $ip);
		return $parts[0].':'.(isset($parts[1]) ? $parts[1] : '').':*:*';
	}
	$parts = explode('.', $ip);
	if(count($parts) == 4)return $parts[0].'.'.$parts[1].'.*.*';
	return '***';
}

//插入一条新的文件记录（每次上传都会生成独立的记录和链接，即使内容与已有文件相同也不会互相覆盖）
//$review 传 false 用于秒传：内容此前已经审核过，审核状态由调用方继承，不必再花一次云端检测的钱和时间
function create_file_record($name, $hash, $size, $ext, $hide, $pwd, $uid, $ip, $review = true){
	global $DB, $conf;
	$token = generate_file_token();
	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`token`,`addtime`,`ip`,`hide`,`pwd`,`uid`) values (:name,:type,:size,:hash,:token,NOW(),:ip,:hide,:pwd,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':token'=>$token, ':ip'=>$ip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($uid?$uid:0)]);
	if(!$sds)return false;
	$id = $DB->lastInsertId();
	if(!$review)return ['id'=>$id, 'token'=>$token];

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
			//机器判定可能误伤，先留档但不公示，等后台在违规公示管理里确认后再放出
			add_violation_log(['id'=>$id, 'name'=>$name, 'type'=>$ext, 'size'=>$size, 'hash'=>$hash, 'ip'=>$ip, 'uid'=>($uid?$uid:0)], 'green', '图片自动检测命中', 0);
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}

	return ['id'=>$id, 'token'=>$token];
}

function get_file_ext($name){
	$extension=explode('.',$name);
	if (($length = count($extension)) > 1) {
		$ext = strtolower($extension[$length - 1]);
	}
	if(strlen($ext)>6)$ext='';
	return $ext;
}

function file_part_merge($hash, $chunks){
	$tmp_dir = sys_get_temp_dir();
	$savePathTemp = $tmp_dir . '/' . $hash. '.parttmp';
	$tempFilePre = $tmp_dir . '/' . $hash. '.part';
	if(file_exists($savePathTemp)){
		unlink($savePathTemp);
	}
	if(!$out = fopen($savePathTemp, "wb")){
		exit('{"code":-1,"msg":"文件合并失败，临时文件夹无写入权限"}');
	}
	for( $index = 1; $index <= $chunks; $index++ ) {
		$chunk_file = $tempFilePre.$index;
		if (!$fp_in = @fopen($chunk_file,"rb")){
			fclose($out);
			unlink($savePathTemp);
			exit('{"code":-1,"msg":"文件合并失败，第'.$index.'分块读取失败"}');
		}
		while (!feof($fp_in)) {
			fwrite($out, fread($fp_in,1024*200));
		}
		fclose($fp_in);
		unlink($chunk_file);
	}
	fclose($out);
	return $savePathTemp;
}

function get_file_range($size){
	if(isset($_SERVER['HTTP_RANGE']) && !empty($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d+)-(\d*)$/i', $_SERVER['HTTP_RANGE'], $match)){
		$start = intval($match[1]);
		$end = intval($match[2]);
		if($start < 0) $start = 0;
		if($end == 0) $end = $size - 1;
		if($end >= $size) $end = $size - 1;
		if($end < $start || $start >= $size) return false;
		return [$start, $end];
	}
	return false;
}

function file_output($hash, $type, $size, $name, $is_view = false, $is_admin = false){
	global $conf, $stor;

	@set_time_limit(0);
	$size = intval($size);
	if($is_admin){
		header("Pragma: no-cache");
    	header("Cache-Control: no-store, no-cache, must-revalidate");
	}else{
		//链接可能通过“替换文件”指向新内容，不能无条件长期缓存，改为用hash做校验的强制revalidate，
		//这样同一个文件重复访问依然能省流量（304），但替换后能立刻拿到新内容
		$etag = '"'.$hash.'"';
		header("ETag: $etag");
		header("Cache-Control: no-cache");
		$client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
		if($client_etag !== '' && $client_etag === $etag){
			header("HTTP/1.1 304 Not Modified");
			exit;
		}
	}

	$filename = '"'.$name.'"; filename*=utf-8\'\''.rawurlencode($name);

	if(\lib\StorHelper::is_cloud() && $conf['downfile_type'] == 1){
		$redirect = $stor->getDownUrl($hash, $name, $is_view ? minetype($type) : null);
		if($redirect){
			header("Location: ".$redirect);
		}else{
			ob_clean();
			exit('Error:'.$stor->errmsg());
		}
	}else{
		if($is_view){
			header("Content-Type: ".minetype($type));
			header("Content-Disposition: inline; filename={$filename}");
		}else{
			header("Content-Description: File Transfer");
        	header("Content-Type: application/force-download");
        	header("Content-Disposition: attachment; filename={$filename}");
		}

		$range = false;
		if(\lib\StorHelper::is_range()){
			header("Accept-Ranges: bytes");
			$range = get_file_range($size);
			//视频等媒体常用Range分段拖动播放；若客户端带着旧版本的If-Range校验值，
			//说明它手里缓存的是替换前的分段，必须忽略Range改发完整最新内容，否则会把新旧文件的片段拼在一起播放
			if($range && !$is_admin && isset($_SERVER['HTTP_IF_RANGE']) && trim($_SERVER['HTTP_IF_RANGE']) !== $etag){
				$range = false;
			}
		}

		if($range){
			header("HTTP/1.1 206 Partial Content");
			header("Content-Length: ".($range[1] - $range[0] + 1));
			header("Content-Range: bytes {$range[0]}-{$range[1]}/{$size}");
			$stor->downfile($hash, $range);
		}else{
			header("Content-Length: {$size}");
			$stor->downfile($hash, $conf['storage']=='local'?[0, $size-1]:false);
		}
	}
}
