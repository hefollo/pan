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
  function message(text) {
    var status = document.getElementById('share-status');
    if (!status) {
      status = document.createElement('p'); status.id = 'share-status';
      status.className = 'share-status'; status.setAttribute('role', 'status');
      document.getElementById('share-main').prepend(status);
    }
    status.textContent = text;
  }
  function jsonRequest(url, data) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', body: data })
      .then(function (r) { if (!r.ok) throw new Error('请求失败，请稍后重试'); return r.json(); })
      .then(function (r) { if (r.code < 0) throw new Error(r.msg || '操作失败'); return r; });
  }
  function freshToken() {
    return fetch('./ajax.php?act=csrf_token', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); }).then(function (r) {
        if (!r.csrf_token) throw new Error('登录状态已失效，请重新登录');
        return r.csrf_token;
      });
  }
  function copyLink(url) {
    var full = new URL(url, location.href).href;
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(full).then(function () { message('链接已复制'); })
        .catch(function () { window.prompt('请复制分享链接', full); });
    } else window.prompt('请复制分享链接', full);
  }
  function hashFile(file) {
    return new Promise(function (resolve, reject) {
      if (!window.SparkMD5) { reject(new Error('上传组件未加载，请刷新后重试')); return; }
      var spark = new SparkMD5.ArrayBuffer(), offset = 0, reader = new FileReader();
      reader.onerror = function () { reject(new Error('读取文件失败')); };
      reader.onload = function (event) {
        spark.append(event.target.result); offset += event.target.result.byteLength;
        if (offset < file.size) read(); else resolve(spark.end());
      };
      function read() { reader.readAsArrayBuffer(file.slice(offset, offset + 2097152)); }
      read();
    });
  }
  function replaceFile(file, id) {
    var csrf;
    function post(action, fields) {
      var data = fields instanceof FormData ? fields : new URLSearchParams(fields);
      data.set('csrf_token', csrf);
      return jsonRequest('./ajax.php?act=' + action, data).then(function (r) {
        if (r.csrf_token) csrf = r.csrf_token;
        return r;
      });
    }
    message('正在读取并替换文件，请保持页面打开…');
    return freshToken().then(function (value) { csrf = value; return hashFile(file); })
      .then(function (hash) { return post('pre_upload', { name: file.name, hash: hash, size: file.size, show: '1', ispwd: '0', pwd: '', replace_id: id }); })
      .then(function (pre) {
        if (pre.code === 1) return pre;
        if (pre.code !== 0) throw new Error(pre.msg || '无法开始替换');
        if (pre.third) {
          var data = new FormData();
          Object.keys(pre.post).forEach(function (key) { data.append(key, pre.post[key]); });
          data.append('file', file);
          return fetch(pre.url, { method: 'POST', body: data }).then(function (r) {
            if (!r.ok) throw new Error('文件上传失败');
            return post('complete_upload', { hash: pre.hash });
          }).then(function (r) { if (r.code !== 1) throw new Error(r.msg || '替换未完成'); return r; });
        }
        function chunk(number) {
          var data = new FormData();
          data.append('file', file.slice((number - 1) * pre.chunksize, number * pre.chunksize));
          data.append('hash', pre.hash); data.append('chunk', number);
          return post('upload_part', data).then(function (r) {
            message('正在替换：' + number + ' / ' + pre.chunks);
            if (r.code === 1) return r;
            if (number < pre.chunks) return chunk(number + 1);
            throw new Error(r.msg || '上传未完成，请重试');
          });
        }
        return chunk(1);
      });
  }
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-share-action]');
    if (!button) return;
    var action = button.getAttribute('data-share-action');
    if (action === 'copy') { copyLink(button.getAttribute('data-url')); return; }
    var main = document.getElementById('share-main');
    if (!main.getAttribute('data-manage-csrf')) return;
    var row = button.closest('[data-file-id]'), id = row.getAttribute('data-file-id');
    var name = row.getAttribute('data-name'), fields = { id: id }, endpoint;
    if (action === 'replace') {
      if (!window.confirm('用新文件替换《' + name + '》？链接保持不变，原内容不可恢复。')) return;
      var input = document.createElement('input'); input.type = 'file'; input.hidden = true;
      document.body.appendChild(input);
      input.addEventListener('change', function () {
        var file = input.files[0]; input.remove(); if (!file) return;
        row.setAttribute('aria-busy', 'true');
        replaceFile(file, id).then(function () { navigate(location.href, false); })
          .catch(function (error) { message(error.message); })
          .finally(function () { row.removeAttribute('aria-busy'); });
      }, { once: true });
      input.addEventListener('cancel', function () { input.remove(); }, { once: true });
      input.click(); return;
    }
    if (action === 'rename') {
      fields.name = window.prompt('重命名文件', name); if (fields.name === null) return; endpoint = 'rename';
    } else if (action === 'pwd') {
      fields.pwd = window.prompt('设置访问密码（1～32位字母或数字，留空清除）', '');
      if (fields.pwd === null) return; endpoint = 'setPwd';
    } else if (action === 'hide') {
      fields.hide = row.getAttribute('data-hide') === '1' ? '0' : '1'; endpoint = 'setHide';
    } else if (action === 'del') {
      if (!window.confirm('确定删除《' + name + '》？删除后无法恢复。')) return;
      fields = { 'ids[]': id }; endpoint = 'deleteFiles';
    } else return;
    button.disabled = true;
    freshToken().then(function (csrf) {
      fields.csrf_token = csrf;
      return jsonRequest('./user.php?act=' + endpoint, new URLSearchParams(fields));
    }).then(function (r) {
      message(r.msg);
      var url = new URL(location.href);
      if (action === 'del' && url.searchParams.has('file')) url.searchParams.delete('file');
      navigate(url.href, true);
    }).catch(function (error) { message(error.message); })
      .finally(function () { button.disabled = false; });
  });
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
