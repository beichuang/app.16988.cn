<?php
namespace Framework\Lib\File;

class BFile
{

    /**
     * @var $name file name
     */
    protected $name;

    /**
     * @var $name file full name filename.ext
     */
    protected $full_name;

    /**
     * @var $type file type image || text || video
     */
    protected $type;

    /**
     * @var $ext file extend name
     */
    protected $ext;

    /**
     * @var $mime_type mime type
     */
    protected $mime_type;

    /**
     * @var $size file size
     */
    protected $size;

    public function __construct($filePath, $filename = null)
    {
        $fileInfo = $this->parse($filePath, $filename);
        $this->name = $fileInfo['name'];
        $this->full_name = $fileInfo['full_name'];
        $this->type = $fileInfo['type'];
        $this->ext = $fileInfo['ext'];
        $this->mime_type = $fileInfo['mime_type'];
        $this->size = $fileInfo['size'];
    }

    public function __get($name)
    {
        if( isset( $this->$name ) ) {
             return $this->$name;
         } else {
             trigger_error( $name . ' variables undefined',  E_USER_NOTICE );
        }
    }

    public function __set($name, $value)
    {
        if (isset($this->$name)) {
            trigger_error( $name . 'is readonly !', E_USER_NOTICE );
        } else {
            $this->$name = $value;
        }
    }

    public static function isFile($filePath)
    {
        return file_exists($filePath) && is_file($filePath);
    }
    /**
     * 解析文件，返回文件的基本信息
     * @param string $filePath 文件路径
     * @return array 和 本类属性一致的数组
     */
    public static function parse($filePath, $filename = null)
    {
        $result = array();
        if (self::isFile($filePath)) {
            $result['full_name'] = $filename ? $filename : basename($filePath);
            $result['name'] = substr($result['full_name'],0, - strrpos($result['full_name'], '.'));
            $result['mime_type'] = self::getMime($filePath);
            $result['type'] = self::getType($filePath);
            $result['ext'] = substr(strrchr($result['full_name'], '.'), 1);
            $result['size'] = filesize($filePath);
        }
        return $result;
    }

    /**
     * 获取文件类型
     * @param string $filePath
     * @return string || null 文件不存在返回null
     */
    public static function getType($filePath)
    {
        if (self::isFile($filePath)) {
            $mime = self::getMime($filePath);
            return strstr($mime, '/', true);
        } else {
            return null;
        }
    }

    /**
     * 获取文件MIME
     * @param string $filePath
     * @return string || null 文件不存在返回null
     */
    public static function getMime($filePath)
    {
        if (self::isFile($filePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE); // 返回 mime 类型
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mime;
        } else {
            return null;
        }
    }
}
