<?php
/*
 * 个人中心顶部：标题、头像（点击更换，脚本 usercenter.js）、昵称、登录方式与 UID、权限状态。
 * 昵称在旧代码里是原样输出的（入库时已经转义过）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '个人中心头部',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $userrow, $uid;
		include_once SYSTEM_ROOT.'usercenter.php';
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		if(empty($expire)){
			$state = '永久有效'; $cls = 'ok';
		}elseif(!is_user_permission_active()){
			$state = '权限已过期'; $cls = 'expired';
		}else{
			$left = max(1, ceil((strtotime($expire) - time()) / 86400));
			$state = '剩 '.$left.' 天'; $cls = $left <= 7 ? 'warn' : 'ok';
		}
		return [
			'face_icon_html' => uc_face_icon_html(),
			'avatar_img_html' => user_avatar_img($userrow),
			'nickname_html' => $userrow['nickname'],
			'account_html' => $userrow['type']=='mail' ? htmlspecialchars($userrow['openid'], ENT_QUOTES, 'UTF-8') : ($userrow['type']=='qq' ? 'QQ 快捷登录' : '微信快捷登录'),
			'uid' => intval($uid),
			'state' => $state,
			'state_cls' => $cls,
		];
	},
];
