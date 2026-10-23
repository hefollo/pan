<?php
namespace lib\Storage\Ext;

/**
 * SMB / CIFS（移植自 OpenList drivers/smb）
 *
 * OpenList 用的是 Go 的 hirochachacha/go-smb2。PHP 这边没有能指望的现成支持：
 * cURL 的 smb:// 只能上传下载、不能删除也不能查文件，smbclient 扩展虚拟主机上基本没有。
 * 所以下面的 SmbConn 自己实现了够用的一小部分 SMB2：协商（2.0.2 / 2.1 / 3.0 / 3.0.2）、
 * SPNEGO 包着的 NTLMv2 登录、报文签名（2.x 用 HMAC-SHA256，3.x 用 AES-CMAC）、
 * 打开 / 新建 / 读 / 写 / 关闭 / 删除。不支持 SMB3 加密，共享要求加密时会直接报错。
 */
class Smb extends Base
{
	private $conn = null;
	private $root;

	public static function meta()
	{
		return [
			'name' => 'SMB / CIFS',
			'icon' => 'fa-windows',
			'group' => '协议与自建服务',
			'source' => 'smb',
			'desc' => 'Windows 共享文件夹、群晖 / 威联通等 NAS 的 SMB 共享，上传下载走本站中转。',
			'direct' => false,
			'range' => true,
			'requires' => ['openssl', 'hash', 'mbstring'],
			'fields' => [
				['name'=>'address', 'label'=>'服务器地址', 'required'=>true, 'placeholder'=>'192.168.1.5', 'tip'=>'主机名或 IP，端口不是 445 的写成 <b>主机:端口</b>。'],
				['name'=>'username', 'label'=>'账号', 'required'=>true, 'tip'=>'域账号写成 <b>域名\\账号</b>。'],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret'],
				['name'=>'share_name', 'label'=>'共享名', 'required'=>true, 'placeholder'=>'share', 'tip'=>'只填共享名本身，例如 <b>\\\\192.168.1.5\\data</b> 就填 <b>data</b>。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'pan/file', 'placeholder'=>'pan/file', 'tip'=>'共享里的子目录，留空默认为 <b>pan/file</b>，目录不存在会自动创建。'],
			],
		];
	}

	protected function init()
	{
		$this->root = trim(str_replace('\\', '/', $this->c('root_folder_path', 'pan/file')), '/');
	}

	public function __destruct()
	{
		if($this->conn)$this->conn->close();
	}

	private function conn()
	{
		if($this->conn)return $this->conn;
		$addr = trim($this->c('address'));
		$addr = preg_replace('#^(smb|cifs)://#i', '', $addr);
		$addr = trim($addr, "/\\ ");
		if($addr === '')return $this->fail('请先填写 SMB 服务器地址');
		$port = 445;
		if(preg_match('/^\[(.+)\](?::(\d+))?$/', $addr, $m)){
			$host = $m[1];
			if(!empty($m[2]))$port = intval($m[2]);
		}elseif(substr_count($addr, ':') === 1){
			list($host, $p) = explode(':', $addr);
			$port = intval($p);
		}else{
			$host = $addr;
		}
		$user = $this->c('username');
		$domain = '';
		if(strpos($user, '\\') !== false)list($domain, $user) = explode('\\', $user, 2);
		elseif(strpos($user, '/') !== false)list($domain, $user) = explode('/', $user, 2);
		$conn = new SmbConn();
		if(!$conn->connect($host, $port, $user, $this->c('password'), $domain, $this->c('share_name'))){
			return $this->fail($conn->error);
		}
		$this->conn = $conn;
		return $conn;
	}

	//SMB 路径用反斜杠，开头不带斜杠，相对共享根目录
	private function path($name)
	{
		$p = $this->root === '' ? $name : $this->root.'/'.$name;
		return str_replace('/', '\\', $p);
	}

	protected function check()
	{
		$conn = $this->conn();
		if(!$conn)return false;
		if($this->root !== '' && !$conn->mkdirs(str_replace('/', '\\', $this->root))){
			return $this->fail('创建存储目录 '.$this->root.' 失败：'.$conn->error);
		}
		return true;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$conn = $this->conn();
		if(!$conn)return false;
		$size = $conn->size($this->path($name));
		if($size === false){
			$this->notfound = $conn->notfound;
			return $this->fail($conn->notfound ? '文件不存在' : 'SMB 查询文件失败：'.$conn->error);
		}
		return ['size'=>$size];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$conn = $this->conn();
		if(!$conn)return false;
		$ok = $conn->upload($this->path($name), $tmpfile);
		//存储目录还没建：建好再传一次
		if(!$ok && $conn->notfound && $this->root !== ''){
			if(!$conn->mkdirs(str_replace('/', '\\', $this->root)))return $this->fail('创建存储目录失败：'.$conn->error);
			$ok = $conn->upload($this->path($name), $tmpfile);
		}
		return $ok ? true : $this->fail('SMB 上传失败：'.$conn->error);
	}

	protected function remove($name)
	{
		$conn = $this->conn();
		if(!$conn)return false;
		if($conn->delete($this->path($name)))return true;
		if($conn->notfound)return true;
		return $this->fail('SMB 删除失败：'.$conn->error);
	}

	protected function link($name)
	{
		return $this->fail('SMB 不能给出下载直链，请把「文件下载方式」设置为网站中转');
	}

	protected function read($name, $range, $sink)
	{
		$conn = $this->conn();
		if(!$conn)return false;
		$start = $range ? intval($range[0]) : 0;
		$end = $range ? intval($range[1]) : -1;
		if(!$conn->download($this->path($name), $start, $end, $sink)){
			return $this->fail('SMB 下载失败：'.$conn->error);
		}
		return true;
	}
}

/**
 * 最小 SMB2 客户端，只给上面的驱动用。
 * 协议细节见微软 [MS-SMB2]、[MS-NLMP]、[MS-SPNG]，以及 OpenList 依赖的 go-smb2。
 */
class SmbConn
{
	const STATUS_SUCCESS = 0;
	const STATUS_PENDING = 0x00000103;
	const STATUS_MORE_PROCESSING = 0xC0000016;
	const STATUS_END_OF_FILE = 0xC0000011;
	const STATUS_NAME_NOT_FOUND = 0xC0000034;
	const STATUS_PATH_NOT_FOUND = 0xC000003A;
	const STATUS_NAME_COLLISION = 0xC0000035;
	const STATUS_NO_SUCH_FILE = 0xC000000F;

