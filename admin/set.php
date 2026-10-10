<?php
/**
 * 系统设置
**/
define('IN_ADMIN', true);
include("../includes/common.php");
$title='系统设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
$mod=isset($_GET['mod'])?$_GET['mod']:null;
//外观设置是一排排的外观卡片，28 套排下来要横向空间，用宽版；其余都是设置表单，收窄看着才不散
$set_shell = ($mod === 'appearance') ? 'admin-page-wide' : 'admin-page';
?>
  <div class="container">
    <div class="<?php echo $set_shell?>">
<?php
if($mod=='site'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">网站信息设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站标题</label>
	  <div class="col-sm-10"><input type="text" name="title" value="<?php echo htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">关键字</label>
	  <div class="col-sm-10"><input type="text" name="keywords" value="<?php echo $conf['keywords']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站描述</label>
	  <div class="col-sm-10"><input type="text" name="description" value="<?php echo $conf['description']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站图标</label>
	  <div class="col-sm-10">
	    <?php /* 图标不跟下面的「修改」按钮走：选了图就直接上传生效，所以这里的控件都不带 name，不会混进 saveSetting 的表单里 */ ?>
	    <div class="site-icon-set">
	      <span class="site-icon-preview"><img id="siteIconImg" src="<?php echo htmlspecialchars(site_icon_url('../'), ENT_QUOTES, 'UTF-8')?>" alt=""></span>
	      <input type="file" id="siteIconFile" accept=".ico,.png,.jpg,.jpeg,.gif,.webp,image/x-icon,image/png,image/jpeg,image/gif,image/webp" style="display:none">
	      <button type="button" class="btn btn-default btn-sm" id="siteIconPick"><i class="fa fa-upload"></i> 上传图标</button>
	      <button type="button" class="btn btn-default btn-sm" id="siteIconReset"<?php echo site_icon_custom() === '' ? ' style="display:none"' : ''?>><i class="fa fa-undo"></i> 恢复默认</button>
	    </div>
	    <span class="help-block">浏览器标签、收藏夹里显示的小图标。支持 ICO、PNG、JPG、GIF、WebP，建议用正方形图片；选好后立即生效，不用点下面的「修改」。上传的图标单独存放在 <code>assets/siteicon/</code>，覆盖上传更新包、在线更新都不会动它，更新之后不用重新换。</span>
	  </div>
	</div><br/>
<?php $blackip_rows = blackip_list();?>
	<div class="form-group">
	  <label class="col-sm-2 control-label">禁止访问IP</label>
	  <div class="col-sm-10">
	    <?php /* 和页脚代码一样：行内控件不带 name，由下面的脚本汇总成这一个 JSON 字段提交；初始值就是现有列表。
	            IP 多了页面会被拉得很长，所以列表放在固定高度的框里滚动，上面给搜索、条数和批量添加 */ ?>
	    <input type="hidden" name="blackip_list" id="blackipField" value="<?php echo htmlspecialchars(json_encode($blackip_rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')?>"/>
	    <div class="blackip-tools">
	      <input type="search" class="form-control input-sm blackip-search" id="blackipSearch" placeholder="搜索 IP 或备注" autocomplete="off">
	      <span class="blackip-count" id="blackipCount"></span>
	      <button type="button" class="btn btn-default btn-sm" id="blackipBatchToggle"><i class="fa fa-list"></i> 批量添加</button>
	      <button type="button" class="btn btn-success btn-sm" id="blackipAdd"><i class="fa fa-plus"></i> 添加 IP</button>
	    </div>
	    <div class="blackip-batch" id="blackipBatch" style="display:none">
	      <textarea class="form-control input-sm" id="blackipBatchText" rows="4" placeholder="一次粘贴多个 IP 或网段，用换行、空格、逗号或 | 隔开都行"></textarea>
	      <div class="blackip-batch-foot">
	        <input type="text" class="form-control input-sm" id="blackipBatchNote" maxlength="50" placeholder="这一批的备注（选填）">
	        <button type="button" class="btn btn-primary btn-sm" id="blackipBatchAdd">加入列表</button>
	        <span class="blackip-batch-msg" id="blackipBatchMsg"></span>
	      </div>
	    </div>
	    <div class="blackip-scroll" id="blackipScroll">
	      <table class="table table-bordered footer-code-table blackip-table">
	        <thead><tr><th style="width:56px">启用</th><th style="width:240px">IP 或网段</th><th>备注</th><th style="width:72px">操作</th></tr></thead>
	        <tbody id="blackipRows">
	        <?php foreach($blackip_rows as $item){?>
	          <tr data-ip-row>
	            <td class="text-center"><input type="checkbox" data-f="enabled" <?php echo $item['enabled'] ? 'checked' : ''?>></td>
	            <td><input type="text" class="form-control input-sm" data-f="ip" maxlength="50" placeholder="如 1.2.3.4 或 1.2.3.0/24" value="<?php echo htmlspecialchars($item['ip'], ENT_QUOTES, 'UTF-8')?>"></td>
	            <td><input type="text" class="form-control input-sm" data-f="note" maxlength="50" placeholder="选填，如：刷流量" value="<?php echo htmlspecialchars($item['note'], ENT_QUOTES, 'UTF-8')?>"></td>
	            <td class="text-center"><button type="button" class="btn btn-danger btn-xs" data-ip-del title="删除"><i class="fa fa-trash"></i></button></td>
	          </tr>
	        <?php }?>
	        </tbody>
	      </table>
	    </div>
	    <span class="help-block">列表里启用的 IP 访问前台会直接返回 403（已登录后台的管理员不受影响）。每条可以是单个 IP（IPv4 / IPv6），也可以是网段，如 <code>1.2.3.0/24</code>。取消勾选「启用」可暂时放行而不删除；搜索只是筛选显示，保存时整份列表都会存；改完点下面「修改」才会保存。你现在的 IP 是 <code><?php echo htmlspecialchars($clientip, ENT_QUOTES, 'UTF-8')?></code>。</span>
	  </div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文件查看页公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="gg_file" rows="3" placeholder="不填写则不显示"><?php echo htmlspecialchars($conf['gg_file'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">违规文件公示</label>
	  <div class="col-sm-10"><select class="form-control" name="violation_open" default="<?php echo isset($conf['violation_open'])?$conf['violation_open']:1?>"><option value="0">关闭</option><option value="1">开启</option></select><span class="help-block">开启后前台显示“违规公示”页，公示内容在<a href="./set_violation.php">违规公示管理</a>里维护</span></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">赞助名单</label>
	  <div class="col-sm-10"><select class="form-control" name="sponsor_open" default="<?php echo isset($conf['sponsor_open'])?$conf['sponsor_open']:1?>"><option value="0">关闭</option><option value="1">开启</option></select><span class="help-block">关闭后前台导航不再显示“赞助名单”、直接敲地址也进不去，后台的<a href="./set_sponsor.php">赞助名单管理</a>菜单同时隐藏。名单数据保留，随时可以再打开。</span></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">公示页说明</label>
	  <div class="col-sm-10"><textarea class="form-control" name="violation_notice" rows="3" placeholder="显示在违规公示页顶部的说明文字"><?php echo htmlspecialchars(isset($conf['violation_notice'])?$conf['violation_notice']:'')?></textarea></div>
	</div><br/>
<?php $footer_code_list = footer_codes();?>
	<div class="form-group">
	  <label class="col-sm-2 control-label">页脚代码</label>
	  <div class="col-sm-10">
	    <?php /* 表格里的控件都不带 name，由下面的脚本汇总成这一个 JSON 字段提交；初始值就是现有列表，脚本万一没跑起来也不会把列表存丢 */ ?>
	    <input type="hidden" name="footer_codes" id="footerCodesField" value="<?php echo htmlspecialchars(json_encode($footer_code_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')?>"/>
	    <div class="table-responsive footer-code-wrap">
	      <table class="table table-bordered footer-code-table">
	        <thead><tr><th style="width:64px">显示</th><th style="width:180px">名称</th><th>代码</th><th style="width:84px">操作</th></tr></thead>
	        <tbody id="footerCodeRows">
	        <?php foreach($footer_code_list as $item){?>
	          <tr data-code-row>
	            <td class="text-center"><input type="checkbox" data-f="enabled" <?php echo $item['enabled'] ? 'checked' : ''?>></td>
	            <td><input type="text" class="form-control" data-f="name" maxlength="50" placeholder="如：百度统计" value="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8')?>"></td>
	            <td><textarea class="form-control footer-code-input" data-f="code" rows="2" placeholder="粘贴 HTML 或 &lt;script&gt; 代码"><?php echo htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8')?></textarea><?php if(footer_code_has_runtime($item['code'])){?><small class="footer-code-note">这段里有旧的「本站已安全运行」代码，这部分已由下面的「页脚运行时间」接管，前台不再输出，可以删掉。</small><?php }?></td>
	            <td class="text-center"><button type="button" class="btn btn-danger btn-xs" data-code-del><i class="fa fa-trash"></i> 删除</button></td>
	          </tr>
	        <?php }?>
	        </tbody>
	      </table>
	    </div>
	    <button type="button" class="btn btn-success btn-sm footer-code-add" id="footerCodeAdd"><i class="fa fa-plus"></i> 添加代码</button>
	    <span class="help-block">每条代码按列表顺序原样输出在前台页脚版权信息后面，可以放统计代码（百度统计、51LA 等）、备案号、徽章链接等。取消勾选「显示」可暂时停用而不删除；删除或修改后点下面「修改」才会保存。</span>
	  </div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">页脚运行时间</label>
	  <div class="col-sm-10"><select class="form-control" name="runtime_open" default="<?php echo isset($conf['runtime_open'])?$conf['runtime_open']:1?>"><option value="0">关闭</option><option value="1">开启</option></select><span class="help-block">开启后前台页脚版权信息下方显示“本站已安全运行：x年x天x时x分钟x秒”，每秒刷新</span></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">建站时间</label>
	  <div class="col-sm-10"><input type="datetime-local" step="1" name="runtime_start" value="<?php echo date('Y-m-d\TH:i:s', site_runtime_start())?>" class="form-control"/><span class="help-block">运行时间从这一刻开始计算，按北京时间，精确到秒</span></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文件搜索功能</label>
	  <div class="col-sm-10"><select class="form-control" name="filesearch" default="<?php echo $conf['filesearch']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<script>
//网站图标：选了图直接上传，不跟表单一起提交
(function(){
	var file = document.getElementById('siteIconFile'), pick = document.getElementById('siteIconPick'), reset = document.getElementById('siteIconReset'), img = document.getElementById('siteIconImg');
	if(!file || !pick || !reset || !img)return;
	function apply(data){
		//默认图标的地址一直是 favicon.ico，带个时间戳浏览器才会重新取
		var url = data.url + (data.custom ? '' : '?t=' + new Date().getTime());
		img.src = url;
		reset.style.display = data.custom ? '' : 'none';
		//当前这个后台标签页上的图标也一起换掉，不用等刷新
		$('link[rel="icon"],link[rel="apple-touch-icon"]').remove();
		$('<link rel="icon">').attr('href', url).appendTo('head');
	}
	function request(act, body){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'POST',
			url : 'ajax.php?act=' + act,
			data : body,
			dataType : 'json',
			processData : false,
			contentType : false,
			success : function(data){
				layer.close(ii);
				if(data.code == 0){
					apply(data);
					layer.msg(data.msg, {icon:1, time:data.msg.length > 12 ? 6000 : 2000});
				}else{
					layer.alert(data.msg, {icon:2});
				}
			},
			error : function(){
				layer.close(ii);
				layer.msg('服务器错误', {icon:2});
			}
		});
	}
	function send(blob, name){
		var fd = new FormData();
		fd.append('file', blob, name);
		request('siteicon', fd);
	}
	pick.onclick = function(){ file.click(); };
	file.onchange = function(){
		var f = file.files && file.files[0];
		//清掉选择，下次再选同一个文件也能触发
		file.value = '';
		if(!f)return;
		//ICO 原样上传：它里面可以带好几个尺寸，转一道就丢了
		if(/\.ico$/i.test(f.name) || f.type === 'image/x-icon' || f.type === 'image/vnd.microsoft.icon'){
			send(f, 'icon.ico');
			return;
		}
		//其余图片先等比缩进最大 256×256 的透明方形画布再传：图标用不着更大的，原图动辄几 MB；小图不放大
		var url = URL.createObjectURL(f), pic = new Image();
		pic.onload = function(){
			URL.revokeObjectURL(url);
			var w = pic.naturalWidth, h = pic.naturalHeight, cv = document.createElement('canvas');
			//浏览器画不了就传原图，由服务端把关
			if(!w || !h || !cv.getContext || !cv.toBlob){ send(f, f.name); return; }
			var side = Math.min(256, Math.max(w, h)), k = side / Math.max(w, h), dw = Math.round(w * k), dh = Math.round(h * k);
			cv.width = cv.height = side;
			cv.getContext('2d').drawImage(pic, Math.round((side - dw) / 2), Math.round((side - dh) / 2), dw, dh);
			cv.toBlob(function(blob){
				if(blob)send(blob, 'icon.png');
				else send(f, f.name);
			}, 'image/png');
		};
		pic.onerror = function(){
			URL.revokeObjectURL(url);
			layer.alert('这张图片浏览器打不开，换一张试试（支持 ICO、PNG、JPG、GIF、WebP）', {icon:2});
		};
		pic.src = url;
	};
	reset.onclick = function(){
		var confirmobj = layer.confirm('恢复成程序自带的默认图标？上传的图标会被删除。', {icon:3}, function(){
			layer.close(confirmobj);
			request('siteiconReset', new FormData());
		});
	};
})();
//页脚代码列表：行内控件不带 name，每次改动都把整张表汇总成 JSON 写进隐藏字段，saveSetting 序列化表单时带上它
(function(){
	var tbody = document.getElementById('footerCodeRows'), field = document.getElementById('footerCodesField'), addBtn = document.getElementById('footerCodeAdd');
	if(!tbody || !field || !addBtn)return;
	function rows(){ return tbody.querySelectorAll('tr[data-code-row]'); }
	function sync(){
		var list = [];
		for(var i = 0, r = rows(); i < r.length; i++){
			list.push({
				enabled: r[i].querySelector('[data-f="enabled"]').checked ? 1 : 0,
				name: r[i].querySelector('[data-f="name"]').value,
				code: r[i].querySelector('[data-f="code"]').value
			});
		}
		field.value = JSON.stringify(list);
	}
	function toggleEmpty(){
		var empty = tbody.querySelector('tr.footer-code-empty');
		if(rows().length){ if(empty)empty.parentNode.removeChild(empty); return; }
		if(empty)return;
		empty = document.createElement('tr');
		empty.className = 'footer-code-empty';
		empty.innerHTML = '<td colspan="4">还没有页脚代码，点下面的「添加代码」新建一条。</td>';
		tbody.appendChild(empty);
	}
	addBtn.addEventListener('click', function(){
		var row = document.createElement('tr');
		row.setAttribute('data-code-row', '1');
		row.innerHTML =
			'<td class="text-center"><input type="checkbox" data-f="enabled" checked></td>' +
			'<td><input type="text" class="form-control" data-f="name" maxlength="50" placeholder="如：百度统计"></td>' +
			'<td><textarea class="form-control footer-code-input" data-f="code" rows="2" placeholder="粘贴 HTML 或 &lt;script&gt; 代码"></textarea></td>' +
			'<td class="text-center"><button type="button" class="btn btn-danger btn-xs" data-code-del><i class="fa fa-trash"></i> 删除</button></td>';
		tbody.appendChild(row);
		toggleEmpty();
		sync();
		row.querySelector('[data-f="name"]').focus();
	});
	tbody.addEventListener('input', sync);
	tbody.addEventListener('change', sync);
	tbody.addEventListener('click', function(e){
		var btn = e.target.closest ? e.target.closest('[data-code-del]') : null;
		if(!btn || !tbody.contains(btn))return;
		var row = btn.closest('tr');
		row.parentNode.removeChild(row);
		toggleEmpty();
		sync();
	});
	toggleEmpty();
})();
//禁止访问 IP 列表：做法同上，汇总成 JSON 写进 #blackipField。
//IP 多的时候列表在固定高度的框里滚动；搜索只是把不匹配的行藏起来，汇总时照样带上，不会因为筛选把条目存丢
(function(){
	var tbody = document.getElementById('blackipRows'), field = document.getElementById('blackipField'), addBtn = document.getElementById('blackipAdd');
	var box = document.getElementById('blackipScroll'), search = document.getElementById('blackipSearch'), countEl = document.getElementById('blackipCount');
	var batch = document.getElementById('blackipBatch'), batchText = document.getElementById('blackipBatchText'), batchNote = document.getElementById('blackipBatchNote'), batchMsg = document.getElementById('blackipBatchMsg');
	if(!tbody || !field || !addBtn)return;
	function rows(){ return tbody.querySelectorAll('tr[data-ip-row]'); }
	function sync(){
		var list = [], on = 0;
		for(var i = 0, r = rows(); i < r.length; i++){
			var en = r[i].querySelector('[data-f="enabled"]').checked ? 1 : 0;
			on += en;
			list.push({
				enabled: en,
				ip: r[i].querySelector('[data-f="ip"]').value,
				note: r[i].querySelector('[data-f="note"]').value
			});
		}
		field.value = JSON.stringify(list);
		if(countEl)countEl.textContent = list.length ? ('共 ' + list.length + ' 条，启用 ' + on + ' 条') : '';
	}
	//空列表、筛选后没有匹配，都用同一行提示
	function tip(text){
		var empty = tbody.querySelector('tr.footer-code-empty');
		if(!text){ if(empty)empty.parentNode.removeChild(empty); return; }
		if(!empty){
			empty = document.createElement('tr');
			empty.className = 'footer-code-empty';
			empty.innerHTML = '<td colspan="4"></td>';
			tbody.appendChild(empty);
		}
		empty.firstChild.textContent = text;
	}
	function filter(){
		var kw = search ? search.value.replace(/^\s+|\s+$/g, '').toLowerCase() : '', shown = 0, r = rows();
		for(var i = 0; i < r.length; i++){
			var hay = (r[i].querySelector('[data-f="ip"]').value + ' ' + r[i].querySelector('[data-f="note"]').value).toLowerCase();
			var hit = !kw || hay.indexOf(kw) !== -1;
			r[i].style.display = hit ? '' : 'none';
			if(hit)shown++;
		}
		if(!r.length)tip('没有禁止访问的 IP，点上面的「添加 IP」或「批量添加」。');
		else if(!shown)tip('没有匹配「' + kw + '」的 IP。');
		else tip('');
	}
	function makeRow(ip, note){
		var row = document.createElement('tr');
		row.setAttribute('data-ip-row', '1');
		row.innerHTML =
			'<td class="text-center"><input type="checkbox" data-f="enabled" checked></td>' +
			'<td><input type="text" class="form-control input-sm" data-f="ip" maxlength="50" placeholder="如 1.2.3.4 或 1.2.3.0/24"></td>' +
			'<td><input type="text" class="form-control input-sm" data-f="note" maxlength="50" placeholder="选填，如：刷流量"></td>' +
			'<td class="text-center"><button type="button" class="btn btn-danger btn-xs" data-ip-del title="删除"><i class="fa fa-trash"></i></button></td>';
		//用 value 属性赋值，不拼进 HTML，粘贴进来的内容不会被当成标签
		row.querySelector('[data-f="ip"]').value = ip || '';
		row.querySelector('[data-f="note"]').value = note || '';
		tbody.appendChild(row);
		return row;
	}
	//新加的行在最底下：先清掉搜索，再把滚动框拉到底，免得加了却看不见
	function reveal(row){
		if(search && search.value){ search.value = ''; }
		filter();
		if(box)box.scrollTop = box.scrollHeight;
		if(row)row.querySelector('[data-f="ip"]').focus();
	}
	addBtn.addEventListener('click', function(){
		var row = makeRow('', '');
		sync();
		reveal(row);
	});
	if(search)search.addEventListener('input', filter);
	//搜索框里回车别把整张设置表单提交了
	if(search)search.addEventListener('keydown', function(e){ if(e.keyCode === 13)e.preventDefault(); });
	var batchToggle = document.getElementById('blackipBatchToggle');
	if(batchToggle && batch)batchToggle.addEventListener('click', function(){
		var open = batch.style.display === 'none';
		batch.style.display = open ? '' : 'none';
		if(open && batchText)batchText.focus();
	});
	var batchAdd = document.getElementById('blackipBatchAdd');
	if(batchAdd && batchText)batchAdd.addEventListener('click', function(){
		var parts = batchText.value.split(/[\s,，|;；]+/), seen = {}, added = 0, dup = 0, last = null;
		for(var i = 0, r = rows(); i < r.length; i++)seen[r[i].querySelector('[data-f="ip"]').value.replace(/^\s+|\s+$/g, '').toLowerCase()] = 1;
		for(var j = 0; j < parts.length; j++){
			var ip = parts[j].replace(/^\s+|\s+$/g, '');
			if(!ip)continue;
			if(seen[ip.toLowerCase()]){ dup++; continue; }
			seen[ip.toLowerCase()] = 1;
			last = makeRow(ip, batchNote ? batchNote.value : '');
			added++;
		}
		if(!added && !dup){ if(batchMsg)batchMsg.textContent = '没有可添加的内容'; return; }
		batchText.value = '';
		if(batchMsg)batchMsg.textContent = '加了 ' + added + ' 条' + (dup ? '，跳过已有的 ' + dup + ' 条' : '') + '；点下面「修改」才会保存，写法不对的保存时会提示';
		sync();
		reveal(null);
	});
	tbody.addEventListener('input', sync);
	tbody.addEventListener('change', sync);
	tbody.addEventListener('click', function(e){
		var btn = e.target.closest ? e.target.closest('[data-ip-del]') : null;
		if(!btn || !tbody.contains(btn))return;
		var row = btn.closest('tr');
		row.parentNode.removeChild(row);
		sync();
		filter();
	});
	sync();
	filter();
})();
</script>
<?php
}elseif($mod=='appearance'){
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
if(!in_array($site_theme, site_theme_keys(), true)){
	$site_theme = default_site_theme();
}
//顺手同步一次静态 404 页的外观：已经设置好外观的站点不用再点一次保存
sync_404_theme($site_theme);
?>
<?php /* 表单包住整块：渐变工具条吸在顶部，外观卡片面板从它下面滚过去，
        两边的值一起提交，翻到最底下也不用回头找保存按钮 */ ?>
<form onsubmit="return saveSetting(this)" method="post" role="form" class="appearance-form">
<?php include './set_appearance_grad.php';?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">外观设置</h3></div>
<div class="panel-body">
	<div class="appearance-group">
	  <div class="appearance-group-head">
	    <strong>布局型外观</strong>
	    <small>会改变页面结构：导航位置、内容排版都不一样，前台和后台同时生效。</small>
	  </div>
	<div class="appearance-options">
	  <label class="appearance-card <?php echo $site_theme === 'dashboard' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="dashboard" <?php echo $site_theme === 'dashboard' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-dashboard">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>控制台侧栏风</strong>
	    <small>顶部导航变为左侧固定侧栏，内容区改为圆角卡片布局，更接近后台管理系统的观感。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'console' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="console" <?php echo $site_theme === 'console' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-console">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>数据控制台风</strong>
	    <small>更紧凑的左侧侧栏，标题与搜索独立成顶栏，列表改为白色卡片，信息密度最高。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'portal' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="portal" <?php echo $site_theme === 'portal' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-portal">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>上传门户风</strong>
	    <small>居中的门户式顶部导航，首页多一块大号上传引导区，绿色配色，适合面向访客的公开分享站。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'workspace' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="workspace" <?php echo $site_theme === 'workspace' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-workspace">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>深色工作台风</strong>
	    <small>深色底 + 左侧图标导航条（鼠标移上去展开文字），内容为深色圆角面板，适合长时间浏览管理。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'mac' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="mac" <?php echo $site_theme === 'mac' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-mac">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>macOS 窗口风</strong>
	    <small>整站套进一个 macOS 窗口：带红黄绿三颗灯的标题栏、底部状态栏，文件列表默认是访达式图标网格，可一键切回列表视图；后台和登录页同款窗口。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'cockpit' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="cockpit" <?php echo $site_theme === 'cockpit' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-cockpit">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>渐变仪表盘风</strong>
	    <small>白色悬浮圆角侧栏（默认展开显示文字），文件列表页顶部是问候栏和紫色渐变额度卡，右侧多一列存储分布、最近上传和快捷入口面板。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'studio' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="studio" <?php echo $site_theme === 'studio' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-studio">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>蓝白工作台风</strong>
	    <small>白色分组侧栏（工作空间 / 个人中心，底部带升级卡）+ 顶部搜索条，文件列表页是蓝白横幅、四张统计卡、带筛选和排序的文件面板，右侧一列今日上传、我的权限、快捷操作和最近动态。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'nebula' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="nebula" <?php echo $site_theme === 'nebula' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-nebula">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>深空科技风</strong>
	    <small>蓝白工作台的深色版：深蓝星空底配发光描边，横幅是大标题加三个卖点，统计卡带彩色辉光，适合长时间浏览的深色站点。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'royal' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="royal" <?php echo $site_theme === 'royal' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-royal">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>紫韵会员风</strong>
	    <small>蓝紫渐变横幅配彩色统计卡，右侧第一块是金色会员权限卡带升级入口，列表下方还有一条卖点推广横幅。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'crisp' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="crisp" <?php echo $site_theme === 'crisp' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-crisp">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>清爽极简风</strong>
	    <small>近乎纯白的底色，横幅不做卡片、直接铺在页面上，四条勾选卖点加两个大按钮，五张统计卡，右侧最近动态是时间轴样式。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'azure' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="azure" <?php echo $site_theme === 'azure' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-azure">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>蓝天白云风</strong>
	    <small>天蓝渐变横幅，右上角三枚浮标卖点，五张统计卡，列表下方是会员升级横幅，整体明亮通透。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'neo' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="neo" <?php echo $site_theme === 'neo' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-neo">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>潮酷涂鸦风</strong>
	    <small>粗黑描边加不带模糊的硬投影，米色底配橙黑撞色：黑色侧栏、橙色选中项，横幅是超大标题加手写标语，五张统计卡各一个高饱和颜色。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'skyline' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="skyline" <?php echo $site_theme === 'skyline' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-skyline">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>云端门户风</strong>
	    <small>家族里唯一一套顶部横向导航：天空渐变的大横幅带远山和居中大搜索框，下面是浅色统计卡和文件面板，右侧一列蓝色上传卡、存储空间、最近上传。后台保持顶栏布局。</small>
	  </label>
	</div>
	</div>
	<div class="appearance-group">
	  <div class="appearance-group-head">
	    <strong>配色型外观</strong>
	    <small>只改变颜色、背景纹理和质感，页面结构保持默认的顶部导航布局。</small>
	  </div>
	<div class="appearance-options">
	  <label class="appearance-card <?php echo $site_theme === 'cloud' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="cloud" <?php echo $site_theme === 'cloud' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-cloud">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>蓝白清爽</strong>
	    <small>蓝白清爽风格，适合默认展示。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'night' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="night" <?php echo $site_theme === 'night' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-night">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>黑夜风格</strong>
	    <small>深色背景、蓝色高亮，适合夜间浏览。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'neon' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="neon" <?php echo $site_theme === 'neon' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-neon">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>霓虹科技黑夜</strong>
	    <small>蓝紫霓虹、科技感边框，适合深色展示。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'aurora' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="aurora" <?php echo $site_theme === 'aurora' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-aurora">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>蓝紫渐变玻璃</strong>
	    <small>渐变背景、半透明玻璃卡片，适合展示型页面。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'onefour' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="onefour" <?php echo $site_theme === 'onefour' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-onefour">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>暗黑科技后台风</strong>
	    <small>深色点阵背景、半透明暗色面板、圆角按钮与标签，整体偏科技感、数据管理感，适合文件列表、管理后台、资源站页面。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'celadon' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="celadon" <?php echo $site_theme === 'celadon' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-celadon">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>青瓷微澜</strong>
	    <small>青瓷色同心波纹、留白通透，冷静耐看，适合作品集、图床和长时间浏览的列表页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'lilac' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="lilac" <?php echo $site_theme === 'lilac' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-lilac">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>淡紫点阵</strong>
	    <small>薰衣草底色配细密点阵，柔和不刺眼，适合内容站、文档站和图片分享。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'paper' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="paper" <?php echo $site_theme === 'paper' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-paper">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>米白纸张</strong>
	    <small>极简纸张质感，横线纸纹配墨黑标题，几乎无色相干扰，适合以文件名和文字为主的列表。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'blush' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="blush" <?php echo $site_theme === 'blush' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-blush">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>淡粉暖调</strong>
	    <small>浅粉底色配玫瑰色高亮，温和轻盈，适合相册、素材站和面向大众的分享页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'sky' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="sky" <?php echo $site_theme === 'sky' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-sky">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>天蓝细纹</strong>
	    <small>青蓝色调配斜向细纹，比默认的蓝白更冷更透，适合工具站和资源下载页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'mint' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="mint" <?php echo $site_theme === 'mint' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-mint">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>薄荷蜂巢</strong>
	    <small>薄荷绿蜂巢暗纹，清爽有生气，适合图床首页和面向年轻用户的站点。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'sunset' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="sunset" <?php echo $site_theme === 'sunset' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-sunset">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>落日熔金</strong>
	    <small>珊瑚橙到品红的落日渐变，叠磨砂玻璃卡片与暖色辉光，浓烈张扬，适合首页和活动页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'abyss' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="abyss" <?php echo $site_theme === 'abyss' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-abyss">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>深海玻璃</strong>
	    <small>深海蓝绿渐变配青色辉光，通透安静的磨砂玻璃，适合长时间浏览的资源站。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'emerald' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="emerald" <?php echo $site_theme === 'emerald' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-emerald">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>翡翠流光</strong>
	    <small>翡翠绿到松石色的流光渐变，磨砂玻璃配柔亮描边，清透有质感，适合作品集和图床。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'sakura' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="sakura" <?php echo $site_theme === 'sakura' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-sakura">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>樱雾玻璃</strong>
	    <small>粉紫到天青的浅色雾面渐变，白色磨砂卡片，轻盈明亮，适合相册和展示型页面。</small>
	  </label>
	</div>
	</div>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
保存后前台页面会立即使用选中的外观。布局型外观会同时改变后台：“控制台侧栏风”“数据控制台风”“深色工作台风”把后台顶部导航变成左侧侧栏，“macOS 窗口风”把后台也套进同一个窗口（顶栏变标题栏、底部加状态栏），“上传门户风”只换后台配色、保留顶部导航；配色型外观不影响后台布局。外观配色按外观分别保存：主色/副色会把该外观的整套颜色一起重算（前台和后台一起变），只对改过的那几套生成覆盖样式，没动过的外观仍然是原配色。
</div>
</div>
</form>
<?php
}elseif($mod=='api'){
$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$admin_path = substr($sitepath, strrpos($sitepath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].str_replace($admin_path,'',$sitepath).'/';
$api_endpoint = $siteurl.'api.php';
?>
<div class="api-settings-page">
  <div class="panel panel-primary api-config-panel">
    <div class="panel-heading">
      <h3 class="panel-title"><i class="fa fa-cloud-upload"></i> 上传API设置</h3>
      <span class="api-status <?php echo !empty($conf['api_open']) ? 'is-on' : 'is-off'?>"><i class="fa fa-circle"></i> <?php echo !empty($conf['api_open']) ? '接口已开启' : '接口已关闭'?></span>
    </div>
    <div class="panel-body">
      <div class="api-intro"><i class="fa fa-shield"></i><div><b>开放前请先确认访问权限</b><span>建议要求用户 API 密钥。使用 CDN 时必须透传 Authorization 或 X-API-Key 请求头，并关闭 api.php 的缓存。</span></div></div>
      <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal api-config-form" role="form">
        <div class="form-group">
          <label class="col-sm-3 control-label">接口状态</label>
          <div class="col-sm-9">
            <select class="form-control" name="api_open" default="<?php echo (int)$conf['api_open']?>"><option value="0">关闭上传API</option><option value="1">开启上传API</option></select>
            <p class="help-block">关闭后，所有通过 <code>api.php</code> 发起的上传请求都将被拒绝。</p>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-3 control-label">访问权限</label>
          <div class="col-sm-9">
            <p class="form-control-static">谁能用上传 API 由会员等级决定：到 <a href="./level.php">会员等级设置</a> 里给相应的等级勾选「上传 API」。给「游客」勾上就是允许不带密钥匿名上传；带密钥上传时看密钥所属账号的等级。</p>
            <p class="help-block">密钥上传的文件会进入对应账号的“我的文件”，并使用该账号的上传额度。</p>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-3 control-label">用户密钥限制</label>
          <div class="col-sm-9">
            <div class="row">
              <div class="col-sm-6"><div class="input-group"><input type="number" min="1" max="20" name="api_key_limit" value="<?php echo isset($conf['api_key_limit']) ? intval($conf['api_key_limit']) : 5?>" class="form-control"><span class="input-group-addon">把/用户</span></div></div>
              <div class="col-sm-6"><div class="input-group"><input type="number" min="0" max="3650" name="api_key_expire_days" value="<?php echo isset($conf['api_key_expire_days']) ? intval($conf['api_key_expire_days']) : 365?>" class="form-control"><span class="input-group-addon">天有效</span></div></div>
            </div>
            <p class="help-block">有效期填写 0 表示永久；它也是用户创建密钥时的默认值。</p>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-3 control-label">来源域名白名单</label>
          <div class="col-sm-9">
            <input type="text" name="api_referer" value="<?php echo htmlspecialchars(isset($conf['api_referer']) ? $conf['api_referer'] : '', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="example.com|upload.example.com"/>
            <p class="help-block">多个域名使用 <code>|</code> 分隔，不填写表示不限制。填写后所有请求都必须携带匹配的 Referer，服务端和命令行调用也不例外；使用用户密钥时通常建议留空。</p>
          </div>
        </div>
        <div class="form-group api-form-actions">
          <div class="col-sm-offset-3 col-sm-9"><button type="submit" name="submit" class="btn btn-primary"><i class="fa fa-save"></i> 保存API设置</button></div>
        </div>
      </form>
    </div>
  </div>

  <div class="panel panel-info api-doc-panel">
    <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-book"></i> 上传API文档</h3></div>
    <div class="panel-body">
      <div class="api-endpoint-card">
        <div class="api-endpoint-meta"><span class="api-method">POST</span><div><b>接口地址</b><small>请求类型：multipart/form-data</small></div></div>
        <div class="api-endpoint-value"><code id="apiEndpoint"><?php echo htmlspecialchars($api_endpoint, ENT_QUOTES, 'UTF-8')?></code><button type="button" class="btn btn-default btn-sm" onclick="copyApiEndpoint(this)"><i class="fa fa-copy"></i> 复制</button></div>
      </div>
      <div class="api-format-row"><span>返回格式</span><b>JSON</b><b>JSONP</b><b>FORM</b><small>支持服务端程序调用和同源浏览器调用</small></div>

      <section class="api-doc-section">
        <div class="api-section-title"><span>01</span><div><h4>请求参数</h4><p>仅 <code>file</code> 为必填项，其余参数均可按需传入。</p></div></div>
        <div class="table-responsive api-table-wrap">
          <table class="table table-hover api-table">
            <thead><tr><th>参数</th><th>含义</th><th>必填</th><th>示例值</th><th>说明</th></tr></thead>
            <tbody>
              <tr><td><code>file</code></td><td>文件</td><td><span class="api-required">必填</span></td><td>—</td><td>multipart 格式文件</td></tr>
              <tr><td><code>Authorization</code></td><td>用户 API 密钥</td><td><span class="api-required">按后台模式</span></td><td><code>Bearer pan_...</code></td><td>放在 HTTP 请求头中；也可使用 <code>X-API-Key</code>，不要作为 URL 参数传递</td></tr>
              <tr><td><code>show</code></td><td>首页显示</td><td>否</td><td><code>1</code></td><td>1 为公开显示，不传或 0 为私密</td></tr>
              <tr><td><code>ispwd</code></td><td>设置密码</td><td>否</td><td><code>0</code></td><td>默认为否</td></tr>
              <tr><td><code>pwd</code></td><td>下载密码</td><td>否</td><td><code>123456</code></td><td>默认留空</td></tr>
              <tr><td><code>format</code></td><td>返回格式</td><td>否</td><td><code>json</code></td><td>可选 json、jsonp、form，默认 json</td></tr>
              <tr><td><code>backurl</code></td><td>跳转地址</td><td>否</td><td><code>https://...</code></td><td>仅在 form 格式下有效</td></tr>
              <tr><td><code>callback</code></td><td>回调函数</td><td>否</td><td><code>callback</code></td><td>仅在 jsonp 格式下有效</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="api-doc-section">
        <div class="api-section-title"><span>02</span><div><h4>返回参数</h4><p><code>code = 0</code> 表示上传成功，其它值会在 <code>msg</code> 中说明原因。</p></div></div>
        <div class="table-responsive api-table-wrap">
          <table class="table table-hover api-table">
            <thead><tr><th>参数</th><th>类型</th><th>示例值</th><th>说明</th></tr></thead>
            <tbody>
              <tr><td><code>code</code></td><td>Int</td><td><code>0</code></td><td>0 为成功，其它为失败</td></tr>
              <tr><td><code>msg</code></td><td>String</td><td>上传成功！</td><td>操作提示或失败原因</td></tr>
              <tr><td><code>hash</code></td><td>String</td><td><code>f1e807...bda8</code></td><td>文件 MD5</td></tr>
              <tr><td><code>name</code></td><td>String</td><td><code>example.jpg</code></td><td>文件名称</td></tr>
              <tr><td><code>size</code></td><td>Int</td><td><code>58937</code></td><td>文件大小，单位为字节</td></tr>
              <tr><td><code>type</code></td><td>String</td><td><code>jpg</code></td><td>文件格式</td></tr>
              <tr><td><code>downurl</code></td><td>String</td><td><code>https://...</code></td><td>下载地址</td></tr>
              <tr><td><code>viewurl</code></td><td>String</td><td><code>https://...</code></td><td>图片、音频和视频文件的预览地址</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="api-doc-section api-example-section">
        <div class="api-section-title"><span>03</span><div><h4>调用示例</h4><p>下面示例以 JSON 格式上传本地文件。</p></div></div>
        <div class="api-code-card"><div class="api-code-head"><span>cURL</span><button type="button" onclick="copyApiCode(this)"><i class="fa fa-copy"></i> 复制代码</button></div><pre><code>curl -X POST \
  -H "Authorization: Bearer pan_请替换为用户密钥" \
  -F "file=@example.jpg" \
  -F "format=json" \
  "<?php echo htmlspecialchars($api_endpoint, ENT_QUOTES, 'UTF-8')?>"</code></pre></div>
      </section>
    </div>
  </div>
</div>
<script>
function apiCopyText(text, button){
	function done(){
		var old = button.innerHTML;
		button.innerHTML = '<i class="fa fa-check"></i> 已复制';
		setTimeout(function(){ button.innerHTML = old; }, 1500);
	}
	if(navigator.clipboard && window.isSecureContext){ navigator.clipboard.writeText(text).then(done); return; }
	var area = document.createElement('textarea');
	area.value = text; area.style.position = 'fixed'; area.style.opacity = '0';
	document.body.appendChild(area); area.select();
	try{ document.execCommand('copy'); done(); }catch(e){ if(window.layer)layer.msg('复制失败，请手动复制'); }
	document.body.removeChild(area);
}
function copyApiEndpoint(button){ apiCopyText(document.getElementById('apiEndpoint').textContent, button); }
function copyApiCode(button){ apiCopyText(button.parentNode.nextElementSibling.textContent, button); }
</script>
<?php
}elseif($mod=='account_n' && $_POST['do']=='submit'){
	if(!checkRefererHost())exit;
	$user=$_POST['user'];
	$oldpwd=$_POST['oldpwd'];
	$newpwd=$_POST['newpwd'];
	$newpwd2=$_POST['newpwd2'];
	if($user==null)showmsg('用户名不能为空！',3);
	saveSetting('admin_user',$user);
	if(!empty($newpwd) && !empty($newpwd2)){
		if(!hash_equals((string)$conf['admin_pwd'], (string)$oldpwd))showmsg('旧密码不正确！',3);
		if($newpwd!=$newpwd2)showmsg('两次输入的密码不一致！',3);
		saveSetting('admin_pwd',$newpwd);
	}
	showmsg('修改成功！请重新登录',1);
}elseif($mod=='account'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">管理员账号设置</h3></div>
<div class="panel-body">
  <form action="./set.php?mod=account_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户名</label>
	  <div class="col-sm-10"><input type="text" name="user" value="<?php echo $conf['admin_user']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">旧密码</label>
	  <div class="col-sm-10"><input type="password" name="oldpwd" value="" class="form-control" placeholder="请输入当前的管理员密码"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">重输密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd2" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='iptype'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户IP地址获取设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-2 control-label">用户IP地址获取方式</label>
	  <div class="col-sm-10"><select class="form-control" name="ip_type" default="<?php echo $conf['ip_type']?>"><option value="0">0_X_FORWARDED_FOR</option><option value="1">1_X_REAL_IP</option><option value="2">2_REMOTE_ADDR</option><option value="3">3_Cloudflare（自动校验来源）</option></select></div>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
此功能设置用于防止用户伪造IP请求。<br/>
X_FORWARDED_FOR：之前的获取真实IP方式，极易被伪造IP<br/>
X_REAL_IP：在网站使用CDN的情况下选择此项，在不使用CDN的情况下也会被伪造<br/>
REMOTE_ADDR：直接获取真实请求IP，无法被伪造，但可能获取到的是CDN节点IP<br/>
<b>你可以从中选择一个能显示你真实地址的IP，优先选下方的选项。</b>
</div>
</div>
<script>
$(document).ready(function(){
	$.ajax({
		type : "GET",
		url : "ajax.php?act=iptype",
		dataType : 'json',
		async: true,
		success : function(data) {
			$("select[name='ip_type']").empty();
			var defaultv = $("select[name='ip_type']").attr('default');
			$.each(data, function(k, item){
				$("select[name='ip_type']").append('<option value="'+k+'" '+(defaultv==k?'selected':'')+'>'+ item.name +' - '+ item.ip +' '+ item.city +'</option>');
			})
		}
	});
})
</script>
<?php
}elseif($mod=='file'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">文件上传设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_image" value="<?php echo $conf['type_image']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以图片的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">音频文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_audio" value="<?php echo $conf['type_audio']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以音频的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">视频文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_video" value="<?php echo $conf['type_video']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以视频的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">禁止上传的文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_block" value="<?php echo $conf['type_block']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">文件名屏蔽关键词</label>
	  <div class="col-sm-9"><input type="text" name="name_block" value="<?php echo $conf['name_block']; ?>" class="form-control" placeholder="多个关键词用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每IP每天限制上传数量</label>
	  <div class="col-sm-9"><input type="text" name="upload_limit" value="<?php echo $conf['upload_limit']; ?>" class="form-control" placeholder="0或留空为不限制"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每分钟上传次数</label>
	  <div class="col-sm-9"><input type="text" name="upload_per_minute" value="<?php echo isset($conf['upload_per_minute'])?$conf['upload_per_minute']:10; ?>" class="form-control" placeholder="默认10，0为不限制"/>
	  <p class="help-block">只有"每天多少个"的话，脚本几秒钟就能刷满额度。这一条按分钟卡，登录用户按账号算，游客按来源地址算（IPv6 归并到 /64 前缀）。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">视频文件需要审核</label>
	  <div class="col-sm-9"><select class="form-control" name="videoreview" default="<?php echo $conf['videoreview']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">上传大小限制</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="upload_size" value="<?php echo $conf['upload_size']; ?>" class="form-control" placeholder="不填写则不限制大小"/><span class="input-group-addon">MB</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">仅限登录用户上传</label>
	  <div class="col-sm-9"><select class="form-control" name="forcelogin" default="<?php echo $conf['forcelogin']?>"><option value="0">0_否</option><option value="1">1_是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">上传时默认在首页公开</label>
	  <div class="col-sm-9"><select class="form-control" name="upload_show_default" default="<?php echo upload_show_default() ? 1 : 0?>"><option value="1">开启：默认勾选「在首页文件列表显示」</option><option value="0">关闭：默认不勾选，上传的文件不出现在首页公共列表</option></select><font color="green">只决定上传页那个勾选框一打开是勾上还是不勾，用户上传时仍可以自己改；首页的快捷上传区也按这里。不勾选的文件只是不在首页列表出现，外链照样能打开。通过上传 API 上传的文件不受影响。</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">在线编辑权限</label>
	  <div class="col-sm-9"><p class="form-control-static">谁能用在线编辑由会员等级决定：到 <a href="./level.php">会员等级设置</a> 里给相应的等级勾选「在线编辑」。等级不带的用户可以单独购买在线编辑包，也可以在用户管理里单独给某个人开通。</p></div>
	</div><br/>
<?php
//用户文件夹：显隐直接由 PHP 按当前配置写死在 style 上，不靠下面的 change 事件，
//从侧栏动态换页进来时脚本执行顺序不一样，靠事件初始化会出现该显示的没显示。
//两行各自显隐、不包外层 div：后台样式按表单下直接挂 .form-group 排版，包一层就对不齐了
$folder_open_now = !empty($conf['folder_open']);
$folder_mode_now = (isset($conf['folder_mode']) && in_array($conf['folder_mode'], ['all', 'login', 'uid'], true)) ? $conf['folder_mode'] : 'login';
?>
	<div class="form-group">
	  <label class="col-sm-3 control-label">用户文件夹</label>
	  <div class="col-sm-9"><select class="form-control" name="folder_open" id="folder_open" default="<?php echo $folder_open_now ? 1 : 0?>"><option value="0">关闭</option><option value="1">开启</option></select><font color="green">开启后「我的文件」里可以建多级文件夹，文件能在文件夹之间移动、复制粘贴。文件夹只是分类记录，不改变文件在存储里的实际位置，外链地址也不会变；复制出来的文件不占当天的上传数量。关闭后列表恢复平铺，已建的文件夹保留，再次开启即可恢复。</font></div>
	</div><br/>
	<div class="form-group" id="folder_mode_row" style="<?php echo $folder_open_now ? '' : 'display:none;'?>">
	  <label class="col-sm-3 control-label">文件夹开放范围</label>
	  <div class="col-sm-9"><p class="form-control-static">谁能用文件夹由会员等级决定：到 <a href="./level.php">会员等级设置</a> 里给相应的等级勾选「用户文件夹」（给「游客」勾上就是所有人都能用）。</p></div>
	</div><br/>
	<div class="form-group" id="folder_uids_group" style="<?php echo $folder_open_now ? '' : 'display:none;'?>">
	  <label class="col-sm-3 control-label">另外放行的UID</label>
	  <div class="col-sm-9"><input type="text" name="folder_uids" value="<?php echo isset($conf['folder_uids']) ? htmlspecialchars($conf['folder_uids']) : ''; ?>" class="form-control" placeholder="选填，例如：1,2,1001"/><font color="green">多个UID用英文逗号分隔。这些登录用户不管是什么会员等级都可以使用文件夹，不需要就留空。</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='user'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户登录设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
  	<div class="form-group">
	  <label class="col-sm-3 control-label">用户登录开关</label>
	  <div class="col-sm-9"><select class="form-control" name="userlogin" default="<?php echo $conf['userlogin']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">聚合登录接口地址</label>
	  <div class="col-sm-9"><input type="text" name="login_apiurl" value="<?php echo $conf['login_apiurl']; ?>" class="form-control" placeholder="接口地址要以http://或https://开头，以/结尾"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用APPID</label>
	  <div class="col-sm-9"><input type="text" name="login_appid" value="<?php echo $conf['login_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用APPKEY</label>
	  <div class="col-sm-9"><input type="text" name="login_appkey" value="<?php echo $conf['login_appkey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启的登录方式</label>
	  <div class="col-sm-9">
	  <input type="hidden" name="login_qq" value="0"/>
	  <input type="hidden" name="login_wx" value="0"/>
	  <input type="hidden" name="mail_reg_open" value="0"/>
	  <label class="checkbox-inline"><input type="checkbox" name="login_qq" value="1" <?php echo $conf['login_qq']?'checked':null;?>> QQ</label>
	  <label class="checkbox-inline"><input type="checkbox" name="login_wx" value="1" <?php echo $conf['login_wx']?'checked':null;?>> 微信</label>
	  <label class="checkbox-inline"><input type="checkbox" name="mail_reg_open" value="1" <?php echo !empty($conf['mail_reg_open'])?'checked':null;?>> 邮箱注册</label>
	  <p class="help-block">开启邮箱注册前，请先在「邮件发信设置」中配置可用的发信通道；关闭后，已有邮箱账号仍可正常登录。</p>
	  </div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
聚合登录接口是使用<a href="https://www.clogin.cc/recommend.php" target="_blank">彩虹聚合登录系统搭建的站点</a>。<br/>
开启后请勿随意更换登录接口站点，否则会导致之前注册的用户全部无法登录。
</div>
</div>
<script>
</script>
<?php
}elseif($mod=='green'){
	$green_label_porn = explode(',', $conf['green_label_porn']);
	$green_label_terrorism = explode(',', $conf['green_label_terrorism']);
?>
<div class="panel panel-primary green-page">
<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-shield"></i> 内容检测设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" role="form">

	<div class="green-sec">
	  <div class="green-sec-h">检测引擎</div>
	  <div class="green-grid">
		<div class="green-f">
		  <label>图片违规检测</label>
		  <select class="form-control" name="green_check" default="<?php echo $conf['green_check']?>"><option value="0">关闭</option><option value="1">阿里云内容安全接口</option><option value="2">腾讯云内容安全接口</option><option value="3">自建检测服务（本机模型）</option></select>
		  <p>自建检测不产生调用费、文件不出服务器，但要在服务器上另跑一个 Python 服务，部署见 <b>tools/nsfw/README.md</b>。</p>
		</div>
		<div class="green-f">
		  <label>检测访问网址</label>
		  <input type="text" name="apiurl" value="<?php echo $conf['apiurl']; ?>" class="form-control" placeholder="不填写则默认使用当前网址"/>
		  <p>云接口回来抓图、以及视频检测回调用的地址。留空用当前网址；填的话要以 http:// 开头、以 / 结尾。套了 CDN 建议填源站地址，绕开 CDN。</p>
		</div>
	  </div>
	  <div id="greenhealth" class="green-health">检测服务状态查询中…</div>
	</div>

	<div class="green-sec">
	  <div class="green-sec-h">检测通知<small>命中「已拦截」或「待人工」时给站长发一封邮件</small></div>
	  <div class="green-grid">
		<div class="green-f">
		  <label>命中后邮件通知</label>
		  <select class="form-control" name="green_notify" default="<?php echo isset($conf['green_notify'])?$conf['green_notify']:0?>"><option value="0">关闭</option><option value="1">开启</option></select>
		  <p>只有<b>已拦截</b>和<b>待人工</b>会发信。放行的不发（没人要看），检测失败也不发（那是检测服务本身的毛病，看状态行就行）。三种引擎都适用。</p>
		</div>
		<div class="green-f">
		  <label>收件邮箱</label>
		  <input type="text" name="green_notify_mail" value="<?php echo htmlspecialchars(isset($conf['green_notify_mail'])?$conf['green_notify_mail']:'', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="留空就发给发件邮箱自己"/>
		  <p>留空则发给<a href="./set_mail.php">邮件发信设置</a>里的发件邮箱。</p>
		</div>
		<div class="green-f">
		  <label>通知合并间隔</label>
		  <div class="input-group"><input type="text" name="green_notify_interval" value="<?php echo htmlspecialchars(isset($conf['green_notify_interval']) && $conf['green_notify_interval']!==''?$conf['green_notify_interval']:'0', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0"/><span class="input-group-addon">分钟</span></div>
		  <p><b>0 = 每命中一条就发一封。</b>一次传几十张、命中十几张的时候这会刷爆信箱，也会烧掉发信通道的日额度；填个 5 或 10，同一窗口内只发一封，期间漏掉的条数会写在下一封信里，不会悄悄丢。</p>
		</div>
	  </div>
<?php if(!empty($conf['green_notify']) && !is_mail_ready()){?>
	  <div class="green-health" style="color:#d9534f">通知开着，但<b>发信通道还没配好</b>——去<a href="./set_mail.php">邮件发信设置</a>勾一个通道并填完参数，否则这封信发不出去（只会写进日志，不影响上传）。</div>
<?php }?>
	</div>

	<div id="green_aliyun" class="green-sec" style="<?php echo $conf['green_check']!='1'?'display:none;':null; ?>">
	  <div class="green-sec-h">阿里云内容安全</div>
	  <div class="green-grid">
		<div class="green-f"><label>AccessKey Id</label><input type="text" name="aliyun_ak" value="<?php echo $conf['aliyun_ak']; ?>" class="form-control"/></div>
		<div class="green-f"><label>AccessKey Secret</label><input type="text" name="aliyun_sk" value="<?php echo $conf['aliyun_sk']; ?>" class="form-control"/></div>
		<div class="green-f">
		  <label>接入区域</label>
		  <select class="form-control" name="green_check_region" default="<?php echo $conf['green_check_region']?>"><option value="cn-beijing">华北2（北京）</option><option value="cn-shanghai">华东2（上海）</option><option value="cn-shenzhen">华南1（深圳）</option><option value="ap-southeast-1">新加坡</option><option value="us-west-1">美西</option></select>
		  <p>选一个离本站服务器最近的。</p>
		</div>
		<div class="green-f"><label>智能鉴黄</label><select class="form-control" name="green_check_porn" default="<?php echo $conf['green_check_porn']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	  </div>
	  <div id="green_check_porn_" class="green-checks" style="<?php echo $conf['green_check_porn']!=1?'display:none;':null; ?>">
		<span class="green-checks-t">鉴黄屏蔽类型</span>
		<label><input type="checkbox" name="green_label_porn[]" value="porn" <?php echo in_array('porn',$green_label_porn)?'checked':null;?>> 色情（porn）</label>
		<label><input type="checkbox" name="green_label_porn[]" value="sexy" <?php echo in_array('sexy',$green_label_porn)?'checked':null;?>> 性感（sexy）</label>
	  </div>
	  <div class="green-grid" style="margin-top:14px">
		<div class="green-f"><label>暴恐涉政识别</label><select class="form-control" name="green_check_terrorism" default="<?php echo $conf['green_check_terrorism']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	  </div>
	  <div id="green_check_terrorism_" class="green-checks" style="<?php echo $conf['green_check_terrorism']!=1?'display:none;':null; ?>">
		<span class="green-checks-t">暴恐涉政屏蔽类型</span>
<?php foreach(['bloody'=>'血腥','explosion'=>'爆炸烟光','outfit'=>'特殊装束','logo'=>'特殊标识','weapon'=>'武器','politics'=>'涉政','violence'=>'打斗','crowd'=>'聚众','parade'=>'游行','carcrash'=>'车祸现场','flag'=>'旗帜','location'=>'地标','drug'=>'涉毒','gamble'=>'赌博'] as $lk=>$lv){?>
		<label><input type="checkbox" name="green_label_terrorism[]" value="<?php echo $lk?>" <?php echo in_array($lk,$green_label_terrorism)?'checked':null;?>> <?php echo $lv?>（<?php echo $lk?>）</label>
<?php }?>
	  </div>
	</div>

	<div id="green_qcloud" class="green-sec" style="<?php echo $conf['green_check']!='2'?'display:none;':null; ?>">
	  <div class="green-sec-h">腾讯云内容安全</div>
	  <div class="green-grid">
		<div class="green-f"><label>SecretId</label><input type="text" name="qcloud_green_id" value="<?php echo $conf['qcloud_green_id']; ?>" class="form-control"/></div>
		<div class="green-f"><label>SecretKey</label><input type="text" name="qcloud_green_key" value="<?php echo $conf['qcloud_green_key']; ?>" class="form-control"/></div>
		<div class="green-f">
		  <label>接入区域</label>
		  <select class="form-control" name="green_check_region" default="<?php echo $conf['green_check_region']?>"><option value="ap-beijing">华北地区(北京)</option><option value="ap-shanghai">华东地区(上海)</option><option value="ap-guangzhou">华南地区(广州)</option><option value="ap-mumbai">亚太南部(孟买)</option><option value="ap-singapore">亚太东南(新加坡)</option><option value="eu-frankfurt">欧洲地区(法兰克福)</option><option value="na-ashburn">美国东部(弗吉尼亚)</option><option value="na-siliconvalley">美国西部(硅谷)</option></select>
		  <p>选一个离本站服务器最近的。</p>
		</div>
	  </div>
	</div>

	<div id="green_self" style="<?php echo $conf['green_check']!='3'?'display:none;':null; ?>">
	  <div class="green-sec">
		<div class="green-sec-h">自建服务连接</div>
		<div class="green-grid">
		  <div class="green-f">
			<label>检测服务地址</label>
			<input type="text" name="green_self_api" value="<?php echo htmlspecialchars(isset($conf['green_self_api'])?$conf['green_self_api']:'', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="http://127.0.0.1:9012/check"/>
			<p>留空即用默认的 http://127.0.0.1:9012/check。服务只监听回环地址，不要暴露到公网。</p>
		  </div>
		  <div class="green-f">
			<label>访问令牌</label>
			<input type="text" name="green_self_token" value="<?php echo htmlspecialchars(isset($conf['green_self_token'])?$conf['green_self_token']:'', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="选填，与 config.json 的 token 一致"/>
			<p>同机还跑着别人的程序时才需要设。</p>
		  </div>
		  <div class="green-f">
			<label>超时时间</label>
			<div class="input-group"><input type="text" name="green_self_timeout" value="<?php echo htmlspecialchars(isset($conf['green_self_timeout']) && $conf['green_self_timeout']!==''?$conf['green_self_timeout']:'5', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="5"/><span class="input-group-addon">秒</span></div>
			<p>服务没起来或超时一律<b>放行</b>，不会因为它挂了让用户传不了图。</p>
		  </div>
		</div>
	  </div>

	  <div class="green-sec">
		<div class="green-sec-h">图片判定<small>0~1 之间，调低更严、误伤也更多</small></div>
		<div class="green-grid">
		  <div class="green-f">
			<label>直接封禁阈值</label>
			<input type="text" name="green_self_block" value="<?php echo htmlspecialchars(isset($conf['green_self_block']) && $conf['green_self_block']!==''?$conf['green_self_block']:'0.85', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0.85"/>
			<p>达到即屏蔽并记入违规公示。</p>
		  </div>
		  <div class="green-f">
			<label>转人工阈值</label>
			<input type="text" name="green_self_review" value="<?php echo htmlspecialchars(isset($conf['green_self_review']) && $conf['green_self_review']!==''?$conf['green_self_review']:'0.6', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0.6"/>
			<p>介于两者之间标成<b>待审核</b>（前台下不了），等人工确认。设成和封禁阈值一样就等于不用中间档。</p>
		  </div>
		</div>
	  </div>

	  <div class="green-sec">
		<div class="green-sec-h">视频检测<small>抽帧送进同一套模型；传完先挂起，检测完自动放行或封禁，不卡上传</small></div>
		<div class="green-grid">
		  <div class="green-f">
			<label>视频违规检测</label>
			<select class="form-control" name="green_video" default="<?php echo isset($conf['green_video'])?$conf['green_video']:0?>"><option value="0">关闭</option><option value="1">开启</option></select>
		  </div>
		</div>
		<div id="green_video_" style="<?php echo empty($conf['green_video'])?'display:none;':null; ?>">
		  <div class="green-grid" style="margin-top:14px">
			<div class="green-f">
			  <label>视频封禁阈值</label>
			  <input type="text" name="green_video_block" value="<?php echo htmlspecialchars(isset($conf['green_video_block']) && $conf['green_video_block']!==''?$conf['green_video_block']:'0.85', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0.85"/>
			  <p>单帧到这个分算「命中一帧」。</p>
			</div>
			<div class="green-f">
			  <label>封禁所需命中帧数</label>
			  <input type="text" name="green_video_hit" value="<?php echo htmlspecialchars(isset($conf['green_video_hit']) && $conf['green_video_hit']!==''?$conf['green_video_hit']:'2', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="2"/>
			  <p><b>最要紧的一项。</b>转场、肤色、泳装剧照都可能让某帧飙到 0.9，只看最高分会误判到没法用。默认要 2 帧才封，命中 1 帧转人工；设成 1 等于按最高分一刀切。</p>
			</div>
			<div class="green-f">
			  <label>视频转人工阈值</label>
			  <input type="text" name="green_video_review" value="<?php echo htmlspecialchars(isset($conf['green_video_review']) && $conf['green_video_review']!==''?$conf['green_video_review']:'0.6', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0.6"/>
			  <p>最高帧落在两者之间的保持待审核。</p>
			</div>
			<div class="green-f">
			  <label>抽帧间隔</label>
			  <div class="input-group"><input type="text" name="green_video_interval" value="<?php echo htmlspecialchars(isset($conf['green_video_interval']) && $conf['green_video_interval']!==''?$conf['green_video_interval']:'5', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="5"/><span class="input-group-addon">秒</span></div>
			  <p>调小查得细但慢，一帧在 CPU 上一两百毫秒。短视频建议 1~2 秒，否则只抽得到一帧。</p>
			</div>
			<div class="green-f">
			  <label>最多抽取帧数</label>
			  <input type="text" name="green_video_frames" value="<?php echo htmlspecialchars(isset($conf['green_video_frames']) && $conf['green_video_frames']!==''?$conf['green_video_frames']:'40', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="40"/>
			  <p>封顶，长片按这个数全片均匀取。40 帧两套模型约 15~25 秒。</p>
			</div>
			<div class="green-f">
			  <label>待检超时自动放行</label>
			  <div class="input-group"><input type="text" name="green_video_timeout" value="<?php echo htmlspecialchars(isset($conf['green_video_timeout']) && $conf['green_video_timeout']!==''?$conf['green_video_timeout']:'30', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="30"/><span class="input-group-addon">分钟</span></div>
			  <p><b class="text-danger">安全阀，别关。</b>视频先挂起再放行，检测服务挂了的话所有视频会永远卡在待审核。</p>
			</div>
			<div class="green-f">
			  <label>跳过超长视频</label>
			  <div class="input-group"><input type="text" name="green_video_maxlen" value="<?php echo htmlspecialchars(isset($conf['green_video_maxlen']) && $conf['green_video_maxlen']!==''?$conf['green_video_maxlen']:'7200', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="7200"/><span class="input-group-addon">秒</span></div>
			  <p>超过不自动检测，直接<b>转人工</b>（不是放行）。</p>
			</div>
			<div class="green-f">
			  <label>跳过超大视频</label>
			  <div class="input-group"><input type="text" name="green_video_maxsize" value="<?php echo htmlspecialchars(isset($conf['green_video_maxsize']) && $conf['green_video_maxsize']!==''?$conf['green_video_maxsize']:'2048', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="2048"/><span class="input-group-addon">MB</span></div>
			  <p>同上，0 为不限制。</p>
			</div>
			<div class="green-f">
			  <label>保存证据帧</label>
			  <select class="form-control" name="green_video_shot" default="<?php echo isset($conf['green_video_shot'])?$conf['green_video_shot']:1?>"><option value="1">开启</option><option value="0">关闭</option></select>
			  <p>命中的那一帧存到 <b>data/greenshot/</b>，复核时在检测记录里直接看。</p>
			</div>
			<div class="green-f g-wide">
			  <label>定时轮询（建议配到 crontab）</label>
			  <input type="text" class="form-control" onclick="this.select()" readonly value="* * * * * curl -s '<?php echo htmlspecialchars($siteurl.'green_cb.php?poll=1&k='.green_poll_key(), ENT_QUOTES, 'UTF-8'); ?>' >/dev/null"/>
			  <p>检测服务跑完会主动回调，但回调可能被反代或 CDN 拦掉，那样文件会一直卡在待审。加上这条就有了兜底。<b>套了 CDN 的话这条是必须的。</b></p>
			</div>
		  </div>
		</div>
	  </div>
	</div>

	<div class="green-submit"><button type="submit" class="btn btn-primary">保存设置</button></div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
阿里云内容安全接口：<a href="https://yundun.console.aliyun.com/?p=cts#/api/statistics" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer">获取密钥</a><br/>
腾讯云内容安全接口：<a href="https://cloud.tencent.com/product/ims" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" rel="noreferrer">获取密钥</a><br/>
屏蔽类型选不选都可以，会同时根据返回的建议结果进行屏蔽
</div>
</div>
<script>
function greenEngine(v){
	var map = {1:'#green_aliyun', 2:'#green_qcloud', 3:'#green_self'};
	for(var k in map){
		var on = (String(k) === String(v));
		/*
		 * 隐藏的那一块要连同里面的表单控件一起禁用（禁用的控件不会被提交）。
		 * 阿里云和腾讯云各有一个同名的 green_check_region，两个都提交的话后面那个
		 * 会把前面的覆盖掉——选着阿里云保存一次，区域就变成腾讯云列表里的值了。
		 */
		$(map[k]).toggle(on).find('input,select,textarea').prop('disabled', !on);
	}
}
$("select[name='green_check']").change(function(){ greenEngine($(this).val()); });
/*
 * 初始状态直接读 default 属性，不读 .val()，也不放进 $(function(){}) 里等 ready。
 *
 * 页面底部那段回填 select[default] 的循环排在本段之后，这里读 .val() 只会拿到第一个
 * 选项「关闭」。整页打开时靠 ready 排到底部之后能绕过去，但从侧栏点进来是动态换页，
 * 页面脚本是插完节点后逐段 eval 的，DOM 早就 ready，$(function(){}) 当场就执行，
 * 于是三块引擎配置全被判成不该显示——选着自建检测，下面的自建服务连接、图片判定、
 * 视频检测却一块都不出来，还连带被 disabled。读 default 属性就跟回填顺序无关了。
 */
greenEngine($("select[name='green_check']").attr('default') || '0');
$("select[name='green_check_porn']").change(function(){
	if($(this).val() == 1){
		$("#green_check_porn_").show();
	}else{
		$("#green_check_porn_").hide();
	}
});
$("select[name='green_check_terrorism']").change(function(){
	if($(this).val() == 1){
		$("#green_check_terrorism_").show();
	}else{
		$("#green_check_terrorism_").hide();
	}
});
$("select[name='green_video']").change(function(){
	$("#green_video_").toggle($(this).val() == 1);
});
//问一次检测服务自己：模型加载了几个、有没有 ffmpeg。
//视频检测依赖 ffmpeg，装没装从网站这边完全看不出来，只能问它
$.getJSON("./ajax.php?act=greenhealth", function(r){
	if(r.code != 0){
		$("#greenhealth").html('<b style="color:#d9534f">检测服务连不上</b>：'+(r.msg||''));
		return;
	}
	var s = '已加载 '+r.models+' 个模型，队列 '+r.queue;
	if(r.video){
		s += '，<b style="color:#5cb85c">视频检测可用</b>（ffmpeg '+r.ffmpeg+'）';
	}else{
		s += '，<b style="color:#d9534f">视频检测不可用</b>：服务端没有 ffmpeg，开了也不会工作';
	}
	$("#greenhealth").html(s);
}).fail(function(){
	$("#greenhealth").html('<span style="color:#d9534f">检测服务状态查不到</span>');
});
</script>
<?php
}
?>
    </div>
  </div>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
$('.appearance-card input[type="radio"]').on('change', function(){
	$('.appearance-card').removeClass('active');
	$(this).closest('.appearance-card').addClass('active');
});
$("#folder_open").on('change', function(){
	$("#folder_mode_row, #folder_uids_group").toggle($("#folder_open").val() === '1');
});

</script>
