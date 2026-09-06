/*
 * 蓝白工作台风的三个交互：
 *   1. Ctrl/⌘ + K 聚焦顶部搜索框（顶栏上那个 kbd 标签写的就是它）
 *   2. 列表上方的排序下拉，选完直接带 sort 参数刷新
 *   3. 列表 / 网格视图切换，选择存在 localStorage 里，下次进来还是上次那个
 * 没开 JS 时：搜索框照样能用（就是没快捷键）、排序下拉不动、列表保持默认的表格视图。
 */
(function(){
	'use strict';

	//—— 1. Ctrl K / ⌘K 聚焦搜索
	var search = document.getElementById('studioSearch');
	if(search){
		document.addEventListener('keydown', function(e){
			if((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')){
				e.preventDefault();
				search.focus();
				search.select();
			}
		});
	}

	//—— 2. 排序：把 sort 参数拼到当前地址上再跳
	var sortSel = document.getElementById('studioSort');
	if(sortSel){
		sortSel.addEventListener('change', function(){
			var base = sortSel.getAttribute('data-base') || '';
			var query = base;
			if(sortSel.value && sortSel.value !== 'new'){
				query += (query === '' ? '' : '&') + 'sort=' + encodeURIComponent(sortSel.value);
			}
			window.location.href = './' + (query === '' ? '' : '?' + query);
		});
	}

	//—— 3. 列表 / 网格视图
	var toggle = document.getElementById('studioViewToggle');
	var table = document.querySelector('.filelist-main');
	if(!toggle || !table)return;
	var KEY = 'studio_view';

	function apply(view){
		var grid = (view === 'grid');
		//网格样式全部挂在 body 上，表格本身的结构不动，切回列表时不需要重建 DOM
		if(grid) document.body.classList.add('studio-grid-on');
		else document.body.classList.remove('studio-grid-on');
		Array.prototype.forEach.call(toggle.querySelectorAll('button'), function(b){
			if(b.getAttribute('data-studio-view') === view) b.classList.add('active');
			else b.classList.remove('active');
		});
	}

	var saved = 'list';
	try{ saved = localStorage.getItem(KEY) || 'list'; }catch(e){}
	apply(saved === 'grid' ? 'grid' : 'list');

	toggle.addEventListener('click', function(e){
		var btn = e.target.closest ? e.target.closest('button[data-studio-view]') : null;
		if(!btn)return;
		var view = btn.getAttribute('data-studio-view');
		apply(view);
		try{ localStorage.setItem(KEY, view); }catch(err){}
	});
})();
