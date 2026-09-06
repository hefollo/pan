<?php
/**
 * 外观整体换色
 *
 * 后台「渐变配色」里选的主色 / 副色，不只是换几个按钮的渐变，而是把这套外观
 * 在 style.css（后台是 admin.css）里用到的**所有**颜色一起换掉：
 * 拿这套外观原本的主色、辅色当锚点，算出新旧两组颜色之间的色相 / 饱和度 / 明度差，
 * 再把同一份差值套到这套外观的每一个颜色上——原来偏蓝的面板、边框、文字、阴影
 * 会整体变成新色系，而灰白黑（没有色相）和语义色（红色危险、绿色成功）保持不动。
 *
 * 灰白黑不跟着色相走，所以页面底色、面板底色、正文色、边框色这四项在后台单独可调，
 * 走的是「按属性类别定点替换」：背景类属性里的面板底色才换成新面板底色、文字类属性里的
 * 正文色才换成新正文色，白卡片改成米色时按钮上的白字不会跟着变。
 *
 * 做法是把该外观作用域内的规则整段抽出来重新上色，选择器原样保留、排在 style.css
 * 之后覆盖，所以：没自定义过的外观一个字节都不生成，页面和原来完全一样。
 * 生成结果按「外观 + 颜色 + 源文件时间」哈希缓存成 assets/css/custom/xxx.css，
 * 目录写不了就退回页面内联，不影响使用。
 */

if(!defined('IN_CRONLITE'))exit('Access Denied');

/* ============ 颜色换算 ============ */

function tr_hex2rgb($hex){
	$hex = ltrim(strtolower($hex), '#');
	if(strlen($hex) === 3)$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
	return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function tr_rgb2hsl($r, $g, $b){
	$r /= 255; $g /= 255; $b /= 255;
	$max = max($r, $g, $b); $min = min($r, $g, $b);
	$l = ($max + $min) / 2;
	$d = $max - $min;
	if($d == 0)return [0.0, 0.0, $l];
	$s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
	if($max == $r)      $h = fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6);
	elseif($max == $g)  $h = ($b - $r) / $d + 2;
	else                $h = ($r - $g) / $d + 4;
	return [$h * 60, $s, $l];
}

function tr_hsl2rgb($h, $s, $l){
	$h = fmod(fmod($h, 360) + 360, 360) / 360;
	if($s == 0){ $v = (int)round($l * 255); return [$v, $v, $v]; }
	$q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
	$p = 2 * $l - $q;
	$out = [];
	foreach([$h + 1/3, $h, $h - 1/3] as $t){
		if($t < 0)$t += 1; if($t > 1)$t -= 1;
		if($t < 1/6)      $v = $p + ($q - $p) * 6 * $t;
		elseif($t < 1/2)  $v = $q;
		elseif($t < 2/3)  $v = $p + ($q - $p) * (2/3 - $t) * 6;
		else              $v = $p;
		$out[] = (int)round($v * 255);
	}
	return $out;
}

//两个色相之间的夹角（0~180）
function tr_hue_gap($a, $b){
	$d = abs(fmod(fmod($a - $b, 360) + 360, 360));
	return $d > 180 ? 360 - $d : $d;
}

/*
 * 明度调整的权重：中间调给足，越靠近纯白／纯黑越接近 0。
 * 近白的卡片底、近黑的深色底都是靠明度撑住层次的，跟着主色一起挪会直接糊掉。
 */
function tr_lweight($l){
	return max(0, 1 - abs(2 * $l - 1));
}

/* ============ 映射规则 ============ */

/*
 * 造一个映射表：新旧各两个锚点。
 * 单色系外观（米白纸张、暗黑科技后台风那种主色本身就是灰的）没法按色相旋转，
 * 单独走「整体染上新色相」的路子，标记为 tint 模式。
 */
