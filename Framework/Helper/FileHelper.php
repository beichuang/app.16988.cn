<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/18
 * Time: 11:58
 */

namespace Framework\Helper;


use Exception;
use Lib\Common\UploadFileChecker;
use Model\Common\OssFiles;

class FileHelper
{
    const ALLOW_MAX_SIZE = 5 * 1024 * 1024;
    const ALLOW_FILE_TYPES = [
        'image/jpg' => "jpg",
        'image/JPG' => "JPG",
        'image/jpeg' =>"jpeg",
        'image/JPEG' =>"JPEG",
        'image/gif' => 'gif',
        'image/png' => 'png',
        'image/x-png' => 'png'
    ];

    /**
     * 上传文件
     * @param string $fileBusinessType 文件业务类型
     * @param int $allowMaxSize 允许文件大小
     * @param array $allowFileTypes 允许文件类型
     * @return array|string
     */
    public static function uploadFiles($fileBusinessType, $allowMaxSize = self::ALLOW_MAX_SIZE, $allowFileTypes = self::ALLOW_FILE_TYPES)
    {
        $filesData = ['result' => false, 'message' => '', 'data' => []];
        try {
            $responseDataList = [];
            if (isset($_FILES) && is_array($_FILES) && $_FILES) {
                $uploadFileChecker = new UploadFileChecker($allowFileTypes, $allowMaxSize);
                foreach ($_FILES as $fileField => $item) {
                    $uploadFileChecker->checkUploadFile($fileField);
                    if (!empty($item['error'])) {
                        $filesData['message'] = '上传文件时发生异常，异常error:' . $item['error'];
                        return $filesData;
                    }
                }

                foreach ($_FILES as $fileField => $file) {
                    $fileName = $file['name'];
                    $fileTmpName = $file['tmp_name'];
                    $fileType = $file['type'];
                    $fileSize = $file['size'];
                    $responseData = OssHelper::upload($fileBusinessType, $fileName, $fileTmpName);
                    if ($responseData) {
                        $responseDataList[] = [
                            'fileName' => $fileField,
                            'previewUrl' => $responseData['oss-request-url'],
                            'type' => $fileType,
                            'size' => $fileSize,
                            'filePath' => $responseData['remoteFileFullName'],
                            "imageSize" => File::getImageSize($fileTmpName, $fileType)
                        ];
                        //保存文件记录
                        try {
                            $ossFileData = [
                                'of_name' => $fileName,
                                'of_path' => $responseData['remoteFileFullName'],
                                'of_url' => $responseData['oss-request-url'],
                                'of_size' => $fileSize,
                                'of_type' => $fileType,
                                'of_total_time' => empty($responseData['info']['total_time']) ? '' : $responseData['info']['total_time'],
                                'of_client_ip' => empty($responseData['info']['local_ip']) ? '' : $responseData['info']['local_ip']
                            ];
                            (new OssFiles())->save($ossFileData);
                        } catch (Exception $ex) {
                            wlog('保存文件上传oss记录时发生异常，异常信息：' . $ex->getMessage());
                        }
                    }
                }
            }

            $filesData['result'] = true;
            $filesData['data'] = $responseDataList;
        } catch (Exception $ex) {
            wlog('上传文件时发生异常，异常信息：' . $ex->getMessage());
            $filesData = '上传文件时发生异常，异常信息：' . $ex->getMessage();
        }

        return $filesData;
    }

    /**
     * 将图片二进制内容转存到oss
     * @param $imageDataBinary
     * @param $fileName
     * @param $fileBusinessType
     * @return array|bool
     */
    public static function saveImageContent2Oss($imageDataBinary,$fileName,$fileBusinessType)
    {
        try {
            if(!$imgSizeInfo = getimagesizefromstring($imageDataBinary)){
                return false;
            };
            $responseData = \Framework\Helper\OssHelper::uploadWithContent($fileBusinessType,$imageDataBinary, $fileName, 0);
            if ($responseData) {
                $ossFileData = [
                    'of_name' => $fileName,
                    'of_path' => $responseData['remoteFileFullName'],
                    'of_url' => $responseData['oss-request-url'],
                    'of_size' => strlen($imageDataBinary),
                    'of_type' => $imgSizeInfo['mime'],
                    'of_total_time' => empty($responseData['info']['total_time']) ? '' : $responseData['info']['total_time'],
                    'of_client_ip' => empty($responseData['info']['local_ip']) ? '' : $responseData['info']['local_ip']
                ];
                (new OssFiles())->save($ossFileData);
                return [
                    'url' => $responseData['oss-request-url'],
                    'width' => $imgSizeInfo[0],
                    'height' => $imgSizeInfo[1],
                    'mime' => $imgSizeInfo['mime'],
                    'path' => $responseData['remoteFileFullName'],
                ];
            }
        } catch (Exception $ex) {
            wlog('保存文件上传oss记录时发生异常，异常信息：' . $ex->getMessage());
            return false;
        }
        return false;
    }
    /**
     * 获取文件url
     * @param string $filePath 文件路径
     * @param string $configKey 文件业务类型（用于旧的FTP方式的文件，新的OSS方式忽略此参数）
     * @param string $fileOssWidth 要缩放的OSS图片宽度
     * @param string $fileOssHeight 要缩放的OSS图片高度
     * @param string $urlSuffix 阿里云oss地址url后缀
     * @return string
     */
    public static function getFileUrl($filePath, $configKey = '', $fileOssWidth = '', $fileOssHeight = '', $urlSuffix = '')
    {

        $fileUrl = '';
        //能匹配到域名的  不用处理
        if(strpos($filePath,'http') !== false){
            return $filePath;
        }
        //根据文件开头字符串区分新旧文件位置
        if (strpos($filePath, OssHelper::ALIYUN_OSS_MARK) === 0) {
            //阿里云oss
            $fileUrl = OssHelper::getOssFileUrl($filePath, $fileOssWidth, $fileOssHeight);
            $fileUrl .= $urlSuffix;
        } else {
            //FTP
            if (!empty($configKey)) {
                $fileUrl = ftp_get_visit_url($configKey, $filePath);
            }
        }

        return $fileUrl;
    }


    /**
     * @param $filePath
     * @param $content
     * @param bool $fileCreate
     */
    public static function writeFile($filePath, $content, $fileCreate = true)
    {
        if (file_exists($filePath) || $fileCreate) {
            file_put_contents($filePath, $content, FILE_APPEND);
        }
    }
}