<?php
include("./includes/common.php");

//赞助名单关掉之后，直接敲地址也进不来（导航入口藏了不等于页面就访问不到）
if(isset($conf['sponsor_open']) && $conf['sponsor_open'] == 0){
    header('Location: ./');
    exit;
}

$title = '赞助名单 - ' . $conf['title'];

include SYSTEM_ROOT.'header.php';
//页面内容是布局 sponsor（DEC-20261025-003）：includes/layouts/sponsor.json，卡片在 includes/blocks/sponsor/
echo theme_render_layout('sponsor', [], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
echo theme_deferred_html();
?></body>
</html>