function theme_recolor_map($spec, $val){
	$pairs = [];
	foreach([['a', 'a'], ['b', 'b']] as $kk){
		list($ro, $rn) = $kk;
		$o = tr_rgb2hsl(...tr_hex2rgb($spec[$ro]));
		$n = tr_rgb2hsl(...tr_hex2rgb($val[$rn]));
		$pairs[] = ['o'=>$o, 'n'=>$n];
	}
	//两个锚点原色都接近灰 -> 单色系外观
	$tint = ($pairs[0]['o'][1] < 0.12 && $pairs[1]['o'][1] < 0.12);
	//锚点本身和三段渐变的中间色直接一一对应，保证选的颜色原封不动地落在主色的位置上
	$exact = [
		strtolower($spec['a']) => strtolower($val['a']),
		strtolower($spec['b']) => strtolower($val['b']),
	];
	if(isset($spec['mid'], $val['mid']))$exact[strtolower($spec['mid'])] = strtolower($val['mid']);
	/*
	 * 界面底色（页面底、面板底、正文、边框）是灰白系，色相映射会绕开它们，所以单独按
	 * 「属性类别」替换：只有背景类属性里的面板底色才换成新的面板底色，文字类属性里的正文色
	 * 才换成新的正文色。这样把白卡片换成米色时，按钮上的白字不会跟着一起变。
	 * 键按 RGB 三元组比对，rgba() 的透明度原样保留，玻璃拟态那几套外观的半透明面板照旧。
	 */
	$neutral = ['bg'=>[], 'fg'=>[], 'ln'=>[]];
	foreach([['pg', 'bg'], ['sf', 'bg'], ['tx', 'fg'], ['ln', 'ln']] as $kk){
		list($key, $slot) = $kk;
		if(!isset($spec[$key], $val[$key]))continue;
		if(strtolower($spec[$key]) === strtolower($val[$key]))continue;   //没改过就不进表
		$neutral[$slot][strtolower($spec[$key])] = strtolower($val[$key]);
	}
	return ['pairs'=>$pairs, 'tint'=>$tint, 'exact'=>$exact, 'neutral'=>$neutral];
}

/*
 * 单个颜色的换算。返回 false 表示这个颜色不动（灰白黑、或者跟这套外观主色差太远的语义色）。
 */
function tr_map_rgb($map, $r, $g, $b){
	//锚点色原样换成选中的颜色
	$key = sprintf('#%02x%02x%02x', $r, $g, $b);
	if(isset($map['exact'][$key]))return tr_hex2rgb($map['exact'][$key]);

	list($h, $s, $l) = tr_rgb2hsl($r, $g, $b);
	//纯白纯黑不碰：白卡片还是白卡片，黑阴影还是黑阴影
	if($l >= 0.985 || $l <= 0.02)return false;

	if($map['tint']){
		//单色系外观：整套灰阶染上新色相，深浅关系保持不变
		$n = $map['pairs'][0]['n'];
		if($n[1] < 0.04)return false;                 //新色也是灰的，那就没什么可染
		//本来就有颜色的（危险红、成功绿这些）不属于这套灰阶，别跟着染
		if($s > 0.25)return false;
		$ns = min(1, $s + $n[1] * 0.34);
		$nl = max(0, min(1, $l + ($n[2] - $map['pairs'][0]['o'][2]) * 0.25 * tr_lweight($l)));
		return tr_hsl2rgb($n[0], $ns, $nl);
	}

	//灰到看不出色相的，跟着不动，免得阴影和分隔线被染色
	if($s < 0.06)return false;

	//挑更近的锚点：色相为主，明暗为辅（有些外观两个锚点同色相、只差深浅）
	$best = null; $bestd = 1e9;
	foreach($map['pairs'] as $p){
		if($p['o'][1] < 0.06)continue;
		$d = tr_hue_gap($h, $p['o'][0]) + abs($l - $p['o'][2]) * 55 + abs($s - $p['o'][1]) * 25;
		if($d < $bestd){ $bestd = $d; $best = $p; }
	}
	if(!$best)return false;
	//离这套外观的主色系太远的，是红色危险、绿色成功这类语义色，保留原样
	if(tr_hue_gap($h, $best['o'][0]) > 55)return false;

	$o = $best['o']; $n = $best['n'];
	$nh = $h + ($n[0] - $o[0]);
	//饱和度按新旧锚点的比例缩放，夹住上下限，免得选了个灰色就把整套外观洗成一片死灰
	$ratio = $o[1] > 0.02 ? $n[1] / $o[1] : 1;
	$ratio = max(0.2, min(3.0, $ratio));
	$ns = max(0, min(1, $s * $ratio));
	//明度差按这个颜色自身的鲜艳程度加权，并且越接近纯白／纯黑越不动：
	//浅色外观的近白底不会被压暗，深色外观的近黑底也不会被压成死黑，正文对比度保持住
	$nl = max(0, min(1, $l + ($n[2] - $o[2]) * min(1, $s * 1.6) * tr_lweight($l)));
	if($map['tint'] === false && $n[1] < 0.04)$nh = $h;   //新色是灰的就别转色相了
	return tr_hsl2rgb($nh, $ns, $nl);
}

