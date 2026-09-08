<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * OpenList 存储驱动（兼容 AList v3 的 API）
 * OpenList 本身是个聚合网盘，后面可以挂阿里云盘、百度网盘、OneDrive、本地磁盘等等，
 * 本站只跟它的 HTTP 接口打交道，底层挂的是什么由它自己处理。
 *
 * 上传：PUT /api/fs/put，走本站中转（它的直传是 PUT 裸流，跟前端那套 POST 表单直传对不上）。
 * 下载：POST /api/fs/get 拿到 raw_url 后 302 过去，不消耗本站流量。
 *
 * 接口的约定和一般 REST 不一样：不管成功失败 HTTP 状态码基本都是 200，
 * 真正的结果在响应体的 code 字段里，所以这里必须解完 JSON 才能判断成败。
 */
class Openlist implements IStorage
{
	private $baseurl;
	private $user;
	private $pass;
	//站长自己填的固定令牌，填了就不用账号密码登录
	private $fixed;
	//以 / 开头、不带结尾斜杠的存储目录，例如 /aliyun/pan/file
	private $filepath;
	private $errmsg;
	//最近一次请求的 HTTP 状态码和接口业务码，401 重登、目录缺失补建都靠它判断
	private $http = 0;
	private $code = 0;
	private $token = null;
	//目录只在第一次写失败时补建，正常上传不额外多一次 mkdir 请求
	private $dirchecked = false;
	//服务端默认发 48 小时的令牌，本站按 24 小时缓存，到点重新登录换一张
	const TOKEN_TTL = 86400;

	public function __construct($config)
	{
		$url = isset($config['url']) ? trim($config['url']) : '';
		if($url !== '' && !preg_match('#^https?://#i', $url)){
			$url = 'https://'.$url;
		}
		$this->baseurl = $url === '' ? '' : rtrim($url, '/').'/';
		$this->user = isset($config['user']) ? trim($config['user']) : '';
		$this->pass = isset($config['pass']) ? $config['pass'] : '';
		$this->fixed = isset($config['token']) ? trim($config['token']) : '';
		$path = isset($config['path']) ? trim($config['path'], " \t\n\r\0\x0B/") : '';
		$this->filepath = '/'.($path === '' ? 'pan/file' : $path);
	}

	public function getClient()
	{
		return $this;
	}

	public function errmsg()
	{
		return $this->errmsg;
	}

	public function exists($name)
	{
		return $this->item($name, true) !== false;
	}

	public function get($name)
	{
		$url = $this->getDownUrl($name, $name);
		if($url === false)return false;
		$res = $this->fetch($url);
		return $res === false ? false : $res['body'];
	}

	public function downfile($name, $range = false)
	{
		$url = $this->getDownUrl($name, $name);
		if($url === false)return false;
		$headers = [];
		if($range){
			$headers[] = 'Range: bytes='.intval($range[0]).'-'.intval($range[1]);
		}
		//边收边吐，不把整个文件读进内存
		return $this->fetch($url, $headers, ['stream'=>true, 'timeout'=>0]) !== false;
	}

	public function upload($name, $tmpfile, $content_type = null)
	{
		$res = $this->put($name, $tmpfile, $content_type);
		//目录不存在时接口回的是 object not found，补建一次再传
		if($res === false && !$this->dirchecked && $this->looksLikeMissingDir()){
			if($this->makeDir())$res = $this->put($name, $tmpfile, $content_type);
		}
		return $res !== false;
	}

	public function savefile($name, $tmpfile, $content_type = null)
	{
		$result = $this->upload($name, $tmpfile, $content_type);
		if($result)@unlink($tmpfile);
		return $result;
	}

	public function getinfo($name)
	{
		$item = $this->item($name);
		if($item === false)return false;
		//接口只给大小不给 MIME，交给调用方按扩展名自己判断
		return ['length'=>isset($item['size']) ? intval($item['size']) : 0, 'content_type'=>null];
	}

	public function delete($name)
	{
		return $this->api('POST', 'api/fs/remove', ['dir'=>$this->filepath, 'names'=>[$name]]) !== false;
	}