	public $error = '';
	public $notfound = false;

	private $sock = null;
	private $mid = 0;
	private $credits = 1;
	private $dialect = 0;
	private $sessionId = "\0\0\0\0\0\0\0\0";
	private $treeId = 0;
	private $signKey = null;
	private $maxRead = 65536;
	private $maxWrite = 65536;
	private $host;

	public function connect($host, $port, $user, $pass, $domain, $share)
	{
		$this->host = $host;
		$errno = 0;
		$errstr = '';
		$sock = @stream_socket_client('tcp://'.(strpos($host, ':') !== false ? '['.$host.']' : $host).':'.$port, $errno, $errstr, 15);
		if(!$sock)return $this->err('连接 '.$host.':'.$port.' 失败：'.$errstr);
		stream_set_timeout($sock, 60);
		$this->sock = $sock;
		if(!$this->negotiate())return false;
		if(!$this->sessionSetup($user, $pass, $domain))return false;
		$share = trim(str_replace('/', '\\', $share), '\\');
		if($share === '')return $this->err('请填写共享名');
		return $this->treeConnect('\\\\'.$host.'\\'.$share);
	}

	public function close()
	{
		if($this->sock){
			@fclose($this->sock);
			$this->sock = null;
		}
	}

	private function err($msg)
	{
		$this->error = $msg;
		return false;
	}

	/* ---------------- 收发 ---------------- */

	private function u16($s){ return mb_convert_encoding($s, 'UTF-16LE', 'UTF-8'); }