/*
 * 这条声明属于哪一类：背景 / 文字 / 边框。界面底色只在对应类别里替换，
 * 其余属性（阴影、滤镜等）只走色相映射。
 */
function tr_prop_slot($p){
	$p = strtolower(trim($p));
	if($p === 'background' || strpos($p, 'background-') === 0)return 'bg';
	if($p === 'color' || $p === '-webkit-text-fill-color' || $p === 'fill')return 'fg';
	if($p === 'border' || strpos($p, 'border-') === 0)return 'ln';
	if($p === 'outline' || strpos($p, 'outline-') === 0)return 'ln';
	//主题自己定义的变量按名字对号入座
	if($p === '--page-bg' || $p === '--surface')return 'bg';
	if($p === '--text')return 'fg';
	if($p === '--line' || $p === '--drop-line')return 'ln';
	return '';
}

//按顶层分号切声明：括号里的分号（data URI、gradient 里的）不算
function tr_split_decls($body){
	$out = []; $depth = 0; $buf = ''; $q = '';
	for($i = 0, $n = strlen($body); $i < $n; $i++){
		$c = $body[$i];
		if($q !== ''){
			$buf .= $c;
			if($c === $q && $body[$i - 1] !== '\\')$q = '';
			continue;
		}
		if($c === '"' || $c === "'"){ $q = $c; $buf .= $c; continue; }
		if($c === '(')$depth++;
		elseif($c === ')')$depth--;
		if($c === ';' && $depth === 0){ $out[] = $buf; $buf = ''; continue; }
		$buf .= $c;
	}
	if($buf !== '')$out[] = $buf;
	return $out;
}

/*
 * 整条规则体换色：逐条声明判断类别，再按类别换色
 */
function tr_recolor_body($map, $body, &$changed){
	$changed = false;
	$out = []; $any = false;
	foreach(tr_split_decls($body) as $decl){
		$slot = '';
		$pos = strpos($decl, ':');
		if($pos !== false)$slot = tr_prop_slot(substr($decl, 0, $pos));
		$one = false;
		$out[] = tr_recolor_text($map, $decl, $one, $slot);
		if($one)$any = true;
	}
	$changed = $any;
	return implode(';', $out);
}

//界面底色的定点替换：按 RGB 三元组比对，命中就换成新的底色
function tr_neutral_hit($map, $slot, $r, $g, $b){
	if($slot === '' || empty($map['neutral'][$slot]))return false;
	$key = sprintf('#%02x%02x%02x', $r, $g, $b);
	return isset($map['neutral'][$slot][$key]) ? tr_hex2rgb($map['neutral'][$slot][$key]) : false;
}

/*
 * 把一段 CSS 声明里的颜色全部换掉，顺便告诉调用方有没有真的改动过
 */
function tr_recolor_text($map, $text, &$changed, $slot = ''){
	$changed = false;
	//#rgb / #rrggbb / #rrggbbaa
	$text = preg_replace_callback('/(?<![\w&])#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3})(?![0-9a-fA-F])/', function($m) use ($map, $slot, &$changed){
		$hex = $m[1]; $tail = '';
		if(strlen($hex) === 8){ $tail = substr($hex, 6, 2); $hex = substr($hex, 0, 6); }
		list($r, $g, $b) = tr_hex2rgb($hex);
		$out = tr_neutral_hit($map, $slot, $r, $g, $b);
		if($out === false)$out = tr_map_rgb($map, $r, $g, $b);
		if($out === false)return $m[0];
		$changed = true;
		return '#'.sprintf('%02x%02x%02x', $out[0], $out[1], $out[2]).$tail;
	}, $text);
	//rgb() / rgba()，逗号和空格分隔都收，alpha 原样保留
	$text = preg_replace_callback('/rgba?\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*([,\/][^)]*)?\)/i', function($m) use ($map, $slot, &$changed){
		$out = tr_neutral_hit($map, $slot, (int)$m[1], (int)$m[2], (int)$m[3]);
		if($out === false)$out = tr_map_rgb($map, (int)$m[1], (int)$m[2], (int)$m[3]);
		if($out === false)return $m[0];
		$changed = true;
		$tail = isset($m[4]) ? $m[4] : '';
		return ($tail === '' ? 'rgb(' : 'rgba(').$out[0].','.$out[1].','.$out[2].$tail.')';
	}, $text);
	return $text;
}