	public function getUploadParam($name, $filename, $max_file_size = 0)
	{
		//OpenList 的上传是 PUT 裸流 + File-Path 头，浏览器直传发的是 POST 表单，对不上
		$this->errmsg = 'OpenList 不支持直传，请把「文件上传方式」设置为网站中转';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null)
	{
		$item = $this->item($name);
		if($item === false)return false;
		if(empty($item['raw_url'])){
			$this->errmsg = 'OpenList 没有返回下载直链，请确认该目录挂载的存储支持直链';
			return false;
		}
		return $this->absolute($item['raw_url']);
	}

	//后台「连接测试」用：先确认地址和令牌能通，再写一个小文件读回来删掉
	public function test()
	{
		if($this->baseurl === ''){
			$this->errmsg = '请先填写 OpenList 地址';
			return false;
		}
		if($this->fixed === '' && ($this->user === '' || $this->pass === '')){
			$this->errmsg = '请填写账号和密码，或者填一个固定令牌';
			return false;
		}
		if($this->authToken() === false)return false;
		//先单独验一次令牌：令牌填错时下面的 mkdir 只会报一句含糊的权限错误
		if($this->api('GET', 'api/me') === false){
			$this->errmsg = '令牌校验失败：'.$this->errmsg;
			return false;
		}
		if(!$this->makeDir())return false;
		$name = 'pantest_'.substr(md5(uniqid('', true)), 0, 8);
		$tmp = sys_get_temp_dir().'/'.$name;
		$content = 'pan openlist test '.date('Y-m-d H:i:s');
		if(@file_put_contents($tmp, $content) === false){
			$this->errmsg = '本地临时目录不可写，无法测试';
			return false;
		}
		$ok = $this->upload($name, $tmp, 'text/plain');
		@unlink($tmp);
		//同 makeDir()：OpenList 报的是它自己那边的真实磁盘路径，跟这里发过去的路径不是一回事，
		//两个都写出来才对得上号（真踩过：发的是 /xiran/pantest_xxx，报错却说 open /pantest_xxx 失败）
		if(!$ok){
			$this->errmsg = '往 '.$this->filePath($name).' 写测试文件失败：'.$this->errmsg
				.'（若报错里的路径和这里对不上，那是 OpenList 挂载点对应的真实磁盘路径，'
				.'请检查该挂载点的「根文件夹路径」填的是不是一个存在且 OpenList 写得进去的目录）';
			return false;
		}
		$read = $this->get($name);
		$this->delete($name);
		if($read === false){
			$this->errmsg = '文件写入成功，但读不回来：'.$this->errmsg;
			return false;
		}
		if($read !== $content){
			$this->errmsg = '文件写入成功，但读回的内容不一致，请检查 OpenList 服务是否正常';
			return false;
		}
		return true;
	}

	/* ---------------- 内部实现 ---------------- */

	private function filePath($name)
	{
		return $this->filepath.'/'.ltrim($name, '/');
	}

	/*
	 * raw_url 可能是上游存储的完整地址，也可能是 OpenList 自己的 /p/xxx、/d/xxx——
	 * 它后台的「站点地址」没填时给的就是这种只有路径的形式，要补回本站填的地址才能用。
	 */
	private function absolute($url)
	{
		if(preg_match('#^https?://#i', $url))return $url;
		return rtrim($this->baseurl, '/').'/'.ltrim($url, '/');
	}

	//令牌：站长填了固定令牌就一直用它，否则拿账号密码登录，登录来的令牌缓存在配置表里
	private function authToken()
	{
		global $conf;
		if($this->fixed !== '')return $this->fixed;
		if($this->token !== null)return $this->token;
		if(!empty($conf['openlist_cache_token']) && intval($conf['openlist_token_expire']) > time() + 300){
			$this->token = $conf['openlist_cache_token'];
			return $this->token;
		}
		return $this->login();
	}

