/*
 * macOS 窗口风：文件列表的网格 / 列表视图切换。
 * 只在这一套外观的文件列表页加载，别的页面和别的外观都不会引进来。
 *
 * 视图状态存在 localStorage 里，翻页、搜索、重新打开都保持上次的选择；
 * 浏览器禁用了存储（隐私模式）时读写都会抛异常，全部吞掉，只是记不住而已，
 * 页面本身仍按默认的网格视图正常显示。
 */
(function () {
  var KEY = 'mac_filelist_view';
  var LIST_CLASS = 'mac-list-view';

  function readView() {
    try {
      return window.localStorage.getItem(KEY) === 'list' ? 'list' : 'grid';
    } catch (e) {
      return 'grid';
    }
  }

  function saveView(view) {
    try {
      window.localStorage.setItem(KEY, view);
    } catch (e) {}
  }

  function apply(table, buttons, view) {
    if (view === 'list') {
      table.className += table.className.indexOf(LIST_CLASS) >= 0 ? '' : ' ' + LIST_CLASS;
    } else {
      table.className = table.className.replace(new RegExp('(^|\\s)' + LIST_CLASS + '(?=\\s|$)', 'g'), '');
    }
    for (var i = 0; i < buttons.length; i++) {
      var on = buttons[i].getAttribute('data-mac-view') === view;
      buttons[i].className = on ? 'active' : '';
      buttons[i].setAttribute('aria-pressed', on ? 'true' : 'false');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('macViewToggle');
    var table = document.querySelector('.filelist-main');
    if (!toggle || !table) return;
    var buttons = toggle.querySelectorAll('button[data-mac-view]');
    if (!buttons.length) return;

    apply(table, buttons, readView());

    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function () {
        var view = this.getAttribute('data-mac-view') === 'list' ? 'list' : 'grid';
        apply(table, buttons, view);
        saveView(view);
      });
    }
  });
})();
