<?php
if(!defined('IN_CRONLITE'))exit();

function mpimg_default_add_text(){
	return '&#32593;&#31449;&#24050;&#24320;&#21551;&#27599;&#26085;&#25968;&#25454;&#22791;&#20221;&#65292;&#25903;&#25345;&#31449;&#28857;&#21487;&#21069;&#24448;<a href="/includes/sponsor" target="_blank">&#36190;&#21161;&#39029;</a>&#12290;&#31449;&#28857;&#38382;&#39064;&#21487;&#32852;&#31995;QQ&#65306;<a href="https://wpa.qq.com/msgrd?v=3&uin=7619897&site=qq&menu=yes&jumpflag=1" target="_blank">7619897</a>&#65292;QQ&#20132;&#27969;&#32676;&#65306;<a href="https://qm.qq.com/q/Wddyy2mcGS" target="_blank">251912122</a>&#12290;';
}

function mpimg_default_gg_text(){
	return '网站已开启一天一备份数据功能，您的支持是我维持下去的动力！前往赞助<a href="/includes/sponsor" target="_blank">点击前往</a> 网站问题可联系QQ：<a href="https://wpa.qq.com/msgrd?v=3&uin=7619897&site=qq&menu=yes&jumpflag=1" target="_blank">7619897</a>，网站内软件等问题勿扰！QQ交流群：<a href="https://qm.qq.com/q/Wddyy2mcGS" target="_blank">251912122</a>。网络并非法外之地！请勿上传儿童色情内容或威胁、骚扰、诽谤、侵权、政治或鼓动非法行为等材料！上传者将屏蔽IP。';
}

/*
 * 广告位的出厂内容：8 个「广告招租」占位，默认全部关闭。
 * 后台没保存过广告设置时用这一份，管理员把要用的那条勾上再保存即可，
 * 免得新站点一装好前台就挂着一排招租广告。
 */