	//登录只能用 request()：走 api() 会再绕回 authToken()，成死循环
	private function login()
	{
		global $conf;
		if($this->baseurl === ''){
			$this->errmsg = 'OpenList 地址未配置';
			return false;
		}
		if($this->user === '' || $this->pass === ''){
			$this->errmsg = 'OpenList 账号或密码未配置';
			return false;
		}
		$res = $this->request('POST', $this->baseurl.'api/auth/login', ['Content-Type: application/json'], [
			'body' => json_encode(['username'=>$this->user, 'password'=>$this->pass], JSON_UNESCAPED_UNICODE)
		]);
		if($res === false)return false;
		$json = $this->parse($res['body']);
		if($json === false){
			$this->errmsg = 'OpenList 登录失败：'.$this->errmsg;
			return false;
		}
		if(empty($json['token'])){
			$this->errmsg = 'OpenList 登录成功但没有返回令牌';
			trigger_error($this->errmsg);
			return false;
		}
		$this->token = $json['token'];
		$expire = time() + self::TOKEN_TTL;
		if(function_exists('saveSetting')){
			saveSetting('openlist_cache_token', $this->token);
			saveSetting('openlist_token_expire', $expire);
		}
		$conf['openlist_cache_token'] = $this->token;
		$conf['openlist_token_expire'] = $expire;
		return $this->token;
	}

	//缓存的令牌被服务端提前作废时（改过密码、重启清了会话），丢掉重登一次
	private function forgetToken()
	{
		global $conf;
		if($this->fixed !== '')return false;
		$this->token = null;
		$conf['openlist_cache_token'] = '';
		$conf['openlist_token_expire'] = 0;
		if(function_exists('saveSetting')){
			saveSetting('openlist_cache_token', '');
			saveSetting('openlist_token_expire', '0');
		}
		return true;
	}

	//带令牌调接口，$body 是数组时按 JSON 发出去。返回 data 段，失败返回 false
	private function api($method, $path, $body = null, $opts = [])
	{
		$token = $this->authToken();
		if($token === false)return false;
		$headers = ['Authorization: '.$token];
		if(!empty($opts['headers']))$headers = array_merge($headers, $opts['headers']);
		if($body !== null){
			$headers[] = 'Content-Type: application/json';
			$opts['body'] = json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		}
		$res = $this->request($method, $this->baseurl.$path, $headers, $opts);
		$json = $res === false ? false : $this->parse($res['body'], $opts);
		//令牌失效：清掉重登一次。上传的文件句柄是每次现开的，重发没有倒带问题
		if($json === false && $this->code == 401 && empty($opts['noretry']) && $this->forgetToken()){
			$opts['noretry'] = true;
			return $this->api($method, $path, $body, $opts);
		}
		return $json;
	}

	/*
	 * 解 OpenList 的响应外壳：{"code":200,"message":"success","data":{...}}。
	 * 连令牌失效这种也是 HTTP 200 + code 401，所以成败只能看 code。
	 * data 为空时返回空数组，调用方一律用 !== false 判断成败。
	 */
	private function parse($body, $opts = [])
	{
		$this->code = 0;
		$json = json_decode($body, true);
		if(!is_array($json) || !isset($json['code'])){
			$this->errmsg = 'OpenList 返回了无法识别的内容（HTTP '.$this->http.'）：'.$this->brief($body);
			if(empty($opts['quiet']))trigger_error($this->errmsg);
			return false;
		}
		$this->code = intval($json['code']);
		if($this->code != 200){
			$msg = isset($json['message']) ? $json['message'] : '';
			$this->errmsg = 'OpenList 接口报错（'.$this->code.'）：'.($msg === '' ? '无错误信息' : $msg);
			if(empty($opts['quiet']))trigger_error($this->errmsg);
			return false;
		}
		return isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
	}

	private function item($name, $quiet = false)
	{
		return $this->api('POST', 'api/fs/get', ['path'=>$this->filePath($name), 'password'=>''], ['quiet'=>$quiet]);
	}

	//目录不存在时接口回的是 500 + object not found，没有专门的错误码，只能认关键字
	private function looksLikeMissingDir()
	{
		if($this->code == 404)return true;
		return $this->errmsg !== null && stripos($this->errmsg, 'not found') !== false;
	}

	/*
	 * mkdir 在服务端是递归的，多级目录一次就能建出来，目录已存在也返回成功。
	 *
	 * 失败时一定要把本站用的路径写进错误里：OpenList 报的是它自己那边的路径——
	 * 存储目录去掉挂载点、再拼上该挂载点的根文件夹路径之后的真实磁盘路径，
	 * 跟后台填的存储目录长得完全是两码事。只报它那半边的话，
	 * 「填的是 /xiran/pan/file，报错却说 mkdir /pan 失败」根本没法对上号。
	 */
	private function makeDir()
	{
		$this->dirchecked = true;
		if($this->api('POST', 'api/fs/mkdir', ['path'=>$this->filepath]) !== false)return true;
		$this->errmsg = '在 OpenList 里创建目录 '.$this->filepath.' 失败：'.$this->errmsg
			.'（若报错里的路径和这里不一样，那是 OpenList 挂载点对应的真实磁盘路径，'
			.'请检查该挂载点的「根文件夹路径」是否存在、OpenList 有没有写入权限）';
		return false;
	}

