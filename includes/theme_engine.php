<?php
/**
 * 外观引擎：主题包 + 卡片 + 布局（DEC-20261025-003）
 *
 * 三层分工：
 *   卡片（includes/blocks/<组>/<名>.php）：系统自己的 PHP，负责取数据、声明可配置项，可信；
 *   主题包（themes/<id>/ 内置、content/themes/<id>/ 站长上传）：只有 theme.json、Mustache 模板、
 *     CSS/JS/图片和默认布局，没有一行 PHP，所以上传主题包不会变成上传后门；
 *   布局：每个页面放哪些卡片、什么顺序、怎么配，存成节点数组（站长在后台拖出来的存 pre_layout，
 *     没拖过就用主题包自带的 layouts/<页面>.json，再没有就用系统 includes/layouts/<页面>.json）。
 *
 * 模板用 Mustache（无逻辑，模板里调用不了任何 PHP 函数）。不用 Twig：PHP 7.x 能用的
 * Twig 3.11.3 有多个未修的沙箱绕过，修复版要 PHP 8.1。
 *
 * 字段命名约定（写主题的人要知道）：以 _html 结尾的字段已经是转义好的 HTML，模板里用三括号
 * {{{x_html}}} 原样输出；其余字段都是纯文本，用双括号 {{x}}，引擎按 ENT_QUOTES 转义。
 */

if(!defined('IN_CRONLITE'))exit('Access Denied');

/* ============ 目录 ============ */

function theme_builtin_dir(){
	return ROOT.'themes/';
}

/* 站长上传的主题：运行时目录，不进仓库、在线更新和打包都跳过它 */
function theme_upload_dir(){
	return ROOT.'content/themes/';
}

/* 系统默认模板：主题没覆盖的卡片都用这里的 */
function theme_system_tpl_dir(){
	return SYSTEM_ROOT.'templates/';
}

function theme_system_layout_dir(){
	return SYSTEM_ROOT.'layouts/';
}

function theme_block_dir(){
	return SYSTEM_ROOT.'blocks/';
}

/* 主题 id：小写字母开头，字母数字和短横线，2～40 个字符 */
function theme_id_valid($id){
	return is_string($id) && preg_match('/^[a-z][a-z0-9\-]{1,39}$/', $id) === 1;
}

/* 模板名 / 页面名：小写字母数字、下划线、短横线组成的段，用 / 分隔；不允许 .. 和绝对路径 */
function theme_name_valid($name){
	return is_string($name) && strlen($name) <= 120 && preg_match('#^[a-z0-9][a-z0-9_\-]*(/[a-z0-9][a-z0-9_\-]*)*$#', $name) === 1;
}

/* ============ 主题注册表 ============ */

/**
 * 读一个主题目录的 theme.json，整理成统一结构。出错返回字符串（错误原因），
 * 后台上传时直接把这句话报给站长。
 */
function theme_manifest_load($dir, $source){
	$file = rtrim($dir, '/\\').'/theme.json';
	if(!is_file($file))return '缺少 theme.json';
	$raw = @file_get_contents($file);
	if($raw === false)return 'theme.json 读不出来';
	if(strncmp($raw, "\xEF\xBB\xBF", 3) === 0)$raw = substr($raw, 3);
	$data = json_decode($raw, true);
	if(!is_array($data))return 'theme.json 不是合法的 JSON';
	return theme_manifest_normalize($data, basename(rtrim($dir, '/\\')), $source, $dir);
}

function theme_manifest_normalize(array $data, $dirname, $source, $dir){
	$id = isset($data['id']) ? (string)$data['id'] : '';
	if(!theme_id_valid($id))return 'theme.json 的 id 不合法（小写字母开头，字母数字和短横线，2～40 个字符）';
	if($id !== $dirname)return 'theme.json 的 id（'.$id.'）和目录名（'.$dirname.'）不一致';
	$str = function($k, $max = 200) use ($data){
		if(!isset($data[$k]) || !is_scalar($data[$k]))return '';
		$v = trim((string)$data[$k]);
		return mb_strlen($v, 'UTF-8') > $max ? mb_substr($v, 0, $max, 'UTF-8') : $v;
	};
	$list = function($k) use ($data){
		$out = [];
		if(isset($data[$k]) && is_array($data[$k])){
			foreach($data[$k] as $v){
				if(is_string($v) && theme_asset_path_valid($v))$out[] = $v;
			}
		}
		return $out;
	};
	$name = $str('name', 40);
	if($name === '')return 'theme.json 缺少 name（主题名称）';
	$engine = isset($data['engine']) ? intval($data['engine']) : 1;
	if($engine !== 1)return '这个主题需要外观引擎第 '.$engine.' 版，当前程序是第 1 版';
	$extends = $str('extends', 40);
	if($extends !== '' && !theme_id_valid($extends))$extends = '';
	$flags = [];
	if(isset($data['flags']) && is_array($data['flags'])){
		foreach($data['flags'] as $f){
			if(is_string($f) && preg_match('/^[a-z][a-z0-9_]{0,30}$/', $f))$flags[] = $f;
		}
	}
	$body_class = $str('body_class', 200);
	if(!preg_match('/^[A-Za-z0-9_\- ]*$/', $body_class))$body_class = '';
	return [
		'id' => $id,
		'name' => $name,
		'description' => $str('description', 300),
		'version' => $str('version', 20),
		'author' => $str('author', 60),
		'engine' => $engine,
		'group' => in_array($str('group', 20), ['layout', 'color'], true) ? $str('group', 20) : 'layout',
		'extends' => $extends,
		'body_class' => $body_class,
		'flags' => array_values(array_unique($flags)),
		'colors' => (isset($data['colors']) && is_array($data['colors'])) ? $data['colors'] : [],
		'settings' => theme_settings_schema_normalize(isset($data['settings']) ? $data['settings'] : []),
		'blocks' => theme_blocks_normalize(isset($data['blocks']) ? $data['blocks'] : []),
		'styles' => $list('styles'),
		'scripts' => $list('scripts'),
		'admin_styles' => $list('admin_styles'),
		'preview' => (isset($data['preview']) && is_string($data['preview']) && theme_asset_path_valid($data['preview'])) ? $data['preview'] : '',
		'legacy' => !empty($data['legacy']) && $source === 'builtin',
		'sort' => isset($data['sort']) ? intval($data['sort']) : 1000,
		'_source' => $source,
		'_dir' => rtrim($dir, '/\\').'/',
	];
}

/* 主题包里引用的静态文件路径：相对路径，不能出主题目录，只认常见静态资源扩展名 */
function theme_asset_path_valid($path){
	if(!is_string($path) || $path === '' || strlen($path) > 200)return false;
	if(!preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-.]+)*\.[A-Za-z0-9]+$#', $path))return false;
	if(strpos($path, '..') !== false)return false;
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	return in_array($ext, theme_static_exts(), true);
}

function theme_static_exts(){
	return ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf'];
}

/**
 * 主题可调设置（theme.json 的 settings）：后台按它生成表单，保存的值在模板里是 settings.<key>，
 * 颜色 / 数字 / 文字类同时输出成 CSS 变量 --theme-<key>。
 */
function theme_settings_schema_normalize($raw){
	$out = [];
	if(!is_array($raw))return $out;
	foreach($raw as $f){
		if(!is_array($f) || !isset($f['key'], $f['type']) || !is_string($f['key']) || !is_string($f['type']))continue;
		if(!preg_match('/^[a-z][a-z0-9_]{0,30}$/', $f['key']))continue;
		//html：原样输出的一段标记（只有管理员能改）；lines：多行文本，每行一条，卡片自己按 | 拆字段
		//menu：导航菜单项列表（JSON），只有导航栏卡片用，编辑器里是可拖动的列表（DEC-20261026-001）
		//code：一段代码（HTML / CSS / JS，lang 指定），编辑器里是带高亮的代码框；原样交给卡片，只有管理员能改
		if(!in_array($f['type'], ['text', 'textarea', 'html', 'lines', 'number', 'switch', 'select', 'color', 'image', 'menu', 'code'], true))continue;
		$item = [
			'key' => $f['key'],
			'type' => $f['type'],
			'label' => isset($f['label']) && is_scalar($f['label']) ? mb_substr((string)$f['label'], 0, 40, 'UTF-8') : $f['key'],
			'help' => isset($f['help']) && is_scalar($f['help']) ? mb_substr((string)$f['help'], 0, 200, 'UTF-8') : '',
			'default' => isset($f['default']) && is_scalar($f['default']) ? $f['default'] : '',
		];
		if($f['type'] === 'number'){
			$item['min'] = isset($f['min']) ? floatval($f['min']) : null;
			$item['max'] = isset($f['max']) ? floatval($f['max']) : null;
			$item['unit'] = isset($f['unit']) && is_string($f['unit']) && preg_match('/^(px|em|rem|%|s|ms|deg)?$/', $f['unit']) ? $f['unit'] : '';
		}
		if($f['type'] === 'code'){
			$item['lang'] = isset($f['lang']) && in_array($f['lang'], ['html', 'css', 'js'], true) ? $f['lang'] : 'html';
		}
		if($f['type'] === 'select'){
			$item['options'] = [];
			if(isset($f['options']) && is_array($f['options'])){
				foreach($f['options'] as $k => $v){
					if(is_scalar($v))$item['options'][(string)$k] = mb_substr((string)$v, 0, 40, 'UTF-8');
				}
			}
			if(!$item['options'])continue;
		}
		$out[] = $item;
		if(count($out) >= 60)break;
	}
	return $out;
}

/*
 * 主题自带的卡片（theme.json 的 blocks）：只有模板和配置项，没有 PHP，所以只能做纯展示的东西（横幅、说明块、链接组……）。
 * 每项 {"id":"hero","name":"首页横幅","scope":"front","pages":["index"],"container":false,"description":"…","schema":[…]}，
 * 卡片 id 是 theme.<id>，模板是主题包里的 templates/blocks/theme/<id>.mustache，模板拿得到 cfg、theme、site、slot_html。
 * 子主题继承父主题的卡片，同名的用子主题自己的。
 */
