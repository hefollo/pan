<?php
namespace lib\Storage\Ext;

/**
 * Cloudreve v3（移植自 OpenList drivers/cloudreve）
 *
 * 接口在 /api/v3 下，登录态是 cloudreve-session 这个 Cookie。
 * 上传先读目录拿到存储策略，再按策略类型分片：本机（local）、从机（remote）、OneDrive、S3，
 * 跟 OpenList 一样只支持这四种，别的策略（OSS、COS、七牛、又拍云）会报不支持。
 * 文件按 ID 操作，本站文件名 → ID 存 pre_stor_map，查不到就列目录找。
 * OpenList 的登录验证码靠外部 OCR 服务识别，这里不做：站点开了登录验证码的请直接填 Cookie。
 */
class Cloudreve extends Base
{
	private $address;
	private $dir;
	private $dirReady = false;

	public static function meta()
	{
		return [
			'name' => 'Cloudreve（v3）',
			'icon' => 'fa-cloud',
			'group' => '协议与自建服务',
			'source' => 'cloudreve',
			'desc' => '自建的 Cloudreve 3.x 网盘，支持本机、从机、OneDrive、S3 存储策略，下载可用它给的直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://docs.cloudreve.org/',
			'link_text' => '官方文档',
			'fields' => [
				['name'=>'address', 'label'=>'Cloudreve 地址', 'required'=>true, 'placeholder'=>'https://pan.example.com', 'tip'=>'站点根地址，不要带 /api/v3。Cloudreve 4.x 请选「Cloudreve V4」。'],
				['name'=>'username', 'label'=>'账号', 'tip'=>'登录邮箱。'],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret'],
				['name'=>'cookie', 'label'=>'会话 Cookie', 'type'=>'secret', 'tip'=>'可不填。浏览器登录后 Cookie 里 <b>cloudreve-session</b> 的值；站点开了登录验证码时必须填这个。'],
				['name'=>'custom_ua', 'label'=>'User-Agent', 'tip'=>'可不填，留空用默认的浏览器标识。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'网盘里的路径，目录不存在会自动创建。'],
			],
		];
	}

	protected function init()
	{
		$addr = rtrim(trim($this->c('address')), '/');
		if($addr !== '' && !preg_match('#^https?://#i', $addr))$addr = 'https://'.$addr;
		$this->address = $addr;
		$this->dir = $this->cleanPath($this->c('root_folder_path', '/pan/file'));
	}

	private function ua()
	{
		return $this->c('custom_ua', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
	}

	private function session()
	{
		$s = $this->state('cookie');
		return $s ? $s : $this->c('cookie');
	}

	/*
	 * 调 /api/v3 接口，返回 data 段。业务码 401 时用账号密码重新登录一次。
	 * 响应里带了新的 cloudreve-session 就存下来，跟 OpenList 一样一直用最新的那个。
	 */
	private function api($method, $path, $body = null, $retry = true)
	{
		if($this->address === '')return $this->fail('请先填写 Cloudreve 地址');
		$opts = ['headers'=>[
			'Cookie' => 'cloudreve-session='.$this->session(),
			'Accept' => 'application/json, text/plain, */*',
			'User-Agent' => $this->ua(),
		]];
		if($body !== null)$opts['json'] = $body;
		$res = $this->request($method, $this->address.'/api/v3'.$path, $opts);
		if($res === false)return false;
		if($res['status'] < 200 || $res['status'] >= 300)return $this->fail('Cloudreve 请求失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		$j = json_decode($res['body'], true);
		if(!is_array($j) || !isset($j['code']))return $this->fail('Cloudreve 返回了无法识别的内容：'.$this->brief($res['body']));
		if(intval($j['code']) !== 0){
			if(intval($j['code']) === 401 && $retry && $path !== '/user/session' && $this->c('username') !== '' && $this->c('password') !== ''){
				if(!$this->login())return false;
				return $this->api($method, $path, $body, false);
			}
			if(intval($j['code']) === 404 || intval($j['code']) === 40016)$this->notfound = true;
			return $this->fail('Cloudreve 接口报错（'.$j['code'].'）：'.(isset($j['msg']) ? $j['msg'] : ''));
		}
		if(isset($res['cookies']['cloudreve-session']) && $res['cookies']['cloudreve-session'] !== $this->session()){
			$this->saveState(['cookie'=>$res['cookies']['cloudreve-session']]);
		}
		return array_key_exists('data', $j) ? $j['data'] : null;
	}

	private function login()
	{
		$cfg = $this->api('GET', '/site/config', null, false);
		if($cfg === false)return false;
		if(is_array($cfg) && !empty($cfg['loginCaptcha'])){
			return $this->fail('这个 Cloudreve 站点开了登录验证码，没法用账号密码自动登录，请在浏览器里登录后把 cloudreve-session 的值填进「会话 Cookie」');
		}
		$r = $this->api('POST', '/user/session', ['username'=>$this->c('username'), 'Password'=>$this->c('password'), 'captchaCode'=>''], false);
		return $r !== false;
	}

	/* ---------------- 目录与查找 ---------------- */

	private function listDir($dir)
	{
		$segs = array_map('rawurlencode', explode('/', ltrim($dir, '/')));
		return $this->api('GET', '/directory/'.implode('/', $segs));
	}

	private function ensureDir()
	{
		if($this->dirReady)return true;
		if($this->session() === '' && $this->c('username') !== '' && !$this->login())return false;
		$r = $this->listDir($this->dir);
		if($r !== false){
			$this->dirReady = true;
			return true;
		}
		//逐级建，已存在的那一级会报「Object existed」，不管它
		$cur = '';
		foreach(explode('/', trim($this->dir, '/')) as $seg){
			$cur .= '/'.$seg;
			$this->api('PUT', '/directory', ['path'=>$cur]);
		}
		$r = $this->listDir($this->dir);
		if($r === false)return $this->fail('Cloudreve 创建存储目录 '.$this->dir.' 失败：'.$this->errmsg);
		$this->dirReady = true;
		return true;
	}

	//在存储目录里按名字找文件，找到顺便记下 ID
	private function find($name)
	{
		$r = $this->listDir($this->dir);
		if($r === false)return false;
		if(!empty($r['objects'])){
			foreach($r['objects'] as $o){
				if($o['name'] === $name && $o['type'] !== 'dir'){
					$this->mapSet($name, $o['id']);
					return $o;
				}
			}
		}
		$this->notfound = true;
		return $this->fail('文件不存在');
	}

	private function fileId($name)
	{
		$id = $this->mapGet($name);
		if($id !== null)return $id;
		$o = $this->find($name);
		return $o === false ? false : $o['id'];
	}

	/* ---------------- 驱动接口 ---------------- */

	protected function check()
	{
		if($this->session() === '' && ($this->c('username') === '' || $this->c('password') === '')){
			return $this->fail('请填写账号密码，或者填一个会话 Cookie');
		}
		return $this->ensureDir();
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$id = $this->mapGet($name);
		if($id !== null){
			$p = $this->api('GET', '/object/property/'.rawurlencode($id).'?trace_root=false&is_folder=false');
			if($p !== false && is_array($p))return ['size'=>isset($p['size']) ? intval($p['size']) : 0, 'id'=>$id];
			//记着的 ID 失效了（网盘里被删过），丢掉再按名字找
			$this->mapDel($name);
			$this->notfound = false;
		}
		$o = $this->find($name);
		if($o === false)return false;
		return ['size'=>intval($o['size']), 'id'=>$o['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		if(!$this->ensureDir())return false;
		$dir = $this->listDir($this->dir);
		if($dir === false)return false;
		$policy = isset($dir['policy']) ? $dir['policy'] : [];
		$u = $this->api('PUT', '/file/upload', [
			'path' => $this->dir,
			'size' => $size,
			'name' => $name,
			'policy_id' => isset($policy['id']) ? $policy['id'] : '',
			'last_modified' => intval(round(microtime(true) * 1000)),
		]);
		if($u === false)return false;
		$type = isset($policy['type']) ? $policy['type'] : '';
		switch($type){
			case 'local': $ok = $this->upLocal($tmpfile, $size, $u); break;
			case 'remote': $ok = $this->upRemote($tmpfile, $size, $u); break;
			case 'onedrive': $ok = $this->upOneDrive($tmpfile, $size, $u); break;
			case 's3': $ok = $this->upS3($tmpfile, $size, $u); break;
			default: $ok = $this->fail('这个存储目录用的存储策略是「'.$type.'」，OpenList 和本站都只支持本机、从机、OneDrive、S3 四种');
		}
		if(!$ok){
			$err = $this->errmsg;
			$this->api('DELETE', '/file/upload/'.$u['sessionID']);
			return $this->fail($err);
		}
		//上传接口不返回文件 ID，列一次目录记下来
		$this->find($name);
		return true;
	}

	private function chunks($size, $u)
	{
		$cs = isset($u['chunkSize']) && intval($u['chunkSize']) > 0 ? intval($u['chunkSize']) : $size;
		$list = [];
		for($off = 0; $off < $size || ($size === 0 && $off === 0); $off += $cs){
			$list[] = [$off, min($cs, $size - $off)];
			if($size === 0)break;
		}
		return $list;
	}

	private function upLocal($tmpfile, $size, $u)
	{
		foreach($this->chunks($size, $u) as $i=>$c){
			$res = $this->request('POST', $this->address.'/api/v3/file/upload/'.$u['sessionID'].'/'.$i, [
				'headers' => ['Cookie'=>'cloudreve-session='.$this->session(), 'Content-Type'=>'application/octet-stream', 'User-Agent'=>$this->ua()],
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
		$url = $u['uploadURLs'][0];
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
		$url = $u['uploadURLs'][0];
		foreach($this->chunks($size, $u) as $i=>$c){
			$res = $this->request('PUT', $url, [
				'headers' => ['Content-Range'=>'bytes '.$c[0].'-'.($c[0] + $c[1] - 1).'/'.$size, 'User-Agent'=>$this->ua()],
				'file' => $tmpfile, 'offset' => $c[0], 'length' => $c[1], 'timeout' => 0,
			]);
			if($res === false)return false;
			if(!in_array($res['status'], [200, 201, 202], true))return $this->fail('OneDrive 分片上传失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		}
		return $this->api('POST', '/callback/onedrive/finish/'.$u['sessionID'], new \stdClass()) !== false;
	}

	private function upS3($tmpfile, $size, $u)
	{
		$etags = [];
		foreach($this->chunks($size, $u) as $i=>$c){
			if(empty($u['uploadURLs'][$i]))return $this->fail('Cloudreve 给的 S3 分片地址不够用');
			$res = $this->request('PUT', $u['uploadURLs'][$i], [
				'headers' => ['User-Agent'=>$this->ua()],
				'file' => $tmpfile, 'offset' => $c[0], 'length' => $c[1], 'timeout' => 0,
			]);
			if($res === false)return false;
			if($res['status'] != 200)return $this->fail('S3 分片上传失败（HTTP '.$res['status'].'）');
			if(empty($res['headers']['etag']))return $this->fail('S3 分片上传后没有返回 ETag');
			$etags[] = $res['headers']['etag'];
		}
		$xml = '<CompleteMultipartUpload>';
		foreach($etags as $i=>$e)$xml .= '<Part><PartNumber>'.($i + 1).'</PartNumber><ETag>'.$e.'</ETag></Part>';
		$xml .= '</CompleteMultipartUpload>';
		$res = $this->request('POST', $u['completeURL'], ['headers'=>['Content-Type'=>'application/xml', 'User-Agent'=>$this->ua()], 'body'=>$xml]);
		if($res === false)return false;
		if($res['status'] != 200)return $this->fail('S3 合并分片失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		return $this->api('GET', '/callback/s3/'.$u['sessionID']) !== false;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$id = $this->fileId($name);
		if($id === false)return $this->notfound ? true : false;
		$r = $this->api('DELETE', '/object', ['items'=>[$id], 'dirs'=>[]]);
		return $r !== false;
	}

	protected function link($name)
	{
		$id = $this->fileId($name);
		if($id === false)return false;
		$url = $this->api('PUT', '/file/download/'.rawurlencode($id));
		if($url === false)return false;
		if(!is_string($url) || $url === '')return $this->fail('Cloudreve 没有返回下载地址');
		if(strpos($url, '/api') === 0)$url = $this->address.$url;
		return ['url'=>$url, 'headers'=>['Referer'=>$this->address, 'User-Agent'=>$this->ua()]];
	}

	//OpenList 取直链时带上 Referer 和 UA；直接跳转给浏览器时这两个头由浏览器自己带，地址本身不需要它们
	public function getDownUrl($name, $filename, $content_type = null)
	{
		$link = $this->link($name);
		return $link === false ? false : $link['url'];
	}
}