	private function put($name, $tmpfile, $content_type)
	{
		//File-Path 是 HTTP 头，只能放 ASCII，服务端收到后会自己 URL 解码。
		//逐段编码而不是整串编码：斜杠留着原样，出问题时抓包一眼能看出传到了哪个目录
		$path = implode('/', array_map('rawurlencode', explode('/', $this->filePath($name))));
		$headers = ['File-Path: '.$path];
		if($content_type)$headers[] = 'Content-Type: '.$content_type;
		return $this->api('PUT', 'api/fs/put', null, ['headers'=>$headers, 'file'=>$tmpfile, 'timeout'=>0]);
	}

	//不带令牌的裸下载：raw_url 自带签名，而且可能指向第三方存储，把令牌带过去等于泄露
	private function fetch($url, $headers = [], $opts = [])
	{
		return $this->request('GET', $url, $headers, $opts);
	}

	private function request($method, $url, $headers = [], $opts = [])
	{
		//两个都要清零：请求根本没发出去时 parse() 不会执行，留着上一次的业务码
		//会让 401 重登和缺目录补建拿旧结果误判
		$this->http = 0;
		$this->code = 0;
		if(!function_exists('curl_init')){
			$this->errmsg = 'OpenList 存储需要服务器开启 PHP cURL 扩展';
			return false;
		}
		if($this->baseurl === ''){
			$this->errmsg = 'OpenList 地址未配置';
			return false;
		}
		$responseHeaders = [];
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, isset($opts['timeout']) ? $opts['timeout'] : 60);
		//大文件传输不设总时长上限，但整整 60 秒一个字节都没动就当作卡死断开，
		//免得 PHP 进程一直挂在那儿
		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60);
		//部分反代不认 Expect: 100-continue，大文件 PUT 会一直卡到超时
		$headers[] = 'Expect:';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$responseHeaders){
			$pos = strpos($line, ':');
			if($pos !== false){
				$responseHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
			}
			return strlen($line);
		});
		if(!empty($opts['stream'])){
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data){
				echo $data;
				flush();
				return strlen($data);
			});
		}
		$handle = null;
		if(isset($opts['body'])){
			curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
		}elseif(!empty($opts['file'])){
			$handle = fopen($opts['file'], 'rb');
			if(!$handle){
				curl_close($ch);
				$this->errmsg = '读取待上传的临时文件失败';
				return false;
			}
			//长度取已打开句柄的 fstat：filesize() 读的是 PHP 的 stat 缓存，
			//同一次请求里文件刚被改写过就会拿到旧长度，curl 会一直等那些根本不存在的字节
			$stat = fstat($handle);
			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_INFILE, $handle);
			curl_setopt($ch, CURLOPT_INFILESIZE, $stat['size']);
		}
		$body = curl_exec($ch);
		$status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$error = curl_error($ch);
		curl_close($ch);
		if($handle)fclose($handle);
		$this->http = $status;
		if($body === false || $status < 200 || $status >= 300){
			$this->errmsg = 'OpenList '.$method.' 失败（HTTP '.$status.'）：'.($error ? $error : $this->brief($body, $status));
			if(empty($opts['quiet']))trigger_error($this->errmsg);
			return false;
		}
		return ['status'=>$status, 'headers'=>$responseHeaders, 'body'=>is_string($body) ? $body : ''];
	}

	//反代挡在前面时返回的可能是一整页 HTML 错误页，只留开头一段
	private function brief($body, $status = 0)
	{
		if(!is_string($body) || trim($body) === ''){
			if($status == 401 || $status == 403)return '账号、密码或令牌不正确';
			if($status == 404)return '地址不对，请确认填的是 OpenList 站点根地址（不要带 /api）';
			return '无返回内容';
		}
		$text = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
		return mb_substr($text, 0, 200, 'UTF-8');
	}
}
