<?php
namespace lib\Storage\Ext;

/**
 * SFTP（移植自 OpenList drivers/sftp）
 *
 * OpenList 用 Go 的 pkg/sftp；这里沿用 FTP 驱动的做法，交给 cURL 的 sftp://。
 * 绝大多数 Linux 发行版自带的 libcurl 都编进了 libssh / libssh2，不用另装 PHP 扩展；
 * 没编进去的话连接测试会直接说明。
 * 和 OpenList 一样不校验服务器的主机密钥（它用的是 InsecureIgnoreHostKey）。
 */
class Sftp extends Ftp
{
	private $keyfile = null;

	public static function meta()
	{
		return [
			'name' => 'SFTP',
			'icon' => 'fa-terminal',
			'group' => '协议与自建服务',
			'source' => 'sftp',
			'desc' => '通过 SSH 登录服务器存文件，有 SSH 账号的 VPS、NAS 都能用，上传下载走本站中转。',
			'direct' => false,
			'range' => true,
			'fields' => [
				['name'=>'address', 'label'=>'服务器地址', 'required'=>true, 'placeholder'=>'192.168.1.5:22', 'tip'=>'主机名或 IP，端口不是 22 的写成 <b>主机:端口</b>。'],
				['name'=>'username', 'label'=>'账号', 'required'=>true],
				['name'=>'password', 'label'=>'密码', 'type'=>'secret', 'tip'=>'用密码登录就填这里；用私钥登录可以不填。'],
				['name'=>'private_key', 'label'=>'私钥', 'type'=>'textarea', 'placeholder'=>"-----BEGIN OPENSSH PRIVATE KEY-----\n...", 'tip'=>'把私钥文件的全部内容粘贴进来（含首尾两行）。填了私钥就优先用私钥登录。<br/>服务器上 libcurl 用的 libssh2 低于 1.11 时，RSA 私钥只能按 SHA1 签名，OpenSSH 8.8 以后默认不收，会报 Authentication failure；换成 <b>ed25519</b> 或 <b>ecdsa</b> 私钥，或者改用密码登录。'],
				['name'=>'passphrase', 'label'=>'私钥密码', 'type'=>'secret', 'tip'=>'私钥本身设了密码才需要填。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'服务器上的绝对路径，目录不存在会在第一次上传时自动创建。'],
			],
		];
	}

	public function __destruct()
	{
		if($this->keyfile !== null)@unlink($this->keyfile);
	}

	protected function init()
	{
		$addr = trim($this->c('address'));
		$addr = preg_replace('#^sftp://#i', '', $addr);
		$this->host = rtrim($addr, '/');
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/pan/file')), '/');
	}

	protected function label()
	{
		return 'SFTP';
	}

	//SFTP 的路径就是 UTF-8，不做编码转换
	protected function enc($str)
	{
		return $str;
	}

	//sftp:// 后面的路径本身就是绝对路径
	protected function url($path)
	{
		$segs = array_map('rawurlencode', explode('/', ltrim($path, '/')));
		return 'sftp://'.$this->host.'/'.implode('/', $segs);
	}

	protected function connect($ch)
	{
		$v = curl_version();
		if(empty($v['protocols']) || !in_array('sftp', $v['protocols'], true)){
			$this->errmsg = '服务器的 cURL 没有编进 SFTP 支持（libssh / libssh2），这个存储用不了';
			return false;
		}
		$user = $this->c('username');
		if($user === '')return $this->fail('请先填写 SFTP 账号');
		$key = trim($this->c('private_key'));
		if($key !== ''){
			if($this->keyfile === null){
				$this->keyfile = tempnam(sys_get_temp_dir(), 'pankey');
				if($this->keyfile === false || @file_put_contents($this->keyfile, $key."\n") === false){
					$this->keyfile = null;
					return $this->fail('本地临时目录不可写，私钥没法交给 cURL');
				}
				@chmod($this->keyfile, 0600);
			}
			curl_setopt($ch, CURLOPT_USERNAME, $user);
			curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PUBLICKEY | CURLSSH_AUTH_PASSWORD);
			curl_setopt($ch, CURLOPT_SSH_PRIVATE_KEYFILE, $this->keyfile);
			//公钥留空：libssh2 自己从私钥推出来
			curl_setopt($ch, CURLOPT_SSH_PUBLIC_KEYFILE, '');
			if($this->c('passphrase') !== '')curl_setopt($ch, CURLOPT_KEYPASSWD, $this->c('passphrase'));
			if($this->c('password') !== '')curl_setopt($ch, CURLOPT_PASSWORD, $this->c('password'));
		}else{
			if($this->c('password') === '')return $this->fail('请填写 SFTP 密码或私钥');
			curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD | CURLSSH_AUTH_KEYBOARD);
			curl_setopt($ch, CURLOPT_USERPWD, $user.':'.$this->c('password'));
		}
		return true;
	}

	protected function deleteRequest($name)
	{
		//SFTP 的 QUOTE 命令自己认双引号，路径里有空格也不怕
		return ['sftp://'.$this->host.'/', 'rm "'.str_replace('"', '\\"', $this->root.'/'.$name).'"'];
	}
}
