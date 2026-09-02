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

    //是不是云存储（本地磁盘之外的都算），后台据此决定要不要显示上传下载方式
    public static function is_cloud($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return !in_array($storage, ['local','sae','ace'], true);
    }

    //能不能直传：浏览器带着签名参数把文件直接 POST 给存储，不经过本站。
    //WebDAV 和 OneDrive 的直传要用 PUT / 上传会话，跟前端这套 POST 表单对不上，只能中转
    public static function is_direct_upload($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['oss','qcloud','obs','upyun','qiniu','s3'], true);
    }

    //能不能直链下载：下载时 302 到存储自己的地址，省本站流量
    public static function is_direct_down($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['oss','qcloud','obs','upyun','qiniu','s3','onedrive'], true);
    }

    //判断是否可以断点续传
    public static function is_range($storage = null){
        global $conf;
        if($storage === null) $storage = $conf['storage'];
        return in_array($storage, ['local','oss','qcloud','obs','qiniu','s3','webdav','onedrive'], true);
    }
}
