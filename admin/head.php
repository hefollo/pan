<?php
@header('Content-Type: text/html; charset=UTF-8');
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
if(!in_array($site_theme, site_theme_keys(), true)){
  $site_theme = default_site_theme();
}
//这四套是固定侧栏外观，菜单竖着排；其余都是顶部横向导航
$is_sidebar_admin = in_array($site_theme, ['console', 'dashboard', 'workspace', 'cockpit'], true);
//内容检测没开的话，检测记录整项不显示（设置页仍在「安全与合规」组里，用来开它）
$green_log_on = !empty($conf['green_check']);
$admin_body_class = !empty($islogin) ? 'admin-body' : 'admin-login-body';
$admin_body_class .= ' admin-theme-' . $site_theme;
//固定侧栏的后台外观不加这个类，顶部导航那套响应式规则（收汉堡、悬停展开）只给顶栏外观用
if(!$is_sidebar_admin)$admin_body_class .= ' top-nav-admin';
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
  <?php /* 弹窗统一在这里加载一份：原来 11 个页面各写各的，还分了 layer 2.3 和 3.1.1 两个版本，
           同一个后台里弹窗长相不一样。版本以 3.1.1 为准，各页不再自己引 */ ?>
  <link href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.min.css" rel="stylesheet"/>
  <link href="../assets/css/admin.css?v=<?php echo asset_ver('assets/css/admin.css')?>" rel="stylesheet"/>
  <script src="https://s4.zstatic.net/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
  <script src="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.min.js"></script>
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
        <?php
        /*
         * 一级菜单固定 5 项：首页 / 文件 / 用户 / 设置 / 退出。
         *
         * 原来是 6 个记录类页面平铺在一级，顶栏外观放到第 8、9 项就会换成两行，
         * 把页面内容压到固定导航底下，所以当时按外观分了叉：侧栏外观把「内容检测记录」
         * 放一级，顶栏外观塞进系统设置里。收成 5 项之后，两种外观可以用同一份菜单，
         * 分叉的判断也就不需要了。
         *
         * 分组按"对象"分：管文件的进「文件」，管人和钱的进「用户」，配置项全进「设置」。
         * 侧栏外观下这些下拉是常驻展开的（admin.css 里改成 static），所以看起来就是
         * 三个带标题的分组，不用点开。
         */
        ?>
        <ul class="nav navbar-nav navbar-right">
          <li class="<?php echo checkIfActive('index,')?>">
            <a href="./"><i class="fa fa-home"></i> 后台首页</a>
          </li>
          <li class="dropdown <?php echo checkIfActive('file,replace,green_log')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-folder-open"></i> 文件<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li class="<?php echo admin_sub_active('file.php')?>"><a href="./file.php"><i class="fa fa-folder-open fa-fw"></i> 文件管理</a></li>
              <li class="<?php echo admin_sub_active('replace.php')?>"><a href="./replace.php"><i class="fa fa-refresh fa-fw"></i> 覆盖记录</a></li>
<?php if($green_log_on){?>
              <li class="<?php echo admin_sub_active('green_log.php')?>"><a href="./green_log.php"><i class="fa fa-shield fa-fw"></i> 内容检测记录</a></li>
<?php }?>
            </ul>
          </li>
          <li class="dropdown <?php echo checkIfActive('user,order,mail_log')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-users"></i> 用户<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li class="<?php echo admin_sub_active('user.php')?>"><a href="./user.php"><i class="fa fa-users fa-fw"></i> 用户管理</a></li>
              <li class="<?php echo admin_sub_active('order.php')?>"><a href="./order.php"><i class="fa fa-shopping-cart fa-fw"></i> 订单记录</a></li>
              <li class="<?php echo admin_sub_active('mail_log.php')?>"><a href="./mail_log.php"><i class="fa fa-envelope-o fa-fw"></i> 发信记录</a></li>
            </ul>
          </li>
          <li class="dropdown <?php echo checkIfActive('set,set_stor,set_script,set_sponsor,set_violation,set_pay,set_mail')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cog"></i> 设置<b class="caret"></b></a>
            <ul class="dropdown-menu admin-settings-menu">
              <li class="dropdown-header">站点</li>
              <li class="<?php echo admin_sub_active('set.php','site')?>"><a href="./set.php?mod=site"><i class="fa fa-globe fa-fw"></i> 网站信息设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','appearance')?>"><a href="./set.php?mod=appearance"><i class="fa fa-paint-brush fa-fw"></i> 外观设置</a></li>
              <li class="<?php echo admin_sub_active('set_script.php')?>"><a href="./set_script.php"><i class="fa fa-bullhorn fa-fw"></i> 广告公告位设置</a></li>
              <li class="divider"></li>
              <li class="dropdown-header">上传与存储</li>
              <li class="<?php echo admin_sub_active('set.php','file')?>"><a href="./set.php?mod=file"><i class="fa fa-upload fa-fw"></i> 文件上传设置</a></li>
              <li class="<?php echo admin_sub_active('set_stor.php')?>"><a href="./set_stor.php"><i class="fa fa-database fa-fw"></i> 存储类型设置</a></li>
              <li class="<?php echo admin_sub_active('set.php','api')?>"><a href="./set.php?mod=api"><i class="fa fa-code fa-fw"></i> 上传API设置</a></li>
              <li class="divider"></li>
              <li class="dropdown-header">用户与付费</li>
              <li class="<?php echo admin_sub_active('set.php','user')?>"><a href="./set.php?mod=user"><i class="fa fa-user-circle fa-fw"></i> 用户登录设置</a></li>
              <li class="<?php echo admin_sub_active('set_mail.php')?>"><a href="./set_mail.php"><i class="fa fa-envelope fa-fw"></i> 邮件发信设置</a></li>
              <li class="<?php echo admin_sub_active('set_pay.php')?>"><a href="./set_pay.php"><i class="fa fa-credit-card fa-fw"></i> 购买套餐设置</a></li>
              <li class="divider"></li>
              <li class="dropdown-header">安全与合规</li>
              <li class="<?php echo admin_sub_active('set.php','green')?>"><a href="./set.php?mod=green"><i class="fa fa-shield fa-fw"></i> 内容检测设置</a></li>
              <li class="<?php echo admin_sub_active('set_violation.php')?>"><a href="./set_violation.php"><i class="fa fa-gavel fa-fw"></i> 违规公示管理</a></li>
              <li class="<?php echo admin_sub_active('set.php','iptype')?>"><a href="./set.php?mod=iptype"><i class="fa fa-map-marker fa-fw"></i> 用户IP地址设置</a></li>
              <li class="divider"></li>
              <li class="dropdown-header">其它</li>
