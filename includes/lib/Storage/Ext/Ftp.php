<?php
namespace lib\Storage\Ext;

/**
 * FTP（移植自 OpenList drivers/ftp）
 *
 * OpenList 用的是 Go 的 jlaffaye/ftp，这里换成 cURL 自带的 ftp://：
 * 不需要另开 PHP 的 ftp 扩展，断点续传（REST）和流式读写 cURL 都现成支持。
 * 跟 OpenList 一样只能中转：FTP 地址要带账号密码，不能发给浏览器。
 */
class Ftp extends Base
{
	protected $host;
	protected $root;

	public static function meta()
	{
		return [
			'name' => 'FTP',
			'icon' => 'fa-server',
			'group' => '协议与自建服务',
			'source' => 'ftp',
			'desc' => '标准 FTP 服务器（也支持显式 TLS 加密的 FTPS），上传下载都走本站中转。',
			'direct' => false,
			'range' => true,
			'fields' => [
				['name'=>'address', 'label'=>'服务器地址', 'required'=>true, 'placeholder'=>'ftp.example.com:21', 'tip'=>'主机名或 IP，端口不是 21 的写成 <b>主机:端口</b>。'],
				['name'=>'username', 'label'=>'账号', 'tip'=>'留空按匿名（anonymous）登录。'],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'服务器上的绝对路径，目录不存在会在第一次上传时自动创建。'],
				['name'=>'encoding', 'label'=>'服务器文件名编码', 'type'=>'select', 'default'=>'', 'options'=>[''=>'UTF-8（默认）', 'GBK'=>'GBK（老的 Windows FTP 服务器）', 'BIG5'=>'BIG5', 'SHIFT_JIS'=>'Shift_JIS'], 'tip'=>'只影响存储目录里的中文；本站存进去的文件名都是英文数字。'],
				['name'=>'tls', 'label'=>'加密', 'type'=>'select', 'default'=>'0', 'options'=>['0'=>'不加密（普通 FTP）', '1'=>'显式 TLS（FTPES，服务器支持才选）'], 'tip'=>'OpenList 的 FTP 驱动只做普通 FTP；这里多给一个 TLS 选项，选了之后服务器不支持就会连接失败。'],
			],
		];
	}

	protected function init()
	{
		$addr = trim($this->c('address'));
		$addr = preg_replace('#^ftps?://#i', '', $addr);
		$this->host = rtrim($addr, '/');
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/pan/file')), '/');
	}

	//服务器那边的路径（已按服务器编码转换），DELE 这种命令里直接用
	protected function serverPath($name)
	{
		return $this->enc($this->root.'/'.$name);
	}

	protected function enc($str)
	{
		$encoding = $this->c('encoding');
		if($encoding === '' || !function_exists('mb_convert_encoding'))return $str;
		return mb_convert_encoding($str, $encoding, 'UTF-8');
	}

	/*
	 * cURL 的 FTP 地址里，主机后面的路径是相对登录目录的；开头写 %2F 才是绝对路径。
	 * 每段单独 URL 编码，斜杠保持原样，cURL 会按段 CWD 进去。
	 */
	protected function url($path)
	{
		$segs = array_map('rawurlencode', explode('/', ltrim($this->enc($path), '/')));
		return 'ftp://'.$this->host.'/%2F'.implode('/', $segs);
	}

	//报错信息里的协议名，SFTP 子类改掉
	protected function label()
	{
		return 'FTP';
	}

	//登录方式和协议相关的选项，返回 false 表示参数不全
	protected function connect($ch)
	{
		$user = $this->c('username');
		curl_setopt($ch, CURLOPT_USERPWD, ($user === '' ? 'anonymous' : $user).':'.($user === '' ? 'anonymous@' : $this->c('password')));
		//被动模式：本站多半在 NAT 后面，主动模式服务器连不回来
		curl_setopt($ch, CURLOPT_FTP_USE_EPSV, true);
		if($this->c('tls') === '1'){
			curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
		}
		return true;
	}

	//删除命令和发命令用的地址
	protected function deleteRequest($name)
	{
		//QUOTE 在登录之后、进目录之前发，所以 DELE 要写服务器上的绝对路径
		return ['ftp://'.$this->host.'/', 'DELE '.$this->serverPath($name)];
	}

	protected function curl($url, $opts)
	{
		if(!function_exists('curl_init'))return $this->fail('这个存储需要服务器开启 PHP cURL 扩展');
		if($this->host === '')return $this->fail('请先填写 '.$this->label().' 服务器地址');
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if($this->connect($ch) === false){
			curl_close($ch);
			return false;
		}
		foreach($opts as $k=>$v)curl_setopt($ch, $k, $v);
		$body = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		return ['ok'=>$errno === 0, 'errno'=>$errno, 'error'=>$error, 'info'=>$info, 'body'=>$body];
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$res = $this->curl($this->url($this->root.'/'.$name), [CURLOPT_NOBODY=>true, CURLOPT_FILETIME=>true, CURLOPT_TIMEOUT=>30]);
		if($res === false)return false;
		if(!$res['ok']){
			//78：服务器说文件不存在；19：RETR/SIZE 被拒，也多半是不存在
			if(in_array($res['errno'], [78, 19], true)){
				$this->notfound = true;
				return $this->fail('文件不存在');
			}
			return $this->fail($this->label().' 查询文件失败：'.$res['error']);
		}
		$size = isset($res['info']['download_content_length']) ? $res['info']['download_content_length'] : -1;
		return ['size'=>$size < 0 ? 0 : intval($size)];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$fp = fopen($tmpfile, 'rb');
		if(!$fp)return $this->fail('读取待上传的临时文件失败');
		$res = $this->curl($this->url($this->root.'/'.$name), [
			CURLOPT_UPLOAD => true,
			CURLOPT_INFILE => $fp,
			CURLOPT_INFILESIZE => $size,
			CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY,
			CURLOPT_TIMEOUT => 0,
		]);
		fclose($fp);
		if($res === false)return false;
		if(!$res['ok'])return $this->fail($this->label().' 上传失败：'.$res['error']);
		return true;
	}

	protected function remove($name)
	{
		list($url, $cmd) = $this->deleteRequest($name);
		$res = $this->curl($url, [
			CURLOPT_NOBODY => true,
			CURLOPT_QUOTE => [$cmd],
			CURLOPT_TIMEOUT => 30,
		]);
		if($res === false)return false;
		if($res['ok'])return true;
		//删的时候文件已经没了，当作删成功；先查一下再判断，免得把权限不够也吞掉
		if($this->stat($name) === false && $this->notfound)return true;
		return $this->fail($this->label().' 删除失败：'.$res['error']);
	}

	protected function link($name)
	{
		return $this->fail($this->label().' 不能给出下载直链，请把「文件下载方式」设置为网站中转');
	}

	protected function read($name, $range, $sink)
	{
		$opts = [
			CURLOPT_TIMEOUT => 0,
			CURLOPT_WRITEFUNCTION => function($ch, $data) use ($sink){
				call_user_func($sink, $data);
				return strlen($data);
			},
		];
		if($range)$opts[CURLOPT_RANGE] = intval($range[0]).'-'.intval($range[1]);
		$res = $this->curl($this->url($this->root.'/'.$name), $opts);
		if($res === false)return false;
		if(!$res['ok'])return $this->fail($this->label().' 下载失败：'.$res['error']);
		return true;
	}
}
