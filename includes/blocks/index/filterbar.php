<?php
/*
 * 类型筛选标签（全部 / 图片 / 视频 / 音频 / 文档 / 压缩包，带数量），保留当前的 m/kw 参数；
 * 工作台家族在右边再加排序下拉和列表 / 网格切换（排序是真的，视图切换由 layout-studio.js 记住）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '类型筛选',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'tools', 'type'=>'switch', 'label'=>'排序与视图切换', 'default'=>0],
	],
	'data' => function($cfg, &$ctx){
		$counts = layout_ctx_counts($ctx);
		$ft = $ctx['data']['ft'];
		$base = layout_ctx_base_query($ctx);
		$filters = [];
		foreach(layout_type_filters() as $key => $label){
			$query = $base;
			if($key !== '')$query .= ($query === '' ? '' : '&').'ft='.$key;
			$filters[] = [
				'href' => './'.($query === '' ? '' : '?'.$query),
				'label' => $label,
				'num' => isset($counts[$key]) ? intval($counts[$key]) : 0,
				'active' => $ft === $key,
			];
		}
		$data = ['filters' => $filters];
		if($cfg['tools']){
			$sort = $ctx['data']['sort'];
			$opts = [];
			foreach(['new'=>'上传时间 ↓', 'old'=>'上传时间 ↑', 'big'=>'文件大小 ↓', 'small'=>'文件大小 ↑'] as $k => $label){
				$opts[] = ['value'=>$k, 'label'=>$label, 'selected'=>$sort === $k];
			}
			$data['tools'] = ['base'=>$base, 'options'=>$opts];
			//排序跳转和视图切换的脚本原来只在工作台家族的页面末尾加载；别的外观开了这个选项也要它（样式见 style.css「排序与视图切换」一段）
			if(!in_array($ctx['theme_id'], studio_family_keys(), true)){
				//通用样式的挂钩（工作台家族有自己的样式，不加，输出和原来一样）
				$data['tools']['generic'] = true;
				theme_defer('layout_studio_js', '<script src="assets/js/layout-studio.js?v='.asset_ver('assets/js/layout-studio.js').'"></script>'."\n", 60);
			}
		}
		return $data;
	},
];