	/*
	 * 发一条请求并等它的最终响应。$charge 是这条请求占用的额度（大读写时按 64KB 一份算）。
	 * 返回 [状态码, 响应头数组, 响应体]，网络出错返回 false。
	 */
	private function call($cmd, $body, $charge = 1)
	{
		if(!$this->sock)return $this->err('连接已断开');
		//2.0.2 没有多额度请求，CreditCharge 必须写 0
		$cc = $this->dialect === 0x0202 || $this->dialect === 0 ? 0 : max(1, $charge);
		$mid = $this->mid;
		$this->mid += max(1, $cc);
		//每条请求至少用掉一份额度，响应里再把服务器批的加回来
		$this->credits -= max(1, $cc);
		$signed = $this->signKey !== null && $cmd !== 0 && $cmd !== 1;
		$hdr = "\xFESMB".pack('v', 64).pack('v', $cc).pack('V', 0).pack('v', $cmd)
			.pack('v', 64).pack('V', $signed ? 0x8 : 0).pack('V', 0).pack('P', $mid)
			.pack('V', 0x0000FEFF).pack('V', $this->treeId).$this->sessionId.str_repeat("\0", 16);
		$msg = $hdr.$body;
		if($signed)$msg = substr($msg, 0, 48).$this->sign($msg).substr($msg, 64);
		$len = strlen($msg);
		$frame = "\0".chr(($len >> 16) & 0xff).chr(($len >> 8) & 0xff).chr($len & 0xff).$msg;
		for($off = 0; $off < strlen($frame); ){
			$n = @fwrite($this->sock, substr($frame, $off, 1048576));
			if($n === false || $n === 0)return $this->err('发送数据失败，连接被断开');
			$off += $n;
		}
		while(true){
			$res = $this->recv();
			if($res === false)return false;
			list($status, $h, $b) = $res;
			if($h['mid'] !== $mid)continue;
			//异步的中间响应：服务器先回一个 PENDING，真结果之后才来
			if($status === self::STATUS_PENDING && ($h['flags'] & 0x2))continue;
			return $res;
		}
	}

	private function readn($n)
	{
		$buf = '';
		while(strlen($buf) < $n){
			$chunk = @fread($this->sock, $n - strlen($buf));
			if($chunk === false || $chunk === ''){
				$meta = stream_get_meta_data($this->sock);
				return $this->err(!empty($meta['timed_out']) ? '等待服务器响应超时' : '连接被服务器断开');
			}
			$buf .= $chunk;
		}
		return $buf;
	}

	private function recv()
	{
		$nb = $this->readn(4);
		if($nb === false)return false;
		$len = (ord($nb[1]) << 16) | (ord($nb[2]) << 8) | ord($nb[3]);
		$msg = $this->readn($len);
		if($msg === false)return false;
		if(substr($msg, 0, 4) === "\xFDSMB")return $this->err('服务器要求 SMB3 加密传输，本站的 SMB 客户端不支持加密，请在共享上关闭「加密数据访问」');
		if(substr($msg, 0, 4) !== "\xFESMB")return $this->err('服务器返回的不是 SMB2 报文（可能只支持已淘汰的 SMB1）');
		$u = unpack('vsize/vcharge/Vstatus/vcmd/vcredits/Vflags/Vnext/Pmid', substr($msg, 4, 32));
		$this->credits += $u['credits'];
		/*
		 * 服务器签了名的响应要核对：签名对不上说明中间有人改包，或者会话密钥算错了，
		 * 两种情况都不能接着用这个连接。异步的中间响应（PENDING）按协议不签名，跳过。
		 */
		if($this->signKey !== null && ($u['flags'] & 0x8) && $u['status'] !== self::STATUS_PENDING){
			$zeroed = substr($msg, 0, 48).str_repeat("\0", 16).substr($msg, 64);
			if(!hash_equals($this->sign($zeroed), substr($msg, 48, 16))){
				$this->close();
				return $this->err('服务器响应的签名校验失败，连接已断开');
			}
		}
		$h = ['status'=>$u['status'], 'cmd'=>$u['cmd'], 'flags'=>$u['flags'], 'mid'=>$u['mid'], 'tree'=>0, 'session'=>substr($msg, 40, 8)];
		if(!($u['flags'] & 0x2)){
			$t = unpack('V', substr($msg, 36, 4));
			$h['tree'] = $t[1];
		}
		return [$u['status'], $h, substr($msg, 64)];
	}

