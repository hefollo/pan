<?php
namespace lib\Storage\Ext;
use \lib\IStorage;

/**
 * 移植自 OpenList 的存储驱动的公共基类
 *
 * Ext 目录下的驱动都是照着 OpenList（https://github.com/OpenListTeam/OpenList ，AGPL-3.0）
 * drivers 目录里对应的 Go 驱动翻译过来的，接口细节以它为准，见项目根目录 NOTICE。
 *
 * 和本站原有那 12 种存储的区别：
 * 1. 参数不是一项一个配置键，而是整份 JSON 存在 pre_config 的 stor_<类型> 里，
 *    令牌这类运行中会变的东西另存 stor_<类型>_state。配置键只有 32 个字符，逐项存会超长。
 * 2. 设置页的表单按 meta() 里的字段说明自动生成，不用每加一种就去改 set_stor.php。
 * 3. 很多网盘按文件 ID 而不是路径寻址，本站的文件名 → 远端 ID 记在 pre_stor_map 表里，
 *    查不到时子类自己列目录兜底。
 *
 * 子类只需要实现 stat / put / remove / link 四件事；流式读取默认按 link() 给的地址去取，
 * FTP、SFTP 这种不走 HTTP 的覆盖 read() 即可。
 */
abstract class Base implements IStorage
{
	//类型键，等于 OpenList 的驱动目录名
	protected $type;
	//站长填的参数（已经按 meta() 的默认值补齐）
	protected $cfg;
	protected $errmsg;
	//最近一次 HTTP 请求的状态码，0 表示请求根本没发出去
	protected $http = 0;
	//stat() 返回 false 时，是「文件确实不存在」还是「接口出错」
	protected $notfound = false;
	private $stateCache = null;

	public function __construct($config, $type = null)
	{
		$this->type = $type;
		$this->cfg = is_array($config) ? $config : [];
		$this->init();
	}

	/*
	 * 驱动说明：设置页卡片、表单、能力标签都从这里取。
	 * name 显示名；icon Font Awesome 图标；group 设置页分组；desc 简介；link / link_text 开通或文档地址；
	 * source 对应的 OpenList 驱动目录；direct 下载能不能 302 到上游；range 能不能断点续传；
	 * no_referer 跳转直链时要不要去掉来源页；requires 需要的 PHP 扩展；
	 * fields 参数表单，每项 name / label / type（text、secret、select、textarea）/ required / default / placeholder / tip / options。
	 */
	public static function meta()
	{
		return [];
	}

	//构造完成后子类做自己的整理（拼地址、去斜杠之类），不要在这里发请求
	protected function init()
	{
	}

	/*
	 * 查文件：存在返回 ['size'=>字节数, 'id'=>远端 ID（没有就不给）]，
	 * 不存在返回 false 并把 $this->notfound 设成 true，接口出错返回 false 且 errmsg 写原因。
	 */
	abstract protected function stat($name);

	//上传，$size 是临时文件的字节数。成功返回 true（按 ID 寻址的记得 mapSet）
	abstract protected function put($name, $tmpfile, $size, $content_type);

	//删除。文件本来就不在也算成功，免得删记录时卡在这里
	abstract protected function remove($name);

	/*
	 * 取下载地址：['url'=>地址, 'headers'=>[取这个地址必须带的请求头]]。
	 * 带了 headers 的地址浏览器直接打开是取不到的，meta() 里 direct 要写 false。
	 */
	abstract protected function link($name);

