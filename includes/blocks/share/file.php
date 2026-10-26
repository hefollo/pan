<?php
/*
 * 文件夹分享页：单个文件（返回所在文件夹、文件名与大小、操作按钮、能预览的格式直接预览）。
 * 操作按钮由 share.php 的 share_actions() 输出（所有者多出改名 / 密码 / 公开私密 / 覆盖 / 删除）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '分享文件预览',
	'group' => '文件夹分享',
	'scope' => 'front',
	'pages' => ['share'],
	'schema' => [
		['key'=>'back_text', 'type'=>'text', 'label'=>'返回链接', 'default'=>'返回所在文件夹'],
		['key'=>'media_note', 'type'=>'text', 'label'=>'音视频下方的说明', 'default'=>'若浏览器不支持此格式，请下载后播放。', 'help'=>'清空不显示'],
		['key'=>'none_text', 'type'=>'text', 'label'=>'不能预览时', 'default'=>'此格式暂不支持在线预览，可下载后查看。'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if($d['view'] !== 'file')return null;
		$file = $d['file'];
		$type = strtolower($file['type']);
		ob_start();
		share_actions($file);
		$actions = ob_get_clean();
		$data = [
			'back' => share_url(['folder'=>$file['folder_id']]),
			'name_html' => share_name($file['name']),
			'meta' => size_format($file['size']).' · '.$file['addtime'],
			'actions_html' => $actions,
			'src' => share_url(['file'=>$d['file_id'], 'action'=>'view']),
		];
		if(!is_scriptable_file_type($type) && in_array($type, ['jpg','jpeg','png','gif','webp','avif','bmp','ico'], true)){
			$data['image'] = true;
		}elseif(in_array($type, ['mp4','webm','ogg','mov','m4v','mp3','wav','m4a','flac','aac'], true)){
			$data['media'] = ['tag' => in_array($type, ['mp3','wav','m4a','flac','aac'], true) ? 'audio' : 'video'];
		}elseif(is_editable_file_type($type) && intval($file['size']) <= get_editable_file_max_size()){
			$data['text_src'] = share_url(['file'=>$d['file_id'], 'action'=>'text']);
		}elseif($type === 'pdf'){
			$data['pdf'] = true;
		}else{
			$data['none'] = true;
		}
		return $data;
	},
];
