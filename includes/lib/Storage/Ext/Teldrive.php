<?php
namespace lib\Storage\Ext;

/**
 * Teldrive（移植自 OpenList drivers/teldrive）
 *
 * 自建的 Teldrive（文件存在 Telegram 频道里）。登录态是 Cookie 里的 access_token。
 * 上传：按块 POST 到 /api/uploads/<随机 ID>，传完取回分片列表，再 POST /api/files 建文件，最后清掉上传会话。
 * OpenList 是并发传块，这里按顺序传（PHP 单进程），效果一样只是慢一点。
 * 下载：默认地址要带 Cookie，只能中转；勾了「用分享链接」会给文件建一个分享，用分享地址直链。
 */
class Teldrive extends Base
{
	private $address;
	private $root;

	public static function meta()
	{
		return [
			'name' => 'Teldrive',
			'icon' => 'fa-paper-plane',
			'group' => '协议与自建服务',
			'source' => 'teldrive',
			'desc' => '自建的 Teldrive（把 Telegram 当网盘用），上传走本站中转，开了分享链接后下载可以直链。',
			'direct' => 'auto',
			'range' => true,
			'link' => 'https://github.com/tgdrive/teldrive',
			'link_text' => '项目地址',
			'fields' => [
				['name'=>'url', 'label'=>'Teldrive 地址', 'required'=>true, 'placeholder'=>'https://teldrive.example.com'],
				['name'=>'cookie', 'label'=>'Cookie', 'type'=>'secret', 'required'=>true, 'placeholder'=>'access_token=xxx', 'tip'=>'浏览器登录 Teldrive 后 Cookie 里的 <b>access_token=...</b>，要连 <b>access_token=</b> 一起填。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file'],
				['name'=>'use_share_link', 'label'=>'下载用分享链接', 'type'=>'select', 'default'=>'0', 'options'=>['0'=>'否（下载走本站中转）', '1'=>'是（给文件建分享，能直链）'], 'tip'=>'分享按 OpenList 的做法设 1 小时有效期。'],
				['name'=>'chunk_size', 'label'=>'分块大小（MB）', 'default'=>'10'],
				['name'=>'random_chunk_name', 'label'=>'分块随机命名', 'type'=>'select', 'default'=>'1', 'options'=>['1'=>'是', '0'=>'否']],
			],
		];
	}

	public static function canDirect($cfg)
	{
		return isset($cfg['use_share_link']) && $cfg['use_share_link'] === '1';
	}

	protected function init()
	{
		$addr = rtrim(trim($this->c('url')), '/');
		if($addr !== '' && !preg_match('#^https?://#i', $addr))$addr = 'https://'.$addr;
		$this->address = $addr;
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/')), '/');
		if($this->root === '')$this->root = '/';
	}

