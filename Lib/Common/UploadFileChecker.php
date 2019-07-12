<?php
namespace Lib\Common;

use Exception\ParamsInvalidException;

/**
 * 检查上传文件是否合法
 *
 * @author Administrator
 *        
 */
class UploadFileChecker
{

    /**
     * 允许的类型
     *
     * @var unknown
     */
    public $types = null;

    /**
     * 允许的文件夹大小
     *
     * @var unknown
     */
    public $size = null;

    /**
     * 构造函数
     *
     * @param array $types
     *            允许的类型
     * @param int $size
     *            允许的文件大小
     */
    public function __construct($types, $size)
    {
        $this->setTypes($types);
        $this->setSize($size);
    }

    /**
     * 设置允许的类型
     *
     * @param unknown $types            
     */
    public function setTypes($types)
    {
        if ($types) {
            $this->types = $types;
        } else {
            $this->types = [
                'image/jpeg' => "jpg",
                'image/gif' => 'gif',
                'image/png' => 'png',
                'image/x-png' => 'png'
            ];
        }
    }

    /**
     * 设置允许的大小
     *
     * @param unknown $size            
     */
    public function setSize($size)
    {
        if ($size) {
            $this->size = $size;
        } else {
            $this->size = 500 * 1024;
        }
    }

    /**
     * 获取允许类型的文件后缀名
     *
     * @return multitype:
     */
    public function getAllowExts()
    {
        return array_unique(array_values($this->types));
    }

    /**
     * 检查上传的文件
     *
     * @param unknown $fileField            
     * @throws ParamsInvalidException
     */
    public function checkUploadFile($fileField)
    {
        if (! $_FILES || ! isset($_FILES[$fileField])) {
            throw new ParamsInvalidException("{$fileField}:缺少上传图片");
        }
        $file = $_FILES[$fileField];
        if ($file['error']) {
            throw new ParamsInvalidException("{$fileField}:上传失败");
        }
        if ($file['size'] > $this->size) {
            $size = $file['size'] / 1024;
            $unit = 'KB';
            if ($size > 1024) {
                $size = $size / 1024;
                $unit = 'MB';
            }
            throw new ParamsInvalidException("{$fileField}:文件大于" . $size . $unit);
        }
        if (! is_file($file['tmp_name'])) {
            throw new ParamsInvalidException("{$fileField}:上传错误");
        }
        $allowExts = $this->getAllowExts();
        $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
        if (!$ext || ! in_array($ext, $allowExts)) {
            throw new ParamsInvalidException("{$fileField}:不支持的格式，请上传" . implode("、", $this->getAllowExts()) . "格式的图片");
        }
    }

    /**
     * 根据文件mime类型获取后缀名
     *
     * @param unknown $type            
     * @return string
     */
    public function getFileExtByType($type)
    {
        $types = $this->getAllowTypes();
        return isset($types[$type]) ? $types[$type] : "";
    }

    /**
     * 获取允许的mime类型
     *
     * @param string $onlyKey            
     * @return multitype: \Lib\Common\unknown
     */
    public function getAllowTypes($onlyKey = false)
    {
        $types = $this->types;
        if ($onlyKey) {
            return array_keys($types);
        } else {
            return $types;
        }
    }
}