function theme_blocks_normalize($raw){
	$out = [];
	if(!is_array($raw))return $out;
	foreach($raw as $b){
		if(!is_array($b) || !isset($b['id']) || !is_string($b['id']) || !preg_match('/^[a-z][a-z0-9_]{0,40}$/', $b['id']))continue;
		$pages = ['*'];
		if(isset($b['pages']) && is_array($b['pages'])){
			$p = [];
			foreach($b['pages'] as $pg){
				if(is_string($pg) && layout_page_valid($pg))$p[] = $pg;
			}
			if($p)$pages = $p;
		}
		$out[$b['id']] = [
			'name' => isset($b['name']) && is_scalar($b['name']) && trim((string)$b['name']) !== '' ? mb_substr(trim((string)$b['name']), 0, 40, 'UTF-8') : $b['id'],
			'description' => isset($b['description']) && is_scalar($b['description']) ? mb_substr((string)$b['description'], 0, 200, 'UTF-8') : '',
			'scope' => isset($b['scope']) && in_array($b['scope'], ['front', 'admin', 'both'], true) ? $b['scope'] : 'front',
			'pages' => $pages,
			'container' => !empty($b['container']),
			'schema' => isset($b['schema']) && is_array($b['schema']) ? $b['schema'] : [],
		];
		if(count($out) >= 50)break;
	}
	return $out;
}

/**
 * 全部主题：id => 清单。内置的在前，上传的同名（理论上安装时就拒绝了）不覆盖内置。
 * 一次请求只扫一遍目录。
 */
function theme_registry($refresh = false){
	static $cache = null;
	if($cache !== null && !$refresh)return $cache;
	$cache = [];
	foreach([[theme_builtin_dir(), 'builtin'], [theme_upload_dir(), 'upload']] as $pair){
		list($base, $source) = $pair;
		if(!is_dir($base))continue;
		$dirs = @scandir($base);
		if(!$dirs)continue;
		foreach($dirs as $d){
			if($d === '.' || $d === '..' || !theme_id_valid($d) || !is_dir($base.$d))continue;
			if(isset($cache[$d]))continue;
			$m = theme_manifest_load($base.$d, $source);
			if(is_array($m))$cache[$d] = $m;
		}
	}
	uasort($cache, function($a, $b){
		if($a['_source'] !== $b['_source'])return $a['_source'] === 'builtin' ? -1 : 1;
		if($a['sort'] !== $b['sort'])return $a['sort'] < $b['sort'] ? -1 : 1;
		return strcmp($a['id'], $b['id']);
	});
	return $cache;
}

function theme_get($id){
	$all = theme_registry();
	return (is_string($id) && isset($all[$id])) ? $all[$id] : null;
}

/* 当前启用的主题：配置里没有或不认识就用默认外观 */
function theme_active_id(){
	global $conf;
	//后台布局编辑器的预览：令牌里写着正在编辑的主题，这个浏览器就按那个主题显示
	$preview = theme_preview_theme();
	if($preview !== '')return $preview;
	$id = isset($conf['site_theme']) ? (string)$conf['site_theme'] : '';
	if(theme_get($id))return $id;
	$def = default_site_theme();
	return theme_get($def) ? $def : $id;
}

/* 继承链：自己 → 父主题 → ……，最多 4 层，遇到环或不存在的父主题就停 */
function theme_chain($id){
	$chain = [];
	while($id !== '' && count($chain) < 4 && !in_array($id, $chain, true)){
		$m = theme_get($id);
		if(!$m)break;
		$chain[] = $id;
		$id = $m['extends'];
	}
	return $chain;
}

/* 主题包里静态文件的地址。$base 是页面到站点根的相对前缀（后台页面传 '../'） */
function theme_asset_url($id, $path, $base = './'){
	$m = theme_get($id);
	if(!$m || !theme_asset_path_valid($path))return '';
	$root = $m['_source'] === 'builtin' ? 'themes/' : 'content/themes/';
	$file = $m['_dir'].$path;
	$ver = is_file($file) ? substr(md5(filemtime($file).'|'.filesize($file)), 0, 8) : '0';
	return $base.$root.$id.'/'.$path.'?v='.$ver;
}

/* ============ 主题设置 ============ */

/**
 * 站长给某个主题保存的设置（pre_theme.settings），按 schema 补默认值、去掉不认识的键。
 * 表还不存在（没升级到 1034）时当成没设置过。
 */
function theme_settings($id){
	static $memo = [];
	if(isset($memo[$id]))return $memo[$id];
	$m = theme_get($id);
	$saved = [];
	if($m){
		$row = theme_db_row('SELECT settings FROM pre_theme WHERE id=:id LIMIT 1', [':id'=>$id]);
		if($row && $row['settings'] !== ''){
			$tmp = json_decode($row['settings'], true);
			if(is_array($tmp))$saved = $tmp;
		}
	}
	$out = [];
	if($m){
		foreach($m['settings'] as $f){
			$out[$f['key']] = theme_setting_clean($f, array_key_exists($f['key'], $saved) ? $saved[$f['key']] : $f['default']);
		}
	}
	return $memo[$id] = $out;
}

function theme_setting_clean(array $f, $v){
	switch($f['type']){
		case 'switch':
			return !empty($v) && $v !== '0' ? 1 : 0;
		case 'number':
			$v = is_numeric($v) ? $v + 0 : (is_numeric($f['default']) ? $f['default'] + 0 : 0);
			if($f['min'] !== null && $v < $f['min'])$v = $f['min'];
			if($f['max'] !== null && $v > $f['max'])$v = $f['max'];
			return $v;
		case 'color':
			$v = is_string($v) ? trim($v) : '';
			return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$f['default']) ? strtolower($f['default']) : '');
		case 'select':
			$v = is_scalar($v) ? (string)$v : '';
			return array_key_exists($v, $f['options']) ? $v : (string)key($f['options']);
		case 'menu':
			return nav_menu_clean($v);
		case 'image':
			$v = is_string($v) ? trim($v) : '';
			//图片地址只收站内相对路径或 http(s)，挡掉 javascript:、data: 之类带别的协议头的
			return ($v === '' || preg_match('#^(https?://[^"\'<>\s]+|(?![A-Za-z][A-Za-z0-9+.\-]*:)[^"\'<>\s]+)$#', $v)) ? mb_substr($v, 0, 500, 'UTF-8') : '';
		default:
			$v = is_scalar($v) ? (string)$v : '';
			if($f['type'] === 'code')return mb_substr($v, 0, 200000, 'UTF-8');
			return mb_substr($v, 0, in_array($f['type'], ['textarea', 'html', 'lines'], true) ? 20000 : 300, 'UTF-8');
	}
}

/* 主题设置输出成 CSS 变量；没有可输出的项返回空串 */
function theme_settings_css($id, $selector = ':root'){
	$m = theme_get($id);
	if(!$m || !$m['settings'])return '';
	$vals = theme_settings($id);
	$css = '';
	foreach($m['settings'] as $f){
		$v = $vals[$f['key']];
		if($f['type'] === 'color' && $v !== ''){
			$css .= '--theme-'.str_replace('_', '-', $f['key']).':'.$v.';';
		}elseif($f['type'] === 'number'){
			$css .= '--theme-'.str_replace('_', '-', $f['key']).':'.$v.$f['unit'].';';
		}elseif($f['type'] === 'image' && $v !== ''){
			$css .= '--theme-'.str_replace('_', '-', $f['key']).':url("'.str_replace(['"', '\\', ')'], '', $v).'");';
		}
	}
	return $css === '' ? '' : $selector.'{'.$css.'}';
}

/* ============ 数据库小工具：表还没建（升级前）时一律当成没有数据 ============ */

function theme_db_ready(){
	global $conf;
	return isset($conf['version']) && intval($conf['version']) >= 1034;
}

function theme_db_row($sql, array $params){
	global $DB;
	if(!theme_db_ready() || !$DB)return null;
	try{
		$row = $DB->getRow($sql, $params);
	}catch(\Exception $e){
		return null;
	}
	return is_array($row) ? $row : null;
}

/* ============ 模板 ============ */

/**
 * 按主题继承链 + 系统默认目录找模板。名字先过 theme_name_valid()，
 * 拼出来的路径再 realpath 一次确认没跑出这几个目录。
 */
class ThemeTemplateLoader implements \Mustache\Loader
{
	private $dirs;
	private $found = [];

	public function __construct(array $dirs){
		$this->dirs = [];
		foreach($dirs as $d){
			$real = realpath($d);
			if($real !== false && is_dir($real))$this->dirs[] = rtrim(str_replace('\\', '/', $real), '/').'/';
		}
	}

	public function path($name){
		if(array_key_exists($name, $this->found))return $this->found[$name];
		$hit = null;
		if(theme_name_valid($name)){
			foreach($this->dirs as $d){
				$file = $d.$name.'.mustache';
				if(!is_file($file))continue;
				$real = str_replace('\\', '/', (string)realpath($file));
				if($real !== '' && strpos($real, $d) === 0){
					$hit = $real;
					break;
				}
			}
		}
		return $this->found[$name] = $hit;
	}

	public function load($name){
		$file = $this->path($name);
		if($file === null)throw new \Mustache\Exception\UnknownTemplateException($name);
		$src = file_get_contents($file);
		if(strncmp($src, "\xEF\xBB\xBF", 3) === 0)$src = substr($src, 3);
		return $src;
	}
}

/* 某个主题的模板查找目录：主题链各层的 templates/，最后是系统默认 */
function theme_tpl_dirs($id){
	$dirs = [];
	foreach(theme_chain($id) as $tid){
		$m = theme_get($tid);
		$dirs[] = $m['_dir'].'templates/';
	}
	$dirs[] = theme_system_tpl_dir();
	return $dirs;
}

function theme_tpl_cache_dir(){
	static $dir = null;
	if($dir !== null)return $dir;
	$tmp = sys_get_temp_dir();
	$dir = '';
	if($tmp && is_dir($tmp) && is_writable($tmp)){
		$d = rtrim($tmp, '/\\').'/mpimg_tpl_'.md5(SYSTEM_ROOT);
		if(is_dir($d) || @mkdir($d, 0700))$dir = is_writable($d) ? $d : '';
	}
	return $dir;
}

/**
 * 每个主题一个 Mustache 引擎。$system_only 为 true 时只看系统默认模板
 * （主题模板出错时退回用）。
 */
