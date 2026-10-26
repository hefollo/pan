<?php
/* 个人中心概览：文件类型分布（GROUP BY type 走 uid 索引后数据量很小，直接查）；一个文件都没有时不显示 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件类型分布',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'文件类型分布'],
	],
	'data' => function($cfg, &$ctx){
		global $DB, $uid;
		$stat = theme_memo('uc.stat', function() use ($DB, $uid){
			return $DB->getRow("SELECT count(*) AS num, COALESCE(SUM(size),0) AS bytes FROM pre_file WHERE uid=:uid", [':uid'=>intval($uid)]);
		});
		$num = $stat ? intval($stat['num']) : 0;
		if($num <= 0)return null;
		$groups = ['image'=>0, 'video'=>0, 'audio'=>0, 'doc'=>0, 'archive'=>0, 'other'=>0];
		$rs = $DB->query("SELECT type, count(*) AS num FROM pre_file WHERE uid=".intval($uid)." GROUP BY type");
		if($rs){
			while($r = $rs->fetch()){
				$g = layout_type_group($r['type']);
				if(!isset($groups[$g]))$g = 'other';
				$groups[$g] += intval($r['num']);
			}
		}
		$labels = ['image'=>'图片', 'video'=>'视频', 'audio'=>'音频', 'doc'=>'文档', 'archive'=>'压缩包', 'other'=>'其它'];
		$list = [];
		foreach($groups as $g => $n){
			if($n <= 0)continue;
			$list[] = ['key'=>$g, 'label'=>$labels[$g], 'num'=>$n, 'pct'=>max(2, round($n / $num * 100))];
		}
		return ['groups' => $list];
	},
];
