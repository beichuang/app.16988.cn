<?php

namespace Framework\Helper;

class File {

    /**
     * PHP下递归创建目录的函数，使用示例ykMakeDir('D:\web\web/a/b/c/d/f');
     *
     * @param string $dir
     *            - 需要创建的目录路径，可以是绝对路径或者相对路径
     * @return boolean 返回是否写入成功
     */
    public static function mkdir($dir) {
        return is_dir($dir) or ( self::mkdir(dirname($dir)) and mkdir($dir, 0777));
    }

    /**
     * 写文件
     *
     * @param string $file
     *            - 需要写入的文件，系统的绝对路径加文件名
     * @param string $content
     *            - 需要写入的内容
     * @param string $mod
     *            - 写入模式，默认为w
     * @param boolean $exit
     *            - 不能写入是否中断程序，默认为中断
     * @return boolean 返回是否写入成功
     */
    public static function write($file, $content, $mod = 'w') {
        if (!@$fp = @fopen($file, $mod)) {
            return false;
        } else {
            @flock($fp, 2);
            @fwrite($fp, $content);
            @fclose($fp);
            return true;
        }
    }

    /**
     * 上传文件方法
     * 
     * @param string $allowType
     *            允许类型 image或后缀
     * @param int $maxSize
     *            最大文件大小
     * @param array $maxWHSize
     *            最大宽高 array(with, hight)
     * @param array $files
     *            结构和$_FILES 一致 至少需要'error'和'tmp_name'和'name'
     * @return array array('0表示成功，1表示失败', '成功返回文件名字数组')
     */
    public static function upload($allowType, $maxSize = 0, $maxWHSize = array(), $files = array()) {
        $result = array();
        if (empty($files)) {
            $files = $_FILES;
        }
        /**
         * 过滤
         */
        foreach ($files as $key => $value) {
            if ($value['error'] > 0) {
                return array(
                    1,
                    $value['error']
                );
            } else {
                $bFileClass = \Framework\Lib\File\BFileManager::getFileClass($value['tmp_name']);
                $bFile = new $bFileClass($value['tmp_name'], $value['name']);

                if ($bFile->type != $allowType && $allowType != $bFile->ext) {
                    return array(1, 'TYPE_NO_ALLOW');
                }
                if ($maxSize > 0 && $maxSize < $bFile->size) {
                    return array(1, 'OUT_MAX_SIZE');
                }
                if ($bFile->type == 'image') {
                    $width = intval($maxWHSize[0]);
                    $height = intval($maxWHSize[1]);
                    if (($width > 0 && $width < $bFile->width) || ($height > 0 && $height < $bFile->height)) {
                        return array(1, 'OUT_MAX_WH');
                    }
                }

                $dir = substr(uniqid(), - 2);
                $filename = md5($bFile->name . time()) . '.' . $bFile->ext;
                $isDir = app('ftp')->isDir($dir);
                if (!$isDir) {
                    if (!app('ftp')->mkdir($dir)) {
                        return array(1, 'MKDIR_FAILED');
                    }
                }
                if (!app('ftp')->chdir($dir)) {
                    return array(1, 'CHDIR_FAILED');
                }
                if (!app('ftp')->put($filename, $value['tmp_name'], FTP_BINARY)) {
                    return array(1, 'PUTFILE_FAILED');
                }
                $result[] = "/{$dir}/{$filename}";
            }
        }
        return array(0, $result);
    }

    public static function ftpUploadFile($localFilePath, $ftpRootPath, $targetFileName, $targetFileExt = null, $thumb = null) {
        if (!$targetFileExt) {
            $fileInfo = pathinfo($targetFileName);
            $targetFileName = $fileInfo['filename'];
            $targetFileExt = $fileInfo['extension'];
        }

        $ftp = app('ftp');
        //var_dump(app('ftp'));die();
        if (!$ftp->getConnection()) {
            throw new \Framework\Exception\FtpException("FTP  没有连接！");
        }

        self::ftpCreatePathIfNotExists($ftpRootPath);
        list ($remotePath, $remoteSubPath) = self::ftpCreatePathIfNotExists($ftpRootPath, $targetFileName);
        $remotefile = md5($targetFileName) . "." . $targetFileExt;
        $remoteFilePath = $remotePath . '/' . $remotefile;
        try {
            @$ftp->delete($remoteFilePath);
        } catch (\Exception $e) {
            
        }
        $res = $ftp->put($remoteFilePath, $localFilePath, FTP_BINARY);
        if (!$res) {
            throw new \Framework\Exception\FtpException("上传{$targetFileName}{$targetFileExt}文件失败");
        } elseif (isset($thumb) && !empty($thumb)) {
            $localFilePathThumb = app()->baseDir . '/Data/Temp/' . md5($targetFileName) . "_200x200." . $targetFileExt;
            $remoteFileThumbPaht = $remotePath . '/' . md5($targetFileName) . "_200x200." . $targetFileExt;
            $imageObj = \Image\Image::open($localFilePath);
            $imageObj->thumb(200, 200, \Image\Image::THUMB_SCALING)->save($localFilePathThumb);
            $res2 = $ftp->put($remoteFileThumbPaht, $localFilePathThumb, FTP_BINARY);
            if ($res2) {
                @unlink($localFilePathThumb);
            }
        }
        $remoteSubPath = $remoteSubPath ? $remoteSubPath : $remotePath;
        return "{$remoteSubPath}/{$remotefile}";
    }

