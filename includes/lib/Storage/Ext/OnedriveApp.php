<?php
namespace lib\Storage\Ext;

/**
 * OneDrive（应用授权，移植自 OpenList drivers/onedrive_app）
 *
 * 跟本站原有的 OneDrive 存储（用户登录授权）不同，这里用 Azure 应用自己的身份（client_credentials），
 * 不用走浏览器授权，适合 Microsoft 365 商业版 / 教育版：管理员给应用授予 Files.ReadWrite.All 应用权限后，
 * 按填的邮箱访问那个用户的 OneDrive。令牌一小时左右过期，过期前 5 分钟重新取，存进运行状态。
 * 4MB 以内直接 PUT 内容，更大的用上传会话按块传（块大小按 OpenList 默认 5MB，必须是 320KB 的整数倍）。
 */
class OnedriveApp extends Base
{
	private static $hosts = [
		'global' => ['https://login.microsoftonline.com', 'https://graph.microsoft.com'],
		'cn' => ['https://login.chinacloudapi.cn', 'https://microsoftgraph.chinacloudapi.cn'],
		'us' => ['https://login.microsoftonline.us', 'https://graph.microsoft.us'],
		'de' => ['https://login.microsoftonline.de', 'https://graph.microsoft.de'],
	];

	private $oauth;
	private $graph;
	private $root;

