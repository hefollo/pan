<?php

namespace lib;

class StorHelper
{
    private static function getConfig($storage){
        global $conf;
        switch($storage){
            case 'local':
                return $conf['filepath'];
                break;
            case 'sae':
            case 'ace':
                return $conf['storagename'];
                break;
            case 'oss':
                return ['accessKeyId' => $conf['oss_ak'], 'accessKeySecret' => $conf['oss_sk'], 'endpoint' => $conf['oss_endpoint'], 'bucket' => $conf['oss_bucket']];
                break;
            case 'qcloud':
                return ['secretId' => $conf['qcloud_id'], 'secretKey' => $conf['qcloud_key'], 'region' => $conf['qcloud_region'], 'bucket' => $conf['qcloud_bucket']];
                break;
            case 'obs':
                return ['accessKey' => $conf['obs_ak'], 'secretKey' => $conf['obs_sk'], 'endpoint' => $conf['obs_endpoint'], 'bucket' => $conf['obs_bucket']];
            case 'upyun':
                return ['operatorName' => $conf['upyun_user'], 'operatorPwd' => $conf['upyun_pwd'], 'serviceName' => $conf['upyun_name']];
            case 'qiniu':
                return ['accessKey' => $conf['qiniu_ak'], 'secretKey' => $conf['qiniu_sk'], 'bucket' => $conf['qiniu_bucket'], 'domain' => $conf['qiniu_domain']];
            case 's3':
                return [
                    'accessKey' => $conf['s3_ak'],
                    'secretKey' => $conf['s3_sk'],
                    'endpoint' => $conf['s3_endpoint'],
                    'region' => empty($conf['s3_region']) ? 'us-east-1' : $conf['s3_region'],
                    'bucket' => $conf['s3_bucket'],
                    'pathStyle' => !empty($conf['s3_path_style']),
                    'prefix' => isset($conf['s3_prefix']) ? $conf['s3_prefix'] : 'file/'
                ];
            case 'webdav':
                return [
                    'url' => isset($conf['webdav_url']) ? $conf['webdav_url'] : '',
                    'user' => isset($conf['webdav_user']) ? $conf['webdav_user'] : '',
                    'pass' => isset($conf['webdav_pass']) ? $conf['webdav_pass'] : '',
                    'path' => isset($conf['webdav_path']) ? $conf['webdav_path'] : 'file'
                ];
            case 'onedrive':
                return [
                    'clientId' => isset($conf['onedrive_client_id']) ? $conf['onedrive_client_id'] : '',
                    'clientSecret' => isset($conf['onedrive_client_secret']) ? $conf['onedrive_client_secret'] : '',
                    'refreshToken' => isset($conf['onedrive_refresh_token']) ? $conf['onedrive_refresh_token'] : '',
                    'china' => isset($conf['onedrive_type']) && $conf['onedrive_type'] == 'china',
                    'path' => isset($conf['onedrive_path']) ? $conf['onedrive_path'] : 'pan/file'
                ];
            case 'openlist':
                return [
                    'url' => isset($conf['openlist_url']) ? $conf['openlist_url'] : '',
                    'user' => isset($conf['openlist_user']) ? $conf['openlist_user'] : '',
                    'pass' => isset($conf['openlist_pass']) ? $conf['openlist_pass'] : '',
                    'token' => isset($conf['openlist_token']) ? $conf['openlist_token'] : '',
                    'path' => isset($conf['openlist_path']) ? $conf['openlist_path'] : 'pan/file'
                ];
            default:
                break;
        }
    }

    public static function getModel($storage)
    {
        $class = "\\lib\\Storage\\".ucwords($storage);
        $config = self::getConfig($storage);
        if(class_exists($class)){
            $model = new $class($config);
            return $model;
        }
        return false;
    }

    /*
     * 存储类型的显示名。
     * 后台设置页和前台上传框的存储下拉都要用，放一处，免得两边对不上。
     */
    public static function names()
    {
        return [
            'local' => '本地存储',
            'oss' => '阿里云 OSS',
            'qcloud' => '腾讯云 COS',
            'obs' => '华为云 OBS',
            'upyun' => '又拍云',
            'qiniu' => '七牛云',
            's3' => '通用 S3 兼容',
            'webdav' => 'WebDAV',
            'onedrive' => 'OneDrive',
            'openlist' => 'OpenList',
            'sae' => 'SaeStorage',
            'ace' => 'AceStorage',
        ];
    }

    public static function name($storage)
    {
        $names = self::names();
        return isset($names[$storage]) ? $names[$storage] : $storage;
    }

    //按存储名缓存实例，一次请求里同一个存储只 new 一遍
    private static $models = [];

    /*
     * 取「某个已有文件」所在存储的驱动。
     *
     * 文件表的 storage 字段记着每条记录当初存到哪儿，所以站长换了全站存储之后，
     * 老文件依然要回原来那个存储去取。凡是针对已有文件的操作——下载、预览、读内容、
     * 删除——都要走这里拿实例，不能再直接用全局的 $stor（那是「当前存储」，只对新上传有效）。
     *
     * 三种情况回落到当前存储：传空（升级前建的老记录）、传的就是当前存储、
     * 传了个已经不认识的存储名。回落等同于改造前的行为，不会让页面直接白屏。
     */
    public static function get($storage = null)
    {
        global $conf, $stor;
        $current = isset($conf['storage']) ? $conf['storage'] : 'local';
        if($storage === null || $storage === '')$storage = $current;
        if($storage === $current)return $stor;
        if(!isset(self::$models[$storage])){
            $model = self::getModel($storage);
            if(!$model){
                trigger_error('文件记录里的存储类型「'.$storage.'」不认识，已回落到当前存储');
                $model = $stor;
            }
            self::$models[$storage] = $model;
        }
        return self::$models[$storage];
    }

    //是不是云存储（本地磁盘之外的都算），后台据此决定要不要显示上传下载方式
    public static function is_cloud($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return !in_array($storage, ['local','sae','ace'], true);
    }

    //能不能直传：浏览器带着签名参数把文件直接 POST 给存储，不经过本站。
    //WebDAV、OneDrive、OpenList 的直传要用 PUT / 上传会话，跟前端这套 POST 表单对不上，只能中转
    public static function is_direct_upload($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['oss','qcloud','obs','upyun','qiniu','s3'], true);
    }

    //能不能直链下载：下载时 302 到存储自己的地址，省本站流量
    public static function is_direct_down($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['oss','qcloud','obs','upyun','qiniu','s3','onedrive','openlist'], true);
    }

    /*
     * 生成直链时会把地址里的域名换成后台填的「文件下载域名」的存储。
     * 这几家的直链默认域名要么不能直接下载（又拍云、七牛必须绑域名），要么站长常换成 CDN，
     * 所以驱动里都做了替换；S3、OneDrive、OpenList 不读这个配置，各自返回完整地址。
     */
    public static function uses_down_domain($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['oss','qcloud','obs','upyun','qiniu'], true);
    }

    //判断是否可以断点续传
    public static function is_range($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['local','oss','qcloud','obs','qiniu','s3','webdav','onedrive','openlist'], true);
    }
}