    public static function ftpCreatePathIfNotExists($targetPath, $targetFileName = '') {
        $ftp = app('ftp');
        if (!$ftp->getConnection()) {
            throw new \Framework\Exception\FtpException("FTP  没有连接！");
        }
        $targetFullPath = $targetPath;
        $subPath = "";
        if ($targetFileName) {
            $str = md5($targetFileName);
            $subPath = substr($str, 0, 2) . '/' . substr($str, 2, 2);
            $targetFullPath = $targetPath . '/' . $subPath;
        }
        if (!$ftp->isDir($targetFullPath)) {
            if (!$ftp->mkdir($targetFullPath, true)) {
                throw new \Framework\Exception\FtpException("创建目录失败");
            }
        }
        return array(
            $targetFullPath,
            $subPath
        );
    }

    public static function ftpDeleteFile($ftpRootPath, $targetFileName) {
        $ftp = app('ftp');
        if (!$ftp->getConnection()) {
            throw new \Framework\Exception\FtpException("FTP  没有连接！");
        }
        $remoteFilePath = $ftpRootPath . '/' . $targetFileName;

        try {
            @$ftp->delete($remoteFilePath);
        } catch (\Exception $e) {
            
        }
        return true;
    }

    public static function ftpUploadFileFromPost($ftpConfigKey, $callBackGetTargetFileName, $types = null, $size = null, $thumb = null) {
        if (!$_FILES) {
            throw new \Exception("没有上传文件");
        }
        if (!$types) {
            $types = [
                'image/jpeg' => "jpg",
                'image/gif' => 'gif',
                'image/png' => 'png',
                'image/x-png' => 'png'
            ];
        }
        if (!$size) {
            $size = 500 * 1024;
        }
        $uploadFileChecker = new \Lib\Common\UploadFileChecker($types, $size);
        foreach (array_keys($_FILES) as $fileField) {
            $uploadFileChecker->checkUploadFile($fileField);
        }
        $fileInfos = array();
        $ftpRootPath = config('app.ftp.path.' . $ftpConfigKey . '.ftpPath');
        try {
            foreach (array_keys($_FILES) as $fileField) {
                $targetFileName = call_user_func($callBackGetTargetFileName, $fileField);
                $ftpPath = \Framework\Helper\File::ftpUploadFile($_FILES[$fileField]['tmp_name'], $ftpRootPath, $targetFileName, $uploadFileChecker->getFileExtByType($_FILES[$fileField]['type']), $thumb);
                $fileInfos[$fileField]['fileName'] = $fileField;
                $fileInfos[$fileField]['previewUrl'] = ftp_get_visit_url($ftpConfigKey, $ftpPath);
                $fileInfos[$fileField]['type'] = $_FILES[$fileField]['type'];
                $fileInfos[$fileField]['size'] = $_FILES[$fileField]['size'];
                $fileInfos[$fileField]['filePath'] = $ftpPath;
                $fileInfos[$fileField]['imageSize'] = self::getImageSize($_FILES[$fileField]['tmp_name'], $_FILES[$fileField]['type']);
            }
        } catch (\Exception $e) {
            foreach ($fileInfos as $k => $tmpFileInfo) {
                \Framework\Helper\File::ftpDeleteFile($ftpRootPath, $tmpFileInfo['filePath']);
            }
            throw $e;
        }
        return $fileInfos;
    }

    /**
     * 获取图片尺寸
     * 
     * @param string $filename            
     * @param string $type            
     * @return array
     */
    public static function getImageSize($filename, $type) {
        $imageInfo = array(
            'width' => 0,
            'height' => 0
        );
        if (strpos(strtolower($type), 'image/') === 0) {
            $imgSize = @getimagesize($filename);
            if ($imgSize && isset($imgSize[0]) && isset($imgSize[1])) {
                $imageInfo['width'] = $imgSize[0];
                $imageInfo['height'] = $imgSize[1];
            }
        }
        return $imageInfo;
    }

}
