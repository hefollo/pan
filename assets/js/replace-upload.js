/*
 * 覆盖上传：用新文件替换某条已有记录的内容，对外链接（token）保持不变。
 *
 * 走的是和普通上传同一套接口（pre_upload / upload_part / complete_upload），只是多带一个
 * replace_id。服务端会再校验一次归属、是否被冻结，并重新过审、写覆盖记录，这里的按钮显隐
 * 只是界面便利，不承担权限判断。
 *
 * 首页“我的文件”和文件详情页各自内联了一份同样的流程，这一份是抽出来共用的，个人中心引它。
 * 依赖 jQuery、layer、SparkMD5，引入本文件前要先确认这三个已经加载。
 *
 * 用法：replaceUpload.pick(fileId) —— 弹出选文件框，选完直接替换，成功后刷新页面。
 */
(function (window, $) {
  if (!$) return;

  var CHUNK_SIZE = 2097152;
  var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice;
  var token = '';
  var $input = null;
  var targetId = 0;

  //页面自己带的那份 token 兜底：个人中心是 uc_csrf，首页/详情页是 replace_csrf_token
  function currentToken() {
    return token || window.uc_csrf || window.replace_csrf_token || '';
  }

  function fail(ii, msg) {
    layer.close(ii);
    layer.alert(msg || '替换失败', { icon: 2 });
  }

  function succeed(ii, msg) {
    layer.close(ii);
    layer.alert(msg || '替换成功，链接保持不变', { icon: 1 }, function () {
      window.location.reload();
    });
  }

  //整文件算 MD5：秒传和分片续传都靠它，服务端也用这个值定位物理文件
  function fileHash(file) {
    return new Promise(function (resolve) {
      var reader = new FileReader();
      var chunks = Math.ceil(file.size / CHUNK_SIZE);
      var current = 0;
      var spark = new SparkMD5();
      if (chunks === 0) {
        resolve(SparkMD5.hashBinary(''));
        return;
      }
      reader.onload = function (e) {
        spark.appendBinary(e.target.result);
        current++;
        if (current < chunks) { loadNext(); } else { resolve(spark.end()); }
      };
      function loadNext() {
        var start = current * CHUNK_SIZE;
        var end = start + CHUNK_SIZE >= file.size ? file.size : start + CHUNK_SIZE;
        reader.readAsBinaryString(blobSlice.call(file, start, end));
      }
      loadNext();
    });
  }

  function start(file, id, ii) {
    fileHash(file).then(function (hash) {
      $.ajax({
        type: 'POST',
        url: 'ajax.php?act=pre_upload',
        dataType: 'json',
        data: {
          csrf_token: currentToken(),
          name: file.name,
          hash: hash,
          size: file.size,
          //隐藏和访问密码沿用原记录，这里传什么服务端在覆盖分支里都不会用
          show: '1',
          ispwd: '0',
          pwd: '',
          replace_id: id
        },
        success: function (res) {
          if (!res) { fail(ii, '服务器返回异常'); return; }
          if (res.csrf_token) token = res.csrf_token;
          //code=1 表示站内已有同样内容，记录直接换掉，不用再传一遍
          if (res.code == 1) { succeed(ii, res.msg); return; }
          if (res.code == 0) { uploadBody(res, file, ii); return; }
          fail(ii, res.msg);
        },
        error: function () {
          layer.close(ii);
          layer.msg('服务器错误');
        }
      });
    });
  }

  function uploadBody(pre, file, ii) {
    //直传：服务端签好参数，浏览器把文件直接 POST 进对象存储
    if (pre.third) {
      var form = new FormData();
      for (var key in pre.post) {
        if (Object.prototype.hasOwnProperty.call(pre.post, key)) form.append(key, pre.post[key]);
      }
      form.append('file', file);
      $.ajax({
        type: 'POST', url: pre.url, data: form, processData: false, contentType: false, dataType: 'html',
        success: function () { completeUpload(pre.hash, ii); },
        error: function () { layer.close(ii); layer.msg('上传失败，请稍后再试'); }
      });
      return;
    }

    var chunks = pre.chunks;
    var chunkSize = pre.chunksize;
    function uploadChunk(chunk) {
      var start = (chunk - 1) * chunkSize;
      var end = start + chunkSize > file.size ? file.size : start + chunkSize;
      var data = new FormData();
      data.append('file', blobSlice.call(file, start, end));
      data.append('hash', pre.hash);
      data.append('chunk', chunk);
      data.append('csrf_token', currentToken());
      $.ajax({
        type: 'POST', url: 'ajax.php?act=upload_part', data: data, processData: false, contentType: false, dataType: 'json',
        success: function (res) {
          if (!res) { fail(ii, '服务器返回异常'); return; }
          if (res.csrf_token) token = res.csrf_token;
          if (res.code == -1) { fail(ii, res.msg); return; }
          if (chunk < chunks) { uploadChunk(chunk + 1); return; }
          //最后一片传完，服务端可能已经直接把替换做完了
          if (res.code == 1) { succeed(ii, res.msg); }
        },
        error: function () { layer.close(ii); layer.msg('上传失败，请稍后再试'); }
      });
    }
    uploadChunk(1);
  }

  function completeUpload(hash, ii) {
    $.ajax({
      type: 'POST', url: 'ajax.php?act=complete_upload', dataType: 'json',
      data: { hash: hash, csrf_token: currentToken() },
      success: function (res) {
        if (res && res.code == 1) { succeed(ii, res.msg); } else { fail(ii, res ? res.msg : ''); }
      },
      error: function () { layer.close(ii); layer.msg('服务器错误'); }
    });
  }

  function ensureInput() {
    if ($input) return $input;
    $input = $('<input type="file" style="display:none">').appendTo(document.body);
    $input.on('change', function () {
      var file = this.files && this.files[0];
      var id = targetId;
      targetId = 0;
      if (!file || !id) return;
      var ii = layer.load(2, { shade: [0.2, '#fff'] });
      //页面可能已经开了很久，会话里的 token 也许被同一浏览器的其它页面刷新过，先取一次最新的
      $.getJSON('ajax.php?act=csrf_token', function (res) {
        if (res && res.csrf_token) token = res.csrf_token;
      }).always(function () {
        start(file, id, ii);
      });
    });
    return $input;
  }

  window.replaceUpload = {
    pick: function (fileId) {
      fileId = parseInt(fileId, 10);
      if (!fileId) return;
      targetId = fileId;
      var $el = ensureInput();
      $el.val('');
      $el.trigger('click');
    }
  };
})(window, window.jQuery);
