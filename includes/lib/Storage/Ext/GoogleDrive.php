<?php
namespace lib\Storage\Ext;

/**
 * Google Drive（移植自 OpenList drivers/google_drive）
 *
 * Drive v3 接口，按文件 ID 操作。令牌三种来源，同 OpenList：
 * 1. 在线续期接口（默认 api.oplist.org）：用 OpenList 工具页拿到的 refresh_token 换令牌，会把 refresh_token 发给那个接口；
 * 2. 自己的 OAuth 客户端：client_id + client_secret + refresh_token 找 Google 换；
 * 3. 服务账号：OpenList 填的是服务器上 JSON 文件的路径，这里改成直接把 JSON 内容粘贴进来，本站用 RS256 签 JWT 换令牌。
 * 上传用可续传会话：小于分块大小一次 PUT，否则按块 PUT 带 Content-Range。
 * 下载地址要带 Authorization 头，只能中转（OpenList 也是 OnlyProxy）。
 */
class GoogleDrive extends Base
{
	//接口根地址，只有测试脚本会用反射改成本地模拟接口
	private $api = 'https://www.googleapis.com';
	private $tokenUrl = 'https://www.googleapis.com/oauth2/v4/token';

	public static function meta()
	{
		return [
			'name' => 'Google Drive',
			'icon' => 'fa-google',
			'group' => '对象存储与国外网盘',
			'source' => 'google_drive',
			'desc' => '谷歌云端硬盘（含共享云端硬盘），上传下载走本站中转。',
			'direct' => false,
			'range' => true,
			'link' => 'https://api.oplist.org/',
			'link_text' => '获取令牌',
			'fields' => [
				['name'=>'auth_mode', 'label'=>'授权方式', 'type'=>'select', 'default'=>'online', 'options'=>['online'=>'在线续期接口（OpenList 工具页的令牌）', 'client'=>'自己的 OAuth 客户端', 'service'=>'服务账号 JSON']],
				['name'=>'refresh_token', 'label'=>'Refresh Token', 'type'=>'secret', 'tip'=>'前两种方式要填。在线续期方式下每次续期会换一张新的，本站自动保存最新的。'],
				['name'=>'api_url_address', 'label'=>'在线续期接口', 'default'=>'https://api.oplist.org/googleui/renewapi', 'tip'=>'选在线续期时用。注意它会收到你的 refresh_token。'],
				['name'=>'client_id', 'label'=>'Client ID', 'tip'=>'选「自己的 OAuth 客户端」时填。'],
				['name'=>'client_secret', 'label'=>'Client Secret', 'type'=>'secret'],
				['name'=>'service_account', 'label'=>'服务账号 JSON', 'type'=>'textarea', 'tip'=>'选「服务账号」时把下载的 JSON 密钥文件整个粘贴进来。服务账号只能看到共享给它的文件夹，根文件夹 ID 要填那个文件夹的 ID。'],
				['name'=>'root_folder_id', 'label'=>'根文件夹 ID', 'default'=>'root', 'tip'=>'文件存到这个文件夹里。<b>root</b> 是「我的云端硬盘」根目录；其他文件夹打开后地址栏 folders/ 后面那串就是 ID。'],
				['name'=>'chunk_size', 'label'=>'分块大小（MB）', 'default'=>'5'],
			],
		];
	}

	private function folder()
	{
		return $this->c('root_folder_id', 'root');
	}

	/* ---------------- 令牌 ---------------- */

