/*
 * 「我的文件」列表的局部刷新，个人中心文件页和游客 ?m=mine 共用。
 *
 * 原来改名、删除、移动、复制、新建文件夹之后都 location.reload()，进文件夹、翻页、筛选、搜索是整页跳转，
 * 界面会白一下。这里改成：带上 X-Pan-Partial 请求头取同一地址的完整页面（服务端见到它就沿用会话里
 * 现有的 csrf 令牌，见 page_csrf_token()），只把列表相关的几块换上去，地址栏用 pushState 同步。
 *
 * 表格本身、表头（全选框）和批量操作条不换，只换 tbody：usercenter.js、folders.js 和游客页脚本
 * 绑在表格上的委托事件、绑在全选框和批量条按钮上的事件都还在。换完触发 pan:listrefresh，
 * 各脚本借这个事件刷新自己的状态（批量条计数、剪切的淡化、拖拽属性）。
 *
 * 取页失败、或新页面里找不到列表时退回整页跳转，不会比原来更差。
 */
(function ($) {
  if (!$ || !window.history || !history.pushState || !window.DOMParser || !window.URL) return;
  var $list = $('.uc-filelist');
  if ($list.length !== 1) return;

  // 除 tbody 以外要跟着换的区块（都带计数或当前位置）。两边数量对得上才换，某个外观没有的区块自然跳过
  var REGIONS = ['.fd-bar', '.uc-toolbar', '.uc-filters', '.searchbox', '.layout-stats', '.layout-filters',
    '.cockpit-head', '.cockpit-quota', '.filelist-footer'];

  // 是不是同一个列表页：个人中心认 tab=files，游客认 m=mine；首页的 ./ 和 index.php 是同一个地方
  function listKey(u) {
    var path = u.pathname.replace(/\/index\.php$/, '/');
    var tab = u.searchParams.get('tab') || (/\/user\.php$/.test(path) ? 'overview' : '');
    return path + '|' + tab + '|' + (u.searchParams.get('m') || '');
  }
  var here = listKey(new URL(location.href));

  function sameList(href) {
    var u;
    try { u = new URL(href, location.href); } catch (e) { return null; }
    if (u.origin !== location.origin || listKey(u) !== here) return null;
    return u;
  }

  // 新页面里的 PAN_FOLDER（当前文件夹、是否在搜索）同步到现有对象上，folders.js 手里拿的就是这个对象
  function syncConfig(doc) {
    if (!window.PAN_FOLDER) return;
    $(doc).find('script:not([src])').each(function () {
      var t = this.textContent || '';
      var i = t.indexOf('var PAN_FOLDER = ');
      if (i === -1) return;
      try { $.extend(window.PAN_FOLDER, JSON.parse(t.slice(i + 17, t.lastIndexOf('}') + 1))); } catch (e) {}
      return false;
    });
  }

  // 进了别的文件夹、翻了页，列表顶部如果已经滚出屏幕就滚回来；本来就看得见就不动
  function scrollToList() {
    var $anchor = $('.fd-bar').length ? $('.fd-bar').first() : $list;
    var top = $anchor.offset().top - 12;
    if ($(window).scrollTop() > top) $('html,body').scrollTop(top);
  }

  var xhr = null;
  var seq = 0;

  function refresh(url, opts) {
    opts = opts || {};
    url = url || location.href;
    var my = ++seq;
    if (xhr) xhr.abort();
    // 慢的时候才把列表压暗；一般一两百毫秒就回来了，什么都不变，免得又闪一下
    var dim = setTimeout(function () { $list.addClass('fl-loading'); }, 250);
    var req = $.ajax({ url: url, type: 'GET', dataType: 'text', cache: false, headers: { 'X-Pan-Partial': '1' } });
    xhr = req;
    req.done(function (html) {
      if (my !== seq) return;
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var $nl = $(doc).find('.uc-filelist');
      if ($nl.length !== 1 || !$nl.children('tbody').length) { location.href = url; return; }
      $list.children('tbody').replaceWith(document.adoptNode($nl.children('tbody')[0]));
      $.each(REGIONS, function (i, sel) {
        var $o = $(sel);
        var $n = $(doc).find(sel);
        if (!$o.length || $o.length !== $n.length) return;
        $o.each(function (k) { $(this).replaceWith(document.adoptNode($n[k])); });
      });
      syncConfig(doc);
      $list.find('thead input[type="checkbox"]').prop('checked', false);
      if (opts.push && url !== location.href) history.pushState({ panList: 1 }, '', url);
      if (doc.title) document.title = doc.title;
      $(document).trigger('pan:listrefresh');
      if (opts.scroll) scrollToList();
    }).fail(function (x, status) {
      if (status !== 'abort' && my === seq) location.href = url;
    }).always(function () {
      clearTimeout(dim);
      if (my === seq) { $list.removeClass('fl-loading'); xhr = null; }
    });
    return req;
  }

  // 列表页内的链接：进文件夹、面包屑、翻页、筛选标签、清除搜索。新窗口打开、带修饰键的点击照旧交给浏览器
  $(document).on('click', 'a[href]', function (e) {
    if (e.isDefaultPrevented() || e.which > 1 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if ((this.target && this.target !== '_self') || this.hasAttribute('download') || this.hasAttribute('data-toggle')) return;
    // 下拉菜单之类的 href="#"、javascript: 链接解析出来也是本页，不能接管
    var raw = this.getAttribute('href') || '';
    if (raw === '' || raw.charAt(0) === '#' || /^\s*javascript:/i.test(raw)) return;
    var u = sameList(this.href);
    if (!u) return;
    e.preventDefault();
    refresh(u.href, { push: true, scroll: true });
  });

  // 搜索框（GET 表单）提交到本列表页的，也走局部切换
  $(document).on('submit', 'form', function (e) {
    if (e.isDefaultPrevented() || String(this.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
    // 先把表单参数拼进地址再判断：个人中心的 tab=files 是放在隐藏输入框里的，光看 action 认不出是文件页
    var u;
    try { u = new URL(this.getAttribute('action') || location.href, location.href); } catch (err) { return; }
    u.search = '?' + $(this).serialize();
    if (!sameList(u.href)) return;
    e.preventDefault();
    refresh(u.href, { push: true, scroll: true });
  });

  // 浏览器前进/后退：地址已经变了，按新地址把列表换过来。只改了 # 后面的部分（锚点）也会触发 popstate，那种不管
  function noHash(href) { return String(href).split('#')[0]; }
  var shown = noHash(location.href);
  $(document).on('pan:listrefresh', function () { shown = noHash(location.href); });
  window.addEventListener('popstate', function () {
    if (noHash(location.href) === shown) return;
    if (sameList(location.href)) refresh(location.href, {});
    else location.reload();
  });

  // 给其它脚本用：操作成功后调 PanList.refresh() 代替 location.reload()
  window.PanList = {
    refresh: function () { return refresh(location.href, {}); }
  };
})(window.jQuery);
