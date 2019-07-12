<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/4/19
 * Time: 22:20
 */

namespace Controller\Common;

use Exception\ParamsInvalidException;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;

class File extends BaseController
{
    public function uploadImagesOld()
    {
        $thumb = app()->request()->params('thumb');
        $imageType = app()->request()->params('imageType');
        switch ($imageType) {
            case  'auction':
                $ftpConfigKey = 'mall_auction_images';
                $prefix = 'auction_images_';
                break;
            default:
                throw new ParamsInvalidException("imageType参数值不正确");
                break;
        }

        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 5 * 1024 * 1024;
        $uid = $this->uid;
        $fileInfos = \Framework\Helper\File::ftpUploadFileFromPost($ftpConfigKey, function ($fileField) use ($uid, $prefix) {
            return $uid . "_" . uniqid($prefix);
        }, $types, $size, $thumb);
        $this->responseJSON(array_values($fileInfos));
    }

    public function uploadImages()
    {
        $imageType = app()->request()->params('imageType');
        switch ($imageType) {
            case  'auction':
                $fileBusinessType = 'mall_auction_images';
                break;
            case  'activity':
                $fileBusinessType = 'activity_images';
                break;
            case  'custom':
                $fileBusinessType = 'mall_custom_images';
                break;
            case 'pc_news':      //pc艺术头条
                $fileBusinessType = 'pc_news_images';
            break;
            case 'pc_treasure':  //
                $fileBusinessType = 'pc_treasure_images';
            break;
            case 'pc_circle':  //pc圈子
                $fileBusinessType = 'pc_circle';
            break;
            case 'pc_other':  //其它
                $fileBusinessType = 'pc_other';
                break;
            default:
                throw new ParamsInvalidException("imageType参数值不正确");
            break;
        }

        $filesData = FileHelper::uploadFiles($fileBusinessType);
        if ($filesData) {
            if (empty($filesData['result'])) {
                $this->responseJSON(empty($filesData['data']) ? [] : $filesData['data'], 1, 1,
                    empty($filesData['message']) ? '' : $filesData['message']);
            } else {
                $this->responseJSON($filesData['data']);
            }
        } else {
            $this->responseJSON([], 1, 1, '上传文件时发生异常');
        }
    }
}