/* ============ CSS 抽取与改写 ============ */

/*
 * 顶层按大括号切成 [选择器, 规则体] 列表；@media 这类块的规则体留着由调用方再切一次
 */
function tr_split_rules($s){
	$out = []; $i = 0; $n = strlen($s); $buf = '';
	while($i < $n){
		$c = $s[$i];
		if($c === '{'){
			$depth = 1; $j = $i + 1;
			while($j < $n && $depth > 0){
				if($s[$j] === '{')$depth++;
				elseif($s[$j] === '}')$depth--;
				$j++;
			}
			//选择器前面常挂着注释（@media 上面尤其多），先摘掉，
			//否则 @media 会被当成普通选择器，整块规则被加上外观前缀
			$out[] = [trim(preg_replace('#/\*.*?\*/#s', '', $buf)), substr($s, $i + 1, $j - $i - 2)];
			$buf = ''; $i = $j; continue;
		}
		$buf .= $c; $i++;
	}
	return $out;
}

//选择器里的注释去掉，再按顶层逗号拆开
function tr_split_selector($sel){
	$sel = preg_replace('#/\*.*?\*/#s', '', $sel);
	$parts = []; $depth = 0; $buf = '';
	for($i = 0, $n = strlen($sel); $i < $n; $i++){
		$c = $sel[$i];
		if($c === '(')$depth++;
		elseif($c === ')')$depth--;
		if($c === ',' && $depth === 0){ $parts[] = trim($buf); $buf = ''; continue; }
		$buf .= $c;
	}
	if(trim($buf) !== '')$parts[] = trim($buf);
	return $parts;
}

/*
 * 决定一条规则在换色后用什么选择器输出：
 *  - 本外观自己的规则：原样保留（只留属于本外观的那几段）
 *  - 别的外观的规则：跳过
 *  - :root：改写成本外观的作用域，这样基础调色板也跟着换（蓝白清爽整套颜色就在 :root 里）
 *  - 其余通用规则：前面加上本外观的作用域，只影响这套外观
 * 返回空串表示这条不要。
 */
function tr_scope_selector($sel, $scope, $others_re, $with_base){
	$parts = tr_split_selector($sel);
	$keep = [];
	$mine = '/'.preg_quote($scope, '/').'\b/';
	foreach($parts as $p){
		if($p === '')continue;
		if(preg_match($mine, $p)){ $keep[] = $p; continue; }
		if(preg_match($others_re, $p))continue;              //别人家的外观，跳过
		//没有主题前缀的通用规则只对「蓝白清爽」有意义：它的调色板就写在 :root 和这些基础规则里，
		//其余外观都在自己的作用域里重新定义过颜色，拿它们的色差去套基础规则只会串色
		if(!$with_base)continue;
		if(preg_match('/^:root$/', $p)){ $keep[] = $scope; continue; }
		if(preg_match('/^html\b/', $p))continue;             //html 上挂的规则没法限定到单套外观
		if(preg_match('/^body\b/', $p)){ $keep[] = preg_replace('/^body/', $scope, $p); continue; }
		$keep[] = $scope.' '.$p;
	}
	return implode(',', $keep);
}

/*
 * 生成某套外观的整体换色样式。$scope='front' 读 style.css，'admin' 读 admin.css。
 * 返回 CSS 文本；这套外观没自定义、或者算下来没有任何颜色变化时返回空串。
 */
