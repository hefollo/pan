/*
 * 个人中心的交互：文件管理（重命名 / 访问密码 / 公开私密 / 删除 / 批量删除 / 复制外链）、
 * 账号设置（改昵称 / 改密码）和登录方式绑定（绑 QQ/微信、绑邮箱设密码、解绑）。
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

  // 提示后刷新：改完名字、密码、公开状态都要让列表重新渲染一次，省得局部更新漏掉状态角标
  function reloadAfter(res) {
    if (res.code === 0) {
      layer.msg(res.msg, { icon: 1, time: 1200 }, function () { location.reload(); });
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

  function refreshBatchBar() {
    var n = selectedIds().length;
    $('#ucSelCount').text(n);
    $('#ucBatchBar').prop('hidden', n === 0);
    // 本页可选的都选上了，全选框才算选中；有禁用项（已冻结）时不计入
    var $boxes = $list.find('tbody .uc-check:not(:disabled)');
    $('#ucCheckAll').prop('checked', $boxes.length > 0 && n === $boxes.length);
  }

  $('#ucCheckAll').on('change', function () {
    $list.find('tbody .uc-check:not(:disabled)').prop('checked', this.checked);
    refreshBatchBar();
  });
  $list.on('change', '.uc-check', refreshBatchBar);
  $('#ucSelClear').on('click', function () {
    $list.find('tbody .uc-check').prop('checked', false);
    $('#ucCheckAll').prop('checked', false);
    refreshBatchBar();
  });

  $('#ucBatchDelete').on('click', function () {
    var ids = selectedIds();
    if (!ids.length) { layer.msg('请先选择文件'); return; }
    layer.confirm('确定删除选中的 ' + ids.length + ' 个文件？删除后外链立即失效，且无法恢复。',
      { icon: 3, title: '批量删除' }, function (idx) {
        layer.close(idx);
        post('deleteFiles', { ids: ids }, reloadAfter);
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
})(window.jQuery);
