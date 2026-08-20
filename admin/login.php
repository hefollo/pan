<?php
/**
 * 登录
**/
$verifycode = 1;

//用 __DIR__ 定位，不依赖运行时的工作目录
if(!function_exists("imagecreate") || !file_exists(__DIR__.'/code.php'))$verifycode=0;
define('IN_ADMIN', true);
include("../includes/common.php");
//登录失败按IP锁定：5次失败锁15分钟。计数记在服务端，清Cookie也绕不过。
//这里刻意用 REMOTE_ADDR 而不是 $clientip：$clientip 默认信任 X-Forwarded-For，
//可以被请求头随意伪造，拿它做限速等于没做。
$login_max_fail = 5;
$login_lock_time = 900;
$login_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

if(isset($_POST['user']) && isset($_POST['pass'])){
	if(!isset($_SESSION['pass_error']))$_SESSION['pass_error']=0;
	//密码是原样存进 pre_config 的（saveSetting 走绑定参数），这里不能再 addslashes，
	//否则密码里带引号或反斜杠时永远对不上
	$user=(string)$_POST['user'];
	$pass=(string)$_POST['pass'];
	$code=isset($_POST['code'])?(string)$_POST['code']:'';
	//验证码一次性使用：验过即作废，同一个码不能反复提交
	$vc_code=isset($_SESSION['vc_code'])?(string)$_SESSION['vc_code']:'';
	unset($_SESSION['vc_code']);
	$locked = login_throttle_locked($login_ip, $login_max_fail, $login_lock_time);
	if ($locked > 0) {
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('登录失败次数过多，请在".ceil($locked/60)."分钟后重试！');history.go(-1);</script>");
	}elseif ($verifycode==1 && ($code === '' || $vc_code === '' || strtolower($code) !== strtolower($vc_code))) {
		//验证码错误也计入失败次数，否则可以靠刷验证码把限速耗过去
		login_throttle_fail($login_ip, $login_lock_time);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('验证码错误！');history.go(-1);</script>");
	}elseif($_SESSION['pass_error']>$login_max_fail) {
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('用户名或密码不正确！');history.go(-1);</script>");
	}elseif(hash_equals((string)$conf['admin_user'], $user) && hash_equals((string)$conf['admin_pwd'], $pass)) {
		//必须用 hash_equals 做二进制比较：== 会把两个纯数字串按数值比，'0123456' == '123456' 为真
		login_throttle_reset($login_ip);
		$_SESSION['pass_error']=0;
		//登录成功换一个会话ID，避免会话固定攻击
		session_regenerate_id(true);
		$session=md5($user.$pass.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$user}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		ob_clean();
		setcookie("admin_token", $token, time() + 2592000);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('登录管理中心成功！');window.location.href='./';</script>");
	}else {
		login_throttle_fail($login_ip, $login_lock_time);
		$_SESSION['pass_error']++;
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('用户名或密码不正确！');history.go(-1);</script>");
	}
}elseif(isset($_GET['logout'])){
	setcookie("admin_token", "", time() - 2592000);
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin==1){
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : 'cloud';
if(!in_array($site_theme, ['cloud', 'night', 'neon', 'aurora', 'onefour', 'celadon', 'lilac', 'paper', 'blush', 'sky', 'mint', 'sunset', 'abyss', 'emerald', 'sakura'], true)){
	$site_theme = 'cloud';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
	<meta charset="UTF-8">
	<meta name="renderer" content="webkit">
	<meta name="viewport" content="width=device-width,height=device-height,inital-scale=1.0,maximum-scale=1.0,user-scalable=no;">
	<title>管理员登录</title>
	<link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
	<link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
	<link href="../assets/css/admin.css?v=<?php echo VERSION?>" rel="stylesheet"/>
</head>
<body class="admin-login-body admin-theme-<?php echo $site_theme;?>">
  <div class="container">
      <div class="row">
          <div class="col-md-offset-4 col-md-4 col-sm-offset-3 col-sm-6">
              <form class="form-horizontal admin-login-form" method="post">
                  <div class="heading">管理员登录</div>
                  <div class="form-group">
                      <i class="fa fa-user"></i><input required name="user" type="text" class="form-control" placeholder="用户名">
                  </div>
                  <div class="form-group">
                      <i class="fa fa-lock"></i><input required name="pass" type="password" class="form-control" placeholder="密码"/>
                  </div>
                  <?php if($verifycode==1){?>
                  <div class="form-group">
                      <i class="fa fa-shield"></i><input required name="code" type="text" class="form-control" placeholder="验证码" autocomplete="off" maxlength="6" style="width:55%"/><img src="./code.php" alt="验证码" title="点击更换" onclick="this.src='./code.php?'+Math.random()" style="height:40px;vertical-align:middle;cursor:pointer;border-radius:6px;margin-left:6px"/>
                  </div>
                  <?php }?>
                  <div class="form-group">
                      <button type="submit" class="btn btn-default"><i class="fa fa-arrow-right"></i></button>
                  </div>
              </form>
          </div>
      </div>
  </div>
</body>
</html>
