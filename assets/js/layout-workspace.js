/**
 * 深色工作台风的右侧文件预览面板：点列表行填充右侧详情，数据全部取自行上的 data-*，
 * 不额外请求接口。其它外观不会加载这个文件。
 */
(function () {
  var panel = document.getElementById('layoutPreview');
  if (!panel) return;

  var empty = panel.querySelector('.layout-preview-empty');
  var body = panel.querySelector('.layout-preview-body');
  var art = panel.querySelector('.layout-preview-art');
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

  //预览区：没有密码的图片/音视频直接放出来，其余（含加密、已封禁、不可预览的类型）显示类型图标
  var previewSeq = 0;

  function renderArt(row) {
    var kind = row.getAttribute('data-preview-kind');
    var src = row.getAttribute('data-preview');
    previewSeq++;
    art.innerHTML = '';
    art.classList.remove('is-media', 'is-text');

    if (!src || !kind) {
      showIcon(row);
      return;
    }
    if (kind === 'image') {
      var img = document.createElement('img');
      img.className = 'layout-preview-img';
      img.alt = row.getAttribute('data-name') || '';
      //文件可能已被删除或替换，加载失败就退回图标，不要留一个破图
      img.onerror = function () { showIcon(row); };
      img.src = src;
      art.appendChild(img);
      art.classList.add('is-media');
      return;
    }
    if (kind === 'video') {
      var video = document.createElement('video');
      video.className = 'layout-preview-video';
      video.src = src;
      video.controls = true;
      video.preload = 'metadata';
      video.onerror = function () { showIcon(row); };
      art.appendChild(video);
      art.classList.add('is-media');
      return;
    }
    if (kind === 'text') {
      renderText(row, src);
      return;
    }
    if (kind === 'audio') {
      //音频没有画面，图标照常显示，下面挂一个播放条
      showIcon(row);
      var audio = document.createElement('audio');
      audio.className = 'layout-preview-audio';
      audio.src = src;
      audio.controls = true;
      audio.preload = 'metadata';
      art.appendChild(audio);
      return;
    }
    showIcon(row);
  }

  /*
   * 文本预览：内容一律用 textContent 塞进 <pre>，绝不能当 HTML 解析——
   * 这些都是用户上传的文件，里面可能有 <script>。
   * 请求带序号，切换文件时晚回来的响应直接丢弃，不会把别的文件内容显示到当前文件上。
   */
  function renderText(row, src) {
    var seq = ++previewSeq;
    showIcon(row);
    var tip = document.createElement('div');
    tip.className = 'layout-preview-loading';
    tip.textContent = '正在读取内容…';
    art.appendChild(tip);

    //Accept 不能用默认的 */*：txprotect.php 会把手机 UA + Accept:*/* 的请求当机器人拦掉
    fetch(src, {
      credentials: 'same-origin',
      headers: { 'Accept': 'text/plain, */*; q=0.01' }
    }).then(function (r) {
      if (!r.ok) throw new Error('http ' + r.status);
      return r.text();
    }).then(function (text) {
      if (seq !== previewSeq) return;
      art.innerHTML = '';
      art.classList.add('is-text');
      var pre = document.createElement('pre');
      pre.className = 'layout-preview-text';
      var limit = 4000;
      var tail = '\n\n…（内容过长，仅预览前 ' + limit + ' 个字符）';
      pre.textContent = text.length > limit ? text.slice(0, limit) + tail : text;
      art.appendChild(pre);
    }).catch(function () {
      if (seq !== previewSeq) return;
      //读取失败时给一句提示，而不是退回图标——否则和"根本没触发预览"看起来一模一样，不好排查
      showIcon(row);
      var err = document.createElement('div');
      err.className = 'layout-preview-loading';
      err.textContent = '内容读取失败';
      art.appendChild(err);
    });
  }

  function showIcon(row) {
    art.innerHTML = '';
    art.classList.remove('is-media', 'is-text');
    var icon = document.createElement('i');
    icon.className = 'fa ' + (row.getAttribute('data-icon') || 'fa-file-o');
    art.appendChild(icon);
  }

  function select(row) {
    Array.prototype.forEach.call(rows, function (r) { r.classList.remove('is-selected'); });
    row.classList.add('is-selected');

    var down = absolute(row.getAttribute('data-down'));
    var view = row.getAttribute('data-view');
    var type = row.getAttribute('data-type') || '';

    renderArt(row);
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
