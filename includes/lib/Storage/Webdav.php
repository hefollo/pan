<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * WebDAV 存储驱动
 * 适配坚果云、Nextcloud / ownCloud、Alist、群晖 WebDAV、Apache mod_dav 等标准服务。
 * WebDAV 没有对象存储那种带签名的直传地址和临时直链，所以上传下载一律走本站中转，
 * 后台的「文件上传/下载方式」对它不起作用。
 */
class Webdav implements IStorage
{
	private $baseurl;
	private $user;
	private $pass;
	private $filepath;
	private $errmsg;
	private $status = 0;
	//目录只在第一次写失败时补建，正常上传不额外多一次 MKCOL 请求
	private $dirchecked = false;

	public function __construct($config)
	{
		$url = isset($config['url']) ? trim($config['url']) : '';
		if($url !== '' && !preg_match('#^https?://#i', $url)){
			$url = 'https://'.$url;
		}
		$this->baseurl = $url === '' ? '' : rtrim($url, '/').'/';
		$this->user = isset($config['user']) ? $config['user'] : '';
		$this->pass = isset($config['pass']) ? $config['pass'] : '';
		$path = isset($config['path']) ? trim($config['path'], " \t\n\r\0\x0B/") : '';
		$this->filepath = $path === '' ? 'file/' : $path.'/';
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
		$res = $this->request('HEAD', $this->fileUrl($name), [], ['nobody'=>true, 'quiet'=>true]);
		if($res !== false)return true;
		//有的服务端不认 HEAD，退回用 PROPFIND 再问一次
		if($this->status === 405 || $this->status === 501){
			return $this->request('PROPFIND', $this->fileUrl($name), ['Depth: 0'], ['quiet'=>true]) !== false;
		}
		return false;
	}

	public function get($name)
	{
		$res = $this->request('GET', $this->fileUrl($name));
		return $res === false ? false : $res['body'];
	}

	public function downfile($name, $range = false)
	{
		$headers = [];
		if($range){
			$headers[] = 'Range: bytes='.intval($range[0]).'-'.intval($range[1]);
		}
		//边收边吐，不把整个文件读进内存
		$res = $this->request('GET', $this->fileUrl($name), $headers, ['stream'=>true, 'timeout'=>0]);
		return $res !== false;
	}

