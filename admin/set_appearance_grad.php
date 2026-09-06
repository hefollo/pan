<?php if(!defined("IN_ADMIN"))exit("Access Denied"); ?>
<?php
/* 外观配色编辑器：28 套外观各存各的颜色，下拉框选哪套就编辑哪套。
   选的主色 / 副色不只管渐变，会把这套外观用到的所有颜色一起重算（见 includes/theme_recolor.php）。
   整块吸在页面顶部，外观卡片从它下面滚过去，翻到最底下也能直接点「保存外观」。
   所有外观的值一起放在下面那个 hidden 里提交，和外观选择共用同一个保存按钮。 */
$grad_specs = theme_gradient_specs();
$grad_saved = isset($conf['theme_gradient']) ? json_decode($conf['theme_gradient'], true) : null;
if(!is_array($grad_saved))$grad_saved = [];
//下拉框里的名字跟下面的卡片标题保持一致
$grad_names = [
	'dashboard'=>'控制台侧栏风', 'console'=>'数据控制台风', 'portal'=>'上传门户风',
	'workspace'=>'深色工作台风', 'mac'=>'macOS 窗口风', 'cockpit'=>'渐变仪表盘风',
	'studio'=>'蓝白工作台风', 'nebula'=>'深空科技风', 'royal'=>'紫韵会员风',
	'crisp'=>'清爽极简风', 'azure'=>'蓝天白云风', 'neo'=>'潮酷涂鸦风', 'skyline'=>'云端门户风',
	'cloud'=>'蓝白清爽', 'night'=>'黑夜风格', 'neon'=>'霓虹科技黑夜', 'aurora'=>'蓝紫渐变玻璃',
	'onefour'=>'暗黑科技后台风', 'celadon'=>'青瓷微澜', 'lilac'=>'淡紫点阵', 'paper'=>'米白纸张',
	'blush'=>'淡粉暖调', 'sky'=>'天蓝细纹', 'mint'=>'薄荷蜂巢', 'sunset'=>'落日熔金',
	'abyss'=>'深海玻璃', 'emerald'=>'翡翠流光', 'sakura'=>'樱雾玻璃',
];
?>
<div class="theme-grad-group" id="tg-group">
  <div class="theme-grad">
    <div class="theme-grad-bar">
      <strong class="theme-grad-title">外观配色</strong>
      <label for="tg-theme">当前外观</label>
      <?php //这个下拉框和下面的外观卡片是同一个选择，两边互相同步，避免出现「选的外观」和「调的外观」对不上 ?>
      <select id="tg-theme" class="form-control">
        <?php foreach($grad_names as $k=>$n){ if(!isset($grad_specs[$k]))continue;?>
        <option value="<?php echo $k?>"<?php echo $site_theme === $k ? ' selected' : ''?>><?php echo $n?><?php echo $site_theme === $k ? '（已生效）' : ''?></option>
        <?php }?>
      </select>
      <span class="theme-grad-state" id="tg-state"></span>
      <span class="theme-grad-btns">
        <button type="button" class="btn btn-default btn-sm" id="tg-reset">恢复默认配色</button>
        <button type="button" class="btn btn-default btn-sm" id="tg-toggle">收起</button>
        <?php //外壳沿用 appearance-submit：各套后台外观本来就给这个按钮写好了配色 ?>
        <span class="appearance-submit theme-grad-save"><input type="submit" name="submit" value="保存外观" class="btn btn-primary"/></span>
      </span>
    </div>
    <div class="theme-grad-body" id="tg-panel">
      <div class="theme-grad-tip">上面的下拉框和下面的外观卡片是<strong>同一个选择</strong>，在哪边选都一样，点「保存外观」时保存的就是它。改这里会把这套外观<strong>整套配色一起换掉</strong>——按钮、进度条、导航高亮、面板、边框、文字、阴影、整页背景都按新主色重算，前台和后台一起变；白灰黑和红绿这类状态色不跟着走，所以页面底、面板底、正文、边框另有「界面底色」四项可以单独调。每套外观各存各的，改哪套只影响哪套，换回去颜色还在。</div>
      <div class="theme-grad-cols">
        <div class="theme-grad-fields">
          <div class="theme-grad-row"><span class="theme-grad-name">主色</span>
            <input type="color" class="tg-color" data-k="a"><input type="text" class="tg-hex form-control" data-k="a" maxlength="7" spellcheck="false"></div>
          <div class="theme-grad-row" id="tg-row-mid"><span class="theme-grad-name">中间色</span>
            <input type="color" class="tg-color" data-k="mid"><input type="text" class="tg-hex form-control" data-k="mid" maxlength="7" spellcheck="false"></div>
          <div class="theme-grad-row"><span class="theme-grad-name">副色</span>
            <input type="color" class="tg-color" data-k="b"><input type="text" class="tg-hex form-control" data-k="b" maxlength="7" spellcheck="false"></div>
          <div class="theme-grad-row"><span class="theme-grad-name">渐变角度</span>
            <input type="range" class="tg-deg" data-k="deg" min="0" max="360" step="5"><input type="number" class="tg-degnum form-control" data-k="deg" min="0" max="360"><em>度</em></div>
          <?php //这四个是灰白系的界面底色，色相映射会绕开它们，所以单独给出来手动调 ?>
          <div class="theme-grad-sub">
            <div class="theme-grad-subhead">界面底色</div>
            <div class="theme-grad-subgrid">
              <div class="theme-grad-row"><span class="theme-grad-name">页面底色</span>
                <input type="color" class="tg-color" data-k="pg"><input type="text" class="tg-hex form-control" data-k="pg" maxlength="7" spellcheck="false"></div>
              <div class="theme-grad-row"><span class="theme-grad-name">面板底色</span>
                <input type="color" class="tg-color" data-k="sf"><input type="text" class="tg-hex form-control" data-k="sf" maxlength="7" spellcheck="false"></div>
              <div class="theme-grad-row"><span class="theme-grad-name">正文色</span>
                <input type="color" class="tg-color" data-k="tx"><input type="text" class="tg-hex form-control" data-k="tx" maxlength="7" spellcheck="false"></div>
              <div class="theme-grad-row"><span class="theme-grad-name">边框色</span>
                <input type="color" class="tg-color" data-k="ln"><input type="text" class="tg-hex form-control" data-k="ln" maxlength="7" spellcheck="false"></div>
            </div>
          </div>
          <div class="theme-grad-sub" id="tg-bg-wrap">
            <div class="theme-grad-subhead">整页背景渐变</div>
            <div class="theme-grad-subgrid">
              <div class="theme-grad-row"><span class="theme-grad-name">起点</span>
                <input type="color" class="tg-color" data-k="bg0"><input type="text" class="tg-hex form-control" data-k="bg0" maxlength="7" spellcheck="false"></div>
              <div class="theme-grad-row" id="tg-row-bg1"><span class="theme-grad-name">中间</span>
                <input type="color" class="tg-color" data-k="bg1"><input type="text" class="tg-hex form-control" data-k="bg1" maxlength="7" spellcheck="false"></div>
              <div class="theme-grad-row"><span class="theme-grad-name">终点</span>
                <input type="color" class="tg-color" data-k="bglast"><input type="text" class="tg-hex form-control" data-k="bglast" maxlength="7" spellcheck="false"></div>
              <div class="theme-grad-row"><span class="theme-grad-name">背景角度</span>
                <input type="range" class="tg-deg" data-k="bgdeg" min="0" max="360" step="5"><input type="number" class="tg-degnum form-control" data-k="bgdeg" min="0" max="360"><em>度</em></div>
            </div>
          </div>
        </div>
        <div class="theme-grad-preview" id="tg-preview">
          <div class="tg-preview-card">
            <div class="tg-preview-line"></div>
            <div class="tg-preview-line short"></div>
            <div class="tg-preview-bar"><i></i></div>
            <div class="tg-preview-foot"><span class="tg-preview-btn">上传文件</span><span class="tg-preview-chip">1</span></div>
          </div>
          <div class="tg-preview-note">预览只是示意，整站配色以实际页面为准</div>
        </div>
      </div>
    </div>
  </div>
  <input type="hidden" name="theme_gradient" id="tg-json" value="<?php echo htmlspecialchars(isset($conf['theme_gradient']) ? $conf['theme_gradient'] : '', ENT_QUOTES)?>">
