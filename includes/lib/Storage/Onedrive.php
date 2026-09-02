<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * OneDrive 存储驱动（微软 Graph API）
 * 支持个人版、商业版 / 世纪互联版，授权在后台「存储类型设置」里点按钮完成，
 * 拿到的 refresh_token 存在配置表里，access_token 过期会自动续期。
 *
 * 上传：走本站中转（Graph 的直传要用 PUT + 上传会话，前端那套 POST 表单直传对不上）。
 * 下载：可以用 OneDrive 返回的临时直链，省本站流量。
 */
class Onedrive implements IStorage
{
	private $clientId;
	private $clientSecret;
	private $refreshToken;
	private $isChina;
	private $filepath;
	private $errmsg;
	private $token = null;
	//Graph 要求分片大小是 320KiB 的整数倍，这里取 10MB
	const CHUNK_SIZE = 10485760;
	//4MB 以内可以一次 PUT 上去，不用开上传会话
	const SIMPLE_MAX = 4194304;

	public function __construct($config)
	{
		$this->clientId = isset($config['clientId']) ? trim($config['clientId']) : '';
		$this->clientSecret = isset($config['clientSecret']) ? trim($config['clientSecret']) : '';
		$this->refreshToken = isset($config['refreshToken']) ? trim($config['refreshToken']) : '';
		$this->isChina = !empty($config['china']);
		$path = isset($config['path']) ? trim($config['path'], " \t\n\r\0\x0B/") : '';
		$this->filepath = $path === '' ? 'pan/file' : $path;
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
		$res = $this->request('GET', $this->contentUrl($name), $this->authHeaders());
		return $res === false ? false : $res['body'];
	}

	public function downfile($name, $range = false)
	{
		$headers = $this->authHeaders();
		if($range){
			$headers[] = 'Range: bytes='.intval($range[0]).'-'.intval($range[1]);
		}
		//边收边吐，不把整个文件读进内存
		return $this->request('GET', $this->contentUrl($name), $headers, ['stream'=>true, 'timeout'=>0]) !== false;
	}

	public function upload($name, $tmpfile, $content_type = null)
	{
		clearstatcache(true, $tmpfile);
		$size = filesize($tmpfile);
		if($size === false){
			$this->errmsg = '读取待上传的临时文件失败';
			return false;
		}
		if($size <= self::SIMPLE_MAX){
			$headers = $this->authHeaders();
			if($content_type)$headers[] = 'Content-Type: '.$content_type;
			return $this->request('PUT', $this->contentUrl($name), $headers, ['file'=>$tmpfile, 'timeout'=>0]) !== false;
		}
		return $this->uploadBySession($name, $tmpfile, $size);
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
		return [
			'length' => isset($item['size']) ? intval($item['size']) : 0,
			'content_type' => isset($item['file']['mimeType']) ? $item['file']['mimeType'] : null
		];
	}

	public function delete($name)
	{
		return $this->request('DELETE', $this->itemUrl($name), $this->authHeaders()) !== false;
	}

	public function getUploadParam($name, $filename, $max_file_size = 0)
	{
		//Graph 的直传是 PUT 上传会话，前端直传流程发的是 POST 表单，对不上，只能中转
		$this->errmsg = 'OneDrive 不支持直传，请把「文件上传方式」设置为网站中转';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null)
	{
		$item = $this->item($name);
		if($item === false)return false;
		if(empty($item['@microsoft.graph.downloadUrl'])){
			$this->errmsg = 'OneDrive 没有返回下载直链';
			return false;
		}
		return $item['@microsoft.graph.downloadUrl'];
	}

	//后台用：当前授权的账号和容量，顺带验证 refresh_token 还有效
	public function drive()
	{
		$res = $this->api('GET', '/me/drive');
		if($res === false)return false;
		return $res;
	}