function theme_engine($id, $system_only = false){
	static $engines = [];
	$key = $system_only ? '' : $id;
	if(isset($engines[$key]))return $engines[$key];
	$loader = new ThemeTemplateLoader($system_only ? [theme_system_tpl_dir()] : theme_tpl_dirs($id));
	$opt = [
		'loader' => $loader,
		'partials_loader' => $loader,
		'strict_callables' => true,
		'entity_flags' => ENT_QUOTES,
		'charset' => 'UTF-8',
		'pragmas' => [\Mustache\Engine::PRAGMA_BLOCKS],
	];
	$cache = theme_tpl_cache_dir();
	if($cache !== '')$opt['cache'] = $cache;
	return $engines[$key] = new \Mustache\Engine($opt);
}

function theme_tpl_exists($name, $id = null){
	if($id === null)$id = theme_active_id();
	return theme_engine($id)->getLoader()->path($name) !== null;
}

/**
 * 渲染一个模板。主题里的模板写错（语法错、引用了不存在的 partial）时，
 * 记一条日志再用系统默认模板重来一次，不让整页白屏；系统模板也错就抛出去（那是程序的 bug）。
 */
function theme_tpl_render($name, array $data, $id = null){
	if($id === null)$id = theme_active_id();
	try{
		return theme_engine($id)->render($name, $data);
	}catch(\Mustache\Exception $e){
		$loader = theme_engine($id)->getLoader();
		$file = $loader->path($name);
		if($file !== null && strpos($file, str_replace('\\', '/', realpath(theme_system_tpl_dir()))) === 0)throw $e;
		theme_error_log($id, $name, $e->getMessage());
		//系统里没有同名模板可退（主题自带卡片的模板）：这块不输出
		if(theme_engine($id, true)->getLoader()->path($name) === null)return '';
		return theme_engine($id, true)->render($name, $data);
	}
}

