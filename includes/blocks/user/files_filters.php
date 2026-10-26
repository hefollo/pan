<?php
/* 「我的文件」的类型筛选标签（保留搜索词，计数按全部文件算） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件类型筛选',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'description' => '分组和图片/视频/音频格式跟着后台「文件设置」走',
	'data' => function($cfg, &$ctx){
		include_once SYSTEM_ROOT.'usercenter.php';
		$s = uc_files_state();
		$counts = uc_files_counts();
		$filters = [];
		foreach(layout_type_filters() as $fk => $label){
			$q = 'tab=files';
			if($s['kw'] !== '')$q .= '&kw='.urlencode($s['kw']);
			if($fk !== '')$q .= '&ft='.$fk;
			//地址在旧代码里是原样拼进 href 的
			$filters[] = ['query_html'=>$q, 'label'=>$label, 'num'=>isset($counts[$fk]) ? intval($counts[$fk]) : 0, 'active'=>$s['ft'] === $fk];
		}
		return ['filters' => $filters];
	},
];
