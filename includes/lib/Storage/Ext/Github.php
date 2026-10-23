<?php
namespace lib\Storage\Ext;

/**
 * GitHub 仓库（移植自 OpenList drivers/github）
 *
 * 把文件提交进一个 GitHub 仓库的分支里：先 POST git/blobs 传内容，再建新 tree、新 commit，最后移动分支指针。
 * 和 OpenList 的两处不同：
 * 1. OpenList 是逐级重建父目录的 tree；这里用 GitHub 支持的「base_tree + 带斜杠的完整路径」一次建出新的根 tree，
 *    中间目录 GitHub 自己补，请求更少，结果一样。
 * 2. 两个上传同时提交时，后到的那个移动分支会被拒（不是快进），这里会重新取分支头再提交，最多试 3 次。
 * 不支持 OpenList 的 GPG 签名提交（PHP 端没有 OpenPGP）。单个文件不能超过 GitHub 的 100MB 限制。
 */
class Github extends Base
{
	const API = 'https://api.github.com';
	//接口根地址，只有测试脚本会用反射改成本地模拟接口
	private $apiBase = self::API;

	private $owner;
	private $repo;
	private $dir;
	private $ref = null;

	public static function meta()
	{
		return [
			'name' => 'GitHub 仓库',
			'icon' => 'fa-github',
			'group' => '对象存储与国外网盘',
			'source' => 'github',
			'desc' => '把文件提交进 GitHub 仓库的一个分支，下载走 raw 直链（可配加速代理）。单文件不超过 100MB。',
			'direct' => true,
			'range' => true,
			'fields' => [
				['name'=>'token', 'label'=>'访问令牌', 'type'=>'secret', 'required'=>true, 'tip'=>'GitHub 的 Personal Access Token，需要这个仓库的 Contents 读写权限。'],
				['name'=>'owner', 'label'=>'仓库所有者', 'required'=>true],
				['name'=>'repo', 'label'=>'仓库名', 'required'=>true],
				['name'=>'ref', 'label'=>'分支', 'tip'=>'留空用仓库默认分支。只能是分支，标签和提交号写不进去。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'仓库里的目录，不存在会随第一次提交自动出现。'],
				['name'=>'gh_proxy', 'label'=>'raw 加速代理', 'placeholder'=>'https://gh-proxy.com/raw.githubusercontent.com', 'tip'=>'可不填。填了下载直链里的 https://raw.githubusercontent.com 会换成它。'],
				['name'=>'committer_name', 'label'=>'提交者名字', 'tip'=>'可不填，名字和邮箱要么都填要么都不填。'],
				['name'=>'committer_email', 'label'=>'提交者邮箱'],
				['name'=>'put_commit_message', 'label'=>'上传提交说明', 'default'=>'pan upload {path}', 'tip'=>'<b>{path}</b> 会换成文件在仓库里的路径。'],
				['name'=>'delete_commit_message', 'label'=>'删除提交说明', 'default'=>'pan remove {path}'],
			],
		];
	}

	protected function init()
	{
		$this->owner = trim($this->c('owner'));
		$this->repo = trim($this->c('repo'));
		$this->dir = trim($this->cleanPath($this->c('root_folder_path', '/')), '/');
		if($this->c('ref') !== '')$this->ref = $this->c('ref');
	}

	private function repoPath($name)
	{
		return $this->dir === '' ? $name : $this->dir.'/'.$name;
	}

	private function api($method, $path, $body = null, $opts = [])
	{
		if(trim($this->c('token')) === '' || $this->owner === '' || $this->repo === '')return $this->fail('请填写访问令牌、仓库所有者和仓库名');
		$opts['headers'] = (isset($opts['headers']) ? $opts['headers'] : []) + [
			'Accept' => 'application/vnd.github.object+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'Authorization' => 'Bearer '.trim($this->c('token')),
			'User-Agent' => 'pan-storage',
		];
		if($body !== null)$opts['json'] = $body;
		$url = strpos($path, 'http') === 0 ? $path : $this->apiBase.'/repos/'.rawurlencode($this->owner).'/'.rawurlencode($this->repo).$path;
		$res = $this->request($method, $url, $opts);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] >= 300){
			if($res['status'] == 404)$this->notfound = true;
			$this->http = $res['status'];
			return $this->fail('GitHub 接口报错（HTTP '.$res['status'].'）：'.(is_array($j) && isset($j['message']) ? $j['message'] : $this->brief($res['body'])));
		}
		return is_array($j) ? $j : [];
	}

	private function ref()
	{
		if($this->ref !== null)return $this->ref;
		$r = $this->api('GET', '');
		if($r === false)return false;
		if(empty($r['default_branch']))return $this->fail('读不到仓库的默认分支');
		return $this->ref = $r['default_branch'];
	}

	private function contents($name)
	{
		$ref = $this->ref();
		if($ref === false)return false;
		$p = implode('/', array_map('rawurlencode', explode('/', $this->repoPath($name))));
		return $this->api('GET', '/contents/'.$p.'?ref='.rawurlencode($ref));
	}

	/*
	 * 在分支头上改一个路径：$sha 为 blob 的 sha 表示写入，null 表示删除。
	 * 分支被别人抢先推进了（PATCH 返回 422）就重新来一遍。
	 */
	private function commitChange($path, $sha, $message)
	{
		$ref = $this->ref();
		if($ref === false)return false;
		for($try = 0; $try < 3; $try++){
			$this->notfound = false;
			$branch = $this->api('GET', '/branches/'.rawurlencode($ref));
			if($branch === false)return $branch;
			$head = $branch['commit']['sha'];
			$baseTree = $branch['commit']['commit']['tree']['sha'];
			$tree = $this->api('POST', '/git/trees', ['base_tree'=>$baseTree, 'tree'=>[['path'=>$path, 'mode'=>'100644', 'type'=>'blob', 'sha'=>$sha]]]);
			if($tree === false)return false;
			if($tree['sha'] === $baseTree)return true;
			$body = ['message'=>$message, 'tree'=>$tree['sha'], 'parents'=>[$head]];
			if($this->c('committer_name') !== '' && $this->c('committer_email') !== ''){
				$body['committer'] = ['name'=>$this->c('committer_name'), 'email'=>$this->c('committer_email')];
				$body['author'] = $body['committer'];
			}
			$commit = $this->api('POST', '/git/commits', $body);
			if($commit === false)return false;
			$up = $this->api('PATCH', '/git/refs/heads/'.rawurlencode($ref), ['sha'=>$commit['sha'], 'force'=>false]);
			if($up !== false)return true;
			if($this->http != 422)return false;
		}
		return $this->fail('GitHub 分支在提交时一直被别的提交抢先，重试 3 次仍未成功：'.$this->errmsg);
	}

	protected function check()
	{
		if($this->c('committer_name') !== '' xor $this->c('committer_email') !== '')return $this->fail('提交者名字和邮箱要么都填，要么都不填');
		$ref = $this->ref();
		if($ref === false)return false;
		if($this->api('GET', '/branches/'.rawurlencode($ref)) === false)return $this->fail('分支 '.$ref.' 读不到（只能写到分支上）：'.$this->errmsg);
		return true;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$c = $this->contents($name);
		if($c === false)return false;
		if(!isset($c['type']) || $c['type'] !== 'file'){
			$this->notfound = true;
			return $this->fail('文件不存在');
		}
		return ['size'=>intval($c['size']), 'id'=>$c['sha']];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		if($size > 104857600)return $this->fail('GitHub 单个文件不能超过 100MB');
		//blob 要 base64 后放进 JSON，先流式写进一个临时文件再发，不把整个文件读进内存
		$body = tempnam(sys_get_temp_dir(), 'ghblob');
		$out = fopen($body, 'wb');
		$in = fopen($tmpfile, 'rb');
		if(!$out || !$in)return $this->fail('准备上传内容时读写临时文件失败');
		fwrite($out, '{"encoding":"base64","content":"');
		while(!feof($in)){
			$chunk = fread($in, 3 * 262144);
			if($chunk === false || $chunk === '')break;
			fwrite($out, base64_encode($chunk));
		}
		fwrite($out, '"}');
		fclose($in);
		fclose($out);
		$blob = $this->api('POST', '/git/blobs', null, ['file'=>$body, 'headers'=>['Content-Type'=>'application/json', 'Accept'=>'application/vnd.github+json'], 'timeout'=>0]);
		@unlink($body);
		if($blob === false)return false;
		$path = $this->repoPath($name);
		return $this->commitChange($path, $blob['sha'], str_replace('{path}', $path, $this->c('put_commit_message', 'pan upload {path}')));
	}

	protected function remove($name)
	{
		$this->notfound = false;
		if($this->stat($name) === false)return $this->notfound;
		$path = $this->repoPath($name);
		return $this->commitChange($path, null, str_replace('{path}', $path, $this->c('delete_commit_message', 'pan remove {path}')));
	}

	protected function link($name)
	{
		$c = $this->contents($name);
		if($c === false)return false;
		if(empty($c['download_url']))return $this->fail('GitHub 没有给出下载地址');
		$url = $c['download_url'];
		$proxy = rtrim(trim($this->c('gh_proxy')), '/');
		if($proxy !== '')$url = preg_replace('#^https://raw\.githubusercontent\.com#', $proxy, $url, 1);
		return ['url'=>$url];
	}
}
