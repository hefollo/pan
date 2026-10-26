<?php
/**
 * 主题包的上传安装、删除、导出（DEC-20261025-003）。
 *
 * 主题包是站长上传的 zip，里面只能有 theme.json、Mustache 模板、布局 JSON、样式表、脚本、图片和字体。
 * 安全上靠三条：
 *   1. 绝不让包里出现能在服务器上执行或改服务器行为的文件（.php/.phtml/.phar/.htaccess/.user.ini……），
 *      扩展名走白名单，不在白名单里的整包拒绝；
 *   2. 路径逐条检查（绝对路径、..、反斜杠、盘符、隐藏文件都拒绝），文件一个个写到自己算好的路径，
 *      不用 ZipArchive::extractTo，不给 zip 里的路径任何发挥空间；
 *   3. 模板是 Mustache，安装前逐个解析一遍，语法错的整包拒绝；模板里本来就调用不了 PHP。
 * 主题包里的 JS 会在本站域名下运行，能做的事和站长自己写的页脚代码一样多，所以后台上传时会提示只装信任来源的主题。
 */

if(!defined('IN_CRONLITE'))exit('Access Denied');

function theme_package_limits(){
	return ['files'=>500, 'file_bytes'=>5 * 1024 * 1024, 'total_bytes'=>30 * 1024 * 1024, 'zip_bytes'=>20 * 1024 * 1024];
}

/* 主题包里允许的扩展名 */
function theme_package_exts(){
	return array_merge(theme_static_exts(), ['json', 'mustache', 'txt', 'md']);
}

/* zip 条目名 → 主题目录里的相对路径；可疑的名字返回 false */
function theme_package_entry_path($name){
	if(!is_string($name) || $name === '' || strlen($name) > 200)return false;
	if(strpos($name, "\0") !== false || strpos($name, '\\') !== false || strpos($name, ':') !== false || $name[0] === '/')return false;
	$parts = explode('/', $name);
	if(end($parts) === '')array_pop($parts);
	foreach($parts as $p){
		if($p === '' || $p === '.' || $p === '..' || $p[0] === '.')return false;
		if(!preg_match('/^[A-Za-z0-9_\-.]+$/', $p))return false;
	}
	return implode('/', $parts);
}

/* SVG 能带脚本：有 <script>、on* 事件、javascript:、foreignObject 的一律不收 */
function theme_package_svg_ok($content){
	return !preg_match('/<\s*script|\son[a-z]+\s*=|javascript\s*:|<\s*foreignObject|<\s*iframe|<\s*embed|<\s*object/i', $content);
}

/*
 * 打开并检查一个主题包。成功返回 ['manifest'=>…, 'files'=>[相对路径=>内容]]，失败返回字符串（原因）。
 * 包里可以多套一层目录（比如 my-theme/theme.json），会自动去掉。
 */
function theme_package_read($zipfile){
	if(!class_exists('ZipArchive'))return '服务器的 PHP 没有开启 zip 扩展，无法解压主题包';
	$lim = theme_package_limits();
	if(!is_file($zipfile) || filesize($zipfile) > $lim['zip_bytes'])return '主题包太大（不能超过 '.size_format($lim['zip_bytes']).'）';
	$zip = new ZipArchive();
	if($zip->open($zipfile) !== true)return '不是有效的 zip 文件';
	if($zip->numFiles > $lim['files'])return '主题包里的文件太多（最多 '.$lim['files'].' 个）';
	$entries = [];
	for($i = 0; $i < $zip->numFiles; $i++){
		$st = $zip->statIndex($i);
		if(!$st)return '主题包读取失败';
		$name = $st['name'];
		if(substr($name, -1) === '/')continue;   //目录条目
		$rel = theme_package_entry_path($name);
		if($rel === false)return '主题包里有不允许的文件路径：'.mb_substr($name, 0, 80, 'UTF-8');
		$entries[$rel] = $st;
	}
	//包里套了一层目录：所有文件都在同一个顶层目录下、theme.json 在那一层
	$prefix = '';
	if(!isset($entries['theme.json'])){
		$tops = [];
		foreach(array_keys($entries) as $rel)$tops[strpos($rel, '/') === false ? '' : substr($rel, 0, strpos($rel, '/'))] = true;
		if(count($tops) === 1 && !isset($tops['']) && isset($entries[key($tops).'/theme.json']))$prefix = key($tops).'/';
		else{ $zip->close(); return '主题包里找不到 theme.json'; }
	}
	$files = [];
	$total = 0;
	$exts = theme_package_exts();
	foreach($entries as $rel => $st){
		$path = $prefix === '' ? $rel : substr($rel, strlen($prefix));
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if(!in_array($ext, $exts, true)){ $zip->close(); return '主题包里有不允许的文件类型：'.$path.'（只能有模板、布局 JSON、样式、脚本、图片、字体和说明文字）'; }
		if($st['size'] > $lim['file_bytes']){ $zip->close(); return '文件太大：'.$path; }
		$total += $st['size'];
		if($total > $lim['total_bytes']){ $zip->close(); return '主题包解压后太大（不能超过 '.size_format($lim['total_bytes']).'）'; }
		$content = $zip->getFromIndex($st['index']);
		if($content === false || strlen($content) !== intval($st['size'])){ $zip->close(); return '文件读取失败：'.$path; }
		if($ext === 'svg' && !theme_package_svg_ok($content)){ $zip->close(); return 'SVG 图片里带了脚本或事件属性：'.$path; }
		$files[$path] = $content;
	}
	$zip->close();
	return theme_package_check($files);
}

