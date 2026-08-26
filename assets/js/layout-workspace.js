/**
 * 深色工作台风的右侧文件预览面板：点列表行填充右侧详情，数据全部取自行上的 data-*，
 * 不额外请求接口。其它外观不会加载这个文件。
 */
(function () {
  var panel = document.getElementById('layoutPreview');
  if (!panel) return;

  var empty = panel.querySelector('.layout-preview-empty');
  var body = panel.querySelector('.layout-preview-body');
  var art = panel.querySelector('.layout-preview-art i');
  var nameEl = panel.querySelector('.layout-preview-name');
  var subEl = panel.querySelector('.layout-preview-sub');
  var downloadEl = panel.querySelector('.layout-preview-download');
  var openEl = panel.querySelector('.layout-preview-open');
  var linkEl = panel.querySelector('.layout-preview-link code');
  var fields = {};
  Array.prototype.forEach.call(panel.querySelectorAll('[data-field]'), function (el) {
    fields[el.getAttribute('data-field')] = el;
  });

  var rows = document.querySelectorAll('.filelist tbody tr[data-name]');
  if (!rows.length) return;

  function absolute(url) {
    var a = document.createElement('a');
    a.href = url;
    return a.href;
  }

  function copy(text, button) {
    var done = function (ok) {
      var old = button.getAttribute('data-label') || button.innerHTML;
      button.setAttribute('data-label', old);
      button.classList.add(ok ? 'is-copied' : 'is-failed');
      button.innerHTML = ok ? '<i class="fa fa-check"></i> 已复制' : '<i class="fa fa-times"></i> 复制失败';
      setTimeout(function () {
        button.innerHTML = old;
        button.classList.remove('is-copied', 'is-failed');
      }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
      return;
    }
    var input = document.createElement('textarea');
    input.value = text;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(input);
    done(ok);
  }

  function select(row) {
    Array.prototype.forEach.call(rows, function (r) { r.classList.remove('is-selected'); });
    row.classList.add('is-selected');

    var down = absolute(row.getAttribute('data-down'));
    var view = row.getAttribute('data-view');
    var type = row.getAttribute('data-type') || '';

    art.className = 'fa ' + (row.getAttribute('data-icon') || 'fa-file-o');
    panel.setAttribute('data-group', row.getAttribute('data-group') || 'other');
    nameEl.textContent = row.getAttribute('data-name') || '';
    subEl.textContent = (type ? type.toUpperCase() + ' · ' : '') + (row.getAttribute('data-size') || '');
    downloadEl.href = row.getAttribute('data-down');
    openEl.href = view;
    linkEl.textContent = down;
    if (fields.size) fields.size.textContent = row.getAttribute('data-size') || '';
    if (fields.type) fields.type.textContent = type || '未知';
    if (fields.time) fields.time.textContent = row.getAttribute('data-time') || '';
    if (fields.ip) fields.ip.textContent = row.getAttribute('data-ip') || '';

    empty.hidden = true;
    body.hidden = false;
  }

  Array.prototype.forEach.call(rows, function (row) {
    row.addEventListener('click', function (e) {
      //行内的下载/查看/编辑按钮要保持原来的跳转行为
      if (e.target.closest('a, button')) return;
      select(row);
    });
  });

  Array.prototype.forEach.call(panel.querySelectorAll('.layout-preview-copy, .layout-preview-copy2'), function (btn) {
    btn.addEventListener('click', function () { copy(linkEl.textContent, btn); });
  });

  select(rows[0]);
})();