</div>
<script type="text/javascript">
(function(){
	var SPECS = <?php echo json_encode($grad_specs)?>;
	var CUSTOM = <?php echo $grad_saved ? json_encode($grad_saved) : '{}'?>;
	var sel = document.getElementById('tg-theme');
	if(!sel)return;
	//NodeList 在旧浏览器上没有 forEach，统一走这个
	function each(list, fn){ Array.prototype.forEach.call(list, fn); }
	var box = document.querySelector('.theme-grad'), json = document.getElementById('tg-json');
	var state = document.getElementById('tg-state'), preview = document.getElementById('tg-preview');
	var panel = document.getElementById('tg-panel'), toggle = document.getElementById('tg-toggle');
	//当前编辑的那套外观最终生效的值：自定义盖在默认值上
	function merged(t){
		var s = SPECS[t] || {}, c = CUSTOM[t] || {}, v = {};
		v.a = c.a || s.a; v.b = c.b || s.b;
		v.deg = (c.deg === 0 || c.deg) ? c.deg : s.deg;
		['mid', 'pg', 'sf', 'tx', 'ln'].forEach(function(k){ if(s[k]) v[k] = c[k] || s[k]; });
		if(s.bg){
			v.bg = s.bg.slice();
			if(c.bg) for(var i = 0; i < v.bg.length; i++) if(c.bg[i]) v.bg[i] = c.bg[i];
			v.bgdeg = (c.bgdeg === 0 || c.bgdeg) ? c.bgdeg : s.bgdeg;
		}
		return v;
	}
	function isHex(v){ return /^#[0-9a-fA-F]{6}$/.test(v); }
	//只把和默认值不一样的写进 hidden，默认外观不落库，前台也就不会多输出覆盖样式
	function pack(){
		var out = {};
		for(var t in CUSTOM){
			var s = SPECS[t], c = CUSTOM[t], one = {};
			if(!s || !c) continue;
			['a', 'b', 'mid', 'pg', 'sf', 'tx', 'ln'].forEach(function(k){
				if(s[k] && c[k] && c[k].toLowerCase() !== s[k].toLowerCase()) one[k] = c[k].toLowerCase();
			});
			if((c.deg === 0 || c.deg) && +c.deg !== +s.deg) one.deg = +c.deg;
			if(s.bg && c.bg){
				var diff = false, bg = [];
				for(var i = 0; i < s.bg.length; i++){
					var v = c.bg[i] || s.bg[i];
					if(v.toLowerCase() !== s.bg[i].toLowerCase()) diff = true;
					bg.push(v.toLowerCase());
				}
				if(diff) one.bg = bg;
			}
			if(s.bg && (c.bgdeg === 0 || c.bgdeg) && +c.bgdeg !== +s.bgdeg) one.bgdeg = +c.bgdeg;
			for(var _ in one){ out[t] = one; break; }
		}
		var n = 0; for(var k in out) n++;
		json.value = n ? JSON.stringify(out) : '';
		state.textContent = out[sel.value] ? '已自定义' : '默认配色';
		state.className = 'theme-grad-state' + (out[sel.value] ? ' on' : '');
	}
	//取/存字段：bg0/bg1/bglast 三个键对应背景色标数组的首、中、末
	function getVal(v, k){
		if(k === 'bg0') return v.bg ? v.bg[0] : '';
		if(k === 'bg1') return (v.bg && v.bg.length > 2) ? v.bg[1] : '';
		if(k === 'bglast') return v.bg ? v.bg[v.bg.length - 1] : '';
		return v[k];
	}
	function setVal(t, k, val){
		var s = SPECS[t];
		if(!CUSTOM[t]) CUSTOM[t] = {};
		var c = CUSTOM[t];
		if(k.indexOf('bg') === 0 && k !== 'bgdeg'){
			if(!s.bg) return;
			if(!c.bg) c.bg = s.bg.slice();
			var i = k === 'bg0' ? 0 : (k === 'bg1' ? 1 : s.bg.length - 1);
			c.bg[i] = val;
		}else{
			c[k] = val;
		}
	}
	function render(){
		var t = sel.value, s = SPECS[t] || {}, v = merged(t);
		each(box.querySelectorAll('.tg-color,.tg-hex'), function(el){
			el.value = getVal(v, el.getAttribute('data-k')) || '';
		});
		each(box.querySelectorAll('.tg-deg,.tg-degnum'), function(el){
			var k = el.getAttribute('data-k');
			el.value = (k === 'bgdeg') ? (v.bgdeg || 0) : v.deg;
		});
		//三段渐变和整页背景不是每套外观都有，没有的行直接收起来
		document.getElementById('tg-row-mid').style.display = s.mid ? '' : 'none';
		document.getElementById('tg-bg-wrap').style.display = s.bg ? '' : 'none';
		document.getElementById('tg-row-bg1').style.display = (s.bg && s.bg.length > 2) ? '' : 'none';
		paint(v);
		pack();
	}
	function paint(v){
		var grad = 'linear-gradient(' + v.deg + 'deg,' + v.a + ',' + v.b + ')';
		//外框铺整页背景（有背景渐变的用渐变，其余用页面底色），里面那张卡用面板底色
		preview.style.background = v.bg
			? 'linear-gradient(' + v.bgdeg + 'deg,' + v.bg.join(',') + ')'
			: (v.pg || '#f7f9ff');
		var card = preview.querySelector('.tg-preview-card');
		if(v.sf) card.style.background = v.sf;
		if(v.ln) card.style.border = '1px solid ' + v.ln;
		each(preview.querySelectorAll('.tg-preview-line'), function(el){ if(v.tx){ el.style.background = v.tx; el.style.opacity = '.16'; } });
		preview.querySelector('.tg-preview-btn').style.background = grad;
		preview.querySelector('.tg-preview-chip').style.background = grad;
		preview.querySelector('.tg-preview-bar i').style.background = 'linear-gradient(90deg,' + v.a + ',' + v.b + ')';
	}
	function onColor(k, val, from){
		if(!isHex(val)) return;
		setVal(sel.value, k, val.toLowerCase());
		each(box.querySelectorAll('[data-k="' + k + '"]'), function(el){ if(el !== from) el.value = val.toLowerCase(); });
		paint(merged(sel.value));
		pack();
	}
	box.addEventListener('input', function(e){
		var el = e.target, k = el.getAttribute && el.getAttribute('data-k');
		if(!k) return;
		if(el.classList.contains('tg-color') || el.classList.contains('tg-hex')){
			onColor(k, el.value, el);
		}else if(el.classList.contains('tg-deg') || el.classList.contains('tg-degnum')){
			var d = Math.max(0, Math.min(360, parseInt(el.value, 10) || 0));
			setVal(sel.value, k, d);
			each(box.querySelectorAll('[data-k="' + k + '"]'), function(o){ if(o !== el) o.value = d; });
			paint(merged(sel.value));
			pack();
		}
	});
	sel.addEventListener('change', render);
	document.getElementById('tg-reset').addEventListener('click', function(){
		delete CUSTOM[sel.value];
		render();
	});
	//这块是吸顶的，展开时占地方，收起状态记在浏览器里，下次进来还是上次那样
	function setCollapsed(on){
		panel.style.display = on ? 'none' : '';
		toggle.textContent = on ? '展开' : '收起';
		try{ localStorage.setItem('tg_collapsed', on ? '1' : '0'); }catch(e){}
	}
	toggle.addEventListener('click', function(){ setCollapsed(panel.style.display !== 'none'); });
	try{ if(localStorage.getItem('tg_collapsed') === '1') setCollapsed(true); }catch(e){}
	//下拉框和下面的外观卡片是同一个选择，两边双向同步：
	//在哪边选都会同时选中另一边，保存的外观和正在调的渐变永远是同一套
	function checkCard(t){
		var r = document.querySelector('.appearance-card input[name="site_theme"][value="' + t + '"]');
		if(!r)return;
		r.checked = true;
		each(document.querySelectorAll('.appearance-card'), function(c){
			//勾选标记原来只有 PHP 渲染时打，点卡片当场不变，这里补上
			if(c.contains(r)) c.className = c.className.replace(/\s*\bactive\b/g, '') + ' active';
			else c.className = c.className.replace(/\s*\bactive\b/g, '');
		});
	}
	sel.addEventListener('change', function(){ checkCard(sel.value); });
	//这段脚本跑在外观卡片前面（工具条吸顶要排在最上面），那会儿卡片还没解析出来，
	//直接 querySelectorAll 绑不上任何东西，所以挂到 document 上做事件委托
	document.addEventListener('change', function(e){
		var r = e.target;
		if(!r || r.name !== 'site_theme' || r.type !== 'radio')return;
		if(!SPECS[r.value])return;
		sel.value = r.value;
		checkCard(r.value);
		render();
	});
	render();
})();
</script>
