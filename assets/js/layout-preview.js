/*
 * 后台布局编辑器的预览框里用（只在预览模式下加载，见 includes/theme_shell.php 的 theme_preview_banner_html）。
 * 预览模式下每张卡片前后有一对注释 <!--pan-block:节点id:卡片id--> … <!--/pan-block:节点id-->：
 *   - 点页面上的元素：找出包着它的最里层那张卡片，把节点 id 发给编辑器（父窗口），编辑器选中它；
 *   - 编辑器发 {panHighlight: 节点id} 过来：给那张卡片的范围画个框。
 * 页面上的链接和按钮在预览框里照常能点；按住 Alt 点才是「选卡片」，免得把正常操作吞掉。
 * 编辑器开着「点文字直接改」时，带 data-pan-edit 的文字点了就地编辑（见下面）。
 */
(function(){
	if(window.parent === window)return;
	var regions = [];   //[{id, block, nodes:[元素…]}]，外层在前
	function scan(){
		regions = [];
		var stack = [];
		var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT | NodeFilter.SHOW_ELEMENT, null, false);
		var n;
		while((n = walker.nextNode())){
			if(n.nodeType === 8){
				var t = n.nodeValue, m;
				if((m = /^pan-block:([A-Za-z0-9_\-]*):([a-z0-9_.]+)$/.exec(t))){
					var r = {id: m[1], block: m[2], nodes: []};
					stack.push(r);
					regions.push(r);
				}else if(/^\/pan-block:/.test(t)){
					stack.pop();
				}
			}else{
				//只记顶层元素：父元素已经在同一个区域里的不重复记
				for(var i = 0; i < stack.length; i++){
					var r2 = stack[i];
					var last = r2.nodes.length ? r2.nodes[r2.nodes.length - 1] : null;
					if(!last || !last.contains(n))r2.nodes.push(n);
				}
			}
		}
	}
	//包着 el 的全部卡片，最里层在前（regions 外层在前，倒过来）
	function regionsOf(el){
		var found = [];
		for(var i = 0; i < regions.length; i++){
			var r = regions[i];
			for(var j = 0; j < r.nodes.length; j++){
				if(r.nodes[j] === el || r.nodes[j].contains(el)){ found.unshift(r); break; }
			}
		}
		return found;
	}
	var box = null;
	function highlight(id){
		if(!box){
			box = document.createElement('div');
			box.style.cssText = 'position:absolute;z-index:2147482999;pointer-events:none;border:2px solid #f59e0b;background:rgba(245,158,11,.08);border-radius:4px;display:none';
			document.body.appendChild(box);
		}
		var r = null;
		for(var i = 0; i < regions.length; i++)if(regions[i].id === id)r = regions[i];
		if(!r || !r.nodes.length){ box.style.display = 'none'; return; }
		var top = Infinity, left = Infinity, right = -Infinity, bottom = -Infinity;
		r.nodes.forEach(function(el){
			if(!el.getBoundingClientRect)return;
			var b = el.getBoundingClientRect();
			if(!b.width && !b.height)return;
			top = Math.min(top, b.top); left = Math.min(left, b.left); right = Math.max(right, b.right); bottom = Math.max(bottom, b.bottom);
		});
		if(top === Infinity){ box.style.display = 'none'; return; }
		box.style.display = 'block';
		box.style.top = (top + window.pageYOffset - 2) + 'px';
		box.style.left = (left + window.pageXOffset - 2) + 'px';
		box.style.width = (right - left + 4) + 'px';
		box.style.height = (bottom - top + 4) + 'px';
		if(top < 0 || bottom > window.innerHeight)window.scrollTo(0, Math.max(0, top + window.pageYOffset - 80));
	}
	document.addEventListener('click', function(e){
		if(!e.altKey)return;
		var list = regionsOf(e.target);
		if(!list.length)return;
		e.preventDefault();
		e.stopPropagation();
		//整条链都发过去：外壳和页面是两份布局，编辑器挑它正在编辑的那份里最里层的一个；panBlock 是最里层，给只认一个的用
		window.parent.postMessage({panBlock: list[0].id, panBlockType: list[0].block, panChain: list.map(function(r){ return {id: r.id, block: r.block}; })}, '*');
	}, true);
	window.addEventListener('message', function(e){
		if(e.data && typeof e.data.panHighlight === 'string')highlight(e.data.panHighlight);
		if(e.data && typeof e.data.panEditMode === 'boolean')setEditMode(e.data.panEditMode);
	});

	/*
	 * 点文字直接改（DEC-20261026-002）：服务端在预览里把卡片的单行文字包成 <span data-pan-edit="节点:键">。
	 * 编辑器打开「点文字直接改」时，点这些文字就地变成可编辑（链接、按钮里的也一样，不会跳走）；
	 * 回车或点别处完成，Esc 放弃；改了的把新文字发给编辑器，由编辑器写回卡片配置、存草稿、刷新预览。
	 */
	var editMode = false, editing = null, original = '';
	function setEditMode(on){
		editMode = !!on;
		document.documentElement.classList.toggle('pan-edit-on', editMode);
		if(!document.getElementById('panEditStyle')){
			var st = document.createElement('style');
			st.id = 'panEditStyle';
			st.textContent = '.pan-edit-on [data-pan-edit]{outline:1px dashed rgba(59,130,246,.6);outline-offset:2px;cursor:text}'
				+ '.pan-edit-on [data-pan-edit]:hover{background:rgba(59,130,246,.10)}'
				+ '[data-pan-edit][contenteditable]{outline:2px solid #3b82f6!important;background:rgba(59,130,246,.12);cursor:text}';
			document.head.appendChild(st);
		}
	}
	function finish(save){
		var el = editing;
		if(!el)return;
		editing = null;
		el.removeAttribute('contenteditable');
		//只留纯文字：粘贴进来的格式、换行一律去掉
		var text = (el.textContent || '').replace(/[\r\n]+/g, ' ');
		if(!save){ el.textContent = original; return; }
		el.textContent = text;
		if(text === original)return;
		var p = el.getAttribute('data-pan-edit').split(':');
		window.parent.postMessage({panEdit: {id: p[0], key: p[1], value: text}}, '*');
	}
	document.addEventListener('click', function(e){
		if(!editMode || e.altKey || !e.target.closest)return;
		var el = e.target.closest('[data-pan-edit]');
		if(!el || el === editing)return;
		e.preventDefault();
		e.stopPropagation();
		finish(true);
		editing = el;
		original = el.textContent;
		el.setAttribute('contenteditable', 'true');
		el.focus();
		var r = document.createRange();
		r.selectNodeContents(el);
		var s = window.getSelection();
		s.removeAllRanges();
		s.addRange(r);
	}, true);
	document.addEventListener('keydown', function(e){
		if(!editing)return;
		if(e.key === 'Enter'){ e.preventDefault(); finish(true); }
		else if(e.key === 'Escape'){ e.preventDefault(); finish(false); }
	}, true);
	document.addEventListener('focusout', function(e){
		if(editing && e.target === editing)finish(true);
	}, true);
	//告诉编辑器：这一页加载好了、地址是什么、有哪些后台面板（后台页面才有，见 theme_render_layout）
	function ready(){
		scan();
		var panels = {};
		var tags = document.querySelectorAll('script.pan-panels');
		for(var i = 0; i < tags.length; i++){
			try{
				var p = JSON.parse(tags[i].textContent);
				for(var k in p)if(Object.prototype.hasOwnProperty.call(p, k))panels[k] = p[k];
			}catch(e){}
		}
		window.parent.postMessage({panPreviewReady: location.pathname + location.search, panPanels: panels}, '*');
	}
	if(document.readyState === 'loading')document.addEventListener('DOMContentLoaded', ready);
	else ready();
	window.addEventListener('load', scan);
})();