	public function upload($name, $tmpfile, $content_type = null)
	{
		$res = $this->put($name, $tmpfile, $content_type);
		//404/409 基本都是存储目录还不存在，补建目录后再传一次
		if($res === false && in_array($this->status, [404, 409], true) && !$this->dirchecked){
			$this->makeDir();
			$res = $this->put($name, $tmpfile, $content_type);
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
		$res = $this->request('HEAD', $this->fileUrl($name), [], ['nobody'=>true]);
		if($res === false)return false;
		$length = isset($res['headers']['content-length']) ? intval($res['headers']['content-length']) : null;
		$type = isset($res['headers']['content-type']) ? $res['headers']['content-type'] : null;
		//个别服务端 HEAD 不返回长度，用 PROPFIND 把属性读出来
		if($length === null){
			$prop = $this->request('PROPFIND', $this->fileUrl($name), ['Depth: 0'], ['quiet'=>true]);
			if($prop !== false && preg_match('#<[a-z0-9]*:?getcontentlength[^>]*>(\d+)<#i', $prop['body'], $m)){
				$length = intval($m[1]);
			}
		}
		return ['length'=>intval($length), 'content_type'=>$type];
	}

	public function delete($name)
	{
		return $this->request('DELETE', $this->fileUrl($name)) !== false;
	}

	public function getUploadParam($name, $filename, $max_file_size = 0)
	{
		//WebDAV 直传要用 PUT，而前端直传流程发的是 POST 表单，走不通，只能中转
		$this->errmsg = 'WebDAV 不支持直传，请把「文件上传方式」设置为网站中转';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null)
	{
		$this->errmsg = 'WebDAV 不支持直链下载，请把「文件下载方式」设置为网站中转';
		return false;
	}

	//后台「连接测试」用：写一个小文件再读回来删掉，验证地址、账号和目录权限
	public function test()
	{
		if($this->baseurl === ''){
			$this->errmsg = '请先填写 WebDAV 地址';
			return false;
		}
		$this->makeDir();
		$name = 'pantest_'.substr(md5(uniqid('', true)), 0, 8);
		$tmp = sys_get_temp_dir().'/'.$name;
		$content = 'pan webdav test '.date('Y-m-d H:i:s');
		if(@file_put_contents($tmp, $content) === false){
			$this->errmsg = '本地临时目录不可写，无法测试';
			return false;
		}
		$ok = $this->upload($name, $tmp, 'text/plain');
		@unlink($tmp);
		if(!$ok)return false;
		$read = $this->get($name);
		$this->delete($name);
		if($read !== $content){
			$this->errmsg = '文件写入成功，但读回的内容不一致，请检查 WebDAV 服务是否正常';
			return false;
		}
		return true;
	}

	private function fileUrl($name)
	{
		return $this->baseurl.$this->encodePath($this->filepath.ltrim($name, '/'));
	}

	private function encodePath($path)
	{
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	//逐级建目录：MKCOL 一次只能建一层，返回 405 表示目录已存在，同样算成功
	private function makeDir()
	{
		$this->dirchecked = true;
		$path = '';
		foreach(explode('/', trim($this->filepath, '/')) as $seg){
			if($seg === '')continue;
			$path .= rawurlencode($seg).'/';
			$this->request('MKCOL', $this->baseurl.$path, [], ['quiet'=>true]);
		}
	}

	private function put($name, $tmpfile, $content_type)
	{
		$headers = [];
		if($content_type)$headers[] = 'Content-Type: '.$content_type;
		return $this->request('PUT', $this->fileUrl($name), $headers, ['file'=>$tmpfile, 'timeout'=>0]);
	}

	private function request($method, $url, $headers = [], $opts = [])
	{
		$this->status = 0;
		if(!function_exists('curl_init')){
			$this->errmsg = 'WebDAV 存储需要服务器开启 PHP cURL 扩展';
			return false;
		}
		if($this->baseurl === ''){
			$this->errmsg = 'WebDAV 地址未配置';
			return false;
		}
		$responseHeaders = [];
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, isset($opts['timeout']) ? $opts['timeout'] : 60);
		//大文件传输不设总时长上限，但整整 60 秒一个字节都没动就当作卡死断开，
		//免得 PHP 进程一直挂在那儿
		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60);
		//固定用 Basic：CURLAUTH_ANY 会先发一次不带凭据的请求探路，服务端回 401 后 curl
		//要把已经发出去的文件流倒回去重发，PUT 大文件时会直接报 "data rewind wasn't possible"。
		//Basic 是第一次请求就把凭据带上，常见的 WebDAV 服务（坚果云、Nextcloud、Alist、群晖）都支持
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->pass);
		//部分服务端不认 Expect: 100-continue，大文件 PUT 会一直卡到超时
		$headers[] = 'Expect:';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$responseHeaders){
			$pos = strpos($line, ':');
			if($pos !== false){
				$responseHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
			}
			return strlen($line);
		});
		if(!empty($opts['nobody'])){
			curl_setopt($ch, CURLOPT_NOBODY, true);
		}
		if(!empty($opts['stream'])){
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data){
				echo $data;
				flush();
				return strlen($data);
			});
		}
		$handle = null;
		if(!empty($opts['file'])){
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
		$this->status = $status;
		if($body === false || $status < 200 || $status >= 300){
			$this->errmsg = 'WebDAV '.$method.' 失败（HTTP '.$status.'）：'.($error ? $error : $this->briefBody($body, $status));
			if(empty($opts['quiet']))trigger_error($this->errmsg);
			return false;
		}
		return ['status'=>$status, 'headers'=>$responseHeaders, 'body'=>is_string($body) ? $body : ''];
	}

	//服务端的报错页可能很长，只留开头一段
	private function briefBody($body, $status)
	{
		if(!is_string($body) || trim($body) === ''){
			if($status == 401)return '账号或密码不正确';
			if($status == 403)return '没有权限，请检查该账号对存储目录的写入权限';
			if($status == 404)return '路径不存在';
			return '无返回内容';
		}
		$text = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
		return mb_substr($text, 0, 200, 'UTF-8');
	}
}