	private function api($method, $path, $opts = [])
	{
		if($this->address === '')return $this->fail('请先填写 Teldrive 地址');
		if(strpos($this->c('cookie'), 'access_token=') !== 0)return $this->fail('Cookie 要以 access_token= 开头');
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['Cookie'=>$this->c('cookie')];
		$res = $this->request($method, $this->address.$path, $opts);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] >= 400){
			if($res['status'] == 404)$this->notfound = true;
			$msg = is_array($j) && isset($j['message']) ? $j['message'] : $this->brief($res['body']);
			return $this->fail('Teldrive 请求失败（HTTP '.$res['status'].'）：'.$msg);
		}
		return is_array($j) ? $j : [];
	}

	private function find($name)
	{
		$r = $this->api('GET', '/api/files', ['query'=>['path'=>$this->root, 'name'=>$name, 'type'=>'file', 'operation'=>'find']]);
		if($r === false)return false;
		if(empty($r['items'][0])){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		$this->mapSet($name, $r['items'][0]['id']);
		return $r['items'][0];
	}

	private function fileId($name)
	{
		$id = $this->mapGet($name);
		if($id !== null)return $id;
		$f = $this->find($name);
		return $f === false ? false : $f['id'];
	}

	protected function check()
	{
		if($this->root === '/')return true;
		return $this->api('POST', '/api/files/mkdir', ['json'=>['path'=>$this->root]]) !== false;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$f = $this->find($name);
		if($f === false)return false;
		return ['size'=>intval($f['size']), 'id'=>$f['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$uploadId = $this->uuid();
		//同名的先删掉，Teldrive 不覆盖
		$old = $this->find($name);
		if($old !== false && !$this->removeId($old['id']))return false;
		$this->notfound = false;
		if($size === 0){
			return $this->api('POST', '/api/files', ['json'=>['name'=>$name, 'type'=>'file', 'path'=>$this->root]]) !== false;
		}
		$cs = max(1, intval($this->c('chunk_size', '10'))) * 1048576;
		$total = (int)ceil($size / $cs);
		$ok = true;
		for($i = 0; $i < $total; $i++){
			$off = $i * $cs;
			$len = min($cs, $size - $off);
			if($this->c('random_chunk_name', '1') === '1')$partName = md5($this->uuid());
			else $partName = $total > 1 ? sprintf('%s.part.%03d', $name, $i + 1) : $name;
			$part = false;
			for($try = 0; $try < 3 && $part === false; $try++){
				$part = $this->api('POST', '/api/uploads/'.$uploadId, [
					'query' => ['partName'=>$partName, 'partNo'=>(string)($i + 1), 'fileName'=>$name],
					'headers' => ['Content-Type'=>'application/octet-stream'],
					'file' => $tmpfile, 'offset' => $off, 'length' => $len, 'timeout' => 0,
				]);
				if($part !== false && empty($part['name']))$part = $this->fail('Teldrive 第 '.($i + 1).' 块上传后没有返回分片信息');
			}
			if($part === false){
				$ok = false;
				break;
			}
		}
		if($ok){
			$parts = $this->api('GET', '/api/uploads/'.$uploadId);
			if($parts === false){
				$ok = false;
			}elseif(count($parts) !== $total){
				$ok = $this->fail('Teldrive 分片数对不上：应为 '.$total.'，服务器上有 '.count($parts));
			}else{
				usort($parts, function($a, $b){ return intval($a['partNo']) - intval($b['partNo']); });
				$fp = [];
				foreach($parts as $p)$fp[] = ['id'=>$p['partId'], 'salt'=>isset($p['salt']) ? $p['salt'] : ''];
				$ok = $this->api('POST', '/api/files', ['json'=>['name'=>$name, 'type'=>'file', 'path'=>$this->root, 'parts'=>$fp, 'size'=>$size]]) !== false;
			}
		}
		$err = $this->errmsg;
		$this->api('DELETE', '/api/uploads/'.$uploadId);
		if(!$ok)return $this->fail($err);
		$this->find($name);
		return true;
	}

	private function removeId($id)
	{
		return $this->api('POST', '/api/files/delete', ['json'=>['ids'=>[$id]]]) !== false;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$id = $this->fileId($name);
		if($id === false)return $this->notfound;
		return $this->removeId($id);
	}

	protected function link($name)
	{
		$id = $this->fileId($name);
		if($id === false)return false;
		if($this->c('use_share_link') === '1'){
			$share = $this->api('GET', '/api/files/'.rawurlencode($id).'/share');
			if($share === false || empty($share['id'])){
				$this->notfound = false;
				if($this->api('POST', '/api/files/'.rawurlencode($id).'/share', ['json'=>['expiresAt'=>gmdate('Y-m-d\TH:i:s.000\Z', time() + 3600)]]) === false)return false;
				$share = $this->api('GET', '/api/files/'.rawurlencode($id).'/share');
				if($share === false || empty($share['id']))return $this->fail('Teldrive 建了分享但读不到分享 ID');
			}
			return ['url'=>$this->address.'/api/shares/'.rawurlencode($share['id']).'/files/'.rawurlencode($id).'/'.rawurlencode($name)];
		}
		return ['url'=>$this->address.'/api/files/'.rawurlencode($id).'/'.rawurlencode($name), 'headers'=>['Cookie'=>$this->c('cookie')]];
	}
}
