<?php

namespace lib;

/*
 * 移植自 OpenList 的存储驱动的注册表（DEC-20261023-001）。
 *
 * 原有的 12 种存储仍由 StorHelper 里写死的清单管，这里只管 includes/lib/Storage/Ext/ 下的新驱动：
 * 类型键 → 类名，参数的读取与保存，能力标签。StorHelper 在自己的清单里找不到时才来问这里，
 * 所以原有存储的行为一点不变。
 *
 * 类型键沿用 OpenList 的驱动目录名，最长不能超过 pre_file.storage 的 20 个字符。
 */
class StorExt
{
	//新驱动按移植完成的顺序登记在这里，没登记的不会出现在设置页
	private static $list = [
		'ftp' => 'Ftp',
		'sftp' => 'Sftp',
		'smb' => 'Smb',
		'seafile' => 'Seafile',
		'cloudreve' => 'Cloudreve',
		'cloudreve_v4' => 'CloudreveV4',
		'kodbox' => 'Kodbox',
		'azure_blob' => 'AzureBlob',
		'bunny_storage' => 'BunnyStorage',
		'ipfs_api' => 'IpfsApi',
		'teldrive' => 'Teldrive',
		'misskey' => 'Misskey',
		'github' => 'Github',
		'cnb_releases' => 'CnbReleases',
		'cloudflare_imgbed' => 'CloudflareImgbed',
		'onedrive_app' => 'OnedriveApp',
		'google_drive' => 'GoogleDrive',
		'dropbox' => 'Dropbox',
		'yandex_disk' => 'YandexDisk',
		'mediafire' => 'Mediafire',
	];

	private static $metas = [];

	public static function keys()
	{
		return array_keys(self::$list);
	}

	public static function has($type)
	{
		return is_string($type) && isset(self::$list[$type]);
	}

	public static function cls($type)
	{
		return self::has($type) ? '\\lib\\Storage\\Ext\\'.self::$list[$type] : null;
	}

	public static function meta($type)
	{
		if(!self::has($type))return [];
		if(!isset(self::$metas[$type])){
			$cls = self::cls($type);
			$meta = class_exists($cls) ? $cls::meta() : [];
			$meta += ['name'=>$type, 'icon'=>'fa-cloud', 'group'=>'其他', 'desc'=>'', 'direct'=>false, 'range'=>true, 'no_referer'=>false, 'fields'=>[], 'requires'=>[]];
			self::$metas[$type] = $meta;
		}
		return self::$metas[$type];
	}

	//已保存的参数，没填的按字段默认值补上
	public static function config($type, $override = null)
	{
		global $conf;
		$raw = isset($conf['stor_'.$type]) ? $conf['stor_'.$type] : '';
		$saved = $raw === '' ? [] : json_decode($raw, true);
		if(!is_array($saved))$saved = [];
		if(is_array($override)){
			foreach($override as $k=>$v)$saved[$k] = $v;
		}
		$cfg = [];
		foreach(self::meta($type)['fields'] as $f){
			$name = $f['name'];
			if(isset($saved[$name]) && !is_array($saved[$name])){
				$cfg[$name] = (string)$saved[$name];
			}else{
				$cfg[$name] = isset($f['default']) ? (string)$f['default'] : '';
			}
		}
		//驱动运行中自己写回的参数（如轮换后的 refresh_token）可能不在表单字段里，也带上
		foreach($saved as $k=>$v){
			if(!isset($cfg[$k]) && !is_array($v))$cfg[$k] = (string)$v;
		}
		return $cfg;
	}

	public static function model($type, $override = null)
	{
		$cls = self::cls($type);
		if($cls === null || !class_exists($cls))return false;
		return new $cls(self::config($type, $override), $type);
	}

	/*
	 * 保存设置页提交的参数：只收 meta() 里登记过的字段，下拉框只收列出的选项。
	 * 已存的参数里那些驱动自己写回、表单上没有的键原样保留。
	 * 参数改了之后旧的运行状态（令牌、Cookie）不一定还对得上，一并清掉，下次用时重新登录。
	 */
	public static function save($type, $input)
	{
		global $conf;
		if(!self::has($type))return '不认识的存储类型';
		if(!is_array($input))$input = [];
		$raw = isset($conf['stor_'.$type]) ? $conf['stor_'.$type] : '';
		$saved = $raw === '' ? [] : json_decode($raw, true);
		if(!is_array($saved))$saved = [];
		$old = $saved;
		foreach(self::meta($type)['fields'] as $f){
			$name = $f['name'];
			$v = isset($input[$name]) && !is_array($input[$name]) ? trim((string)$input[$name]) : '';
			if(isset($f['type']) && $f['type'] === 'select' && !isset($f['options'][$v])){
				$v = isset($f['default']) ? (string)$f['default'] : '';
			}
			$saved[$name] = $v;
		}
		$changed = false;
		foreach(self::meta($type)['fields'] as $f){
			$name = $f['name'];
			if(!isset($old[$name]) || (string)$old[$name] !== $saved[$name])$changed = true;
		}
		$json = json_encode($saved, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		saveSetting('stor_'.$type, $json);
		$conf['stor_'.$type] = $json;
		if($changed){
			saveSetting('stor_'.$type.'_state', '');
			$conf['stor_'.$type.'_state'] = '';
		}
		return true;
	}

	public static function name($type)
	{
		$m = self::meta($type);
		return isset($m['name']) ? $m['name'] : $type;
	}

	/*
	 * 能不能 302 到存储自己的地址。meta() 里 direct 写 'auto' 的，要看站长填的参数
	 * （比如 Bunny 只有填了 CDN 地址才有能给浏览器的地址），交给驱动的 canDirect() 判断。
	 * 这里说能、getDownUrl() 却拿不到地址时，file_output() 会直接报错而不是退回中转，所以不能说大话。
	 */
	public static function is_direct_down($type)
	{
		if(!self::has($type))return false;
		$d = self::meta($type)['direct'];
		if($d === 'auto'){
			$cls = self::cls($type);
			return method_exists($cls, 'canDirect') && $cls::canDirect(self::config($type));
		}
		return !empty($d);
	}

	public static function is_range($type)
	{
		return self::has($type) && !empty(self::meta($type)['range']);
	}

	public static function no_referer($type)
	{
		return self::has($type) && !empty(self::meta($type)['no_referer']);
	}
}