	private function token($force = false)
	{
		if(!$force && $this->state('access_token') && intval($this->state('expire', 0)) > time() + 120)return $this->state('access_token');
		$mode = $this->c('auth_mode', 'online');
		if($mode === 'service')return $this->serviceToken();
		$rt = $this->state('refresh_token') ? $this->state('refresh_token') : $this->c('refresh_token');
		if($rt === '')return $this->fail('请填写 Refresh Token');
		if($mode === 'online'){
			$api = $this->c('api_url_address', 'https://api.oplist.org/googleui/renewapi');
			$r = $this->json('GET', $api, ['query'=>['refresh_ui'=>$rt, 'server_use'=>'true', 'driver_txt'=>'googleui_go']]);
			if($r === false)return false;
			if(empty($r['refresh_token']) || empty($r['access_token'])){
				return $this->fail('在线续期失败：'.(!empty($r['text']) ? $r['text'] : '没有返回令牌，refresh_token 可能填错了'));
			}
			$this->saveState(['access_token'=>$r['access_token'], 'refresh_token'=>$r['refresh_token'], 'expire'=>time() + 3500]);
			return $r['access_token'];
		}
		if($this->c('client_id') === '' || $this->c('client_secret') === '')return $this->fail('请填写 Client ID 和 Client Secret');
		$r = $this->json('POST', $this->tokenUrl, ['form'=>['client_id'=>$this->c('client_id'), 'client_secret'=>$this->c('client_secret'), 'refresh_token'=>$rt, 'grant_type'=>'refresh_token']]);
		if($r === false)return false;
		if(empty($r['access_token']))return $this->fail('换令牌失败：'.(isset($r['error_description']) ? $r['error_description'] : (isset($r['error']) ? $r['error'] : '')));
		$this->saveState(['access_token'=>$r['access_token'], 'expire'=>time() + (isset($r['expires_in']) ? intval($r['expires_in']) : 3500)]);
		return $r['access_token'];
	}

	private function b64url($s)
	{
		return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
	}

	private function serviceToken()
	{
		$sa = json_decode($this->c('service_account'), true);
		if(!is_array($sa) || empty($sa['private_key']) || empty($sa['client_email']))return $this->fail('服务账号 JSON 不完整（要有 private_key 和 client_email）');
		$aud = !empty($sa['token_uri']) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token';
		$now = time();
		$scope = 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/drive.appdata https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive.metadata https://www.googleapis.com/auth/drive.metadata.readonly https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/drive.scripts';
		$head = $this->b64url(json_encode(['alg'=>'RS256', 'typ'=>'JWT']));
		$claim = $this->b64url(json_encode(['iss'=>$sa['client_email'], 'scope'=>$scope, 'aud'=>$aud, 'exp'=>$now + 3600, 'iat'=>$now], JSON_UNESCAPED_SLASHES));
		$key = openssl_pkey_get_private($sa['private_key']);
		if(!$key)return $this->fail('服务账号的私钥读不出来');
		$sig = '';
		if(!openssl_sign($head.'.'.$claim, $sig, $key, OPENSSL_ALGO_SHA256))return $this->fail('服务账号签名失败');
		$r = $this->json('POST', $aud, ['form'=>['assertion'=>$head.'.'.$claim.'.'.$this->b64url($sig), 'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer']]);
		if($r === false)return false;
		if(empty($r['access_token']))return $this->fail('服务账号换令牌失败：'.(isset($r['error_description']) ? $r['error_description'] : (isset($r['error']) ? $r['error'] : '')));
		$this->saveState(['access_token'=>$r['access_token'], 'expire'=>$now + (isset($r['expires_in']) ? intval($r['expires_in']) : 3500)]);
		return $r['access_token'];
	}

	/* ---------------- 请求 ---------------- */

	//$path 以 / 开头时拼到接口根地址后面；自动带上共享云端硬盘需要的两个参数
	private function call($method, $path, $opts = [], $retry = true)
	{
		$tok = $this->token();
		if($tok === false)return false;
		$url = strpos($path, 'http') === 0 ? $path : $this->api.$path;
		$url .= (strpos($url, '?') === false ? '?' : '&').'includeItemsFromAllDrives=true&supportsAllDrives=true';
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['Authorization'=>'Bearer '.$tok];
		$res = $this->request($method, $url, $opts);
		if($res === false)return false;
		if($res['status'] == 401 && $retry){
			if($this->token(true) === false)return false;
			return $this->call($method, $path, $opts, false);
		}
		return $res;
	}

	private function gerr($res, $what)
	{
		$j = json_decode($res['body'], true);
		if($res['status'] == 404)$this->notfound = true;
		return $this->fail('Google Drive '.$what.'失败（HTTP '.$res['status'].'）：'.(isset($j['error']['message']) ? $j['error']['message'] : $this->brief($res['body'])));
	}

	//文件名里的单引号和反斜杠在查询语句里要转义
	private function q($s)
	{
		return str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
	}