/* 检查解出来的文件：theme.json、模板语法、布局 JSON */
function theme_package_check(array $files){
	if(!isset($files['theme.json']))return '主题包里找不到 theme.json';
	$raw = $files['theme.json'];
	if(strncmp($raw, "\xEF\xBB\xBF", 3) === 0)$raw = substr($raw, 3);
	$data = json_decode($raw, true);
	if(!is_array($data))return 'theme.json 不是合法的 JSON';
	$id = isset($data['id']) ? (string)$data['id'] : '';
	if(!theme_id_valid($id))return 'theme.json 的 id 不合法（小写字母开头，字母数字和短横线，2～40 个字符）';
	if(is_dir(theme_builtin_dir().$id))return '主题 id「'.$id.'」和内置外观重名，请换一个';
	$m = theme_manifest_normalize($data, $id, 'upload', theme_upload_dir().$id);
	if(!is_array($m))return $m;
	foreach(array_merge($m['styles'], $m['scripts'], $m['admin_styles'], $m['preview'] !== '' ? [$m['preview']] : []) as $p){
		if(!isset($files[$p]))return 'theme.json 里引用的文件不存在：'.$p;
	}
	if($m['extends'] !== '' && !theme_get($m['extends']))return '要继承的主题「'.$m['extends'].'」不存在';
	$engine = new \Mustache\Engine(['pragmas'=>[\Mustache\Engine::PRAGMA_BLOCKS]]);
	foreach($files as $path => $content){
		if(substr($path, -9) === '.mustache'){
			if(strpos($path, 'templates/') !== 0 || !theme_name_valid(substr($path, 10, -9)))return '模板要放在 templates/ 下，名字只能用小写字母、数字、下划线和短横线：'.$path;
			try{
				$engine->getParser()->parse($engine->getTokenizer()->scan($content));
			}catch(\Mustache\Exception $e){
				return '模板有语法错误：'.$path.'（'.mb_substr($e->getMessage(), 0, 120, 'UTF-8').'）';
			}
		}
		if(strpos($path, 'layouts/') === 0 && substr($path, -5) === '.json'){
			$nodes = json_decode($content, true);
			if(!is_array($nodes))return '布局文件不是合法的 JSON：'.$path;
		}
	}
	return ['manifest'=>$m, 'files'=>$files];
}

/* 删除一个目录（只删 content/themes/ 里面的） */
function theme_package_rmdir($dir){
	$base = realpath(theme_upload_dir());
	$real = realpath($dir);
	if($base === false || $real === false || strpos($real, $base.DIRECTORY_SEPARATOR) !== 0)return false;
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach($it as $f){
		if($f->isDir() && !$f->isLink())@rmdir($f->getPathname());
		else @unlink($f->getPathname());
	}
	return @rmdir($real);
}

