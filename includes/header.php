<?php
@header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $title?></title>
  <meta name="keywords" content="<?php echo $conf['keywords']?>">
  <meta name="description" content="<?php echo $conf['description']?>">
  <meta name="viewport" content="width=device-width,height=device-height,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="format-detection" content="telephone=no">
  <meta name="google-adsense-account" content="ca-pub-6112564004010114">
  <link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/bootstrap-material-design.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/ripples.min.css" rel="stylesheet">
  <?php if($is_file){?><link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.css"><link href="assets/css/ckplayer.css" rel="stylesheet"><?php }?>
  <link href="assets/css/style.css?v=<?php echo asset_ver('assets/css/style.css')?>" rel="stylesheet">
  <!--[if lt IE 9]>
    <script src="https://s4.zstatic.net/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://s4.zstatic.net/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
  <script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
</head>
<?php
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
if(!in_array($site_theme, site_theme_keys(), true)){
  $site_theme = default_site_theme();
}
//布局型外观（侧栏/门户/工作台/macOS 窗口/渐变仪表盘）共用一套结构样式，统一挂 layout-theme
$layout_themes = ['dashboard', 'console', 'portal', 'workspace', 'mac', 'cockpit'];
$body_class = 'theme-' . $site_theme;
if(in_array($site_theme, $layout_themes, true)){
  $body_class .= ' layout-theme';
  include_once SYSTEM_ROOT.'layout_blocks.php';
}
?>
<body class="<?php echo $body_class?>">

  <div class="navbar navbar-default">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-responsive-collapse">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="./"><?php echo $conf['title']?></a>
      </div>
      <div class="navbar-collapse collapse navbar-responsive-collapse">
        <ul class="nav navbar-nav">
          <li class="<?php echo checkIfActive('index,')?>"><a href="./"><i class="fa fa-list" aria-hidden="true"></i> 文件列表</a></li>
          <li class="<?php echo checkIfActive('upload')?>"><a href="./upload.php"><i class="fa fa-upload" aria-hidden="true"></i> 上传文件</a></li>
          <?php //赞助名单可以在「网站信息设置」里整个关掉，关了两种外观的入口都不出现
          if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1){
            //布局型外观用站内的赞助页，保持自己的导航布局；其它主题仍跳转原来的独立赞助页
            if(in_array($site_theme, $layout_themes, true)){?>
          <li class="<?php echo checkIfActive('sponsor')?>"><a href="./sponsor.php"><i class="fa fa-money" aria-hidden="true"></i> 赞助名单</a></li>
          <?php }else{?>
          <li><a href="./includes/sponsor/"><i class="fa fa-money" aria-hidden="true"></i> 赞助名单</a></li>
          <?php }
          }?>
          <?php //开启购买功能且配置完整时才显示入口
          if(function_exists('is_buy_open') && is_buy_open()){?>
          <li class="<?php echo checkIfActive('buy')?>"><a href="./buy.php"><i class="fa fa-shopping-cart" aria-hidden="true"></i> 购买权限</a></li>
          <?php }?>
          <?php if(!isset($conf['violation_open']) || $conf['violation_open'] == 1){?>
          <li class="<?php echo checkIfActive('violation')?>"><a href="./violation.php"><i class="fa fa-gavel" aria-hidden="true"></i> 违规公示</a></li>
          <?php }?>
          <?php if($is_file){?>
          <li class="<?php echo checkIfActive('file')?>"><a href=""><i class="fa fa-file" aria-hidden="true"></i> 文件查看</a></li>
          <?php }?>
        </ul>
        <ul class="nav navbar-nav navbar-right">
          <?php //登录用户的「我的文件」直接进个人中心的文件页，那里才有重命名/删除等管理操作；
          //游客没有账号，只能看 $_SESSION['fileids'] 那套浏览器缓存记录，仍然走首页的 ?m=mine
          if($islogin2){?>
          <li class="<?php echo checkIfActive('user')?>"><a href="./user.php?tab=files"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a></li>
          <?php }else{?>
          <li class="<?php echo checkIfActive('mine')?>"><a href="./?m=mine"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a></li>
          <?php }?>
          <?php if($conf['userlogin']){?>
            <?php if($islogin2){?>
            <li class="dropdown">
              <a data-target="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-<?php echo $userrow['type']=='qq'?'qq':($userrow['type']=='mail'?'envelope':'wechat');?>" aria-hidden="true"></i> <?php echo $userrow['nickname']?><b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="./user.php"><i class="fa fa-user-circle" aria-hidden="true"></i> 个人中心</a></li>
                <li><a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out" aria-hidden="true"></i> 退出登录</a></li>
              </ul>
            </li>
            <?php }else{?>
            <li class="<?php echo checkIfActive('login')?>"><a href="./login.php"><i class="fa fa-user-circle" aria-hidden="true"></i> 未登录</a></li>
            <?php }?>
          <?php }?>
        </ul>
        <?php
        //侧栏型外观（数据控制台风/深色工作台风）在侧栏底部补一张今日上传统计卡，对应原型里的存储条
        //这三套是固定侧栏布局，底部放得下卡片；上传门户风和配色型外观是顶部导航，
        //改在文件列表页和上传页顶部显示一条权限条（见 render_permission_bar）
        if($site_theme === 'console' || $site_theme === 'workspace' || $site_theme === 'dashboard'){
          $side_limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
          //统计走会话缓存，pre_file 上没有 ip/addtime 索引，不能每次打开页面都扫一遍
          $side_today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
          $side_percent = $side_limit > 0 ? min(100, round($side_today / $side_limit * 100)) : min(100, $side_today * 10);
        ?>
        <div class="layout-side-card">
          <div class="layout-side-row"><strong>今日上传</strong><span><?php echo $side_limit > 0 ? $side_today.' / '.$side_limit : $side_today.' 个'?></span></div>
          <div class="layout-side-bar"><i style="width:<?php echo intval($side_percent)?>%"></i></div>
          <small><?php echo $side_limit > 0 ? ('今日还可上传 '.max(0, $side_limit - $side_today).' 个文件') : '当前账号不限每日上传数量'?></small>
        </div>
        <?php
        //登录用户再补一张权限卡：当前额度、到期时间、买过的套餐，方便随时看还剩多久
        if(!empty($islogin2)){
          $side_size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
          $side_expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
          $side_plan = function_exists('layout_user_plan') ? layout_user_plan($DB) : null;
          if(empty($side_expire)){
            $side_state = '永久有效'; $side_state_cls = 'ok';
          }elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
            $side_state = '已过期'; $side_state_cls = 'expired';
          }else{
            $side_left = max(1, ceil((strtotime($side_expire) - time()) / 86400));
            $side_state = '剩 '.$side_left.' 天'; $side_state_cls = $side_left <= 7 ? 'warn' : 'ok';
          }
        ?>
        <div class="layout-side-card">
          <div class="layout-side-row"><strong>我的权限</strong><span class="layout-side-tag layout-side-tag-<?php echo $side_state_cls?>"><?php echo $side_state?></span></div>
          <div class="layout-side-kv"><span>每日上传</span><b><?php echo $side_limit > 0 ? $side_limit.' 个' : '不限制'?></b></div>
          <div class="layout-side-kv"><span>单文件</span><b><?php echo $side_size > 0 ? $side_size.' MB' : '不限制'?></b></div>
          <?php if(!empty($userrow['bonus_limit']) && $side_limit > 0){?>
          <div class="layout-side-kv"><span>其中加量包</span><b>+<?php echo intval($userrow['bonus_limit'])?> 个/天</b></div>
          <?php }?>
          <?php if(!empty($side_expire)){?>
          <div class="layout-side-kv"><span>到期时间</span><b><?php echo htmlspecialchars(date('Y-m-d', strtotime($side_expire)))?></b></div>
          <?php }?>
          <?php if($side_plan && $side_plan['bought']){?>
          <div class="layout-side-kv"><span>已购套餐</span><b title="<?php echo htmlspecialchars($side_plan['plan_name'], ENT_QUOTES, 'UTF-8')?>"><?php echo htmlspecialchars($side_plan['plan_name'], ENT_QUOTES, 'UTF-8')?></b></div>
          <?php }?>
          <?php if(function_exists('is_buy_open') && is_buy_open()){?>
          <a class="layout-side-buy" href="./buy.php"><?php echo ($side_plan && $side_plan['bought']) ? '续费 / 升级权限' : '购买权限'?> <i class="fa fa-angle-right" aria-hidden="true"></i></a>
          <?php }?>
        </div>
        <?php }?>
        <?php }?>
      </div>
    </div>
  </div>

  <script src="includes/ads.php?v=<?php echo VERSION?>"></script>
