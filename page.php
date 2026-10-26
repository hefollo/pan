<?php
/*
 * 自定义页面（DEC-20261026-001）：站长在后台「页面布局」里建的页面，page.php?p=<地址名>。
 * 页面内容就是布局 page-<地址名>（卡片在 includes/blocks/，通用展示卡片在 includes/blocks/common/）。
 * 设成网站入口时，index.php 在不带参数打开 / 时也走这里（那时 $custom_page 已经设好）。
 */
if(!defined('IN_CRONLITE'))include("./includes/common.php");

if(empty($custom_page)){
	$custom_page = custom_page_get(isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : '');
	if(!$custom_page){
		http_response_code(404);
		sysmsg('页面不存在或已经删除');
	}
}
//导航栏据此判断当前是哪一项（「文件列表」在入口页上不算当前页）
$GLOBALS['custom_page_current'] = $custom_page['slug'];
$title = $custom_page['title'].' - '.$conf['title'];

/*
 * 页面设置里关掉了「显示网站外壳」：整页只有页面自己的内容（一般是「代码」卡片），没有导航、页脚和外观样式。
 * 只带最基本的 <head> 和 jQuery（和前台其它页面同一个版本，「代码」卡片的脚本可以照样用 $）；
 * 后台编辑器预览时照样加定位脚本和提示条。
 */
if(!$custom_page['shell']){
	$site_theme = theme_active_id();
	//平时由 header.php 加载的卡片共用函数（通用展示卡片要用）
	include_once SYSTEM_ROOT.'layout_blocks.php';
	$cp_html = theme_render_layout('page-'.$custom_page['slug'], ['custom_page'=>$custom_page], 'front', $site_theme);
	echo '<!DOCTYPE html>'."\n".'<html lang="zh-cn">'."\n".'<head>'."\n".'<meta charset="utf-8">'."\n"
		.'<meta name="viewport" content="width=device-width, initial-scale=1">'."\n"
		.'<title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title>'."\n"
		.site_icon_tags()
		.'<script src="https://s4.zstatic.net/ajax/libs/jquery/1.12.4/jquery.min.js"></script>'."\n"
		.'</head>'."\n".'<body>'."\n".$cp_html.theme_deferred_html().theme_preview_banner_html().'</body>'."\n".'</html>';
	exit;
}

include SYSTEM_ROOT.'header.php';
echo theme_render_layout('page-'.$custom_page['slug'], ['custom_page'=>$custom_page], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
echo theme_deferred_html();
?></body>
</html>