/* 主题模板出错的记录：一次请求同一个模板只记一次，后台主题管理页读最近几条 */
function theme_error_log($id, $name, $msg){
	static $seen = [];
	if(isset($seen[$id.'|'.$name]))return;
	$seen[$id.'|'.$name] = 1;
	$dir = theme_tpl_cache_dir();
	if($dir === '')return;
	$line = date('Y-m-d H:i:s')."\t".$id."\t".$name."\t".str_replace(["\r", "\n", "\t"], ' ', mb_substr($msg, 0, 300, 'UTF-8'))."\n";
	$file = $dir.'/errors.log';
	if(is_file($file) && filesize($file) > 64 * 1024)@unlink($file);
	@file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/* ============ 卡片 ============ */

/* 卡片 id：<组>.<名>，都是小写字母数字和下划线 */
function block_id_valid($id){
	return is_string($id) && preg_match('/^[a-z][a-z0-9_]{0,30}\.[a-z][a-z0-9_]{0,40}$/', $id) === 1;
}

/**
 * 取一张卡片的定义：includes/blocks/<组>/<名>.php 返回的数组。按需加载，一次请求只 include 一次。
 *
 * 定义里的键：
 *   name       后台编辑器里显示的名字
 *   scope      front（前台）/ admin（后台）/ both（两边都能用）
 *   group      编辑器里的分组名
 *   pages      能放在哪些页面，['*'] 表示任意页面
 *   container  true 表示能放子卡片，子卡片渲染结果以 slot_html 传给模板
 *   schema     可配置项，格式同主题 settings（text/textarea/number/switch/select/color/image）
 *   data       function(array $cfg, array &$ctx)，返回给模板的数据数组；返回 null 表示这次不输出
 *   template   模板名，默认 blocks/<组>/<名>
 *
 * theme.<名> 是主题自带的卡片（theme_blocks_normalize），按 $theme（默认当前外观）的继承链找。
 */
function block_get($id, $theme = null){
	static $defs = [];
	if(is_string($id) && strncmp($id, 'theme.', 6) === 0){
		if($theme === null)$theme = theme_active_id();
		$key = $theme.'|'.$id;
		if(array_key_exists($key, $defs))return $defs[$key];
		return $defs[$key] = block_get_theme($id, $theme);
	}
	if(array_key_exists($id, $defs))return $defs[$id];
	if(!block_id_valid($id))return $defs[$id] = null;
	list($g, $n) = explode('.', $id, 2);
	$file = theme_block_dir().$g.'/'.$n.'.php';
	if(!is_file($file))return $defs[$id] = null;
	$def = include $file;
	if(!is_array($def))return $defs[$id] = null;
	$def['id'] = $id;
	$def += ['name'=>$id, 'scope'=>'front', 'group'=>'', 'pages'=>['*'], 'container'=>false, 'schema'=>[], 'data'=>null, 'template'=>'blocks/'.$g.'/'.$n, 'description'=>''];
	$def['schema'] = theme_settings_schema_normalize($def['schema']);
	return $defs[$id] = $def;
}

function block_get_theme($id, $theme){
	if(!block_id_valid($id))return null;
	$n = substr($id, 6);
	foreach(theme_chain($theme) as $tid){
		$m = theme_get($tid);
		if(!$m || !isset($m['blocks'][$n]))continue;
		$b = $m['blocks'][$n];
		return [
			'id' => $id,
			'name' => $b['name'],
			'description' => $b['description'],
			'scope' => $b['scope'],
			'group' => '主题：'.$m['name'],
			'pages' => $b['pages'],
			'container' => $b['container'],
			'schema' => theme_settings_schema_normalize($b['schema']),
			'data' => null,
			'template' => 'blocks/theme/'.$n,
			'theme_block' => $tid,
		];
	}
	return null;
}

/* 全部卡片（后台编辑器用），按 scope 过滤；给了 $theme 再加上这个主题（含继承链）自带的卡片 */
function block_registry($scope = null, $theme = null){
	$out = [];
	$base = theme_block_dir();
	foreach((array)@glob($base.'*/*.php') as $file){
		$id = basename(dirname($file)).'.'.basename($file, '.php');
		if(strncmp($id, 'theme.', 6) === 0)continue;
		$def = block_get($id);
		if($def && ($scope === null || $def['scope'] === $scope || $def['scope'] === 'both'))$out[$id] = $def;
	}
	if($theme !== null){
		foreach(theme_chain($theme) as $tid){
			$m = theme_get($tid);
			foreach($m ? array_keys($m['blocks']) : [] as $n){
				$def = block_get('theme.'.$n, $theme);
				if($def && !isset($out[$def['id']]) && ($scope === null || $def['scope'] === $scope || $def['scope'] === 'both'))$out[$def['id']] = $def;
			}
		}
	}
	ksort($out);
	return $out;
}

/* 卡片配置：按 schema 补默认值、清洗类型；schema 里没有的键丢掉 */
function block_config(array $def, $cfg){
	$cfg = is_array($cfg) ? $cfg : [];
	$out = [];
	foreach($def['schema'] as $f){
		$out[$f['key']] = theme_setting_clean($f, array_key_exists($f['key'], $cfg) ? $cfg[$f['key']] : $f['default']);
	}
	return $out;
}

/* 渲染一个布局节点（含子节点）。卡片不存在、被隐藏、不适用于当前页面、data 返回 null 都输出空串 */
function block_render_node($node, array &$ctx){
	if(!is_array($node) || !empty($node['hidden']) || !isset($node['block']))return '';
	$def = block_get($node['block'], $ctx['theme_id']);
	if(!$def || ($def['scope'] !== 'both' && $def['scope'] !== $ctx['scope']))return '';
	if($def['pages'] !== ['*'] && !in_array($ctx['page'], $def['pages'], true))return '';
	//通用选项里的显示条件（给谁看、什么时间段）：不满足就不输出；编辑器预览里照样画出来，标上虚线
	$opts = isset($node['opts']) && is_array($node['opts']) ? $node['opts'] : [];
	$cond = $opts ? layout_opts_hidden_reason($opts, $ctx) : '';
	if($cond !== '' && empty($ctx['preview']))return '';
	$cfg = block_config($def, isset($node['config']) ? $node['config'] : []);
	$nid = isset($node['id']) && preg_match('/^[A-Za-z0-9_\-]{1,40}$/', (string)$node['id']) ? (string)$node['id'] : '';
	if(!empty($ctx['preview']) && $nid !== '')$cfg = layout_inline_edit_mark($cfg, $def, $nid);
	//先问卡片自己这次要不要输出，不输出的容器连子卡片都不渲染：
	//子卡片的 data 回调可能有副作用（登记页脚后的脚本），比如个人中心没打开的页签
	$data = [];
	if(is_callable($def['data'])){
		$data = call_user_func_array($def['data'], [$cfg, &$ctx]);
		if($data === null)return '';
		if(!is_array($data))$data = [];
	}
	$slot = '';
	if($def['container'] && !empty($node['children']) && is_array($node['children'])){
		$ctx['depth'] = isset($ctx['depth']) ? $ctx['depth'] + 1 : 1;
		if($ctx['depth'] <= 8)$slot = block_render_nodes($node['children'], $ctx);
		$ctx['depth']--;
	}
	$data['cfg'] = $cfg;
	//主题自带的卡片没有 PHP 拆不了多行配置：lines 类型的配置项另外给一份拆好的 lines.<键>，每行按 | 分成 c1～c6
	if(!empty($def['theme_block'])){
		foreach($def['schema'] as $f){
			if($f['type'] !== 'lines')continue;
			$rows = [];
			foreach(preg_split('/\r?\n/', (string)$cfg[$f['key']]) as $line){
				if(trim($line) === '')continue;
				$row = ['text'=>trim($line)];
				foreach(array_slice(explode('|', $line), 0, 6) as $i => $col)$row['c'.($i + 1)] = trim($col);
				$rows[] = $row;
			}
			$data['lines'][$f['key']] = $rows;
		}
	}
	$data['slot_html'] = $slot;
	$data['theme'] = $ctx['theme_vars'];
	$data['site'] = $ctx['site_vars'];
	//主题自带的卡片忘了带模板：记一条错误，不输出
	if(!empty($def['theme_block']) && !theme_tpl_exists($def['template'], $ctx['theme_id'])){
		theme_error_log($ctx['theme_id'], $def['template'], '主题卡片 '.$def['id'].' 缺少模板 templates/'.$def['template'].'.mustache');
		return '';
	}
	$html = theme_tpl_render($def['template'], $data, $ctx['theme_id']);
	if(!empty($ctx['preview']))$html = layout_inline_edit_spans($html);
	if($opts)$html = layout_opts_wrap($html, $opts, $cond);
	if(!empty($ctx['preview'])){
		$html = '<!--pan-block:'.$nid.':'.$def['id'].'-->'.$html.'<!--/pan-block:'.$nid.'-->';
	}
	return $html;
}

function block_render_nodes(array $nodes, array &$ctx){
	$html = '';
	foreach($nodes as $node)$html .= block_render_node($node, $ctx);
	return $html;
}

/* ============ 预览里点文字直接改（DEC-20261026-002） ============ */

/*
 * 只在编辑器预览里：卡片的单行文字配置项（text 类型）前后加不可见的定位符 U+2063 节点:键 U+2062 … U+2064，
 * 渲染完由 layout_inline_edit_spans() 把落在正文里的换成 <span data-pan-edit="节点:键">，
 * layout-preview.js 让它们可以点了直接改，改完把新文字发回编辑器。
 * 链接、图标、类名、提示占位这类不是给人看的字段不加；含 {占位} 的不加（显示出来的是替换后的样子，改了会丢占位）；空值不加。
 */
function layout_inline_edit_mark(array $cfg, array $def, $nid){
	foreach($def['schema'] as $f){
		if($f['type'] !== 'text' || preg_match('/link|href|url|placeholder|icon|cls|class|style|dom_id/', $f['key']))continue;
		$v = isset($cfg[$f['key']]) ? $cfg[$f['key']] : '';
		if(!is_string($v) || $v === '' || strpos($v, '{') !== false || preg_match('/[\x{2062}-\x{2064}]/u', $v))continue;
		$cfg[$f['key']] = "\u{2063}".$nid.':'.$f['key']."\u{2062}".$v."\u{2064}";
	}
	return $cfg;
}

/* 定位符换成可编辑的 span；落在标签属性里、或 option/textarea/title/script/style 里的只去掉定位符 */
function layout_inline_edit_spans($html){
	if(strpos($html, "\u{2063}") === false)return $html;
	return preg_replace_callback('/\x{2063}([A-Za-z0-9_\-]{1,40}):([a-z0-9_]{1,31})\x{2062}(.*?)\x{2064}/su', function($m) use ($html){
		$text = $m[3][0];
		$before = substr($html, 0, $m[0][1]);
		$lt = strrpos($before, '<');
		$gt = strrpos($before, '>');
		if($lt !== false && ($gt === false || $lt > $gt))return $text;
		if(preg_match_all('#<(/?)(option|textarea|title|script|style)\b#i', $before, $mm) && $mm[1][count($mm[1]) - 1] === '')return $text;
		return '<span class="pan-edit" data-pan-edit="'.$m[1][0].':'.$m[2][0].'">'.$text.'</span>';
	}, $html, -1, $count, PREG_OFFSET_CAPTURE);
}

/* ============ 卡片通用选项（DEC-20261026-002） ============ */

/*
 * 每张卡片（包括后台面板）都有的一组选项，存在布局节点的 opts 里，和卡片自己的 config 分开。
 * 显示条件（who / from / to）在服务端判断，不满足就整张不输出；
 * 其余是排版和外观，设了任何一项才在卡片外面包一层 <div class="pan-blk">，一项都没设时输出和原来逐字一样。
 * 颜色除了 #rrggbb 还可以选外观自己的颜色（primary 主色、surface 面板色……），换外观跟着变。
 */
function layout_opts_schema(){
	$px = function($list){ $o = [''=>'默认']; foreach($list as $v)$o[(string)$v] = $v.' px'; return $o; };
	return [
		['key'=>'who', 'type'=>'select', 'group'=>'显示条件', 'label'=>'给谁看', 'default'=>'', 'front'=>1, 'options'=>[''=>'所有人', 'login'=>'仅登录用户', 'guest'=>'仅游客（未登录）', 'member'=>'仅付费会员', 'nonmember'=>'非付费会员（含游客）']],
		['key'=>'device', 'type'=>'select', 'group'=>'显示条件', 'label'=>'在哪显示', 'default'=>'', 'options'=>[''=>'电脑和手机', 'pc'=>'只在电脑上（宽度 ≥ 768px）', 'mobile'=>'只在手机上（宽度 < 768px）'], 'help'=>'编辑器右边的预览框比较窄时算手机；看电脑效果点预览上方的「新窗口打开」'],
		['key'=>'from', 'type'=>'datetime', 'group'=>'显示条件', 'label'=>'从这个时间开始显示', 'default'=>'', 'help'=>'留空不限，按网站时间'],
		['key'=>'to', 'type'=>'datetime', 'group'=>'显示条件', 'label'=>'到这个时间停止显示', 'default'=>''],
		['key'=>'mt', 'type'=>'select', 'group'=>'间距与宽度', 'label'=>'上边距', 'default'=>'', 'options'=>$px([0, 8, 16, 24, 40, 64])],
		['key'=>'mb', 'type'=>'select', 'group'=>'间距与宽度', 'label'=>'下边距', 'default'=>'', 'options'=>$px([0, 8, 16, 24, 40, 64])],
		['key'=>'pad', 'type'=>'select', 'group'=>'间距与宽度', 'label'=>'内边距', 'default'=>'', 'options'=>$px([8, 16, 24, 40])],
		['key'=>'maxw', 'type'=>'number', 'group'=>'间距与宽度', 'label'=>'最大宽度', 'default'=>0, 'min'=>0, 'max'=>4000, 'unit'=>'px', 'help'=>'0 不限'],
		['key'=>'center', 'type'=>'switch', 'group'=>'间距与宽度', 'label'=>'限宽后居中', 'default'=>0],
		['key'=>'align', 'type'=>'select', 'group'=>'外观', 'label'=>'文字对齐', 'default'=>'', 'options'=>[''=>'默认', 'left'=>'靠左', 'center'=>'居中', 'right'=>'靠右']],
		['key'=>'bg', 'type'=>'color', 'group'=>'外观', 'label'=>'背景色', 'default'=>'', 'presets'=>[''=>'不设', 'surface'=>'外观的面板色', 'primary'=>'外观的主色', 'soft'=>'淡灰', 'transparent'=>'透明']],
		['key'=>'color', 'type'=>'color', 'group'=>'外观', 'label'=>'文字颜色', 'default'=>'', 'presets'=>[''=>'不设', 'primary'=>'外观的主色', 'white'=>'白色', 'muted'=>'浅一些']],
		['key'=>'radius', 'type'=>'select', 'group'=>'外观', 'label'=>'圆角', 'default'=>'', 'options'=>$px([0, 4, 8, 12, 20])],
		['key'=>'shadow', 'type'=>'select', 'group'=>'外观', 'label'=>'阴影', 'default'=>'', 'options'=>[''=>'无', 'sm'=>'浅', 'md'=>'中', 'lg'=>'深']],
		['key'=>'border', 'type'=>'select', 'group'=>'外观', 'label'=>'边框', 'default'=>'', 'options'=>[''=>'无', 'line'=>'细线', 'primary'=>'主色线']],
		['key'=>'cls', 'type'=>'text', 'group'=>'高级', 'label'=>'CSS 类名', 'default'=>'', 'help'=>'加在外层 div 上，多个用空格隔开'],
		['key'=>'css', 'type'=>'text', 'group'=>'高级', 'label'=>'行内样式', 'default'=>'', 'help'=>'如 font-size:18px;，不能有引号、尖括号、url()'],
	];
}

/* 整理一个节点的 opts：只留认识的键和合法的值，和默认值一样的不存 */
function layout_opts_clean($raw){
	$out = [];
	if(!is_array($raw))return $out;
	foreach(layout_opts_schema() as $f){
		$k = $f['key'];
		if(!isset($raw[$k]) || !is_scalar($raw[$k]))continue;
		$v = trim((string)$raw[$k]);
		switch($f['type']){
			case 'select':
				if(!array_key_exists($v, $f['options']))$v = '';
				break;
			case 'switch':
				$v = ($v !== '' && $v !== '0') ? 1 : 0;
				break;
			case 'number':
				$v = is_numeric($v) ? max($f['min'], min($f['max'], intval($v))) : 0;
				break;
			case 'datetime':
				$v = str_replace('T', ' ', $v);
				if(!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $v))$v = '';
				break;
			case 'color':
				if(!array_key_exists($v, $f['presets']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $v))$v = '';
				$v = strtolower($v);
				break;
			default:
				if($k === 'cls')$v = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9_\- ]+/', '', $v)));
				//行内样式带了能跳出属性或注入的东西就整条不要（同容器卡片）
				if($k === 'css')$v = preg_match('/url\s*\(|expression|javascript|[<>"\'\\\\]/i', $v) ? '' : $v;
				$v = mb_substr($v, 0, 300, 'UTF-8');
		}
		if($v !== '' && $v !== 0 && $v !== $f['default'])$out[$k] = $v;
	}
	return $out;
}

/* 显示条件不满足的原因（给编辑器预览标注用）；满足返回空串 */
function layout_opts_hidden_reason(array $o, array $ctx){
	global $islogin2;
	if(!empty($o['who']) && $ctx['scope'] === 'front'){
		$login = !empty($islogin2);
		$member = false;
		if($login && function_exists('current_level')){
			$lv = current_level();
			//付费等级（站长自建的）和管理员等级都算会员
			$member = in_array(intval($lv['type']), [0, 3], true);
		}
		$labels = ['login'=>'仅登录用户可见', 'guest'=>'仅游客可见', 'member'=>'仅付费会员可见', 'nonmember'=>'仅非付费会员可见'];
		$ok = ['login'=>$login, 'guest'=>!$login, 'member'=>$member, 'nonmember'=>!$member];
		if(isset($ok[$o['who']]) && !$ok[$o['who']])return $labels[$o['who']];
	}
	$now = date('Y-m-d H:i');
	if(!empty($o['from']) && $now < (strlen($o['from']) === 10 ? $o['from'].' 00:00' : $o['from']))return $o['from'].' 起显示';
	if(!empty($o['to']) && $now >= (strlen($o['to']) === 10 ? $o['to'].' 23:59' : $o['to']))return '已于 '.$o['to'].' 停止显示';
	return '';
}

/*
 * 引擎自己要的一小段样式（通用选项的电脑/手机显示、分栏）：同一段一个页面只输出一次，第一次用到时随卡片输出。
 * 不写进外观样式表，是因为后台、无外壳的代码页不加载前台样式表，也免得没用到的页面多出样式。
 */
