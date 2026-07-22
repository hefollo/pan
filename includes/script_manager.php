<?php
if(!defined('IN_CRONLITE'))exit();

function mpimg_default_add_text(){
	return '&#32593;&#31449;&#24050;&#24320;&#21551;&#27599;&#26085;&#25968;&#25454;&#22791;&#20221;&#65292;&#25903;&#25345;&#31449;&#28857;&#21487;&#21069;&#24448;<a href="https://mpimg.cn/includes/sponsor" target="_blank">&#36190;&#21161;&#39029;</a>&#12290;&#31449;&#28857;&#38382;&#39064;&#21487;&#32852;&#31995;QQ&#65306;<a href="https://wpa.qq.com/msgrd?v=3&uin=7619897&site=qq&menu=yes&jumpflag=1" target="_blank">7619897</a>&#65292;QQ&#20132;&#27969;&#32676;&#65306;<a href="https://qm.qq.com/q/Wddyy2mcGS" target="_blank">251912122</a>&#12290;';
}

function mpimg_default_gg_text(){
	return '网站已开启一天一备份数据功能，您的支持是我维持下去的动力！前往赞助<a href="/includes/sponsor" target="_blank">点击前往</a> 网站问题可联系QQ：<a href="https://wpa.qq.com/msgrd?v=3&uin=7619897&site=qq&menu=yes&jumpflag=1" target="_blank">7619897</a>，网站内软件等问题勿扰！QQ交流群：<a href="https://qm.qq.com/q/Wddyy2mcGS" target="_blank">251912122</a>。网络并非法外之地！请勿上传儿童色情内容或威胁、骚扰、诽谤、侵权、政治或鼓动非法行为等材料！上传者将屏蔽IP。';
}