function mpimg_default_ads(){
	return [
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#2f86ff', 'tooltip'=>'点击查看广告详情，欢迎咨询广告位出租。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#ff4d5f', 'tooltip'=>'抢占广告位，提升曝光，欢迎咨询。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#10b981', 'tooltip'=>'开启您的广告展示，吸引更多用户。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#f59e0b', 'tooltip'=>'提升品牌知名度，覆盖更多用户。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#f59e0b', 'tooltip'=>'让更多人看到您，增加用户点击。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#10b981', 'tooltip'=>'广告位有限，欢迎联系预订。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#ff4d5f', 'tooltip'=>'点击了解详情，助力品牌增长。'],
		['enabled'=>0, 'mode'=>'text', 'text'=>'广告招租', 'href'=>'#', 'image'=>'', 'bgColor'=>'#2f86ff', 'tooltip'=>'广告位等您来抢，详情请咨询客服。'],
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

/**
 * 后台广告表格里的文字框现在是可拖大的 textarea，
 * 这里把换行、制表符归一成空格，避免前台渲染时多出空白。
**/
function mpimg_ads_clean_line($value){
	$value = preg_replace('/\s+/u', ' ', (string)$value);
	return trim((string)$value);
}

function mpimg_ads_clean_url($value){
	return preg_replace('/\s+/u', '', (string)$value);
}

/**
 * 图片广告的宽度（0 = 不锁宽，铺满整行）。
 * 旧版本这里存的是百分比，换成像素后换了个键，免得把旧的 50（%）当成 50px 读。
**/
function mpimg_ads_image_width($conf){
	$value = isset($conf['ads_image_width_px']) ? (int)$conf['ads_image_width_px'] : 0;
	if($value < 0){ $value = 0; }
	if($value > 2000){ $value = 2000; }
	return $value;
}

function mpimg_ads_image_height($conf){
	$value = isset($conf['ads_image_height']) ? (int)$conf['ads_image_height'] : 0;
	if($value < 0){ $value = 0; }
	if($value > 600){ $value = 600; }
	return $value;
}

/**
 * 一行同时并排几条广告（1~4）。默认 3 条，图片广告并排铺开更像 banner 位。
 * 广告条数不够时格子宽度不变、从左往右排：设 3 条只有 1 条广告，就占最左边那一格。
**/
function mpimg_ads_per_view($conf){
	$value = isset($conf['ads_per_view']) ? (int)$conf['ads_per_view'] : 3;
	if($value < 1){ $value = 1; }
	if($value > 4){ $value = 4; }
	return $value;
}

/**
 * 锁了高度之后，图和框的比例往往对不上，这里决定怎么放：
 * cover = 放大盖满、多余部分裁掉；contain = 完整放进去、周围留白；fill = 直接拉伸。
**/
function mpimg_ads_image_fit($conf){
	$value = isset($conf['ads_image_fit']) ? (string)$conf['ads_image_fit'] : 'cover';
	return in_array($value, ['cover', 'contain', 'fill'], true) ? $value : 'cover';
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
		$text = isset($texts[$key]) ? mpimg_ads_clean_line($texts[$key]) : '';
		$href = isset($hrefs[$key]) ? mpimg_ads_clean_url($hrefs[$key]) : '#';
		$image = isset($images[$key]) ? mpimg_ads_clean_url($images[$key]) : '';
		$mode = isset($modes[$key]) && in_array($modes[$key], ['text', 'image'], true) ? $modes[$key] : ($image !== '' ? 'image' : 'text');
		$color = isset($colors[$key]) ? trim($colors[$key]) : '#2f86ff';
		$tooltip = isset($tooltips[$key]) ? mpimg_ads_clean_line($tooltips[$key]) : '';
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

/**
 * 广告轮播的实现只写一份：首页服务端渲染时跟在广告条后面内联一次，
 * includes/ads.php 输出的脚本里再带一份。ads.php 这个名字容易被拦截插件屏蔽，
 * 内联那份能保证首页的轮播照样能转；两份都到也不会重复接管。
**/
function mpimg_ads_carousel_js(){
	return <<<'JS'
(function () {
  if (window.mpimgInitAdCarousels) { return; }

  function initAdCarousels() {
    var roots = document.querySelectorAll('.mpimg-ad-carousel');
    for (var i = 0; i < roots.length; i++) {
      initAdCarousel(roots[i]);
    }
  }

  function initAdCarousel(root) {
    if (!root || root.getAttribute('data-mpimg-carousel') === '1') {
      return;
    }
    var track = root.querySelector('.mpimg-ad-track');
    if (!track || track.children.length === 0) {
      return;
    }
    root.setAttribute('data-mpimg-carousel', '1');
    var total = track.children.length;
    var index = 0;
    var timer = null;
    var dots = [];
    var dotWrap = null;
    var perView = 1;
    var pages = 1;

    //一屏并排几条由 CSS 变量说了算，窄屏的媒体查询会把它压回 1，所以每次都现读
    function readPerView() {
      var raw = 0;
      if (window.getComputedStyle) {
        raw = parseInt(window.getComputedStyle(root).getPropertyValue('--mpimg-ad-per-view'), 10);
      }
      if (!raw || raw < 1) { raw = 1; }
      return raw;
    }

    //并排多条时容器提示会指错人（空着的格子上也会冒出来），改用浏览器原生 title，一条一屏时再摘掉
    function applyTips() {
      for (var t = 0; t < total; t++) {
        var tipEl = track.children[t].querySelector('[data-tooltip]');
        if (!tipEl) { continue; }
        if (perView > 1) {
          tipEl.title = tipEl.getAttribute('data-tooltip') || '';
        } else {
          tipEl.removeAttribute('title');
        }
      }
    }

    //悬停提示挂在轮播容器上：可视区是 overflow:hidden 的，挂在按钮上会被裁掉。
    //一屏并排好几条的时候挂上去会指错是哪一条，这种情况干脆不挂
    function syncTip() {
      var tip = '';
      if (perView === 1) {
        var slide = track.children[index];
        var link = slide ? slide.querySelector('[data-tooltip]') : null;
        tip = link ? (link.getAttribute('data-tooltip') || '') : '';
      }
      root.setAttribute('data-tooltip', tip);
    }

    function setSingle(on) {
      var name = root.className.replace(/\s*is-single/g, '');
      root.className = on ? name + ' is-single' : name;
    }

    if (total < 2) {
      setSingle(true);
      var refreshSingle = function () { perView = readPerView(); applyTips(); syncTip(); };
      refreshSingle();
      window.addEventListener('resize', refreshSingle, false);
      return;
    }

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      track.style.transition = 'none';
    }

    //一页 = 并排的那几条，位移永远是一整个可视区宽度，所以还是按 100% 走
    function goTo(next) {
      index = (next % pages + pages) % pages;
      track.style.transform = 'translateX(' + (-index * 100) + '%)';
      syncTip();
      for (var i = 0; i < dots.length; i++) {
        dots[i].className = 'mpimg-ad-dot' + (i === index ? ' is-active' : '');
        dots[i].setAttribute('aria-current', i === index ? 'true' : 'false');
      }
    }
    function start() {
      if (!timer && pages > 1) {
        timer = setInterval(function () { goTo(index + 1); }, 5000);
      }
    }
    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }
    function step(delta) {
      goTo(index + delta);
      stop();
      start();
    }

    function makeNav(dir) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mpimg-ad-nav mpimg-ad-' + dir;
      btn.setAttribute('aria-label', dir === 'prev' ? '上一页广告' : '下一页广告');
      btn.innerHTML = dir === 'prev' ? '&#10094;' : '&#10095;';
      btn.onclick = function () { step(dir === 'prev' ? -1 : 1); };
      (root.querySelector('.mpimg-ad-viewport') || root).appendChild(btn);
    }
    makeNav('prev');
    makeNav('next');

    function buildDots() {
      if (!dotWrap) {
        dotWrap = document.createElement('div');
        dotWrap.className = 'mpimg-ad-dots';
        root.appendChild(dotWrap);
      }
      while (dotWrap.firstChild) { dotWrap.removeChild(dotWrap.firstChild); }
      dots = [];
      for (var d = 0; d < pages; d++) {
        dotWrap.appendChild((function (target) {
          var dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'mpimg-ad-dot';
          dot.setAttribute('aria-label', '第 ' + (target + 1) + ' 页广告');
          dot.onclick = function () { goTo(target); stop(); start(); };
          dots.push(dot);
          return dot;
        })(d));
      }
    }

    //并排条数变了（换屏宽、转屏）就重算页数、重排圆点
    function layout() {
      var pv = readPerView();
      var next = Math.max(1, Math.ceil(total / pv));
      if (dotWrap && pv === perView && next === pages) {
        return;
      }
      perView = pv;
      pages = next;
      applyTips();
      buildDots();
      setSingle(pages < 2);
      goTo(index < pages ? index : pages - 1);
      if (pages < 2) { stop(); } else { start(); }
    }
    layout();

    root.onmouseenter = stop;
    root.onmouseleave = start;
    root.addEventListener('focusin', stop, false);
    root.addEventListener('focusout', start, false);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    }, false);
    var resizeTimer = null;
    window.addEventListener('resize', function () {
      if (resizeTimer) { clearTimeout(resizeTimer); }
      resizeTimer = setTimeout(layout, 150);
    }, false);

    //手机上支持左右滑
    var startX = null;
    track.addEventListener('touchstart', function (e) {
      startX = e.touches[0].clientX;
      stop();
    }, false);
    track.addEventListener('touchend', function (e) {
      if (startX === null) { return; }
      var delta = e.changedTouches[0].clientX - startX;
      startX = null;
      if (Math.abs(delta) > 40) { step(delta < 0 ? 1 : -1); } else { start(); }
    }, false);
  }

  window.mpimgInitAdCarousels = initAdCarousels;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdCarousels, false);
  } else {
    initAdCarousels();
  }
})();
JS;
}