<?php //赞助名单整个关掉时，后台这一项也不显示（开关在「网站信息设置」里）
if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1){?>
              <li class="<?php echo admin_sub_active('set_sponsor.php')?>"><a href="./set_sponsor.php"><i class="fa fa-money fa-fw"></i> 赞助名单管理</a></li>
<?php }?>
              <li class="<?php echo admin_sub_active('set.php','account')?>"><a href="./set.php?mod=account"><i class="fa fa-key fa-fw"></i> 管理账号设置</a></li>
            </ul>
          </li>
          <li><a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out"></i> 退出登录</a></li>
        </ul>
      </div><!-- /.navbar-collapse -->
    </div><!-- /.container -->
  </nav><!-- /.navbar -->
<?php }?>
<script>
/*
 * 设置表单的统一提交：所有设置页的表单都是 onsubmit="return saveSetting(this)"，
 * 原来每个页面底部各抄一份一模一样的实现（共 5 份），改提示文案要改 5 个地方。
 * 存储设置页在提交前还要多校验一次下载域名，那一份仍留在它自己页面里覆盖这个默认实现。
 */
function saveSetting(obj){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data){
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {icon:1, closeBtn:false}, function(){ window.location.reload(); });
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error : function(){
			layer.close(ii);
			layer.alert('服务器错误', {icon:2});
		}
	});
	return false;
}
/*
 * 文件状态选择器（0 正常 / 1 封禁 / 2 待审核）。
 * 文件管理和内容检测记录都用它，所以放在 head.php 里只写一份，免得两边各写一套慢慢跑偏。
 *
 * 平时只显示当前状态一个小标签，点一下才就地展开三个选项——十几行表格里摆一排排按钮
 * 太吵。展开是在原格子里换内容，不是弹下拉菜单：表格外面套着 overflow 的容器，
 * 真弹菜单会被裁掉一半。
 *
 * 页面只要按这个结构渲染，行为就自动生效（bootstrap-table 动态插进来的行也一样）：
 *   <div class="admin-block-pick" data-id="文件ID" data-v="当前值">
 *     <a class="admin-block-cur v2">待审</a>
 *     <span class="admin-block-opts"><a data-v="0">正常</a>...</span>
 *   </div>
 * 改完调用页面自己的 adminBlockDone(id, status, res)，各页决定是刷新列表还是别的。
 */
window.ADMIN_BLOCK_NAMES = {'0':'正常', '1':'封禁', '2':'待审'};
window.adminBlockHtml = function(id, value){
	var v = String(value == null ? 0 : value), h = '';
	h += '<div class="admin-block-pick" data-id="' + id + '" data-v="' + v + '">';
	h += '<a class="admin-block-cur v' + v + '">' + (window.ADMIN_BLOCK_NAMES[v] || '未知') + '</a>';
	h += '<span class="admin-block-opts">';
	for(var k in window.ADMIN_BLOCK_NAMES){
		h += '<a data-v="' + k + '"' + (k === v ? ' class="on v' + k + '"' : '') + '>' + window.ADMIN_BLOCK_NAMES[k] + '</a>';
	}
	return h + '</span></div>';
};
jQuery(function($){
	$(document).on('click', '.admin-block-cur', function(e){
		e.stopPropagation();   //不然会被下面那条"点空白处收起"立刻关掉
		$('.admin-block-pick.is-open').removeClass('is-open');
		$(this).closest('.admin-block-pick').addClass('is-open');
	});
	$(document).on('click', '.admin-block-opts>a', function(e){
		e.stopPropagation();
		var $a = $(this), $box = $a.closest('.admin-block-pick');
		var id = $box.data('id'), v = String($a.data('v'));
		if(v === String($box.data('v'))){ $box.removeClass('is-open'); return; }  //选的就是当前状态
		$box.addClass('is-busy');
		$.getJSON('./ajax_file.php?act=setBlock&id=' + id + '&status=' + v, function(res){
			//改成功不弹提示：列表马上就刷成新状态了，那个标签本身就是反馈，
			//再飘一个框出来只会挡住正在看的那几行。只有失败才需要说一声
			if(!(res && res.code === 0) && window.layer){
				layer.msg((res && res.msg) ? res.msg : '修改失败');
			}
			if(window.adminBlockDone)adminBlockDone(id, v, res);
		}).fail(function(){
			if(window.layer)layer.msg('服务器错误');
			//失败也要走一遍：界面必须回到真实状态，不能停在点过、其实没改成的那一项上
			if(window.adminBlockDone)adminBlockDone(id, v, null);
		});
	});
	//点页面别处就收起，展开着不动很碍眼
	$(document).on('click', function(){ $('.admin-block-pick.is-open').removeClass('is-open'); });
});
</script>