function layout_inline_css_once($key, $css){
	if(!empty($GLOBALS['_pan_inline_css'][$key]))return '';
	$GLOBALS['_pan_inline_css'][$key] = 1;
	return '<style>'.$css.'</style>';
}

/* 通用选项里的颜色换成 CSS 值 */
function layout_opts_color($v, $what){
	$map = $what === 'bg'
		? ['surface'=>'var(--surface,rgba(127,127,127,.06))', 'primary'=>'var(--primary,var(--admin-primary,#2563eb))', 'soft'=>'rgba(127,127,127,.08)', 'transparent'=>'transparent']
		: ['primary'=>'var(--primary,var(--admin-primary,#2563eb))', 'white'=>'#fff', 'muted'=>'var(--muted,rgba(127,127,127,.95))'];
	return isset($map[$v]) ? $map[$v] : (preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : '');
}

/* 按通用选项给卡片包一层；只有显示条件的不包。$cond 非空是预览里本来看不到的卡片，加虚线和说明 */
function layout_opts_wrap($html, array $o, $cond = ''){
	$style = '';
	if(isset($o['mt']))$style .= 'margin-top:'.intval($o['mt']).'px;';
	if(isset($o['mb']))$style .= 'margin-bottom:'.intval($o['mb']).'px;';
	if(isset($o['pad']))$style .= 'padding:'.intval($o['pad']).'px;';
	if(!empty($o['maxw']))$style .= 'max-width:'.intval($o['maxw']).'px;';
	if(!empty($o['center']))$style .= 'margin-left:auto;margin-right:auto;';
	if(!empty($o['align']))$style .= 'text-align:'.$o['align'].';';
	if(!empty($o['bg']) && ($c = layout_opts_color($o['bg'], 'bg')) !== '')$style .= 'background:'.$c.';';
	if(!empty($o['color']) && ($c = layout_opts_color($o['color'], 'color')) !== '')$style .= 'color:'.$c.';';
	if(isset($o['radius']))$style .= 'border-radius:'.intval($o['radius']).'px;';
	$shadows = ['sm'=>'0 1px 3px rgba(0,0,0,.08)', 'md'=>'0 4px 14px rgba(0,0,0,.10)', 'lg'=>'0 12px 32px rgba(0,0,0,.14)'];
	if(!empty($o['shadow']) && isset($shadows[$o['shadow']]))$style .= 'box-shadow:'.$shadows[$o['shadow']].';';
	if(!empty($o['border']))$style .= 'border:1px solid '.($o['border'] === 'primary' ? 'var(--primary,var(--admin-primary,#2563eb))' : 'var(--line,rgba(127,127,127,.25))').';';
	if(!empty($o['css']))$style .= $o['css'];
	$cls = '';
	if(!empty($o['device']))$cls .= $o['device'] === 'pc' ? ' pan-only-pc' : ' pan-only-m';
	if(!empty($o['cls']))$cls .= ' '.$o['cls'];
	if($cond !== ''){
		$html = '<div class="pan-preview-cond" style="outline:2px dashed rgba(245,158,11,.75);outline-offset:2px;opacity:.6;position:relative"><span style="position:absolute;right:0;top:-1.5em;font:12px/1.4 sans-serif;color:#b45309;background:#fffbeb;padding:0 6px;border-radius:3px;z-index:5">'.htmlspecialchars($cond, ENT_QUOTES, 'UTF-8').'</span>'.$html.'</div>';
	}
	if($style === '' && $cls === '')return $html;
	//只在电脑 / 只在手机靠一小段样式
	$pre = empty($o['device']) ? '' : layout_inline_css_once('device', '@media (max-width:767px){.pan-only-pc{display:none!important}}@media (min-width:768px){.pan-only-m{display:none!important}}');
	return $pre.'<div class="pan-blk'.htmlspecialchars($cls, ENT_QUOTES, 'UTF-8').'"'.($style !== '' ? ' style="'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'"' : '').'>'.$html.'</div>';
}

/* ============ 布局 ============ */

/* 一次请求内的共享计算：同一个统计多张卡片都要用时只算一次 */
function theme_memo($key, $fn){
	static $memo = [];
	if(!array_key_exists($key, $memo))$memo[$key] = $fn();
	return $memo[$key];
}

/**
 * 整理布局节点：只留认识的键，补 id，限制层数和总数，防止后台存进来的 JSON 失控。
 */
function layout_normalize($nodes, $depth = 0, &$count = 0){
	$out = [];
	if(!is_array($nodes) || $depth > 8)return $out;
	foreach($nodes as $n){
		if(!is_array($n) || !isset($n['block']) || !block_id_valid($n['block']))continue;
		if(++$count > 400)break;
		$item = [
			'id' => (isset($n['id']) && preg_match('/^[A-Za-z0-9_\-]{1,40}$/', (string)$n['id'])) ? (string)$n['id'] : 'n'.$count,
			'block' => $n['block'],
			'config' => (isset($n['config']) && is_array($n['config'])) ? $n['config'] : [],
		];
		if(!empty($n['hidden']))$item['hidden'] = 1;
		if(isset($n['opts']) && is_array($n['opts']) && ($o = layout_opts_clean($n['opts'])))$item['opts'] = $o;
		if(isset($n['children']) && is_array($n['children']))$item['children'] = layout_normalize($n['children'], $depth + 1, $count);
		$out[] = $item;
	}
	return $out;
}

/* 页面名：前台 index/upload/…，后台以 admin/ 开头，公共外壳是 _shell / admin/_shell */
function layout_page_valid($page){
	return is_string($page) && preg_match('#^(admin/)?_?[a-z][a-z0-9_\-]{0,40}$#', $page) === 1;
}

function layout_read_json($file){
	if(!is_file($file))return null;
	$raw = @file_get_contents($file);
	if($raw === false)return null;
	if(strncmp($raw, "\xEF\xBB\xBF", 3) === 0)$raw = substr($raw, 3);
	$data = json_decode($raw, true);
	if(!is_array($data))return null;
	return isset($data['nodes']) && is_array($data['nodes']) ? $data['nodes'] : $data;
}

/*
 * 主题（含继承链）自带的默认布局；没有的话看系统里有没有按主题标记准备的默认布局
 * （layouts/<页面>@<标记>.json，比如工作台家族共用的 _shell@studio.json；标记从后往前找，
 * theme.json 里越靠后的标记越具体），再没有就是系统默认 layouts/<页面>.json。
 */
function layout_default($page, $id){
	$flags = [];
	foreach(theme_chain($id) as $tid){
		$m = theme_get($tid);
		$nodes = layout_read_json($m['_dir'].'layouts/'.$page.'.json');
		if($nodes !== null)return layout_normalize($nodes);
		$flags = array_merge($flags, array_reverse($m['flags']));
	}
	$sys = theme_system_layout_dir();
	foreach(array_unique($flags) as $f){
		$nodes = layout_read_json($sys.$page.'@'.$f.'.json');
		if($nodes !== null)return layout_normalize($nodes);
	}
	$nodes = layout_read_json($sys.$page.'.json');
	if($nodes !== null)return layout_normalize($nodes);
	//自定义页面（page-<地址名>）没有专门的默认布局时，用主题 / 系统给自定义页面准备的通用默认布局 page.json
	if(strncmp($page, 'page-', 5) === 0)return layout_default('page', $id);
	return [];
}

/**
 * 某个主题某个页面现在用的布局：$draft 为 true 时先看草稿（后台预览用）。
 * 站长没在后台改过（pre_layout 没有这行）就用主题默认布局。
 */
/*
 * 布局按哪个主题存：自定义页面（page-<地址名>）是网站内容，所有外观共用一份，存在 theme='*' 下；
 * 其余页面跟外观走（各外观的结构不一样）。DEC-20261026-001
 */
function layout_store_theme($page, $id){
	return strncmp($page, 'page-', 5) === 0 ? '*' : $id;
}

function layout_load($page, $id, $draft = false){
	if(!layout_page_valid($page))return [];
	$row = theme_db_row('SELECT draft,published FROM pre_layout WHERE theme=:t AND page=:p LIMIT 1', [':t'=>layout_store_theme($page, $id), ':p'=>$page]);
	if($row){
		foreach($draft ? ['draft', 'published'] : ['published'] as $col){
			if(isset($row[$col]) && $row[$col] !== '' && $row[$col] !== null){
				$nodes = json_decode($row[$col], true);
				if(is_array($nodes))return layout_normalize($nodes);
			}
		}
	}
	return layout_default($page, $id);
}

/* ============ 渲染入口 ============ */

/* 给模板的主题信息：theme.id、theme.is_<flag>、theme.settings.<key> */
function theme_vars($id){
	static $memo = [];
	if(isset($memo[$id]))return $memo[$id];
	$m = theme_get($id);
	$vars = ['id'=>$id, 'name'=>$m ? $m['name'] : $id, 'settings'=>theme_settings($id)];
	$vars['is_'.str_replace('-', '_', $id)] = true;
	if($m){
		foreach($m['flags'] as $f)$vars['is_'.$f] = true;
	}
	return $memo[$id] = $vars;
}

function theme_site_vars(){
	global $conf;
	static $vars = null;
	if($vars !== null)return $vars;
	//网站标题在旧代码里一直是原样输出的（站长可能在里面写了标签），所以给的是 _html
	$vars = ['title_html'=>isset($conf['title']) ? $conf['title'] : ''];
	return $vars;
}

/*
 * 后台布局编辑器的预览（DEC-20261025-003）：编辑器签一个令牌写进 cookie pan_preview（全站路径，半小时有效），
 * 带着它的请求按草稿布局渲染、用令牌里的主题。后台登录 cookie 只发到后台目录，前台认不出管理员，
 * 所以靠签名：令牌 = 主题|过期时间|HMAC(SYS_KEY)。草稿本身不是机密，令牌泄露最多让人看到没发布的排版。
 */
function theme_preview_token($theme, $ttl = 1800){
	$exp = time() + $ttl;
	return $theme.'|'.$exp.'|'.hash_hmac('sha256', 'preview|'.$theme.'|'.$exp, SYS_KEY);
}

