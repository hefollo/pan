<?php
namespace lib\Storage;
use \lib\IStorage;

class Oss implements IStorage {
	private $config;
	private $bucket;
	private $ossClient;
	private $errmsg;
	private $filepath = 'file/';

	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->ossClient = new \OSS\OssClient($config['accessKeyId'], $config['accessKeySecret'], $config['endpoint']);
	}

	public function getClient(){
		return $this->ossClient;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			return $this->ossClient->doesObjectExist($this->bucket, $this->filepath.$name);
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downloadTo($name, $path) {
		try {
			$this->ossClient->getObject($this->bucket, $this->filepath.$name, [\OSS\OssClient::OSS_FILE_DOWNLOAD=>$path]);
			return true;
		} catch(\Throwable $e) { return false; }
	}

	public function get($name) {
		try {
			return $this->ossClient->getObject($this->bucket, $this->filepath.$name);
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		if($range){
			$options['range'] = $range[0].'-'.$range[1];
		}
		try {
			\download_output_chunk($this->ossClient->getObject($this->bucket, $this->filepath.$name, $options));
			return true;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$this->ossClient->uploadFile($this->bucket, $this->filepath.$name, $tmpfile);
			return true;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile);
	}

	public function getinfo($name) {
		try {
			$objectMeta = $this->ossClient->getObjectMeta($this->bucket, $this->filepath.$name);
			$result = ['length'=>$objectMeta['content-length'], 'content_type'=>$objectMeta['content-type']];
			return $result;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$this->ossClient->deleteObject($this->bucket, $this->filepath.$name);
			return true;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	/*
	 * 浏览器直传完成后的核对发布，在存储端完成，文件不经过本站（约定见 lib\VerifiedUpload::publish）。
	 * 阿里云 OSS 上单次上传的对象，ETag 就是内容的 MD5。
	 * 先把 pending 对象复制到一个浏览器写不了的快照键（直传凭证里的 key 是写死的，只能写它自己那一个），
	 * 对快照核对校验值和长度，通过才复制成正式文件，最后删掉快照。
	 * 只有真的发布了才返回 true；返回 false 表示「这里定不了」，调用方会改走取回本站核对的老办法。
	 */
	public function publishStaged($stagingName, $hash, $size, $content_type = null) {
		$snapshot = 'pending/'.bin2hex(random_bytes(24));
		$copied = false;
		try {
			if(!$this->stagedMatches($stagingName, $hash, $size))return false;
			$this->ossClient->copyObject($this->bucket, $this->filepath.$stagingName, $this->bucket, $this->filepath.$snapshot);
			$copied = true;
			if(!$this->stagedMatches($snapshot, $hash, $size))return false;
			$options = [];
			if($content_type){
				//直传表单带上去的 Content-Disposition（原始文件名）等不留在正式文件上，只给类型，和中转上传的结果一样
				$options[\OSS\OssClient::OSS_HEADERS] = ['x-oss-metadata-directive' => 'REPLACE', \OSS\OssClient::OSS_CONTENT_TYPE => $content_type];
			}
			$this->ossClient->copyObject($this->bucket, $this->filepath.$snapshot, $this->bucket, $this->filepath.$hash, $options);
			return $this->stagedMatches($hash, $hash, $size);
		} catch(\Throwable $e) {
			return false;
		} finally {
			if($copied){
				try { $this->ossClient->deleteObject($this->bucket, $this->filepath.$snapshot); } catch(\Throwable $e) {}
			}
		}
	}

	private function stagedMatches($name, $hash, $size) {
		$meta = $this->ossClient->getObjectMeta($this->bucket, $this->filepath.$name);
		return is_array($meta) && isset($meta['etag'], $meta['content-length'])
			&& intval($meta['content-length']) === intval($size)
			&& \lib\VerifiedUpload::sameMd5($meta['etag'], $hash);
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$url = 'https://'.$this->bucket.'.'.$this->config['endpoint'].'/';
		$key = $this->filepath.$name;
		$expire = 3600;
		$expiration = gmdate("Y-m-d\TH:i:s.000\Z", time() + $expire);
		$conditions = [];
		$conditions[] = ['bucket' => $this->bucket];
        $conditions[] = ['eq', '$key', $key];
        if($max_file_size > 0){
            $conditions[] = ['content-length-range', 1, $max_file_size];
        }
        $policy_param = [
            'expiration' => $expiration,
            'conditions' => $conditions
        ];
        $policy = base64_encode(json_encode($policy_param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$signature = base64_encode(hash_hmac('sha1', $policy, $this->config['accessKeySecret'], true));
		$param = [
			'Cache-Control' => 'max-age=2592000',
			'Content-Disposition' => 'attachment; filename='.$filename,
            'OSSAccessKeyId' => $this->config['accessKeyId'],
            'policy' => $policy,
            'Signature' => $signature,
            'key' => $key,
            'success_action_status' => '200',
        ];
		return ['url'=>$url, 'post'=>$param];
	}
	
	public function getDownUrl($name, $filename, $content_type = null){
		global $conf;
		$timeout = 315360000;
		$filename = '"'.$filename.'"; filename*=utf-8\'\''.rawurlencode($filename);
		if(!$content_type){
			$options = [
				'response-content-type' => 'application/force-download',
				'response-content-disposition' => 'attachment; filename='.$filename,
			];
		}else{
			$options = [
				'response-content-type' => $content_type,
				'response-content-disposition' => 'inline; filename='.$filename,
			];
		}
		try {
			$url = $this->ossClient->signUrl($this->bucket, $this->filepath.$name, $timeout, 'GET', $options);
			if(!empty($conf['downfile_domain'])){
				$url_arr = parse_url($url);
				$url = str_replace($url_arr['scheme'].'://'.$url_arr['host'], ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'], $url);
			}else{
				$url = str_replace('http://', 'https://', $url);
			}
			return $url;
        } catch(\OSS\Core\OssException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}
}
