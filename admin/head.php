<?php
@header('Content-Type: text/html; charset=UTF-8');
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : 'cloud';
if(!in_array($site_theme, ['cloud', 'night', 'neon', 'aurora', 'onefour', 'celadon', 'lilac', 'paper', 'blush', 'sky', 'mint', 'sunset', 'abyss', 'emerald', 'sakura', 'dashboard', 'console', 'portal', 'workspace'], true)){
  $site_theme = 'cloud';
}
$admin_body_class = !empty($islogin) ? 'admin-body' : 'admin-login-body';
$admin_body_class .= ' admin-theme-' . $site_theme;
//子菜单要精确到 set.php 的 mod 参数，checkIfActive 只认文件名区分不了，这里单独判断
if(!function_exists('admin_sub_active')){
	function admin_sub_active($file, $mod = null){
		$self = basename($_SERVER['SCRIPT_NAME']);
		if($self !== $file) return null;
		if($mod === null) return 'active';
		return (isset($_GET['mod']) && $_GET['mod'] === $mod) ? 'active' : null;
	}
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width,height=device-height,initial-scale=1.0,maximum-scale=1.0,user-scalable=no;">
  <title><?php echo $title ?></title>
  <link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
  <link href="../assets/css/bootstrap-table.css?v=1" rel="stylesheet"/>
  <link href="../assets/css/admin.css?v=<?php echo VERSION?>" rel="stylesheet"/>
  <script src="https://s4.zstatic.net/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
  <script src="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <!--[if lt IE 9]>
    <script src="https://s4.zstatic.net/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://s4.zstatic.net/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
</head>
<body class="<?php echo $admin_body_class;?>">
<?php if(!empty($islogin)){?>
  <nav class="navbar navbar-fixed-top navbar-default">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="sr-only">导航按钮</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="./">彩虹外链网盘管理中心</a>
      </div><!-- /.navbar-header -->
      <div id="navbar" class="collapse navbar-collapse">
        <ul class="nav navbar-nav navbar-right">
          <li class="<?php echo checkIfActive('index,')?>">
            <a href="./"><i class="fa fa-home"></i> 后台首页</a>
          </li>
		      <li class="<?php echo checkIfActive('file')?>">
            <a href="./file.php"><i class="fa fa-folder-open"></i> 文件管理</a>
          </li>
          <li class="<?php echo checkIfActive('replace')?>">
            <a href="./replace.php"><i class="fa fa-refresh"></i> 覆盖记录</a>
          </li>
          <li class="<?php echo checkIfActive('user')?>">
            <a href="./user.php"><i class="fa fa-users"></i> 用户管理</a>
          </li>
		      <li class="<?php echo checkIfActive('set,set_stor,set_script,set_sponsor,set_violation')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cog"></i> 系统设置<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li class="<?php echo admin_sub_active('set.php','site')?>"><a href="./set.php?mod=site">网站信息设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','appearance')?>"><a href="./set.php?mod=appearance">外观设置</a></li>
              <li class="<?php echo admin_sub_active('set_script.php')?>"><a href="./set_script.php">广告公告位设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','user')?>"><a href="./set.php?mod=user">用户登录设置</a></li>
              <li class="<?php echo admin_sub_active('set_stor.php')?>"><a href="./set_stor.php">存储类型设置</a></li>
			        <li class="<?php echo admin_sub_active('set.php','file')?>"><a href="./set.php?mod=file">文件上传设置</a></li>
			        <li class="<?php echo admin_sub_active('set.php','green')?>"><a href="./set.php?mod=green">图片检测设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','api')?>"><a href="./set.php?mod=api">上传API设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','iptype')?>"><a href="./set.php?mod=iptype">用户IP地址设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','account')?>"><a href="./set.php?mod=account">管理账号设置</a></li>
              <li class="<?php echo admin_sub_active('set_violation.php')?>"><a href="./set_violation.php">违规公示管理</a></li>
              <li class="<?php echo admin_sub_active('set_sponsor.php')?>"><a href="./set_sponsor.php">赞助名单管理</a></li>
            </ul>
          </li>
          <li><a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out"></i> 退出登录</a></li>
        </ul>
      </div><!-- /.navbar-collapse -->
    </div><!-- /.container -->
  </nav><!-- /.navbar -->
<?php }?>