	public static function meta()
	{
		return [
			'name' => 'OneDrive（应用授权）',
			'icon' => 'fa-windows',
			'group' => '对象存储与国外网盘',
			'source' => 'onedrive_app',
			'desc' => '用 Azure 应用身份访问 Microsoft 365 商业版 / 教育版用户的 OneDrive，不需要浏览器授权，下载可用微软直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
			'link_text' => 'Azure 应用注册',
			'fields' => [
				['name'=>'region', 'label'=>'区域', 'type'=>'select', 'default'=>'global', 'options'=>['global'=>'国际版', 'cn'=>'世纪互联（中国）', 'us'=>'美国政府版', 'de'=>'德国版']],
				['name'=>'client_id', 'label'=>'应用 ID', 'required'=>true],
				['name'=>'client_secret', 'label'=>'应用机密', 'type'=>'secret', 'required'=>true, 'tip'=>'「证书和密码」里客户端密码的<b>值</b>。'],
				['name'=>'tenant_id', 'label'=>'租户 ID', 'required'=>true, 'tip'=>'应用「概述」里的 Directory (tenant) ID。应用要在「API 权限」里加 Microsoft Graph 的<b>应用程序权限</b> Files.ReadWrite.All 并由管理员同意。'],
				['name'=>'email', 'label'=>'用户邮箱', 'required'=>true, 'tip'=>'要存文件的那个 OneDrive 用户的登录邮箱。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'网盘里的路径，目录不存在会在上传时自动创建。'],
				['name'=>'chunk_size', 'label'=>'分块大小（MB）', 'default'=>'5'],
				['name'=>'custom_host', 'label'=>'下载域名替换', 'tip'=>'可不填。填了就把微软直链里的域名换成它（比如自己的反代）。'],
			],
		];
	}

	protected function init()
	{
		$r = $this->c('region', 'global');
		if(!isset(self::$hosts[$r]))$r = 'global';
		list($this->oauth, $this->graph) = self::$hosts[$r];
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/')), '/');
	}

	private function token($force = false)
	{
		if(!$force && $this->state('token') && intval($this->state('expire', 0)) > time() + 300)return $this->state('token');
		$res = $this->json('POST', $this->oauth.'/'.rawurlencode($this->c('tenant_id')).'/oauth2/token', ['form'=>[
			'grant_type' => 'client_credentials',
			'client_id' => $this->c('client_id'),
			'client_secret' => $this->c('client_secret'),
			'resource' => $this->graph.'/',
			'scope' => $this->graph.'/.default',
		]]);
		if($res === false)return false;
		if(empty($res['access_token']))return $this->fail('取应用令牌失败：'.(isset($res['error_description']) ? $res['error_description'] : json_encode($res, JSON_UNESCAPED_UNICODE)));
		$this->saveState(['token'=>$res['access_token'], 'expire'=>time() + (isset($res['expires_in']) ? intval($res['expires_in']) : 3600)]);
		return $res['access_token'];
	}

	private function itemUrl($name = null)
	{
		$path = $name === null ? $this->root : $this->root.'/'.$name;
		$base = $this->graph.'/v1.0/users/'.rawurlencode($this->c('email')).'/drive/root';
		if($path === '' || $path === '/')return $base;
		return $base.':'.implode('/', array_map('rawurlencode', explode('/', $path))).':';
	}

	private function api($method, $url, $opts = [], $retry = true)
	{
		$tok = $this->token();
		if($tok === false)return false;
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['Authorization'=>'Bearer '.$tok];
		$res = $this->request($method, $url, $opts);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] >= 400){
			$code = isset($j['error']['code']) ? $j['error']['code'] : '';
			if($code === 'InvalidAuthenticationToken' && $retry){
				if($this->token(true) === false)return false;
				return $this->api($method, $url, $opts, false);
			}
			if($res['status'] == 404 || $code === 'itemNotFound')$this->notfound = true;
			return $this->fail('OneDrive 接口报错（'.($code !== '' ? $code : 'HTTP '.$res['status']).'）：'.(isset($j['error']['message']) ? $j['error']['message'] : $this->brief($res['body'])));
		}
		return is_array($j) ? $j : [];
	}

	protected function check()
	{
		return $this->api('GET', $this->graph.'/v1.0/users/'.rawurlencode($this->c('email')).'/drive') !== false;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$f = $this->api('GET', $this->itemUrl($name));
		if($f === false)return false;
		if(!isset($f['file'])){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['size'=>intval($f['size']), 'id'=>$f['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		if($size <= 4194304){
			return $this->api('PUT', $this->itemUrl($name).'/content', ['file'=>$tmpfile, 'length'=>$size, 'timeout'=>0, 'headers'=>['Content-Type'=>'application/octet-stream']]) !== false;
		}
		$s = $this->api('POST', $this->itemUrl($name).'/createUploadSession', ['json'=>['item'=>['@microsoft.graph.conflictBehavior'=>'replace']]]);
		if($s === false)return false;
		if(empty($s['uploadUrl']))return $this->fail('OneDrive 没有返回上传地址');
		//块大小必须是 320KB 的整数倍
		$cs = max(1, intval($this->c('chunk_size', '5'))) * 1048576;
		$cs = max(327680, $cs - $cs % 327680);
		for($off = 0; $off < $size; $off += $cs){
			$len = min($cs, $size - $off);
			$ok = false;
			for($try = 0; $try < 3 && !$ok; $try++){
				//上传地址自带签名，不能再带令牌
				$res = $this->request('PUT', $s['uploadUrl'], ['headers'=>['Content-Range'=>'bytes '.$off.'-'.($off + $len - 1).'/'.$size], 'file'=>$tmpfile, 'offset'=>$off, 'length'=>$len, 'timeout'=>0]);
				if($res === false)continue;
				if(in_array($res['status'], [200, 201, 202], true))$ok = true;
				elseif($res['status'] < 500)return $this->fail('OneDrive 分块上传失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
			}
			if(!$ok)return $this->fail('OneDrive 分块上传失败，重试 3 次仍不成功：'.$this->errmsg);
		}
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		if($this->api('DELETE', $this->itemUrl($name)) !== false)return true;
		return $this->notfound;
	}

	protected function link($name)
	{
		$f = $this->api('GET', $this->itemUrl($name));
		if($f === false)return false;
		if(empty($f['@microsoft.graph.downloadUrl']))return $this->fail('OneDrive 没有返回下载地址');
		$url = $f['@microsoft.graph.downloadUrl'];
		if($this->c('custom_host') !== ''){
			$url = preg_replace('#^(https?://)[^/]+#', '$1'.$this->c('custom_host'), $url, 1);
		}
		return ['url'=>$url];
	}
}
