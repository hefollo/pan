<?php
include("./includes/common.php");

if(isset($conf['violation_open']) && $conf['violation_open'] == 0){
    header('Location: ./');
    exit;
}

$title = '违规文件公示 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
//页面内容是布局 violation（DEC-20261025-003）：includes/layouts/violation.json，卡片在 includes/blocks/violation/
echo theme_render_layout('violation', [], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
echo theme_deferred_html();
?></body>
</html>