/* content/themes/ 目录本身：不存在就建，放一个禁止执行脚本的 .htaccess（Apache 有用；nginx 要站长自己配） */
function theme_package_prepare_dir(){
	$base = theme_upload_dir();
	if(!is_dir($base) && !@mkdir($base, 0755, true))return '无法创建目录 content/themes/，请检查网站目录的写权限';
	$ht = $base.'.htaccess';
	if(!is_file($ht)){
		@file_put_contents($ht, "# 这里只放主题包里的静态文件，不允许执行任何脚本\n<FilesMatch \"\\.(php|phtml|phar|pht|php\\d|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
	}
	if(!is_file($base.'index.html'))@file_put_contents($base.'index.html', '');
	return true;
}

/*
 * 安装（或升级）一个主题包：先写进临时目录，全部写成功再换下旧目录。返回 manifest 或错误原因。
 */
function theme_package_install($zipfile){
	global $DB;
	$pkg = theme_package_read($zipfile);
	if(!is_array($pkg))return $pkg;
	$ok = theme_package_prepare_dir();
	if($ok !== true)return $ok;
	$m = $pkg['manifest'];
	$base = theme_upload_dir();
	$tmp = $base.'.tmp-'.bin2hex(random_bytes(6)).'/';
	if(!@mkdir($tmp, 0755))return '无法写入 content/themes/，请检查目录权限';
	foreach($pkg['files'] as $path => $content){
		$dir = dirname($tmp.$path);
		if(!is_dir($dir) && !@mkdir($dir, 0755, true)){ theme_package_rmdir($tmp); return '无法创建目录：'.$path; }
		if(@file_put_contents($tmp.$path, $content) === false){ theme_package_rmdir($tmp); return '写入文件失败：'.$path; }
	}
	$dest = $base.$m['id'];
	$old = '';
	if(is_dir($dest)){
		$old = $base.'.old-'.bin2hex(random_bytes(6));
		if(!@rename($dest, $old)){ theme_package_rmdir($tmp); return '无法替换旧版本（目录被占用？）'; }
	}
	if(!@rename(rtrim($tmp, '/'), $dest)){
		if($old !== '')@rename($old, $dest);
		theme_package_rmdir($tmp);
		return '安装失败：无法移动到 content/themes/'.$m['id'];
	}
	if($old !== '')theme_package_rmdir($old);
	theme_registry(true);
	if(theme_db_ready()){
		$DB->exec("INSERT INTO pre_theme (id, source, version, settings, installed_at, updated_at) VALUES (:id, 'upload', :v, NULL, NOW(), NOW()) ON DUPLICATE KEY UPDATE source='upload', version=VALUES(version), updated_at=NOW()", [':id'=>$m['id'], ':v'=>$m['version']]);
	}
	return $m;
}

/* 删除上传的主题（正在用的不能删）：目录、设置、布局一起删 */
function theme_package_delete($id){
	global $DB, $conf;
	$m = theme_get($id);
	if(!$m || $m['_source'] !== 'upload')return '只能删除站长上传的主题';
	if(isset($conf['site_theme']) && $conf['site_theme'] === $id)return '这个主题正在使用，先换成别的外观再删';
	foreach(theme_registry() as $tid => $t){
		if($tid !== $id && $t['extends'] === $id)return '主题「'.$t['name'].'」继承了它，先删掉那个';
	}
	if(!theme_package_rmdir(theme_upload_dir().$id))return '删除目录失败，请检查 content/themes/ 的写权限';
	if(theme_db_ready()){
		$DB->exec("DELETE FROM pre_theme WHERE id=:id", [':id'=>$id]);
		$DB->exec("DELETE FROM pre_layout WHERE theme=:id", [':id'=>$id]);
	}
	theme_registry(true);
	return true;
}

/*
 * 把一个主题（内置的也行，拿来当写新主题的起点）打成 zip，返回临时文件路径或错误原因。
 * 站长在后台改过的布局（pre_layout 的已发布版本）一并写进 layouts/，导出的包装到别的站上就是同一个样子。
 */
function theme_package_export($id){
	global $DB;
	$m = theme_get($id);
	if(!$m)return '主题不存在';
	if(!class_exists('ZipArchive'))return '服务器的 PHP 没有开启 zip 扩展';
	$file = tempnam(sys_get_temp_dir(), 'thm');
	$zip = new ZipArchive();
	if($zip->open($file, ZipArchive::OVERWRITE) !== true)return '无法创建 zip';
	/*
	 * 内置外观导出成一个「继承它的子主题」骨架：内置外观的样式在全站的 style.css 里，不在主题目录，
	 * 直接导出原目录拿去改也改不到样式，而且 id 和内置重名、传不上来。骨架里带上它现在的布局和一个空样式表。
	 */
	if($m['_source'] === 'builtin'){
		$new_id = substr($id, 0, 30).'-custom';
		$manifest = ['id'=>$new_id, 'name'=>$m['name'].'（自定义）', 'description'=>'基于「'.$m['name'].'」的自定义主题', 'version'=>'1.0.0', 'author'=>'',
			'engine'=>1, 'extends'=>$id, 'group'=>$m['group'], 'styles'=>['assets/style.css'], 'settings'=>[]];
		$zip->addFromString($new_id.'/theme.json', json_encode($manifest, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
		$zip->addFromString($new_id.'/assets/style.css', "/* 在这里写样式。body 上同时有 .theme-".$new_id." 和 .theme-".$id."，原外观的样式照样生效，这里只写要改的部分。 */\n");
		foreach(['_shell', 'index', 'upload', 'file', 'user', 'buy', 'login', 'edit', 'sponsor', 'violation', 'share'] as $page){
			$nodes = layout_load($page, $id);
			if($nodes)$zip->addFromString($new_id.'/layouts/'.$page.'.json', json_encode(['nodes'=>$nodes], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
		}
		$zip->addFromString($new_id.'/README.md', "# ".$manifest['name']."\n\n继承内置外观「".$m['name']."」（".$id."）。改 assets/style.css、在 templates/ 下放同名模板覆盖卡片、改 layouts/ 下的默认布局，打包成 zip 在后台「外观设置 → 主题包」上传。写法见程序目录里的 docs/theme.md。\n");
		$zip->close();
		return $file;
	}
	$dir = realpath($m['_dir']);
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach($it as $f){
		if(!$f->isFile())continue;
		$rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
		if(theme_package_entry_path($rel) === false)continue;
		$zip->addFile($f->getPathname(), $id.'/'.$rel);
	}
	if(theme_db_ready()){
		foreach($DB->getAll("SELECT page, published FROM pre_layout WHERE theme=:t AND published IS NOT NULL AND published<>''", [':t'=>$id]) as $row){
			if(layout_page_valid($row['page']))$zip->addFromString($id.'/layouts/'.$row['page'].'.json', json_encode(['nodes'=>json_decode($row['published'], true)], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
		}
	}
	$zip->close();
	return $file;
}
