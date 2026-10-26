<?php
/*
 * 渐变仪表盘风右栏：存储分布（按文件类型的条形图 + 图例）。
 * 一个扩展名只会落进一个分组，剩下的都算「其他」；负数说明统计口径对不上，兜底成 0。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '存储分布',
	'group' => '仪表盘',
	'scope' => 'front',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'存储分布'],
		['key'=>'sub', 'type'=>'text', 'label'=>'小字', 'default'=>'按文件类型', 'help'=>'清空不显示'],
	],
	'data' => function($cfg, &$ctx){
		$counts = layout_ctx_counts($ctx);
		$total = intval($counts['']);
		$groups = [['image', '图片'], ['video', '视频'], ['doc', '文档'], ['archive', '压缩包']];
		$known = 0;
		foreach($groups as $g)$known += intval($counts[$g[0]]);
		$other = max(0, $total - $known);
		$bar = [];
		$legend = [];
		foreach($groups as $g){
			$num = intval($counts[$g[0]]);
			$pct = $total > 0 ? round($num / $total * 100, 2) : 0;
			if($pct > 0)$bar[] = ['key'=>$g[0], 'pct'=>$pct];
			$legend[] = ['key'=>$g[0], 'label'=>$g[1], 'num'=>number_format($num)];
		}
		$other_pct = $total > 0 ? round($other / $total * 100, 2) : 0;
		if($other_pct > 0)$bar[] = ['key'=>'other', 'pct'=>$other_pct];
		$legend[] = ['key'=>'other', 'label'=>'其他', 'num'=>number_format($other)];
		if(!$bar)$bar[] = ['key'=>'empty', 'pct'=>100];
		return ['bar'=>$bar, 'legend'=>$legend];
	},
];
