<?php
/* 上传 API 页签：已有密钥列表（状态、白名单、到期、最近使用、启停与删除） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => 'API 密钥列表',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $DB, $uid;
		$keys = [];
		foreach($DB->getAll("SELECT * FROM pre_api_key WHERE uid=:uid ORDER BY id DESC", [':uid'=>intval($uid)]) as $k){
			$keys[] = [
				'id' => intval($k['id']),
				'name' => $k['name'],
				'prefix' => $k['key_prefix'],
				'expired' => !empty($k['expiretime']) && strtotime($k['expiretime']) <= time(),
				'enabled' => intval($k['enable']) === 1,
				'allow_ip' => $k['allow_ip'] !== '' ? str_replace('|', '、', $k['allow_ip']) : '',
				'expiretime' => !empty($k['expiretime']) ? $k['expiretime'] : '',
				'lasttime' => !empty($k['lasttime']) ? $k['lasttime'] : '',
				'lastip' => $k['lastip'],
			];
		}
		return ['keys' => $keys];
	},
];
