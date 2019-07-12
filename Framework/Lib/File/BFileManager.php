<?php
namespace Framework\Lib\File;

class BFileManager
{
    public static function getFileClass($filePath)
    {
        $classMap = array(
            'image' => ImageFile::class,
        );
        $fileType = BFile::getType($filePath);
        return $classMap[$fileType];
    }
}
