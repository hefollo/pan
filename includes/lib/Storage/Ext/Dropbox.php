<?php
namespace lib\Storage\Ext;

/**
 * Dropbox（移植自 OpenList drivers/dropbox）
 *
 * API v2，按路径操作。令牌两种来源：自己的应用（client_id + client_secret + refresh_token），
 * 或 OpenList 的在线续期接口（会把 refresh_token 发给它）。团队空间按 OpenList 的做法带 Dropbox-API-Path-Root。
 * 上传都走上传会话：start → 每 20MB 一次 append_v2 → finish。和 OpenList 的两处不同：
 * 1. finish 用 overwrite 且不自动改名（OpenList 是 add + autorename，同名时会被改名，本站要求文件名不变）；
 * 2. 每一步都检查返回结果（OpenList 只看网络错误）。
 * 下载用 get_temporary_link 给的四小时临时直链。
 */
class Dropbox extends Base
{
	const PART = 20971520;

	//接口根地址，只有测试脚本会用反射改成本地模拟接口
	private $api = 'https://api.dropboxapi.com';
	private $content = 'https://content.dropboxapi.com';
	private $root;

	public static function meta()
	{
		return [
			'name' => 'Dropbox',
			'icon' => 'fa-dropbox',
			'group' => '对象存储与国外网盘',
			'source' => 'dropbox',
			'desc' => 'Dropbox 个人版和团队版，下载可用临时直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://www.dropbox.com/developers/apps',
			'link_text' => '创建应用',
			'fields' => [
				['name'=>'use_online_api', 'label'=>'令牌来源', 'type'=>'select', 'default'=>'0', 'options'=>['0'=>'自己的应用（client_id / secret）', '1'=>'OpenList 在线续期接口']],
				['name'=>'refresh_token', 'label'=>'Refresh Token', 'type'=>'secret', 'required'=>true],
				['name'=>'client_id', 'label'=>'App key'],
				['name'=>'client_secret', 'label'=>'App secret', 'type'=>'secret'],
				['name'=>'api_url_address', 'label'=>'在线续期接口', 'default'=>'https://api.oplist.org/dropboxs/renewapi', 'tip'=>'选在线续期时用。注意它会收到你的 refresh_token。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'目录不存在会在上传时自动创建。'],
			],
		];
	}

	protected function init()
	{
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/')), '/');
	}

	private function path($name)
	{
		return $this->root.'/'.$name;
	}

	private function token($force = false)
	{
		if(!$force && $this->state('access_token') && intval($this->state('expire', 0)) > time() + 120)return $this->state('access_token');
		$rt = $this->state('refresh_token') ? $this->state('refresh_token') : $this->c('refresh_token');
		if($rt === '')return $this->fail('请填写 Refresh Token');
		if($this->c('use_online_api') === '1'){
			$r = $this->json('GET', $this->c('api_url_address', 'https://api.oplist.org/dropboxs/renewapi'), ['query'=>['refresh_ui'=>$rt, 'server_use'=>'true', 'driver_txt'=>'dropboxs_go']]);
			if($r === false)return false;
			if(empty($r['refresh_token']) || empty($r['access_token']))return $this->fail('在线续期失败：'.(!empty($r['text']) ? $r['text'] : '没有返回令牌'));
			$this->saveState(['access_token'=>$r['access_token'], 'refresh_token'=>$r['refresh_token'], 'expire'=>time() + 3500]);
			return $r['access_token'];
		}
		$r = $this->json('POST', $this->api.'/oauth2/token', ['form'=>['grant_type'=>'refresh_token', 'refresh_token'=>$rt, 'client_id'=>$this->c('client_id'), 'client_secret'=>$this->c('client_secret')]]);
		if($r === false)return false;
		if(empty($r['access_token']))return $this->fail('换令牌失败：'.(isset($r['error_description']) ? $r['error_description'] : (isset($r['error']) ? (is_string($r['error']) ? $r['error'] : json_encode($r['error'])) : '')));
		$this->saveState(['access_token'=>$r['access_token'], 'expire'=>time() + (isset($r['expires_in']) ? intval($r['expires_in']) : 14000)]);
		return $r['access_token'];
	}

	private function headers($tok)
	{
		$h = ['Authorization'=>'Bearer '.$tok];
		$ns = $this->state('root_ns');
		if($ns)$h['Dropbox-API-Path-Root'] = json_encode(['.tag'=>'root', 'root'=>$ns]);
		return $h;
	}

	/*
	 * RPC 接口（api.dropboxapi.com），返回 [状态码, 解开的响应]。
	 * 令牌过期 / 无效（401 或 expired_access_token）时换一次再试。
	 */
	private function rpc($endpoint, $body, $retry = true)
	{
		$tok = $this->token();
		if($tok === false)return false;
		$opts = ['headers'=>$this->headers($tok)];
		if($body === null){
			$opts['body'] = 'null';
			$opts['headers']['Content-Type'] = 'application/json';
		}else{
			$opts['json'] = $body;
		}
		$res = $this->request('POST', $this->api.$endpoint, $opts);
		if($res === false)return false;
		if($res['status'] != 200 && $retry && ($res['status'] == 401 || strpos($res['body'], 'expired_access_token') !== false || strpos($res['body'], 'invalid_access_token') !== false)){
			if($this->token(true) === false)return false;
			return $this->rpc($endpoint, $body, false);
		}
		return [$res['status'], json_decode($res['body'], true), $res['body']];
	}

	private function dbxErr($r, $what)
	{
		if(strpos($r[2], 'not_found') !== false)$this->notfound = true;
		return $this->fail('Dropbox '.$what.'失败（HTTP '.$r[0].'）：'.(isset($r[1]['error_summary']) ? $r[1]['error_summary'] : $this->brief($r[2])));
	}

	//内容接口（content.dropboxapi.com）：参数放在 Dropbox-API-Arg 头里，必须是 ASCII（json_encode 默认就转义了中文）
	private function content($endpoint, $arg, $opts = [])
	{
		$tok = $this->token();
		if($tok === false)return false;
		$opts['headers'] = $this->headers($tok) + ['Content-Type'=>'application/octet-stream', 'Dropbox-API-Arg'=>json_encode($arg, JSON_UNESCAPED_SLASHES)];
		if(!isset($opts['file']) && !isset($opts['body']))$opts['body'] = '';
		$opts['timeout'] = 0;
		$res = $this->request('POST', $this->content.$endpoint, $opts);
		if($res === false)return false;
		return [$res['status'], json_decode($res['body'], true), $res['body']];
	}

	protected function check()
	{
		$r = $this->rpc('/2/check/user', ['query'=>'foo']);
		if($r === false)return false;
		if($r[0] != 200 || !isset($r[1]['result']) || $r[1]['result'] !== 'foo')return $this->dbxErr($r, '校验账号');
		$a = $this->rpc('/2/users/get_current_account', null);
		if($a === false)return false;
		if($a[0] != 200)return $this->dbxErr($a, '读取账号信息');
		$ns = isset($a[1]['root_info']['root_namespace_id']) ? $a[1]['root_info']['root_namespace_id'] : '';
		$this->saveState(['root_ns'=>$ns === '' ? null : $ns]);
		return true;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$r = $this->rpc('/2/files/get_metadata', ['path'=>$this->path($name)]);
		if($r === false)return false;
		if($r[0] != 200)return $this->dbxErr($r, '查询文件');
		if(!isset($r[1]['.tag']) || $r[1]['.tag'] !== 'file'){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['size'=>intval($r[1]['size']), 'id'=>$r[1]['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$s = $this->content('/2/files/upload_session/start', ['close'=>false]);
		if($s === false)return false;
		if($s[0] != 200 || empty($s[1]['session_id']))return $this->dbxErr($s, '开始上传');
		$sid = $s[1]['session_id'];
		$offset = 0;
		while($offset < $size){
			$len = min(self::PART, $size - $offset);
			$r = $this->content('/2/files/upload_session/append_v2', ['cursor'=>['session_id'=>$sid, 'offset'=>$offset], 'close'=>false], ['file'=>$tmpfile, 'offset'=>$offset, 'length'=>$len]);
			if($r === false)return false;
			if($r[0] != 200)return $this->dbxErr($r, '上传第 '.(intdiv($offset, self::PART) + 1).' 块');
			$offset += $len;
		}
		$f = $this->content('/2/files/upload_session/finish', [
			'cursor' => ['session_id'=>$sid, 'offset'=>$offset],
			'commit' => ['path'=>$this->path($name), 'mode'=>'overwrite', 'autorename'=>false, 'mute'=>false, 'strict_conflict'=>false],
		]);
		if($f === false)return false;
		if($f[0] != 200)return $this->dbxErr($f, '完成上传');
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$r = $this->rpc('/2/files/delete_v2', ['path'=>$this->path($name)]);
		if($r === false)return false;
		if($r[0] == 200)return true;
		$this->dbxErr($r, '删除');
		return $this->notfound;
	}

	protected function link($name)
	{
		$r = $this->rpc('/2/files/get_temporary_link', ['path'=>$this->path($name)]);
		if($r === false)return false;
		if($r[0] != 200 || empty($r[1]['link']))return $this->dbxErr($r, '获取下载地址');
		return ['url'=>$r[1]['link']];
	}
}
