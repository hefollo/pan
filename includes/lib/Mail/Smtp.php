<?php
namespace lib\Mail;

/*
 * SMTP 发信。
 *
 * 直接用 socket 走一遍 SMTP 会话，不引入第三方库（和 Alipay.php / Epay.php 一样的风格）。
 * 支持三种加密方式：
 *   ssl   一上来就是 TLS 加密连接，端口通常是 465
 *   tls   先明文连接再 STARTTLS 升级，端口通常是 587
 *   none  全程明文，端口 25（国内云基本都封了这个端口）
 *
 * 超时卡得比较死：连接 8 秒、每条命令 10 秒。SMTP 服务器不通的时候会一直挂着，
 * 之前快捷登录接口就是因为没有超时把 PHP 进程占满过。
 */
class Smtp implements ISender
{
	const CONNECT_TIMEOUT = 8;
	const CMD_TIMEOUT = 10;

	private $host;
	private $port;
	private $secure;
	private $user;
	private $pass;
	private $from;
	private $from_name;
	private $sock = null;
	//整个会话的往来记录，失败时回给后台看，比一句“发送失败”有用得多
	private $log = [];

	public function __construct($conf)
	{
		$this->host = isset($conf['host']) ? trim($conf['host']) : '';
		$this->port = isset($conf['port']) ? intval($conf['port']) : 465;
		$this->secure = isset($conf['secure']) ? strtolower(trim($conf['secure'])) : 'ssl';
		$this->user = isset($conf['user']) ? trim($conf['user']) : '';
		$this->pass = isset($conf['pass']) ? trim($conf['pass']) : '';
		$this->from = isset($conf['from']) ? trim($conf['from']) : '';
		$this->from_name = isset($conf['from_name']) ? trim($conf['from_name']) : '';
		if ($this->port <= 0) $this->port = $this->secure === 'ssl' ? 465 : 587;
	}

	public function name()
	{
		return 'SMTP';
	}

	public function isReady()
	{
		return $this->host !== '' && $this->user !== '' && $this->pass !== '' && $this->from !== '';
	}

	public function send($to, $subject, $html, $text)
	{
		$this->log = [];
		if (!$this->isReady()) {
			return ['ok' => false, 'msg' => 'SMTP 参数没有配置完整'];
		}
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return ['ok' => false, 'msg' => '收件地址格式不正确'];
		}

		$prefix = $this->secure === 'ssl' ? 'ssl://' : '';
		$errno = 0;
		$errstr = '';
		//证书校验交给 PHP 默认行为，不关掉：关掉的话中间人可以拿到你的邮箱密码
		$this->sock = @fsockopen($prefix . $this->host, $this->port, $errno, $errstr, self::CONNECT_TIMEOUT);
		if (!$this->sock) {
			return ['ok' => false, 'msg' => '连接 ' . $this->host . ':' . $this->port . ' 失败：' . ($errstr ? $errstr : '端口不通或被服务器防火墙拦截') . '（国内云服务器通常封禁 25 端口，请改用 465/SSL）'];
		}
		stream_set_timeout($this->sock, self::CMD_TIMEOUT);

