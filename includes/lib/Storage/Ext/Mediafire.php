<?php
namespace lib\Storage\Ext;

/**
 * MediaFire（移植自 OpenList drivers/mediafire）
 *
 * 用浏览器 Cookie 换 session_token（十来分钟就过期，按 OpenList 的做法 8 分钟内续一次，续不上再用 Cookie 重新换），
 * 上传要另取一个 upload 类型的 action_token。文件按 quick_key 操作，本站文件名 → quick_key 存 pre_stor_map。
 * 上传流程：upload/check（带整个文件的 sha256）→ 按单元 upload/resumable（已传过的单元按位图跳过）→ upload/poll_upload。
 * 和 OpenList 的不同：
 * 1. 内容已在账号里（hash_exists）时，OpenList 直接认账号里已有的那个文件，可能是站长自己别处的文件，本站删除时会误删；
 *    这里改用 upload/instant 在存储目录里新建一份。
 * 2. OpenList 只轮询一次上传结果，可能还没处理完就返回空 ID；这里最多等一分钟。
 * 3. 并发上传单元改成按顺序传。
 * 下载地址要带 Origin / Referer / UA，只走中转。
 */
class Mediafire extends Base
{
	const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

	//各接口根地址，只有测试脚本会用反射改成本地模拟接口
	private $appBase = 'https://app.mediafire.com';
	private $apiBase = 'https://www.mediafire.com/api/1.5';
	private $hostBase = 'https://www.mediafire.com';
	private $folderKey = false;
	private $actionToken = null;
	private $lastCall = 0;

	public static function meta()
	{
		return [
			'name' => 'MediaFire',
			'icon' => 'fa-fire',
			'group' => '对象存储与国外网盘',
			'source' => 'mediafire',
			'desc' => 'MediaFire 网盘（用浏览器 Cookie 登录），上传下载走本站中转。',
			'direct' => false,
			'range' => true,
			'fields' => [
				['name'=>'cookie', 'label'=>'Cookie', 'type'=>'secret', 'required'=>true, 'tip'=>'浏览器登录 mediafire.com 后，开发者工具里复制完整的 Cookie。'],
				['name'=>'session_token', 'label'=>'Session Token', 'type'=>'secret', 'tip'=>'可不填，会用 Cookie 自动换。'],
				['name'=>'root_folder_path', 'label'=>'存储目录', 'default'=>'/pan/file', 'placeholder'=>'/pan/file', 'tip'=>'按文件夹名逐级查找，不存在会自动创建。'],
				['name'=>'limit_rate', 'label'=>'每秒最多请求数', 'default'=>'2', 'tip'=>'MediaFire 对请求频率很敏感，OpenList 默认每秒 2 次。'],
			],
		];
	}

	/* ---------------- 会话 ---------------- */

	private function throttle()
	{
		$rate = floatval($this->c('limit_rate', '2'));
		if($rate <= 0)return;
		$gap = 1 / $rate;
		$wait = $this->lastCall + $gap - microtime(true);
		if($wait > 0)usleep((int)($wait * 1000000));
		$this->lastCall = microtime(true);
	}

	private function cookie()
	{
		$c = $this->state('cookie');
		return $c ? $c : $this->c('cookie');
	}

	private function fromCookie()
	{
		if($this->cookie() === '')return $this->fail('请填写 MediaFire 的 Cookie');
		$this->throttle();
		$res = $this->request('POST', $this->hostBase.'/application/get_session_token.php', ['body'=>'', 'headers'=>[
			'Cookie'=>$this->cookie(), 'Origin'=>$this->hostBase, 'Referer'=>$this->hostBase.'/', 'User-Agent'=>self::UA, 'Accept'=>'*/*',
		]]);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if($res['status'] != 200 || empty($j['response']['session_token']))return $this->fail('用 Cookie 换 session_token 失败（HTTP '.$res['status'].'），Cookie 可能已过期');
		$save = ['session_token'=>$j['response']['session_token'], 'token_at'=>time()];
		//MediaFire 会顺带下发新的 Cookie，跟 OpenList 一样存下来用新的
		if(!empty($res['cookies'])){
			$parts = [];
			foreach($res['cookies'] as $k=>$v)$parts[] = $k.'='.$v;
			$save['cookie'] = implode('; ', $parts);
		}
		$this->saveState($save);
		return $save['session_token'];
	}

	private function session()
	{
		$tok = $this->state('session_token');
		if(!$tok && $this->c('session_token') !== ''){
			$tok = $this->c('session_token');
			$this->saveState(['session_token'=>$tok, 'token_at'=>0]);
		}
		if(!$tok)return $this->fromCookie();
		if(time() - intval($this->state('token_at', 0)) < 480)return $tok;
		$j = $this->post('/user/renew_session_token.php', ['session_token'=>$tok], false);
		if($j !== false && !empty($j['response']['session_token'])){
			$this->saveState(['session_token'=>$j['response']['session_token'], 'token_at'=>time()]);
			return $j['response']['session_token'];
		}
		return $this->fromCookie();
	}

