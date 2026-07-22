<?php
/**
 * 登录
**/
$verifycode = 1;

if(!function_exists("imagecreate") || !file_exists('code.php'))$verifycode=0;
define('IN_ADMIN', true);
include("../includes/common.php");
if(isset($_POST['user']) && isset($_POST['pass'])){
	if(!$_SESSION['pass_error'])$_SESSION['pass_error']=0;
	$user=daddslashes($_POST['user']);
	$pass=daddslashes($_POST['pass']);
	$code=daddslashes($_POST['code']);
	if ($verifycode==1 && (!$code || strtolower($code) != $_SESSION['vc_code'])) {
		unset($_SESSION['vc_code']);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('验证码错误！');history.go(-1);</script>");
	}elseif($_SESSION['pass_error']>5) {
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('用户名或密码不正确！');history.go(-1);</script>");
	}elseif($user==$conf['admin_user'] && $pass==$conf['admin_pwd']) {
		$session=md5($user.$pass.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$user}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		ob_clean();
		setcookie("admin_token", $token, time() + 2592000);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('登录管理中心成功！');window.location.href='./';</script>");
	}else {
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
if(!in_array($site_theme, ['cloud', 'night', 'neon', 'aurora', 'onefour'], true)){
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
                  <div class="form-group">
                      <button type="submit" class="btn btn-default"><i class="fa fa-arrow-right"></i></button>
                  </div>
              </form>
          </div>
      </div>
  </div>
</body>
</html>