/**
 * 前台广告位：一屏并排放「每屏条数」条（后台可调，默认 3），放不下的由 initAdCarousel() 翻页轮播。
 * 广告条数不超过一屏就加 is-single，不出箭头和圆点。
**/
function mpimg_render_ads_html($conf){
	if(!mpimg_conf_enabled_any($conf, ['ads_enable', 'gg_js_enable'], 1)){
		return '';
	}

	$image_width = mpimg_ads_image_width($conf);
	$image_height = mpimg_ads_image_height($conf);
	$image_fit = mpimg_ads_image_fit($conf);
	$per_view = mpimg_ads_per_view($conf);
	$text_style = 'display:inline-flex;flex:0 1 auto;min-width:110px;max-width:320px;align-items:center;justify-content:center;min-height:36px;padding:8px 16px;font-size:13px;font-weight:600;line-height:1.4;text-align:center;color:#fff!important;text-decoration:none;border:1px solid rgba(255,255,255,.2);border-radius:10px;box-shadow:0 2px 6px rgba(24,46,84,.14);position:relative;overflow:visible;box-sizing:border-box;';
	$image_style = 'display:flex;align-items:center;justify-content:center;width:100%;max-width:100%;padding:0;margin:0;text-decoration:none;background:transparent;border:0;box-shadow:none;position:relative;overflow:visible;box-sizing:border-box;';
	$image_tag_style = 'display:block;width:var(--mpimg-ad-img-w,100%);max-width:100%;height:var(--mpimg-ad-img-h,auto);object-fit:var(--mpimg-ad-img-fit,cover);border-radius:12px;';

	$links = [];
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
		$link = '<a href="'.mpimg_html_escape(mpimg_safe_href($ad['href'])).'" target="_blank" rel="nofollow noopener" class="'.$class.'" style="'.mpimg_html_escape($link_style).'" data-tooltip="'.mpimg_html_escape($tooltip).'">';
		if($use_image){
			$alt = $text !== '' ? $text : $tooltip;
			$link .= '<img src="'.mpimg_html_escape($image).'" alt="'.mpimg_html_escape($alt).'" loading="lazy" style="'.mpimg_html_escape($image_tag_style).'">';
		}else{
			$link .= mpimg_html_escape($text);
		}
		$links[] = $link.'</a>';
	}

	$count = count($links);
	if($count === 0){
		return '';
	}

	//格子宽度始终按后台设的并排条数算，广告条数不够就从左往右排、后面的格子空着
	$columns = max(1, $per_view);
	//容器默认只放得下 1320px，图片又有 max-width:100%，所以宽度拖得再大也会被卡在这里。
	//设了具体宽度就把容器上限一并顶开（按并排条数乘出来），否则“拖满”在宽屏上看着像没生效
	$grid_max = $image_width > 0 ? max(1320, ($image_width + 24) * $columns) : 1320;
	$band_style = '--mpimg-ad-grid-max:'.$grid_max.'px;--mpimg-ad-per-view:'.$columns.';--mpimg-ad-img-w:'.($image_width > 0 ? $image_width.'px' : '100%').';--mpimg-ad-img-h:'.($image_height > 0 ? $image_height.'px' : 'auto').';--mpimg-ad-img-fit:'.$image_fit.';width:100%;margin:0 0 22px;padding:4px 0 0;background:none;border:0;box-shadow:none;position:relative;z-index:2;box-sizing:border-box;';
	$wrap_style = 'position:relative;max-width:var(--mpimg-ad-grid-max,1320px);width:calc(100% - 24px);margin:0 auto;padding:0 12px;box-sizing:border-box;';
	$viewport_style = 'position:relative;overflow:hidden;padding:6px 0;border-radius:12px;';
	$track_style = 'display:flex;align-items:stretch;transition:transform .45s cubic-bezier(.4,0,.2,1);';
	$slide_style = 'flex:0 0 calc(100% / var(--mpimg-ad-per-view,1));max-width:100%;display:flex;align-items:center;justify-content:center;min-width:0;padding:0 6px;box-sizing:border-box;';

	$html = '';
	foreach($links as $link){
		$html .= '<div class="mpimg-ad-slide" style="'.mpimg_html_escape($slide_style).'">'.$link.'</div>';
	}

	//一屏就放得下所有广告时不用轮播，箭头和圆点都收起来
	$wrap_class = 'mpimg-link-grid mpimg-ad-carousel'.($count <= $columns ? ' is-single' : '');
	return '<div class="mpimg-link-band" data-mpimg-dynamic="ads" style="'.mpimg_html_escape($band_style).'">'
		.'<div class="'.$wrap_class.'" data-mpimg-dynamic="ads" style="'.mpimg_html_escape($wrap_style).'">'
		.'<div class="mpimg-ad-viewport" style="'.mpimg_html_escape($viewport_style).'">'
		.'<div class="mpimg-ad-track" style="'.mpimg_html_escape($track_style).'">'.$html.'</div>'
		.'</div></div></div>'
		.'<script>'.mpimg_ads_carousel_js().'</script>';
}