	private function find($name)
	{
		$res = $this->call('GET', '/drive/v3/files?'.http_build_query(['q'=>"name = '".$this->q($name)."' and '".$this->q($this->folder())."' in parents and trashed = false", 'fields'=>'files(id,name,size,mimeType)', 'pageSize'=>'10']));
		if($res === false)return false;
		if($res['status'] != 200)return $this->gerr($res, '查找文件');
		$j = json_decode($res['body'], true);
		if(empty($j['files'][0]['id'])){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		$this->mapSet($name, $j['files'][0]['id']);
		return $j['files'][0];
	}

	private function fileId($name)
	{
		$id = $this->mapGet($name);
		if($id !== null)return $id;
		$f = $this->find($name);
		return $f === false ? false : $f['id'];
	}

	/* ---------------- 驱动接口 ---------------- */

	protected function check()
	{
		$res = $this->call('GET', '/drive/v3/files/'.rawurlencode($this->folder()).'?fields=id,mimeType');
		if($res === false)return false;
		if($res['status'] != 200)return $this->gerr($res, '读取根文件夹');
		return true;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$id = $this->mapGet($name);
		if($id !== null){
			$res = $this->call('GET', '/drive/v3/files/'.rawurlencode($id).'?fields=id,size,trashed');
			if($res === false)return false;
			$j = json_decode($res['body'], true);
			if($res['status'] == 200 && empty($j['trashed']))return ['size'=>intval($j['size']), 'id'=>$id];
			$this->mapDel($name);
		}
		$f = $this->find($name);
		if($f === false)return false;
		return ['size'=>isset($f['size']) ? intval($f['size']) : 0, 'id'=>$f['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$res = $this->call('POST', '/upload/drive/v3/files?uploadType=resumable', [
			'json' => ['name'=>$name, 'parents'=>[$this->folder()]],
			'headers' => ['X-Upload-Content-Type'=>$content_type ? $content_type : 'application/octet-stream', 'X-Upload-Content-Length'=>(string)$size],
			'follow' => false,
		]);
		if($res === false)return false;
		if($res['status'] != 200 || empty($res['headers']['location']))return $this->gerr($res, '创建上传会话');
		$url = $res['headers']['location'];
		$cs = max(1, intval($this->c('chunk_size', '5'))) * 1048576;
		//可续传块必须是 256KB 的整数倍（最后一块除外）
		$cs = max(262144, $cs - $cs % 262144);
		$last = null;
		if($size < $cs){
			$last = $this->call('PUT', $url, ['file'=>$tmpfile, 'length'=>$size, 'timeout'=>0]);
			if($last === false)return false;
		}else{
			for($off = 0; $off < $size; $off += $cs){
				$len = min($cs, $size - $off);
				for($try = 0; $try < 3; $try++){
					$last = $this->call('PUT', $url, ['headers'=>['Content-Range'=>'bytes '.$off.'-'.($off + $len - 1).'/'.$size], 'file'=>$tmpfile, 'offset'=>$off, 'length'=>$len, 'timeout'=>0]);
					//中间块成功返回 308，最后一块返回 200 / 201
					if($last !== false && in_array($last['status'], [200, 201, 308], true))break;
				}
				if($last === false)return false;
				if(!in_array($last['status'], [200, 201, 308], true))return $this->gerr($last, '分块上传');
			}
		}
		if(!in_array($last['status'], [200, 201], true))return $this->gerr($last, '上传');
		$j = json_decode($last['body'], true);
		if(!empty($j['id']))$this->mapSet($name, $j['id']);
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$id = $this->fileId($name);
		if($id === false)return $this->notfound;
		$res = $this->call('DELETE', '/drive/v3/files/'.rawurlencode($id));
		if($res === false)return false;
		if($res['status'] == 204 || $res['status'] == 200 || $res['status'] == 404)return true;
		return $this->gerr($res, '删除');
	}

	protected function link($name)
	{
		$id = $this->fileId($name);
		if($id === false)return false;
		$tok = $this->token();
		if($tok === false)return false;
		return [
			'url' => $this->api.'/drive/v3/files/'.rawurlencode($id).'?includeItemsFromAllDrives=true&supportsAllDrives=true&alt=media&acknowledgeAbuse=true',
			'headers' => ['Authorization'=>'Bearer '.$tok],
		];
	}
}
