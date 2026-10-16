/*
 * 用户文件夹（虚拟目录）的前端交互，个人中心「我的文件」和游客 ?m=mine 共用。
 *
 * 页面负责渲染工具条、文件夹行和批量操作条上的按钮，并在 window.PAN_FOLDER 里给出配置
 * （当前文件夹、csrf、归属者、文件行勾选框的选择器）。这里只管交互：
 * 新建 / 改名 / 删除文件夹，剪切 / 复制 / 粘贴，「移动或复制到」对话框，拖拽移动，Ctrl+X/C/V。
 *
 * 所有写操作都 POST 到 ajax.php?act=folder*，服务端会按当前访客再查一遍归属，
 * 这里的显隐和校验只是界面便利。剪贴板放在 sessionStorage，切换文件夹后还在，关掉标签页就没了。
 */
(function ($) {
  var cfg = window.PAN_FOLDER;
  if (!$ || !cfg || !window.layer) return;

  var CLIP_KEY = 'pan_folder_clip';
  var $list = $(cfg.list);
  var memClip = null; // sessionStorage 不可用（隐私模式等）时退回内存

  function post(act, data, done, retried) {
    var payload = $.extend({}, data || {}, { csrf_token: cfg.csrf });
    var ii = layer.load(2, { shade: [0.2, '#fff'] });
    $.ajax({
      type: 'POST',
      url: './ajax.php?act=' + act,
      data: payload,
      dataType: 'json',
      success: function (res) {
        layer.close(ii);
        // 同一会话里别的页面（比如点「上传到这里」开出来的上传页）会换掉 csrf_token，取一次最新值再试一遍
        if (res && res.msg === 'CSRF TOKEN ERROR' && !retried) {
          $.getJSON('./ajax.php?act=csrf_token').done(function (t) {
            if (t && t.csrf_token) { cfg.csrf = t.csrf_token; post(act, data, done, true); }
            else done(res);
          }).fail(function () { done(res); });
          return;
        }
        done(res || { code: -1, msg: '服务器返回异常' });
      },
      error: function () {
        layer.close(ii);
        layer.msg('网络错误，请稍后再试');
      }
    });
  }

  // 成功后局部刷新列表（filelist-live.js），不整页 reload；那个脚本没加载时才退回整页刷新
  function reloadAfter(res) {
    if (res.code === 0) {
      if (window.PanList) {
        layer.msg(res.msg, { icon: 1, time: 1300 });
        window.PanList.refresh();
      } else {
        layer.msg(res.msg, { icon: 1, time: 1300 }, function () { location.reload(); });
      }
    } else {
      layer.alert(res.msg || '操作失败', { icon: 2 });
    }
  }

  /*
   * 输入框弹窗：和 usercenter.js 一样自己搭，不用 layer.prompt。
   * layer.prompt 会把初始值直接拼进 HTML，文件夹名里的引号会把输入框弄坏；这里用 .val() 塞值。
   */
  function askName(title, value, done) {
    layer.open({
      type: 1,
      title: title,
      area: Math.min(340, $(window).width() - 24) + 'px',
      btn: ['确定', '取消'],
      content: '<div class="uc-dialog"><input type="text" class="uc-input uc-dialog-input"></div>',
      success: function (layero) {
        var $el = layero.find('.uc-dialog-input');
        $el.attr('maxlength', cfg.nameMax || 60).attr('placeholder', '文件夹名称').val(value || '');
        setTimeout(function () { $el.focus().select(); }, 30);
        $el.on('keydown', function (e) { if (e.keyCode === 13) layero.find('.layui-layer-btn0').click(); });
      },
      yes: function (index, layero) {
        var val = $.trim(layero.find('.uc-dialog-input').val());
        if (!val) { layer.msg('文件夹名不能为空'); return; }
        layer.close(index);
        done(val);
      }
    });
  }

  /* ---------------- 选择 ---------------- */

  function selected() {
    var files = [], folders = [];
    $list.find('tbody ' + cfg.fileCheck + ':checked').each(function () {
      var id = parseInt($(this).closest('tr').data('id'), 10);
      if (id > 0) files.push(id);
    });
    $list.find('tbody .fd-check:checked').each(function () {
      var id = parseInt($(this).closest('tr').data('folder-id'), 10);
      if (id > 0) folders.push(id);
    });
    return { files: files, folders: folders };
  }

  // 清空勾选后触发一次 change，让页面自己的批量操作条跟着刷新
  function clearSelection() {
    var $boxes = $list.find('tbody ' + cfg.fileCheck + ', tbody .fd-check');
    $boxes.prop('checked', false);
    $boxes.first().trigger('change');
  }

  /* ---------------- 剪贴板 ---------------- */

  function clipGet() {
    var c = memClip;
    try { c = JSON.parse(sessionStorage.getItem(CLIP_KEY) || 'null'); } catch (e) {}
    // 换了账号（或游客登录了）剪贴板就作废，服务端反正也不会认别人的东西
    if (!c || c.owner !== cfg.owner || !$.isArray(c.files) || !$.isArray(c.folders)) return null;
    if (!c.files.length && !c.folders.length) return null;
    return c;
  }

  function clipSet(c) {
    memClip = c;
    try {
      if (c) sessionStorage.setItem(CLIP_KEY, JSON.stringify(c));
      else sessionStorage.removeItem(CLIP_KEY);
    } catch (e) {}
    refreshClip();
  }

  function refreshClip() {
    var c = clipGet();
    $list.find('tbody tr').removeClass('fd-cut');
    if (!c || cfg.searching) { $('#fdPaste').prop('hidden', true); return; }
    $('#fdPaste').prop('hidden', false)
      .attr('title', '把剪贴板里的内容' + (c.mode === 'cut' ? '移动' : '复制') + '到当前文件夹');
    $('#fdPasteNum').text('（' + (c.files.length + c.folders.length) + ' 项）');
    // 剪切的行在原位置变淡，和系统资源管理器一样
    if (c.mode === 'cut') {
      $.each(c.files, function (i, id) { $list.find('tbody tr[data-id="' + id + '"]').addClass('fd-cut'); });
      $.each(c.folders, function (i, id) { $list.find('tbody tr[data-folder-id="' + id + '"]').addClass('fd-cut'); });
    }
  }

  function toClip(mode) {
    var s = selected();
    var n = s.files.length + s.folders.length;
    if (!n) { layer.msg('请先勾选要' + (mode === 'cut' ? '剪切' : '复制') + '的文件或文件夹'); return; }
    clipSet({ mode: mode, files: s.files, folders: s.folders, owner: cfg.owner, from: cfg.current });
    clearSelection();
    layer.msg('已' + (mode === 'cut' ? '剪切' : '复制') + ' ' + n + ' 项，打开目标文件夹后点「粘贴」', { icon: 1, time: 2200 });
  }

  function transfer(kind, files, folders, target, after) {
    post(kind === 'move' ? 'folderMove' : 'folderCopy',
      { file_ids: files, folder_ids: folders, target: target },
      function (res) {
        if (res.code === 0 && after) after();
        reloadAfter(res);
      });
  }

  function paste() {
    var c = clipGet();
    if (!c) return;
    if (c.mode === 'cut') {
      if (c.from === cfg.current && !c.folders.length) { layer.msg('这些文件已经在当前文件夹里了'); return; }
      // 剪切只能粘贴一次，成功后清掉剪贴板
      transfer('move', c.files, c.folders, cfg.current, function () { clipSet(null); });
    } else {
      // 复制可以反复粘贴到不同位置
      transfer('copy', c.files, c.folders, cfg.current);
    }
  }

  /* ---------------- 移动或复制到…（目录树选择） ---------------- */

  function openPicker(files, folders) {
    if (!files.length && !folders.length) { layer.msg('请先勾选要移动或复制的文件或文件夹'); return; }
    post('folderTree', {}, function (res) {
      if (res.code !== 0) { layer.alert(res.msg || '读取文件夹失败', { icon: 2 }); return; }
      var kids = {};
      $.each(res.folders || [], function (i, f) { (kids[f.parent_id] = kids[f.parent_id] || []).push(f); });
      // 要移动的文件夹自己和它下面的子文件夹不能作为目标
      var banned = {};
      function ban(id) { banned[id] = true; $.each(kids[id] || [], function (i, f) { ban(f.id); }); }
      $.each(folders, function (i, id) { ban(id); });

      // 目录树要在打开对话框之前拼好：layer 按「自动高度」打开时只量一次内容，
      // 打开后再往里塞节点，对话框的内容区高度还是 0，树就被整个藏掉了
      function esc(s) { return $('<i>').text(s).html(); }
      var html = '';
      function add(id, name, depth) {
        html += '<div class="fd-node' + (banned[id] ? ' is-disabled' : '') + '" data-id="' + id + '"'
          + ' style="padding-left:' + (10 + depth * 18) + 'px"'
          + (banned[id] ? ' title="不能放进它自己或它的子文件夹"' : '') + '>'
          + '<i class="fa fa-fw ' + (id === 0 ? 'fa-hdd-o' : 'fa-folder') + '" aria-hidden="true"></i>'
          + '<span>' + esc(name) + '</span>'
          + (id === cfg.current ? '<em>当前位置</em>' : '')
          + '</div>';
        $.each(kids[id] || [], function (i, f) { add(f.id, f.name, depth + 1); });
      }
      add(0, '我的文件', 0);

      var target = null;
      var n = files.length + folders.length;
      layer.open({
        type: 1,
        title: '移动或复制 ' + n + ' 项到…',
        area: [Math.min(420, $(window).width() - 24) + 'px', 'auto'],
        content: '<div class="fd-picker"><div class="fd-tree">' + html + '</div></div>',
        btn: ['移动到这里', '复制到这里', '取消'],
        success: function (layero) {
          var $tree = layero.find('.fd-tree');
          $tree.on('click', '.fd-node:not(.is-disabled)', function () {
            $tree.find('.fd-node').removeClass('is-on');
            $(this).addClass('is-on');
            target = parseInt($(this).attr('data-id'), 10);
          });
        },
        yes: function (index) {
          if (target === null) { layer.msg('请先点选目标文件夹'); return; }
          if (target === cfg.current && !folders.length) { layer.msg('已经在这个文件夹里了'); return; }
          layer.close(index);
          transfer('move', files, folders, target);
        },
        btn2: function (index) {
          if (target === null) { layer.msg('请先点选目标文件夹'); return false; }
          layer.close(index);
          transfer('copy', files, folders, target);
          return false;
        }
      });
    });
  }


  function refreshShares() {
    if (window.PanList) window.PanList.refresh();
  }
  function copyShareValue(input) {
    function fallback() {
      input.focus(); input.select();
      layer.msg(document.execCommand('copy') ? '链接已复制' : '请手动复制已选中的链接');
    }
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(input.value).then(function () { layer.msg('链接已复制'); }, fallback);
    else fallback();
  }
  function shareDialog(id) {
    post('folderShareGet', { id: id }, function (res) {
      if (res.code !== 0) { layer.alert(res.msg); return; }
      var share = res.share;
      layer.open({
        type: 1, title: share && share.enabled ? '查看文件夹分享' : '创建文件夹分享', area: Math.min(560, $(window).width() - 24) + 'px',
        content: '<div class="uc-dialog fd-share-dialog" style="padding:22px">' +
          '<h3 class="fd-share-name" style="margin-top:0;word-break:break-all"></h3>' +
          '<p>分享包含此文件夹及所有子文件夹中的正常文件，<b>包括设为私密的文件</b>。文件原有密码仍需单独输入。</p>' +
          '<p class="fd-share-status" style="font-weight:600"></p><label>分享链接</label><div style="display:flex;gap:8px;margin:8px 0 18px"><input class="uc-input fd-share-url" readonly aria-label="分享链接" style="flex:1;min-width:0"><a class="uc-btn fd-share-open" target="_blank" rel="noopener">打开</a><button class="uc-btn fd-share-copy" type="button">复制</button></div>' +
          '<label>访问密码 <select class="uc-input fd-share-mode"><option value="keep">保持现状</option><option value="set">设置新密码</option><option value="clear">取消密码</option></select></label>' +
          '<input class="uc-input fd-share-password" type="password" autocomplete="new-password" placeholder="输入新密码（4～72字节）" style="display:none;width:100%;margin-top:8px">' +
          '<p><label>有效期（北京时间，留空为永久）<input type="datetime-local" class="uc-input fd-share-expire" style="width:100%"></label></p>' +
          '<p class="fd-share-permission"></p><div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="uc-btn uc-btn-primary fd-share-save">保存分享</button><button type="button" class="uc-btn fd-share-reset">重置链接</button><button type="button" class="uc-btn fd-share-revoke">取消分享</button></div>' +
          '<p style="font-size:12px;margin-bottom:0">新增或移入的文件会自动加入分享；移出、删除的文件不再显示。取消此分享不会撤销原有独立文件链接。</p></div>',
        success: function (layero, index) {
          var box = layero.find('.fd-share-dialog');
          box.find('.fd-share-name').text(res.name);
          function paint() {
            var active = share && share.enabled;
            layero.find('.layui-layer-title').text(active ? '查看文件夹分享' : '创建文件夹分享');
            box.find('.fd-share-url').val(active ? share.url : '保存后生成分享链接');
            box.find('.fd-share-open').attr('href', active ? share.url : '#').toggle(active);
            box.find('.fd-share-copy').prop('disabled', !active);
            box.find('.fd-share-status').text(!share ? '尚未创建分享' : !active ? '分享已取消' : share.expired ? '分享已过期，请修改有效期后保存' : share.has_password ? '分享已开启 · 需要密码' : '分享已开启 · 无需密码');
            box.find('.fd-share-expire').val(share && share.expiretime ? share.expiretime.slice(0, 16).replace(' ', 'T') : '');
            box.find('.fd-share-mode').val('keep');
            box.find('.fd-share-password').val('').hide();
            box.find('.fd-share-save,.fd-share-mode,.fd-share-expire,.fd-share-password').prop('disabled', !res.can_edit);
            box.find('.fd-share-save').text(active ? '保存设置' : '开启分享');
            box.find('.fd-share-reset').prop('disabled', !res.can_edit || !active);
            box.find('.fd-share-revoke').prop('disabled', !active);
            box.find('.fd-share-permission').text(res.can_edit ? '' : '当前没有文件夹权限，仍可取消已有分享。');
          }
          paint();
          layer.style(index, { height: 'auto' });
          box.on('change', '.fd-share-mode', function () { box.find('.fd-share-password').toggle(this.value === 'set'); layer.style(index, { height: 'auto' }); });
          box.on('click', '.fd-share-copy', function () {
            var input = box.find('.fd-share-url')[0];
            copyShareValue(input);
          });
          function result(r) {
            if (r.code !== 0) { layer.alert(r.msg || '操作失败'); return; }
            share = r.share; paint(); refreshShares(); layer.msg(r.msg);
          }
          box.on('click', '.fd-share-save', function () {
            post('folderShareSave', { id: id, password_mode: box.find('.fd-share-mode').val(), password: box.find('.fd-share-password').val(), expiretime: box.find('.fd-share-expire').val() }, result);
          });
          box.on('click', '.fd-share-reset', function () {
            layer.confirm('重置后旧链接立即失效，确定继续？', function (idx) { layer.close(idx); post('folderShareReset', { id: id }, result); });
          });
          box.on('click', '.fd-share-revoke', function () {
            layer.confirm('取消此文件夹分享？原有独立文件链接不受影响。', function (idx) {
              layer.close(idx); post('folderShareRevoke', { id: id }, function (r) {
                if (r.code !== 0) { layer.alert(r.msg); return; }
                layer.close(index); refreshShares(); layer.msg(r.msg);
              });
            });
          });
        }
      });
    });
  }
  $(document).on('click', '#fdShare', function () { shareDialog($(this).attr('data-folder-id')); });
  $(document).on('click', '#fdShareManage', function () {
    post('folderShareList', {}, function (res) {
      if (res.code !== 0) { layer.alert(res.msg); return; }
      // 先填内容再打开，layer 的 auto 高度只在打开时测量。
      var box = $('<div class="uc-dialog fd-share-list" style="padding:20px;max-height:65vh;overflow:auto"></div>');
      var index;
      if (!res.shares.length) box.text('还没有文件夹分享。打开文件夹，点击“分享文件夹”即可创建。');
      res.shares.forEach(function (share) {
        var row = $('<div>').css({ padding: '12px 0', borderBottom: '1px solid #eee' });
        $('<strong>').text(share.name).appendTo(row);
        var state = share.missing ? '文件夹已删除，分享已失效' : !share.enabled ? '已取消' : share.expired ? '已过期' : '分享中';
        if (share.enabled && !share.missing) state += ' · ' + (share.has_password ? '需要密码' : '无需密码') + ' · ' + (share.expiretime ? '有效期至 ' + share.expiretime : '永久有效');
        $('<p>').text(state).css('margin', '6px 0').appendTo(row);
        if (share.enabled) {
          var link = $('<div>').css({ display: 'flex', gap: '8px', marginBottom: '10px' }).appendTo(row);
          $('<input class="uc-input" readonly aria-label="分享链接">').val(share.url).css({ flex: 1, minWidth: 0 }).appendTo(link);
          $('<a class="uc-btn" target="_blank" rel="noopener">打开</a>').attr('href', share.url).appendTo(link);
          $('<button type="button" class="uc-btn">复制</button>').appendTo(link).on('click', function () { copyShareValue($(this).siblings('input')[0]); });
        }
        if (!share.missing) $('<button type="button" class="uc-btn">管理</button>').appendTo(row).on('click', function () { layer.close(index); shareDialog(share.folder_id); });
        if (share.enabled) $('<button type="button" class="uc-btn">取消分享</button>').css('marginLeft', '8px').appendTo(row).on('click', function () {
          layer.confirm('取消此分享？原有独立文件链接不受影响。', function (idx) {
            layer.close(idx); post('folderShareRevoke', { id: share.folder_id }, function (r) {
              if (r.code !== 0) { layer.alert(r.msg); return; }
              layer.close(index); refreshShares(); layer.msg(r.msg);
            });
          });
        });
        box.append(row);
      });
      box.hide().appendTo(document.body);
      index = layer.open({ type: 1, title: '文件夹分享管理', area: Math.min(580, $(window).width() - 24) + 'px', content: box, end: function () { box.remove(); } });
    });
  });

  // 失去权限时仅保留上面的分享管理，不接管拖拽或复制粘贴快捷键。
  if (cfg.enabled === false) return;

  /* ---------------- 工具条与文件夹行 ---------------- */

  // 工具条（.fd-bar）在局部刷新时会整块换掉，按钮事件挂在 document 上委托，换完照样能点
  $(document).on('click', '#fdNew', function () {
    askName('新建文件夹', '', function (name) {
      post('folderCreate', { parent_id: cfg.current, name: name }, reloadAfter);
    });
  });
  $(document).on('click', '#fdPaste', paste);
  $(document).on('click', '#fdCut', function () { toClip('cut'); });
  $(document).on('click', '#fdCopy', function () { toClip('copy'); });
  $(document).on('click', '#fdMoveTo', function () { var s = selected(); openPicker(s.files, s.folders); });

  $list.on('click', '[data-fd]', function () {
    var $tr = $(this).closest('tr');
    var id = parseInt($tr.data('folder-id'), 10);
    var name = String($tr.attr('data-name') || '');
    if ($(this).data('fd') === 'share') {
      shareDialog(id);
    } else if ($(this).data('fd') === 'rename') {
      askName('重命名文件夹', name, function (val) {
        post('folderRename', { id: id, name: val }, reloadAfter);
      });
    } else if ($(this).data('fd') === 'delete') {
      layer.confirm('删除文件夹「' + $('<i>').text(name).html() + '」？<br>它的子文件夹会一起删除，里面的文件<b>不会被删除</b>，会全部移到根目录。',
        { icon: 3, title: '删除文件夹' }, function (idx) {
          layer.close(idx);
          post('folderDelete', { ids: [id] }, reloadAfter);
        });
    }
  });

  /* ---------------- 拖拽移动 ---------------- */

  // 把文件行或文件夹行拖到文件夹行、面包屑上即移动；拖的是已勾选的行时，整批一起移动
  var dragging = null;
  function markDraggable() {
    $list.find('tbody tr[data-id], tbody tr.fd-row').attr('draggable', 'true');
  }
  markDraggable();

  $list.on('dragstart', 'tbody tr[draggable]', function (e) {
    var $tr = $(this);
    var s;
    if ($tr.find(cfg.fileCheck + ', .fd-check').is(':checked')) {
      s = selected();
    } else if ($tr.hasClass('fd-row')) {
      s = { files: [], folders: [parseInt($tr.data('folder-id'), 10)] };
    } else {
      s = { files: [parseInt($tr.data('id'), 10)], folders: [] };
    }
    if (!s.files.length && !s.folders.length) return false;
    dragging = s;
    var dt = e.originalEvent && e.originalEvent.dataTransfer;
    if (dt) {
      dt.effectAllowed = 'move';
      try { dt.setData('text/plain', 'pan-folder'); } catch (err) {}
    }
    $tr.addClass('fd-dragging');
  });
  $list.on('dragend', 'tbody tr[draggable]', function () {
    dragging = null;
    $('.fd-dragging').removeClass('fd-dragging');
    $('.fd-drop-over').removeClass('fd-drop-over');
  });

  function dropTarget(el) {
    var $el = $(el);
    var id = $el.hasClass('fd-row') ? $el.data('folder-id') : $el.attr('data-fd-drop');
    id = parseInt(id, 10);
    if (isNaN(id) || !dragging) return null;
    // 不能拖进自己
    if ($.inArray(id, dragging.folders) !== -1) return null;
    return id;
  }
  $(document).on('dragover', '.fd-row, [data-fd-drop]', function (e) {
    if (dropTarget(this) === null) return;
    e.preventDefault();
    $(this).addClass('fd-drop-over');
  }).on('dragleave', '.fd-row, [data-fd-drop]', function () {
    $(this).removeClass('fd-drop-over');
  }).on('drop', '.fd-row, [data-fd-drop]', function (e) {
    var id = dropTarget(this);
    $(this).removeClass('fd-drop-over');
    if (id === null) return;
    e.preventDefault();
    var s = dragging;
    dragging = null;
    transfer('move', s.files, s.folders, id);
  });

  /* ---------------- 快捷键 ---------------- */

  // Ctrl/⌘ + X / C / V：焦点在输入框里、页面上有选中的文字、或者开着弹窗时不接管，免得抢了正常的复制粘贴
  $(document).on('keydown', function (e) {
    if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey) return;
    var t = e.target;
    if (t && (t.isContentEditable || (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) && t.type !== 'checkbox'))) return;
    if (window.getSelection && String(window.getSelection()) !== '') return;
    if ($('.layui-layer:visible').length) return;
    var k = String(e.key || '').toLowerCase();
    if (k === 'x' || k === 'c') {
      var s = selected();
      if (!s.files.length && !s.folders.length) return;
      e.preventDefault();
      toClip(k === 'x' ? 'cut' : 'copy');
    } else if (k === 'v') {
      if (cfg.searching || !clipGet()) return;
      e.preventDefault();
      paste();
    }
  });

  refreshClip();

  // 列表局部刷新后：新换上的行要重新标可拖拽，剪切的淡化和粘贴按钮（工具条也换了）要重新套一遍
  $(document).on('pan:listrefresh', function () {
    dragging = null;
    markDraggable();
    refreshClip();
  });

  // 给页面自己的批量删除用：勾选里有文件夹时先删文件夹（里面的文件回根目录），再删文件
  window.PanFolders = {
    selected: selected,
    deleteFolders: function (ids, done) { post('folderDelete', { ids: ids }, done); }
  };
})(window.jQuery);
