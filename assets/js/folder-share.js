/* 分享页面只替换主体，目录切换、搜索和翻页保持页面稳定。 */
(function () {
  'use strict';
  if (!window.fetch || !window.AbortController || !window.URLSearchParams || !Element.prototype.replaceWith) return;
  var pending;
  function navigate(url, push) {
    if (pending) pending.abort();
    var controller = new AbortController();
    pending = controller;
    var main = document.getElementById('share-main');
    main.classList.add('share-loading');
    fetch(url, { credentials: 'same-origin', headers: { 'X-Pan-Share': '1' }, signal: controller.signal })
      .then(function (response) { return response.text(); })
      .then(function (html) {
        if (pending !== controller) return;
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.getElementById('share-main');
        if (!next) throw new Error('invalid response');
        main.replaceWith(next);
        document.title = next.getAttribute('data-title') + ' · 文件夹分享';
        if (push) history.pushState({}, '', url);
      }).catch(function (error) {
        if (error.name !== 'AbortError') location.assign(url);
      }).finally(function () {
        if (pending === controller) {
          pending = null;
          document.getElementById('share-main').classList.remove('share-loading');
        }
      });
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-share-nav]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    navigate(link.href, true);
  });
  document.addEventListener('submit', function (event) {
    if (!event.target.matches('[data-share-search]')) return;
    event.preventDefault();
    navigate(event.target.action + '?' + new URLSearchParams(new FormData(event.target)).toString(), true);
  });
  window.addEventListener('popstate', function () { navigate(location.href, false); });
})();
