<?php
namespace lib\Storage\Ext;

/**
 * CloudFlare ImgBed（移植自 OpenList drivers/cloudflare_imgbed）
 *
 * MarSeventh/CloudFlare-ImgBed 这类图床的后端接口，Bearer 令牌。
 * 图床会按自己的命名规则给上传的文件改名，上传接口返回的 src 才是它真正的路径，
 * 所以本站文件名 → 图床路径记在 pre_stor_map 里，查大小时列目录按这个路径找。
 * 上传按 OpenList 分三种：20MB 以下普通表单；更大的按「大文件渠道类型」走分块上传（telegram / cfr2 / s3 / discord）
 * 或 HuggingFace 的 LFS 直传；没配大文件渠道类型时照样用普通表单传。
 * 和 OpenList 的一处不同：下载地址一律用「图床地址/file/路径」，不拼它返回的 publicUrl 的域名
 * （OpenList 那样拼会丢掉 /file 这一段）。
 */
class CloudflareImgbed extends Base
{
	const HF_THRESHOLD = 20971520;

	private $address;
	private $dir;

	public static function meta()
	{
		return [
			'name' => 'CloudFlare ImgBed',
			'icon' => 'fa-picture-o',
			'group' => '协议与自建服务',
			'source' => 'cloudflare_imgbed',
			'desc' => '自建的 CloudFlare ImgBed 图床（后端可以是 Telegram、R2、S3、Discord、HuggingFace），下载走它的文件地址。',
			'direct' => true,
			'range' => true,
			'link' => 'https://github.com/MarSeventh/CloudFlare-ImgBed',
			'link_text' => '项目地址',
			'fields' => [
				['name'=>'address', 'label'=>'图床地址', 'required'=>true, 'placeholder'=>'https://img.example.com'],
				['name'=>'token', 'label'=>'认证令牌', 'type'=>'secret', 'required'=>true, 'tip'=>'图床后台生成的 API Token，需要上传、列表、删除权限。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file'],
				['name'=>'smallChannelName', 'label'=>'普通文件渠道名', 'tip'=>'20MB 以下的文件上传到的渠道名。'],
				['name'=>'largeChannelName', 'label'=>'大文件渠道名'],
				['name'=>'largeChannelType', 'label'=>'大文件渠道类型', 'type'=>'select', 'default'=>'', 'options'=>[''=>'不单独处理（照样用普通表单传）', 'huggingface'=>'Hugging Face（直传）', 'telegram'=>'Telegram（分块）', 'cfr2'=>'Cloudflare R2（分块）', 's3'=>'S3（分块）', 'discord'=>'Discord（分块）']],
			],
		];
	}

	protected function init()
	{
		$addr = rtrim(trim($this->c('address')), '/');
		if($addr !== '' && !preg_match('#^https?://#i', $addr))$addr = 'https://'.$addr;
		$this->address = $addr;
		$this->dir = $this->cleanPath($this->c('root_folder_path', '/'));
	}

