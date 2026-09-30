<footer class="footer text-center">
      <div class="container">
        <p class="text-muted">Copyright &copy; <?php echo date('Y')?> <a href="/"><?php echo $conf['title']?></a> <?php echo footer_codes_html()?> </p>
<?php if(site_runtime_open()){?>
        <p class="text-muted site-runtime">本站已安全运行：<span id="site-runtime-time"></span></p>
<?php }?>
      </div>
    </footer>
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
</script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6112564004010114"crossorigin="anonymous"></script>