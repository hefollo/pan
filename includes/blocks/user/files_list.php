<?php
/*
 * 「我的文件」列表：文件夹导航、批量操作条、文件表格（改名 / 密码 / 公开私密 / 在线编辑 / 覆盖 / 删除）、翻页。
 * 局部刷新（assets/js/filelist-live.js）按选择器换掉表格 tbody 和带计数的区块，usercenter.js
 * 读每行的 data-*，所以表格结构、id 和 data-* 换皮时要保留。脚本放到页脚之后。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '我的文件列表',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $DB;
		include_once SYSTEM_ROOT.'usercenter.php';
		$s = uc_files_state();
		$page = $s['page'];
		$offset = $s['pagesize'] * ($page - 1);
		//当前层的子文件夹排在最前面，只在第一页出现，不占分页
		$fd_rows = ($s['fd_on'] && !$s['fd_searching'] && intval($page) === 1) ? folder_render_rows($s['fd_all'], $s['fd_cur'], $s['fd_url']) : '';
		$rows = [];
		$rs = $DB->query("SELECT * FROM pre_file WHERE{$s['where']} ORDER BY id DESC LIMIT {$offset},{$s['pagesize']}");
		while($rs && $res = $rs->fetch()){
			$blocked = intval($res['block']) === 1;
			$rows[] = [
				'id' => intval($res['id']),
				'name_html' => $res['name'],
				'hidden' => intval($res['hide']) === 1,
				'haspwd' => !empty($res['pwd']),
				'viewurl' => './file.php?hash='.$res['token'],
				'fileurl' => './down.php/'.$res['token'].'.'.($res['type'] ? $res['type'] : 'file'),
				'blocked' => $blocked,
				'pending' => intval($res['block']) === 2,
				//冻结和待人工复核都不给删，勾选框和删除按钮统一按这个判断
				'nodelete' => file_delete_locked_reason($res),
				'icon' => type_to_icon($res['type']),
				'loc_html' => ($s['fd_on'] && $s['fd_searching']) ? folder_render_loc($s['fd_all'], $res['folder_id'], $s['fd_url']) : '',
				'size' => size_format($res['size']),
				'addtime' => $res['addtime'],
				'can_edit' => can_show_online_edit_entry($res),
			];
		}
		$empty_text = '';
		if(!$rows && $fd_rows === ''){
			if($s['kw'] !== '' || $s['ft'] !== '')$empty_text = '没有符合条件的文件';
			elseif($s['fd_on'] && $s['fd_cur'] > 0)$empty_text = '这个文件夹是空的';
			else $empty_text = '还没有上传过文件';
		}
		//翻页：地址在旧代码里是原样拼进 href 的（kw 已经 urlencode 过）
		$pager = [];
		$pages = $s['pages'];
		$qs = $s['qs'];
		$link = function($p, $label) use ($qs){ return ['disabled'=>false, 'href_html'=>'./user.php?'.$qs.'&page='.$p, 'label_html'=>$label]; };
		if($page > 1){
			$pager[] = $link(1, '首页');
			$pager[] = $link($page - 1, '&laquo;');
		}
		$start = max(1, $page - 4);
		$end = min($pages, $start + 9);
		for($i = $start; $i <= $end; $i++){
			$pager[] = $i == $page ? ['disabled'=>true, 'href_html'=>'', 'label_html'=>(string)$i] : $link($i, (string)$i);
		}
		if($page < $pages){
			$pager[] = $link($page + 1, '&raquo;');
			$pager[] = $link($pages, '尾页');
		}
		theme_defer('user.files_scripts', theme_tpl_render('blocks/user/files_scripts', [
			'version' => VERSION,
			'folder_script_html' => folder_render_script($s['fd_cur'], $ctx['data']['csrf_token'], $s['fd_searching'], '.uc-check'),
		], $ctx['theme_id']), 20);
		return [
			'fd_on' => $s['fd_on'],
			'folder_bar_html' => $s['fd_on'] ? folder_render_bar($s['fd_all'], $s['fd_cur'], $s['fd_url'], $s['fd_searching']) : '',
			'batch_buttons_html' => $s['fd_on'] ? folder_render_batch_buttons() : '',
			'folder_rows_html' => $fd_rows,
			'rows' => $rows,
			'empty' => $empty_text !== '',
			'empty_text' => $empty_text,
			'numrows' => $s['numrows'],
			'page' => $page,
			'pages' => $pages,
			'multi' => $pages > 1,
			'pager' => $pager,
		];
	},
];
