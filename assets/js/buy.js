/**
 * 购买页：下单 -> 展示二维码 -> 轮询查单 -> 支付成功刷新页面
 * 只在 buy.php 加载。二维码用 qrcode.js 在本地生成，不把支付串发到第三方生码接口。
 */
(function () {
  var mask = document.getElementById('buyMask');
  if (!mask) return;

  var qrBox = document.getElementById('buyQr');
  var jumpBtn = document.getElementById('buyJump');
  var dialogTitle = document.getElementById('buyDialogTitle');
  var channelBox = document.getElementById('buyChannels');
  var channelBtns = channelBox ? channelBox.querySelectorAll('.buy-channel') : [];
  var pendingPlan = null;
  var stateEl = document.getElementById('buyState');
  var amountEl = document.getElementById('buyAmount');
  var planEl = document.getElementById('buyPlanName');
  var closeBtn = document.getElementById('buyClose');
  var timer = null;
  var currentTradeNo = '';
  var busy = false;

  //微信/QQ 等内置浏览器对 fetch 的支持参差不齐，这里统一用 XHR，兼容性最好
  function post(act, data) {
    var body = [];
    for (var k in data) {
      if (Object.prototype.hasOwnProperty.call(data, k)) {
        body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
      }
    }
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', './buy.php?act=' + act, true);
      xhr.withCredentials = true;
      xhr.timeout = 40000;
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      //必须显式带 Accept：includes/txprotect.php 会把「手机 UA + Accept 恰好是 */*」
      //的请求当成机器人拦掉，而 fetch/XHR 默认发的就是 */*，手机上会直接被拒
      xhr.setRequestHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
      xhr.onload = function () {
        var text = xhr.responseText || '';
        try {
          resolve(JSON.parse(text));
        } catch (e) {
          //服务端没返回 JSON（PHP 报错、被防火墙拦了、登录跳转等），把真实内容带出来一点，
          //否则只报一句“请求失败”根本没法排查
          reject(new Error('服务器返回了异常内容（HTTP ' + xhr.status + '）：'
            + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').slice(0, 80)));
        }
      };
      xhr.onerror = function () { reject(new Error('网络连接失败，请检查网络后重试')); };
      xhr.ontimeout = function () { reject(new Error('请求超时，请稍后重试')); };
      xhr.send(body.join('&'));
    });
  }

  //手机上扫不了自己屏幕上的码，当面付要直接跳支付宝
  function isMobile() {
    return /Android|iPhone|iPad|iPod|Mobile|MicroMessenger|QQ\//i.test(navigator.userAgent);
  }

  //二维码库来自 CDN，内置浏览器里可能加载不出来；画不出来就退化成跳转按钮
  function renderQr(text) {
    if (typeof QRCode === 'undefined') return false;
    try {
      qrBox.innerHTML = '';
      new QRCode(qrBox, { text: text, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
      return true;
    } catch (e) {
      qrBox.innerHTML = '';
      return false;
    }
  }

  function stopPolling() {
    if (timer) { clearInterval(timer); timer = null; }
  }

  function closeDialog() {
    stopPolling();
    currentTradeNo = '';
    pendingPlan = null;
    mask.hidden = true;
  }

  function setState(text, cls) {
    stateEl.textContent = text;
    stateEl.className = 'buy-state' + (cls ? ' ' + cls : '');
  }

  function startPolling(tradeNo) {
    stopPolling();
    timer = setInterval(function () {
      post('query', { trade_no: tradeNo }).then(function (res) {
        //对话框已经关掉或者换了订单，旧的轮询结果直接丢弃
        if (currentTradeNo !== tradeNo) return;
        if (res.code !== 0) {
          setState(res.msg || '查询失败', 'is-error');
          return;
        }
        if (res.paid) {
          stopPolling();
          setState('支付成功，权限已发放，正在刷新…', 'is-ok');
          setTimeout(function () { window.location.reload(); }, 1200);
        }
      }).catch(function () {
        //网络抖动不算失败，下一轮继续
      });
    }, 3000);
  }

  //选中的支付方式，页面上只有一种方式时是个隐藏域
  function payType() {
    var checked = document.querySelector('input[name="pay_type"]:checked')
      || document.querySelector('input[name="pay_type"]');
    return checked ? checked.value : '';
  }

  //易支付有多个通道时，先弹窗让用户选微信/支付宝/QQ，选完再下单
  function askChannel(planId, btn) {
    pendingPlan = { id: planId, btn: btn };
    dialogTitle.textContent = '选择支付方式';
    planEl.textContent = '';
    amountEl.textContent = '';
    channelBox.hidden = false;
    qrBox.hidden = true;
    jumpBtn.hidden = true;
    setState('请选择你要使用的支付方式');
    mask.hidden = false;
  }

  function buy(planId, btn) {
    if (payType() === 'epay' && channelBtns.length > 1) {
      askChannel(planId, btn);
      return;
    }
    create(planId, btn, channelBtns.length ? channelBtns[0].getAttribute('data-channel') : '');
  }

  function create(planId, btn, channel) {
    if (busy) return;
    busy = true;
    var oldText = btn.textContent;
    btn.textContent = '正在下单…';
    btn.disabled = true;
    if (!mask.hidden) setState('正在下单…');

    post('create', { plan_id: planId, pay_type: payType(), channel: channel || '' }).then(function (res) {
      busy = false;
      btn.textContent = oldText;
      btn.disabled = false;
      if (res.code !== 0) {
        if (!mask.hidden) setState(res.msg || '下单失败', 'is-error');
        else alert(res.msg || '下单失败');
        return;
      }
      currentTradeNo = res.trade_no;
      planEl.textContent = res.plan_name;
      amountEl.textContent = '应付金额 ¥' + res.price;
      qrBox.innerHTML = '';

      if (channelBox) channelBox.hidden = true;
      if (res.pay_type === 'epay') {
        //易支付是跳转付款：新窗口打开收银台，本页继续轮询，付完自动开通
        dialogTitle.textContent = res.channel_name || '易支付';
        qrBox.hidden = true;
        jumpBtn.hidden = false;
        jumpBtn.href = res.pay_url;
        jumpBtn.textContent = '打开支付页面';
        var win = window.open(res.pay_url, '_blank');
        if (win) {
          setState('已打开支付页面，付款完成后本页会自动开通');
        } else {
          setState('浏览器拦截了新窗口，请点上面的按钮打开支付页面');
        }
      } else {
        dialogTitle.textContent = '支付宝当面付';
        var ok = renderQr(res.qr_code);
        qrBox.hidden = !ok;
        //手机上给一个直接打开支付宝的按钮；二维码没画出来时它就是唯一的付款入口
        jumpBtn.hidden = !(isMobile() || !ok);
        jumpBtn.href = res.qr_code;
        jumpBtn.textContent = '打开支付宝付款';
        if (!ok) {
          setState('点上面的按钮打开支付宝完成付款，付完本页会自动开通');
        } else if (isMobile()) {
          setState('用支付宝扫码，或点上面的按钮直接打开支付宝');
        } else {
          setState('请使用支付宝扫描二维码完成支付');
        }
      }
      mask.hidden = false;
      startPolling(res.trade_no);
    }).catch(function (err) {
      busy = false;
      btn.textContent = oldText;
      btn.disabled = false;
      var msg = (err && err.message) ? err.message : '下单请求失败，请稍后重试';
      //弹窗已经打开的话就写在弹窗里，微信内置浏览器的 alert 很丑而且会盖住页面
      if (!mask.hidden) setState(msg, 'is-error');
      else alert(msg);
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.buy-plan-btn[data-plan]'), function (btn) {
    btn.addEventListener('click', function () { buy(btn.getAttribute('data-plan'), btn); });
  });

  Array.prototype.forEach.call(channelBtns, function (cbtn) {
    cbtn.addEventListener('click', function () {
      if (!pendingPlan) return;
      create(pendingPlan.id, pendingPlan.btn, cbtn.getAttribute('data-channel'));
    });
  });

  Array.prototype.forEach.call(document.querySelectorAll('input[name="pay_type"]'), function (radio) {
    radio.addEventListener('change', function () {
      Array.prototype.forEach.call(document.querySelectorAll('.buy-method'), function (label) {
        label.className = label.className.replace(/\s*is-on/g, '');
      });
      var box = radio.parentNode;
      if (box && box.className.indexOf('buy-method') >= 0) box.className += ' is-on';
    });
  });

  closeBtn.addEventListener('click', closeDialog);
  mask.addEventListener('click', function (e) { if (e.target === mask) closeDialog(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !mask.hidden) closeDialog(); });
})();
