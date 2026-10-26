<?php
/*
 * 文件列表：表格 + 底部的数量与翻页；游客的「我的文件」是另一种表格（勾选、批量删除、文件夹）。
 *
 * 这张卡片的标记有几处是脚本和样式认的，换皮时要保留：
 *   - 表格 class（filelist / guest-filelist）、tbody、每行的 data-* 属性：
 *     深色工作台风的预览面板读 data-*，局部刷新（assets/js/filelist-live.js）按选择器换 tbody；
 *   - filelist-wrap / fl-* 类名：手机端 style.css 把每一行排成卡片，靠类名定位各列；
 *   - #replaceFileInput、#guestBatchBar、.guest-check 等 id / 类名：页面末尾的脚本要用。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件列表',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'action_col', 'type'=>'select', 'label'=>'操作列位置', 'default'=>'left', 'options'=>['left'=>'第二列', 'right'=>'最右边']],
		['key'=>'cards', 'type'=>'switch', 'label'=>'手机上排成卡片', 'default'=>1, 'help'=>'自带卡片网格的外观（门户风、macOS 窗口风）关掉'],
	],
	'data' => function($cfg, &$ctx){
		global $DB, $conf;
		$d = $ctx['data'];
		$guest_mine = $d['guest_mine'];
		$is_mine = $d['is_mine'];
		$sql = $d['sql'];
		$numrows = $DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
		$pagesize = 15;
		$pages = ceil($numrows / $pagesize);
		$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
		$offset = $pagesize * ($page - 1);
		$rs = $DB->query("SELECT * FROM pre_file WHERE{$sql} ORDER BY {$d['order_sql']} LIMIT $offset,$pagesize");
		//游客开了文件夹：当前层的子文件夹排在最前面，只在第一页出现，不占分页
		$fd_rows = '';
		if($d['fd_on'] && !$d['fd_searching'] && $page <= 1){
			$fd_rows = folder_render_rows($d['fd_all'], $d['fd_cur'], $d['fd_url']);
		}
		$layout_text_max = defined('LAYOUT_TEXT_PREVIEW_MAX') ? LAYOUT_TEXT_PREVIEW_MAX : 256 * 1024;
		$rows = [];
		$i = 1;
		while($res = $rs->fetch()){
			$fileurl = './down.php/'.$res['token'].'.'.($res['type'] ? $res['type'] : 'file');
			$viewurl = './file.php?hash='.$res['token'];
			$type_text = $res['type'] ? $res['type'] : '未知';
			$row = [
				'id' => intval($res['id']),
				'token' => $res['token'],
				'fileurl' => $fileurl,
				'viewurl' => $viewurl,
				'icon' => type_to_icon($res['type']),
				'name_html' => $res['name'],
				//data-name 在旧代码里是对入库值再转义一次，模板里用双括号输出 name_raw 得到同样结果
				'name_raw' => $res['name'],
				'size' => size_format($res['size']),
				'type_text' => $type_text,
				'addtime' => $res['addtime'],
			];
			if($guest_mine){
				$blocked = intval($res['block']) === 1;
				$can_manage = can_manage_file($res);
				$delete_reason = file_delete_locked_reason($res);
				if($delete_reason === '' && !$can_manage)$delete_reason = '游客只能管理本浏览器七天内上传的文件';
				$states = [];
				if($blocked)$states[] = ['cls'=>' uc-badge-danger', 'text_html'=>'已冻结'];
				if(intval($res['block']) === 2)$states[] = ['cls'=>' uc-badge-warn', 'text_html'=>'待人工审核'];
				$states[] = intval($res['hide']) === 1 ? ['cls'=>'', 'text_html'=>'私密'] : ['cls'=>' uc-badge-ok', 'text_html'=>'公开'];
				if(!empty($res['pwd']))$states[] = ['cls'=>' uc-badge-warn', 'text_html'=>'<i class="fa fa-lock" aria-hidden="true"></i> 有密码'];
				$row += [
					'blocked' => $blocked,
					'can_edit' => can_show_online_edit_entry($res),
					//改名是跟文件夹一起加的，开了文件夹才有
					'can_rename' => $d['fd_on'] && $can_manage && !$blocked,
					'can_replace' => $can_manage,
					'can_delete' => $delete_reason === '',
					'delete_reason' => $delete_reason,
					'states' => $states,
					'loc_html' => $d['fd_searching'] ? folder_render_loc($d['fd_all'], $res['folder_id'], $d['fd_url']) : '',
				];
				$rows[] = $row;
				continue;
			}
			//深色工作台风的预览面板要直接显示图片/视频/文本：没有设密码、没被封禁、且是可在线预览的
			//类型时才给出预览地址，其余情况面板里还是显示文件类型图标
			$preview_url = '';
			$preview_kind = '';
			if(empty($res['pwd']) && intval($res['block']) === 0){
				if(is_view($res['type'])){
					$preview_kind = get_view_type($res['type']);
					if($preview_kind === 'image' || $preview_kind === 'video' || $preview_kind === 'audio'){
						$preview_url = './view.php/'.$res['token'].'.'.$res['type'].'?preview=1';
					}else{
						$preview_kind = '';
					}
				}elseif(is_editable_file_type($res['type']) && intval($res['size']) <= $layout_text_max){
					//txt/json/js 这类文本文件走 text.php 取内容；太大的不自动拉，免得点一下列表就下几 MB
					$preview_kind = 'text';
					$preview_url = './text.php?hash='.$res['token'].'&preview=1';
				}
			}
			$row += [
				'num' => $i++,
				'group' => layout_type_group($res['type']),
				'preview_url' => $preview_url,
				'preview_kind' => $preview_kind,
				'ip' => preg_replace('/\d+$/', '*', $res['ip']),
				'has_pwd' => !empty($res['pwd']),
				'can_edit' => $is_mine && can_show_online_edit_entry($res),
				'can_replace' => $is_mine && can_manage_file($res),
			];
			$rows[] = $row;
		}
		//翻页：地址参数在旧代码里就是原样拼进 href 的（kw 已经 urlencode 过），所以给 _html
		$link = $d['link'];
		$pager = [];
		$item = function($disabled, $p, $label) use ($link){
			return ['disabled'=>$disabled, 'href_html'=>$disabled ? '' : 'index.php?page='.$p.$link, 'label_html'=>$label];
		};
		if($page > 1){
			$pager[] = $item(false, 1, '首页');
			$pager[] = $item(false, $page - 1, '&laquo;');
		}else{
			$pager[] = $item(true, 0, '首页');
			$pager[] = $item(true, 0, '&laquo;');
		}
		$pagegroup = 10;
		$start = $page < $pagegroup ? 1 : floor($page / $pagegroup) * $pagegroup;
		$end = min($start + $pagegroup, $pages);
		for($p = $start; $p <= $end; $p++){
			$pager[] = $p == $page ? $item(true, 0, (string)$p) : $item(false, $p, (string)$p);
		}
		if($page < $pages){
			$pager[] = $item(false, $page + 1, '&raquo;');
			$pager[] = $item(false, $pages, '尾页');
		}else{
			$pager[] = $item(true, 0, '&raquo;');
			$pager[] = $item(true, 0, '尾页');
		}
		$empty = ($numrows == 0 && $fd_rows === '');
		$data = [
			'is_mine' => $is_mine,
			'guest_mine' => $guest_mine,
			'fd_on' => $d['fd_on'],
			'cards' => !$guest_mine && $cfg['cards'],
			'action_right' => $cfg['action_col'] === 'right',
			'rows' => $rows,
			'folder_rows_html' => $fd_rows,
			'empty' => $empty,
			'empty_text' => ($d['fd_on'] && $d['fd_cur'] > 0 && !$d['fd_searching']) ? '这个文件夹是空的' : '还没上传过任何文件',
			'numrows' => $numrows,
			'page' => $page,
			'pages' => $pages,
			'pager' => $pager,
		];
		if($guest_mine && $d['fd_on']){
			$data['folder_bar_html'] = folder_render_bar($d['fd_all'], $d['fd_cur'], $d['fd_url'], $d['fd_searching'], './upload.php'.($d['fd_cur'] > 0 ? '?folder='.$d['fd_cur'] : ''));
			$data['batch_buttons_html'] = folder_render_batch_buttons();
		}
		//「我的文件」的操作脚本（覆盖上传、游客批量删除、改名、文件夹）放到页脚之后
		if($is_mine){
			theme_defer('index.mine_scripts', theme_tpl_render('blocks/index/mine_scripts', [
				'guest_mine' => $guest_mine,
				'version' => VERSION,
				'csrf_html' => $d['csrf_token'],
				'folder_script_html' => $d['fd_on'] ? folder_render_script($d['fd_cur'], $d['csrf_token'], $d['fd_searching'], '.guest-check') : '',
			], $ctx['theme_id']), 40);
		}
		return $data;
	},
];
