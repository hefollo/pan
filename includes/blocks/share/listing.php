<?php
/*
 * 文件夹分享页：目录浏览 / 全部文件、搜索与筛选、路径、文件表格、翻页。
 * folder-share.js 靠 data-share-nav 做局部刷新，表格和这些属性换皮时要保留。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '分享文件列表',
	'group' => '文件夹分享',
	'scope' => 'front',
	'pages' => ['share'],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if($d['view'] !== 'list' || !$d['listing'])return null;
		$listing = $d['listing'];
		$sc = $d['ctx'];
		$state = ['folder'=>$listing['current'], 'mode'=>$listing['mode'], 'q'=>$listing['q'], 'type'=>$listing['type'], 'sort'=>$listing['sort']];
		$types = [];
		foreach(layout_type_filters() as $key => $label)$types[] = ['key'=>$key, 'label'=>$label, 'selected'=>$key === $listing['type']];
		$sorts = [];
		foreach(['newest'=>'最新上传', 'oldest'=>'最早上传', 'name'=>'名称排序', 'size'=>'大小排序'] as $key => $label)$sorts[] = ['key'=>$key, 'label'=>$label, 'selected'=>$key === $listing['sort']];
		$crumbs = [];
		if(!$listing['recursive']){
			foreach(folder_path($sc['all'], $listing['current']) as $i => $f)$crumbs[] = ['sep'=>$i > 0, 'href'=>share_url(['folder'=>$f['id']]), 'name'=>$f['name']];
		}
		$folders = [];
		foreach($listing['folders'] as $f)$folders[] = ['href'=>share_url(['folder'=>$f['id']]), 'name'=>$f['name']];
		$files = [];
		foreach($listing['files'] as $f){
			ob_start();
			share_actions($f);
			$actions = ob_get_clean();
			$path = '';
			if($listing['recursive']){
				$names = array_column(folder_path($sc['all'], intval($f['folder_id'])), 'name');
				array_shift($names);
				$path = $names ? implode(' / ', $names) : '分享根目录';
			}
			$files[] = [
				'href' => share_url(['file'=>$f['id']]),
				'name_html' => share_name($f['name']),
				'locked' => !empty($f['locked']),
				'recursive' => $listing['recursive'],
				'path' => $path,
				'size' => size_format($f['size']),
				'addtime' => $f['addtime'],
				'actions_html' => $actions,
			];
		}
		return [
			'mode_folder' => $listing['mode'] === 'folder',
			'mode_all' => $listing['mode'] === 'all',
			'folder_href' => share_url(['folder'=>$listing['current']]),
			'all_href' => share_url(array_merge($state, ['mode'=>'all', 'folder'=>$sc['root']])),
			'total' => $listing['total'],
			'folder_count' => $listing['folders'] ? count($listing['folders']) : 0,
			'token' => $d['token'],
			'root' => $sc['root'],
			'q' => $listing['q'],
			'types' => $types,
			'sorts' => $sorts,
			'filtered' => $listing['q'] !== '' || $listing['type'] !== '',
			'clear_href' => share_url(['mode'=>'all', 'folder'=>$sc['root']]),
			'recursive' => $listing['recursive'],
			'crumbs' => $crumbs,
			'folders' => $folders,
			'files' => $files,
			'empty' => !$listing['files'] && !$listing['folders'],
			'page' => $listing['page'],
			'pages' => $listing['pages'],
			'prev_href' => $listing['page'] > 1 ? share_url(array_merge($state, ['page'=>$listing['page'] - 1])) : '',
			'next_href' => $listing['page'] < $listing['pages'] ? share_url(array_merge($state, ['page'=>$listing['page'] + 1])) : '',
		];
	},
];
