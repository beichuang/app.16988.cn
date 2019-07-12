<?php
namespace Framework\Helper;

use OSS\OssClient;
use OSS\Core\OssException;

include __DIR__ . "/../../Modules/aliyun-oss/autoload.php";

/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/17
 * Time: 11:10
 */
class OssHelper
{
    //阿里云oss文件标识，用于区分新旧文件
    const ALIYUN_OSS_MARK = 'aliyun_oss';

    /**
     * 获取阿里云oss相关配置信息
     * @param string $key
     * @return mixed|string
     */
    private static function getOssConfig($key = '')
    {
        $ossConfig = config('app.aliyun_oss');
        if ($key) {
            return empty($ossConfig[$key]) ? '' : $ossConfig[$key];
        }
        return $ossConfig;
    }

    /**
     * 根据Config配置，得到一个OssClient实例
     *
     * @return OssClient 一个OssClient实例
     */
    private static function getOssClient()
    {
        try {
            $ossConfig = self::getOssConfig();
            $accessKeyId = empty($ossConfig['aliyun_oss.accessKeyId']) ? '' : $ossConfig['aliyun_oss.accessKeyId'];
            $accessKeySecret = empty($ossConfig['aliyun_oss.accessKeySecret']) ? '' : $ossConfig['aliyun_oss.accessKeySecret'];
            $endpoint = empty($ossConfig['aliyun_oss.endpoint']) ? '' : $ossConfig['aliyun_oss.endpoint'];

            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint, false);
        } catch (OssException $e) {
            wlog('创建阿里云OssClient时发生异常，异常信息：' . $e->getMessage());
            throw new OssException($e);
        }
        return $ossClient;
    }

    /**
     * 上传文件到阿里云OSS
     * @param string $fileBusinessType 文件业务类型
     * @param string $fileName 文件名称
     * @param string $localFileFullName 本地文件完整名称
     * @return null
     */
    public static function upload($fileBusinessType, $fileName, $localFileFullName)
    {
        $ossClient = self::getOssClient();
        $ossConfig = self::getOssConfig();
        $bucket = empty($ossConfig['aliyun_oss.bucket']) ? '' : $ossConfig['aliyun_oss.bucket'];
        $remoteFileFullName = self::getFileFullName($fileBusinessType, $fileName);
        $responseData = $ossClient->uploadFile($bucket, $remoteFileFullName, $localFileFullName);
        if ($responseData) {
            $responseData['remoteFileFullName'] = $remoteFileFullName;
        }
        return $responseData;
    }

    /**
     * 上传内容到阿里云OSS
     * @param string $fileBusinessType 文件业务类型
     * @param string $fileName 文件名称
     * @param string $localFileFullName 本地文件完整名称
     * @return null
     */
    public static function uploadWithContent($fileBusinessType, $content,$fileName,$position=0)
    {
        $ossClient = self::getOssClient();
        $ossConfig = self::getOssConfig();
        $bucket = empty($ossConfig['aliyun_oss.bucket']) ? '' : $ossConfig['aliyun_oss.bucket'];
        $remoteFileFullName = self::getFileFullName($fileBusinessType, $fileName);
        $responseData = $ossClient->putObject($bucket, $remoteFileFullName, $content);
        if ($responseData) {
            $responseData['remoteFileFullName'] = $remoteFileFullName;
        }
        return $responseData;
    }

    /**
     * 获取文件url
     * @param string $fileFullName 文件路径
     * @param string $fileOssWidth 要缩放的文件宽度
     * @param string $fileOssHeight 要缩放的文件高度
     * @return string
     */
    public static function getOssFileUrl($fileFullName, $fileOssWidth = '', $fileOssHeight = '')
    {
        $ossConfig = self::getOssConfig();
        $endpoint = empty($ossConfig['aliyun_oss.endpoint']) ? '' : $ossConfig['aliyun_oss.endpoint'];
        $bucket = empty($ossConfig['aliyun_oss.bucket']) ? '' : $ossConfig['aliyun_oss.bucket'];
        $url = $bucket . '.' . $endpoint . '/' . str_replace(array('%2F', '%25'), array('/', '%'), rawurlencode($fileFullName));
        if (!empty($fileOssWidth) || !empty($fileOssHeight)) {
            $url .= '?x-oss-process=image/resize';
            if (!empty($fileOssWidth)) {
                $url .= ',w_' . $fileOssWidth;
            } elseif (!empty($fileOssHeight)) {
                $url .= ',h_' . $fileOssHeight;
            }
        }

        return get_request_url_schema() . '://' . $url;
    }

    /**
     * 获得文件存放路径
     * @param string $fileBusinessType 文件业务类型
     * @param string $fileName 文件名称
     * @return string 文件存放路径
     */
    private static function getFileFullName($fileBusinessType, $fileName)
    {
        $currentDate = date('Ymd');
        $currentYearMonth = substr($currentDate, 0, 6);
        $currentDay = substr($currentDate, -2);
        $folderName = date('His') ;
        $ext=pathinfo($fileName,PATHINFO_EXTENSION);
        $targetFileName=$folderName.rand(10000, 99999).'.'.$ext;
        return static::ALIYUN_OSS_MARK . '/' . $fileBusinessType . '/' . $currentYearMonth . '/' . $currentDay . '/' . $targetFileName;
    }
}