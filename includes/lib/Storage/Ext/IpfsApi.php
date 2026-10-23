<?php
namespace lib\Storage\Ext;

/**
 * IPFS（移植自 OpenList drivers/ipfs_api）
 *
 * 走 Kubo（go-ipfs）的 RPC 接口。OpenList 有 ipfs / ipns / mfs 三种模式，但只有 mfs 能写，
 * 本站存储必须能上传删除，所以固定用 mfs：先 add 拿到 CID，再 files/cp 到 MFS 目录里，
 * 删除是 files/rm。下载给网关地址 /ipfs/<CID>?filename=。
 * 注意：files/rm 只是从 MFS 里拿掉，内容是否从节点上清理取决于节点的 pin 和垃圾回收。
 */
class IpfsApi extends Base
{
	private $api;
	private $gateway;
	private $root;

	public static function meta()
	{
		return [
			'name' => 'IPFS（MFS）',
			'icon' => 'fa-cubes',
			'group' => '协议与自建服务',
			'source' => 'ipfs_api',
			'desc' => '自建的 IPFS 节点（Kubo），文件放进 MFS 目录，下载走 IPFS 网关。',
			'direct' => true,
			'range' => true,
			'link' => 'https://docs.ipfs.tech/install/command-line/',
			'link_text' => '安装 Kubo',
			'fields' => [
				['name'=>'endpoint', 'label'=>'RPC 地址', 'required'=>true, 'default'=>'http://127.0.0.1:5001', 'tip'=>'Kubo 的 RPC 接口（默认 5001 端口）。<b>不要暴露到公网</b>，本站和节点最好在同一台机器或内网。'],
				['name'=>'gateway', 'label'=>'网关地址', 'required'=>true, 'default'=>'http://127.0.0.1:8080', 'tip'=>'下载用的 IPFS 网关。选直链下载时浏览器要能访问它，填公网网关或者你自己反代出去的地址。'],
				['name'=>'root_folder_path', 'label'=>'MFS 目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'目录不存在会自动创建。'],
			],
		];
	}

	protected function init()
	{
		$this->api = rtrim(trim($this->c('endpoint', 'http://127.0.0.1:5001')), '/');
		if($this->api !== '' && !preg_match('#^https?://#i', $this->api))$this->api = 'http://'.$this->api;
		$this->gateway = rtrim(trim($this->c('gateway', 'http://127.0.0.1:8080')), '/');
		if($this->gateway !== '' && !preg_match('#^https?://#i', $this->gateway))$this->gateway = 'http://'.$this->gateway;
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/pan/file')), '/');
	}

	private function mfsPath($name)
	{
		return $this->root.'/'.$name;
	}

	//Kubo 的 RPC 一律 POST；多个同名参数（arg）要自己拼，http_build_query 拼不出来
	private function rpc($cmd, $args = [], $opts = [])
	{
		$qs = [];
		foreach($args as $a){
			if(is_array($a))$qs[] = rawurlencode($a[0]).'='.rawurlencode($a[1]);
			else $qs[] = 'arg='.rawurlencode($a);
		}
		$url = $this->api.'/api/v0/'.$cmd.($qs ? '?'.implode('&', $qs) : '');
		if(!isset($opts['multipart']))$opts['body'] = '';
		$res = $this->request('POST', $url, $opts);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] != 200){
			$msg = is_array($j) && isset($j['Message']) ? $j['Message'] : $this->brief($res['body']);
			if(stripos($msg, 'does not exist') !== false || stripos($msg, 'not found') !== false)$this->notfound = true;
			return $this->fail('IPFS '.$cmd.' 失败：'.$msg);
		}
		return is_array($j) ? $j : [];
	}

	protected function check()
	{
		if($this->rpc('version') === false)return false;
		return $this->rpc('files/mkdir', [$this->root, ['parents', 'true']]) !== false;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$j = $this->rpc('files/stat', [$this->mfsPath($name)]);
		if($j === false)return false;
		if(isset($j['Type']) && $j['Type'] !== 'file'){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['size'=>isset($j['Size']) ? intval($j['Size']) : 0, 'id'=>isset($j['Hash']) ? $j['Hash'] : null];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$j = $this->rpc('add', [['pin', 'true']], ['multipart'=>['file'=>new \CURLFile($tmpfile, 'application/octet-stream', $name)], 'timeout'=>0]);
		if($j === false)return false;
		if(empty($j['Hash']))return $this->fail('IPFS add 没有返回 CID');
		//同名的先删，files/cp 不会覆盖
		$this->rpc('files/rm', [$this->mfsPath($name), ['force', 'true']]);
		if($this->rpc('files/cp', ['/ipfs/'.$j['Hash'], $this->mfsPath($name), ['parents', 'true']]) === false)return false;
		$this->mapSet($name, $j['Hash']);
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		if($this->rpc('files/rm', [$this->mfsPath($name)]) !== false)return true;
		return $this->notfound;
	}

	protected function link($name)
	{
		$cid = $this->mapGet($name);
		if($cid === null){
			$st = $this->stat($name);
			if($st === false)return false;
			$cid = $st['id'];
		}
		return ['url'=>$this->gateway.'/ipfs/'.rawurlencode($cid).'?filename='.rawurlencode($name)];
	}
}
