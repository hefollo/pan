<?php
/*
 * 按外观拆分前台 / 后台样式表（DEC-20261025-003 / S26）。
 *
 * assets/css/style.css（前台）和 assets/css/admin.css（后台）是源文件，所有外观的样式都写在里面，
 * 靠 body 上的 theme-<id> / admin-theme-<id> 区分。整份 472KB / 337KB，每个页面只用得到其中一小部分。
 * 这个脚本给每套内置外观生成一份只含「可能对它生效」的规则的样式表：
 *   assets/css/build/style.<id>.css、assets/css/build/admin.<id>.css，
 * 以及 _base（不属于任何内置外观的上传主题用：只有公共规则）。
 *
 * 规则：按原文件顺序逐条保留，只去掉选择器里（:not() / :is() / :where() / :has() 之外）出现了
 * 别的外观类名的那些选择器——那些选择器在这套外观的页面上永远匹配不到（外观类名只出现在 <body> 上）。
 * 留下的规则顺序、内容都和源文件一样，层叠结果不变。注释去掉，其余原样。
 *
 * 运行时（includes/theme_shell.php 的 theme_css_bundle_url）对比 manifest.json 里记的源文件 md5，
 * 源文件改过而没重新生成时自动退回整份 style.css / admin.css，所以忘了跑这个脚本也不会出错，只是页面大一点。
 *
 * 用法：php tools/build_theme_css.php（改了 style.css / admin.css 或增删内置外观后运行）
 */

if(PHP_SAPI !== 'cli')exit('只能在命令行运行');
$root = dirname(__DIR__).'/';
$out_dir = $root.'assets/css/build/';

$ids = [];
foreach(glob($root.'themes/*/theme.json') as $f)$ids[] = basename(dirname($f));
sort($ids);
if(!$ids)exit("themes/ 下没有内置外观\n");

/* ---------- 解析 ---------- */

/*
 * 把一段 CSS 切成顶层条目：['type'=>'rule','sel'=>…,'body'=>…] / ['type'=>'block','head'=>'@media …','items'=>[…]]
 * / ['type'=>'raw','text'=>…]（@keyframes、@font-face 这类整块原样保留，@import / @charset 语句）。
 * 跳过注释，认得引号里的字符串，大括号配对。
 */
function css_parse($s, &$i = 0, $nested = false){
	$items = [];
	$len = strlen($s);
	$buf = '';
	while($i < $len){
		$c = $s[$i];
		if($c === '/' && $i + 1 < $len && $s[$i + 1] === '*'){
			$end = strpos($s, '*/', $i + 2);
			$i = $end === false ? $len : $end + 2;
			continue;
		}
		if($c === '"' || $c === "'"){
			$j = css_skip_string($s, $i);
			$buf .= substr($s, $i, $j - $i);
			$i = $j;
			continue;
		}
		if($c === '}'){
			$i++;
			if($nested)return $items;
			throw new Exception('多余的 } 在偏移 '.$i);
		}
		if($c === ';' && ltrim($buf) !== '' && ltrim($buf)[0] === '@'){
			$items[] = ['type'=>'raw', 'text'=>trim($buf).';'];
			$buf = '';
			$i++;
			continue;
		}
		if($c === '{'){
			$head = trim($buf);
			$buf = '';
			$i++;
			if($head !== '' && $head[0] === '@'){
				$name = strtolower(preg_replace('/^@([a-z-]+).*$/s', '$1', $head));
				if(in_array($name, ['media', 'supports', 'layer', 'container', 'document', '-moz-document'], true)){
					$items[] = ['type'=>'block', 'head'=>$head, 'items'=>css_parse($s, $i, true)];
				}else{
					$body = css_read_block($s, $i);
					$items[] = ['type'=>'raw', 'text'=>$head.'{'.$body.'}'];
				}
			}else{
				$items[] = ['type'=>'rule', 'sel'=>$head, 'body'=>css_read_block($s, $i)];
			}
			continue;
		}
		$buf .= $c;
		$i++;
	}
	if($nested)throw new Exception('块没有闭合');
	if(trim($buf) !== '')throw new Exception('文件结尾有没结束的内容：'.substr(trim($buf), 0, 60));
	return $items;
}

function css_skip_string($s, $i){
	$q = $s[$i];
	$len = strlen($s);
	for($j = $i + 1; $j < $len; $j++){
		if($s[$j] === chr(92)){ $j++; continue; }
		if($s[$j] === $q)return $j + 1;
	}
	return $len;
}

