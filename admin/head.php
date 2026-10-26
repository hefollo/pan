<?php
@header('Content-Type: text/html; charset=UTF-8');
//theme_active_id()：平时就是后台选的外观；后台布局编辑器预览时是正在编辑的那个主题（DEC-20261025-003）
$site_theme = theme_active_id();
if(!in_array($site_theme, site_theme_keys(), true)){
  $site_theme = default_site_theme();
}
//这四套是固定侧栏外观，菜单竖着排；其余都是顶部横向导航
$is_sidebar_admin = theme_chain_in($site_theme, ['console', 'dashboard', 'workspace', 'cockpit', 'studio', 'nebula', 'royal', 'crisp', 'azure', 'neo']) !== false;
$admin_body_class = !empty($islogin) ? 'admin-body' : 'admin-login-body';
//继承了内置外观的上传主题同时挂上父外观的类名，admin.css 里按 admin-theme-* 写的样式照样生效
$admin_body_class .= ' ' . theme_chain_classes($site_theme, 'admin-theme-');
//固定侧栏的后台外观不加这个类，顶部导航那套响应式规则（收汉堡、悬停展开）只给顶栏外观用
if(!$is_sidebar_admin)$admin_body_class .= ' top-nav-admin';
//后台名称跟着「网站标题」走：左上角和浏览器标签共用这一份。
//标签：首页就是「网站标题管理中心」，其它页面前面加上页面名。
//网站标题是站长填的，要转义；各页面自己的 $title 是写死的文字（有的用 HTML 实体写），原样输出
$admin_brand = admin_brand_html();
$admin_doc_title = (isset($title) && $title !== '') ? $title.' - '.$admin_brand : $admin_brand;
//子菜单要精确到 set.php 的 mod 参数，checkIfActive 只认文件名区分不了，这里单独判断
if(!function_exists('admin_sub_active')){
	function admin_sub_active($file, $mod = null){
		$self = basename($_SERVER['SCRIPT_NAME']);
		if($self !== $file) return null;
		if($mod === null) return 'active';
		return (isset($_GET['mod']) && $_GET['mod'] === $mod) ? 'active' : null;
	}
}
//覆盖上传更新包会把根目录的 favicon.ico 盖回默认图标，管理员一进后台就照着上传的那张补回去
if(!empty($islogin))site_icon_sync_root();
/*
 * <head>、导航和后台公共脚本交给外观引擎（includes/theme_shell.php 的 theme_admin_shell_open，DEC-20261025-003）：
 * 导航是后台外壳布局 admin/_shell 里的卡片（includes/blocks/admin/navbar.php），
 * <head> 和公共脚本（统一保存、动态换页、文件状态选择器）是固定模板 includes/templates/admin/。
 * 页面内容紧跟在动态换页的起点标记 #admin-dynamic-content-start 后面输出。
 */
echo theme_admin_shell_open($site_theme, $admin_body_class, $admin_doc_title);
