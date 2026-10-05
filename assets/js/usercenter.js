/*
 * 个人中心的交互：文件管理（重命名 / 访问密码 / 公开私密 / 删除 / 批量删除 / 复制外链）、
 * 账号设置（换头像 / 改昵称 / 改密码）和登录方式绑定（绑 QQ/微信、绑邮箱设密码、解绑）。
 *
 * 所有写操作都 POST 到 user.php?act=xxx，服务端会再校验一次归属和 CSRF，
 * 这里的按钮显隐只是界面便利，不承担权限判断。
 */
(function ($) {
  if (!$) return;

  function post(act, data, done) {
    data = data || {};
    data.csrf_token = uc_csrf;
    var ii = layer.load(2, { shade: [0.2, '#fff'] });
    $.ajax({
      type: 'POST',
      url: './user.php?act=' + act,
      data: data,
      dataType: 'json',
      traditional: false,
      success: function (res) {
        layer.close(ii);
        if (!res) { layer.msg('服务器返回异常'); return; }
        done(res);
      },
      error: function () {
        layer.close(ii);
        layer.msg('网络错误，请稍后再试');
      }
    });
  }

  /*
   * 自己搭一个输入框弹窗，不用 layer.prompt。
   * layer.prompt 在内部强制校验"输入不能为空"，值一空就不回调，
   * 于是"访问密码留空表示取消密码"这个操作根本点不动确定。
   * 顺带好处：值是用 .val() 塞进去的，文件名里的引号尖括号不会破坏 HTML。
   */
  function askText(opt, done) {
    var html = '<div class="uc-dialog">'
      + (opt.tip ? '<p class="uc-dialog-tip"></p>' : '')
      + '<input type="text" class="uc-input uc-dialog-input">'
      + '</div>';
    layer.open({
      type: 1,
      title: opt.title,
      area: '340px',
      shadeClose: false,
      btn: ['确定', '取消'],
      content: html,
      success: function (layero) {
        if (opt.tip) layero.find('.uc-dialog-tip').text(opt.tip);
        var $el = layero.find('.uc-dialog-input');
        $el.attr('placeholder', opt.placeholder || '').val(opt.value || '');
        setTimeout(function () { $el.focus(); }, 30);
      },
      yes: function (index, layero) {
        var val = layero.find('.uc-dialog-input').val();
        if (opt.required && !$.trim(val)) { layer.msg('不能为空'); return; }
        layer.close(index);
        done($.trim(val));
      }
    });
  }

  // 提示后刷新：改完名字、密码、公开状态都要让列表重新渲染一次，省得局部更新漏掉状态角标。
  // 文件页上由 filelist-live.js 局部刷新列表，不整页闪；别的页签（API 密钥等）没有它，照旧整页刷新
  function reloadAfter(res) {
    if (res.code === 0) {
      if (window.PanList) {
        layer.msg(res.msg, { icon: 1, time: 1200 });
        window.PanList.refresh();
      } else {
        layer.msg(res.msg, { icon: 1, time: 1200 }, function () { location.reload(); });
      }
    } else {
      layer.msg(res.msg || '操作失败', { icon: 2 });
    }
  }

  /* ---------------- 文件管理 ---------------- */

  var $list = $('.uc-filelist');

  function selectedIds() {
    var ids = [];
    $list.find('tbody .uc-check:checked').each(function () {
      ids.push($(this).closest('tr').data('id'));
    });
    return ids;
  }

  // 文件夹行（后台开了用户文件夹才有）的勾选框是 .fd-check，和文件行分开，免得文件夹 id 混进删文件的请求
  function selectedFolderIds() {
    var ids = [];
    $list.find('tbody .fd-check:checked').each(function () {
      ids.push($(this).closest('tr').data('folder-id'));
    });
    return ids;
  }

  function refreshBatchBar() {
    var n = selectedIds().length + selectedFolderIds().length;
    $('#ucSelCount').text(n);
    $('#ucBatchBar').prop('hidden', n === 0);
    // 本页可选的都选上了，全选框才算选中；有禁用项（已冻结）时不计入
    var $boxes = $list.find('tbody .uc-check:not(:disabled), tbody .fd-check');
    $('#ucCheckAll').prop('checked', $boxes.length > 0 && n === $boxes.length);
  }

  $('#ucCheckAll').on('change', function () {
    $list.find('tbody .uc-check:not(:disabled), tbody .fd-check').prop('checked', this.checked);
    refreshBatchBar();
  });
  $list.on('change', '.uc-check, .fd-check', refreshBatchBar);
  // 列表局部刷新后勾选都没了，批量条跟着收起
  $(document).on('pan:listrefresh', refreshBatchBar);
  $('#ucSelClear').on('click', function () {
    $list.find('tbody .uc-check, tbody .fd-check').prop('checked', false);
    $('#ucCheckAll').prop('checked', false);
    refreshBatchBar();
  });

  $('#ucBatchDelete').on('click', function () {
    var ids = selectedIds();
    var fids = selectedFolderIds();
    if (!ids.length && !fids.length) { layer.msg('请先选择文件'); return; }
    var tip = '确定删除选中的 ' + ids.length + ' 个文件？删除后外链立即失效，且无法恢复。';
    if (fids.length) {
      tip = '确定删除选中的 ' + (ids.length ? ids.length + ' 个文件和 ' : '') + fids.length + ' 个文件夹？<br>'
        + '文件夹里的文件<b>不会被删除</b>，会移到根目录'
        + (ids.length ? '；选中的文件删除后外链立即失效，且无法恢复。' : '。');
    }
    layer.confirm(tip, { icon: 3, title: '批量删除' }, function (idx) {
      layer.close(idx);
      if (!fids.length) { post('deleteFiles', { ids: ids }, reloadAfter); return; }
      if (!window.PanFolders) { layer.msg('页面脚本没有加载完整，请刷新后重试'); return; }
      // 先删文件夹（里面的文件回根目录），再删勾选的文件，两步的结果合在一起提示
      window.PanFolders.deleteFolders(fids, function (res) {
        if (res.code !== 0 || !ids.length) { reloadAfter(res); return; }
        post('deleteFiles', { ids: ids }, function (r2) {
          r2.msg = res.msg + '；' + (r2.msg || '');
          reloadAfter(r2);
        });
      });
    });
  });

  $list.on('click', '[data-uc]', function () {
    var $tr = $(this).closest('tr');
    var id = $tr.data('id');
    var act = $(this).data('uc');

    if (act === 'copy') {
      // data-down 是相对地址，转成完整外链再复制，粘出去才能直接用
      var url = new URL($tr.data('down'), location.href).href;
      copyText(url);
      return;
    }

    if (act === 'del') {
      layer.confirm('确定删除《' + $tr.find('.uc-name').text() + '》？删除后外链立即失效，且无法恢复。',
        { icon: 3, title: '删除文件' }, function (idx) {
          layer.close(idx);
          post('deleteFiles', { ids: [id] }, reloadAfter);
        });
      return;
    }

    if (act === 'replace') {
      //换的是内容，外链不变，所以旧内容直接就没了，先确认一下
      if (!window.replaceUpload) { layer.msg('替换组件未加载，请刷新页面重试'); return; }
      layer.confirm('用新文件替换《' + $tr.find('.uc-name').text() + '》的内容？外链地址保持不变，文件名会换成新文件的名字，原内容不可恢复。',
        { icon: 3, title: '重新上传替换' }, function (idx) {
          layer.close(idx);
          window.replaceUpload.pick(id);
        });
      return;
    }

    if (act === 'hide') {
      var toHide = $tr.data('hide') == 1 ? 0 : 1;
      post('setHide', { id: id, hide: toHide }, reloadAfter);
      return;
    }

    if (act === 'rename') {
      // 库里存的文件名是 HTML 转义过的，浏览器解析 data-name 时已经还原成原文，
      // 提交回去服务端再转义一次，正好round-trip，不会出现 &amp;quot; 这种叠加
      askText({
        title: '重命名',
        tip: '扩展名会保持不变，外链地址也不会变。',
        value: $tr.attr('data-name'),
        required: true
      }, function (val) {
        post('rename', { id: id, name: val }, reloadAfter);
      });
      return;
    }

    if (act === 'pwd') {
      var has = $tr.data('haspwd') == 1;
      askText({
        title: has ? '修改访问密码' : '设置访问密码',
        tip: has ? '留空并确定，即可取消该文件的访问密码。' : '1-32 位字母或数字。',
        placeholder: has ? '留空表示取消密码' : '请输入访问密码',
        value: '',
        required: false
      }, function (val) {
        if (val === '' && !has) { layer.msg('没有做任何修改'); return; }
        post('setPwd', { id: id, pwd: val }, reloadAfter);
      });
    }
  });

  function copyText(text) {
    // Clipboard API 只在 https 或 localhost 下可用，http 站点要退回 execCommand
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () {
        layer.msg('外链已复制', { icon: 1 });
      }, function () { fallbackCopy(text); });
    } else {
      fallbackCopy(text);
    }
  }

  function fallbackCopy(text) {
    var el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'fixed';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(el);
    if (ok) layer.msg('外链已复制', { icon: 1 });
    else layer.alert(text, { title: '复制失败，请手动复制外链' });
  }

  /* ---------------- API 密钥 ---------------- */

  function copyApiSecret(text, successMessage) {
    function done() { layer.msg(successMessage || '内容已复制', { icon: 1 }); }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallback(); });
      return;
    }
    fallback();
    function fallback() {
      var el = document.createElement('textarea');
      el.value = text;
      el.setAttribute('readonly', '');
      el.style.position = 'fixed';
      el.style.left = '-9999px';
      document.body.appendChild(el);
      el.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(el);
      if (ok) done();
      else layer.alert(text, { title: '请手动复制' });
    }
  }

  $('#ucApiCreate').on('click', function () {
    var name = $.trim($('#ucApiName').val());
    if (!name) { layer.msg('请填写密钥名称'); return; }
    post('createApiKey', {
      name: name,
      allow_ip: $.trim($('#ucApiAllowIp').val()),
      expire_days: $('#ucApiExpire').val()
    }, function (res) {
      if (res.code !== 0) { layer.msg(res.msg || '创建密钥失败', { icon: 2 }); return; }
      $('#ucApiSecret').text(res.key || '');
      $('#ucApiSecretBox').prop('hidden', false);
      $('#ucApiName,#ucApiAllowIp').val('');
      layer.msg(res.msg, { icon: 1 });
    });
  });

  $('#ucApiCopy').on('click', function () {
    var key = $('#ucApiSecret').text();
    if (key) copyApiSecret(key, 'API 密钥已复制');
  });

  $('[data-api-copy-text]').on('click', function () {
    copyApiSecret($(this).attr('data-api-copy-text') || '', '接口地址已复制');
  });

  $('.uc-api-docs').on('click', '[data-api-copy-code]', function () {
    copyApiSecret($(this).closest('.uc-api-code').find('pre').text(), '示例代码已复制');
  });

  $('.uc-api-key-list').on('click', '[data-api-key-toggle]', function () {
    var id = $(this).closest('tr').data('api-key-id');
    post('toggleApiKey', { id: id }, reloadAfter);
  }).on('click', '[data-api-key-delete]', function () {
    var id = $(this).closest('tr').data('api-key-id');
    layer.confirm('删除后使用该密钥的程序会立即无法上传，确定删除吗？', { icon: 3, title: '删除 API 密钥' }, function (idx) {
      layer.close(idx);
      post('deleteApiKey', { id: id }, reloadAfter);
    });
  });

  /* ---------------- 账号设置 ---------------- */

  $('#ucNickSave').on('click', function () {
    var name = $.trim($('#ucNickInput').val());
    if (!name) { layer.msg('昵称不能为空'); return; }
    post('profile', { nickname: name }, function (res) {
      if (res.code === 0) {
        layer.msg(res.msg, { icon: 1, time: 1200 }, function () { location.reload(); });
      } else {
        layer.msg(res.msg || '保存失败', { icon: 2 });
      }
    });
  });

  /* ---------------- 登录方式绑定 ---------------- */

  // 绑定 QQ/微信走的是和登录同一套 oauth 跳转，只是多带一个 bind=1，
  // 让 login.php 知道这趟回来是绑定而不是登录
  $('[data-uc-bind]').on('click', function () {
    var type = $(this).data('uc-bind');
    var ii = layer.load(2, { shade: [0.2, '#fff'] });
    $.post('./login.php?act=connect', { type: type, bind: '1' }, function (res) {
      layer.close(ii);
      if (res && res.code === 0 && res.url) location.href = res.url;
      else layer.msg((res && res.msg) || '获取跳转地址失败', { icon: 2 });
    }, 'json').fail(function () {
      layer.close(ii);
      layer.msg('网络错误，请稍后再试');
    });
  });

  $('[data-uc-unbind]').on('click', function () {
    var type = $(this).data('uc-unbind');
    var name = { qq: 'QQ', wx: '微信', mail: '邮箱' }[type] || type;
    var tip = type === 'mail'
      ? '解绑邮箱后，登录密码会一并清除，之后只能用快捷登录进来。确定解绑吗？'
      : '确定解绑' + name + '？解绑后就不能再用它登录了。';
    layer.confirm(tip, { icon: 3, title: '解绑' + name }, function (idx) {
      layer.close(idx);
      post('unbind', { type: type }, reloadAfter);
    });
  });

  $('#ucBindMailBtn').on('click', function () {
    $('#ucBindMailForm').prop('hidden', false);
    $(this).prop('hidden', true);
    $('#ucBindEmail').focus();
  });

  // 验证码按钮的倒计时：发信接口自己也有频率限制，这里只是别让用户狂点
  var bindTick = 0;
  $('#ucBindSendCode').on('click', function () {
    if (bindTick > 0) return;
    var email = $.trim($('#ucBindEmail').val());
    if (!email) { layer.msg('请先填写邮箱'); return; }
    var $btn = $(this);
    post('sendbindcode', { email: email }, function (res) {
      if (res.code !== 0) { layer.msg(res.msg || '发送失败', { icon: 2 }); return; }
      layer.msg('验证码已发送，请查收邮件', { icon: 1 });
      bindTick = 60;
      $btn.text(bindTick + ' 秒后重发');
      var timer = setInterval(function () {
        bindTick--;
        if (bindTick <= 0) {
          clearInterval(timer);
          $btn.text('获取验证码');
        } else {
          $btn.text(bindTick + ' 秒后重发');
        }
      }, 1000);
    });
  });

  $('#ucBindSubmit').on('click', function () {
    var data = {
      email: $.trim($('#ucBindEmail').val()),
      code: $.trim($('#ucBindCode').val()),
      password: $('#ucBindPwd').val()
    };
    if (!data.email) { layer.msg('请填写邮箱'); return; }
    if (!data.code) { layer.msg('请填写验证码'); return; }
    if (!data.password) { layer.msg('请设置登录密码'); return; }
    post('bindmail', data, reloadAfter);
  });

  $('#ucPwdSave').on('click', function () {
    var oldpwd = $('#ucOldPwd').val();
    var newpwd = $('#ucNewPwd').val();
    var newpwd2 = $('#ucNewPwd2').val();
    if (!oldpwd || !newpwd) { layer.msg('请填写原密码和新密码'); return; }
    if (newpwd !== newpwd2) { layer.msg('两次输入的新密码不一致'); return; }
    post('chpwd', { oldpwd: oldpwd, newpwd: newpwd }, function (res) {
      if (res.code === 0) {
        $('#ucOldPwd,#ucNewPwd,#ucNewPwd2').val('');
        layer.alert(res.msg, { icon: 1 });
      } else {
        layer.msg(res.msg || '修改失败', { icon: 2 });
      }
    });
  });

  /* ---------------- 头像 ---------------- */

  /*
   * 选好图片先在浏览器里裁：弹一个取景框，拖动、缩放选出正方形区域，
   * 用 canvas 导出 256×256 的 PNG 再上传。这样手机拍的几 MB 的照片也只传一百来 KB，
   * 服务器没装 GD（自己裁不了图）时也照样能用。
   * 浏览器不支持 canvas 导出的话直接传原图，由服务端居中裁。
   */
  var AVATAR_OUT = 256;
  var AVATAR_RAW_MAX = 5 * 1024 * 1024;   // 和服务端 user_avatar_save() 的上限一致
  var AVATAR_PICK_MAX = 30 * 1024 * 1024; // 要裁的原图再大浏览器也吃力

  //把页面上所有头像位换成新图；url 为空就去掉图片，露出下面垫着的图标 / 首字
  function setAvatar(url) {
    function make(cls, gone) {
      var img = document.createElement('img');
      img.className = cls;
      img.alt = '';
      img.onerror = function () {
        if (gone) gone(this);
        if (this.parentNode) this.parentNode.removeChild(this);
      };
      img.src = url;
      return img;
    }
    $('[data-user-face]').each(function () {
      $(this).children('img.user-face').remove();
      if (url) $(this).append(make('user-face'));
    });
    //导航栏那个头像位就是登录方式图标本身：有图时靠 has-face 把字形藏掉，所以这个类要跟着图片一起加减
    $('[data-user-nav]').each(function () {
      var $icon = $(this);
      $icon.removeClass('has-face').children('img.nav-face').remove();
      if (!url) return;
      $icon.append(make('nav-face', function (el) { $(el.parentNode).removeClass('has-face'); })).addClass('has-face');
    });
  }

  function uploadAvatar(blob, name) {
    var fd = new FormData();
    fd.append('csrf_token', uc_csrf);
    fd.append('file', blob, name);
    var ii = layer.load(2, { shade: [0.2, '#fff'] });
    $.ajax({
      type: 'POST',
      url: './user.php?act=avatar',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (res) {
        layer.close(ii);
        if (!res) { layer.msg('服务器返回异常'); return; }
        if (res.code === 0) {
          setAvatar(res.url);
          $('#ucAvatarReset').prop('hidden', false);
          layer.msg(res.msg, { icon: 1 });
        } else {
          layer.msg(res.msg || '上传失败', { icon: 2 });
        }
      },
      error: function () {
        layer.close(ii);
        layer.msg('上传失败，图片可能太大或者网络不通');
      }
    });
  }

  function uploadRawAvatar(file) {
    if (file.size > AVATAR_RAW_MAX) { layer.msg('图片不能超过 5MB', { icon: 2 }); return; }
    uploadAvatar(file, file.name || 'avatar');
  }

  function openCropper(file) {
    var URLApi = window.URL || window.webkitURL;
    var canCrop = !!(URLApi && URLApi.createObjectURL && window.HTMLCanvasElement && HTMLCanvasElement.prototype.toBlob);
    if (!canCrop) { uploadRawAvatar(file); return; }

    var src = URLApi.createObjectURL(file);
    var img = new Image();
    img.onerror = function () {
      URLApi.revokeObjectURL(src);
      layer.msg('这张图片打不开，换一张试试', { icon: 2 });
    };
    img.onload = function () {
      var iw = img.naturalWidth || img.width;
      var ih = img.naturalHeight || img.height;
      if (!iw || !ih) { img.onerror(); return; }

      //取景框边长：桌面 280，窄屏按窗口宽度收
      var view = Math.max(180, Math.min(280, $(window).width() - 72));
      var base = view / Math.min(iw, ih);   // 刚好铺满取景框的缩放
      var zoom = 1, scale = base;
      var x = (view - iw * scale) / 2, y = (view - ih * scale) / 2;
      var canvas, ctx, $range, drag = null;
      var dpr = Math.min(window.devicePixelRatio || 1, 2);

      //图片不能露出取景框的边
      function clamp() {
        x = Math.min(0, Math.max(view - iw * scale, x));
        y = Math.min(0, Math.max(view - ih * scale, y));
      }
      function draw() {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, view, view);
        ctx.drawImage(img, x, y, iw * scale, ih * scale);
      }
      //缩放时保持取景框正中间那一点不动
      function setZoom(z) {
        z = Math.min(4, Math.max(1, z));
        var cx = (view / 2 - x) / scale, cy = (view / 2 - y) / scale;
        zoom = z;
        scale = base * zoom;
        x = view / 2 - cx * scale;
        y = view / 2 - cy * scale;
        clamp();
        draw();
        if ($range) $range.val(Math.round(zoom * 100));
      }
      function point(e) {
        var o = e.originalEvent || e;
        var t = (o.touches && o.touches[0]) || (o.changedTouches && o.changedTouches[0]) || o;
        return { x: t.clientX, y: t.clientY };
      }

      //内容要在打开前拼好、尺寸写死：layer 的高度只在打开时量一次
      var html = '<div class="uc-crop">'
        + '<div class="uc-crop-stage" style="width:' + view + 'px;height:' + view + 'px">'
        + '<canvas style="width:' + view + 'px;height:' + view + 'px"></canvas><span class="uc-crop-ring"></span></div>'
        + '<div class="uc-crop-zoom"><i class="fa fa-search-minus" aria-hidden="true"></i>'
        + '<input type="range" min="100" max="400" step="1" value="100" aria-label="缩放">'
        + '<i class="fa fa-search-plus" aria-hidden="true"></i></div>'
        + '<p class="uc-crop-tip">拖动图片调整位置，拖滑块或滚轮缩放</p>'
        + '</div>';

      layer.open({
        type: 1,
        title: '裁剪头像',
        area: [(view + 48) + 'px', 'auto'],
        resize: false,
        shadeClose: false,
        content: html,
        btn: ['使用这张', '取消'],
        success: function (layero) {
          canvas = layero.find('canvas')[0];
          canvas.width = canvas.height = Math.round(view * dpr);
          ctx = canvas.getContext('2d');
          $range = layero.find('input[type=range]');
          draw();

          var $stage = layero.find('.uc-crop-stage');
          $stage.on('mousedown touchstart', function (e) {
            var p = point(e);
            drag = { px: p.x, py: p.y, x: x, y: y };
            e.preventDefault();
          });
          $(document).on('mousemove.uccrop touchmove.uccrop', function (e) {
            if (!drag) return;
            var p = point(e);
            x = drag.x + p.x - drag.px;
            y = drag.y + p.y - drag.py;
            clamp();
            draw();
          }).on('mouseup.uccrop touchend.uccrop touchcancel.uccrop', function () {
            drag = null;
          });
          $stage.on('wheel', function (e) {
            var o = e.originalEvent || e;
            e.preventDefault();
            setZoom(zoom * (o.deltaY < 0 ? 1.1 : 1 / 1.1));
          });
          $range.on('input change', function () {
            setZoom((parseInt(this.value, 10) || 100) / 100);
          });
        },
        yes: function (index) {
          var out = document.createElement('canvas');
          out.width = out.height = AVATAR_OUT;
          var octx = out.getContext('2d');
          //透明图垫白底，和服务端转 JPEG 时的做法一致
          octx.fillStyle = '#fff';
          octx.fillRect(0, 0, AVATAR_OUT, AVATAR_OUT);
          if ('imageSmoothingQuality' in octx) octx.imageSmoothingQuality = 'high';
          var side = view / scale;
          octx.drawImage(img, -x / scale, -y / scale, side, side, 0, 0, AVATAR_OUT, AVATAR_OUT);
          out.toBlob(function (blob) {
            layer.close(index);
            if (blob) uploadAvatar(blob, 'avatar.png');
            else uploadRawAvatar(file);
          }, 'image/png');
        },
        end: function () {
          $(document).off('.uccrop');
          URLApi.revokeObjectURL(src);
        }
      });
    };
    img.src = src;
  }

  var $avatarFile = $('#ucAvatarFile');
  function pickAvatar() {
    if (!$avatarFile.length) return;
    //先清空，不然连着选同一个文件不会触发 change
    $avatarFile.val('');
    $avatarFile[0].click();
  }
  $('#ucAvatar').on('click', pickAvatar).on('keydown', function (e) {
    if (e.which === 13 || e.which === 32) { e.preventDefault(); pickAvatar(); }
  });
  $('#ucAvatarPick').on('click', pickAvatar);

  $avatarFile.on('change', function () {
    var file = this.files && this.files[0];
    if (!file) return;
    //有的浏览器不给 type，退回去看扩展名；真正的类型校验在服务端
    var ok = file.type ? /^image\/(jpeg|png|gif|webp)$/.test(file.type) : /\.(jpe?g|png|gif|webp)$/i.test(file.name || '');
    if (!ok) { layer.msg('只支持 JPG、PNG、GIF、WebP 图片', { icon: 2 }); return; }
    if (file.size > AVATAR_PICK_MAX) { layer.msg('图片太大了，换一张 30MB 以内的', { icon: 2 }); return; }
    if (!window.FormData) { layer.msg('当前浏览器太旧，无法上传头像'); return; }
    openCropper(file);
  });

  $('#ucAvatarReset').on('click', function () {
    layer.confirm('确定去掉自己上传的头像，恢复成默认头像吗？', { icon: 3, title: '恢复默认头像' }, function (idx) {
      layer.close(idx);
      post('avatarReset', {}, function (res) {
        if (res.code === 0) {
          setAvatar(res.url);
          $('#ucAvatarReset').prop('hidden', true);
          layer.msg(res.msg, { icon: 1 });
        } else {
          layer.msg(res.msg || '操作失败', { icon: 2 });
        }
      });
    });
  });

})(window.jQuery);
