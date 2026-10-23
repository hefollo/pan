<?php
namespace lib\Storage\Ext;

/**
 * Bunny Storage（移植自 OpenList drivers/bunny_storage）
 *
 * 存储区的 HTTP API：请求头带 AccessKey，PUT 上传、DELETE 删除、GET 下载，列目录拿文件大小。
 * 填了拉取区（CDN）地址时下载给 CDN 地址，还能按 Bunny 的 Token Authentication 签名（sha256 或 HS256）；
 * 没填 CDN 地址时，存储区的下载要带 AccessKey，只能走中转（同 OpenList 的 OnlyProxy）。
 */
class BunnyStorage extends Base
{
	private $endpoint;
	private $cdn;
	private $root;

	public static function meta()
	{
		return [
			'name' => 'Bunny Storage',
			'icon' => 'fa-cloud',
			'group' => '对象存储与国外网盘',
			'source' => 'bunny_storage',
			'desc' => 'Bunny.net 的边缘存储，配上拉取区（CDN）后下载可以直接走 CDN。',
			'direct' => 'auto',
			'range' => true,
			'link' => 'https://bunny.net/storage/',
			'link_text' => '官方网站',
			'fields' => [
				['name'=>'storage_zone_name', 'label'=>'存储区名称', 'required'=>true],
				['name'=>'access_key', 'label'=>'存储区密码', 'type'=>'secret', 'required'=>true, 'tip'=>'存储区「FTP &amp; API Access」里的 Password（不是账户的 API Key）。'],
				['name'=>'endpoint', 'label'=>'存储区接口地址', 'default'=>'storage.bunnycdn.com', 'tip'=>'按存储区主区域填，例如纽约是 <b>ny.storage.bunnycdn.com</b>，默认（德国）是 <b>storage.bunnycdn.com</b>。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file'],
				['name'=>'cdn_base_url', 'label'=>'拉取区地址', 'placeholder'=>'https://xxx.b-cdn.net', 'tip'=>'可不填。填了下载就直接跳到 CDN；不填只能走本站中转。'],
				['name'=>'cdn_token_key', 'label'=>'拉取区 Token 密钥', 'type'=>'secret', 'tip'=>'拉取区开了 Token Authentication 才需要填。'],
				['name'=>'cdn_token_method', 'label'=>'Token 签名方式', 'type'=>'select', 'default'=>'sha256', 'options'=>['sha256'=>'SHA256（旧版）', 'hmac_sha256'=>'HMAC-SHA256（HS256）']],
				['name'=>'sign_url_expire', 'label'=>'签名有效期（小时）', 'default'=>'4'],
			],
		];
	}

	public static function canDirect($cfg)
	{
		return isset($cfg['cdn_base_url']) && trim($cfg['cdn_base_url']) !== '';
	}

	private function normBase($raw, $fallback)
	{
		$raw = trim($raw);
		if($raw === '')$raw = $fallback;
		if($raw === '')return '';
		if(strpos($raw, '://') === false)$raw = 'https://'.$raw;
		return rtrim($raw, '/');
	}

	protected function init()
	{
		$this->endpoint = $this->normBase($this->c('endpoint'), 'storage.bunnycdn.com');
		$this->cdn = $this->normBase($this->c('cdn_base_url'), '');
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/')), '/');
	}

	private function encPath($p)
	{
		return implode('/', array_map('rawurlencode', explode('/', ltrim($p, '/'))));
	}

	private function storageUrl($path, $dir = false)
	{
		$zone = trim($this->c('storage_zone_name'), '/');
		$p = $this->encPath($path);
		return $this->endpoint.'/'.rawurlencode($zone).'/'.$p.($dir && $p !== '' ? '/' : '');
	}

	private function bunnyErr($res)
	{
		$j = json_decode($res['body'], true);
		if(is_array($j) && isset($j['Message']))return $j['Message'];
		if(is_array($j) && isset($j[0]['Message']))return $j[0]['Message'];
		if($res['status'] == 401 || $res['status'] == 403)return '存储区名称或密码不对（HTTP '.$res['status'].'）';
		return 'HTTP '.$res['status'].' '.$this->brief($res['body']);
	}

	private function call($method, $url, $opts = [])
	{
		if($this->c('storage_zone_name') === '' || $this->c('access_key') === '')return $this->fail('请填写存储区名称和密码');
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['AccessKey'=>$this->c('access_key')];
		return $this->request($method, $url, $opts);
	}

	protected function check()
	{
		$res = $this->call('GET', $this->storageUrl($this->root, true));
		if($res === false)return false;
		//目录不存在时 Bunny 列出来是空数组，不报错；第一次上传会自动建出来
		if($res['status'] == 200)return true;
		return $this->fail('访问存储区失败：'.$this->bunnyErr($res));
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$res = $this->call('GET', $this->storageUrl($this->root, true));
		if($res === false)return false;
		if($res['status'] != 200)return $this->fail('列目录失败：'.$this->bunnyErr($res));
		$list = json_decode($res['body'], true);
		if(is_array($list)){
			foreach($list as $o){
				if(isset($o['ObjectName']) && $o['ObjectName'] === $name && empty($o['IsDirectory'])){
					return ['size'=>intval($o['Length'])];
				}
			}
		}
		$this->notfound = true;
		return $this->fail('文件不存在');
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$res = $this->call('PUT', $this->storageUrl($this->root.'/'.$name), [
			'headers' => ['Content-Type'=>$content_type ? $content_type : 'application/octet-stream'],
			'file' => $tmpfile, 'length' => $size, 'timeout' => 0,
		]);
		if($res === false)return false;
		return ($res['status'] >= 200 && $res['status'] < 300) ? true : $this->fail('上传失败：'.$this->bunnyErr($res));
	}

	protected function remove($name)
	{
		$res = $this->call('DELETE', $this->storageUrl($this->root.'/'.$name));
		if($res === false)return false;
		if(($res['status'] >= 200 && $res['status'] < 300) || $res['status'] == 404)return true;
		return $this->fail('删除失败：'.$this->bunnyErr($res));
	}

	protected function link($name)
	{
		if($this->cdn === ''){
			return ['url'=>$this->storageUrl($this->root.'/'.$name), 'headers'=>['AccessKey'=>$this->c('access_key')]];
		}
		$path = $this->root.'/'.$name;
		$url = $this->cdn.'/'.$this->encPath($path);
		if($this->c('cdn_token_key') !== ''){
			$hours = intval($this->c('sign_url_expire', '4'));
			if($hours <= 0)$hours = 4;
			$expires = (string)(time() + $hours * 3600);
			//签名用的是解码后的路径，连同拉取区地址里自带的前缀
			$basePath = (string)parse_url($this->cdn, PHP_URL_PATH);
			$sigPath = rtrim($basePath, '/').'/'.ltrim($path, '/');
			if(strtolower($this->c('cdn_token_method', 'sha256')) === 'hmac_sha256'){
				$token = 'HS256-'.rtrim(strtr(base64_encode(hash_hmac('sha256', $sigPath.$expires, $this->c('cdn_token_key'), true)), '+/', '-_'), '=');
			}else{
				$token = rtrim(strtr(base64_encode(hash('sha256', $this->c('cdn_token_key').$sigPath.$expires, true)), '+/', '-_'), '=');
			}
			$url .= '?'.http_build_query(['expires'=>$expires, 'token'=>$token]);
		}
		return ['url'=>$url];
	}
}
