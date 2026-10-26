<?php
/*
 * 页脚。页脚本身是外壳布局里的 shell.footer 卡片（includes/blocks/shell/footer.php 写着结构约定），
 * 这里输出外壳布局里「页面内容」之后的部分，再加末尾那几行基础脚本（includes/templates/shell/tail.mustache）。
 * 引入本文件之前，页面要把自己的 .container 等外层全部收掉：页脚要直接挂在 <body> 下。
 */
echo theme_shell_close(isset($site_theme) ? $site_theme : default_site_theme());
