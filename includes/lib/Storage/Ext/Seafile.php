<?php
namespace lib\Storage\Ext;

/**
 * Seafile（移植自 OpenList drivers/seafile）
 *
 * 用的是 Seafile 的 api2：账号密码换令牌（或直接填令牌）、按资料库 ID + 路径操作文件。
 * 存储目录的写法跟 OpenList 一样：没填资料库 ID 时，第一段是资料库名，后面是库里的路径。
 * 下载给的是 Seafile 文件服务器的一次性地址（reuse=1 可以重复用一小段时间），浏览器能直接打开。
 */
class Seafile extends Base
{
	private $address;
	private $repoId = null;
	private $dir;
	private $ready = false;

	public static function meta()
	{
		return [
			'name' => 'Seafile',
			'icon' => 'fa-cloud',
			'group' => '协议与自建服务',
			'source' => 'seafile',
			'desc' => '自建或托管的 Seafile 网盘，按资料库存文件，下载可用它给的临时直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://www.seafile.com/',
			'link_text' => '官方网站',
			'fields' => [
				['name'=>'address', 'label'=>'Seafile 地址', 'required'=>true, 'placeholder'=>'https://seafile.example.com', 'tip'=>'站点根地址，不要带 /api2。'],
				['name'=>'username', 'label'=>'账号', 'tip'=>'登录邮箱。填了下面的令牌就可以不填账号密码。'],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret'],
				['name'=>'token', 'label'=>'API 令牌', 'type'=>'secret', 'tip'=>'可不填。Seafile 网页「设置」里生成的 API Token，填了就不用账号密码登录。'],
				['name'=>'repo_id', 'label'=>'资料库 ID', 'tip'=>'可不填。填了的话下面的存储目录就是这个资料库里的路径。'],
				['name'=>'repo_pwd', 'label'=>'资料库密码', 'type'=>'secret', 'tip'=>'资料库是加密的才需要填。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/我的资料库/pan/file', 'tip'=>'没填资料库 ID 时，<b>第一段必须是资料库名</b>，例如 <b>/我的资料库/pan/file</b>；填了资料库 ID 时就是库里的路径，例如 <b>/pan/file</b>。目录不存在会自动创建。'],
			],
		];
	}

	protected function init()
	{
		$addr = rtrim(trim($this->c('address')), '/');
		if($addr !== '' && !preg_match('#^https?://#i', $addr))$addr = 'https://'.$addr;
		$this->address = $addr;
	}

	/* ---------------- 登录与请求 ---------------- */

	private function authHeader($refresh = false)
	{
		if($this->c('token') !== '')return 'Token '.$this->c('token');
		if(!$refresh && $this->state('token'))return 'Token '.$this->state('token');
		if($this->c('username') === '' || $this->c('password') === '')return $this->fail('请填写 Seafile 账号密码，或者填一个 API 令牌');
		$res = $this->json('POST', $this->address.'/api2/auth-token/', ['form'=>['username'=>$this->c('username'), 'password'=>$this->c('password')]]);
		if($res === false)return false;
		if($this->http >= 400 || empty($res['token'])){
			$msg = isset($res['non_field_errors']) ? implode('；', (array)$res['non_field_errors']) : json_encode($res, JSON_UNESCAPED_UNICODE);
			return $this->fail('Seafile 登录失败：'.$msg);
		}
		$this->saveState(['token'=>$res['token']]);
		return 'Token '.$res['token'];
	}