function theme_recolor_css($theme, $scope = 'front'){
	$specs = theme_gradient_specs();
	if(!isset($specs[$theme]))return '';
	$val = theme_gradient_of($theme, true);
	if(!$val)return '';
	$map = theme_recolor_map($specs[$theme], $val);

	$file = ROOT.'assets/css/'.($scope === 'admin' ? 'admin.css' : 'style.css');
	if(!is_file($file))return '';
	$css = @file_get_contents($file);
	if($css === false)return '';

	$sc = $scope === "admin" ? "body.admin-theme-".$theme : "body.theme-".$theme;
	//「蓝白清爽」用的就是 :root 里那套基础调色板，只有它需要连通用规则一起换色
	$with_base = ($theme === "cloud");
	$others_re = $scope === 'admin' ? '/body\.admin-theme-[a-z]+/' : '/body\.theme-[a-z]+/';

	$out = '';
	foreach(tr_split_rules($css) as $rule){
		list($sel, $body) = $rule;
		if($sel === '')continue;
		if($sel[0] === '@'){
			//@media / @supports：里面再切一层，外壳原样套回去
			if(strpos($sel, '@media') !== 0 && strpos($sel, '@supports') !== 0)continue;
			$inner = '';
			foreach(tr_split_rules($body) as $ir){
				$piece = tr_rule_out($ir[0], $ir[1], $sc, $others_re, $map, $with_base);
				if($piece !== '')$inner .= $piece;
			}
			if($inner !== '')$out .= $sel.'{'.$inner.'}';
			continue;
		}
		$out .= tr_rule_out($sel, $body, $sc, $others_re, $map, $with_base);
	}
	return $out;
}

//一条规则换色后要不要输出：颜色没变过就丢掉，生成的文件里只留真正变了的
function tr_rule_out($sel, $body, $sc, $others_re, $map, $with_base){
	if($sel === '' || $sel[0] === '@')return '';
	if(strpos($body, '#') === false && stripos($body, 'rgb') === false)return '';
	//外观卡片上的缩略图是「这套外观长什么样」的说明图，跟着当前外观换色就不成样子了
	if(strpos($sel, '.appearance-preview') !== false)return '';
	$changed = false;
	$newbody = tr_recolor_body($map, $body, $changed);
	if(!$changed)return '';
	$newsel = tr_scope_selector($sel, $sc, $others_re, $with_base);
	if($newsel === '')return '';
	return $newsel.'{'.$newbody.'}';
}

/*
 * 换色样式的缓存文件。命中就直接返回相对 URL，没有就现生成一份。
 * 哈希带上源文件的修改时间和大小：改了 style.css 会自动重算，不用手动清缓存。
 * 目录不可写时返回空串，调用方改成页面内联输出。
 */
function theme_recolor_url($theme, $scope = 'front'){
	$val = theme_gradient_of($theme, true);
	if(!$val)return '';
	$src = ROOT.'assets/css/'.($scope === 'admin' ? 'admin.css' : 'style.css');
	if(!is_file($src))return '';
	$key = md5($theme.'|'.$scope.'|'.json_encode($val).'|'.filemtime($src).'|'.filesize($src));
	$name = 'theme-'.$scope.'-'.$theme.'-'.substr($key, 0, 10).'.css';
	$dir = ROOT.'assets/css/custom/';
	$path = $dir.$name;
	if(is_file($path))return 'assets/css/custom/'.$name;
	if(!is_dir($dir) && !@mkdir($dir, 0755, true))return '';
	if(!is_writable($dir))return '';
	$css = theme_recolor_css($theme, $scope);
	if($css === '')return '';
	if(@file_put_contents($path, $css, LOCK_EX) === false)return '';
	//同一套外观的旧缓存清掉，免得越攒越多
	foreach(glob($dir.'theme-'.$scope.'-'.$theme.'-*.css') as $old){
		if(basename($old) !== $name)@unlink($old);
	}
	return 'assets/css/custom/'.$name;
}

/*
 * 页面里直接 echo：能用缓存文件就发 <link>，不能就内联 <style>。
 * $prefix 是相对路径前缀（后台在子目录里，要 ../）。
 */
function theme_recolor_tag($theme, $scope = 'front', $prefix = ''){
	$url = theme_recolor_url($theme, $scope);
	if($url !== '')return "\n".'<link href="'.$prefix.$url.'" rel="stylesheet">'."\n";
	$css = theme_recolor_css($theme, $scope);
	return $css === '' ? '' : "\n<style id=\"theme-recolor\">".$css."</style>\n";
}
