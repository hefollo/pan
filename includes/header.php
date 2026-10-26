<?php
@header('Content-Type: text/html; charset=UTF-8');
//当前外观：页面里还要用它判断布局（index.php 等），算一次各处共用
//theme_active_id()：平时就是后台选的外观；后台布局编辑器预览时是正在编辑的那个主题（DEC-20261025-003）
$site_theme = theme_active_id();
if(!in_array($site_theme, site_theme_keys(), true)){
  $site_theme = default_site_theme();
}
//卡片共用的统计、查询小函数（includes/layout_blocks.php）
include_once SYSTEM_ROOT.'layout_blocks.php';
/*
 * <head>、导航、侧栏卡片……都交给外观引擎（includes/theme_shell.php，DEC-20261025-003）：
 * 外壳是一份布局（_shell），这里输出「页面内容」卡片之前的部分，之后的部分由 footer.php 输出。
 * 结构说明（首屏守卫、侧栏卡片、导航）见 includes/templates/shell/head.mustache 和 includes/blocks/shell/。
 */
echo theme_shell_open($site_theme);
