<?php
namespace lib\Storage\Ext;

/**
 * Cloudreve V4（移植自 OpenList drivers/cloudreve_v4）
 *
 * 接口在 /api/v4 下，按 cloudreve://my/路径 寻址，Bearer 令牌登录：
 * 账号密码换 access_token + refresh_token，快过期（10 分钟内）就用 refresh_token 换新的，
 * 新令牌存进运行状态。也可以直接填 refresh_token / access_token（比如开了验证码、两步验证的账号）。
 * 上传按存储策略分：本机或中转（relay）、从机、OneDrive、S3、金山 KS3，其余不支持（同 OpenList）。
 */
class CloudreveV4 extends Base
{
	private $address;
	private $root;
	private $dirReady = false;
	//最近一次接口的业务码，和报错时带回来的 data（解锁要用）
	private $code = 0;
	private $lastData = null;

	public static function meta()
	{
		return [
			'name' => 'Cloudreve V4',
			'icon' => 'fa-cloud',
			'group' => '协议与自建服务',
			'source' => 'cloudreve_v4',
			'desc' => '自建的 Cloudreve 4.x 网盘，支持本机、从机、OneDrive、S3、KS3 存储策略，下载可用它给的直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://docs.cloudreve.org/',
			'link_text' => '官方文档',
			'fields' => [
				['name'=>'address', 'label'=>'Cloudreve 地址', 'required'=>true, 'placeholder'=>'https://pan.example.com', 'tip'=>'站点根地址，不要带 /api/v4。'],
				['name'=>'username', 'label'=>'邮箱'],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret'],
				['name'=>'refresh_token', 'label'=>'Refresh Token', 'type'=>'secret', 'tip'=>'可不填。账号开了登录验证码或两步验证、没法用密码登录时，填浏览器里拿到的 refresh_token。'],
				['name'=>'access_token', 'label'=>'Access Token', 'type'=>'secret', 'tip'=>'可不填。只填它的话过期后就用不了，建议填上面的 Refresh Token 或账号密码。'],
				['name'=>'custom_ua', 'label'=>'User-Agent', 'tip'=>'可不填，留空用默认的浏览器标识。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'「我的文件」里的路径，也可以写完整的 <b>cloudreve://my/pan/file</b>。目录不存在会自动创建。'],
			],
		];
	}

	protected function init()
	{
		$addr = rtrim(trim($this->c('address')), '/');
		if($addr !== '' && !preg_match('#^https?://#i', $addr))$addr = 'https://'.$addr;
		$this->address = $addr;
		$root = trim($this->c('root_folder_path', '/pan/file'));
		if(preg_match('#^cloudreve://([^/]+)(.*)$#i', $root, $m)){
			$this->root = 'cloudreve://'.$m[1].$this->encPath($m[2]);
		}else{
			$this->root = 'cloudreve://my'.$this->encPath($root);
		}
		$this->root = rtrim($this->root, '/');
	}

	//cloudreve:// 地址里的每段路径要 URL 编码，跟它前端的写法一致
	private function encPath($path)
	{
		$path = trim(str_replace('\\', '/', $path), '/');
		if($path === '')return '';
		return '/'.implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	private function uri($name)
	{
		return $this->root.'/'.rawurlencode($name);
	}

	private function ua()
	{
		return $this->c('custom_ua', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
	}

	/* ---------------- 令牌 ---------------- */

	private function tok($key)
	{
		$v = $this->state($key);
		return $v !== null ? (string)$v : $this->c($key);
	}

	private function canLogin()
	{
		return $this->c('username') !== '' && $this->c('password') !== '';
	}

	private function jwtExp($jwt)
	{
		$p = explode('.', $jwt);
		if(count($p) !== 3)return 0;
		$j = json_decode(base64_decode(strtr($p[1], '-_', '+/')), true);
		return is_array($j) && isset($j['exp']) ? intval($j['exp']) : 0;
	}

	private function saveToken($t)
	{
		$this->saveState([
			'access_token' => isset($t['access_token']) ? $t['access_token'] : '',
			'refresh_token' => isset($t['refresh_token']) ? $t['refresh_token'] : '',
			'access_expires' => isset($t['access_expires']) ? $t['access_expires'] : '',
			'refresh_expires' => isset($t['refresh_expires']) ? $t['refresh_expires'] : '',
		]);
	}

	//访问令牌 10 分钟内过期就算过期，跟 OpenList 的判断一样
	private function expired()
	{
		if($this->tok('refresh_token') === '')return $this->canLogin() && $this->tok('access_token') === '';
		if($this->tok('access_token') === '')return true;
		$exp = $this->tok('access_expires');
		$t = $exp !== '' ? strtotime($exp) : $this->jwtExp($this->tok('access_token'));
		return $t && $t < time() + 600;
	}

	private function login()
	{
		$cfg = $this->call('GET', '/site/config/login');
		if($cfg === false)return false;
		$pre = $this->call('GET', '/session/prepare?email='.rawurlencode($this->c('username')));
		if($pre === false)return false;
		if(is_array($pre) && isset($pre['password_enabled']) && !$pre['password_enabled'])return $this->fail('这个账号没有开启密码登录，请改填 Refresh Token');
		if(is_array($cfg) && !empty($cfg['login_captcha']))return $this->fail('这个 Cloudreve 站点开了登录验证码，没法用密码自动登录，请改填 Refresh Token');
		$r = $this->call('POST', '/session/token', ['email'=>$this->c('username'), 'password'=>$this->c('password')]);
		if($r === false)return false;
		if(empty($r['token']['access_token']))return $this->fail('Cloudreve 登录成功但没有返回令牌（账号可能开了两步验证）');
		$this->saveToken($r['token']);
		return true;
	}

	private function refresh()
	{
		$rt = $this->tok('refresh_token');
		if($rt === ''){
			return $this->canLogin() ? $this->login() : true;
		}
		$r = $this->call('POST', '/session/token/refresh', ['refresh_token'=>$rt]);
		if($r === false){
			//refresh_token 失效（40020）时有账号密码就重新登录
			if($this->canLogin())return $this->login();
			return $this->fail('Cloudreve 刷新令牌失败：'.$this->errmsg);
		}
		$this->saveToken($r);
		return true;
	}

	/* ---------------- 请求 ---------------- */

	//不带自动刷新的底层请求，返回 data 段，业务码记在 $this->code
	private function call($method, $path, $body = null)
	{
		$this->code = 0;
		if($this->address === '')return $this->fail('请先填写 Cloudreve 地址');
		$h = ['Accept'=>'application/json, text/plain, */*', 'User-Agent'=>$this->ua()];
		if($this->tok('access_token') !== '')$h['Authorization'] = 'Bearer '.$this->tok('access_token');
		$opts = ['headers'=>$h];
		if($body !== null)$opts['json'] = $body;
		$res = $this->request($method, $this->address.'/api/v4'.$path, $opts);
		if($res === false)return false;
		if($res['status'] < 200 || $res['status'] >= 300)return $this->fail('Cloudreve 请求失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		$j = json_decode($res['body'], true);
		if(!is_array($j) || !isset($j['code']))return $this->fail('Cloudreve 返回了无法识别的内容：'.$this->brief($res['body']));
		$this->code = intval($j['code']);
		if($this->code !== 0){
			if($this->code === 40016)$this->notfound = true;
			$this->lastData = isset($j['data']) ? $j['data'] : null;
			return $this->fail('Cloudreve 接口报错（'.$this->code.'）：'.(isset($j['msg']) ? $j['msg'] : ''));
		}
		return array_key_exists('data', $j) ? $j['data'] : null;
	}

	private function api($method, $path, $body = null, $retry = true)
	{
		if($this->expired() && !$this->refresh())return false;
		if($this->tok('access_token') === '' && !$this->canLogin()){
			return $this->fail('请填写账号密码，或者填 Refresh Token / Access Token');
		}
		$r = $this->call($method, $path, $body);
		if($r === false && $this->code === 401 && $retry && $this->canLogin()){
			if(!$this->login())return false;
			return $this->api($method, $path, $body, false);
		}
		return $r;
	}

	/* ---------------- 目录 ---------------- */

	private function ensureDir()
	{
		if($this->dirReady)return true;
		if($this->api('GET', '/file/info?uri='.rawurlencode($this->root)) !== false){
			$this->dirReady = true;
			return true;
		}
		if(!$this->notfound)return false;
		if(!preg_match('#^(cloudreve://[^/]+)(.*)$#', $this->root, $m))return false;
		$cur = $m[1];
		foreach(explode('/', trim($m[2], '/')) as $seg){
			if($seg === '')continue;
			$cur .= '/'.$seg;
			$r = $this->api('POST', '/file/create', ['type'=>'folder', 'uri'=>$cur, 'error_on_conflict'=>true]);
			if($r === false && $this->code !== 40004)return $this->fail('Cloudreve 创建目录失败：'.$this->errmsg);
		}
		$this->dirReady = true;
		return true;
	}

	/* ---------------- 驱动接口 ---------------- */

	protected function check()
	{
		return $this->ensureDir();
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$f = $this->api('GET', '/file/info?uri='.rawurlencode($this->uri($name)));
		if($f === false)return false;
		if(!is_array($f) || (isset($f['type']) && intval($f['type']) === 1)){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['size'=>intval($f['size']), 'id'=>isset($f['id']) ? $f['id'] : null];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		if(!$this->ensureDir())return false;
		if($size === 0){
			$r = $this->api('POST', '/file/create', ['type'=>'file', 'uri'=>$this->uri($name), 'error_on_conflict'=>true]);
			return $r !== false;
		}
		$list = $this->api('GET', '/file?'.http_build_query(['page_size'=>'10', 'uri'=>$this->root, 'order_by'=>'created_at', 'order_direction'=>'asc', 'page'=>'0']));
		if($list === false)return false;
		$policy = isset($list['storage_policy']) ? $list['storage_policy'] : [];
		$body = [
			'uri' => $this->uri($name),
			'size' => $size,
			'policy_id' => isset($policy['id']) ? $policy['id'] : '',
			'last_modified' => intval(round(microtime(true) * 1000)),
			'mime_type' => '',
		];
		$u = $this->api('PUT', '/file/upload', $body);
		//同名文件已存在（V4 默认不覆盖）：先删掉旧的再传
		if($u === false && $this->code === 40004){
			if(!$this->remove($name))return false;
			$u = $this->api('PUT', '/file/upload', $body);
		}
		if($u === false)return false;
		$p = isset($u['storage_policy']) ? $u['storage_policy'] : [];
		$type = !empty($p['relay']) ? 'local' : (isset($p['type']) ? $p['type'] : '');
		switch($type){
			case 'local': $ok = $this->upLocal($tmpfile, $size, $u); break;
			case 'remote': $ok = $this->upRemote($tmpfile, $size, $u); break;
			case 'onedrive': $ok = $this->upOneDrive($tmpfile, $size, $u); break;
			case 's3':
			case 'ks3': $ok = $this->upS3($tmpfile, $size, $u, $type); break;
			default: $ok = $this->fail('这个存储目录用的存储策略是「'.$type.'」，OpenList 和本站都只支持本机、从机、OneDrive、S3、KS3');
		}
		if(!$ok){
			$err = $this->errmsg;
			$this->api('DELETE', '/file/upload', ['id'=>$u['session_id'], 'uri'=>isset($u['uri']) ? $u['uri'] : $this->uri($name)]);
			return $this->fail($err);
		}
		return true;
	}

	private function chunks($size, $u)
	{
		$cs = isset($u['chunk_size']) && intval($u['chunk_size']) > 0 ? intval($u['chunk_size']) : $size;
		$list = [];
		for($off = 0; $off < $size; $off += $cs)$list[] = [$off, min($cs, $size - $off)];
		return $list;
	}

	private function upLocal($tmpfile, $size, $u)
	{
		foreach($this->chunks($size, $u) as $i=>$c){
			if($this->expired() && !$this->refresh())return false;
			$res = $this->request('POST', $this->address.'/api/v4/file/upload/'.$u['session_id'].'/'.$i, [
				'headers' => ['Authorization'=>'Bearer '.$this->tok('access_token'), 'Content-Type'=>'application/octet-stream', 'User-Agent'=>$this->ua()],
				'file' => $tmpfile, 'offset' => $c[0], 'length' => $c[1], 'timeout' => 0,
			]);
			if($res === false)return false;
			$j = json_decode($res['body'], true);
			if($res['status'] != 200 || !is_array($j) || intval($j['code']) !== 0){
				return $this->fail('Cloudreve 上传第 '.($i + 1).' 片失败：'.(is_array($j) && isset($j['msg']) ? $j['msg'] : $this->brief($res['body'])));
			}
		}
		return true;
	}

	private function upRemote($tmpfile, $size, $u)
	{
		$url = $u['upload_urls'][0];
		foreach($this->chunks($size, $u) as $i=>$c){
			$res = $this->request('POST', $url.(strpos($url, '?') === false ? '?' : '&').'chunk='.$i, [
				'headers' => ['Authorization'=>(string)$u['credential'], 'User-Agent'=>$this->ua()],
				'file' => $tmpfile, 'offset' => $c[0], 'length' => $c[1], 'timeout' => 0,
			]);
			if($res === false)return false;
			$j = json_decode($res['body'], true);
			if($res['status'] != 200 || !is_array($j) || intval($j['code']) !== 0){
				return $this->fail('Cloudreve 从机上传第 '.($i + 1).' 片失败：'.(is_array($j) && isset($j['msg']) ? $j['msg'] : 'HTTP '.$res['status']));
			}
		}
		return true;
	}

	private function upOneDrive($tmpfile, $size, $u)
	{
		$url = $u['upload_urls'][0];
		foreach($this->chunks($size, $u) as $c){
			$res = $this->request('PUT', $url, [
				'headers' => ['Content-Range'=>'bytes '.$c[0].'-'.($c[0] + $c[1] - 1).'/'.$size, 'User-Agent'=>$this->ua()],
				'file' => $tmpfile, 'offset' => $c[0], 'length' => $c[1], 'timeout' => 0,
			]);
			if($res === false)return false;
			if(!in_array($res['status'], [200, 201, 202], true))return $this->fail('OneDrive 分片上传失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		}
		return $this->api('POST', '/callback/onedrive/'.$u['session_id'].'/'.$u['callback_secret'], new \stdClass()) !== false;
	}

	private function upS3($tmpfile, $size, $u, $type)
	{
		$etags = [];
		foreach($this->chunks($size, $u) as $i=>$c){
			if(empty($u['upload_urls'][$i]))return $this->fail('Cloudreve 给的分片地址不够用');
			$h = ['User-Agent'=>$this->ua()];
			if($type === 'ks3')$h['Content-Type'] = 'application/octet-stream';
			$res = $this->request('PUT', $u['upload_urls'][$i], ['headers'=>$h, 'file'=>$tmpfile, 'offset'=>$c[0], 'length'=>$c[1], 'timeout'=>0]);
			if($res === false)return false;
			if($res['status'] != 200)return $this->fail('分片上传失败（HTTP '.$res['status'].'）');
			if(empty($res['headers']['etag']))return $this->fail('分片上传后没有返回 ETag');
			$etags[] = $res['headers']['etag'];
		}
		$xml = '<CompleteMultipartUpload>';
		foreach($etags as $i=>$e)$xml .= '<Part><PartNumber>'.($i + 1).'</PartNumber><ETag>'.$e.'</ETag></Part>';
		$xml .= '</CompleteMultipartUpload>';
		$res = $this->request('POST', $u['completeURL'], ['headers'=>['Content-Type'=>$type === 'ks3' ? 'application/octet-stream' : 'application/xml', 'User-Agent'=>$this->ua()], 'body'=>$xml]);
		if($res === false)return false;
		if($res['status'] != 200)return $this->fail('合并分片失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		return $this->api('GET', '/callback/'.$type.'/'.$u['session_id'].'/'.$u['callback_secret']) !== false;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$body = ['uris'=>[$this->uri($name)], 'unlink'=>false, 'skip_soft_delete'=>true];
		$r = $this->api('DELETE', '/file', $body);
		if($r !== false)return true;
		if($this->notfound)return true;
		//文件被锁（比如还有没结束的上传会话）：先解锁再删，跟 OpenList 一样
		if($this->code === 40073 && is_array($this->lastData)){
			$tokens = [];
			foreach($this->lastData as $item)if(isset($item['token']))$tokens[] = $item['token'];
			if($tokens && $this->api('DELETE', '/file/lock', ['tokens'=>$tokens]) !== false){
				return $this->api('DELETE', '/file', $body) !== false;
			}
		}
		return false;
	}

	protected function link($name)
	{
		$r = $this->api('POST', '/file/url', ['uris'=>[$this->uri($name)], 'download'=>true]);
		if($r === false)return false;
		if(empty($r['urls'][0]['url']))return $this->fail('Cloudreve 没有返回下载地址');
		return ['url'=>$r['urls'][0]['url'], 'headers'=>['Referer'=>$this->address, 'User-Agent'=>$this->ua()]];
	}

	//同 v3：Referer、UA 是 OpenList 中转时带的，地址本身浏览器可以直接打开
	public function getDownUrl($name, $filename, $content_type = null)
	{
		$link = $this->link($name);
		return $link === false ? false : $link['url'];
	}
}