function mpimg_render_notice_html($conf){
	if(!mpimg_conf_enabled_any($conf, ['ads_enable', 'gg_js_enable'], 1)){
		return '';
	}
	$text = trim((string)mpimg_conf_value_any($conf, ['ads_notice_text', 'gg_js_text'], mpimg_default_gg_text()));
	if($text === ''){
		return '';
	}
	$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
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
		'celadon' => [
			'bg' => 'rgba(255,255,255,.94)',
			'border' => 'rgba(24,120,120,.2)',
			'shadow' => '0 14px 36px rgba(24,120,120,.12)',
			'text' => '#17696b',
			'link' => '#2b9c9c',
		],
		'lilac' => [
			'bg' => 'rgba(255,255,255,.94)',
			'border' => 'rgba(96,82,190,.2)',
			'shadow' => '0 14px 36px rgba(96,82,190,.12)',
			'text' => '#453a94',
			'link' => '#6d5dd3',
		],
		'paper' => [
			'bg' => 'rgba(255,255,255,.94)',
			'border' => 'rgba(120,114,98,.2)',
			'shadow' => '0 14px 36px rgba(120,114,98,.12)',
			'text' => '#6b6760',
			'link' => '#3f3f3d',
		],
		'blush' => [
			'bg' => 'rgba(255,255,255,.94)',
			'border' => 'rgba(190,110,135,.2)',
			'shadow' => '0 14px 36px rgba(190,110,135,.12)',
			'text' => '#a13c5d',
			'link' => '#e0648a',
		],
		'sky' => [
			'bg' => 'rgba(255,255,255,.94)',
			'border' => 'rgba(14,120,180,.2)',
			'shadow' => '0 14px 36px rgba(14,120,180,.12)',
			'text' => '#075e86',
			'link' => '#0ea5e9',
		],
		'mint' => [
			'bg' => 'rgba(255,255,255,.94)',
			'border' => 'rgba(30,150,100,.2)',
			'shadow' => '0 14px 36px rgba(30,150,100,.12)',
			'text' => '#106b41',
			'link' => '#22b573',
		],
		'sunset' => [
			'bg' => 'rgba(255,255,255,.14)',
			'border' => 'rgba(255,255,255,.24)',
			'shadow' => '0 16px 42px rgba(60,10,40,.3)',
			'text' => '#fff3ea',
			'link' => '#ffb057',
		],
		'abyss' => [
			'bg' => 'rgba(255,255,255,.14)',
			'border' => 'rgba(255,255,255,.24)',
			'shadow' => '0 16px 42px rgba(2,24,36,.3)',
			'text' => '#e8fbff',
			'link' => '#38e0d8',
		],
		'emerald' => [
			'bg' => 'rgba(255,255,255,.14)',
			'border' => 'rgba(255,255,255,.24)',
			'shadow' => '0 16px 42px rgba(2,34,20,.3)',
			'text' => '#eafff3',
			'link' => '#4ade80',
		],
		'sakura' => [
			'bg' => 'rgba(255,255,255,.7)',
			'border' => 'rgba(255,255,255,.24)',
			'shadow' => '0 16px 42px rgba(190,120,160,.3)',
			'text' => '#3d2030',
			'link' => '#e0648a',
		],
		'dashboard' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#e7eaf1',
			'shadow' => '0 10px 28px rgba(31,41,55,.08)',
			'text' => '#1f2430',
			'link' => '#4f6bff',
		],
		'console' => [
			'bg' => 'rgba(255,255,255,.97)',
			'border' => '#e7eaf0',
			'shadow' => '0 10px 28px rgba(28,39,64,.07)',
			'text' => '#151c2d',
			'link' => '#3867f4',
		],
		'portal' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#dfe7e2',
			'shadow' => '0 12px 30px rgba(23,33,29,.08)',
			'text' => '#17211d',
			'link' => '#0d7c57',
		],
		'workspace' => [
			'bg' => 'rgba(23,28,37,.96)',
			'border' => '#2a313d',
			'shadow' => '0 16px 40px rgba(0,0,0,.4)',
			'text' => '#edf1f7',
			'link' => '#f4c95d',
		],
		'mac' => [
			'bg' => 'rgba(246,246,248,.96)',
			'border' => 'rgba(0,0,0,.11)',
			'shadow' => 'none',
			'text' => '#1d1d1f',
			'link' => '#0a84ff',
		],
		'cockpit' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#ecedf6',
			'shadow' => '0 12px 34px rgba(46,44,92,.08)',
			'text' => '#191d33',
			'link' => '#6d5df6',
		],
		//工作台家族五套
		'studio' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#e8edf7',
			'shadow' => '0 10px 30px rgba(38,60,105,.07)',
			'text' => '#1f2a44',
			'link' => '#3b7dfb',
		],
		'nebula' => [
			'bg' => 'rgba(11,23,48,.94)',
			'border' => '#1b2c50',
			'shadow' => '0 14px 40px rgba(0,0,0,.45)',
			'text' => '#dce8ff',
			'link' => '#7fb0ff',
		],
		'royal' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#e6e6fa',
			'shadow' => '0 12px 32px rgba(70,60,140,.09)',
			'text' => '#231d48',
			'link' => '#6366f1',
		],
		'crisp' => [
			'bg' => 'rgba(255,255,255,.97)',
			'border' => '#e9eef6',
			'shadow' => '0 8px 24px rgba(15,23,42,.06)',
			'text' => '#0f172a',
			'link' => '#2563eb',
		],
		'azure' => [
			'bg' => 'rgba(255,255,255,.96)',
			'border' => '#dfeafc',
			'shadow' => '0 12px 30px rgba(28,80,140,.08)',
			'text' => '#14314f',
			'link' => '#3b82f6',
		],
		'neo' => [
			'bg' => '#fffdf8',
			'border' => '#111111',
			'shadow' => '0 4px 0 #111111',
			'text' => '#141414',
			'link' => '#ff6b2c',
		],
		'skyline' => [
			'bg' => 'rgba(255,255,255,.97)',
			'border' => '#e6ecf6',
			'shadow' => '0 10px 28px rgba(26,37,64,.07)',
			'text' => '#1a2540',
			'link' => '#2563eb',
		],
	];
	if(!isset($theme_styles[$site_theme])){
		$site_theme = default_site_theme();
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
		'imageWidth' => mpimg_ads_image_width($conf),
		'imageHeight' => mpimg_ads_image_height($conf),
		'imageFit' => mpimg_ads_image_fit($conf),
		'perView' => mpimg_ads_per_view($conf),
	];

	echo mpimg_ads_carousel_js()."\n";
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
    '.mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.96);--gg-ad-border:#dbe8f7;--gg-ad-shadow:none;width:100%;margin:0 0 22px;padding:4px 0 0;background:none;border:0;box-shadow:none;box-sizing:border-box}',
    '.mpimg-link-grid{position:relative;max-width:var(--mpimg-ad-grid-max,1320px);width:calc(100% - 24px);margin:0 auto;padding:0 12px;box-sizing:border-box}',
    '.mpimg-link-grid .dh{display:inline-flex;flex:0 1 auto;min-width:110px;max-width:320px;align-items:center;justify-content:center;min-height:36px;padding:8px 16px;font-size:13px;font-weight:600;line-height:1.4;text-align:center;color:#fff!important;text-decoration:none;background:var(--ad-card-bg,#2f86ff);border:1px solid rgba(255,255,255,.2);border-radius:10px;box-shadow:0 2px 6px rgba(24,46,84,.14);transition:transform .2s ease,box-shadow .2s ease,opacity .2s ease;position:relative;overflow:visible;box-sizing:border-box}',
    '.mpimg-link-grid .dh.has-image{display:flex;align-items:center;justify-content:center;width:100%;min-width:0;max-width:100%;margin:0;padding:0;background:transparent;border:0;box-shadow:none;overflow:visible}',
    '.mpimg-link-grid .dh.has-image img{display:block;width:var(--mpimg-ad-img-w,100%);max-width:100%;height:var(--mpimg-ad-img-h,auto);object-fit:var(--mpimg-ad-img-fit,cover);border-radius:12px}',
    '.mpimg-ad-viewport{position:relative;overflow:hidden;padding:6px 0;border-radius:12px}',
    '.mpimg-ad-track{display:flex;align-items:stretch;transition:transform .45s cubic-bezier(.4,0,.2,1);will-change:transform}',
    '.mpimg-ad-slide{flex:0 0 calc(100% / var(--mpimg-ad-per-view,1));max-width:100%;display:flex;align-items:center;justify-content:center;min-width:0;padding:0 6px;box-sizing:border-box}',
    '.mpimg-ad-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;display:flex;align-items:center;justify-content:center;width:30px;height:30px;padding:0;border:0;border-radius:50%;background:rgba(15,23,42,.42);color:#fff;font-size:14px;line-height:1;cursor:pointer;opacity:.55;transition:opacity .2s ease,background .2s ease}',
    '.mpimg-ad-nav:hover,.mpimg-ad-nav:focus{opacity:1;background:rgba(15,23,42,.66);color:#fff;outline:none}',
    '.mpimg-ad-prev{left:10px}',
    '.mpimg-ad-next{right:10px}',
    '.mpimg-ad-dots{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:8px}',
    '.mpimg-ad-dot{width:7px;height:7px;padding:0;border:0;border-radius:999px;background:#94a3b8;opacity:.45;cursor:pointer;transition:width .2s ease,opacity .2s ease}',
    '.mpimg-ad-dot.is-active{width:18px;opacity:.9}',
    '.mpimg-link-grid.is-single .mpimg-ad-nav,.mpimg-link-grid.is-single .mpimg-ad-dots{display:none}',
    '.mpimg-link-grid .dh:hover,.mpimg-link-grid .dh:focus{color:#fff!important;transform:translateY(-1px);box-shadow:0 4px 12px rgba(24,46,84,.2);opacity:.94}',
    '.mpimg-ad-carousel[data-tooltip]:not([data-tooltip=""]):hover::after{content:attr(data-tooltip);position:absolute;top:calc(100% + 4px);left:50%;transform:translateX(-50%);min-width:160px;max-width:260px;background:rgba(10,18,30,.92);color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;line-height:1.5;text-align:center;white-space:normal;word-wrap:break-word;box-shadow:0 10px 24px rgba(0,0,0,.22);pointer-events:none;z-index:20}',
    'body.theme-night .mpimg-link-band{--gg-ad-bg:rgba(9,15,25,.94);--gg-ad-border:#26354f;--gg-ad-shadow:0 14px 36px rgba(0,0,0,.28)}',
    'body.theme-neon .mpimg-link-band{--gg-ad-bg:linear-gradient(180deg,rgba(13,26,49,.9),rgba(8,17,33,.92));--gg-ad-border:rgba(86,130,218,.46);--gg-ad-shadow:0 16px 42px rgba(0,0,0,.35)}',
    'body.theme-neon .mpimg-link-grid .dh{box-shadow:0 0 22px rgba(47,134,255,.16)}',
    'body.theme-aurora .mpimg-link-band{--gg-ad-bg:rgba(20,28,88,.54);--gg-ad-border:rgba(255,255,255,.2);--gg-ad-shadow:0 14px 40px rgba(15,16,70,.18);backdrop-filter:blur(14px)}',
    'body.theme-aurora .mpimg-link-grid .dh{border-color:rgba(255,255,255,.28);box-shadow:0 12px 28px rgba(30,20,93,.18)}',
    'body.theme-onefour .mpimg-link-band{--gg-ad-bg:rgba(6,6,9,.92);--gg-ad-border:rgba(255,255,255,.08);--gg-ad-shadow:0 16px 40px rgba(0,0,0,.34)}',
    'body.theme-onefour .mpimg-link-grid .dh{border-color:rgba(255,255,255,.1);box-shadow:none}',
    'body.theme-dashboard .theme-announcement-bar{--announce-bg:rgba(255,255,255,.96);--announce-border:#e7eaf1;--announce-shadow:0 10px 28px rgba(31,41,55,.08);--announce-text:#1f2430;--announce-link:#4f6bff;--announce-c1:#4f6bff;--announce-c2:#22c55e;--announce-c3:#6b7280;margin:0 0 0 248px!important;width:calc(100% - 248px)!important;border-radius:0 0 14px 14px}',
    'body.theme-dashboard .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.98);--gg-ad-border:#e7eaf1;--gg-ad-shadow:0 10px 28px rgba(31,41,55,.08);margin-left:248px!important;width:calc(100% - 248px)!important}',
    'body.theme-dashboard .mpimg-link-grid .dh{border-radius:12px;border-color:#e7eaf1;box-shadow:0 8px 18px rgba(31,41,55,.1)}',
    '@media (max-width:767px){body.theme-dashboard .theme-announcement-bar,body.theme-dashboard .mpimg-link-band{margin-left:0!important;width:100%!important}}',
    'body.theme-console .theme-announcement-bar{--announce-bg:rgba(255,255,255,.97);--announce-border:#e7eaf0;--announce-shadow:0 10px 28px rgba(28,39,64,.07);--announce-text:#151c2d;--announce-link:#3867f4;--announce-c1:#3867f4;--announce-c2:#16a369;--announce-c3:#7b8497;margin:0 0 22px 244px!important;width:calc(100% - 244px)!important}',
    'body.theme-console .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.98);--gg-ad-border:#e7eaf0;--gg-ad-shadow:0 10px 28px rgba(28,39,64,.07);margin-left:244px!important;width:calc(100% - 244px)!important}',
    'body.theme-console .mpimg-link-grid .dh{border-radius:11px;border-color:#e7eaf0;box-shadow:0 8px 18px rgba(28,39,64,.1)}',
    'body.theme-portal .theme-announcement-bar{--announce-bg:rgba(255,255,255,.96);--announce-border:#dfe7e2;--announce-shadow:0 12px 30px rgba(23,33,29,.08);--announce-text:#17211d;--announce-link:#0d7c57;--announce-c1:#0d7c57;--announce-c2:#2aa773;--announce-c3:#66716c;margin:0 0 6px!important}',
    'body.theme-portal .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.96);--gg-ad-border:#dfe7e2;--gg-ad-shadow:0 12px 30px rgba(23,33,29,.08);margin:0 0 18px!important}',
    'body.theme-portal .mpimg-link-grid .dh{border-radius:12px;border-color:#dfe7e2;box-shadow:0 10px 24px rgba(23,33,29,.08)}',
    'body.theme-workspace .theme-announcement-bar{--announce-bg:rgba(23,28,37,.96);--announce-border:#2a313d;--announce-shadow:0 16px 40px rgba(0,0,0,.4);--announce-text:#edf1f7;--announce-link:#f4c95d;--announce-c1:#f4c95d;--announce-c2:#5ed6a0;--announce-c3:#8993a3;margin:12px 0 0 84px!important;width:calc(100% - 96px)!important;border-radius:14px}',
    'body.theme-workspace .mpimg-link-band{--gg-ad-bg:rgba(23,28,37,.96);--gg-ad-border:#2a313d;--gg-ad-shadow:0 16px 40px rgba(0,0,0,.4);margin-left:84px!important;width:calc(100% - 96px)!important;border-radius:14px}',
    'body.theme-workspace .mpimg-link-grid .dh{border-radius:12px;border-color:#2a313d;background:#1d232e;box-shadow:none}',
    '@media (max-width:767px){body.theme-console .theme-announcement-bar,body.theme-console .mpimg-link-band,body.theme-workspace .theme-announcement-bar,body.theme-workspace .mpimg-link-band{margin-left:0!important;width:100%!important}}',
    'body.theme-celadon .theme-announcement-bar{--announce-bg:rgba(255,255,255,.94);--announce-border:rgba(24,120,120,.2);--announce-shadow:0 14px 36px rgba(24,120,120,.12);--announce-text:#17696b;--announce-link:#2b9c9c;--announce-c1:#2b9c9c;--announce-c2:#17696b;--announce-c3:#2b9c9c}',
    'body.theme-celadon .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.95);--gg-ad-border:rgba(24,120,120,.18);--gg-ad-shadow:0 14px 36px rgba(24,120,120,.12)}',
    'body.theme-celadon .mpimg-link-grid .dh{border-color:rgba(24,120,120,.22);box-shadow:none}',
    'body.theme-lilac .theme-announcement-bar{--announce-bg:rgba(255,255,255,.94);--announce-border:rgba(96,82,190,.2);--announce-shadow:0 14px 36px rgba(96,82,190,.12);--announce-text:#453a94;--announce-link:#6d5dd3;--announce-c1:#6d5dd3;--announce-c2:#453a94;--announce-c3:#6d5dd3}',
    'body.theme-lilac .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.95);--gg-ad-border:rgba(96,82,190,.18);--gg-ad-shadow:0 14px 36px rgba(96,82,190,.12)}',
    'body.theme-lilac .mpimg-link-grid .dh{border-color:rgba(96,82,190,.22);box-shadow:none}',
    'body.theme-paper .theme-announcement-bar{--announce-bg:rgba(255,255,255,.94);--announce-border:rgba(120,114,98,.2);--announce-shadow:0 14px 36px rgba(120,114,98,.12);--announce-text:#6b6760;--announce-link:#3f3f3d;--announce-c1:#3f3f3d;--announce-c2:#6b6760;--announce-c3:#3f3f3d}',
    'body.theme-paper .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.95);--gg-ad-border:rgba(120,114,98,.18);--gg-ad-shadow:0 14px 36px rgba(120,114,98,.12)}',
    'body.theme-paper .mpimg-link-grid .dh{border-color:rgba(120,114,98,.22);box-shadow:none}',
    'body.theme-blush .theme-announcement-bar{--announce-bg:rgba(255,255,255,.94);--announce-border:rgba(190,110,135,.2);--announce-shadow:0 14px 36px rgba(190,110,135,.12);--announce-text:#a13c5d;--announce-link:#e0648a;--announce-c1:#e0648a;--announce-c2:#a13c5d;--announce-c3:#e0648a}',
    'body.theme-blush .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.95);--gg-ad-border:rgba(190,110,135,.18);--gg-ad-shadow:0 14px 36px rgba(190,110,135,.12)}',
    'body.theme-blush .mpimg-link-grid .dh{border-color:rgba(190,110,135,.22);box-shadow:none}',
    'body.theme-sky .theme-announcement-bar{--announce-bg:rgba(255,255,255,.94);--announce-border:rgba(14,120,180,.2);--announce-shadow:0 14px 36px rgba(14,120,180,.12);--announce-text:#075e86;--announce-link:#0ea5e9;--announce-c1:#0ea5e9;--announce-c2:#075e86;--announce-c3:#0ea5e9}',
    'body.theme-sky .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.95);--gg-ad-border:rgba(14,120,180,.18);--gg-ad-shadow:0 14px 36px rgba(14,120,180,.12)}',
    'body.theme-sky .mpimg-link-grid .dh{border-color:rgba(14,120,180,.22);box-shadow:none}',
    'body.theme-mint .theme-announcement-bar{--announce-bg:rgba(255,255,255,.94);--announce-border:rgba(30,150,100,.2);--announce-shadow:0 14px 36px rgba(30,150,100,.12);--announce-text:#106b41;--announce-link:#22b573;--announce-c1:#22b573;--announce-c2:#106b41;--announce-c3:#22b573}',
    'body.theme-mint .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.95);--gg-ad-border:rgba(30,150,100,.18);--gg-ad-shadow:0 14px 36px rgba(30,150,100,.12)}',
    'body.theme-mint .mpimg-link-grid .dh{border-color:rgba(30,150,100,.22);box-shadow:none}',
    'body.theme-sunset .theme-announcement-bar{--announce-bg:rgba(255,255,255,.14);--announce-border:rgba(255,255,255,.24);--announce-shadow:0 16px 42px rgba(60,10,40,.3);--announce-text:#fff3ea;--announce-link:#ffb057;--announce-c1:#ffb057;--announce-c2:#ff6b9d;--announce-c3:#fff3ea}',
    'body.theme-sunset .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.14);--gg-ad-border:rgba(255,255,255,.24);--gg-ad-shadow:0 16px 42px rgba(60,10,40,.3)}',
    'body.theme-sunset .mpimg-link-grid .dh{border-color:rgba(255,255,255,.3);box-shadow:none}',
    'body.theme-abyss .theme-announcement-bar{--announce-bg:rgba(255,255,255,.14);--announce-border:rgba(255,255,255,.24);--announce-shadow:0 16px 42px rgba(2,24,36,.3);--announce-text:#e8fbff;--announce-link:#38e0d8;--announce-c1:#38e0d8;--announce-c2:#7dd3fc;--announce-c3:#e8fbff}',
    'body.theme-abyss .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.14);--gg-ad-border:rgba(255,255,255,.24);--gg-ad-shadow:0 16px 42px rgba(2,24,36,.3)}',
    'body.theme-abyss .mpimg-link-grid .dh{border-color:rgba(255,255,255,.3);box-shadow:none}',
    'body.theme-emerald .theme-announcement-bar{--announce-bg:rgba(255,255,255,.14);--announce-border:rgba(255,255,255,.24);--announce-shadow:0 16px 42px rgba(2,34,20,.3);--announce-text:#eafff3;--announce-link:#4ade80;--announce-c1:#4ade80;--announce-c2:#5eead4;--announce-c3:#eafff3}',
    'body.theme-emerald .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.14);--gg-ad-border:rgba(255,255,255,.24);--gg-ad-shadow:0 16px 42px rgba(2,34,20,.3)}',
    'body.theme-emerald .mpimg-link-grid .dh{border-color:rgba(255,255,255,.3);box-shadow:none}',
    'body.theme-sakura .theme-announcement-bar{--announce-bg:rgba(255,255,255,.7);--announce-border:rgba(255,255,255,.24);--announce-shadow:0 16px 42px rgba(190,120,160,.3);--announce-text:#3d2030;--announce-link:#e0648a;--announce-c1:#e0648a;--announce-c2:#8b5cf6;--announce-c3:#3d2030}',
    'body.theme-sakura .mpimg-link-band{--gg-ad-bg:rgba(255,255,255,.7);--gg-ad-border:rgba(255,255,255,.24);--gg-ad-shadow:0 16px 42px rgba(190,120,160,.3)}',
    'body.theme-sakura .mpimg-link-grid .dh{border-color:rgba(255,255,255,.3);box-shadow:none}',
    '.navbar{margin-bottom:0}',
    '@keyframes themeAnnouncementScroll{from{transform:translateX(0)}to{transform:translateX(-100%)}}',
    '@keyframes themeAnnouncementColor{0%,100%{color:var(--announce-c1)}35%{color:var(--announce-c2)}70%{color:var(--announce-c3)}}',
    '@media (max-width:768px){.theme-announcement-bar{margin:-18px 0 0}.theme-announcement-text{padding:0 14px;line-height:32px;font-size:14px}.mpimg-link-band{margin:0 0 14px;padding:2px 0 0;--mpimg-ad-per-view:1!important}.mpimg-ad-slide{padding:0 4px}.mpimg-link-grid{width:calc(100% - 16px);padding:0 8px}.mpimg-link-grid .dh{min-width:0;min-height:32px;padding:7px 12px;font-size:12px}.mpimg-link-grid .dh.has-image img{width:100%!important;border-radius:10px}.mpimg-ad-nav{width:26px;height:26px;font-size:12px}.mpimg-ad-prev{left:6px}.mpimg-ad-next{right:6px}.mpimg-ad-dots{margin-top:6px}.mpimg-ad-carousel[data-tooltip]:not([data-tooltip=""]):hover::after{min-width:130px;max-width:200px;font-size:11px;padding:6px 8px}}',
    //各套外观里给广告条写死的白底、边框、毛玻璃和重阴影统一去掉，只留居中的一排小按钮
    '.mpimg-link-band{background:none!important;border-top:0!important;border-bottom:0!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}',
    'body.theme-mac .mpimg-link-band{border-left:0!important;border-right:0!important}',
    'body .mpimg-link-grid .dh{box-shadow:0 2px 6px rgba(24,46,84,.14)!important}',
    'body .mpimg-link-grid .dh:hover,body .mpimg-link-grid .dh:focus{box-shadow:0 4px 12px rgba(24,46,84,.2)!important}',
    'body .mpimg-link-grid .dh.has-image{box-shadow:none!important}'
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
  // 工作台家族的导航是左侧栏，顶部另有一条搜索条紧跟在它后面；
  // 插在侧栏后面会把公告条顶到搜索条上方去，所以这几套改成插在搜索条后面
  var topbar = document.querySelector('.studio-topbar');
  if (topbar && topbar.parentNode) {
    if (topbar.nextSibling) {
      topbar.parentNode.insertBefore(element, topbar.nextSibling);
    } else {
      topbar.parentNode.appendChild(element);
    }
    return;
  }
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
  //首页是服务端渲染的，公告条放在导航栏之后；这里保持一致，不要塞进导航栏内部
  //（侧栏主题下导航栏是 flex 容器，塞进去会把侧栏内容挤窄）
  insertAfterNavbar(bar);
}