		try {
			$this->expect('220');
			$ehlo = $this->cmd('EHLO ' . $this->heloName(), '250');

			if ($this->secure === 'tls') {
				$this->cmd('STARTTLS', '220');
				$ok = @stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				if (!$ok) throw new \Exception('STARTTLS 升级加密失败，请确认端口和加密方式是否匹配');
				//加密之后要重新握手
				$ehlo = $this->cmd('EHLO ' . $this->heloName(), '250');
			}

			//登录：AUTH LOGIN 是各家邮箱都支持的方式，账号密码分两步用 base64 发过去
			$this->cmd('AUTH LOGIN', '334');
			$this->cmd(base64_encode($this->user), '334', false, true);
			$this->cmd(base64_encode($this->pass), '235', false, true);

			$this->cmd('MAIL FROM:<' . $this->from . '>', '250');
			$this->cmd('RCPT TO:<' . $to . '>', '250');
			$this->cmd('DATA', '354');
			$this->write($this->buildMessage($to, $subject, $html, $text) . "\r\n.");
			$this->expect('250');
			$this->cmd('QUIT', '221', true);
			$this->close();
			return ['ok' => true, 'msg' => '发送成功'];
		} catch (\Exception $e) {
			$this->close();
			return ['ok' => false, 'msg' => $e->getMessage()];
		}
	}

	//会话记录，出错时附在提示后面
	public function sessionLog()
	{
		return $this->log;
	}

	/*
	 * 有些邮箱服务器会校验 EHLO 后面的域名，用发件地址的域名最稳妥
	 */
	private function heloName()
	{
		$at = strrpos($this->from, '@');
		$domain = $at === false ? '' : substr($this->from, $at + 1);
		return $domain !== '' ? $domain : 'localhost';
	}

	private function cmd($cmd, $expect, $ignore_fail = false, $secret = false)
	{
		$this->write($cmd, $secret);
		return $this->expect($expect, $ignore_fail);
	}

	/*
	 * $secret=true 的内容（账号、授权码）只在日志里留个占位，否则后台点一次测试发信
	 * 就把邮箱授权码显示出来了；邮件正文太长也只留摘要
	 */
	private function write($data, $secret = false)
	{
		if($secret){
			$this->log[] = '> (账号或授权码，已隐藏)';
		}elseif(strlen($data) > 200){
			$this->log[] = '> (邮件正文 ' . strlen($data) . ' 字节，已省略)';
		}else{
			$this->log[] = '> ' . $data;
		}
		@fwrite($this->sock, $data . "\r\n");
	}

	private function expect($code, $ignore_fail = false)
	{
		$line = '';
		//多行响应形如 250-XXX，最后一行才是 250 XXX
		while ($str = @fgets($this->sock, 1024)) {
			$line .= $str;
			if (!isset($str[3]) || $str[3] === ' ') break;
			$meta = stream_get_meta_data($this->sock);
			if (!empty($meta['timed_out'])) break;
		}
		$line = trim($line);
		$this->log[] = '< ' . $line;
		if ($line === '') {
			if ($ignore_fail) return '';
			throw new \Exception('服务器没有响应（可能是加密方式或端口不对，也可能被防火墙掐断）');
		}
		if (strncmp($line, $code, strlen($code)) !== 0) {
			if ($ignore_fail) return $line;
			throw new \Exception('服务器返回：' . $line);
		}
		return $line;
	}

	private function close()
	{
		if ($this->sock) {
			@fclose($this->sock);
			$this->sock = null;
		}
	}

	/*
	 * 组装邮件内容：同时给纯文本和 HTML 两个版本（multipart/alternative），
	 * 只发 HTML 的话有些邮箱会判定成垃圾邮件
	 */
	private function buildMessage($to, $subject, $html, $text)
	{
		$boundary = '=_' . md5(uniqid('', true));
		$from = $this->from_name !== ''
			? $this->encodeHeader($this->from_name) . ' <' . $this->from . '>'
			: $this->from;

		$headers = [
			'Date: ' . date('r'),
			'From: ' . $from,
			'To: <' . $to . '>',
			'Subject: ' . $this->encodeHeader($subject),
			'Message-ID: <' . md5(uniqid('', true)) . '@' . $this->heloName() . '>',
			'MIME-Version: 1.0',
			'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
		];

		$body = '--' . $boundary . "\r\n"
			. "Content-Type: text/plain; charset=UTF-8\r\n"
			. "Content-Transfer-Encoding: base64\r\n\r\n"
			. chunk_split(base64_encode($text)) . "\r\n"
			. '--' . $boundary . "\r\n"
			. "Content-Type: text/html; charset=UTF-8\r\n"
			. "Content-Transfer-Encoding: base64\r\n\r\n"
			. chunk_split(base64_encode($html)) . "\r\n"
			. '--' . $boundary . "--";

		//正文里以点开头的行要变成两个点，否则会被当成信件结束标记
		$message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
		return preg_replace('/^\./m', '..', $message);
	}

	//邮件头里的中文要按 RFC 2047 编码，直接塞会乱码
	private function encodeHeader($str)
	{
		if (preg_match('/^[\x20-\x7e]*$/', $str)) return $str;
		return '=?UTF-8?B?' . base64_encode($str) . '?=';
	}
}
