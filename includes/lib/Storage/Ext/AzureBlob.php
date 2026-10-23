<?php
namespace lib\Storage\Ext;

/**
 * Azure Blob Storage（移植自 OpenList drivers/azure_blob）
 *
 * OpenList 用的是微软的 Go SDK，这里直接按 REST 接口写：
 * 请求用 Shared Key 签名，下载给只读的 Service SAS 地址（有效期按小时设）。
 * 64MB 以内一次 Put Blob；更大的按 OpenList 的块大小（4 / 8 / 16MB）Put Block 再 Put Block List。
 * 容器不存在会自动创建（同 OpenList）。
 */
class AzureBlob extends Base
{
	const VERSION = '2021-08-06';

	private $account;
	private $base;
	private $container;
	private $prefix;

	public static function meta()
	{
		return [
			'name' => 'Azure Blob',
			'icon' => 'fa-windows',
			'group' => '对象存储与国外网盘',
			'source' => 'azure_blob',
			'desc' => '微软 Azure 的 Blob 对象存储，下载可用带签名的临时直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://azure.microsoft.com/products/storage/blobs',
			'link_text' => '官方网站',
			'fields' => [
				['name'=>'endpoint', 'label'=>'Endpoint', 'required'=>true, 'placeholder'=>'https://账户名.blob.core.windows.net/', 'tip'=>'存储账户的 Blob 服务地址，也可以只填账户名。账户名只能是小写字母和数字。'],
				['name'=>'access_key', 'label'=>'访问密钥', 'type'=>'secret', 'required'=>true, 'tip'=>'存储账户「访问密钥」里的 key1 或 key2。'],
				['name'=>'container_name', 'label'=>'容器名', 'required'=>true, 'tip'=>'不存在会自动创建。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'pan/file', 'placeholder'=>'pan/file', 'tip'=>'容器里的前缀，留空表示直接放在容器根目录。'],
				['name'=>'sign_url_expire', 'label'=>'直链有效期（小时）', 'default'=>'4'],
			],
		];
	}

	protected function init()
	{
		$ep = trim($this->c('endpoint'));
		if($ep !== '' && !preg_match('#^https?://#i', $ep) && strpos($ep, '.') === false){
			$ep = 'https://'.strtolower($ep).'.blob.core.windows.net/';
		}
		if($ep !== '' && !preg_match('#^https?://#i', $ep))$ep = 'https://'.$ep;
		$u = parse_url($ep);
		$host = isset($u['host']) ? $u['host'] : '';
		$path = isset($u['path']) ? trim($u['path'], '/') : '';
		//Azurite 这类模拟器是「主机/账户名/容器」的路径式地址，账户名在路径第一段
		if(filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost'){
			$this->account = strtolower(explode('/', $path)[0]);
		}else{
			$this->account = strtolower(explode('.', $host)[0]);
		}
		$this->base = rtrim($ep, '/');
		$this->container = trim($this->c('container_name'), "/ \\");
		$this->prefix = trim(str_replace('\\', '/', $this->c('root_folder_path', 'pan/file')), '/');
	}

	private function blobName($name)
	{
		return $this->prefix === '' ? $name : $this->prefix.'/'.$name;
	}

	private function encName($blob)
	{
		return implode('/', array_map('rawurlencode', explode('/', $blob)));
	}

	private function blobUrl($name)
	{
		return $this->base.'/'.rawurlencode($this->container).'/'.$this->encName($this->blobName($name));
	}

	private function validate()
	{
		if($this->account === '' || !preg_match('/^[a-z0-9]+$/', $this->account))return $this->fail('存储账户名只能是小写字母和数字，请检查 Endpoint');
		if($this->container === '')return $this->fail('请填写容器名');
		if(base64_decode($this->c('access_key'), true) === false || $this->c('access_key') === '')return $this->fail('访问密钥格式不对（应是一串 Base64）');
		return true;
	}

	/*
	 * Shared Key 签名的请求。$headers 里的 x-ms-* 会进签名；Content-Length / Content-Type 按规则进对应位置。
	 */
	private function call($method, $url, $headers = [], $opts = [])
	{
		if(!$this->validate())return false;
		$headers['x-ms-date'] = gmdate('D, d M Y H:i:s').' GMT';
		$headers['x-ms-version'] = self::VERSION;
		$len = '';
		if(isset($opts['length']))$len = (string)$opts['length'];
		elseif(isset($opts['body']))$len = (string)strlen($opts['body']);
		if($len === '0')$len = '';
		$ctype = isset($headers['Content-Type']) ? $headers['Content-Type'] : '';
		//没指定类型时 cURL 发请求体会自己补一个 x-www-form-urlencoded，签名里却是空的，必须显式去掉
		if($ctype === '')$headers[] = 'Content-Type:';
		$ms = [];
		foreach($headers as $k=>$v){
			$lk = strtolower($k);
			if(strpos($lk, 'x-ms-') === 0)$ms[$lk] = trim($v);
		}
		ksort($ms);
		$canonHeaders = '';
		foreach($ms as $k=>$v)$canonHeaders .= $k.':'.$v."\n";
		$u = parse_url($url);
		$resource = '/'.$this->account.(isset($u['path']) ? $u['path'] : '/');
		if(!empty($u['query'])){
			$q = [];
			foreach(explode('&', $u['query']) as $pair){
				$kv = explode('=', $pair, 2);
				$q[strtolower(rawurldecode($kv[0]))][] = isset($kv[1]) ? rawurldecode($kv[1]) : '';
			}
			ksort($q);
			foreach($q as $k=>$vals){
				sort($vals);
				$resource .= "\n".$k.':'.implode(',', $vals);
			}
		}
		$sts = $method."\n\n\n".$len."\n\n".$ctype."\n\n\n\n\n\n\n".$canonHeaders.$resource;
		$sig = base64_encode(hash_hmac('sha256', $sts, base64_decode($this->c('access_key')), true));
		$headers['Authorization'] = 'SharedKey '.$this->account.':'.$sig;
		$opts['headers'] = $headers;
		return $this->request($method, $url, $opts);
	}

	private function azErr($res)
	{
		if(preg_match('#<Code>([^<]+)</Code>#', $res['body'], $m)){
			$msg = $m[1];
			if(preg_match('#<Message>([^<\n]+)#', $res['body'], $mm))$msg .= '：'.$mm[1];
			return $msg;
		}
		return 'HTTP '.$res['status'];
	}

	protected function check()
	{
		$res = $this->call('PUT', $this->base.'/'.rawurlencode($this->container).'?restype=container', [], ['body'=>'']);
		if($res === false)return false;
		if($res['status'] == 201 || ($res['status'] == 409 && strpos($res['body'], 'ContainerAlreadyExists') !== false))return true;
		return $this->fail('创建或访问容器失败：'.$this->azErr($res));
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$res = $this->call('HEAD', $this->blobUrl($name));
		if($res === false)return false;
		if($res['status'] == 404){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		if($res['status'] != 200)return $this->fail('查询文件失败（HTTP '.$res['status'].'）');
		return ['size'=>isset($res['headers']['content-length']) ? intval($res['headers']['content-length']) : 0];
	}

	private function blockSize($size)
	{
		if($size > 1073741824)return 16777216;
		if($size > 268435456)return 8388608;
		return 4194304;
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$url = $this->blobUrl($name);
		$ct = $content_type ? $content_type : 'application/octet-stream';
		if($size <= 67108864){
			$res = $this->call('PUT', $url, ['x-ms-blob-type'=>'BlockBlob', 'Content-Type'=>$ct, 'x-ms-blob-content-type'=>$ct], ['file'=>$tmpfile, 'length'=>$size, 'timeout'=>0]);
			if($res === false)return false;
			if($res['status'] == 404 && strpos($res['body'], 'ContainerNotFound') !== false){
				if(!$this->check())return false;
				$res = $this->call('PUT', $url, ['x-ms-blob-type'=>'BlockBlob', 'Content-Type'=>$ct, 'x-ms-blob-content-type'=>$ct], ['file'=>$tmpfile, 'length'=>$size, 'timeout'=>0]);
				if($res === false)return false;
			}
			return $res['status'] == 201 ? true : $this->fail('上传失败：'.$this->azErr($res));
		}
		$bs = $this->blockSize($size);
		$ids = [];
		for($off = 0, $i = 0; $off < $size; $off += $bs, $i++){
			//块 ID 编码前必须等长
			$id = base64_encode(sprintf('block-%08d', $i));
			$len = min($bs, $size - $off);
			$res = $this->call('PUT', $url.'?comp=block&blockid='.rawurlencode($id), [], ['file'=>$tmpfile, 'offset'=>$off, 'length'=>$len, 'timeout'=>0]);
			if($res === false)return false;
			if($res['status'] != 201)return $this->fail('上传第 '.($i + 1).' 块失败：'.$this->azErr($res));
			$ids[] = $id;
		}
		$xml = '<?xml version="1.0" encoding="utf-8"?><BlockList>';
		foreach($ids as $id)$xml .= '<Latest>'.$id.'</Latest>';
		$xml .= '</BlockList>';
		$res = $this->call('PUT', $url.'?comp=blocklist', ['Content-Type'=>'application/xml', 'x-ms-blob-content-type'=>$ct], ['body'=>$xml]);
		if($res === false)return false;
		return $res['status'] == 201 ? true : $this->fail('合并分块失败：'.$this->azErr($res));
	}

	protected function remove($name)
	{
		$res = $this->call('DELETE', $this->blobUrl($name));
		if($res === false)return false;
		if($res['status'] == 202 || $res['status'] == 404)return true;
		return $this->fail('删除失败：'.$this->azErr($res));
	}

	//只读的 Service SAS（签名字段顺序按 2020-12-06 之后的版本）
	protected function link($name)
	{
		if(!$this->validate())return false;
		$hours = max(1, intval($this->c('sign_url_expire', '4')));
		$se = gmdate('Y-m-d\TH:i:s\Z', time() + $hours * 3600);
		$canon = '/blob/'.$this->account.'/'.$this->container.'/'.$this->blobName($name);
		$sts = implode("\n", ['r', '', $se, $canon, '', '', '', self::VERSION, 'b', '', '', '', '', '', '', '']);
		$sig = base64_encode(hash_hmac('sha256', $sts, base64_decode($this->c('access_key')), true));
		return ['url'=>$this->blobUrl($name).'?'.http_build_query(['sv'=>self::VERSION, 'se'=>$se, 'sr'=>'b', 'sp'=>'r', 'sig'=>$sig], '', '&', PHP_QUERY_RFC3986)];
	}
}
