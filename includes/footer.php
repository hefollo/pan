<?php
/*
 * 页脚。结构上有三条约定，改的时候别破坏：
 *   1. <footer class="footer"> 必须直接挂在 <body> 下：各外观的页脚样式（侧栏外观给页脚留的左边距、
 *      macOS 窗口风的状态栏、涂鸦风的标语……）都是按 body>footer.footer 写的。所以引入本文件之前，
 *      页面要把自己的 .container 等外层全部收掉。
 *   2. 页脚前面的 #siteFooterGap 是占位块：自带最小高度当作「内容和页脚之间的间距」，
 *      内容不满一屏时由下面的脚本把它撑高，页脚就落在窗口底部，不会悬在半空。
 *      不用 body flex 来贴底：.container 两侧是 auto 边距，变成 flex 子项后宽度会缩成内容宽度，牵连所有外观的主体布局。
 *   3. 版权和每条看得见的页脚代码各占一格（.site-footer-item），排成一行、放不下自动换行；
 *      统计代码这类看不见的不占格子，统一放在末尾的 .site-footer-extra 里。
 */
$footer_groups = footer_code_groups();
?>
<div class="site-footer-gap" id="siteFooterGap" aria-hidden="true"></div>
<footer class="footer text-center">
      <div class="container">
        <p class="text-muted site-footer-line"><span class="site-footer-item">Copyright &copy; <?php echo date('Y')?> <a href="/"><?php echo $conf['title']?></a></span><?php foreach($footer_groups['shown'] as $footer_code){?><span class="site-footer-item"><?php echo $footer_code?></span><?php }?></p>
<?php if(site_runtime_open()){?>
        <p class="text-muted site-runtime">本站已安全运行：<span id="site-runtime-time"></span></p>
<?php }?>
<?php if($footer_groups['silent']){?>
        <div class="site-footer-extra"><?php echo implode("\n", $footer_groups['silent'])?></div>
<?php }?>
      </div>
    </footer>
<script>
/*
 * 页脚贴底：内容不满一屏时，把页脚前面的占位块撑高，让页脚正好落在窗口底部。
 * 算法不依赖「先把占位块缩回去再量」，直接用当前高度反推，所以反复调用不会闪。
 * 占位块被样式隐藏的外观（macOS 窗口风：页脚是窗口底部的状态栏，要紧贴窗口）不处理。
 */
(function(){
  var gap = document.getElementById('siteFooterGap'), foot = gap ? gap.nextElementSibling : null;
  if(!gap || !foot || !window.getComputedStyle) return;
  var min = null;
  function fit(){
    var gs = getComputedStyle(gap);
    if(gs.display === 'none') return;
    if(min === null) min = parseFloat(gs.minHeight) || 0;
    var cur = gap.getBoundingClientRect().height;
    var bs = getComputedStyle(document.body), fs = getComputedStyle(foot);
    //页脚底边再往下的东西：页脚自己的下边距、body 的下内边距
    var tail = (parseFloat(fs.marginBottom) || 0) + (parseFloat(bs.paddingBottom) || 0) + (parseFloat(bs.marginBottom) || 0);
    var bottom = foot.getBoundingClientRect().bottom + (window.pageYOffset || document.documentElement.scrollTop || 0) + tail;
    var need = Math.max(min, cur + (window.innerHeight || document.documentElement.clientHeight) - bottom);
    need = Math.floor(need);
    if(Math.abs(need - cur) >= 1) gap.style.height = need + 'px';
  }
  fit();
  window.addEventListener('load', fit);
  window.addEventListener('resize', fit);
  //列表局部刷新、图片加载、折叠展开都会改变内容高度
  if(window.ResizeObserver){
    try{ new ResizeObserver(function(){ fit(); }).observe(document.body); }catch(e){}
  }else{
    setInterval(fit, 1500);
  }
})();
</script>
<?php if(site_runtime_open()){?>
<script>
//页脚运行时间：起点是后台「网站信息设置 → 建站时间」，服务端按北京时间换成时间戳给过来；年按 365 天算
(function(){
  var start = <?php echo site_runtime_start()*1000?>, el = document.getElementById('site-runtime-time');
  if(!el) return;
  function tick(){
    var s = Math.max(0, Math.floor((Date.now() - start) / 1000));
    var y = Math.floor(s / 31536000); s -= y * 31536000;
    var d = Math.floor(s / 86400); s -= d * 86400;
    var h = Math.floor(s / 3600); s -= h * 3600;
    var m = Math.floor(s / 60); s -= m * 60;
    el.textContent = y + '年' + d + '天' + h + '时' + m + '分钟' + s + '秒';
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php }?>
<script>
//把当前外观存一份到浏览器，静态的 404.html 读不到后台配置，靠这个跟随外观
try{localStorage.setItem('site_theme','<?php echo isset($site_theme)?$site_theme:default_site_theme()?>');}catch(e){}
</script>
<?php //蓝白工作台风：顶栏的 Ctrl K 快捷键每个页面都要能用，排序和视图切换只有列表页有元素，脚本里各自判断
if(isset($site_theme) && in_array($site_theme, studio_family_keys(), true)){?>
<script src="assets/js/layout-studio.js?v=<?php echo VERSION?>"></script>
<?php }?>
<script src="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/js/material.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/js/ripples.min.js"></script>
<script>
  $.material.init();
  /*
   * 手机上点了导航菜单里的链接，就把展开的菜单收起来。
   * 一般的链接是整页跳转，新页面的菜单本来就是收着的；但「我的文件」页会把指向本列表的链接
   * 改成局部刷新（assets/js/filelist-live.js，导航里的「我的文件」也在内），页面不重载，菜单就一直开着。
   * 带 data-toggle 的是下拉开关本身，点它是为了展开下拉，不收。桌面端菜单没有 .in，这里不会触发。
   */
  $(document).on('click', '.navbar-collapse.in a[href]', function(){
    if(this.getAttribute('data-toggle'))return;
    var $menu = $(this).closest('.navbar-collapse');
    if($.fn.collapse)$menu.collapse('hide');
  });
</script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6112564004010114"crossorigin="anonymous"></script>