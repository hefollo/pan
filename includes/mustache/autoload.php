<?php
/*
 * Mustache.php v3.2.0（https://github.com/bobthecow/mustache.php，MIT，许可证见同目录 LICENSE）。
 *
 * 外观模板引擎（DEC-20261025-003）。不放 includes/vendor/：在线更新不碰 vendor 目录，
 * 放进去的话在线更新上来的站点会缺这个库。src/ 是原样拷贝，不要改；升级时整个替换 src/。
 * 只认 \Mustache\ 命名空间，旧的 Mustache_xxx 类名（src/compat.php）用不到，不加载。
 */
spl_autoload_register(function($class){
	if(strncmp($class, 'Mustache\\', 9) !== 0)return;
	$file = __DIR__.'/src/'.str_replace('\\', '/', substr($class, 9)).'.php';
	if(is_file($file))require $file;
});