function theme_preview_theme(){
	static $theme = null;
	if($theme !== null)return $theme;
	$theme = '';
	$tok = isset($_COOKIE['pan_preview']) && is_string($_COOKIE['pan_preview']) ? $_COOKIE['pan_preview'] : '';
	//编辑器页面自己（admin/layout.php）不进预览：它用站点当前的外观，不带定位注释
	if($tok === '' || !defined('SYS_KEY') || defined('LAYOUT_EDITOR'))return $theme;
	$parts = explode('|', $tok);
	if(count($parts) !== 3 || !theme_id_valid($parts[0]) || !ctype_digit($parts[1]) || intval($parts[1]) < time())return $theme;
	if(!hash_equals(hash_hmac('sha256', 'preview|'.$parts[0].'|'.$parts[1], SYS_KEY), $parts[2]))return $theme;
	if(!theme_get($parts[0]))return $theme;
	return $theme = $parts[0];
}

/* 当前请求是不是后台布局编辑器的预览（按草稿渲染、在卡片前后加定位用的注释） */
function layout_preview_active(){
	return theme_preview_theme() !== '';
}

/**
 * 渲染某个页面（或外壳）的布局，返回 HTML。
 * $data 是页面控制器准备好的共享数据，卡片的 data 回调从 $ctx['data'] 里取。
 */
