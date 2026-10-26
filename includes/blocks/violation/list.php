<?php
/*
 * 违规文件公示列表（每页 20 条）。只输出脱敏后的文件名和 IP，不输出 token 和任何可访问链接，
 * 避免公示页反过来变成违规内容的索引。filelist-wrap / fl-* 是手机端排成卡片用的。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '违规公示列表',
	'group' => '赞助与公示',
	'scope' => 'front',
	'schema' => [
		['key'=>'empty', 'type'=>'text', 'label'=>'没有记录时', 'default'=>'暂无违规封禁记录'],
	],
	'data' => function($cfg, &$ctx){
		global $DB;
		$sql = " is_show=1";
		$numrows = $DB->getColumn("SELECT count(*) from pre_violation WHERE{$sql}");
		$pagesize = 20;
		$pages = ceil($numrows / $pagesize);
		$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
		if($page < 1)$page = 1;
		$offset = $pagesize * ($page - 1);
		$rs = $DB->query("SELECT * FROM pre_violation WHERE{$sql} ORDER BY id DESC LIMIT $offset,$pagesize");
		$rows = [];
		$i = $offset + 1;
		while($res = $rs->fetch()){
			$rows[] = [
				'n' => $i++,
				'icon' => type_to_icon($res['type']),
				'name' => violation_mask_name($res['name']),
				'remark' => $res['remark'] ? $res['remark'] : '',
				'size' => size_format($res['size']),
				'type' => $res['type'] ? $res['type'] : '未知',
				'ip' => violation_mask_ip($res['ip']),
				'addtime' => $res['addtime'],
			];
		}
		return [
			'rows' => $rows,
			'empty' => $numrows == 0,
			'numrows' => $numrows,
			'page' => $page,
			'pages' => $pages,
			'pager' => layout_pager_items($page, $pages, 'violation.php?page='),
		];
	},
];
