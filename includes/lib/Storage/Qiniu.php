<?php
namespace lib\Storage;
use \lib\IStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

class Qiniu implements IStorage {
	private $config;
	private $bucket;
	private $auth;
	private $errmsg;
	private $filepath = 'file/';
	
	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->auth = new \Qiniu\Auth($config['accessKey'], $config['secretKey']);
	}

	public function getClient(){
		return $this->auth;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			$bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
			list($res, $err) = $bucketMgr->stat($this->bucket, $this->filepath.$name);
			return $res!==null;
        } catch(\Exception $e) {
			return false;
		}
	}

	public function downloadTo($name, $path) {
		//Read the bucket's configured origin, not the current storage's shared download domain.
		$domain = rtrim(trim((string)$this->config['domain']), '/');
		if($domain === ''){
			$this->errmsg = __FUNCTION__ . ': 没有填写空间绑定域名';
			trigger_error($this->errmsg);
			return false;
		}
		//绑定域名一般不带协议，而七牛的域名不一定配了 HTTPS 证书（测试域名、没上证书的自有域名只有 http）。
		//原来一律补 https://，这类域名直传完成后的回源核对必失败。没写协议就先试 https 再试 http，写了的按写的来
		$bases = preg_match('#^https?://#i', $domain) ? [$domain] : ['https://'.$domain, 'http://'.$domain];
		$client = new Client(['timeout'=>300, 'connect_timeout'=>15]);
		foreach($bases as $base){
			try {
				//下载凭证是对整条地址（含协议）签的，换协议要重新签
				$url = $this->auth->privateDownloadUrl($base.'/'.$this->filepath.$name, 300);
				$response = $client->request('GET', $url, ['sink'=>$path]);
				if($response->getStatusCode() === 200)return true;
				$this->errmsg = __FUNCTION__ . ': HTTP ' . $response->getStatusCode();
			} catch(\Throwable $e) {
				$this->errmsg = __FUNCTION__ . ': ' . $e->getMessage();
			}
		}
		trigger_error($this->errmsg);
		return false;
	}

	public function get($name) {
		try {
			$url = $this->getDownUrl($name);
			if(!$url) return false;
			$client = new Client(['timeout' => 30]);
			$request = new Psr7\Request('GET', $url);
			$response = $client->send($request);
			$body = $response->getBody();
			return $body->getContents();
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		$headers = [];
		if($range){
			$headers[] = 'Range: bytes='.intval($range[0]).'-'.intval($range[1]);
		}
		$url = $this->getDownUrl($name);
		if(!$url) return false;
		if(!function_exists('curl_init')){
			$this->errmsg = __FUNCTION__ . ': 需要服务器开启 PHP cURL 扩展';
			trigger_error($this->errmsg);
			return false;
		}
		//边收边吐。原来是先把整个文件从七牛取回本站再输出，而且总时长只给 30 秒：
		//文件稍大或线路慢一点，访客那边就是链接一直没反应，30 秒内取不完还会整个失败。
		//现在不设总时长上限（限速下载大文件本来就久），连续 60 秒一个字节都没动才断开
		$errbody = '';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60);
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$errbody){
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if($code >= 200 && $code < 300){
				\download_output_chunk($data);
			}elseif(strlen($errbody) < 1024){
				//不是 2xx 时收到的是七牛的报错（或跳转页），留给 errmsg，不能当文件内容吐给访客
				$errbody .= $data;
			}
			return strlen($data);
		});
		$ok = curl_exec($ch);
		$status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$error = curl_error($ch);
		curl_close($ch);
		if($ok === false || $status < 200 || $status >= 300){
			$this->errmsg = __FUNCTION__ . ': HTTP ' . $status . ' ' . ($error ? $error : trim($errbody));
			trigger_error($this->errmsg);
			return false;
		}
		return true;
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$token = $this->auth->uploadToken($this->bucket);
			$uploadMgr = new \Qiniu\Storage\UploadManager();
			//七牛拒绝上传时 putFile 不抛异常，错误在返回值的第二项里。不看的话没传上去也当成功，
			//会建出一条下载时报 File Not Found 的记录
			list($ret, $err) = $uploadMgr->putFile($token, $this->filepath.$name, $tmpfile);
			if($err !== null){
				$this->errmsg = __FUNCTION__ . ': ' . $err->message();
				trigger_error($this->errmsg);
				return false;
			}
			return true;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		try {
			$key = $this->filepath.$name;
			$token = $this->auth->uploadToken($this->bucket, $key);
			$uploadMgr = new \Qiniu\Storage\UploadManager();
			list($ret, $err) = $uploadMgr->putFile($token, $key, $tmpfile);
			if($err !== null){
				$this->errmsg = __FUNCTION__ . ': ' . $err->message();
				trigger_error($this->errmsg);
				return false;
			}
			return true;
		} catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function getinfo($name) {
		try {
			$bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
			list($res, $err) = $bucketMgr->stat($this->bucket, $this->filepath.$name);
			if($res!==null){
				$result = ['length'=>$res['fsize'], 'content_type'=>$res['fsize']];
				return $result;
			}else{
				$this->errmsg = $err ? $err->message() : 'Unknown Error';
			}
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
			list($res, $err) = $bucketMgr->delete($this->bucket, $this->filepath.$name);
			if($err===null){
				return true;
			}else{
				$this->errmsg = $err->message();
			}
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	/*
	 * 浏览器直传完成后的核对发布，在存储端完成，文件不经过本站（约定见 lib\VerifiedUpload::publish）。
	 * 七牛对表单直传的文件会记下内容的 MD5（查询文件信息时的 md5 字段）。
	 * 先把 pending 对象复制到一个浏览器写不了的快照键（直传凭证里的 key 是写死的，只能写它自己那一个），
	 * 对快照核对校验值和长度，通过才复制成正式文件，最后删掉快照。
	 * 只有真的发布了才返回 true；返回 false 表示「这里定不了」，调用方会改走取回本站核对的老办法。
	 */
	public function publishStaged($stagingName, $hash, $size, $content_type = null) {
		$snapshot = 'pending/'.bin2hex(random_bytes(24));
		$copied = false;
		try {
			$mgr = $this->bucketManager();
			if(!$this->stagedMatches($mgr, $stagingName, $hash, $size))return false;
			//七牛的接口出错不抛异常，错误在返回值的第二项里
			list($ret, $err) = $mgr->copy($this->bucket, $this->filepath.$stagingName, $this->bucket, $this->filepath.$snapshot, true);
			if($err !== null)return false;
			$copied = true;
			if(!$this->stagedMatches($mgr, $snapshot, $hash, $size))return false;
			list($ret, $err) = $mgr->copy($this->bucket, $this->filepath.$snapshot, $this->bucket, $this->filepath.$hash, true);
			if($err !== null)return false;
			return $this->stagedMatches($mgr, $hash, $hash, $size);
		} catch(\Throwable $e) {
			return false;
		} finally {
			if($copied){
				try { $this->bucketManager()->delete($this->bucket, $this->filepath.$snapshot); } catch(\Throwable $e) {}
			}
		}
	}

	private $bucketMgr = null;

	private function bucketManager() {
		if($this->bucketMgr === null)$this->bucketMgr = new \Qiniu\Storage\BucketManager($this->auth);
		return $this->bucketMgr;
	}

	private function stagedMatches($mgr, $name, $hash, $size) {
		list($res, $err) = $mgr->stat($this->bucket, $this->filepath.$name);
		return $err === null && is_array($res) && isset($res['md5'], $res['fsize'])
			&& intval($res['fsize']) === intval($size)
			&& \lib\VerifiedUpload::sameMd5($res['md5'], $hash);
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$config = new \Qiniu\Config();
		$config->useHTTPS = true;
		list($upHost, $err) = $config->getUpHostV2($this->config['accessKey'], $this->bucket);
        if ($err != null) {
            $this->errmsg = $err->message();
			return false;
        }
		$url = $upHost;
		$key = $this->filepath.$name;
		$policy = [];
		if($max_file_size > 0){
			$policy['fsizeLimit'] = $max_file_size;
		}
		$token = $this->auth->uploadToken($this->bucket, $key, 3600, $policy);
		$post = [
			'key' => $key,
			'token' => $token,
		];
		return ['url'=>$url, 'post'=>$post];
	}

	public function getDownUrl($name, $filename = null, $content_type = null){
		global $conf;
		$expires = 315360000;
		if(!$conf['downfile_type'] || empty($conf['downfile_domain'])) $conf['downfile_domain'] = $this->config['domain'];
		$url = ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'].'/'.$this->filepath.$name;
		if(!empty($filename) && $content_type==null){
			$url .= '?attname='.rawurlencode($filename);
		}
		$url = $this->auth->privateDownloadUrl($url, $expires);
		return $url;
	}
}