	private function post($endpoint, $data, $auth = true)
	{
		if($auth){
			$tok = $this->session();
			if($tok === false)return false;
			$data['session_token'] = $tok;
		}
		$data['response_format'] = 'json';
		$this->throttle();
		$res = $this->request('POST', $this->apiBase.$endpoint, ['form'=>$data, 'headers'=>[
			'Cookie'=>$this->cookie(), 'User-Agent'=>self::UA, 'Origin'=>$this->appBase, 'Referer'=>$this->appBase.'/',
		]]);
		if($res === false)return false;
		$j = json_decode($res['body'], true);
		if(!is_array($j) || !isset($j['response']))return $this->fail('MediaFire 返回了无法识别的内容（HTTP '.$res['status'].'）：'.$this->brief($res['body']));
		return $j;
	}

	//调接口并要求 result 为 Success；会话失效（错误码 105）时用 Cookie 重新换一次再试
	private function call($endpoint, $data)
	{
		for($i = 0; $i < 2; $i++){
			$j = $this->post($endpoint, $data);
			if($j === false)return false;
			$r = $j['response'];
			if(isset($r['result']) && $r['result'] === 'Success')return $r;
			$code = isset($r['error']) ? intval($r['error']) : 0;
			if($i === 0 && in_array($code, [105, 127], true)){
				$this->saveState(['session_token'=>null]);
				if($this->fromCookie() === false)return false;
				continue;
			}
			if(in_array($code, [110, 111, 112], true))$this->notfound = true;
			return $this->fail('MediaFire 接口报错（'.$code.'）：'.(isset($r['message']) ? $r['message'] : json_encode($r, JSON_UNESCAPED_UNICODE)));
		}
		return false;
	}

	private function action()
	{
		if($this->actionToken !== null)return $this->actionToken;
		$r = $this->call('/user/get_action_token.php', ['type'=>'upload', 'lifespan'=>'1440']);
		if($r === false)return false;
		return $this->actionToken = $r['action_token'];
	}

	/* ---------------- 目录 ---------------- */

	private function folderItems($key, $type)
	{
		$all = [];
		for($chunk = 1; $chunk < 100; $chunk++){
			$r = $this->call('/folder/get_content.php', ['folder_key'=>$key, 'content_type'=>$type, 'chunk'=>(string)$chunk, 'chunk_size'=>'100', 'details'=>'yes']);
			if($r === false)return false;
			$c = isset($r['folder_content']) ? $r['folder_content'] : [];
			$items = isset($c[$type]) ? $c[$type] : [];
			$all = array_merge($all, $items);
			if(!isset($c['more_chunks']) || $c['more_chunks'] !== 'yes')break;
		}
		return $all;
	}

	private function folder()
	{
		if($this->folderKey !== false)return $this->folderKey;
		$root = $this->cleanPath($this->c('root_folder_path', '/pan/file'));
		$cached = $this->state('folder');
		if(is_array($cached) && $cached['root'] === $root)return $this->folderKey = $cached['key'];
		$key = '';
		foreach(explode('/', trim($root, '/')) as $seg){
			if($seg === '')continue;
			$found = null;
			$list = $this->folderItems($key, 'folders');
			if($list === false)return false;
			foreach($list as $f)if($f['name'] === $seg)$found = $f['folderkey'];
			if($found === null){
				$r = $this->call('/folder/create.php', ['parent_key'=>$key, 'foldername'=>$seg, 'action_on_duplicate'=>'skip']);
				if($r === false)return false;
				$found = $r['folder_key'];
			}
			$key = $found;
		}
		$this->saveState(['folder'=>['root'=>$root, 'key'=>$key]]);
		return $this->folderKey = $key;
	}

	private function quickKey($name)
	{
		$k = $this->mapGet($name);
		if($k !== null)return $k;
		$folder = $this->folder();
		if($folder === false)return false;
		$list = $this->folderItems($folder, 'files');
		if($list === false)return false;
		foreach($list as $f){
			if($f['filename'] === $name){
				$this->mapSet($name, $f['quickkey']);
				return $f['quickkey'];
			}
		}
		$this->notfound = true;
		return $this->fail('文件不存在');
	}

	/* ---------------- 驱动接口 ---------------- */

	protected function check()
	{
		return $this->folder() !== false;
	}

	protected function stat($name)
	{
		$this->notfound = false;
		$k = $this->quickKey($name);
		if($k === false)return false;
		$r = $this->call('/file/get_info.php', ['quick_key'=>$k]);
		if($r === false){
			if($this->notfound)$this->mapDel($name);
			return false;
		}
		$info = isset($r['file_info'][0]) ? $r['file_info'][0] : (isset($r['file_info']) ? $r['file_info'] : []);
		return ['size'=>isset($info['size']) ? intval($info['size']) : 0, 'id'=>$k];
	}

