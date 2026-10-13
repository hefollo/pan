<?php
namespace lib\Storage;
use \lib\IStorage;

class Obs implements IStorage {
	private $config;
	private $bucket;
	private $obsClient;
	private $errmsg;
	private $filepath = 'file/';

	public function __construct($config) {
		$this->bucket = $config['bucket'];
		$this->config = $config;
		$this->obsClient = new \Obs\ObsClient([
			'key' => $config['accessKey'],
			'secret' => $config['secretKey'],
			'endpoint' => $config['endpoint'],
			'ssl_verify' => false,
		]);
	}

	public function getClient(){
		return $this->obsClient;
	}
	
	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			$this->obsClient->getObjectMetadata([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			return false;
		}
	}

	public function downloadTo($name, $path) {
		$handle = fopen($path, 'wb');
		if(!$handle)return false;
		try {
			$resp = $this->obsClient->getObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name, 'SaveAsStream'=>true]);
			while(!$resp['Body']->eof()){
				$chunk = $resp['Body']->read(102400);
				if(fwrite($handle, $chunk) !== strlen($chunk))return false;
			}
			return true;
		} catch(\Throwable $e) { return false; }
		finally { fclose($handle); }
	}

	public function get($name) {
		try {
			$resp = $this->obsClient->getObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			return $resp['Body'];
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		$options = [];
		if($range){
			$options['Range'] = 'bytes='.$range[0].'-'.$range[1];
		}
		try {
			$resp = $this->obsClient->getObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name,
				'SaveAsStream' => true
			] + $options);
			while(!$resp['Body'] -> eof()){
				\download_output_chunk($resp['Body'] -> read(102400));
			}
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
        try {
			$this->obsClient->putObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name,
				'SourceFile' => $tmpfile
			]);
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile);
	}

	public function getinfo($name) {
		try {
			$objectMeta = $this->obsClient->getObjectMetadata([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			$result = ['length'=>$objectMeta['ContentLength'], 'content_type'=>$objectMeta['ContentType']];
			return $result;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			$this->obsClient->deleteObject([
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name
			]);
			return true;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}

	/*
	 * 浏览器直传完成后的核对发布，在存储端完成，文件不经过本站（约定见 lib\VerifiedUpload::publish）。
	 * 华为云 OBS 上单次上传的对象，ETag 就是内容的 MD5。
	 * 先把 pending 对象复制到一个浏览器写不了的快照键（直传凭证里的 key 是写死的，只能写它自己那一个），
	 * 对快照核对校验值和长度，通过才复制成正式文件，最后删掉快照。
	 * 只有真的发布了才返回 true；返回 false 表示「这里定不了」，调用方会改走取回本站核对的老办法。
	 */
	public function publishStaged($stagingName, $hash, $size, $content_type = null) {
		$snapshot = 'pending/'.bin2hex(random_bytes(24));
		$copied = false;
		try {
			if(!$this->stagedMatches($stagingName, $hash, $size))return false;
			$this->obsClient->copyObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$snapshot, 'CopySource'=>$this->bucket.'/'.$this->filepath.$stagingName]);
			$copied = true;
			if(!$this->stagedMatches($snapshot, $hash, $size))return false;
			$args = ['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$hash, 'CopySource'=>$this->bucket.'/'.$this->filepath.$snapshot];
			if($content_type){
				//直传表单带上去的 Content-Disposition（原始文件名）等不留在正式文件上，只给类型，和中转上传的结果一样
				$args['MetadataDirective'] = \Obs\ObsClient::ReplaceMetadata;
				$args['ContentType'] = $content_type;
			}
			$this->obsClient->copyObject($args);
			return $this->stagedMatches($hash, $hash, $size);
		} catch(\Throwable $e) {
			return false;
		} finally {
			if($copied){
				try { $this->obsClient->deleteObject(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$snapshot]); } catch(\Throwable $e) {}
			}
		}
	}

	private function stagedMatches($name, $hash, $size) {
		$meta = $this->obsClient->getObjectMetadata(['Bucket'=>$this->bucket, 'Key'=>$this->filepath.$name]);
		return isset($meta['ETag'], $meta['ContentLength'])
			&& intval($meta['ContentLength']) === intval($size)
			&& \lib\VerifiedUpload::sameMd5($meta['ETag'], $hash);
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$key = $this->filepath.$name;
		$url = 'https://'.$this->bucket.'.'.$this->config['endpoint'].'/';
		$formParams = [
			'Cache-Control' => 'max-age=2592000',
			'Content-Disposition' => 'attachment; filename='.$filename,
			'success_action_status' => '200'
		];
		try {
			$resp = $this->obsClient->createPostSignature([
				'Bucket' => $this->bucket,
				'Key' => $key,
				'Expires' => 3600,
				'FormParams' => $formParams,
			]);
			$param = $formParams + [
				'key' => $key,
				'AccessKeyId' => $this->config['accessKey'],
				'policy' => $resp['Policy'],
				'signature' => $resp['Signature'],
			];
			return ['url'=>$url, 'post'=>$param];
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}
	
	public function getDownUrl($name, $filename, $content_type = null){
		global $conf;
		$expires = 315360000;
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
			$resp = $this->obsClient->createSignedUrl([
				'Method' => 'GET',
				'Bucket' => $this->bucket,
				'Key' => $this->filepath.$name,
				'Expires' => $expires,
				'QueryParams' => $options
			]);
			$url = $resp['SignedUrl'];
			if(!empty($conf['downfile_domain'])){
				$url_arr = parse_url($url);
				$url = str_replace($url_arr['scheme'].'://'.$url_arr['host'], ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'], $url);
			}
			return $url;
        } catch(\Obs\ObsException $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->__toString();
			trigger_error($this->errmsg);
			return false;
		}
	}
}
