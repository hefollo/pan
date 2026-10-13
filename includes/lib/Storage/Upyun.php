<?php
namespace lib\Storage;
use \lib\IStorage;

class Upyun implements IStorage {
	private $config;
	private $client;
	private $errmsg;
	private $filepath = 'file/';

	public function __construct($config) {
		$this->config = $config;
		$serviceConfig = new \Upyun\Config($config['serviceName'], $config['operatorName'], $config['operatorPwd']);
		$this->client = new \Upyun\Upyun($serviceConfig);
	}
	
	public function getClient(){
		return $this->client;
	}

	public function errmsg(){
		return $this->errmsg;
	}

	public function exists($name) {
		try {
			return $this->client->has($this->filepath.$name);
        } catch(\Exception $e) {
			return false;
		}
	}

	public function downloadTo($name, $path) {
		$handle = fopen($path, 'wb');
		if(!$handle)return false;
		try {
			$this->client->read($this->filepath.$name, $handle);
			return true;
		} catch(\Throwable $e) { return false; }
		finally { fclose($handle); }
	}

	public function get($name) {
		try {
			return $this->client->read($this->filepath.$name);
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function downfile($name, $range = false) {
		try {
			$body = $this->client->read($this->filepath.$name, true);
			while(!$body -> eof()){
				\download_output_chunk($body -> read(102400));
			}
			return true;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function upload($name, $tmpfile, $content_type = null) {
		$params['Content-Type'] = $content_type;
        try {
			$this->client->write($this->filepath.$name, fopen($tmpfile, 'rb'), $params);
			return true;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}
	
	public function savefile($name, $tmpfile, $content_type = null) {
		return $this->upload($name, $tmpfile, $content_type);
	}

	public function getinfo($name) {
		try {
			$info = $this->client->info($this->filepath.$name);
			$minetype = $this->client->getMimetype($this->filepath.$name);
			$result = ['length'=>$info['x-upyun-file-size'], 'content_type'=>$minetype];
			return $result;
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	public function delete($name) {
		try {
			return $this->client->delete($this->filepath.$name);
        } catch(\Exception $e) {
			$this->errmsg = __FUNCTION__ . ": " . $e->getMessage();
			trigger_error($this->errmsg);
			return false;
		}
	}

	/*
	 * 浏览器直传完成后的核对发布，在存储端完成，文件不经过本站（约定见 lib\VerifiedUpload::publish）。
	 * 又拍云查询文件信息时会返回内容的 MD5（Content-Md5）。
	 * 先把 pending 对象复制到一个浏览器写不了的快照键（直传凭证里的 key 是写死的，只能写它自己那一个），
	 * 对快照核对校验值和长度，通过才复制成正式文件，最后删掉快照。
	 * 只有真的发布了才返回 true；返回 false 表示「这里定不了」，调用方会改走取回本站核对的老办法。
	 */
	public function publishStaged($stagingName, $hash, $size, $content_type = null) {
		$snapshot = 'pending/'.bin2hex(random_bytes(24));
		$copied = false;
		try {
			//又拍云的直链不带任何参数，浏览器拿到的类型只能是存下来的那一份。复制会把类型原样带过去，
			//所以直传上去的类型不对就不走这条路，让调用方按老办法重传（那边会带上正确的类型）
			if(!$this->stagedMatches($stagingName, $hash, $size, $content_type))return false;
			if(!$this->client->copy($this->filepath.$stagingName, $this->filepath.$snapshot))return false;
			$copied = true;
			if(!$this->stagedMatches($snapshot, $hash, $size, $content_type))return false;
			if(!$this->client->copy($this->filepath.$snapshot, $this->filepath.$hash))return false;
			return $this->stagedMatches($hash, $hash, $size, $content_type);
		} catch(\Throwable $e) {
			return false;
		} finally {
			if($copied){
				try { $this->client->delete($this->filepath.$snapshot); } catch(\Throwable $e) {}
			}
		}
	}

	private function stagedMatches($name, $hash, $size, $content_type) {
		$info = $this->client->info($this->filepath.$name, ['Content-Md5', 'Content-Type']);
		if(!is_array($info) || !isset($info['x-upyun-file-size'], $info['content-md5']))return false;
		if(intval($info['x-upyun-file-size']) !== intval($size) || !\lib\VerifiedUpload::sameMd5($info['content-md5'], $hash))return false;
		if($content_type){
			$stored = isset($info['content-type']) ? strtolower(trim(explode(';', $info['content-type'])[0])) : '';
			if($stored !== strtolower($content_type))return false;
		}
		return true;
	}

	public function getUploadParam($name, $filename, $max_file_size = 0){
		$params = [];
		if($max_file_size > 0){
			$params['content-length-range'] = '1,'.$max_file_size;
		}
		$result = $this->client->getUploadParam($this->filepath.$name, $params);
		$post = [
			'policy' => $result['policy'],
			'authorization' => $result['authorization'],
		];
		return ['url'=>$result['url'], 'post'=>$post];
	}

	public function getDownUrl($name, $filename, $content_type = null){
		global $conf;
		if(empty($conf['downfile_domain'])){
			$this->errmsg = '文件下载域名不能为空';
			return false;
		}
		$url = ($conf['downfile_protocol']==1?'https://':'http://').$conf['downfile_domain'].'/'.$this->filepath.$name;
		return $url;
	}
}
