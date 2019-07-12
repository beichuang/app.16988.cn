<?php
namespace Framework\Lib\File;

use Intervention\Image\ImageManagerStatic as Image;

class ImageFile extends BFile
{
    /**
     * @var width 图片宽度
     */
    protected $width;
    /**
     * @var height 图片高度
     */
    protected $height;

    public function __construct($filePath, $filename = null)
    {
        parent::__construct($filePath, $filename);
        $fileInfo = self::parse($filePath, $filename);
        $this->width = $fileInfo['width'];
        $this->height = $fileInfo['height'];
    }

    /**
     * 解析文件
     * @param string $filePath
     * @return array || null null表示文件不是图片
     */
    public static function parse($filePath, $filename = null)
    {
        $image = Image::make($filePath);
        $result = parent::parse($filePath, $filename);
        $result['width'] = $image->width();
        $result['height'] = $image->height();
        return $result;
    }
}