	//后台「连接测试」用：写一个小文件再读回来删掉
	public function test()
	{
		if($this->refreshToken === ''){
			$this->errmsg = '还没有完成 OneDrive 授权';
			return false;
		}
		$name = 'pantest_'.substr(md5(uniqid('', true)), 0, 8);
		$tmp = sys_get_temp_dir().'/'.$name;
		$content = 'pan onedrive test '.date('Y-m-d H:i:s');
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
			$this->errmsg = '文件写入成功，但读回的内容不一致';
			return false;
		}
		return true;
	}

	/* ---------------- OAuth ---------------- */

	public static function loginHost($china = false)
	{
		return $china ? 'https://login.partner.microsoftonline.cn' : 'https://login.microsoftonline.com';
	}

	public static function graphHost($china = false)
	{
		return $china ? 'https://microsoftgraph.chinacloudapi.cn/v1.0' : 'https://graph.microsoft.com/v1.0';
	}

	public static function scope()
	{
		return 'offline_access Files.ReadWrite.All';
	}

	//后台点「获取授权」跳转到的微软登录地址
	public static function authUrl($clientId, $redirectUri, $state, $china = false)
	{
		return static::loginHost($china).'/common/oauth2/v2.0/authorize?'.http_build_query([
			'client_id' => $clientId,
			'response_type' => 'code',
			'redirect_uri' => $redirectUri,
			'response_mode' => 'query',
			'scope' => self::scope(),
			'state' => $state
		]);
	}

	//用授权码换 token，成功返回 ['refresh_token'=>..,'access_token'=>..,'expires_in'=>..]
	public static function exchangeCode($clientId, $clientSecret, $code, $redirectUri, $china = false)
	{
		return self::tokenRequest($china, [
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'code' => $code,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $redirectUri
		]);
	}

	private static function tokenRequest($china, $post)
	{
		$url = static::loginHost($china).'/common/oauth2/v2.0/token';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
		$body = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);
		if($body === false)return ['error'=>'请求微软服务器失败：'.$error];
		$json = json_decode($body, true);
		if(!is_array($json))return ['error'=>'微软服务器返回了无法识别的内容'];
		if(isset($json['error'])){
			$json['error'] = $json['error'].'：'.(isset($json['error_description']) ? $json['error_description'] : '');
		}
		return $json;
	}

	/* ---------------- 内部实现 ---------------- */

	//access_token 有效期一小时，缓存在配置表里，过期前 5 分钟就提前换新的
	private function accessToken()
	{
		global $conf;
		if($this->token !== null)return $this->token;
		if(!empty($conf['onedrive_access_token']) && intval($conf['onedrive_token_expire']) > time() + 300){
			$this->token = $conf['onedrive_access_token'];
			return $this->token;
		}
		if($this->refreshToken === ''){
			$this->errmsg = '还没有完成 OneDrive 授权，请到后台存储类型设置里点「获取授权」';
			return false;
		}
		$res = self::tokenRequest($this->isChina, [
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'refresh_token' => $this->refreshToken,
			'grant_type' => 'refresh_token',
			'scope' => self::scope()
		]);
		if(empty($res['access_token'])){
			$this->errmsg = 'OneDrive 续期失败：'.(isset($res['error']) ? $res['error'] : '未知错误').'，请重新授权';
			trigger_error($this->errmsg);
			return false;
		}
		$this->token = $res['access_token'];
		$expire = time() + (isset($res['expires_in']) ? intval($res['expires_in']) : 3600);
		if(function_exists('saveSetting')){
			saveSetting('onedrive_access_token', $this->token);
			saveSetting('onedrive_token_expire', $expire);
			//微软每次续期都会换一张新的 refresh_token，旧的过一段时间就作废，必须存下来
			if(!empty($res['refresh_token']) && $res['refresh_token'] !== $this->refreshToken){
				$this->refreshToken = $res['refresh_token'];
				saveSetting('onedrive_refresh_token', $this->refreshToken);
				$conf['onedrive_refresh_token'] = $this->refreshToken;
			}
		}
		$conf['onedrive_access_token'] = $this->token;
		$conf['onedrive_token_expire'] = $expire;
		return $this->token;
	}

	private function authHeaders()
	{
		$token = $this->accessToken();
		if($token === false)return false;
		return ['Authorization: Bearer '.$token];
	}

	private function itemPath($name)
	{
		$path = $this->filepath.'/'.ltrim($name, '/');
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	private function itemUrl($name)
	{
		return static::graphHost($this->isChina).'/me/drive/root:/'.$this->itemPath($name);
	}

	private function contentUrl($name)
	{
		return $this->itemUrl($name).':/content';
	}

	private function item($name, $quiet = false)
	{
		$headers = $this->authHeaders();
		if($headers === false)return false;
		$res = $this->request('GET', $this->itemUrl($name), $headers, ['quiet'=>$quiet]);
		if($res === false)return false;
		$json = json_decode($res['body'], true);
		if(!is_array($json)){
			$this->errmsg = 'OneDrive 返回了无法识别的内容';
			return false;
		}
		return $json;
	}

	private function api($method, $path, $opts = [])
	{
		$headers = $this->authHeaders();
		if($headers === false)return false;
		$res = $this->request($method, static::graphHost($this->isChina).$path, $headers, $opts);
		if($res === false)return false;
		$json = json_decode($res['body'], true);
		return is_array($json) ? $json : [];
	}

	//大文件走上传会话，一片一片 PUT 上去
	private function uploadBySession($name, $tmpfile, $size)
	{
		$headers = $this->authHeaders();
		if($headers === false)return false;
		$headers[] = 'Content-Type: application/json';
		$res = $this->request('POST', $this->itemUrl($name).':/createUploadSession', $headers, [
			'body' => '{"item":{"@microsoft.graph.conflictBehavior":"replace"}}'
		]);
		if($res === false)return false;
		$json = json_decode($res['body'], true);
		if(empty($json['uploadUrl'])){
			$this->errmsg = 'OneDrive 没有返回上传会话地址';
			trigger_error($this->errmsg);
			return false;
		}
		$uploadUrl = $json['uploadUrl'];
		$handle = fopen($tmpfile, 'rb');
		if(!$handle){
			$this->errmsg = '读取待上传的临时文件失败';
			return false;
		}
		$offset = 0;
		$ok = true;
		while($offset < $size){
			$length = min(self::CHUNK_SIZE, $size - $offset);
			fseek($handle, $offset);
			$chunk = fread($handle, $length);
			if($chunk === false || strlen($chunk) != $length){
				$this->errmsg = '读取上传分片失败';
				$ok = false;
				break;
			}
			$end = $offset + $length - 1;
			//网络抖动导致的单片失败重试两次，整个大文件不用从头再来
			$done = false;
			for($try = 0; $try < 3; $try++){
				//上传会话地址自带凭据，不能再带 Authorization 头
				$put = $this->request('PUT', $uploadUrl, [
					'Content-Length: '.$length,
					'Content-Range: bytes '.$offset.'-'.$end.'/'.$size
				], ['body'=>$chunk, 'timeout'=>0, 'quiet'=>$try < 2]);
				if($put !== false){
					$done = true;
					break;
				}
			}
			if(!$done){
				$ok = false;
				break;
			}
			$offset += $length;
		}
		fclose($handle);
		if(!$ok){
			//失败就把没传完的会话取消掉，免得在 OneDrive 上留下半截文件
			$this->request('DELETE', $uploadUrl, [], ['quiet'=>true]);
			return false;
		}
		return true;
	}

	private function request($method, $url, $headers = [], $opts = [])
	{
		if(!function_exists('curl_init')){
			$this->errmsg = 'OneDrive 存储需要服务器开启 PHP cURL 扩展';
			return false;
		}
		if($headers === false)return false;
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
		if($body === false || $status < 200 || $status >= 300){
			$this->errmsg = 'OneDrive '.$method.' 失败（HTTP '.$status.'）：'.($error ? $error : $this->briefError($body, $status));
			if(empty($opts['quiet']))trigger_error($this->errmsg);
			return false;
		}
		return ['status'=>$status, 'headers'=>$responseHeaders, 'body'=>is_string($body) ? $body : ''];
	}

	//Graph 的报错是 JSON，把里面的 message 挑出来，比整段扔出去好读
	private function briefError($body, $status)
	{
		if(is_string($body) && $body !== ''){
			$json = json_decode($body, true);
			if(isset($json['error']['message']))return $json['error']['message'];
			return mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 200, 'UTF-8');
		}
		if($status == 401)return '授权已失效，请重新授权';
		if($status == 404)return '文件或目录不存在';
		return '无返回内容';
	}
}