/* 读到配对的 }（$i 在 { 之后），返回中间的内容（去掉注释），$i 停在 } 之后 */
function css_read_block($s, &$i){
	$len = strlen($s);
	$depth = 1;
	$out = '';
	while($i < $len){
		$c = $s[$i];
		if($c === '/' && $i + 1 < $len && $s[$i + 1] === '*'){
			$end = strpos($s, '*/', $i + 2);
			$i = $end === false ? $len : $end + 2;
			continue;
		}
		if($c === '"' || $c === "'"){
			$j = css_skip_string($s, $i);
			$out .= substr($s, $i, $j - $i);
			$i = $j;
			continue;
		}
		if($c === '{')$depth++;
		if($c === '}'){
			$depth--;
			if($depth === 0){ $i++; return $out; }
		}
		$out .= $c;
		$i++;
	}
	throw new Exception('块没有闭合');
}

/* 选择器列表按顶层逗号拆开（括号、方括号、引号里的逗号不算） */
function css_split_selectors($sel){
	$parts = [];
	$depth = 0;
	$cur = '';
	$len = strlen($sel);
	for($i = 0; $i < $len; $i++){
		$c = $sel[$i];
		if($c === '"' || $c === "'"){
			$j = css_skip_string($sel, $i);
			$cur .= substr($sel, $i, $j - $i);
			$i = $j - 1;
			continue;
		}
		if($c === '(' || $c === '[')$depth++;
		if($c === ')' || $c === ']')$depth--;
		if($c === ',' && $depth === 0){ $parts[] = trim($cur); $cur = ''; continue; }
		$cur .= $c;
	}
	$parts[] = trim($cur);
	return $parts;
}

/* 选择器里「必须有」的外观类名：去掉函数式伪类（:not(…) 等）里面的内容后再找 */
function css_required_themes($sel, $prefix, array $ids){
	$flat = '';
	$depth = 0;
	$len = strlen($sel);
	for($i = 0; $i < $len; $i++){
		$c = $sel[$i];
		if($c === '(')$depth++;
		if($depth === 0)$flat .= $c;
		if($c === ')')$depth--;
	}
	preg_match_all('/\.'.preg_quote($prefix, '/').'([a-z0-9][a-z0-9-]*)(?![A-Za-z0-9_-])/', $flat, $m);
	return array_values(array_intersect(array_unique($m[1]), $ids));
}

/* ---------- 生成 ---------- */

function css_emit(array $items, $theme, $prefix, array $ids, &$stats){
	$out = '';
	foreach($items as $it){
		if($it['type'] === 'raw'){
			$out .= $it['text']."\n";
		}elseif($it['type'] === 'block'){
			$inner = css_emit($it['items'], $theme, $prefix, $ids, $stats);
			if($inner !== '')$out .= $it['head'].'{'."\n".$inner.'}'."\n";
		}else{
			$keep = [];
			foreach(css_split_selectors($it['sel']) as $sel){
				$req = css_required_themes($sel, $prefix, $ids);
				if(!$req || ($theme !== '' && $req === [$theme]))$keep[] = $sel;
			}
			$stats['rules']++;
			if(!$keep){ $stats['dropped']++; continue; }
			$out .= implode(',', $keep).'{'.$it['body'].'}'."\n";
		}
	}
	return $out;
}

if(!is_dir($out_dir) && !mkdir($out_dir, 0755, true))exit("无法创建 $out_dir\n");
$manifest = ['generated_by'=>'tools/build_theme_css.php', 'themes'=>$ids, 'sources'=>[]];
foreach(['style'=>'theme-', 'admin'=>'admin-theme-'] as $name => $prefix){
	$src = $root.'assets/css/'.$name.'.css';
	$css = file_get_contents($src);
	if(strncmp($css, "\xEF\xBB\xBF", 3) === 0)$css = substr($css, 3);
	try{
		$items = css_parse($css);
	}catch(Exception $e){
		exit("$name.css 解析失败：".$e->getMessage()."\n");
	}
	$manifest['sources'][$name] = md5_file($src);
	$header = "/* 由 tools/build_theme_css.php 从 assets/css/$name.css 生成，不要直接改。外观：%s */\n";
	foreach(array_merge(['_base'], $ids) as $theme){
		$stats = ['rules'=>0, 'dropped'=>0];
		$body = css_emit($items, $theme === '_base' ? '' : $theme, $prefix, $ids, $stats);
		file_put_contents($out_dir.$name.'.'.$theme.'.css', sprintf($header, $theme).$body);
		printf("%-6s %-10s %7.1f KB  规则 %d，去掉 %d\n", $name, $theme, (strlen($body)) / 1024, $stats['rules'], $stats['dropped']);
	}
	printf("%-6s 源文件   %7.1f KB\n", $name, strlen($css) / 1024);
}
file_put_contents($out_dir.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo "完成：", $out_dir, "\n";