//一屏并排「每屏条数」条，放不下的翻页轮播：5 秒一页，鼠标移上去或切到后台标签页就暂停。
function renderAds() {
  if (mpimgPayload.type !== 'ads' || document.querySelector('.mpimg-link-band[data-mpimg-dynamic="ads"], .mpimg-link-band[data-mpimg-dynamic="gg"], .mpimg-link-grid[data-mpimg-dynamic="ads"], .mpimg-link-grid[data-mpimg-dynamic="gg"], .txtguanggao[data-mpimg-dynamic="ads"], .txtguanggao[data-mpimg-dynamic="gg"]')) {
    return;
  }
  var ads = mpimgPayload.ads || [];
  var band = document.createElement('div');
  band.className = 'mpimg-link-band';
  band.setAttribute('data-mpimg-dynamic', 'ads');
  band.style.setProperty('--mpimg-ad-img-w', mpimgPayload.imageWidth > 0 ? mpimgPayload.imageWidth + 'px' : '100%');
  band.style.setProperty('--mpimg-ad-img-h', mpimgPayload.imageHeight > 0 ? mpimgPayload.imageHeight + 'px' : 'auto');
  band.style.setProperty('--mpimg-ad-img-fit', mpimgPayload.imageFit || 'cover');
  var wrap = document.createElement('div');
  wrap.className = 'mpimg-link-grid mpimg-ad-carousel';
  wrap.setAttribute('data-mpimg-dynamic', 'ads');
  var viewport = document.createElement('div');
  viewport.className = 'mpimg-ad-viewport';
  var track = document.createElement('div');
  track.className = 'mpimg-ad-track';

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
    var slide = document.createElement('div');
    slide.className = 'mpimg-ad-slide';
    slide.appendChild(link);
    track.appendChild(slide);
  }

  if (track.children.length > 0) {
    //格子宽度始终按后台设的并排条数算，广告条数不够就从左往右排、后面的格子空着
    var columns = Math.max(1, Math.min(parseInt(mpimgPayload.perView, 10) || 3, 4));
    band.style.setProperty('--mpimg-ad-per-view', String(columns));
    band.style.setProperty('--mpimg-ad-grid-max', (mpimgPayload.imageWidth > 0 ? Math.max(1320, (mpimgPayload.imageWidth + 24) * columns) : 1320) + 'px');
    viewport.appendChild(track);
    wrap.appendChild(viewport);
    band.appendChild(wrap);
    insertAfterNavbar(band);
    if (window.mpimgInitAdCarousels) { window.mpimgInitAdCarousels(); }
  }
}

ready(function () {
  ensureStyle();
  renderAnnouncement();
  renderAds();
  if (window.mpimgInitAdCarousels) { window.mpimgInitAdCarousels(); }
});
JS;
	echo "\n})();\n";
}