	private function api($method, $path, $opts = [])
	{
		if($this->address === '' || $this->c('token') === '')return $this->fail('请填写图床地址和认证令牌');
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['Authorization'=>'Bearer '.$this->c('token')];
		for($i = 0; $i < 3; $i++){
			$res = $this->request($method, strpos($path, 'http') === 0 ? $path : $this->address.$path, $opts);
			if($res === false)return false;
			if($res['status'] == 429){
				sleep(($i + 1) * 2);
				continue;
			}
			break;
		}
		$j = json_decode($res['body'], true);
		if(is_array($j) && (!empty($j['error']) || (!empty($j['message']) && empty($j['success']) && !isset($j[0])))){
			return $this->fail('图床接口报错：'.(!empty($j['error']) ? $j['error'] : $j['message']));
		}
		if($res['status'] >= 400)return $this->fail('图床请求失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		return is_array($j) ? $j : [];
	}

	private function srcPath($resp)
	{
		$src = isset($resp[0]['src']) ? $resp[0]['src'] : (isset($resp['src']) ? $resp['src'] : '');
		if($src === '')return $this->fail('图床没有返回文件路径（src）');
		if(strpos($src, '/file/') === 0)$src = substr($src, 6);
		return ltrim($src, '/');
	}

	protected function check()
	{
		$r = $this->api('GET', '/api/manage/list', ['query'=>['start'=>'0', 'count'=>'1', 'dir'=>'/']]);
		if($r === false)return $this->fail('连接图床失败：'.$this->errmsg);
		if($this->c('smallChannelName') === '')return $this->fail('请填写普通文件渠道名');
		return true;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$p = $this->mapGet($name);
		if($p === null){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		$pos = strrpos('/'.$p, '/');
		$parent = $pos === 0 ? '/' : substr('/'.$p, 0, $pos);
		for($start = 0; ; $start += 1000){
			$r = $this->api('GET', '/api/manage/list', ['query'=>['dir'=>$parent, 'start'=>(string)$start, 'count'=>'1000']]);
			if($r === false)return false;
			$files = isset($r['files']) ? $r['files'] : [];
			foreach($files as $f){
				if(trim($f['name'], '/') === $p){
					$m = isset($f['metadata']) ? $f['metadata'] : [];
					$size = isset($m['FileSizeBytes']) ? $m['FileSizeBytes'] : (isset($m['File-Size']) ? $m['File-Size'] : 0);
					return ['size'=>intval($size), 'id'=>$p];
				}
			}
			$dirs = isset($r['directories']) ? $r['directories'] : [];
			if(count($files) + count($dirs) < 1000)break;
		}
		$this->notfound = true;
		return $this->fail('文件不存在');
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$mime = $content_type ? $content_type : 'application/octet-stream';
		$type = $this->c('largeChannelType');
		if($size < self::HF_THRESHOLD || $type === ''){
			$p = $this->standard($name, $tmpfile, $size);
		}elseif($type === 'huggingface'){
			$p = $this->huggingface($name, $tmpfile, $size, $mime);
		}else{
			$p = $this->chunked($name, $tmpfile, $size, $mime, $type);
		}
		if($p === false)return false;
		$this->mapSet($name, $p);
		return true;
	}

	private function standard($name, $tmpfile, $size)
	{
		$channel = $size >= self::HF_THRESHOLD ? $this->c('largeChannelName') : $this->c('smallChannelName');
		if($channel === '')return $this->fail('没有配置'.($size >= self::HF_THRESHOLD ? '大文件' : '普通文件').'渠道名');
		$r = $this->api('POST', '/upload', [
			'query' => ['returnFormat'=>'default', 'channelName'=>$channel, 'uploadFolder'=>$this->dir, 'autoRetry'=>'true'],
			'multipart' => ['file'=>new \CURLFile($tmpfile, 'application/octet-stream', $name)],
			'timeout' => 0,
		]);
		return $r === false ? false : $this->srcPath($r);
	}

	private function chunked($name, $tmpfile, $size, $mime, $type)
	{
		$channel = $this->c('largeChannelName');
		if($channel === '')return $this->fail('分块上传要先配置大文件渠道名');
		$cs = 5242880;
		$total = (int)ceil($size / $cs);
		$init = $this->api('POST', '/upload', [
			'query' => ['initChunked'=>'true', 'uploadChannel'=>$type, 'channelName'=>$channel],
			'form' => ['originalFileName'=>$name, 'originalFileType'=>$mime, 'totalChunks'=>(string)$total],
		]);
		if($init === false)return false;
		if(empty($init['success']) || empty($init['uploadId']))return $this->fail('图床初始化分块上传失败（没有返回 uploadId）');
		for($i = 0; $i < $total; $i++){
			$part = tempnam(sys_get_temp_dir(), 'imgbed');
			file_put_contents($part, $this->readPart($tmpfile, $i * $cs, min($cs, $size - $i * $cs)));
			$ok = false;
			for($try = 0; $try < 3 && !$ok; $try++){
				$res = $this->request('POST', $this->address.'/upload?'.http_build_query(['chunked'=>'true', 'uploadChannel'=>$type, 'channelName'=>$channel]), [
					'headers' => ['Authorization'=>'Bearer '.$this->c('token')],
					'multipart' => ['uploadId'=>$init['uploadId'], 'chunkIndex'=>(string)$i, 'totalChunks'=>(string)$total, 'originalFileName'=>$name, 'originalFileType'=>$mime, 'file'=>new \CURLFile($part, 'application/octet-stream', $name)],
					'timeout' => 0,
				]);
				$ok = $res !== false && $res['status'] == 200;
			}
			@unlink($part);
			if(!$ok)return $this->fail('图床第 '.($i + 1).' 块上传失败'.($res ? '（HTTP '.$res['status'].'）' : '：'.$this->errmsg));
		}
		$r = $this->api('POST', '/upload', [
			'query' => ['chunked'=>'true', 'merge'=>'true', 'uploadChannel'=>$type, 'channelName'=>$channel, 'returnFormat'=>'default', 'uploadFolder'=>$this->dir],
			'form' => ['uploadId'=>$init['uploadId'], 'totalChunks'=>(string)$total, 'originalFileName'=>$name, 'originalFileType'=>$mime],
			'timeout' => 0,
		]);
		return $r === false ? false : $this->srcPath($r);
	}

	private function huggingface($name, $tmpfile, $size, $mime)
	{
		$channel = $this->c('largeChannelName');
		if($channel === '')return $this->fail('HuggingFace 直传要先配置大文件渠道名');
		$g = $this->api('POST', '/upload/huggingface/getUploadUrl', ['json'=>[
			'fileName' => $name, 'fileType' => $mime, 'fileSize' => $size,
			'sha256' => hash_file('sha256', $tmpfile),
			'fileSample' => base64_encode($this->readPart($tmpfile, 0, min($size, 512))),
			'channelName' => $channel, 'uploadFolder' => $this->dir,
		]]);
		if($g === false)return false;
		//秒传：HuggingFace 上已经有同样内容
		if(empty($g['alreadyExists']) && !empty($g['needsLfs'])){
			if(empty($g['uploadAction']['href']))return $this->fail('图床没有给出 HuggingFace 上传地址');
			$href = $g['uploadAction']['href'];
			$headers = isset($g['uploadAction']['header']) ? $g['uploadAction']['header'] : [];
			if(isset($headers['chunk_size'])){
				$cs = intval($headers['chunk_size']);
				if($cs <= 0)$cs = 20971520;
				$urls = [];
				foreach($headers as $k=>$v)if($k !== 'chunk_size' && ctype_digit((string)$k))$urls[intval($k)] = $v;
				ksort($urls);
				$parts = [];
				foreach($urls as $n=>$url){
					$off = ($n - 1) * $cs;
					$res = $this->request('PUT', $url, ['file'=>$tmpfile, 'offset'=>$off, 'length'=>min($cs, $size - $off), 'timeout'=>0]);
					if($res === false)return false;
					if($res['status'] != 200)return $this->fail('HuggingFace 第 '.$n.' 片上传失败（HTTP '.$res['status'].'）');
					$parts[] = ['partNumber'=>$n, 'etag'=>isset($res['headers']['etag']) ? $res['headers']['etag'] : ''];
				}
				$res = $this->request('POST', $href, ['headers'=>['Content-Type'=>'application/vnd.git-lfs+json', 'Authorization'=>'Bearer '.$this->c('token')], 'body'=>json_encode(['oid'=>$g['oid'], 'parts'=>$parts])]);
				if($res === false)return false;
				if($res['status'] != 200)return $this->fail('HuggingFace 合并分片失败：'.$this->brief($res['body']));
			}else{
				$res = $this->request('PUT', $href, ['headers'=>$headers, 'file'=>$tmpfile, 'length'=>$size, 'timeout'=>0]);
				if($res === false)return false;
				if($res['status'] != 200)return $this->fail('HuggingFace 直传失败（HTTP '.$res['status'].'）');
			}
		}
		$c = $this->api('POST', '/upload/huggingface/commitUpload', ['json'=>[
			'fullId' => isset($g['fullId']) ? $g['fullId'] : '', 'filePath' => isset($g['filePath']) ? $g['filePath'] : '',
			'sha256' => isset($g['oid']) ? $g['oid'] : '', 'fileSize' => $size, 'fileName' => $name, 'fileType' => $mime,
			'channelName' => isset($g['channelName']) ? $g['channelName'] : $channel,
		]]);
		if($c === false)return false;
		if(empty($c['success']))return $this->fail('图床登记 HuggingFace 文件失败');
		return $this->srcPath($c);
	}

	protected function remove($name)
	{
		$p = $this->mapGet($name);
		if($p === null)return true;
		$enc = implode('/', array_map('rawurlencode', explode('/', $p)));
		return $this->api('POST', '/api/manage/delete/'.$enc, ['query'=>['folder'=>'false'], 'body'=>'']) !== false;
	}

	protected function link($name)
	{
		$p = $this->mapGet($name);
		if($p === null){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['url'=>$this->address.'/file/'.implode('/', array_map('rawurlencode', explode('/', $p)))];
	}
}