	protected function put($name, $tmpfile, $size, $content_type)
	{
		$folder = $this->folder();
		if($folder === false)return false;
		$hash = hash_file('sha256', $tmpfile);
		$at = $this->action();
		if($at === false)return false;
		$chk = $this->post('/upload/check.php', ['session_token'=>$at, 'filename'=>$name, 'size'=>(string)$size, 'hash'=>$hash, 'folder_key'=>$folder, 'resumable'=>'yes'], false);
		if($chk === false)return false;
		$r = $chk['response'];
		if(!isset($r['result']) || $r['result'] !== 'Success')return $this->fail('MediaFire 上传检查失败：'.(isset($r['message']) ? $r['message'] : ''));
		if(isset($r['hash_exists']) && $r['hash_exists'] === 'yes'){
			$i = $this->post('/upload/instant.php', ['session_token'=>$at, 'hash'=>$hash, 'size'=>(string)$size, 'filename'=>$name, 'folder_key'=>$folder, 'action_on_duplicate'=>'replace'], false);
			if($i !== false && isset($i['response']['result']) && $i['response']['result'] === 'Success' && !empty($i['response']['quickkey'])){
				$this->mapSet($name, $i['response']['quickkey']);
				return true;
			}
			//秒传不成就老老实实传一遍
		}
		$ru = isset($r['resumable_upload']) ? $r['resumable_upload'] : [];
		$key = isset($ru['upload_key']) ? $ru['upload_key'] : '';
		if(!isset($ru['all_units_ready']) || $ru['all_units_ready'] !== 'yes'){
			$unit = intval(isset($ru['unit_size']) ? $ru['unit_size'] : 0);
			$num = intval(isset($ru['number_of_units']) ? $ru['number_of_units'] : 0);
			if($unit <= 0 || $num <= 0)return $this->fail('MediaFire 没有给出分片信息');
			$words = [];
			if(!empty($ru['bitmap']['words']))foreach($ru['bitmap']['words'] as $w)$words[] = intval($w);
			for($u = 0; $u < $num; $u++){
				$wi = intdiv($u, 16);
				if($wi < count($words) && (($words[$wi] >> ($u % 16)) & 1))continue;
				$off = $u * $unit;
				$len = min($unit, $size - $off);
				$ok = false;
				for($try = 0; $try < 3 && !$ok; $try++){
					$this->throttle();
					$res = $this->request('POST', $this->apiBase.'/upload/resumable.php?'.http_build_query(['folder_key'=>$folder, 'response_format'=>'json', 'session_token'=>$at, 'key'=>$key]), [
						'headers' => [
							'x-filehash'=>$hash, 'x-filesize'=>(string)$size, 'x-unit-id'=>(string)$u, 'x-unit-size'=>(string)$len,
							'x-unit-hash'=>$this->partHash($tmpfile, $off, $len, 'sha256'), 'x-filename'=>$name,
							'Content-Type'=>'application/octet-stream', 'Cookie'=>$this->cookie(), 'User-Agent'=>self::UA,
						],
						'file' => $tmpfile, 'offset' => $off, 'length' => $len, 'timeout' => 0,
					]);
					if($res === false || $res['status'] != 200)continue;
					$j = json_decode($res['body'], true);
					if(!empty($j['response']['doupload']['key']))$key = $j['response']['doupload']['key'];
					$ok = true;
				}
				if(!$ok)return $this->fail('MediaFire 第 '.($u + 1).' 个单元上传失败'.($res ? '（HTTP '.$res['status'].'）' : '：'.$this->errmsg));
			}
		}
		for($i = 0; $i < 30; $i++){
			$p = $this->post('/upload/poll_upload.php', ['session_token'=>$at, 'key'=>$key], false);
			if($p === false)return false;
			$d = isset($p['response']['doupload']) ? $p['response']['doupload'] : [];
			if(!empty($d['quickkey'])){
				$this->mapSet($name, $d['quickkey']);
				return true;
			}
			if(isset($d['fileerror']) && $d['fileerror'] !== '' && $d['fileerror'] !== '0')return $this->fail('MediaFire 处理上传失败（错误码 '.$d['fileerror'].'）');
			sleep(2);
		}
		return $this->fail('MediaFire 一分钟内没有处理完上传，稍后到网盘里看看');
	}

	protected function remove($name)
	{
		$this->notfound = false;
		$k = $this->quickKey($name);
		if($k === false)return $this->notfound;
		if($this->call('/file/delete.php', ['quick_key'=>$k]) !== false)return true;
		return $this->notfound;
	}

	protected function link($name)
	{
		$k = $this->quickKey($name);
		if($k === false)return false;
		$r = $this->call('/file/get_links.php', ['quick_key'=>$k, 'link_type'=>'direct_download']);
		if($r === false)return false;
		if(empty($r['links'][0]['direct_download']))return $this->fail('MediaFire 没有返回下载地址');
		$url = $r['links'][0]['direct_download'];
		$res = $this->request('HEAD', $url, ['follow'=>false, 'headers'=>['User-Agent'=>self::UA]]);
		if($res !== false && $res['status'] == 302 && !empty($res['headers']['location']))$url = $res['headers']['location'];
		return ['url'=>$url, 'headers'=>['Origin'=>$this->appBase, 'Referer'=>$this->appBase.'/', 'User-Agent'=>self::UA]];
	}
}