	/* ---------------- IStorage ---------------- */

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
		return $this->stat($name) !== false;
	}

	public function get($name)
	{
		$buf = '';
		$ok = $this->read($name, false, function($data) use (&$buf){
			$buf .= $data;
		});
		return $ok ? $buf : false;
	}

	public function downfile($name, $range = false)
	{
		return $this->read($name, $range, function($data){
			\download_output_chunk($data);
		});
	}

	public function upload($name, $tmpfile, $content_type = null)
	{
		$stat = @stat($tmpfile);
		if($stat === false){
			$this->errmsg = '读取待上传的临时文件失败';
			return false;
		}
		return $this->put($name, $tmpfile, intval($stat['size']), $content_type) === true;
	}

	public function savefile($name, $tmpfile, $content_type = null)
	{
		$result = $this->upload($name, $tmpfile, $content_type);
		if($result)@unlink($tmpfile);
		return $result;
	}

	public function getinfo($name)
	{
		$info = $this->stat($name);
		if($info === false)return false;
		return ['length'=>isset($info['size']) ? intval($info['size']) : 0, 'content_type'=>null];
	}

	public function delete($name)
	{
		$ok = $this->remove($name);
		if($ok)$this->mapDel($name);
		return $ok;
	}

	public function getUploadParam($name, $filename, $max_file_size = 0)
	{
		$meta = static::meta();
		$this->errmsg = (isset($meta['name']) ? $meta['name'] : '该存储').' 不支持直传，请把「文件上传方式」设置为网站中转';
		return false;
	}

	public function getDownUrl($name, $filename, $content_type = null)
	{
		$link = $this->link($name);
		if($link === false)return false;
		if(!empty($link['headers'])){
			$this->errmsg = '这个存储的下载地址必须带特定请求头才能取，不能直接跳转，请把「文件下载方式」设置为网站中转';
			return false;
		}
		return $link['url'];
	}

	/*
	 * 后台「连接测试」：先查必填项和 PHP 扩展，再给子类一个检查登录的机会，
	 * 最后写一个小文件、读回来核对、删掉。
	 */
	public function test()
	{
		$meta = static::meta();
		if(!empty($meta['requires'])){
			foreach((array)$meta['requires'] as $ext){
				if(!extension_loaded($ext)){
					$this->errmsg = '服务器的 PHP 没有开启 '.$ext.' 扩展，这个存储用不了';
					return false;
				}
			}
		}
		if(!empty($meta['fields'])){
			foreach($meta['fields'] as $f){
				if(!empty($f['required']) && trim((string)$this->c($f['name'])) === ''){
					$this->errmsg = '请先填写「'.$f['label'].'」';
					return false;
				}
			}
		}
		if(!$this->check())return false;
		$name = 'pantest_'.substr(md5(uniqid('', true)), 0, 8);
		$tmp = sys_get_temp_dir().'/'.$name;
		$content = 'pan storage test '.date('Y-m-d H:i:s');
		if(@file_put_contents($tmp, $content) === false){
			$this->errmsg = '本地临时目录不可写，无法测试';
			return false;
		}
		$ok = $this->upload($name, $tmp, 'text/plain');
		@unlink($tmp);
		if(!$ok){
			$this->errmsg = '写入测试文件失败：'.$this->errmsg;
			return false;
		}
		$read = $this->get($name);
		$readerr = $this->errmsg;
		if(!$this->delete($name)){
			$this->errmsg = '测试文件 '.$name.' 写入成功但删除失败：'.$this->errmsg.'，请到网盘里手动删掉';
			return false;
		}
		if($read === false){
			$this->errmsg = '文件写入成功，但读不回来：'.$readerr;
			return false;
		}
		if($read !== $content){
			$this->errmsg = '文件写入成功，但读回的内容不一致（读到 '.strlen($read).' 字节，应为 '.strlen($content).' 字节）';
			return false;
		}
		return true;
	}

	//连接测试的第一步：登录、确认存储目录在不在。默认什么都不做
	protected function check()
	{
		return true;
	}

	/* ---------------- 读取 ---------------- */

	/*
	 * 把文件内容（或其中一段）交给 $sink，一块一块地给，不整个读进内存。
	 * $range 是 [起, 止]（含两端），false 表示整个文件。
	 */
	protected function read($name, $range, $sink)
	{
		$link = $this->link($name);
		if($link === false)return false;
		$headers = isset($link['headers']) ? $link['headers'] : [];
		if($range){
			$headers['Range'] = 'bytes='.intval($range[0]).'-'.intval($range[1]);
		}
		$res = $this->request('GET', $link['url'], ['headers'=>$headers, 'sink'=>$sink, 'timeout'=>0]);
		if($res === false)return false;
		if($res['status'] < 200 || $res['status'] >= 300){
			$this->errmsg = '下载失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']);
			return false;
		}
		return true;
	}

	/* ---------------- 参数与运行状态 ---------------- */

	//取一项参数，没填返回 $default
	protected function c($key, $default = '')
	{
		if(!isset($this->cfg[$key]))return $default;
		$v = $this->cfg[$key];
		return (is_string($v) && trim($v) === '') ? $default : $v;
	}

	//运行中保存的东西（刷新后的令牌、过期时间、Cookie 等），存在 stor_<类型>_state
	protected function state($key = null, $default = null)
	{
		if($this->stateCache === null){
			global $conf;
			$raw = $this->type !== null && isset($conf['stor_'.$this->type.'_state']) ? $conf['stor_'.$this->type.'_state'] : '';
			$arr = $raw === '' ? [] : json_decode($raw, true);
			$this->stateCache = is_array($arr) ? $arr : [];
		}
		if($key === null)return $this->stateCache;
		return array_key_exists($key, $this->stateCache) ? $this->stateCache[$key] : $default;
	}

	/*
	 * 合并写入运行状态。传 null 的键会被删掉。
	 * 后台「连接测试」用的是屏幕上填着、还没保存的参数，这时换来的令牌也照样存下：
	 * 令牌跟参数是一一对应的，参数没保存的话下次用旧参数时令牌对不上会自动重新登录。
	 */
	protected function saveState($data)
	{
		global $conf;
		$state = $this->state();
		foreach($data as $k=>$v){
			if($v === null)unset($state[$k]);
			else $state[$k] = $v;
		}
		$this->stateCache = $state;
		if($this->type === null)return;
		$json = json_encode($state, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		$conf['stor_'.$this->type.'_state'] = $json;
		if(function_exists('saveSetting'))saveSetting('stor_'.$this->type.'_state', $json);
	}

	/*
	 * 参数本身被上游改掉了（比如 refresh_token 每用一次就换一张新的），写回 stor_<类型>。
	 * 不写回的话下次请求还拿旧的去换，直接失效。
	 */
	protected function saveConfig($data)
	{
		global $conf;
		foreach($data as $k=>$v)$this->cfg[$k] = $v;
		if($this->type === null)return;
		$saved = isset($conf['stor_'.$this->type]) ? json_decode($conf['stor_'.$this->type], true) : [];
		if(!is_array($saved))$saved = [];
		foreach($data as $k=>$v)$saved[$k] = $v;
		$json = json_encode($saved, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		$conf['stor_'.$this->type] = $json;
		if(function_exists('saveSetting'))saveSetting('stor_'.$this->type, $json);
	}

	/* ---------------- 文件名 → 远端 ID ---------------- */

	protected function mapGet($name)
	{
		global $DB;
		if(!is_object($DB) || $this->type === null)return null;
		$fid = $DB->getColumn("SELECT `fid` FROM `pre_stor_map` WHERE `storage`=:s AND `name`=:n LIMIT 1", [':s'=>$this->type, ':n'=>$name]);
		return ($fid === false || $fid === null || $fid === '') ? null : (string)$fid;
	}

	protected function mapSet($name, $fid)
	{
		global $DB;
		if(!is_object($DB) || $this->type === null)return;
		$DB->exec("REPLACE INTO `pre_stor_map` (`storage`,`name`,`fid`,`addtime`) VALUES (:s,:n,:f,NOW())", [':s'=>$this->type, ':n'=>$name, ':f'=>(string)$fid]);
	}

	protected function mapDel($name)
	{
		global $DB;
		if(!is_object($DB) || $this->type === null)return;
		$DB->exec("DELETE FROM `pre_stor_map` WHERE `storage`=:s AND `name`=:n", [':s'=>$this->type, ':n'=>$name]);
	}

	/* ---------------- HTTP ---------------- */

	/*
	 * 发一个 HTTP 请求，不管状态码是多少都把响应交回来（很多接口的错误信息在 4xx 的响应体里）。
	 * 只有请求根本没发出去（DNS、连接、超时）才返回 false。
	 *
	 * $opts：
	 *   headers   ['名'=>'值'] 或 ['名: 值']
	 *   query     拼到地址后面的参数
	 *   json      按 JSON 发的请求体
	 *   form      按 x-www-form-urlencoded 发的请求体
	 *   multipart 按 multipart/form-data 发，值可以是 \CURLFile
	 *   body      原样发出的请求体
	 *   file      把这个文件当请求体发出去（流式，不读进内存），配合 offset / length 只发其中一段
	 *   sink      回调函数：2xx 的响应体一块块交给它，不攒在内存里
	 *   timeout   总超时秒数，0 不限（大文件传输用）
	 *   follow    跟不跟跳转，默认跟
	 *   cookie    Cookie 字符串
	 *   proxy     不用环境代理时传 false（暂未用到）
	 * 返回 ['status'=>, 'headers'=>[小写名=>值], 'cookies'=>[名=>值], 'body'=>, 'url'=>最终地址]
	 */
	protected function request($method, $url, $opts = [])
	{
		$this->http = 0;
		if(!function_exists('curl_init')){
			$this->errmsg = '这个存储需要服务器开启 PHP cURL 扩展';
			return false;
		}
		if(!empty($opts['query'])){
			$url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($opts['query'], '', '&', PHP_QUERY_RFC3986);
		}
		$headers = [];
		if(!empty($opts['headers'])){
			foreach($opts['headers'] as $k=>$v){
				if(is_int($k))$headers[] = $v;
				elseif($v !== null)$headers[] = $k.': '.$v;
			}
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !isset($opts['follow']) || $opts['follow']);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, isset($opts['timeout']) ? intval($opts['timeout']) : 60);
		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 60);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		if(!empty($opts['cookie']))curl_setopt($ch, CURLOPT_COOKIE, $opts['cookie']);
		if($method === 'HEAD')curl_setopt($ch, CURLOPT_NOBODY, true);

		$handle = null;
		if(array_key_exists('json', $opts)){
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
		}elseif(isset($opts['form'])){
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
			curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($opts['form']) ? http_build_query($opts['form'], '', '&') : $opts['form']);
		}elseif(isset($opts['multipart'])){
			curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['multipart']);
		}elseif(isset($opts['body'])){
			curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
		}elseif(!empty($opts['file'])){
			$handle = fopen($opts['file'], 'rb');
			if(!$handle){
				curl_close($ch);
				$this->errmsg = '读取待上传的临时文件失败';
				return false;
			}
			$offset = isset($opts['offset']) ? intval($opts['offset']) : 0;
			if(isset($opts['length'])){
				$length = intval($opts['length']);
			}else{
				$fs = fstat($handle);
				$length = $fs['size'] - $offset;
			}
			if($offset > 0)fseek($handle, $offset);
			$left = $length;
			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_INFILESIZE, $length);
			curl_setopt($ch, CURLOPT_READFUNCTION, function($ch, $fp, $want) use ($handle, &$left){
				if($left <= 0)return '';
				$data = fread($handle, min($want, $left));
				if($data === false)return '';
				$left -= strlen($data);
				return $data;
			});
		}
		//部分反代不认 Expect: 100-continue，大文件上传会一直卡到超时
		$headers[] = 'Expect:';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$respHeaders = [];
		$cookies = [];
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$respHeaders, &$cookies){
			//跟了跳转的话会收到好几段响应头，新的一段开始时把上一段清掉
			if(stripos($line, 'HTTP/') === 0){
				$respHeaders = [];
				return strlen($line);
			}
			$pos = strpos($line, ':');
			if($pos !== false){
				$k = strtolower(trim(substr($line, 0, $pos)));
				$v = trim(substr($line, $pos + 1));
				$respHeaders[$k] = $v;
				if($k === 'set-cookie'){
					$pair = explode(';', $v, 2);
					$eq = strpos($pair[0], '=');
					if($eq !== false)$cookies[trim(substr($pair[0], 0, $eq))] = trim(substr($pair[0], $eq + 1));
				}
			}
			return strlen($line);
		});
		$errbody = '';
		if(isset($opts['sink'])){
			$sink = $opts['sink'];
			//只有 2xx 的内容才交出去，错误页攒起来留作报错，不能当文件内容发给下载的人
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($sink, &$errbody){
				$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
				if($code >= 200 && $code < 300){
					call_user_func($sink, $data);
				}elseif(strlen($errbody) < 65536){
					$errbody .= $data;
				}
				return strlen($data);
			});
		}
		$body = curl_exec($ch);
		$status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$error = curl_error($ch);
		curl_close($ch);
		if($handle)fclose($handle);
		$this->http = $status;
		if($body === false){
			$this->errmsg = '请求 '.$this->host($url).' 失败：'.($error ? $error : '未知错误');
			return false;
		}
		if(isset($opts['sink']))$body = $errbody;
		return ['status'=>$status, 'headers'=>$respHeaders, 'cookies'=>$cookies, 'body'=>is_string($body) ? $body : '', 'url'=>$final];
	}

	/*
	 * 请求并把响应体按 JSON 解开。不是 JSON 返回 false；是 JSON 就原样返回数组，
	 * 业务上成没成功由调用方看各家自己的 code / errno 字段。
	 */
	protected function json($method, $url, $opts = [])
	{
		$res = $this->request($method, $url, $opts);
		if($res === false)return false;
		$json = json_decode($res['body'], true);
		if(!is_array($json)){
			$this->errmsg = $this->host($url).' 返回了无法识别的内容（HTTP '.$res['status'].'）：'.$this->brief($res['body']);
			return false;
		}
		return $json;
	}

	protected function host($url)
	{
		$h = parse_url($url, PHP_URL_HOST);
		return $h ? $h : $url;
	}

	//错误页可能是一整页 HTML，只留开头一段
	protected function brief($body)
	{
		if(!is_string($body) || trim($body) === '')return '无返回内容';
		$text = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
		return function_exists('mb_substr') ? mb_substr($text, 0, 200, 'UTF-8') : substr($text, 0, 200);
	}

	//报错并返回 false，子类里一行写完
	protected function fail($msg)
	{
		$this->errmsg = $msg;
		return false;
	}

	/* ---------------- 工具 ---------------- */

	//整个文件的哈希，流式算，不读进内存
	protected function fileHash($path, $algo = 'md5')
	{
		return hash_file($algo, $path);
	}

	//文件里某一段的哈希
	protected function partHash($path, $offset, $length, $algo = 'md5', $raw = false)
	{
		$fp = fopen($path, 'rb');
		if(!$fp)return false;
		fseek($fp, $offset);
		$ctx = hash_init($algo);
		$left = $length;
		while($left > 0 && !feof($fp)){
			$data = fread($fp, min(1048576, $left));
			if($data === false || $data === '')break;
			hash_update($ctx, $data);
			$left -= strlen($data);
		}
		fclose($fp);
		return hash_final($ctx, $raw);
	}

	//读文件里的一段（分片上传时用，片一般只有几 MB）
	protected function readPart($path, $offset, $length)
	{
		$fp = fopen($path, 'rb');
		if(!$fp)return false;
		fseek($fp, $offset);
		$data = '';
		while(strlen($data) < $length && !feof($fp)){
			$chunk = fread($fp, $length - strlen($data));
			if($chunk === false || $chunk === '')break;
			$data .= $chunk;
		}
		fclose($fp);
		return $data;
	}

	//去掉首尾斜杠后以 / 开头的路径，空串得到 /
	protected function cleanPath($path)
	{
		$path = trim(str_replace('\\', '/', (string)$path), " \t\r\n/");
		$path = preg_replace('#/+#', '/', $path);
		return '/'.$path;
	}

	protected function joinPath($dir, $name)
	{
		$dir = rtrim($this->cleanPath($dir), '/');
		return $dir.'/'.ltrim($name, '/');
	}

	protected function uuid()
	{
		$b = random_bytes(16);
		$b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
		$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
		$h = bin2hex($b);
		return substr($h, 0, 8).'-'.substr($h, 8, 4).'-'.substr($h, 12, 4).'-'.substr($h, 16, 4).'-'.substr($h, 20);
	}

	//毫秒时间戳
	protected function ms()
	{
		return (string)round(microtime(true) * 1000);
	}
}