	private function statusText($status)
	{
		$map = [
			0xC000006D => '账号或密码不正确',
			0xC0000022 => '没有权限',
			0xC00000CC => '共享名不存在',
			0xC0000034 => '文件不存在',
			0xC000003A => '目录不存在',
			0xC0000043 => '文件正被占用',
			0xC000007F => '磁盘空间不足',
			0xC0000072 => '账号已被禁用',
			0xC0000234 => '账号已被锁定',
			0xC000006E => '账号受限制（可能不允许空密码登录）',
			0xC0000071 => '密码已过期',
		];
		$hex = sprintf('0x%08X', $status);
		return isset($map[$status]) ? $map[$status].'（'.$hex.'）' : '服务器返回 '.$hex;
	}

	/* ---------------- 签名 ---------------- */

	private function sign($msg)
	{
		if($this->dialect >= 0x0300){
			return $this->cmac($this->signKey, $msg);
		}
		return substr(hash_hmac('sha256', $msg, $this->signKey, true), 0, 16);
	}

	//AES-128-CMAC（RFC 4493）
	private function cmac($key, $msg)
	{
		$enc = function($block) use ($key){
			return openssl_encrypt($block, 'aes-128-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
		};
		$dbl = function($b){
			$out = '';
			$carry = 0;
			for($i = 15; $i >= 0; $i--){
				$v = (ord($b[$i]) << 1) | $carry;
				$carry = ($v >> 8) & 1;
				$out = chr($v & 0xff).$out;
			}
			if(ord($b[0]) & 0x80)$out[15] = chr(ord($out[15]) ^ 0x87);
			return $out;
		};
		$L = $enc(str_repeat("\0", 16));
		$K1 = $dbl($L);
		$K2 = $dbl($K1);
		$n = (int)ceil(strlen($msg) / 16);
		if($n === 0){
			$n = 1;
			$complete = false;
		}else{
			$complete = strlen($msg) % 16 === 0;
		}
		$last = substr($msg, ($n - 1) * 16);
		if($complete){
			$last = $last ^ $K1;
		}else{
			$last = str_pad($last."\x80", 16, "\0") ^ $K2;
		}
		$x = str_repeat("\0", 16);
		for($i = 0; $i < $n - 1; $i++){
			$x = $enc($x ^ substr($msg, $i * 16, 16));
		}
		return $enc($x ^ $last);
	}

	//SP800-108 计数器模式 KDF（HMAC-SHA256），SMB 3.0 派生签名密钥用
	private function kdf($key, $label, $context)
	{
		return substr(hash_hmac('sha256', pack('N', 1).$label."\0".$context.pack('N', 128), $key, true), 0, 16);
	}

	/* ---------------- 协商与登录 ---------------- */

	private function negotiate()
	{
		$dialects = [0x0202, 0x0210, 0x0300, 0x0302];
		$body = pack('v', 36).pack('v', count($dialects)).pack('v', 1).pack('v', 0)
			.pack('V', 0x4).random_bytes(16).pack('P', 0);
		foreach($dialects as $d)$body .= pack('v', $d);
		$res = $this->call(0, $body);
		if($res === false)return false;
		list($status, $h, $b) = $res;
		if($status !== 0)return $this->err('SMB 协商失败：'.$this->statusText($status));
		$u = unpack('vsize/vsecmode/vdialect', substr($b, 0, 6));
		$this->dialect = $u['dialect'];
		if(!in_array($this->dialect, $dialects, true))return $this->err(sprintf('服务器选了不支持的 SMB 版本 0x%04X', $this->dialect));
		$m = unpack('Vtrans/Vread/Vwrite', substr($b, 28, 12));
		//单次读写最多 1MB，2.0.2 只能 64KB
		$cap = $this->dialect === 0x0202 ? 65536 : 1048576;
		$this->maxRead = max(4096, min($cap, $m['read']));
		$this->maxWrite = max(4096, min($cap, $m['write']));
		return true;
	}

	private function sessionSetup($user, $pass, $domain)
	{
		$flags = 0x00000001 | 0x00000004 | 0x00000010 | 0x00000200 | 0x00008000 | 0x00080000 | 0x00800000 | 0x20000000 | 0x80000000;
		$negotiate = "NTLMSSP\0".pack('V', 1).pack('V', $flags).str_repeat("\0", 16);
		$res = $this->setupLeg($this->spnegoInit($negotiate));
		if($res === false)return false;
		list($status, $h, $b) = $res;
		if($status !== self::STATUS_MORE_PROCESSING)return $this->err('SMB 登录失败：'.$this->statusText($status));
		$this->sessionId = $h['session'];
		$blob = $this->secBlob($b);
		$pos = strpos($blob, "NTLMSSP\0");
		if($pos === false)return $this->err('服务器没有返回 NTLM 质询，可能不支持账号密码登录');
		$chal = substr($blob, $pos);
		$cflags = unpack('V', substr($chal, 20, 4))[1];
		$serverChallenge = substr($chal, 24, 8);
		$ti = unpack('vlen/vmax/Voff', substr($chal, 40, 8));
		$targetInfo = substr($chal, $ti['off'], $ti['len']);

		//NTLMv2：时间戳优先用服务器在 TargetInfo 里给的
		$ts = null;
		for($i = 0; $i + 4 <= strlen($targetInfo); ){
			$av = unpack('vid/vlen', substr($targetInfo, $i, 4));
			if($av['id'] === 0)break;
			if($av['id'] === 7)$ts = substr($targetInfo, $i + 4, 8);
			$i += 4 + $av['len'];
		}
		if($ts === null){
			$ft = (int)((microtime(true) + 11644473600) * 10000000);
			$ts = pack('P', $ft);
		}
		$ntHash = hash('md4', $this->u16($pass), true);
		$ntowf = hash_hmac('md5', $this->u16(strtoupper($user).$domain), $ntHash, true);
		$clientChallenge = random_bytes(8);
		$temp = "\x01\x01\0\0\0\0\0\0".$ts.$clientChallenge."\0\0\0\0".$targetInfo."\0\0\0\0";
		$proof = hash_hmac('md5', $serverChallenge.$temp, $ntowf, true);
		$ntResp = $proof.$temp;
		$sessionKey = hash_hmac('md5', $proof, $ntowf, true);
		$lmResp = str_repeat("\0", 24);

		$fields = [$lmResp, $ntResp, $this->u16($domain), $this->u16($user), $this->u16(''), ''];
		$offset = 64;
		$head = "NTLMSSP\0".pack('V', 3);
		$payload = '';
		foreach($fields as $f){
			$head .= pack('v', strlen($f)).pack('v', strlen($f)).pack('V', $offset + strlen($payload));
			$payload .= $f;
		}
		$head .= pack('V', $flags & $cflags);
		$auth = $head.$payload;

		$res = $this->setupLeg($this->spnegoResp($auth));
		if($res === false)return false;
		list($status, $h, $b) = $res;
		if($status !== 0)return $this->err('SMB 登录失败：'.$this->statusText($status));
		$sflags = unpack('v', substr($b, 2, 2))[1];
		if($sflags & 0x4)return $this->err('服务器要求这个会话加密传输，本站的 SMB 客户端不支持 SMB3 加密');
		//来宾 / 匿名会话没有会话密钥，不签名
		if(!($sflags & 0x3)){
			$this->signKey = $this->dialect >= 0x0300 ? $this->kdf($sessionKey, "SMB2AESCMAC\0", "SmbSign\0") : $sessionKey;
		}
		return true;
	}

	private function setupLeg($token)
	{
		$body = pack('v', 25).chr(0).chr(1).pack('V', 0).pack('V', 0).pack('v', 64 + 24).pack('v', strlen($token)).pack('P', 0).$token;
		return $this->call(1, $body);
	}

	private function secBlob($b)
	{
		$u = unpack('vsize/vflags/voff/vlen', substr($b, 0, 8));
		return substr($b, $u['off'] - 64, $u['len']);
	}

	/* ---------------- SPNEGO（最小 DER） ---------------- */

	private function der($tag, $content)
	{
		$len = strlen($content);
		if($len < 0x80)$l = chr($len);
		elseif($len < 0x100)$l = "\x81".chr($len);
		elseif($len < 0x10000)$l = "\x82".pack('n', $len);
		else $l = "\x84".pack('N', $len);
		return chr($tag).$l.$content;
	}

	private function spnegoInit($ntlm)
	{
		$mech = $this->der(0x06, "\x2b\x06\x01\x04\x01\x82\x37\x02\x02\x0a");
		$init = $this->der(0x30, $this->der(0xa0, $this->der(0x30, $mech)).$this->der(0xa2, $this->der(0x04, $ntlm)));
		return $this->der(0x60, $this->der(0x06, "\x2b\x06\x01\x05\x05\x02").$this->der(0xa0, $init));
	}

	private function spnegoResp($ntlm)
	{
		return $this->der(0xa1, $this->der(0x30, $this->der(0xa2, $this->der(0x04, $ntlm))));
	}

	/* ---------------- 共享与文件 ---------------- */

	private function treeConnect($unc)
	{
		$path = $this->u16($unc);
		$res = $this->call(3, pack('v', 9).pack('v', 0).pack('v', 64 + 8).pack('v', strlen($path)).$path);
		if($res === false)return false;
		list($status, $h, $b) = $res;
		if($status !== 0)return $this->err('连接共享 '.$unc.' 失败：'.$this->statusText($status));
		$this->treeId = $h['tree'];
		$sf = unpack('Cstype/Creserved/Vflags', substr($b, 2, 6));
		if($sf['flags'] & 0x8)return $this->err('共享 '.$unc.' 要求加密访问，本站的 SMB 客户端不支持 SMB3 加密，请在共享上关闭「加密数据访问」');
		return true;
	}

	/*
	 * 打开文件或目录，成功返回 ['id'=>16 字节句柄, 'size'=>, 'dir'=>]。
	 * 不存在时 notfound 置 true。
	 */
	private function create($path, $access, $disposition, $options)
	{
		$this->notfound = false;
		$name = $this->u16($path);
		$body = pack('v', 57).chr(0).chr(0).pack('V', 2).pack('P', 0).pack('P', 0)
			.pack('V', $access).pack('V', 0x80).pack('V', 7).pack('V', $disposition).pack('V', $options)
			.pack('v', 64 + 56).pack('v', strlen($name)).pack('V', 0).pack('V', 0)
			.($name === '' ? "\0" : $name);
		$res = $this->call(5, $body);
		if($res === false)return false;
		list($status, $h, $b) = $res;
		if($status !== 0){
			if(in_array($status, [self::STATUS_NAME_NOT_FOUND, self::STATUS_PATH_NOT_FOUND, self::STATUS_NO_SUCH_FILE], true))$this->notfound = true;
			return $this->err($this->statusText($status));
		}
		$size = unpack('P', substr($b, 48, 8))[1];
		$attr = unpack('V', substr($b, 56, 4))[1];
		return ['id'=>substr($b, 64, 16), 'size'=>$size, 'dir'=>($attr & 0x10) !== 0];
	}

	private function closeFile($fid)
	{
		$this->call(6, pack('v', 24).pack('v', 0).pack('V', 0).$fid);
	}

	public function size($path)
	{
		$f = $this->create($path, 0x00000080, 1, 0x40);
		if($f === false)return false;
		$this->closeFile($f['id']);
		return intval($f['size']);
	}

	public function mkdirs($path)
	{
		$cur = '';
		foreach(explode('\\', trim($path, '\\')) as $seg){
			if($seg === '')continue;
			$cur = $cur === '' ? $seg : $cur.'\\'.$seg;
			/*
			 * 先按「打开已有目录」试，不在再「新建」。不用 FILE_OPEN_IF 一步到位：
			 * 有的服务器（impacket 实测）对已存在的目录用 OPEN_IF 打开会回拒绝访问。
			 * 新建时撞上同名（别的请求刚好先建了）也算成功。
			 */
			$f = $this->create($cur, 0x00000080, 1, 0x1);
			if($f === false && $this->notfound){
				$f = $this->create($cur, 0x00000080, 2, 0x1);
				if($f === false && strpos($this->error, '0xC0000035') !== false)continue;
			}
			if($f === false)return false;
			$this->closeFile($f['id']);
		}
		return true;
	}

	public function delete($path)
	{
		//DELETE 权限 + 关闭时删除
		$f = $this->create($path, 0x00010080, 1, 0x40 | 0x1000);
		if($f === false)return false;
		$this->closeFile($f['id']);
		return true;
	}

	//一次能塞多大的读写：不超过协商上限，也不超过手里的额度（每 64KB 一份）
	private function ioSize($max)
	{
		if($this->dialect === 0x0202)return min($max, 65536);
		return max(65536, min($max, $this->credits * 65536));
	}

	public function upload($path, $tmpfile)
	{
		$fp = fopen($tmpfile, 'rb');
		if(!$fp)return $this->err('读取待上传的临时文件失败');
		//GENERIC_WRITE，FILE_OVERWRITE_IF：有同名的直接覆盖
		$f = $this->create($path, 0x40000000 | 0x00000080, 5, 0x40);
		if($f === false){
			fclose($fp);
			return false;
		}
		$offset = 0;
		$ok = true;
		while(!feof($fp)){
			$size = $this->ioSize($this->maxWrite);
			$data = fread($fp, $size);
			if($data === false || $data === '')break;
			$len = strlen($data);
			$body = pack('v', 49).pack('v', 64 + 48).pack('V', $len).pack('P', $offset).$f['id']
				.pack('V', 0).pack('V', 0).pack('v', 0).pack('v', 0).pack('V', 0).$data;
			$res = $this->call(9, $body, (int)ceil($len / 65536));
			if($res === false || $res[0] !== 0){
				if($res !== false)$this->err('写入失败：'.$this->statusText($res[0]));
				$ok = false;
				break;
			}
			$offset += $len;
		}
		fclose($fp);
		$this->closeFile($f['id']);
		return $ok;
	}

	/*
	 * 读 [start, end]（含两端），end 为 -1 表示读到结尾，读到的内容交给 $sink。
	 */
	public function download($path, $start, $end, $sink)
	{
		$f = $this->create($path, 0x00000001 | 0x00000080, 1, 0x40);
		if($f === false)return false;
		$size = intval($f['size']);
		if($end < 0 || $end >= $size)$end = $size - 1;
		$offset = $start;
		$ok = true;
		while($offset <= $end){
			$want = min($this->ioSize($this->maxRead), $end - $offset + 1);
			$body = pack('v', 49).chr(0x50).chr(0).pack('V', $want).pack('P', $offset).$f['id']
				.pack('V', 0).pack('V', 0).pack('V', 0).pack('v', 0).pack('v', 0)."\0";
			$res = $this->call(8, $body, (int)ceil($want / 65536));
			if($res === false){
				$ok = false;
				break;
			}
			list($status, $h, $b) = $res;
			if($status === self::STATUS_END_OF_FILE)break;
			if($status !== 0){
				$this->err('读取失败：'.$this->statusText($status));
				$ok = false;
				break;
			}
			$u = unpack('vsize/Coff/Cres/Vlen', substr($b, 0, 8));
			$data = substr($b, $u['off'] - 64, $u['len']);
			if($data === '' || $data === false)break;
			call_user_func($sink, $data);
			$offset += strlen($data);
		}
		$this->closeFile($f['id']);
		return $ok;
	}
}
