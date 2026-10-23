<?php
namespace lib\Storage\Ext;

/**
 * CNB Releases（移植自 OpenList drivers/cnb_releases）
 *
 * 把文件作为 cnb.cool 仓库某个发行版（release）的附件存放。OpenList 里每个发行版是一个文件夹；
 * 本站只用一个：按填的名字（同时比对发行版名和标签名）找，找不到就以它为标签新建，ID 存进运行状态。
 * 上传：先要上传地址，multipart 传上去（返回 204），再 POST 一次校验地址。下载给 cnb.cool 上的附件地址。
 */
class CnbReleases extends Base
{
	//接口和网页的根地址，只有测试脚本会用反射改成本地模拟接口
	private $apiBase = 'https://api.cnb.cool';
	private $webBase = 'https://cnb.cool';
	private $releaseId = null;

	public static function meta()
	{
		return [
			'name' => 'CNB Releases',
			'icon' => 'fa-tags',
			'group' => '对象存储与国外网盘',
			'source' => 'cnb_releases',
			'desc' => '把文件作为 cnb.cool（云原生构建）仓库发行版的附件存放，下载用附件直链。',
			'direct' => true,
			'range' => true,
			'link' => 'https://cnb.cool/',
			'link_text' => '官方网站',
			'fields' => [
				['name'=>'repo', 'label'=>'仓库', 'required'=>true, 'placeholder'=>'组织名/仓库名', 'tip'=>'cnb.cool 地址里组织之后的完整仓库路径。'],
				['name'=>'token', 'label'=>'访问令牌', 'type'=>'secret', 'required'=>true, 'tip'=>'需要仓库发行版的读写权限。'],
				['name'=>'release', 'label'=>'发行版', 'default'=>'pan', 'tip'=>'文件存到这个发行版里（按发行版名或标签名查找），不存在会以它为标签新建。'],
				['name'=>'default_branch', 'label'=>'新建发行版的分支', 'default'=>'main'],
			],
		];
	}

	private function repoPath()
	{
		return implode('/', array_map('rawurlencode', explode('/', trim($this->c('repo'), '/'))));
	}

	private function api($method, $path, $body = null, $opts = [])
	{
		if($this->c('repo') === '' || $this->c('token') === '')return $this->fail('请填写仓库和访问令牌');
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + ['Accept'=>'application/json', 'Authorization'=>'Bearer '.$this->c('token')];
		if($body !== null)$opts['json'] = $body;
		$url = strpos($path, 'http') === 0 ? $path : $this->apiBase.'/'.$this->repoPath().$path;
		$res = $this->request($method, $url, $opts);
		if($res === false)return false;
		if(!in_array($res['status'], [200, 201, 204], true)){
			if($res['status'] == 404)$this->notfound = true;
			$j = json_decode($res['body'], true);
			return $this->fail('CNB 接口报错（HTTP '.$res['status'].'）：'.(is_array($j) && isset($j['errmsg']) ? $j['errmsg'] : $this->brief($res['body'])));
		}
		$j = json_decode($res['body'], true);
		return is_array($j) ? $j : [];
	}

	private function release()
	{
		if($this->releaseId !== null)return $this->releaseId;
		$name = $this->c('release', 'pan');
		$cached = $this->state('release');
		if(is_array($cached) && $cached['name'] === $name)return $this->releaseId = $cached['id'];
		$list = $this->api('GET', '/-/releases');
		if($list === false)return false;
		foreach($list as $r){
			if((isset($r['tag_name']) && $r['tag_name'] === $name) || (isset($r['name']) && $r['name'] === $name)){
				$this->releaseId = $r['id'];
				break;
			}
		}
		if($this->releaseId === null){
			$r = $this->api('POST', '/-/releases', ['name'=>$name, 'tag_name'=>$name, 'target_commitish'=>$this->c('default_branch', 'main')]);
			if($r === false)return false;
			if(empty($r['id'])){
				//有的版本建完不回内容，再列一次
				$list = $this->api('GET', '/-/releases');
				if($list === false)return false;
				foreach($list as $x)if(isset($x['tag_name']) && $x['tag_name'] === $name)$r = $x;
				if(empty($r['id']))return $this->fail('CNB 新建发行版后找不到它');
			}
			$this->releaseId = $r['id'];
		}
		$this->saveState(['release'=>['name'=>$name, 'id'=>$this->releaseId]]);
		return $this->releaseId;
	}

	private function asset($name)
	{
		$rid = $this->release();
		if($rid === false)return false;
		$r = $this->api('GET', '/-/releases/'.rawurlencode($rid));
		if($r === false)return false;
		if(!empty($r['assets'])){
			foreach($r['assets'] as $a)if($a['name'] === $name)return $a;
		}
		$this->notfound = true;
		return $this->fail('文件不存在');
	}

	protected function check()
	{
		return $this->release() !== false;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$a = $this->asset($name);
		if($a === false)return false;
		return ['size'=>intval($a['size']), 'id'=>$a['id']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$rid = $this->release();
		if($rid === false)return false;
		$u = $this->api('POST', '/-/releases/'.rawurlencode($rid).'/asset-upload-url', ['asset_name'=>$name, 'overwrite'=>true, 'size'=>$size]);
		if($u === false)return false;
		if(empty($u['upload_url']))return $this->fail('CNB 没有返回上传地址');
		$res = $this->request('POST', $u['upload_url'], [
			'multipart' => ['file'=>new \CURLFile($tmpfile, 'application/octet-stream', $name)],
			'timeout' => 0,
		]);
		if($res === false)return false;
		if($res['status'] != 204)return $this->fail('CNB 上传失败（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		if(!empty($u['verify_url']) && $this->api('POST', $u['verify_url']) === false)return false;
		return true;
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$a = $this->asset($name);
		if($a === false)return $this->notfound;
		return $this->api('DELETE', '/-/releases/'.rawurlencode($this->release()).'/assets/'.rawurlencode($a['id'])) !== false;
	}

	protected function link($name)
	{
		$a = $this->asset($name);
		if($a === false)return false;
		return ['url'=>$this->webBase.$a['path']];
	}
}