function mpimg_default_ads(){
	return [
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#2f86ff', 'tooltip'=>'点击查看广告详情，欢迎咨询广告位出租。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#ff4d5f', 'tooltip'=>'抢占广告位，提升曝光，欢迎咨询。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#10b981', 'tooltip'=>'开启您的广告展示，吸引更多用户。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#f59e0b', 'tooltip'=>'提升品牌知名度，覆盖更多用户。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#f59e0b', 'tooltip'=>'让更多人看到您，增加用户点击。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#10b981', 'tooltip'=>'广告位有限，欢迎联系预订。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#ff4d5f', 'tooltip'=>'点击了解详情，助力品牌增长。'],
		['enabled'=>1, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#2f86ff', 'tooltip'=>'广告位等您来抢，详情请咨询客服。'],
	];
}

function mpimg_conf_value($conf, $key, $default = ''){
	return isset($conf[$key]) && $conf[$key] !== '' ? $conf[$key] : $default;
}

function mpimg_conf_value_any($conf, $keys, $default = ''){
	foreach((array)$keys as $key){
		if(isset($conf[$key]) && $conf[$key] !== ''){
			return $conf[$key];
		}
	}
	return $default;
}

function mpimg_conf_enabled($conf, $key, $default = 1){
	if(!isset($conf[$key]) || $conf[$key] === ''){
		return (int)$default === 1;
	}
	return (int)$conf[$key] === 1;
}

function mpimg_conf_enabled_any($conf, $keys, $default = 1){
	foreach((array)$keys as $key){
		if(isset($conf[$key]) && $conf[$key] !== ''){
			return (int)$conf[$key] === 1;
		}
	}
	return (int)$default === 1;
}

function mpimg_get_ads($conf){
	$default = mpimg_default_ads();
	$ads_json = null;
	if(array_key_exists('ads_json', $conf)){
		$ads_json = $conf['ads_json'];
	}elseif(array_key_exists('gg_ads_json', $conf)){
		$ads_json = $conf['gg_ads_json'];
	}
	if($ads_json === null || $ads_json === ''){
		return $default;
	}
	$ads = json_decode($ads_json, true);
	if(!is_array($ads)){
		return $default;
	}
	$result = [];
	foreach($ads as $index=>$ad){
		if(!is_array($ad))continue;
		$fallback = isset($default[$index]) ? $default[$index] : ['enabled'=>0, 'mode'=>'text', 'text'=>'', 'href'=>'#', 'image'=>'', 'bgColor'=>'#2f86ff', 'tooltip'=>''];
		$mode = isset($ad['mode']) && in_array($ad['mode'], ['text', 'image'], true) ? $ad['mode'] : (isset($ad['image']) && trim((string)$ad['image']) !== '' ? 'image' : 'text');
		$result[] = [
			'enabled' => !empty($ad['enabled']) ? 1 : 0,
			'mode' => $mode,
			'text' => isset($ad['text']) ? trim((string)$ad['text']) : $fallback['text'],
			'href' => isset($ad['href']) && $ad['href'] !== '' ? trim((string)$ad['href']) : '#',
			'image' => isset($ad['image']) ? trim((string)$ad['image']) : $fallback['image'],
			'bgColor' => isset($ad['bgColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $ad['bgColor']) ? $ad['bgColor'] : $fallback['bgColor'],
			'tooltip' => isset($ad['tooltip']) ? trim((string)$ad['tooltip']) : $fallback['tooltip'],
		];
	}
	return $result;
}

function mpimg_ads_from_post($post){
	$rows = [];
	$indexes = isset($post['ad_index']) && is_array($post['ad_index']) ? $post['ad_index'] : null;
	$texts = isset($post['ad_text']) && is_array($post['ad_text']) ? $post['ad_text'] : (isset($post['gg_ad_text']) && is_array($post['gg_ad_text']) ? $post['gg_ad_text'] : []);
	$hrefs = isset($post['ad_href']) && is_array($post['ad_href']) ? $post['ad_href'] : (isset($post['gg_ad_href']) && is_array($post['gg_ad_href']) ? $post['gg_ad_href'] : []);
	$images = isset($post['ad_image']) && is_array($post['ad_image']) ? $post['ad_image'] : [];
	$modes = isset($post['ad_mode']) && is_array($post['ad_mode']) ? $post['ad_mode'] : [];
	$colors = isset($post['ad_color']) && is_array($post['ad_color']) ? $post['ad_color'] : (isset($post['gg_ad_color']) && is_array($post['gg_ad_color']) ? $post['gg_ad_color'] : []);
	$tooltips = isset($post['ad_tooltip']) && is_array($post['ad_tooltip']) ? $post['ad_tooltip'] : (isset($post['gg_ad_tooltip']) && is_array($post['gg_ad_tooltip']) ? $post['gg_ad_tooltip'] : []);
	$enabled = isset($post['ad_enabled']) && is_array($post['ad_enabled']) ? $post['ad_enabled'] : (isset($post['gg_ad_enabled']) && is_array($post['gg_ad_enabled']) ? $post['gg_ad_enabled'] : []);

	if($indexes === null){
		$indexes = array_unique(array_merge(array_keys($texts), array_keys($hrefs), array_keys($images), array_keys($colors), array_keys($tooltips)));
		sort($indexes);
	}

	foreach($indexes as $idx){
		$key = (string)$idx;
		$text = isset($texts[$key]) ? trim($texts[$key]) : '';
		$href = isset($hrefs[$key]) ? trim($hrefs[$key]) : '#';
		$image = isset($images[$key]) ? trim($images[$key]) : '';
		$mode = isset($modes[$key]) && in_array($modes[$key], ['text', 'image'], true) ? $modes[$key] : ($image !== '' ? 'image' : 'text');
		$color = isset($colors[$key]) ? trim($colors[$key]) : '#2f86ff';
		$tooltip = isset($tooltips[$key]) ? trim($tooltips[$key]) : '';
		if($text === '' && $image === '' && ($href === '' || $href === '#') && $tooltip === '')continue;
		if(!preg_match('/^#[0-9a-fA-F]{6}$/', $color)){
			$color = '#2f86ff';
		}
		$rows[] = [
			'enabled' => isset($enabled[$key]) ? 1 : 0,
			'mode' => $mode,
			'text' => $text,
			'href' => $href === '' ? '#' : $href,
			'image' => $image,
			'bgColor' => $color,
			'tooltip' => $tooltip,
		];
	}

	return $rows;
}

function mpimg_json($value){
	return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function mpimg_html_escape($value){
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mpimg_safe_href($href){
	$href = trim((string)$href);
	if($href === ''){
		return '#';
	}
	if($href[0] === '#' || $href[0] === '/'){
		return $href;
	}
	$parts = parse_url($href);
	if(!$parts || empty($parts['scheme'])){
		return '#';
	}
	$scheme = strtolower($parts['scheme']);
	return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $href : '#';
}

function mpimg_safe_image($src){
	$src = trim((string)$src);
	if($src === ''){
		return '';
	}
	if($src[0] === '/'){
		return $src;
	}
	$parts = parse_url($src);
	if(!$parts || empty($parts['scheme'])){
		return '';
	}
	$scheme = strtolower($parts['scheme']);
	return in_array($scheme, ['http', 'https'], true) ? $src : '';
}

function mpimg_render_ads_html($conf){
	if(!mpimg_conf_enabled_any($conf, ['ads_enable', 'gg_js_enable'], 1)){
		return '';
	}

	$html = '';
	$band_style = 'width:100%;margin:0 0 28px;padding:8px 0 10px;background:rgba(255,255,255,.96);border-bottom:1px solid #dbe8f7;box-shadow:0 10px 28px rgba(47,134,255,.08);position:relative;z-index:2;box-sizing:border-box;';
	$wrap_style = 'display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;max-width:1320px;width:calc(100% - 24px);margin:0 auto;padding:0 12px;box-sizing:border-box;';
	$text_style = 'display:flex;align-items:center;justify-content:center;min-width:0;min-height:46px;padding:13px 12px;text-align:center;color:#fff!important;text-decoration:none;border:1px solid rgba(255,255,255,.22);border-radius:10px;box-shadow:0 8px 20px rgba(24,46,84,.12);position:relative;overflow:visible;box-sizing:border-box;';
	$image_style = 'display:flex;align-items:center;justify-content:center;width:auto;max-width:100%;min-height:46px;height:60px;padding:0 8px;margin:0 auto;text-align:center;color:#fff!important;text-decoration:none;background:transparent;border:0;border-radius:10px;box-shadow:none;position:relative;overflow:visible;';
	$image_tag_style = 'display:block;width:auto;max-width:100%;height:60px;object-fit:contain;border-radius:10px;border:1px solid rgba(255,255,255,.18);background:#fff;';
	foreach(mpimg_get_ads($conf) as $ad){
		$text = trim((string)$ad['text']);
		$image = mpimg_safe_image(isset($ad['image']) ? $ad['image'] : '');
		$mode = isset($ad['mode']) && in_array($ad['mode'], ['text', 'image'], true) ? $ad['mode'] : ($image !== '' ? 'image' : 'text');
		$use_image = $mode === 'image' && $image !== '';
		if(empty($ad['enabled']) || ($text === '' && $image === '')){
			continue;
		}
		$color = isset($ad['bgColor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $ad['bgColor']) ? $ad['bgColor'] : '#2f86ff';
		$tooltip = trim((string)($ad['tooltip'] ?: $text ?: '广告'));
		$class = $use_image ? 'dh has-image' : 'dh';
		$link_style = ($use_image ? $image_style : ('background:'.$color.';'.$text_style));
		$html .= '<a href="'.mpimg_html_escape(mpimg_safe_href($ad['href'])).'" target="_blank" rel="nofollow noopener" class="'.$class.'" style="'.mpimg_html_escape($link_style).'" data-tooltip="'.mpimg_html_escape($tooltip).'">';
		if($use_image){
			$alt = $text !== '' ? $text : $tooltip;
			$html .= '<img src="'.mpimg_html_escape($image).'" alt="'.mpimg_html_escape($alt).'" loading="lazy" style="'.mpimg_html_escape($image_tag_style).'">';
		}else{
			$html .= mpimg_html_escape($text);
		}
		$html .= '</a>';
	}

	if($html === ''){
		return '';
	}

	return '<div class="mpimg-link-band" data-mpimg-dynamic="ads" style="'.mpimg_html_escape($band_style).'"><div class="mpimg-link-grid" style="'.mpimg_html_escape($wrap_style).'">'.$html.'</div></div>';
}

function mpimg_render_notice_html($conf){
	if(!mpimg_conf_enabled_any($conf, ['ads_enable', 'gg_js_enable'], 1)){
		return '';
	}
	$text = trim((string)mpimg_conf_value_any($conf, ['ads_notice_text', 'gg_js_text'], mpimg_default_gg_text()));
	if($text === ''){
		return '';
	}
	$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : 'cloud';
	$theme_styles = [
		'cloud' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#e3edf8',
			'shadow' => '0 10px 28px rgba(47,134,255,.08)',
			'text' => '#19304f',
			'link' => '#2f86ff',
		],
		'night' => [
			'bg' => 'rgba(13,22,34,.9)',
			'border' => '#26354f',
			'shadow' => '0 14px 36px rgba(0,0,0,.28)',
			'text' => '#dbe8ff',
			'link' => '#70aaff',
		],
		'neon' => [
			'bg' => 'linear-gradient(90deg,rgba(13,26,49,.92),rgba(8,17,33,.92))',
			'border' => 'rgba(86,130,218,.46)',
			'shadow' => '0 16px 42px rgba(0,0,0,.35)',
			'text' => '#cad8f0',
			'link' => '#73c7ff',
		],
		'aurora' => [
			'bg' => 'rgba(20,28,88,.72)',
			'border' => 'rgba(255,255,255,.16)',
			'shadow' => '0 14px 40px rgba(15,16,70,.24)',
			'text' => '#eef5ff',
			'link' => '#78edff',
		],
		'onefour' => [
			'bg' => 'rgba(7,7,9,.9)',
			'border' => 'rgba(255,255,255,.08)',
			'shadow' => '0 16px 40px rgba(0,0,0,.34)',
			'text' => '#e7e8ec',
			'link' => '#ffffff',
		],
	];
	if(!isset($theme_styles[$site_theme])){
		$site_theme = 'cloud';
	}
	$current = $theme_styles[$site_theme];
	$wrap_style = 'display:block;clear:both;width:100%;overflow:hidden;white-space:nowrap;position:relative;margin:-28px 0 0;padding:0;background:'.$current['bg'].';border-top:1px solid '.$current['border'].';border-bottom:1px solid '.$current['border'].';box-shadow:'.$current['shadow'].';color:'.$current['text'].';';
	$text_style = 'display:inline-block;min-width:max-content;padding:0 20px;line-height:36px;white-space:nowrap;font-size:15px;font-weight:600;animation:themeAnnouncementScroll 45s linear infinite;will-change:transform;';
	$style = '<style>.theme-announcement-bar .theme-announcement-text:hover{animation-play-state:paused}.theme-announcement-bar .theme-announcement-text a{color:'.$current['link'].'!important;font-weight:700;text-decoration:none}.theme-announcement-bar .theme-announcement-text a:hover{text-decoration:underline}@keyframes themeAnnouncementScroll{from{transform:translateX(100%)}to{transform:translateX(-100%)}}</style>';
	return $style.'<div class="theme-announcement-bar" data-mpimg-dynamic="announcement" style="'.mpimg_html_escape($wrap_style).'"><div id="adsNoticeText" class="theme-announcement-text" style="'.mpimg_html_escape($text_style).'">'.$text.'</div></div>';
}

function mpimg_output_script($type, $conf){
	$is_announcement = in_array($type, ['announcement', 'add'], true);
	if($is_announcement){
		@header('Content-Type: application/javascript; charset=UTF-8');
		@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		@header('Pragma: no-cache');
		echo "/* mpimg announcement script removed */\n";
		return;
	}
	$type_name = $is_announcement ? 'announcement' : 'ads';
	$enabled_keys = $is_announcement ? ['announcement_enable', 'add_js_enable'] : ['ads_enable', 'gg_js_enable'];
	$text_keys = $is_announcement ? ['announcement_text', 'add_js_text'] : ['ads_notice_text', 'gg_js_text'];
	$default_text = $is_announcement ? mpimg_default_add_text() : mpimg_default_gg_text();

	@header('Content-Type: application/javascript; charset=UTF-8');
	@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	@header('Pragma: no-cache');

	if(!mpimg_conf_enabled_any($conf, $enabled_keys, 1)){
		echo "/* mpimg {$type_name} script disabled */\n";
		return;
	}

	$payload = [
		'type' => $type_name,
		'textId' => $is_announcement ? 'scrollText' : 'adsNoticeText',
		'text' => mpimg_conf_value_any($conf, $text_keys, $default_text),
		'ads' => $is_announcement ? [] : mpimg_get_ads($conf),
	];

	echo '(function(){'."\n";
	echo 'var mpimgPayload = '.mpimg_json($payload).";\n";
	echo <<<'JS'
if (window.__mpimgDynamicScripts && window.__mpimgDynamicScripts[mpimgPayload.type]) {
  return;
}
window.__mpimgDynamicScripts = window.__mpimgDynamicScripts || {};
window.__mpimgDynamicScripts[mpimgPayload.type] = true;

function ready(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback);
  } else {
    callback();
  }
}

function ensureStyle() {
  if (document.getElementById('mpimg-dynamic-script-style')) {
    return;
  }
  var style = document.createElement('style');
  style.id = 'mpimg-dynamic-script-style';
  style.innerHTML = [
    '.theme-announcement-bar{--announce-bg:rgba(255,255,255,.96);--announce-border:#e3edf8;--announce-shadow:0 10px 28px rgba(47,134,255,.08);--announce-text:#19304f;--announce-link:#2f86ff;--announce-c1:#2f86ff;--announce-c2:#119a8f;--announce-c3:#6b8cff;display:block;clear:both;width:100%;overflow:hidden;white-space:nowrap;position:relative;margin:-28px 0 0;padding:0;border-top:1px solid var(--announce-border);border-bottom:1px solid var(--announce-border);background:var(--announce-bg);box-shadow:var(--announce-shadow);color:var(--announce-text)}',
    '.theme-announcement-text{display:inline-block;min-width:max-content;padding:0 20px;line-height:36px;white-space:nowrap;font-size:15px;font-weight:600;animation:themeAnnouncementScroll 60s linear infinite;will-change:transform}',
    '.theme-announcement-text:hover{animation-play-state:paused}',
    '.theme-announcement-text span{color:var(--announce-text)}',
    '.theme-announcement-text a,.theme-announcement-link{color:var(--announce-link)!important;font-weight:700;text-decoration:none}',
    '.theme-announcement-text a:hover,.theme-announcement-link:hover{text-decoration:underline}',
    'body.theme-night .theme-announcement-bar{--announce-bg:rgba(13,22,34,.9);--announce-border:#26354f;--announce-shadow:0 14px 36px rgba(0,0,0,.28);--announce-text:#c5d2e6;--announce-link:#70aaff;--announce-c1:#70aaff;--announce-c2:#c5d2e6;--announce-c3:#7c5cff}',
    'body.theme-neon .theme-announcement-bar{--announce-bg:linear-gradient(90deg,rgba(13,26,49,.92),rgba(8,17,33,.92));--announce-border:rgba(86,130,218,.46);--announce-shadow:0 16px 42px rgba(0,0,0,.35);--announce-text:#cad8f0;--announce-link:#73c7ff;--announce-c1:#73c7ff;--announce-c2:#b69cff;--announce-c3:#24d7ff}',
    'body.theme-aurora .theme-announcement-bar{--announce-bg:rgba(20,28,88,.72);--announce-border:rgba(255,255,255,.16);--announce-shadow:0 14px 40px rgba(15,16,70,.24);--announce-text:#e6eeff;--announce-link:#78edff;--announce-c1:#67e8ff;--announce-c2:#f0b7ff;--announce-c3:#eef5ff;backdrop-filter:blur(14px)}',
    'body.theme-onefour .theme-announcement-bar{--announce-bg:rgba(7,7,9,.9);--announce-border:rgba(255,255,255,.08);--announce-shadow:0 16px 40px rgba(0,0,0,.34);--announce-text:#d8dae4;--announce-link:#ffffff;--announce-c1:#ffffff;--announce-c2:#b8bcc8;--announce-c3:#8e939f}',
    '.mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.96);--gg-ad-border:#dbe8f7;--gg-ad-shadow:0 10px 28px rgba(47,134,255,.08);width:100%;margin:0 0 28px;padding:8px 0 10px;background:var(--gg-ad-bg);border-bottom:1px solid var(--gg-ad-border);box-shadow:var(--gg-ad-shadow);box-sizing:border-box}',
    '.mpimg-link-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;max-width:1320px;width:calc(100% - 24px);margin:0 auto;padding:0 12px;box-sizing:border-box}',
    '.mpimg-link-grid .dh{display:flex;align-items:center;justify-content:center;min-width:0;min-height:46px;padding:13px 12px;text-align:center;color:#fff!important;text-decoration:none;background:var(--ad-card-bg,#2f86ff);border:1px solid rgba(255,255,255,.22);border-radius:10px;box-shadow:0 8px 20px rgba(24,46,84,.12);transition:transform .2s ease,box-shadow .2s ease,opacity .2s ease;position:relative;overflow:visible;box-sizing:border-box}',
    '.mpimg-link-grid .dh.has-image{display:flex;align-items:center;justify-content:center;width:auto;max-width:100%;margin:0 auto;padding:0 8px;background:transparent;border-color:transparent;box-shadow:none;overflow:visible}',
    '.mpimg-link-grid .dh.has-image img{display:block;width:auto;max-width:100%;height:60px;min-height:46px;object-fit:contain;border-radius:10px;border:1px solid rgba(255,255,255,.18);background:#fff}',
    '.mpimg-link-grid .dh:hover,.mpimg-link-grid .dh:focus{color:#fff!important;transform:translateY(-2px);box-shadow:0 12px 26px rgba(24,46,84,.18);opacity:.94}',
    '.mpimg-link-grid .dh:hover::after,.mpimg-link-grid .dh:focus::after{content:attr(data-tooltip);position:absolute;top:-38px;left:50%;transform:translateX(-50%);min-width:160px;max-width:240px;background:rgba(10,18,30,.92);color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;line-height:1.5;white-space:normal;word-wrap:break-word;box-shadow:0 10px 24px rgba(0,0,0,.22);z-index:20}',
    'body.theme-night .mpimg-link-band{--gg-ad-bg:rgba(9,15,25,.94);--gg-ad-border:#26354f;--gg-ad-shadow:0 14px 36px rgba(0,0,0,.28)}',
    'body.theme-neon .mpimg-link-band{--gg-ad-bg:linear-gradient(180deg,rgba(13,26,49,.9),rgba(8,17,33,.92));--gg-ad-border:rgba(86,130,218,.46);--gg-ad-shadow:0 16px 42px rgba(0,0,0,.35)}',
    'body.theme-neon .mpimg-link-grid .dh{box-shadow:0 0 22px rgba(47,134,255,.16)}',
    'body.theme-aurora .mpimg-link-band{--gg-ad-bg:rgba(20,28,88,.54);--gg-ad-border:rgba(255,255,255,.2);--gg-ad-shadow:0 14px 40px rgba(15,16,70,.18);backdrop-filter:blur(14px)}',
    'body.theme-aurora .mpimg-link-grid .dh{border-color:rgba(255,255,255,.28);box-shadow:0 12px 28px rgba(30,20,93,.18)}',
    'body.theme-onefour .mpimg-link-band{--gg-ad-bg:rgba(6,6,9,.92);--gg-ad-border:rgba(255,255,255,.08);--gg-ad-shadow:0 16px 40px rgba(0,0,0,.34)}',
    'body.theme-onefour .mpimg-link-grid .dh{border-color:rgba(255,255,255,.1);box-shadow:none}',
    '.navbar{margin-bottom:0}',
    '@keyframes themeAnnouncementScroll{from{transform:translateX(0)}to{transform:translateX(-100%)}}',
    '@keyframes themeAnnouncementColor{0%,100%{color:var(--announce-c1)}35%{color:var(--announce-c2)}70%{color:var(--announce-c3)}}',
    '@media (max-width:768px){.theme-announcement-bar{margin:-18px 0 0}.theme-announcement-text{padding:0 14px;line-height:32px;font-size:14px}.mpimg-link-band{margin:0 0 18px;padding:6px 0 8px}.mpimg-link-grid{grid-template-columns:repeat(2,minmax(0,1fr));width:calc(100% - 16px);padding:0 8px}.mpimg-link-grid .dh:hover::after,.mpimg-link-grid .dh:focus::after{top:-44px;min-width:130px;max-width:170px;font-size:11px;padding:6px 8px}}'
  ].join('');
  document.head.appendChild(style);
}

function safeHref(href) {
  href = String(href || '#').trim();
  if (href === '' || href.charAt(0) === '#' || href.charAt(0) === '/') {
    return href || '#';
  }
  var parser = document.createElement('a');
  parser.href = href;
  var protocol = String(parser.protocol || '').toLowerCase();
  return /^(https?:|mailto:|tel:)$/i.test(protocol) ? href : '#';
}

function safeImage(src) {
  src = String(src || '').trim();
  if (!src) {
    return '';
  }
  if (src.charAt(0) === '/') {
    return src;
  }
  var parser = document.createElement('a');
  parser.href = src;
  var protocol = String(parser.protocol || '').toLowerCase();
  return /^(https?:)$/i.test(protocol) ? src : '';
}

function appendAnimatedText(target, markup) {
  var source = document.createElement('span');
  source.innerHTML = String(markup || '');
  var seq = 0;

  function appendText(text, parent) {
    for (var i = 0; i < text.length; i++) {
      var span = document.createElement('span');
      span.textContent = text.charAt(i);
      span.style.animation = 'themeAnnouncementColor 4s linear infinite ' + (seq * 0.06) + 's';
      seq++;
      parent.appendChild(span);
    }
  }

  function walk(node, parent) {
    if (node.nodeType === 3) {
      appendText(node.textContent || '', parent);
      return;
    }
    if (node.nodeType !== 1) {
      return;
    }
    if (node.tagName === 'A') {
      var link = document.createElement('a');
      link.href = safeHref(node.getAttribute('href'));
      link.target = node.getAttribute('target') || '_blank';
      link.rel = 'nofollow noopener';
      link.className = 'theme-announcement-link';
      link.textContent = node.textContent || link.href;
      parent.appendChild(link);
      return;
    }
    if (node.tagName === 'BR') {
      parent.appendChild(document.createTextNode(' '));
      return;
    }
    for (var child = node.firstChild; child; child = child.nextSibling) {
      walk(child, parent);
    }
  }

  for (var child = source.firstChild; child; child = child.nextSibling) {
    walk(child, target);
  }
}

function insertIntoPage(element) {
  var navbar = document.querySelector('.navbar.navbar-default');
  if (navbar) {
    navbar.appendChild(element);
    return;
  }
  if (document.body) {
    document.body.insertBefore(element, document.body.firstChild);
  }
}

function insertAfterNavbar(element) {
  var navbar = document.querySelector('.navbar.navbar-default');
  if (navbar && navbar.parentNode) {
    if (navbar.nextSibling) {
      navbar.parentNode.insertBefore(element, navbar.nextSibling);
    } else {
      navbar.parentNode.appendChild(element);
    }
    return;
  }
  insertIntoPage(element);
}

function renderAnnouncement() {
  if (document.getElementById(mpimgPayload.textId) || !String(mpimgPayload.text || '').trim()) {
    return;
  }
  var bar = document.createElement('div');
  bar.className = 'theme-announcement-bar';
  bar.setAttribute('role', 'region');
  bar.setAttribute('aria-label', '网站公告');

  var text = document.createElement('div');
  text.id = mpimgPayload.textId;
  text.className = 'theme-announcement-text';
  appendAnimatedText(text, mpimgPayload.text);
  bar.appendChild(text);
  insertIntoPage(bar);
}

function renderAds() {
  if (mpimgPayload.type !== 'ads' || document.querySelector('.mpimg-link-band[data-mpimg-dynamic="ads"], .mpimg-link-band[data-mpimg-dynamic="gg"], .mpimg-link-grid[data-mpimg-dynamic="ads"], .mpimg-link-grid[data-mpimg-dynamic="gg"], .txtguanggao[data-mpimg-dynamic="ads"], .txtguanggao[data-mpimg-dynamic="gg"]')) {
    return;
  }
  var ads = mpimgPayload.ads || [];
  var band = document.createElement('div');
  band.className = 'mpimg-link-band';
  band.setAttribute('data-mpimg-dynamic', 'ads');
  var wrap = document.createElement('div');
  wrap.className = 'mpimg-link-grid';
  wrap.setAttribute('data-mpimg-dynamic', 'ads');

  for (var i = 0; i < ads.length; i++) {
    var ad = ads[i] || {};
    var imageSrc = safeImage(ad.image);
    var mode = ad.mode === 'image' ? 'image' : 'text';
    var useImage = mode === 'image' && !!imageSrc;
    if (!ad.enabled || (!ad.text && !imageSrc)) {
      continue;
    }
    var link = document.createElement('a');
    link.href = safeHref(ad.href);
    link.target = '_blank';
    link.rel = 'nofollow noopener';
    link.className = useImage ? 'dh has-image' : 'dh';
    link.style.setProperty('--ad-card-bg', /^#[0-9a-f]{6}$/i.test(ad.bgColor || '') ? ad.bgColor : '#2f86ff');
    link.setAttribute('data-tooltip', ad.tooltip || ad.text || '广告');
    if (useImage) {
      var img = document.createElement('img');
      img.src = imageSrc;
      img.alt = ad.text || ad.tooltip || 'ad';
      img.loading = 'lazy';
      link.appendChild(img);
    } else {
      link.textContent = ad.text;
    }
    wrap.appendChild(link);
  }

  if (wrap.children.length > 0) {
    band.appendChild(wrap);
    insertAfterNavbar(band);
  }
}

ready(function () {
  ensureStyle();
  renderAnnouncement();
  renderAds();
});
JS;
	echo "\n})();\n";
}
