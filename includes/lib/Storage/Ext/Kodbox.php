<?php
namespace lib\Storage\Ext;

/**
 * 可道云 KodBox（移植自 OpenList drivers/kodbox）
 *
 * 账号密码换 accessToken，之后每个请求在表单里带上它；业务码 "10001" 表示令牌失效，重新登录一次。
 * 可道云的文件路径是它自己的格式（{source:1}/xxx/），上传接口会把新文件的路径还回来，
 * 记在 pre_stor_map 里，删除、取信息、下载都用这个路径。
 *
 * 和 OpenList 不同：下载只走中转。可道云的下载地址把 accessToken 明文放在网址里，
 * 302 给浏览器就等于把站长的可道云令牌发给了每一个下载的人。
 */
class Kodbox extends Base
{
	private $address;
	private $dir;

	public static function meta()
	{
		return [
			'name' => '可道云 KodBox',
			'icon' => 'fa-folder',
			'group' => '协议与自建服务',
			'source' => 'kodbox',
			'desc' => '自建的可道云（KodBox）私有云，上传下载走本站中转。',
			'direct' => false,
			'range' => true,
			'link' => 'https://kodcloud.com/',
			'link_text' => '官方网站',
			'fields' => [
				['name'=>'address', 'label'=>'可道云地址', 'required'=>true, 'placeholder'=>'https://kod.example.com', 'tip'=>'站点根地址，就是浏览器里打开可道云的那个地址。'],
				['name'=>'username', 'label'=>'账号', 'required'=>true],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret', 'required'=>true],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'required'=>true, 'placeholder'=>'{source:1}/pan/', 'tip'=>'可道云内部的目录路径：在可道云里打开要用的文件夹，地址栏 <b>#/?path=</b> 后面那一段就是，例如 <b>{source:1}/</b>（个人空间根目录）。目录要先在可道云里建好。'],
			],
		];
	}

	protected function init()
	{
		$addr = rtrim(trim($this->c('address')), '/');
		if($addr !== '' && !preg_match('#^https?://#i', $addr))$addr = 'https://'.$addr;
		$this->address = $addr;
		$this->dir = ltrim(trim($this->c('root_folder_path')), '/');
	}

	private function login()
	{
		if($this->address === '')return $this->fail('请先填写可道云地址');
		$res = $this->request('POST', $this->address.'/?user/index/loginSubmit&'.http_build_query(['name'=>$this->c('username'), 'password'=>$this->c('password')]), ['body'=>'']);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] >= 400 || !is_array($j) || $j['code'] !== true || empty($j['info'])){
			return $this->fail('可道云登录失败：'.(is_array($j) && isset($j['data']) ? (is_string($j['data']) ? $j['data'] : json_encode($j['data'], JSON_UNESCAPED_UNICODE)) : $this->brief($res['body'])));
		}
		$this->saveState(['token'=>(string)$j['info']]);
		return (string)$j['info'];
	}

	/*
	 * 调接口，$form 里自动补 accessToken。返回解开的整个响应（code 为 true 时）。
	 * 上传时 $form 里有 \CURLFile，就按 multipart 发。
	 */
	private function api($action, $form = [], $timeout = 60)
	{
		if($this->address === '')return $this->fail('请先填写可道云地址');
		$token = $this->state('token');
		if(!$token && ($token = $this->login()) === false)return false;
		for($i = 0; $i < 2; $i++){
			$data = $form + ['accessToken'=>$token];
			$multipart = false;
			foreach($data as $v)if($v instanceof \CURLFile)$multipart = true;
			$res = $this->request('POST', $this->address.'/?'.$action, $multipart ? ['multipart'=>$data, 'timeout'=>$timeout] : ['form'=>$data, 'timeout'=>$timeout]);
			if($res === false)return false;
			$j = json_decode($res['body'], true);
			if(!is_array($j) || !array_key_exists('code', $j))return $this->fail('可道云返回了无法识别的内容（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
			if($j['code'] === '10001' && $i === 0){
				if(($token = $this->login()) === false)return false;
				continue;
			}
			break;
		}
		if($j['code'] !== true){
			$msg = isset($j['data']) ? (is_string($j['data']) ? $j['data'] : json_encode($j['data'], JSON_UNESCAPED_UNICODE)) : '';
			return $this->fail('可道云接口报错：'.$msg);
		}
		return $j;
	}

	//本站文件名 → 可道云路径；没记过就在存储目录里按名字找
	private function path($name)
	{
		$p = $this->mapGet($name);
		if($p !== null)return $p;
		$j = $this->api('explorer/list/path', ['path'=>$this->dir]);
		if($j === false)return false;
		$list = isset($j['data']['fileList']) && is_array($j['data']['fileList']) ? $j['data']['fileList'] : [];
		foreach($list as $f){
			if(isset($f['name']) && $f['name'] === $name){
				$this->mapSet($name, $f['path']);
				return $f['path'];
			}
		}
		$this->notfound = true;
		return $this->fail('文件不存在');
	}

	protected function check()
	{
		if($this->dir === '')return $this->fail('请填写存储目录（可道云内部路径，例如 {source:1}/）');
		if($this->login() === false)return false;
		$j = $this->api('explorer/list/path', ['path'=>$this->dir]);
		if($j === false)return $this->fail('读取存储目录失败：'.$this->errmsg.'（请确认目录已在可道云里建好、路径照地址栏 path= 后面那段填写）');
		return true;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$p = $this->path($name);
		if($p === false)return false;
		$j = $this->api('explorer/index/pathInfo', ['dataArr'=>json_encode([['path'=>$p]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
		if($j === false){
			//记着的路径失效了（在可道云里被删过）
			$this->mapDel($name);
			$this->notfound = true;
			return false;
		}
		return ['size'=>isset($j['data']['size']) ? intval($j['data']['size']) : 0, 'id'=>$p];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$j = $this->api('explorer/upload/fileUpload', ['file'=>new \CURLFile($tmpfile, 'application/octet-stream', $name), 'path'=>$this->dir], 0);
		if($j === false)return false;
		if(empty($j['info']) || !is_string($j['info']))return $this->fail('可道云上传成功但没有返回文件路径');
		$this->mapSet($name, $j['info']);
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$p = $this->path($name);
		if($p === false)return $this->notfound;
		$j = $this->api('explorer/index/pathDelete', ['dataArr'=>json_encode([['path'=>$p, 'name'=>$name]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'shiftDelete'=>'1']);
		return $j !== false;
	}

	protected function link($name)
	{
		$p = $this->path($name);
		if($p === false)return false;
		$token = $this->state('token');
		if(!$token && ($token = $this->login()) === false)return false;
		return ['url'=>$this->address.'/?explorer/index/fileOut&'.http_build_query(['path'=>$p, 'download'=>'1', 'accessToken'=>$token])];
	}

	//下载地址里带着令牌，不能发给浏览器
	public function getDownUrl($name, $filename, $content_type = null)
	{
		return $this->fail('可道云的下载地址里带着账号令牌，不能直接跳转，请把「文件下载方式」设置为网站中转');
	}
}
