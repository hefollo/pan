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

    //判断是否可以直接链接
    public static function is_cloud(){
        global $conf;
        $is_cloud = false;
        if(in_array($conf['storage'], ['oss','qcloud','obs','upyun','qiniu','s3'])) $is_cloud = true;
        return $is_cloud;
    }

    //判断是否可以断点续传
    public static function is_range(){
        global $conf;
        $is_range = false;
        if(in_array($conf['storage'], ['local','oss','qcloud','obs','qiniu','s3'])) $is_range = true;
        return $is_range;
    }
}