function theme_render_layout($page, array $data = [], $scope = 'front', $id = null){
	if($id === null)$id = theme_active_id();
	$preview = layout_preview_active();
	$ctx = [
		'page' => $page,
		'scope' => $scope,
		'theme_id' => $id,
		'theme_vars' => theme_vars($id),
		'site_vars' => theme_site_vars(),
		'data' => $data,
		'preview' => $preview,
		'depth' => 0,
	];
	$html = block_render_nodes(layout_load($page, $id, $preview), $ctx);
	//预览时把这一页捕获到的后台面板（编号 => 名称）交给 layout-preview.js，编辑器拿它列「可添加的面板」
	if($preview && $scope === 'admin' && ($panels = theme_panel_list())){
		$html .= '<script type="application/json" class="pan-panels">'.json_encode($panels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
	}
	return $html;
}

/* ============ 页面末尾的脚本 ============ */

/*
 * 卡片要的脚本（比如 macOS 窗口风的视图切换、门户风首屏的上传组件）不能直接写在卡片里：
 * 它们要等页脚和基础脚本（jQuery 插件、Bootstrap）都输出以后再加载。卡片渲染时用
 * theme_defer() 登记，页面在 footer.php 之后 echo theme_deferred_html()。
 * $key 相同的只输出一次；$weight 小的先输出，同权重按登记顺序。
 */
function theme_defer($key, $html, $weight = 50){
	if(!isset($GLOBALS['_theme_deferred']))$GLOBALS['_theme_deferred'] = [];
	if(isset($GLOBALS['_theme_deferred'][$key]))return;
	$GLOBALS['_theme_deferred'][$key] = [$weight, count($GLOBALS['_theme_deferred']), $html];
}

function theme_deferred_html(){
	$list = isset($GLOBALS['_theme_deferred']) ? $GLOBALS['_theme_deferred'] : [];
	$GLOBALS['_theme_deferred'] = [];
	uasort($list, function($a, $b){
		if($a[0] !== $b[0])return $a[0] < $b[0] ? -1 : 1;
		return $a[1] < $b[1] ? -1 : 1;
	});
	$html = '';
	foreach($list as $item)$html .= $item[2];
	return $html;
}

/*
 * 卡片上站长可改的一段文字（DEC-20261026-002）：先转义，再把 {名字} 换成给定的值（值是已经安全的 HTML，比如数字、带链接的网站名）。
 * 默认文字和原来模板里写死的一样，所以没改过时输出逐字不变。$br 为 true 时换行变成 <br>。
 */
function theme_text_html($text, array $vars = [], $br = false){
	$html = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
	if($br)$html = implode('<br>', preg_split('/\r\n|\r|\n/', $html));
	if($vars){
		$map = [];
		foreach($vars as $k => $v)$map['{'.$k.'}'] = (string)$v;
		$html = strtr($html, $map);
	}
	return $html;
}

/* lines 类型的配置拆成行，每行再按 | 拆成 $fields 个字段；空行跳过 */
function theme_config_lines($text, $fields = 1){
	$out = [];
	foreach(preg_split('/\r\n|\r|\n/', (string)$text) as $line){
		if(trim($line) === '')continue;
		$parts = array_map('trim', explode('|', $line, $fields));
		$out[] = array_pad($parts, $fields, '');
	}
	return $out;
}

/* ============ 后台面板捕获 ============ */

/*
 * 后台页面的卡片粒度是「面板」（DEC-20261025-003 第 5 节⑨）：页面代码原样保留，
 * 每个面板前后各调一次 theme_panel_begin() / theme_panel_end()，中间输出的 HTML 收进登记表；
 * 页面最后渲染布局，布局里的 admin.panel 卡片按编号把面板摆出来。
 * 捕获发生在页面顶层，变量照常往下流，所以面板之间互相依赖的变量不受影响。
 */
function theme_panel_begin($id, $label = ''){
	if(!isset($GLOBALS['_theme_panels']))$GLOBALS['_theme_panels'] = [];
	$GLOBALS['_theme_panel_stack'][] = [$id, $label];
	ob_start();
}

function theme_panel_end(){
	$html = ob_get_clean();
	list($id, $label) = array_pop($GLOBALS['_theme_panel_stack']);
	$GLOBALS['_theme_panels'][$id] = ['html' => $html, 'label' => $label];
}

/* 取一个捕获的面板；没有这个编号返回 null */
function theme_panel_get($id){
	return isset($GLOBALS['_theme_panels'][$id]) ? $GLOBALS['_theme_panels'][$id] : null;
}

/* 这一页捕获到的全部面板：编号 => 名称（后台布局编辑器列「可添加的面板」用） */
function theme_panel_list(){
	$out = [];
	foreach(isset($GLOBALS['_theme_panels']) ? $GLOBALS['_theme_panels'] : [] as $id => $p)$out[$id] = $p['label'];
	return $out;
}

/* 主题的标记（theme.json 的 flags），沿继承链合并 */
function theme_flags($id){
	$flags = [];
	foreach(theme_chain($id) as $tid){
		$m = theme_get($tid);
		$flags = array_merge($flags, $m['flags']);
	}
	return array_values(array_unique($flags));
}

/*
 * 保存站长给某个主题填的设置（按 schema 清洗后存进 pre_theme.settings），返回 true / 错误原因。
 * 内置主题没有这行时顺手插一行（source=builtin）。
 */
function theme_settings_save($id, $input){
	global $DB;
	$m = theme_get($id);
	if(!$m)return '主题不存在';
	if(!theme_db_ready())return '请先运行数据库升级（/install/update.php）';
	$vals = [];
	foreach($m['settings'] as $f){
		$vals[$f['key']] = theme_setting_clean($f, is_array($input) && array_key_exists($f['key'], $input) ? $input[$f['key']] : $f['default']);
	}
	//非法的 UTF-8 字节替换掉，别让整份设置因为一个字段存成空串
	$json = json_encode($vals, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	if($json === false)return '设置内容无法保存（编码错误）';
	$ok = $DB->exec("INSERT INTO pre_theme (id, source, version, settings, installed_at, updated_at) VALUES (:id, :src, :ver, :s, NOW(), NOW()) ON DUPLICATE KEY UPDATE settings=VALUES(settings), updated_at=NOW()",
		[':id'=>$id, ':src'=>$m['_source'], ':ver'=>$m['version'], ':s'=>$json]);
	return $ok === false ? '保存失败['.$DB->error().']' : true;
}

/*
 * 继承链上每个主题都给一个类名（theme-子 theme-父……）：子主题继承父主题时，
 * 父主题按 body.theme-父 写的样式（内置外观都在 style.css / admin.css 里）照样生效。
 */
function theme_chain_classes($id, $prefix){
	$out = [];
	foreach(theme_chain($id) as $tid)$out[] = $prefix.$tid;
	return $out ? implode(' ', $out) : $prefix.$id;
}

/* $id 或它继承的主题里有没有在名单 $list 里的（按外观名单判断的地方用，比如侧栏型后台、深色登录页） */
function theme_chain_in($id, array $list){
	foreach(theme_chain($id) as $tid){
		if(in_array($tid, $list, true))return $tid;
	}
	return in_array($id, $list, true) ? $id : false;
}

/*
 * 主题设置里约定的两个颜色项直接接到页面的主色变量上（写主题的人不用自己去对 style.css / admin.css 的变量名）：
 *   primary → 前台 --primary、后台 --admin-primary；primary_dark → --primary-dark、--admin-primary-dark。
 * 选择器带上主题类名并比父外观样式表里的规则多一级（html 前缀），所以能盖过去。
 * $scope 为 front / admin；没有这两项（内置外观都没有）返回空串。
 */
function theme_settings_bridge_css($id, $scope = 'front'){
	$m = theme_get($id);
	if(!$m || !$m['settings'])return '';
	$vals = theme_settings($id);
	$map = $scope === 'admin' ? ['primary'=>'--admin-primary', 'primary_dark'=>'--admin-primary-dark'] : ['primary'=>'--primary', 'primary_dark'=>'--primary-dark'];
	$css = '';
	foreach($m['settings'] as $f){
		if($f['type'] === 'color' && isset($map[$f['key']]) && $vals[$f['key']] !== '')$css .= $map[$f['key']].':'.$vals[$f['key']].';';
	}
	if($css === '')return '';
	//admin.css 里是 body.admin-theme-x.admin-body 这种两个类的写法，前面加 html 才压得过；前台 style.css 是 body.theme-x
	return ($scope === 'admin' ? 'html body.admin-theme-'.$id.'.admin-body,html body.admin-theme-'.$id.'.admin-login-body' : 'html body.theme-'.$id).'{'.$css.'}';
}

/* ============ 后台布局编辑器 ============ */

/*
 * 能在编辑器里改的页面：页面名 => [名称, scope, 预览地址（相对站点根；{admin} 换成后台目录名，{file} 换成最新一个公开文件）]。
 * 个人中心、在线编辑要登录用户才看得到，预览框里会跳登录页；没有预览地址的页面只能保存后去前台看。
 */
function layout_editor_pages(){
	//自定义页面排在前台固定页面后面，第 4 项是地址名（编辑器据此显示「页面设置 / 删除页面」）
	$custom = [];
	foreach(custom_pages() as $slug => $p)$custom['page-'.$slug] = ['自定义页面：'.$p['title'], 'front', 'page.php?p='.$slug, $slug];
	return array_slice(layout_editor_pages_fixed(), 0, 11, true) + $custom + array_slice(layout_editor_pages_fixed(), 11, null, true);
}

/* 给编辑器页面下拉框用的清单：[{id, name, scope, slug?, title?}]，自定义页面带地址名和标题 */
function layout_editor_page_list(){
	$out = [];
	$pages = custom_pages();
	foreach(layout_editor_pages() as $id => $info){
		$item = ['id'=>$id, 'name'=>$info[0], 'scope'=>$info[1]];
		if(isset($info[3]) && isset($pages[$info[3]])){
			$item['slug'] = $info[3];
			$item['title'] = $pages[$info[3]]['title'];
			$item['shell'] = $pages[$info[3]]['shell'];
		}
		$out[] = $item;
	}
	return $out;
}

function layout_editor_pages_fixed(){
	return [
		'_shell' => ['前台公共外壳（导航、侧栏、页脚）', 'front', ''],
		'index' => ['首页 / 文件列表', 'front', ''],
		'upload' => ['上传页', 'front', 'upload.php'],
		'file' => ['文件页', 'front', 'file.php?hash={file}'],
		'user' => ['个人中心', 'front', 'user.php'],
		'buy' => ['购买页', 'front', 'buy.php'],
		'login' => ['登录页', 'front', 'login.php'],
		'edit' => ['在线编辑', 'front', null],
		'sponsor' => ['赞助名单', 'front', 'sponsor.php'],
		'violation' => ['违规公示', 'front', 'violation.php'],
		'share' => ['文件夹分享', 'front', null],
		'admin/_shell' => ['后台公共外壳（导航）', 'admin', '{admin}/'],
		'admin/index' => ['后台首页', 'admin', '{admin}/'],
		'admin/file' => ['后台：文件管理', 'admin', '{admin}/file.php'],
		'admin/folders' => ['后台：用户文件夹', 'admin', '{admin}/folders.php'],
		'admin/replace' => ['后台：覆盖记录', 'admin', '{admin}/replace.php'],
		'admin/green_log' => ['后台：内容检测记录', 'admin', '{admin}/green_log.php'],
		'admin/user' => ['后台：用户管理', 'admin', '{admin}/user.php'],
		'admin/order' => ['后台：订单记录', 'admin', '{admin}/order.php'],
		'admin/mail_log' => ['后台：发信记录', 'admin', '{admin}/mail_log.php'],
		'admin/level' => ['后台：会员等级设置', 'admin', '{admin}/level.php'],
		'admin/set-site' => ['后台：网站信息设置', 'admin', '{admin}/set.php?mod=site'],
		'admin/set-appearance' => ['后台：外观设置', 'admin', '{admin}/set.php?mod=appearance'],
		'admin/set-file' => ['后台：文件上传设置', 'admin', '{admin}/set.php?mod=file'],
		'admin/set-api' => ['后台：上传API设置', 'admin', '{admin}/set.php?mod=api'],
		'admin/set-user' => ['后台：用户登录设置', 'admin', '{admin}/set.php?mod=user'],
		'admin/set-green' => ['后台：内容检测设置', 'admin', '{admin}/set.php?mod=green'],
		'admin/set-iptype' => ['后台：用户IP地址设置', 'admin', '{admin}/set.php?mod=iptype'],
		'admin/set-account' => ['后台：管理账号设置', 'admin', '{admin}/set.php?mod=account'],
		'admin/set_script' => ['后台：广告公告位设置', 'admin', '{admin}/set_script.php'],
		'admin/set_stor' => ['后台：存储类型设置', 'admin', '{admin}/set_stor.php'],
		'admin/set_mail' => ['后台：邮件发信设置', 'admin', '{admin}/set_mail.php'],
		'admin/set_pay' => ['后台：购买套餐设置', 'admin', '{admin}/set_pay.php'],
		'admin/set_violation' => ['后台：违规公示管理', 'admin', '{admin}/set_violation.php'],
		'admin/set_sponsor' => ['后台：赞助名单管理', 'admin', '{admin}/set_sponsor.php'],
		'admin/update' => ['后台：程序更新日志', 'admin', '{admin}/update.php'],
		'admin/login' => ['后台登录页', 'admin', '{admin}/login.php'],
	];
}

/*
 * 页面里必须有的卡片（卡片定义里写了 required，比如外壳的「页面内容」、上传页挂载点、购买页支付弹窗）：
 * 主题默认布局里有的，保存时也必须有、而且不能隐藏。
 */
function layout_check_required($page, array $nodes, $theme){
	$have = [];
	$need = [];
	$walk = function($list, $into) use (&$walk, &$have, &$need, $theme){
		foreach($list as $n){
			if($into === 'have' && empty($n['hidden']))$have[$n['block']] = true;
			if($into === 'need'){
				$def = block_get($n['block'], $theme);
				if($def && !empty($def['required']))$need[$n['block']] = $def['name'];
			}
			if(!empty($n['children']))$walk($n['children'], $into);
		}
	};
	$walk($nodes, 'have');
	$walk(layout_default($page, $theme), 'need');
	foreach($need as $bid => $name){
		if(!isset($have[$bid]))return '「'.$name.'」不能删也不能隐藏，页面靠它才能正常工作';
	}
	return true;
}

/* 编辑器里的卡片清单：这个页面能放的卡片（按 scope 和 pages 过滤，含主题自带的），带配置项定义 */
function layout_editor_blocks($page, $theme){
	$pages = layout_editor_pages();
	$scope = isset($pages[$page]) ? $pages[$page][1] : (strpos($page, 'admin/') === 0 ? 'admin' : 'front');
	$out = [];
	foreach(block_registry($scope, $theme) as $id => $def){
		if($def['pages'] !== ['*'] && !in_array($page, $def['pages'], true))continue;
		$schema = $def['schema'];
		//菜单配置项附上可选的内置项和自定义页面，编辑器据此出下拉框
		foreach($schema as $i => $f){
			if($f['type'] !== 'menu')continue;
			$schema[$i]['builtins'] = [];
			foreach(nav_menu_builtins() as $k => $b)$schema[$i]['builtins'][] = ['key'=>$k, 'label'=>$b[0], 'icon'=>$b[1]];
			$schema[$i]['pages'] = array_values(custom_pages());
		}
		$out[] = ['id'=>$id, 'name'=>$def['name'], 'group'=>$def['group'], 'container'=>!empty($def['container']), 'required'=>!empty($def['required']), 'schema'=>$schema, 'description'=>$def['description']];
	}
	return $out;
}

/* 一个主题一个页面在编辑器里的状态：草稿、已发布、主题默认 */
function layout_editor_state($theme, $page){
	$row = theme_db_row('SELECT draft,published,updated_at FROM pre_layout WHERE theme=:t AND page=:p LIMIT 1', [':t'=>layout_store_theme($page, $theme), ':p'=>$page]);
	$dec = function($v){ $x = ($v === null || $v === '') ? null : json_decode($v, true); return is_array($x) ? layout_normalize($x) : null; };
	$st = [
		'draft' => $row ? $dec($row['draft']) : null,
		'published' => $row ? $dec($row['published']) : null,
		'default' => layout_default($page, $theme),
		'updated_at' => $row ? $row['updated_at'] : '',
	];
	//前台外壳：导航菜单是网站级的，单独存的，放回导航栏卡片的配置里给编辑器显示
	//网站级菜单没配置过时，沿用布局里自带的（主题默认外壳里给的，或改成网站级之前存下的草稿）
	if($page === '_shell'){
		foreach(['draft', 'published', 'default'] as $k){
			if($st[$k] === null)continue;
			$nodes = $st[$k];
			$own = layout_take_menu($nodes);
			$site = nav_menu_site($k === 'draft');
			$st[$k] = layout_put_menu($nodes, $site !== '' ? $site : (string)$own);
		}
	}
	return $st;
}

/*
 * 前台导航菜单是网站级的（DEC-20261026-001）：换外观菜单不变。编辑时还是在「前台公共外壳 → 导航栏」卡片里改，
 * 保存时从外壳布局里拿出来，已发布的存 pre_config.nav_menu，草稿存 nav_menu_draft（JSON 编码的字符串，空串表示没有草稿，只在预览里生效）。
 */
function nav_menu_site($draft = false){
	global $conf;
	if($draft && isset($conf['nav_menu_draft']) && $conf['nav_menu_draft'] !== ''){
		$d = json_decode((string)$conf['nav_menu_draft'], true);
		if(is_string($d))return nav_menu_clean($d);
	}
	return isset($conf['nav_menu']) ? nav_menu_clean((string)$conf['nav_menu']) : '';
}

function nav_menu_store($key, $val){
	global $conf;
	saveSetting($key, $val);
	$conf[$key] = $val;
}

/* 从外壳布局里取出导航栏卡片的 menu 配置并删掉（单独存）；布局里没有导航栏卡片返回 null */
function layout_take_menu(array &$nodes){
	$found = null;
	foreach($nodes as &$n){
		if($n['block'] === 'shell.navbar' && $found === null){
			$found = isset($n['config']['menu']) ? nav_menu_clean($n['config']['menu']) : '';
		}
		if($n['block'] === 'shell.navbar' && is_array($n['config']))unset($n['config']['menu']);
		if(!empty($n['children'])){
			$r = layout_take_menu($n['children']);
			if($found === null)$found = $r;
		}
	}
	unset($n);
	return $found;
}

/* 把菜单放回导航栏卡片的配置里 */
function layout_put_menu(array $nodes, $menu){
	foreach($nodes as &$n){
		if($n['block'] === 'shell.navbar'){
			if(!is_array($n['config']))$n['config'] = [];
			$n['config']['menu'] = $menu;
		}
		if(!empty($n['children']))$n['children'] = layout_put_menu($n['children'], $menu);
	}
	unset($n);
	return $nodes;
}

/*
 * 编辑器的写操作：draft 保存草稿 / publish 发布（不传节点就把草稿发布出去）/ discard 丢弃草稿 / reset 恢复主题默认。
 * 返回 true 或错误原因。
 */
function layout_editor_save($theme, $page, $action, $nodes = null){
	global $DB;
	if(!theme_get($theme))return '主题不存在';
	if(!layout_page_valid($page))return '页面名不合法';
	if(!theme_db_ready())return '请先运行数据库升级（/install/update.php）';
	//自定义页面所有外观共用一份（theme='*'）；外壳里的导航菜单另存成网站级的（见 nav_menu_site）
	$key = layout_store_theme($page, $theme);
	if($action === 'reset'){
		$DB->exec("DELETE FROM pre_layout WHERE theme=:t AND page=:p", [':t'=>$key, ':p'=>$page]);
		return true;
	}
	if($action === 'discard'){
		$DB->exec("UPDATE pre_layout SET draft=NULL, updated_at=NOW() WHERE theme=:t AND page=:p", [':t'=>$key, ':p'=>$page]);
		$DB->exec("DELETE FROM pre_layout WHERE theme=:t AND page=:p AND (published IS NULL OR published='')", [':t'=>$key, ':p'=>$page]);
		if($page === '_shell')nav_menu_store('nav_menu_draft', '');
		return true;
	}
	if($action === 'publish' && $nodes === null){
		$st = layout_editor_state($theme, $page);
		$nodes = $st['draft'] !== null ? $st['draft'] : ($st['published'] !== null ? $st['published'] : $st['default']);
	}
	if(!is_array($nodes))return '布局内容不合法';
	$nodes = layout_normalize($nodes);
	$req = layout_check_required($page, $nodes, $theme);
	if($req !== true)return $req;
	$menu = $page === '_shell' ? layout_take_menu($nodes) : null;
	$json = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	if($json === false || strlen($json) > 1024 * 1024)return '布局太大';
	if($action === 'draft'){
		$ok = $DB->exec("INSERT INTO pre_layout (theme,page,draft,published,updated_at) VALUES (:t,:p,:d,NULL,NOW()) ON DUPLICATE KEY UPDATE draft=VALUES(draft), updated_at=NOW()", [':t'=>$key, ':p'=>$page, ':d'=>$json]);
	}elseif($action === 'publish'){
		$ok = $DB->exec("INSERT INTO pre_layout (theme,page,draft,published,updated_at) VALUES (:t,:p,NULL,:d,NOW()) ON DUPLICATE KEY UPDATE published=VALUES(published), draft=NULL, updated_at=NOW()", [':t'=>$key, ':p'=>$page, ':d'=>$json]);
	}else{
		return '未知操作';
	}
	if($ok === false)return '保存失败['.$DB->error().']';
	if($menu !== null){
		if($action === 'draft'){
			nav_menu_store('nav_menu_draft', json_encode($menu));
		}else{
			nav_menu_store('nav_menu', $menu);
			nav_menu_store('nav_menu_draft', '');
		}
	}
	return true;
}

/* ============ 自定义页面（DEC-20261026-001） ============ */

/*
 * 站长在「页面布局」编辑器里建的页面：访问地址 page.php?p=<地址名>，内容是布局 page-<地址名>（所有外观共用一份，见 layout_store_theme()；草稿 / 发布和别的页面一样）。
 * 清单存在 pre_config.custom_pages（JSON：[{slug, title}]）。地址名建好以后不能改（菜单、外链都按它指过去），要改就新建一个再删旧的。
 * pre_config.home_page 是网站入口：空表示打开网站根目录 / 是文件列表（默认）；设成某个页面时，不带参数打开 / 显示那个页面，
 * 文件列表走菜单里的「文件列表」（./?list=1）和带参数的链接。
 */
function custom_page_slug_valid($slug){
	return is_string($slug) && preg_match('/^[a-z0-9][a-z0-9\-]{0,30}$/', $slug) === 1;
}

function custom_pages(){
	global $conf;
	$out = [];
	$list = isset($conf['custom_pages']) ? json_decode((string)$conf['custom_pages'], true) : null;
	if(!is_array($list))return $out;
	foreach($list as $p){
		if(!is_array($p) || !isset($p['slug'], $p['title']) || !custom_page_slug_valid($p['slug']) || !is_scalar($p['title']))continue;
		//shell：是否显示网站外壳（导航、页脚、外观样式），默认显示；关掉时整页只有页面自己的内容（适合整页自己写代码）
		$out[$p['slug']] = ['slug'=>$p['slug'], 'title'=>mb_substr(trim((string)$p['title']), 0, 40, 'UTF-8'), 'shell'=>!isset($p['shell']) || !empty($p['shell'])];
	}
	return $out;
}

function custom_page_get($slug){
	$pages = custom_pages();
	return is_string($slug) && isset($pages[$slug]) ? $pages[$slug] : null;
}

/* 网站入口页的地址名；没设、或者设的页面已经删了，返回空串（/ 是文件列表） */
function home_page_slug(){
	global $conf;
	$slug = isset($conf['home_page']) ? (string)$conf['home_page'] : '';
	return $slug !== '' && custom_page_get($slug) ? $slug : '';
}

function custom_pages_store(array $pages){
	global $conf;
	$json = json_encode(array_values($pages), JSON_UNESCAPED_UNICODE);
	saveSetting('custom_pages', $json);
	$conf['custom_pages'] = $json;
}

/* 新建（$create 为 true）或改标题；成功返回 true，否则返回原因 */
function custom_page_save($slug, $title, $create, $shell = null){
	if(!custom_page_slug_valid($slug))return '地址名只能用小写字母、数字和短横线，最多 31 个字符';
	$title = is_string($title) ? trim($title) : '';
	if($title === '' || mb_strlen($title, 'UTF-8') > 40)return '标题要填，40 个字以内';
	$pages = custom_pages();
	if($create){
		if(isset($pages[$slug]))return '地址名「'.$slug.'」已经有了';
		if(count($pages) >= 50)return '自定义页面最多 50 个';
	}elseif(!isset($pages[$slug])){
		return '页面不存在';
	}
	//$shell 为 null 表示不改（新建时默认显示外壳）
	$pages[$slug] = ['slug'=>$slug, 'title'=>$title, 'shell'=>$shell === null ? (isset($pages[$slug]) ? $pages[$slug]['shell'] : true) : (bool)$shell];
	custom_pages_store($pages);
	return true;
}

/* 删除页面：清单、各主题的布局一起删；它是网站入口的话入口恢复成文件列表 */
function custom_page_delete($slug){
	global $DB, $conf;
	$pages = custom_pages();
	if(!isset($pages[$slug]))return '页面不存在';
	unset($pages[$slug]);
	custom_pages_store($pages);
	if(theme_db_ready())$DB->exec("DELETE FROM pre_layout WHERE page=:p", [':p'=>'page-'.$slug]);
	if(isset($conf['home_page']) && $conf['home_page'] === $slug){
		saveSetting('home_page', '');
		$conf['home_page'] = '';
	}
	return true;
}

/* 设置网站入口：空串 = 文件列表 */
function home_page_set($slug){
	global $conf;
	if($slug !== '' && !custom_page_get($slug))return '页面不存在';
	saveSetting('home_page', $slug);
	$conf['home_page'] = $slug;
	return true;
}

/* ============ 导航菜单（DEC-20261026-001） ============ */

/*
 * 前台导航的内置菜单项：键 => [名称, 图标, 地址]。哪些显示还要看各自的开关（赞助名单、购买、违规公示），
 * 在导航栏卡片（includes/blocks/shell/navbar.php）里判断。顺序就是没配置过菜单时的默认顺序。
 */
function nav_menu_builtins(){
	return [
		'files' => ['文件列表', 'fa-list', './'],
		'upload' => ['上传文件', 'fa-upload', './upload.php'],
		'sponsor' => ['赞助名单', 'fa-money', './sponsor.php'],
		'buy' => ['购买权限', 'fa-shopping-cart', './buy.php'],
		'violation' => ['违规公示', 'fa-gavel', './violation.php'],
	];
}

/*
 * 菜单配置（导航栏卡片的 menu 配置项）清洗：JSON 数组，每项
 *   {type:"builtin", key:"files"} / {type:"page", slug:"home"} / {type:"link", href:"https://…", blank:1}
 * 都可以带 label（改名）、icon（Font Awesome 4 的 fa-xxx）、hidden（先藏起来）。
 * 不认识的项丢掉；全部丢光或者没配置过返回空串，表示用默认菜单（和改造前一模一样）。
 */
function nav_menu_clean($v){
	$list = is_array($v) ? $v : (is_string($v) && $v !== '' ? json_decode($v, true) : null);
	if(!is_array($list))return '';
	$builtins = nav_menu_builtins();
	$out = [];
	foreach($list as $it){
		if(!is_array($it) || !isset($it['type']) || !is_string($it['type']))continue;
		$item = ['type'=>$it['type']];
		if($it['type'] === 'builtin'){
			if(!isset($it['key']) || !is_string($it['key']) || !isset($builtins[$it['key']]))continue;
			$item['key'] = $it['key'];
		}elseif($it['type'] === 'page'){
			if(!isset($it['slug']) || !custom_page_slug_valid($it['slug']))continue;
			$item['slug'] = $it['slug'];
		}elseif($it['type'] === 'link'){
			$href = isset($it['href']) && is_string($it['href']) ? trim($it['href']) : '';
			//只收 http(s) 或站内相对地址，挡掉 javascript: 之类
			if($href === '' || strlen($href) > 500 || !preg_match('#^(https?://[^\s"\'<>]+|(?![A-Za-z][A-Za-z0-9+.\-]*:)[^\s"\'<>]+)$#', $href))continue;
			$item['href'] = $href;
			if(!empty($it['blank']))$item['blank'] = 1;
		}else{
			continue;
		}
		if(isset($it['label']) && is_scalar($it['label']) && trim((string)$it['label']) !== '')$item['label'] = mb_substr(trim((string)$it['label']), 0, 20, 'UTF-8');
		if(isset($it['icon']) && is_string($it['icon']) && preg_match('/^fa-[a-z0-9-]{1,30}$/', $it['icon']))$item['icon'] = $it['icon'];
		if(!empty($it['hidden']))$item['hidden'] = 1;
		if($item['type'] === 'link' && !isset($item['label']))$item['label'] = '链接';
		$out[] = $item;
		if(count($out) >= 30)break;
	}
	return $out ? json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
}
