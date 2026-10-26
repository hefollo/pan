/*
 * 后台布局编辑器（admin/layout.php，DEC-20261025-003）。
 *
 * 布局是一棵节点树：{id, block, config, hidden, children}。左边把树画成嵌套列表（SortableJS 拖动），
 * 选中一个节点在下面按卡片的 schema 出配置表单；右边 iframe 是带草稿的预览（ajax.php?act=layoutPreview 发的
 * 签名 cookie），预览页里的 assets/js/layout-preview.js 负责「Alt+点页面选卡片」和画高亮框。
 * 后台页面的内容是页面自己捕获的面板（admin.panel），可添加的面板 = 主题默认布局里出现过的 + 预览页报上来的。
 * 写进页面的文字一律走 jQuery .text()/.val()，不拼 HTML。
 */
(function($){
	var $root = $('#layoutEditor');
	if(!$root.length || $root.data('leInit'))return;
	$root.data('leInit', 1);
	var boot = {};
	try{ boot = JSON.parse($root.attr('data-boot') || '{}'); }catch(e){}

	var state = {
		theme: boot.theme || '',
		page: '',
		blocks: {},       //卡片 id => 定义
		blockList: [],
		nodes: {},        //节点 id => {id, block, config, hidden}（不含 children）
		tree: [],         //[{id, children:[…]}]
		defaultNodes: [],
		panels: {},       //后台面板编号 => 名称
		selected: '',
		dirty: false,
		previewUrl: null,
		loadId: 0
	};
	var sortables = [];

	function msg(text, ok){ layer.msg(text, {icon: ok ? 1 : 2, time: text.length > 14 ? 4000 : 2000}); }
	function post(act, data, done){
		var ii = layer.load(2, {shade:[0.1, '#fff']});
		$.ajax({type:'POST', url:'ajax.php?act=' + act, data:data, dataType:'json',
			success:function(d){ layer.close(ii); done(d || {code:-1, msg:'返回内容为空'}); },
			error:function(xhr){ layer.close(ii); done({code:-1, msg:'服务器错误（HTTP ' + xhr.status + '）'}); }
		});
	}
	function clone(o){ return JSON.parse(JSON.stringify(o)); }
	function newId(prefix){
		var id;
		do{ id = (prefix || 'n') + Math.random().toString(36).slice(2, 8); }while(state.nodes[id]);
		return id;
	}
	function blockName(bid){ return state.blocks[bid] ? state.blocks[bid].name : bid; }
	function isAdminPage(){ return state.page.indexOf('admin/') === 0; }

	/* ---------- 树 <-> 节点 ---------- */

	function loadNodes(list){
		state.nodes = {};
		state.tree = [];
		var walk = function(arr, out){
			$.each(arr || [], function(_, n){
				if(!n || !n.block)return;
				var id = n.id && !state.nodes[n.id] ? String(n.id) : newId();
				state.nodes[id] = {id:id, block:n.block, config:$.isPlainObject(n.config) ? clone(n.config) : {}, hidden:!!n.hidden};
				var item = {id:id, children:[]};
				walk(n.children, item.children);
				out.push(item);
			});
		};
		walk(list, state.tree);
	}
	function exportNodes(){
		var walk = function(arr){
			return $.map(arr, function(item){
				var n = state.nodes[item.id];
				var o = {id:n.id, block:n.block, config:n.config};
				if(n.hidden)o.hidden = 1;
				var def = state.blocks[n.block];
				if((def && def.container) || item.children.length)o.children = walk(item.children);
				return o;
			});
		};
		return walk(state.tree);
	}
	//拖动之后按 DOM 重建树
	function treeFromDom(){
		var walk = function($ul){
			var out = [];
			$ul.children('li.le-node').each(function(){
				var id = $(this).attr('data-id');
				if(!state.nodes[id])return;
				out.push({id:id, children:walk($(this).children('ul.le-list'))});
			});
			return out;
		};
		state.tree = walk($('#leTree').children('ul.le-list'));
	}
	function findItem(id, arr, parent){
		arr = arr || state.tree;
		for(var i = 0; i < arr.length; i++){
			if(arr[i].id === id)return {item:arr[i], list:arr, index:i, parent:parent || null};
			var r = findItem(id, arr[i].children, arr[i]);
			if(r)return r;
		}
		return null;
	}
	function usedPanels(){
		var used = {};
		$.each(state.nodes, function(_, n){ if(n.block === 'admin.panel' && n.config && n.config.panel)used[n.config.panel] = true; });
		return used;
	}

	/* ---------- 画树 ---------- */

	function renderTree(){
		$.each(sortables, function(_, s){ s.destroy(); });
		sortables = [];
		var $tree = $('#leTree').empty();
		var build = function(arr){
			var $ul = $('<ul class="le-list"></ul>');
			$.each(arr, function(_, item){
				var n = state.nodes[item.id], def = state.blocks[n.block];
				var $li = $('<li class="le-node"></li>').attr('data-id', n.id);
				var $row = $('<div class="le-row"></div>');
				if(n.id === state.selected)$row.addClass('le-selected');
				if(n.hidden)$row.addClass('le-hidden');
				if(!def)$row.addClass('le-unknown');
				$row.append('<span class="le-handle" title="拖动排序"><i class="fa fa-bars"></i></span>');
				if(def && def.container)$row.append('<i class="fa fa-folder-o le-ico" title="容器：可以放子卡片"></i>');
				var label = blockName(n.block);
				if(n.block === 'admin.panel'){
					var pid = n.config.panel || '';
					label = '面板：' + (state.panels[pid] || pid || '（未选）');
				}
				$('<a href="javascript:void(0)" class="le-name"></a>').text(label).attr('title', n.block + ' #' + n.id).appendTo($row);
				if(def && def.required)$row.append('<span class="label label-default le-req" title="页面靠它工作，不能删也不能隐藏">必需</span>');
				if(!def)$row.append('<span class="label label-warning">不适用于这个页面</span>');
				var $ops = $('<span class="le-ops"></span>');
				if(!(def && def.required)){
					$ops.append($('<a href="javascript:void(0)" data-op="hide"></a>').attr('title', n.hidden ? '显示' : '隐藏').append('<i class="fa ' + (n.hidden ? 'fa-eye-slash' : 'fa-eye') + '"></i>'));
					$ops.append('<a href="javascript:void(0)" data-op="copy" title="复制"><i class="fa fa-clone"></i></a>');
					$ops.append('<a href="javascript:void(0)" data-op="del" title="删除"><i class="fa fa-trash-o"></i></a>');
				}
				$row.append($ops);
				$li.append($row);
				if(def && def.container)$li.append(build(item.children));
				else if(item.children.length)$li.append(build(item.children));
				$ul.append($li);
			});
			return $ul;
		};
		var $top = build(state.tree);
		$tree.append($top);
		if(!state.tree.length)$tree.append('<p class="le-empty">这个页面现在没有卡片，用上面的「添加卡片」加。</p>');
		if(window.Sortable){
			$tree.find('ul.le-list').each(function(){
				sortables.push(Sortable.create(this, {
					group:'le', handle:'.le-handle', animation:150, fallbackOnBody:true, swapThreshold:0.65,
					//拖完按 DOM 重建树再重画；重画会销毁 Sortable 实例，放到事件处理完以后
					onEnd:function(){ treeFromDom(); setDirty(); setTimeout(renderTree, 0); }
				}));
			});
		}
		renderPalette();
	}

	/* ---------- 添加卡片 ---------- */

	function renderPalette(){
		var $p = $('#lePalette').empty();
		if(isAdminPage()){
			var used = usedPanels(), ids = [];
			$.each(state.panels, function(id){ ids.push(id); });
			if(ids.length){
				$p.append($('<li class="dropdown-header"></li>').text('这一页的面板'));
				$.each(ids, function(_, id){
					var $a = $('<a href="javascript:void(0)"></a>').attr('data-panel', id).text((state.panels[id] || id) + (used[id] ? '（已在页面上）' : ''));
					$p.append($('<li></li>').toggleClass('disabled', !!used[id]).append($a));
				});
			}
		}
		var groups = {}, order = [];
		$.each(state.blockList, function(_, b){
			if(b.id === 'admin.panel' || b.required)return;
			var g = b.group || '其它';
			if(!groups[g]){ groups[g] = []; order.push(g); }
			groups[g].push(b);
		});
		$.each(order, function(_, g){
			if($p.children().length)$p.append('<li class="divider"></li>');
			$p.append($('<li class="dropdown-header"></li>').text(g));
			$.each(groups[g], function(_, b){
				$p.append($('<li></li>').append($('<a href="javascript:void(0)"></a>').attr('data-block', b.id).attr('title', b.description || '').text(b.name)));
			});
		});
		if(!$p.children().length)$p.append('<li class="disabled"><a href="javascript:void(0)">这个页面没有可添加的卡片</a></li>');
	}
	//加到选中的容器里；选中的是普通卡片就加在它后面；没选中加在最外层末尾
	function addNode(bid, config){
		var id = newId();
		state.nodes[id] = {id:id, block:bid, config:config || {}, hidden:false};
		var item = {id:id, children:[]};
		var sel = state.selected ? findItem(state.selected) : null;
		if(sel){
			var def = state.blocks[state.nodes[sel.item.id].block];
			if(def && def.container)sel.item.children.push(item);
			else sel.list.splice(sel.index + 1, 0, item);
		}else{
			state.tree.push(item);
		}
		state.selected = id;
		setDirty();
		renderTree();
		renderConfig();
	}

	/* ---------- 配置表单 ---------- */

	function renderConfig(){
		var $c = $('#leConfig').empty();
		var n = state.nodes[state.selected];
		if(!n){ $c.append('<p class="le-empty">选中一张卡片，在这里改它的参数。</p>'); return; }
		var def = state.blocks[n.block];
		var $h = $('<div class="le-config-head"></div>');
		$('<b></b>').text(blockName(n.block)).appendTo($h);
		$('<code></code>').text(n.block).appendTo($h);
		$c.append($h);
		if(def && def.description)$c.append($('<p class="help-block"></p>').text(def.description));
		if(n.block === 'admin.panel'){
			var $sel = $('<select class="form-control input-sm"></select>');
			var cur = n.config.panel || '', seen = false;
			$.each(state.panels, function(id, label){
				$sel.append($('<option></option>').val(id).text((label || id) + '（' + id + '）'));
				if(id === cur)seen = true;
			});
			if(!seen)$sel.prepend($('<option></option>').val(cur).text(cur ? cur + '（这一页没有这个面板）' : '（未选）'));
			$sel.val(cur).on('change', function(){ n.config.panel = $(this).val(); setDirty(); renderTree(); });
			$c.append($('<div class="form-group"></div>').append('<label>放哪个面板</label>').append($sel));
			return;
		}
		if(!def || !def.schema || !def.schema.length){ $c.append('<p class="le-empty">这张卡片没有可调的参数。</p>'); return; }
		$.each(def.schema, function(_, f){
			var v = Object.prototype.hasOwnProperty.call(n.config, f.key) ? n.config[f.key] : f['default'];
			var $g = $('<div class="form-group"></div>');
			var $in;
			if(f.type === 'code'){
				$g.append($('<label></label>').text(f.label));
				$g.append(codeField(f, n, v));
				if(f.help)$g.append($('<span class="help-block"></span>').text(f.help));
				$c.append($g);
				return;
			}
			if(f.type === 'menu'){
				$g.append($('<label></label>').text(f.label));
				$g.append(menuField(f, n, v));
				if(f.help)$g.append($('<span class="help-block"></span>').text(f.help));
				$c.append($g);
				return;
			}
			if(f.type === 'switch'){
				$in = $('<input type="checkbox">').prop('checked', !!(v && v !== '0'));
				$g.append($('<label class="le-switch"></label>').append($in).append(document.createTextNode(' ' + f.label)));
				$in.on('change', function(){ n.config[f.key] = this.checked ? 1 : 0; setDirty(); });
			}else{
				$g.append($('<label></label>').text(f.label));
				if(f.type === 'select'){
					$in = $('<select class="form-control input-sm"></select>');
					$.each(f.options || {}, function(k, label){ $in.append($('<option></option>').val(k).text(label)); });
					$in.val(String(v));
				}else if(f.type === 'textarea' || f.type === 'html' || f.type === 'lines'){
					$in = $('<textarea class="form-control input-sm" rows="4"></textarea>').val(v == null ? '' : String(v));
					if(f.type === 'html')$in.addClass('le-code');
				}else if(f.type === 'number'){
					$in = $('<input type="number" class="form-control input-sm">').val(v);
					if(f.min !== null && f.min !== undefined)$in.attr('min', f.min);
					if(f.max !== null && f.max !== undefined)$in.attr('max', f.max);
				}else if(f.type === 'color'){
					$in = $('<input type="text" class="form-control input-sm" placeholder="#rrggbb">').val(v == null ? '' : String(v));
				}else{
					$in = $('<input type="text" class="form-control input-sm">').val(v == null ? '' : String(v));
				}
				$in.on('input change', function(){
					var val = $(this).val();
					n.config[f.key] = f.type === 'number' ? (val === '' ? f['default'] : Number(val)) : val;
					setDirty();
				});
				$g.append($in);
			}
			if(f.help)$g.append($('<span class="help-block"></span>').text(f.help));
			$c.append($g);
		});
	}

	/*
	 * 代码框（配置类型 code，「代码」卡片用）：有 CodeMirror 就用它（高亮、行号、自动缩进、补全闭合标签），
	 * 没加载上就是普通的等宽文本框。改动照常走 setDirty，开着实时预览时停手 0.5 秒右边就刷新。
	 */
	function codeField(f, n, v){
		var $box = $('<div class="le-code-box"></div>');
		var $ta = $('<textarea class="form-control input-sm le-code" rows="10" spellcheck="false"></textarea>').val(v == null ? '' : String(v)).appendTo($box);
		var set = function(val){ n.config[f.key] = val; setDirty(); };
		if(!window.CodeMirror){
			$ta.on('input', function(){ set($(this).val()); });
			return $box;
		}
		var mode = {html:'htmlmixed', css:'css', js:'javascript'}[f.lang] || 'htmlmixed';
		//等表单插进页面再建编辑器，不然量不出尺寸
		setTimeout(function(){
			if(!$ta.closest('body').length)return;
			var cm = CodeMirror.fromTextArea($ta[0], {mode:mode, lineNumbers:true, lineWrapping:true, tabSize:2, indentUnit:2, indentWithTabs:false, matchBrackets:true, autoCloseTags:mode === 'htmlmixed', viewportMargin:Infinity});
			cm.on('change', function(){ set(cm.getValue()); });
			$box.data('cm', cm);
		}, 0);
		return $box;
	}

	/*
	 * 导航菜单配置（类型 menu，导航栏卡片用，DEC-20261026-001）：一行一个菜单项，可拖动排序、隐藏、改名、换图标，
	 * 可添加自定义页面和链接。存的是 JSON 字符串；和默认菜单（内置项按原顺序、没改过）一样时存空串，输出和原来一致。
	 */
	function menuField(f, n, v){
		var builtins = f.builtins || [], pages = f.pages || [];
		var bmap = {}, pmap = {};
		$.each(builtins, function(_, b){ bmap[b.key] = b; });
		$.each(pages, function(_, p){ pmap[p.slug] = p; });
		var list = [];
		try{ list = v ? JSON.parse(v) : []; }catch(e){ list = []; }
		if(!$.isArray(list) || !list.length)list = $.map(builtins, function(b){ return {type:'builtin', key:b.key}; });
		var $box = $('<div class="le-menu"></div>');
		var $ul = $('<ul class="le-menu-list"></ul>').appendTo($box);
		function defaultJson(){ return JSON.stringify($.map(builtins, function(b){ return {type:'builtin', key:b.key}; })); }
		function commit(redraw){
			var clean = $.map(list, function(it){
				var o = {type:it.type};
				if(it.type === 'builtin')o.key = it.key;
				if(it.type === 'page')o.slug = it.slug;
				if(it.type === 'link'){ o.href = it.href || ''; if(it.blank)o.blank = 1; }
				if(it.label)o.label = it.label;
				if(it.icon)o.icon = it.icon;
				if(it.hidden)o.hidden = 1;
				return o;
			});
			var json = JSON.stringify(clean);
			n.config[f.key] = json === defaultJson() ? '' : json;
			setDirty();
			if(redraw)draw();
		}
		function draw(){
			$ul.empty();
			$.each(list, function(i, it){
				var $li = $('<li class="le-menu-item"></li>').attr('data-i', i).toggleClass('le-menu-hidden', !!it.hidden);
				$li.append('<span class="le-handle" title="拖动排序"><i class="fa fa-bars"></i></span>');
				var kind, deflabel, deficon;
				if(it.type === 'builtin'){ kind = '内置'; deflabel = bmap[it.key] ? bmap[it.key].label : it.key; deficon = bmap[it.key] ? bmap[it.key].icon : ''; }
				else if(it.type === 'page'){ kind = '页面'; deflabel = pmap[it.slug] ? pmap[it.slug].title : it.slug + '（已删除）'; deficon = 'fa-file-text-o'; }
				else{ kind = '链接'; deflabel = '链接'; deficon = 'fa-link'; }
				$('<span class="label label-default le-menu-kind"></span>').text(kind).appendTo($li);
				var $body = $('<span class="le-menu-body"></span>').appendTo($li);
				$('<input type="text" class="form-control input-sm le-menu-label" maxlength="20">').attr('placeholder', deflabel).val(it.label || '').on('input', function(){ it.label = $.trim($(this).val()); commit(false); }).appendTo($body);
				$('<input type="text" class="form-control input-sm le-menu-icon" maxlength="33" title="Font Awesome 4 图标名，比如 fa-home">').attr('placeholder', deficon).val(it.icon || '').on('input', function(){ var x = $.trim($(this).val()); it.icon = /^fa-[a-z0-9-]{1,30}$/.test(x) ? x : ''; commit(false); }).appendTo($body);
				if(it.type === 'link'){
					$('<input type="text" class="form-control input-sm le-menu-href" placeholder="https://… 或 ./xxx.php">').val(it.href || '').on('input', function(){ it.href = $.trim($(this).val()); commit(false); }).appendTo($body);
					$('<label class="le-menu-blank"><input type="checkbox"> 新窗口</label>').find('input').prop('checked', !!it.blank).on('change', function(){ it.blank = this.checked ? 1 : 0; commit(false); }).end().appendTo($body);
				}
				var $ops = $('<span class="le-ops"></span>').appendTo($li);
				$('<a href="javascript:void(0)"></a>').attr('title', it.hidden ? '显示' : '隐藏').append('<i class="fa ' + (it.hidden ? 'fa-eye-slash' : 'fa-eye') + '"></i>').on('click', function(){ it.hidden = it.hidden ? 0 : 1; commit(true); }).appendTo($ops);
				if(it.type !== 'builtin')$('<a href="javascript:void(0)" title="删除"><i class="fa fa-trash-o"></i></a>').on('click', function(){ list.splice(i, 1); commit(true); }).appendTo($ops);
				$ul.append($li);
			});
			//添加：自定义页面、链接、被删掉的内置项
			$add.empty().append('<option value="">＋ 添加菜单项…</option>');
			var have = {};
			$.each(list, function(_, it){ if(it.type === 'builtin')have['b:' + it.key] = 1; if(it.type === 'page')have['p:' + it.slug] = 1; });
			$.each(pages, function(_, p){ if(!have['p:' + p.slug])$add.append($('<option></option>').val('p:' + p.slug).text('页面：' + p.title)); });
			$.each(builtins, function(_, b){ if(!have['b:' + b.key])$add.append($('<option></option>').val('b:' + b.key).text('内置：' + b.label)); });
			$add.append('<option value="link">链接（站内或站外地址）</option>');
			if(!pages.length)$add.append('<option value="" disabled>（还没有自定义页面：先点上面的「新建页面」）</option>');
		}
		var $add = $('<select class="form-control input-sm le-menu-add"></select>').appendTo($box).on('change', function(){
			var x = $(this).val();
			if(!x)return;
			if(x === 'link')list.push({type:'link', href:'', label:'链接'});
			else if(x.indexOf('p:') === 0)list.unshift({type:'page', slug:x.slice(2)});   //页面默认加在最前面（比如「首页」排在「文件列表」上面）
			else list.push({type:'builtin', key:x.slice(2)});
			commit(true);
		});
		$('<a href="javascript:void(0)" class="le-menu-reset">恢复默认菜单</a>').on('click', function(){
			list = $.map(builtins, function(b){ return {type:'builtin', key:b.key}; });
			commit(true);
		}).appendTo($box);
		draw();
		if(window.Sortable)Sortable.create($ul[0], {handle:'.le-handle', animation:150, onEnd:function(e){
			var moved = list.splice(e.oldIndex, 1)[0];
			list.splice(e.newIndex, 0, moved);
			commit(true);
		}});
		return $box;
	}

	function select(id, fromPreview){
		if(!state.nodes[id])return;
		state.selected = id;
		$('#leTree .le-row').removeClass('le-selected');
		var $row = $('#leTree li.le-node[data-id="' + id + '"]').children('.le-row').addClass('le-selected');
		renderConfig();
		if(fromPreview && $row.length && $row[0].scrollIntoView)$row[0].scrollIntoView({block:'nearest'});
		if(!fromPreview)highlight(id);
	}
	function highlight(id){
		var w = $('#lePreview')[0].contentWindow;
		try{ if(w)w.postMessage({panHighlight:id}, '*'); }catch(e){}
	}

	/* ---------- 状态 / 读写 ---------- */

	function setDirty(){
		state.dirty = true;
		$('#leStatus').text(live() ? '正在更新预览…' : '有没保存的修改').addClass('le-dirty');
		if(live())scheduleLive();
	}

	/*
	 * 实时预览：改动停下 0.5 秒（打字时等停手）就把草稿存起来、在后台刷新预览。
	 * 存的是草稿，访客看到的还是已发布的布局，要点「发布」才生效。同一时间只发一个保存请求，期间又改了就等它回来再存一次。
	 */
	function live(){ return $('#leLive').prop('checked'); }
	function scheduleLive(delay){
		clearTimeout(state.liveTimer);
		state.liveTimer = setTimeout(liveSave, delay || 500);
	}
	function stopLive(){
		clearTimeout(state.liveTimer);
		state.livePending = false;
	}
	function liveSave(){
		if(state.liveBusy){ state.livePending = true; return; }
		state.liveBusy = true;
		state.livePending = false;
		var theme = state.theme, page = state.page;
		$.ajax({type:'POST', url:'ajax.php?act=layoutSave', dataType:'json',
			data:{theme:theme, page:page, 'do':'draft', nodes:JSON.stringify(exportNodes())},
			success:function(d){
				if(theme !== state.theme || page !== state.page)return;
				if(!d || d.code !== 0){ $('#leStatus').text((d && d.msg) || '草稿没存上').addClass('le-dirty'); return; }
				if(!state.livePending){
					state.dirty = false;
					$('#leStatus').text('草稿已自动保存，还没发布 · ' + new Date().toTimeString().slice(0, 8)).removeClass('le-dirty');
				}
				reloadPreview(true);
			},
			error:function(xhr){
				if(theme === state.theme && page === state.page)$('#leStatus').text('自动保存失败（HTTP ' + xhr.status + '），改动还在，可以点「保存草稿并预览」重试').addClass('le-dirty');
			},
			complete:function(){
				state.liveBusy = false;
				if(state.livePending)scheduleLive(50);
			}
		});
	}
	function setStatus(d){
		state.dirty = false;
		var t;
		if(d.draft)t = '正在编辑草稿（还没发布）';
		else if(d.published)t = '已发布的布局';
		else t = '主题默认布局（没改过）';
		if(d.updated_at)t += ' · ' + d.updated_at;
		$('#leStatus').text(t).removeClass('le-dirty');
	}
	function collectDefaultPanels(list){
		$.each(list || [], function(_, n){
			if(n.block === 'admin.panel' && n.config && n.config.panel && !state.panels[n.config.panel])state.panels[n.config.panel] = '';
			collectDefaultPanels(n.children);
		});
	}
	function loadPage(){
		stopLive();
		var my = ++state.loadId;
		$.ajax({url:'ajax.php?act=layoutGet', data:{theme:state.theme, page:state.page}, dataType:'json', cache:false,
			success:function(d){
				if(my !== state.loadId)return;
				if(!d || d.code !== 0){ msg(d && d.msg ? d.msg : '读取失败'); return; }
				state.blocks = {};
				state.blockList = d.blocks || [];
				$.each(state.blockList, function(_, b){ state.blocks[b.id] = b; });
				state.defaultNodes = d['default'] || [];
				state.panels = {};
				collectDefaultPanels(state.defaultNodes);
				loadNodes(d.draft || d.published || d['default'] || []);
				collectDefaultPanels(exportNodes());
				state.selected = '';
				//整页就一张「代码」卡片（空白代码页）：直接选中它，打开就能写
				if(state.tree.length === 1 && state.nodes[state.tree[0].id].block === 'core.code')state.selected = state.tree[0].id;
				setStatus(d);
				renderTree();
				renderConfig();
				state.previewUrl = d.preview_url;
				reloadPreview();
				if(state.afterLoad){ var after = state.afterLoad; state.afterLoad = null; after(); }
			},
			error:function(xhr){ if(my === state.loadId)msg('读取失败（HTTP ' + xhr.status + '）'); }
		});
	}
	function save(action, done){
		//等正在路上的自动保存回来，免得它在发布 / 丢弃之后才落库，又冒出一份草稿
		if(state.liveBusy){ setTimeout(function(){ save(action, done); }, 100); return; }
		var data = {theme:state.theme, page:state.page, 'do':action};
		if(action === 'draft' || action === 'publish')data.nodes = JSON.stringify(exportNodes());
		post('layoutSave', data, function(d){
			if(d.code !== 0){ msg(d.msg || '保存失败'); return; }
			msg(d.msg, true);
			if(done)done();
		});
	}

	/* ---------- 预览 ---------- */

	function previewOn(done){
		post('layoutPreview', {theme:state.theme}, function(d){
			if(d.code !== 0){ msg(d.msg || '打不开预览'); return; }
			state.previewAt = Date.now();
			if(done)done();
		});
	}
	/*
	 * 预览凭证（cookie pan_preview）半小时过期。编辑器开久了凭证过期，预览框就成了访客看到的样子：
	 * 不显示草稿、没有定位脚本，选中卡片也不画框。所以：开着编辑器每 10 分钟悄悄续一次；
	 * 刷新预览前凭证超过 20 分钟先续；预览框加载完发现不是预览模式（过期、或在别处点了「退出预览」），续一次再刷新。
	 */
	function previewToken(done){
		$.ajax({type:'POST', url:'ajax.php?act=layoutPreview', data:{theme:state.theme}, dataType:'json',
			success:function(d){ if(d && d.code === 0)state.previewAt = Date.now(); },
			complete:function(){ if(done)done(); }
		});
	}
	setInterval(function(){ if(state.previewAt)previewToken(); }, 10 * 60 * 1000);
	//isRetry：这次加载本身就是续凭证之后的重试，再不对也不重试了（避免来回刷新）
	function checkPreviewMode(frame, isRetry){
		$(frame).one('load', function(){
			var ok = true;
			try{
				var d = frame.contentDocument;
				ok = !d || !d.body || d.location.href === 'about:blank' || !!d.getElementById('panPreviewBar');
			}catch(e){}
			if(ok || isRetry)return;
			previewToken(function(){ reloadPreview(false, true); });
		});
	}
	//soft 为 true（实时预览）时新页面先在下面一层加载、滚到原来的位置再换上来，不闪白屏也不跳回顶部；fresh 为 true 表示凭证刚续过
	function reloadPreview(soft, fresh){
		if(!fresh && state.previewUrl !== null && state.previewUrl !== undefined && state.previewAt && Date.now() - state.previewAt > 20 * 60 * 1000){
			previewToken(function(){ reloadPreview(soft, true); });
			return;
		}
		var $wrap = $('#lePreviewWrap'), $f = $('#lePreview'), $e = $('#lePreviewEmpty');
		$wrap.find('iframe.le-buffer').remove();
		if(state.previewUrl === null || state.previewUrl === undefined){
			$f.hide().attr('src', 'about:blank');
			$e.show().text(state.page === 'share' ? '文件夹分享页要带分享链接才能打开：保存草稿后，自己打开一个分享链接看效果。' : (state.page === 'file' ? '还没有公开的文件，文件页没法预览。' : '这个页面要特定条件才能打开（比如登录后才看得到），保存草稿后自己去前台看效果。'));
			$('#lePreviewUrl').text('');
			$('#lePreviewOpen').hide();
			return;
		}
		var url = (boot.site_base || '../') + state.previewUrl;
		var src = url + (url.indexOf('?') === -1 ? '?' : '&') + '_lp=' + Date.now();
		$e.hide();
		$('#lePreviewUrl').text('/' + state.previewUrl);
		$('#lePreviewOpen').show().attr('href', url);
		if(!soft || !$f.is(':visible') || !$f.attr('src') || $f.attr('src') === 'about:blank'){
			checkPreviewMode($f[0], !!fresh);
			$f.show().attr('src', src);
			return;
		}
		var y = 0;
		try{ y = $f[0].contentWindow.pageYOffset || 0; }catch(err){}
		var $nf = $('<iframe class="le-buffer" title="预览"></iframe>');
		//只在第一次加载完时换上来（换上来以后它就是 #lePreview，之后手动刷新再触发 load 不能再走这里）
		$nf.one('load', function(){
			if(!$nf.parent().length)return;
			try{ $nf[0].contentWindow.scrollTo(0, y); }catch(err){}
			$('#lePreview').remove();
			$nf.removeClass('le-buffer').attr('id', 'lePreview');
			if(state.selected)highlight(state.selected);
		});
		checkPreviewMode($nf[0], !!fresh);
		$nf.attr('src', src).appendTo($wrap);
	}
	window.addEventListener('message', function(e){
		var from = false;
		$('#lePreviewWrap iframe').each(function(){ if(this.contentWindow === e.source)from = true; });
		if(!from || !e.data)return;
		if(typeof e.data.panBlock === 'string'){
			var chain = $.isArray(e.data.panChain) ? e.data.panChain : [{id:e.data.panBlock, block:e.data.panBlockType}];
			var hit = null;
			$.each(chain, function(_, r){
				//外壳的「页面内容」占位包着整页，不算选中它
				if(r && state.nodes[r.id] && state.nodes[r.id].block === r.block){ hit = r.id; return false; }
			});
			if(hit){
				select(hit, true);
			}else if(chain.length && chain[0] && /^shell\.|^admin\.navbar$/.test(String(chain[0].block))){
				msg('这是公共外壳里的卡片，在页面下拉框里选「' + (isAdminPage() ? '后台公共外壳' : '前台公共外壳') + '」再编辑');
			}
		}
		if(e.data.panPanels && typeof e.data.panPanels === 'object'){
			var changed = false;
			$.each(e.data.panPanels, function(id, label){
				if(typeof id !== 'string' || typeof label !== 'string')return;
				if(state.panels[id] !== label){ state.panels[id] = label; changed = true; }
			});
			if(changed && isAdminPage()){ renderTree(); renderConfig(); }
		}
		if(state.selected)highlight(state.selected);
	});

	/* ---------- 导入导出 ---------- */

	function exportFile(){
		var blob = new Blob([JSON.stringify({nodes:exportNodes()}, null, 4) + '\n'], {type:'application/json'});
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = (state.theme + '-' + state.page.replace(/\//g, '-')) + '.json';
		document.body.appendChild(a);
		a.click();
		setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 1000);
	}
	function importFile(file){
		if(!file)return;
		if(file.size > 1024 * 1024){ msg('文件太大'); return; }
		var r = new FileReader();
		r.onload = function(){
			var data;
			try{ data = JSON.parse(String(r.result).replace(/^﻿/, '')); }catch(e){ msg('不是合法的 JSON'); return; }
			var list = $.isArray(data) ? data : (data && $.isArray(data.nodes) ? data.nodes : null);
			if(!list){ msg('JSON 里没有布局（要是节点数组，或 {"nodes": [...]}）'); return; }
			loadNodes(list);
			state.selected = '';
			setDirty();
			renderTree();
			renderConfig();
			msg('已导入，检查一下再保存草稿或发布', true);
		};
		r.readAsText(file);
	}

	/* ---------- 事件 ---------- */

	function confirmLeave(go){
		if(!state.dirty){ go(); return; }
		layer.confirm('有没保存的修改，确定丢掉吗？', {icon:3, btn:['丢掉', '取消']}, function(i){ layer.close(i); go(); });
	}
	$('#leTree').on('click', '.le-name', function(){
		select($(this).closest('li.le-node').attr('data-id'));
	}).on('click', '.le-ops a', function(){
		var id = $(this).closest('li.le-node').attr('data-id'), n = state.nodes[id], op = $(this).attr('data-op');
		if(!n)return;
		if(op === 'hide'){
			n.hidden = !n.hidden;
		}else if(op === 'copy'){
			var f = findItem(id);
			var dup = function(item){
				var src = state.nodes[item.id], nid = newId();
				state.nodes[nid] = {id:nid, block:src.block, config:clone(src.config), hidden:src.hidden};
				return {id:nid, children:$.map(item.children, dup)};
			};
			var copy = dup(f.item);
			f.list.splice(f.index + 1, 0, copy);
			state.selected = copy.id;
		}else if(op === 'del'){
			var hasReq = false;
			var chk = function(item){ var d = state.blocks[state.nodes[item.id].block]; if(d && d.required)hasReq = true; $.each(item.children, function(_, c){ chk(c); }); };
			var fd = findItem(id);
			chk(fd.item);
			if(hasReq){ msg('里面有页面必需的卡片，不能整个删掉；先把它拖出来'); return; }
			var drop = function(item){ delete state.nodes[item.id]; $.each(item.children, function(_, c){ drop(c); }); };
			drop(fd.item);
			fd.list.splice(fd.index, 1);
			if(!state.nodes[state.selected])state.selected = '';
		}
		setDirty();
		renderTree();
		renderConfig();
	});
	$('#lePalette').on('click', 'a[data-block]', function(){
		addNode($(this).attr('data-block'));
	}).on('click', 'a[data-panel]', function(){
		if($(this).parent().hasClass('disabled'))return false;
		addNode('admin.panel', {panel:$(this).attr('data-panel')});
	});
	$('#leDraft').on('click', function(){ stopLive(); save('draft', function(){ state.dirty = false; loadPage(); }); });
	try{ if(localStorage.getItem('layoutEditorLive') === '0')$('#leLive').prop('checked', false); }catch(e){}
	$('#leLive').on('change', function(){
		try{ localStorage.setItem('layoutEditorLive', this.checked ? '1' : '0'); }catch(e){}
		if(this.checked && state.dirty)scheduleLive(10);
		if(!this.checked)stopLive();
	});
	$('#lePublish').on('click', function(){
		layer.confirm('发布后访客立刻看到这个布局，确定吗？', {icon:3, btn:['发布', '取消']}, function(i){
			layer.close(i);
			stopLive();
			save('publish', function(){ state.dirty = false; loadPage(); });
		});
	});
	$('#leDiscard').on('click', function(){
		layer.confirm('丢掉草稿，回到已发布的布局？', {icon:3, btn:['丢掉', '取消']}, function(i){
			layer.close(i);
			stopLive();
			save('discard', function(){ state.dirty = false; loadPage(); });
		});
	});
	$('#leReset').on('click', function(){
		layer.confirm('删掉这个页面的草稿和已发布布局，恢复成主题默认？访客会立刻看到默认布局。', {icon:3, btn:['恢复默认', '取消']}, function(i){
			layer.close(i);
			stopLive();
			save('reset', function(){ state.dirty = false; loadPage(); });
		});
	});
	$('#leExport').on('click', exportFile);
	$('#leImport').on('click', function(){ $('#leImportFile').val('').trigger('click'); });
	$('#leImportFile').on('change', function(){ importFile(this.files && this.files[0]); });
	$('#lePreviewReload').on('click', reloadPreview);
	$('#lePreviewOff').on('click', function(){
		post('layoutPreview', {off:1}, function(){ msg('已退出预览模式，其它页面不再显示草稿', true); $('#lePreview').attr('src', 'about:blank'); });
	});
	$('#leTheme').on('change', function(){
		var v = $(this).val();
		confirmLeave(function(){ state.theme = v; previewOn(loadPage); });
		if(state.dirty)$(this).val(state.theme);
	});
	$('#lePage').on('change', function(){
		var v = $(this).val();
		confirmLeave(function(){ state.page = v; $('#lePage').val(v); pageOps(); loadPage(); });
		if(state.dirty)$(this).val(state.page);
	});

	/* ---------- 自定义页面（DEC-20261026-001） ---------- */

	state.pageList = boot.pages || [];
	state.home = boot.home || '';
	function pageItem(id){
		var hit = null;
		$.each(state.pageList, function(_, p){ if(p.id === id){ hit = p; return false; } });
		return hit;
	}
	//页面下拉框：前台固定页面、自定义页面、后台页面三组
	function renderPageSelect(){
		var $s = $('#lePage').empty();
		var groups = {front:$('<optgroup label="前台"></optgroup>'), custom:$('<optgroup label="自定义页面"></optgroup>'), admin:$('<optgroup label="后台"></optgroup>')};
		$.each(state.pageList, function(_, p){
			var g = p.slug ? 'custom' : (p.scope === 'admin' ? 'admin' : 'front');
			var name = p.slug ? p.title + '（page.php?p=' + p.slug + '）' + (p.slug === state.home ? ' · 网站入口' : '') : p.name;
			groups[g].append($('<option></option>').val(p.id).text(name));
		});
		$s.append(groups.front);
		if(groups.custom.children().length)$s.append(groups.custom);
		$s.append(groups.admin);
		$s.val(state.page);
	}
	//当前选的是自定义页面才有「页面设置」「删除页面」
	function pageOps(){
		var p = pageItem(state.page);
		$('#lePageEdit, #lePageDel').toggle(!!(p && p.slug));
		//自定义页面是网站内容，所有外观共用一份；上面选的主题只决定预览用哪套外观
		$('#lePageHint').toggle(!!(p && p.slug));
	}
	function pageDialog(p){
		var create = !p;
		var html = '<div class="le-page-form" style="padding:16px 20px">'
			+ '<div class="form-group"><label>标题</label><input type="text" class="form-control" name="title" maxlength="40" placeholder="比如：首页、关于我们"></div>'
			+ '<div class="form-group"><label>地址名</label><div class="input-group"><span class="input-group-addon">page.php?p=</span><input type="text" class="form-control" name="slug" maxlength="31" placeholder="home"></div>'
			+ '<span class="help-block">小写字母、数字和短横线。建好以后不能改（菜单和别人收藏的链接都指着它）。</span></div>'
			+ (create ? '<div class="form-group"><label>从哪开始</label>'
				+ '<div class="radio" style="margin-top:0"><label><input type="radio" name="start" value="cards" checked> 卡片：先放一张横幅，再往里加卡片</label></div>'
				+ '<div class="radio"><label><input type="radio" name="start" value="code"> 空白代码页：一张「代码」卡片，直接写 HTML、CSS、JS</label></div></div>' : '')
			+ '<div class="checkbox"><label><input type="checkbox" name="shell" checked> 显示网站外壳（导航、页脚和外观样式）；不勾的话整页只有你写的内容</label></div>'
			+ '<div class="checkbox"><label><input type="checkbox" name="home"> 设为网站入口：打开网站根目录 / 时显示这个页面（文件列表仍可从菜单「文件列表」进入）</label></div>'
			+ '</div>';
		layer.open({type:1, title:create ? '新建页面' : '页面设置', area:['480px', 'auto'], content:html, btn:[create ? '新建' : '保存', '取消'],
			success:function($l){
				if(!create){
					$l.find('[name=title]').val(p.title);
					$l.find('[name=slug]').val(p.slug).prop('readonly', true);
					$l.find('[name=home]').prop('checked', p.slug === state.home);
					$l.find('[name=shell]').prop('checked', p.shell !== false);
				}
				setTimeout(function(){ $l.find('[name=title]').focus(); }, 50);
			},
			yes:function(i, $l){
				var data = {title:$.trim($l.find('[name=title]').val()), slug:$.trim($l.find('[name=slug]').val()).toLowerCase(), home:$l.find('[name=home]').prop('checked') ? 1 : 0, shell:$l.find('[name=shell]').prop('checked') ? 1 : 0};
				var start = create ? $l.find('[name=start]:checked').val() : '';
				if(create)data.create = 1;
				//空白代码页：页面加载完以后把布局换成一张「代码」卡片并存成草稿
				if(start === 'code')state.afterLoad = function(){
					loadNodes([{id:'code', block:'core.code', config:{html:'<div class="container">\n  <h1>' + $('<div>').text(data.title).html() + '</h1>\n  <p>在这里直接写 HTML。</p>\n</div>\n', css:'', js:''}}]);
					state.selected = 'code';
					renderTree();
					renderConfig();
					save('draft', function(){ state.dirty = false; $('#leStatus').text('草稿已保存，还没发布').removeClass('le-dirty'); reloadPreview(true); });
				};
				post('pageSave', data, function(d){
					if(d.code !== 0){ msg(d.msg || '保存失败'); return; }
					layer.close(i);
					msg(d.msg, true);
					state.pageList = d.pages || state.pageList;
					state.home = d.home || '';
					var go = function(){ state.page = 'page-' + data.slug; renderPageSelect(); pageOps(); loadPage(); };
					if(create)confirmLeave(go);
					else{ renderPageSelect(); pageOps(); }
				});
			}
		});
	}
	$('#lePageNew').on('click', function(){ pageDialog(null); });
	//宽屏编辑：左边编辑区放宽（写代码时），记在浏览器里
	function wide(on){
		$root.toggleClass('le-wide', on);
		$('#leWide').toggleClass('active', on);
		try{ localStorage.setItem('layoutEditorWide', on ? '1' : '0'); }catch(e){}
		$('.le-code-box').each(function(){ var cm = $(this).data('cm'); if(cm)cm.refresh(); });
	}
	$('#leWide').on('click', function(){ wide(!$root.hasClass('le-wide')); });
	try{ if(localStorage.getItem('layoutEditorWide') === '1')wide(true); }catch(e){}
	$('#lePageEdit').on('click', function(){ var p = pageItem(state.page); if(p && p.slug)pageDialog(p); });
	$('#lePageDel').on('click', function(){
		var p = pageItem(state.page);
		if(!p || !p.slug)return;
		layer.confirm('删除页面「' + $('<div>').text(p.title).html() + '」？它在所有主题里的布局都会删掉，菜单里指向它的项不再显示。', {icon:3, btn:['删除', '取消']}, function(i){
			layer.close(i);
			stopLive();
			post('pageDelete', {slug:p.slug}, function(d){
				if(d.code !== 0){ msg(d.msg || '删除失败'); return; }
				msg(d.msg, true);
				state.pageList = d.pages || state.pageList;
				state.home = d.home || '';
				state.dirty = false;
				state.page = 'index';
				renderPageSelect();
				pageOps();
				loadPage();
			});
		});
	});
	window.addEventListener('beforeunload', function(e){
		if(state.dirty){ e.preventDefault(); e.returnValue = ''; }
	});

	/* ---------- 起步 ---------- */

	$.each(boot.themes || [], function(_, t){ $('#leTheme').append($('<option></option>').val(t.id).text(t.name + (t.active ? '（当前外观）' : ''))); });
	$('#leTheme').val(state.theme);
	var m = /[?&]page=([^&]+)/.exec(location.search);
	state.page = m && pageItem(decodeURIComponent(m[1])) ? decodeURIComponent(m[1]) : 'index';
	renderPageSelect();
	pageOps();
	if(!window.Sortable)$('#leTree').before('<div class="alert alert-warning">拖动组件（SortableJS）没加载上，只能用添加/删除，不能拖动排序。</div>');
	previewOn(loadPage);
})(jQuery);
