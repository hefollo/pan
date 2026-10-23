<?php
namespace lib\Storage\Ext;

/**
 * Misskey 云盘（移植自 OpenList drivers/misskey）
 *
 * Misskey 实例的 /api/drive/* 接口，Bearer 访问令牌。云盘按文件夹 ID 组织，
 * 存储目录按名字一级级找（找不到就建），找到的文件夹 ID 存进运行状态。
 * 和 OpenList 的一处不同：上传时 force 传 true。OpenList 传 false，内容相同时 Misskey 会直接还回已有文件、不新建，
 * 本站两个文件就可能指向同一个网盘文件，删其中一个另一个也跟着没了。
 */
class Misskey extends Base
{
	private $endpoint;
	private $folderId = false;

	public static function meta()
	{
		return [
			'name' => 'Misskey 云盘',
			'icon' => 'fa-cloud',
			'group' => '协议与自建服务',
			'source' => 'misskey',
			'desc' => 'Misskey 实例自带的云盘，下载用它给的文件地址直链。',
			'direct' => true,
			'range' => true,
			'fields' => [
				['name'=>'endpoint', 'label'=>'实例地址', 'required'=>true, 'default'=>'https://misskey.io'],
				['name'=>'access_token', 'label'=>'访问令牌', 'type'=>'secret', 'required'=>true, 'tip'=>'「设置 → API」里生成，权限至少勾上云盘的读和写。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'云盘里的文件夹，按名字逐级查找，不存在会自动创建；留 / 表示云盘根目录。'],
			],
		];
	}

	protected function init()
	{
		$this->endpoint = rtrim(trim($this->c('endpoint', 'https://misskey.io')), '/');
		if($this->endpoint !== '' && !preg_match('#^https?://#i', $this->endpoint))$this->endpoint = 'https://'.$this->endpoint;
	}

	private function api($path, $body = [], $opts = [])
	{
		if($this->c('access_token') === '')return $this->fail('请填写访问令牌');
		$opts['headers'] = ['Authorization'=>'Bearer '.$this->c('access_token')];
		if(!isset($opts['multipart']))$opts['json'] = $body ? $body : new \stdClass();
		$res = $this->request('POST', $this->endpoint.'/api/drive'.$path, $opts);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] >= 300){
			$msg = is_array($j) && isset($j['error']['message']) ? $j['error']['message'] : $this->brief($res['body']);
			if(is_array($j) && isset($j['error']['code']) && stripos($j['error']['code'], 'NO_SUCH') !== false)$this->notfound = true;
			return $this->fail('Misskey 接口报错（HTTP '.$res['status'].'）：'.$msg);
		}
		return $j === null ? [] : $j;
	}

	//按名字逐级找存储目录对应的文件夹 ID，没有就建；null 表示云盘根目录
	private function folder()
	{
		if($this->folderId !== false)return $this->folderId;
		$root = $this->cleanPath($this->c('root_folder_path', '/pan/file'));
		$cached = $this->state('folder');
		if(is_array($cached) && $cached['root'] === $root){
			return $this->folderId = $cached['id'];
		}
		$parent = null;
		foreach(explode('/', trim($root, '/')) as $seg){
			if($seg === '')continue;
			$list = $this->api('/folders/find', ['name'=>$seg, 'parentId'=>$parent]);
			if($list === false)return false;
			if(!empty($list[0]['id'])){
				$parent = $list[0]['id'];
				continue;
			}
			$f = $this->api('/folders/create', ['name'=>$seg, 'parentId'=>$parent]);
			if($f === false)return false;
			$parent = $f['id'];
		}
		$this->saveState(['folder'=>['root'=>$root, 'id'=>$parent]]);
		return $this->folderId = $parent;
	}

	private function fileId($name)
	{
		$id = $this->mapGet($name);
		if($id !== null)return $id;
		$folder = $this->folder();
		if($folder === false)return false;
		$list = $this->api('/files/find', ['name'=>$name, 'folderId'=>$folder]);
		if($list === false)return false;
		if(empty($list[0]['id'])){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		$this->mapSet($name, $list[0]['id']);
		return $list[0]['id'];
	}

	private function show($name)
	{
		$id = $this->fileId($name);
		if($id === false)return false;
		$f = $this->api('/files/show', ['fileId'=>$id]);
		if($f === false && $this->notfound)$this->mapDel($name);
		return $f;
	}

	protected function check()
	{
		return $this->folder() !== false;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$f = $this->show($name);
		if($f === false)return false;
		return ['size'=>intval($f['size']), 'id'=>$f['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$folder = $this->folder();
		if($folder === false)return false;
		$form = ['file'=>new \CURLFile($tmpfile, $content_type ? $content_type : 'application/octet-stream', $name), 'name'=>$name, 'comment'=>'', 'isSensitive'=>'false', 'force'=>'true'];
		if($folder !== null)$form['folderId'] = $folder;
		$f = $this->api('/files/create', [], ['multipart'=>$form, 'timeout'=>0]);
		if($f === false)return false;
		if(empty($f['id']))return $this->fail('Misskey 上传成功但没有返回文件 ID');
		$this->mapSet($name, $f['id']);
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$id = $this->fileId($name);
		if($id === false)return $this->notfound;
		if($this->api('/files/delete', ['fileId'=>$id]) !== false)return true;
		return $this->notfound;
	}

	protected function link($name)
	{
		$f = $this->show($name);
		if($f === false)return false;
		if(empty($f['url']))return $this->fail('Misskey 没有返回文件地址');
		return ['url'=>$f['url']];
	}
}
