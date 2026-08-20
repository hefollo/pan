<?php
/**
 * 后台登录验证码
 * login.php 用 file_exists('code.php') 判断是否启用验证码，缺了这个文件验证码就形同虚设
 */
session_start();

@header('Content-Type: image/png');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
@header('Pragma: no-cache');

if(!function_exists('imagecreate')){
	//没装GD就不出图，login.php 那边会自动跳过验证码，改由IP限速兜底
	@header('Content-Type: text/plain; charset=UTF-8');
	exit('GD not available');
}

//去掉 0/1/l/i/o 之类容易看错的字符
$chars = '23456789abcdefghjkmnpqrstuvwxyz';
$max = strlen($chars) - 1;
$code = '';
for($i = 0; $i < 4; $i++){
	$code .= $chars[random_int(0, $max)];
}
//login.php 比较的是 strtolower($_POST['code'])，这里存小写
$_SESSION['vc_code'] = $code;

$width = 120;
$height = 40;
$img = imagecreatetruecolor($width, $height);

$bg = imagecolorallocate($img, 245, 247, 250);
imagefilledrectangle($img, 0, 0, $width, $height, $bg);

//干扰点
for($i = 0; $i < 320; $i++){
	$dot = imagecolorallocate($img, random_int(180, 230), random_int(180, 230), random_int(180, 230));
	imagesetpixel($img, random_int(0, $width - 1), random_int(0, $height - 1), $dot);
}
//干扰线
for($i = 0; $i < 4; $i++){
	$line = imagecolorallocate($img, random_int(140, 200), random_int(140, 200), random_int(140, 200));
	imageline($img, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $line);
}

//逐字绘制并随机上下错位，避免整体等高好被切割识别
$x = 12;
for($i = 0; $i < strlen($code); $i++){
	$fg = imagecolorallocate($img, random_int(20, 110), random_int(20, 110), random_int(90, 160));
	imagestring($img, 5, $x, random_int(6, 16), $code[$i], $fg);
	$x += 25;
}

imagepng($img);
imagedestroy($img);