	//带令牌发请求；401 时重新登录一次（填的是固定令牌就不重试）
	private function api($method, $path, $opts = [])
	{
		if($this->address === '')return $this->fail('请先填写 Seafile 地址');
		$url = preg_match('#^https?://#i', $path) ? $path : $this->address.$path;
		for($i = 0; $i < 2; $i++){
			$auth = $this->authHeader($i > 0);
			if($auth === false)return false;
			$o = $opts;
			$o['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['Authorization'=>$auth, 'Accept'=>'application/json'];
			$res = $this->request($method, $url, $o);
			if($res === false)return false;
			if($res['status'] != 401 || $this->c('token') !== '')break;
		}
		return $res;
	}

	private function apiOk($res, $what)
	{
		if($res === false)return false;
		if($res['status'] >= 400){
			if($res['status'] == 404)$this->notfound = true;
			return $this->fail('Seafile '.$what.'失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		}
		return true;
	}

	/*
	 * 定位资料库和库里的目录。没填资料库 ID 时按名字在资料库列表里找，
	 * 找到的 ID 存进运行状态，下次不用再列。
	 */
	private function prepare()
	{
		if($this->ready)return true;
		$root = $this->cleanPath($this->c('root_folder_path', '/pan/file'));
		if($this->c('repo_id') !== ''){
			$this->repoId = $this->c('repo_id');
			$this->dir = $root;
		}else{
			$cached = $this->state('repo');
			if(is_array($cached) && isset($cached['root']) && $cached['root'] === $root){
				$this->repoId = $cached['id'];
				$this->dir = $cached['dir'];
			}else{
				if($root === '/')return $this->fail('没填资料库 ID 时，存储目录第一段必须是资料库名');
				$res = $this->api('GET', '/api2/repos/');
				if(!$this->apiOk($res, '读取资料库列表'))return false;
				$list = json_decode($res['body'], true);
				if(!is_array($list))return $this->fail('Seafile 资料库列表无法识别：'.$this->brief($res['body']));
				$rel = substr($root, 1);
				foreach($list as $lib){
					if(!isset($lib['name']))continue;
					if($rel === $lib['name'] || strpos($rel, $lib['name'].'/') === 0){
						$this->repoId = $lib['id'];
						$sub = substr($rel, strlen($lib['name']));
						$this->dir = $sub === '' ? '/' : $sub;
						break;
					}
				}
				if($this->repoId === null)return $this->fail('找不到存储目录 '.$root.' 对应的资料库，请确认第一段是资料库名');
				$this->saveState(['repo'=>['root'=>$root, 'id'=>$this->repoId, 'dir'=>$this->dir]]);
			}
		}
		//加密资料库要先用密码解锁，Seafile 会记住一段时间
		if($this->c('repo_pwd') !== '' && intval($this->state('unlock_at', 0)) < time() - 1800){
			$res = $this->api('POST', '/api2/repos/'.$this->repoId.'/', ['form'=>['password'=>$this->c('repo_pwd')]]);
			if(!$this->apiOk($res, '解锁加密资料库'))return false;
			if(strpos($res['body'], 'success') === false)return $this->fail('Seafile 资料库密码不正确');
			$this->saveState(['unlock_at'=>time()]);
		}
		$this->ready = true;
		return true;
	}

	private function repoPath($name)
	{
		return rtrim($this->dir, '/').'/'.$name;
	}

	//逐级建目录，已存在的跳过
	private function mkdirs()
	{
		if($this->dir === '/')return true;
		$cur = '';
		foreach(explode('/', trim($this->dir, '/')) as $seg){
			$cur .= '/'.$seg;
			$res = $this->api('GET', '/api2/repos/'.$this->repoId.'/dir/', ['query'=>['p'=>$cur]]);
			if($res !== false && $res['status'] == 200)continue;
			$res = $this->api('POST', '/api2/repos/'.$this->repoId.'/dir/', ['query'=>['p'=>$cur], 'form'=>['operation'=>'mkdir']]);
			if($res === false)return false;
			if($res['status'] >= 400 && $res['status'] != 409){
				return $this->fail('Seafile 创建目录 '.$cur.' 失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
			}
		}
		return true;
	}

	/* ---------------- 驱动接口 ---------------- */

	protected function check()
	{
		if(!$this->prepare())return false;
		return $this->mkdirs();
	}

	protected function stat($name)
	{
		$this->notfound = false;
		if(!$this->prepare())return false;
		$res = $this->api('GET', '/api2/repos/'.$this->repoId.'/file/detail/', ['query'=>['p'=>$this->repoPath($name)]]);
		if(!$this->apiOk($res, '查询文件'))return false;
		$j = json_decode($res['body'], true);
		if(!is_array($j))return $this->fail('Seafile 文件信息无法识别：'.$this->brief($res['body']));
		return ['size'=>isset($j['size']) ? intval($j['size']) : 0, 'id'=>isset($j['id']) ? $j['id'] : null];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		if(!$this->prepare())return false;
		$res = $this->api('GET', '/api2/repos/'.$this->repoId.'/upload-link/', ['query'=>['p'=>$this->dir]]);
		//目录不在的话拿上传地址这一步就会报错，先建目录再拿一次
		if($res !== false && $res['status'] >= 400){
			if(!$this->mkdirs())return false;
			$res = $this->api('GET', '/api2/repos/'.$this->repoId.'/upload-link/', ['query'=>['p'=>$this->dir]]);
		}
		if(!$this->apiOk($res, '获取上传地址'))return false;
		$url = json_decode($res['body'], true);
		if(!is_string($url) || $url === '')return $this->fail('Seafile 没有返回上传地址：'.$this->brief($res['body']));
		$res = $this->api('POST', $url, [
			'multipart' => ['file'=>new \CURLFile($tmpfile, 'application/octet-stream', $name), 'parent_dir'=>$this->dir, 'replace'=>'1'],
			'timeout' => 0,
		]);
		return $this->apiOk($res, '上传');
	}

	protected function remove($name)
	{
		if(!$this->prepare())return false;
		$res = $this->api('DELETE', '/api2/repos/'.$this->repoId.'/file/', ['query'=>['p'=>$this->repoPath($name)]]);
		if($res === false)return false;
		//文件本来就不在：Seafile 有的版本回 404，有的照样回 200
		if($res['status'] == 404)return true;
		return $this->apiOk($res, '删除');
	}

	protected function link($name)
	{
		if(!$this->prepare())return false;
		$res = $this->api('GET', '/api2/repos/'.$this->repoId.'/file/', ['query'=>['p'=>$this->repoPath($name), 'reuse'=>'1']]);
		if(!$this->apiOk($res, '获取下载地址'))return false;
		$url = json_decode($res['body'], true);
		if(!is_string($url) || $url === '')return $this->fail('Seafile 没有返回下载地址：'.$this->brief($res['body']));
		return ['url'=>$url];
	}
}
