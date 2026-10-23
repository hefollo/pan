<?php
namespace lib\Storage\Ext;

/**
 * Yandex Disk（移植自 OpenList drivers/yandex_disk）
 *
 * REST API（cloud-api.yandex.net/v1/disk/resources），请求头 Authorization: OAuth <令牌>，按路径操作。
 * 令牌来源同 OpenList：默认走在线续期接口（会把 refresh_token 发给它），也可以填自己应用的 client_id / secret。
 * 上传：先要上传地址再 PUT；目录不在时 Yandex 回 409，逐级建好再要一次地址。下载要临时地址，浏览器能直接打开。
 * 和 OpenList 的一处不同：删除带 permanently=true 直接删掉（OpenList 是进回收站，空间不会释放）。
 */
class YandexDisk extends Base
{
	//接口根地址，只有测试脚本会用反射改成本地模拟接口
	private $api = 'https://cloud-api.yandex.net/v1/disk/resources';
	private $oauthUrl = 'https://oauth.yandex.com/token';
	private $root;

	public static function meta()
	{
		return [
			'name' => 'Yandex Disk',
			'icon' => 'fa-cloud',
			'group' => '对象存储与国外网盘',
			'source' => 'yandex_disk',
			'desc' => 'Yandex 网盘，下载可用它给的临时直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://api.oplist.org/',
			'link_text' => '获取令牌',
			'fields' => [
				['name'=>'use_online_api', 'label'=>'令牌来源', 'type'=>'select', 'default'=>'1', 'options'=>['1'=>'OpenList 在线续期接口', '0'=>'自己的应用（client_id / secret）']],
				['name'=>'refresh_token', 'label'=>'Refresh Token', 'type'=>'secret', 'required'=>true],
				['name'=>'api_url_address', 'label'=>'在线续期接口', 'default'=>'https://api.oplist.org/yandexui/renewapi', 'tip'=>'注意它会收到你的 refresh_token。'],
				['name'=>'client_id', 'label'=>'Client ID'],
				['name'=>'client_secret', 'label'=>'Client Secret', 'type'=>'secret'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'目录不存在会自动创建。'],
			],
		];
	}

	protected function init()
	{
		$this->root = rtrim($this->cleanPath($this->c('root_folder_path', '/')), '/');
	}

	private function path($name)
	{
		return $this->root.'/'.$name;
	}

	private function token($force = false)
	{
		if(!$force && $this->state('access_token'))return $this->state('access_token');
		$rt = $this->state('refresh_token') ? $this->state('refresh_token') : $this->c('refresh_token');
		if($rt === '')return $this->fail('请填写 Refresh Token');
		if($this->c('use_online_api', '1') === '1'){
			$r = $this->json('GET', $this->c('api_url_address', 'https://api.oplist.org/yandexui/renewapi'), ['query'=>['refresh_ui'=>$rt, 'server_use'=>'true', 'driver_txt'=>'yandexui_go']]);
			if($r === false)return false;
			if(empty($r['refresh_token']) || empty($r['access_token']))return $this->fail('在线续期失败：'.(!empty($r['text']) ? $r['text'] : '没有返回令牌'));
		}else{
			if($this->c('client_id') === '' || $this->c('client_secret') === '')return $this->fail('请填写 Client ID 和 Client Secret');
			$r = $this->json('POST', $this->oauthUrl, ['form'=>['grant_type'=>'refresh_token', 'refresh_token'=>$rt, 'client_id'=>$this->c('client_id'), 'client_secret'=>$this->c('client_secret')]]);
			if($r === false)return false;
			if(empty($r['access_token']))return $this->fail('换令牌失败：'.(isset($r['error_description']) ? $r['error_description'] : (isset($r['error']) ? $r['error'] : '')));
		}
		$this->saveState(['access_token'=>$r['access_token'], 'refresh_token'=>!empty($r['refresh_token']) ? $r['refresh_token'] : $rt]);
		return $r['access_token'];
	}

	//返回 [状态码, 解开的响应]；UnauthorizedError 时换一次令牌再试
	private function call($method, $sub, $query, $retry = true)
	{
		$tok = $this->token();
		if($tok === false)return false;
		$res = $this->request($method, $this->api.$sub, ['query'=>$query, 'headers'=>['Authorization'=>'OAuth '.$tok, 'Accept'=>'application/json'], 'body'=>$method === 'PUT' || $method === 'POST' ? '' : null]);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] == 401 && $retry){
			if($this->token(true) === false)return false;
			return $this->call($method, $sub, $query, false);
		}
		return [$res['status'], is_array($j) ? $j : []];
	}

	private function yErr($r, $what)
	{
		if($r[0] == 404)$this->notfound = true;
		return $this->fail('Yandex '.$what.'失败（HTTP '.$r[0].'）：'.(isset($r[1]['description']) ? $r[1]['description'] : (isset($r[1]['error']) ? $r[1]['error'] : '')));
	}

	private function mkdirs()
	{
		$cur = '';
		foreach(explode('/', trim($this->root, '/')) as $seg){
			if($seg === '')continue;
			$cur .= '/'.$seg;
			$r = $this->call('PUT', '', ['path'=>$cur]);
			if($r === false)return false;
			//201 新建，409 已存在
			if($r[0] != 201 && $r[0] != 409)return $this->yErr($r, '创建目录 '.$cur);
		}
		return true;
	}

	protected function check()
	{
		return $this->mkdirs();
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$r = $this->call('GET', '', ['path'=>$this->path($name), 'fields'=>'size,type,name']);
		if($r === false)return false;
		if($r[0] != 200)return $this->yErr($r, '查询文件');
		if(!isset($r[1]['type']) || $r[1]['type'] !== 'file'){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['size'=>intval($r[1]['size'])];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$q = ['path'=>$this->path($name), 'overwrite'=>'true'];
		$r = $this->call('GET', '/upload', $q);
		if($r === false)return false;
		if($r[0] == 409){
			if(!$this->mkdirs())return false;
			$r = $this->call('GET', '/upload', $q);
			if($r === false)return false;
		}
		if($r[0] != 200 || empty($r[1]['href']))return $this->yErr($r, '获取上传地址');
		$res = $this->request(!empty($r[1]['method']) ? $r[1]['method'] : 'PUT', $r[1]['href'], ['file'=>$tmpfile, 'length'=>$size, 'timeout'=>0, 'headers'=>['Content-Type'=>'application/octet-stream']]);
		if($res === false)return false;
		if($res['status'] != 201 && $res['status'] != 202)return $this->fail('Yandex 上传失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$r = $this->call('DELETE', '', ['path'=>$this->path($name), 'permanently'=>'true']);
		if($r === false)return false;
		if($r[0] == 204 || $r[0] == 202 || $r[0] == 404)return true;
		return $this->yErr($r, '删除');
	}

	protected function link($name)
	{
		$r = $this->call('GET', '/download', ['path'=>$this->path($name)]);
		if($r === false)return false;
		if($r[0] != 200 || empty($r[1]['href']))return $this->yErr($r, '获取下载地址');
		return ['url'=>$r[1]['href']];
	}
}
