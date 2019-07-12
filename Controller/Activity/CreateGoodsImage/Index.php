<?php

// 活动管理

namespace Controller\Activity\CreateGoodsImage;

use Exception\ParamsInvalidException;
use Framework\Helper\File;
use Framework\Helper\FileHelper;
use Framework\Helper\OssHelper;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;
use Model\Common\OssFiles;

class Index
{
    public function updateGoodsImage()
    {
        $dir = '/data/www/attach.16988.cn/mall_goods_attr_images';
        //$dir = '/Users/jinjiabo/work/php/xiangju/app.16988.cn/Controller/Activity/CreateGoodsImage/images';
        $goodsImages = app('mysqlbxd_mall_common')->select('select * from goods_image limit 1');
        foreach ($goodsImages as $goodsImageItem) {
            $fileName = $goodsImageItem['gi_img'];
            $goodsId = $goodsImageItem['g_id'];
            $goodsImageId = $goodsImageItem['gi_id'];

            if (strpos($fileName, OssHelper::ALIYUN_OSS_MARK) === 0) {
                wlog("fail|{$goodsId}|{$goodsImageId}|商品图片路径为{$fileName}的商品图片为阿里云oss", 'updateGoodsImage');
                continue;
            }

            $fileFullName = "$dir/$fileName";
            if (is_file($fileFullName)) {
                $responseData = OssHelper::upload('mall_goods_attr_images', $fileName, $fileFullName);
                if ($responseData) {
                    //保存文件记录
                    try {
                        $fileType = 'image/jpeg';
                        $ossFileData = [
                            'of_name' => $fileName,
                            'of_path' => $responseData['remoteFileFullName'],
                            'of_url' => $responseData['oss-request-url'],
                            'of_size' => 0,
                            'of_type' => $fileType,
                            'of_total_time' => empty($responseData['info']['total_time']) ? '' : $responseData['info']['total_time'],
                            'of_client_ip' => empty($responseData['info']['local_ip']) ? '' : $responseData['info']['local_ip']
                        ];
                        (new OssFiles())->save($ossFileData);
                    } catch (\Exception $ex) {
                        wlog('保存文件上传oss记录时发生异常，异常信息：' . $ex->getMessage(), 'updateGoodsImage');
                    }
                    //更新商品图片路径
                    app('mysqlbxd_mall_common')->query('update goods_image set gi_img=:gi_img where gi_id=:gi_id',
                        [':gi_id' => $goodsImageId, ':gi_img' => $responseData['remoteFileFullName']]);
                    wlog("success|{$goodsId}|{$goodsImageId}|商品图片路径为{$fileName}的商品图片更改为阿里云oss路径{$responseData['remoteFileFullName']}成功",
                        'updateGoodsImage');
                }
            } else {
                wlog("fail|{$goodsId}|{$goodsImageId}|商品图片路径为{$fileName}不存在", 'updateGoodsImage');
            }
        }
        echo 'success';
    }

    public function updateGoodsSurfaceImage()
    {
        $dir = '/data/www/attach.16988.cn/mall_goods_attr_images';
        //$dir = '/Users/jinjiabo/work/php/xiangju/app.16988.cn/Controller/Activity/CreateGoodsImage/images';
        $goodsData = app('mysqlbxd_mall_user')->select("SELECT * FROM goods WHERE `g_surfaceImg` IS NOT NULL AND `g_surfaceImg`!='' LIMIT 1");
        foreach ($goodsData as $goodsItem) {
            $surfaceImgPath = '';
            $goodsId = $goodsItem['g_id'];
            $surfaceImgJson = $goodsItem['g_surfaceImg'];
            if ($surfaceImgJson) {
                $surfaceImgData = json_decode($surfaceImgJson, true);
                if ($surfaceImgData) {
                    $surfaceImgPath = $surfaceImgData['gi_img'];
                }
            }
            if (empty($surfaceImgPath)) {
                continue;
            }
            if (strpos($surfaceImgPath, OssHelper::ALIYUN_OSS_MARK) === 0) {
                wlog("fail|{$goodsId}|{$goodsId}|商品图片路径为{$surfaceImgPath}的商品图片为阿里云oss", 'updateGoodsSurfaceImage');
                continue;
            }

            $fileName = substr($surfaceImgPath, strrpos($surfaceImgPath, '/') + 1);
            $fileFullName = "$dir/$surfaceImgPath";
            if (is_file($fileFullName)) {
                $responseData = OssHelper::upload('mall_goods_attr_images', $fileName, $fileFullName);
                if ($responseData) {
                    //保存文件记录
                    try {
                        $fileType = 'image/jpeg';
                        $ossFileData = [
                            'of_name' => $fileName,
                            'of_path' => $responseData['remoteFileFullName'],
                            'of_url' => $responseData['oss-request-url'],
                            'of_size' => 0,
                            'of_type' => $fileType,
                            'of_total_time' => empty($responseData['info']['total_time']) ? '' : $responseData['info']['total_time'],
                            'of_client_ip' => empty($responseData['info']['local_ip']) ? '' : $responseData['info']['local_ip']
                        ];
                        (new OssFiles())->save($ossFileData);
                    } catch (\Exception $ex) {
                        wlog('保存文件上传oss记录时发生异常，异常信息：' . $ex->getMessage(), 'updateGoodsSurfaceImage');
                    }
                    //更新商品封面图片路径
                    $surfaceImgData['gi_img'] = $responseData['remoteFileFullName'];
                    $surfaceImgJson = json_encode($surfaceImgData);
                    app('mysqlbxd_mall_user')->query('update goods set g_surfaceImg=:g_surfaceImg where g_id=:g_id',
                        [':g_id' => $goodsId, ':g_surfaceImg' => $surfaceImgJson]);
                    wlog("success|{$goodsId}|商品图片路径为{$fileFullName}的商品图片更改为阿里云oss路径{$responseData['remoteFileFullName']}成功", 'updateGoodsSurfaceImage');
                }
            } else {
                wlog("fail|{$goodsId}|商品图片路径为{$fileFullName}不存在", 'updateGoodsSurfaceImage');
            }
        }
        echo 'success';
    }

    public function addGoodsImage()
    {
        $dir = __DIR__ . '/images';
        $handle = opendir($dir);
        while (($fileName = readdir($handle)) !== false) {
            $goodsId = substr($fileName, 0, strrpos($fileName, '.'));
            $fileFullName = "$dir/$fileName";
            if (is_file($fileFullName)) {
                $responseData = OssHelper::upload('mall_goods_attr_images', $fileName, $fileFullName);
                if ($responseData) {
                    $imageSize = [];
                    //保存文件记录
                    try {
                        $fileType = 'image/jpeg';
                        $imageSize = File::getImageSize($fileFullName, $fileType);
                        $ossFileData = [
                            'of_name' => $fileName,
                            'of_path' => $responseData['remoteFileFullName'],
                            'of_url' => $responseData['oss-request-url'],
                            'of_size' => 0,
                            'of_type' => $fileType,
                            'of_total_time' => empty($responseData['info']['total_time']) ? '' : $responseData['info']['total_time'],
                            'of_client_ip' => empty($responseData['info']['local_ip']) ? '' : $responseData['info']['local_ip']
                        ];
                        (new OssFiles())->save($ossFileData);
                    } catch (\Exception $ex) {
                        wlog('保存文件上传oss记录时发生异常，异常信息：' . $ex->getMessage(), 'createGoodsImage');
                    }
                    //插入到商品图片表中
                    $gi_sort = app('mysqlbxd_mall_common')->fetchColumn('select max(gi_sort) as gi_sort from goods_image where g_id=:gid',
                        [':gid' => $goodsId]);
                    $params = [
                        'g_id' => $goodsId,
                        'gi_img' => $responseData['remoteFileFullName'],
                        'gi_sort' => $gi_sort + 1,
                        'gi_width' => $imageSize['width'],
                        'gi_height' => $imageSize['height'],
                    ];
                    app('mysqlbxd_mall_common')->insert('goods_image', $params);
                    wlog("success|{$goodsId}|商品id为{$goodsId}的商品图片路径{$fileFullName}添加成功", 'addGoodsImage');
                }
            } else {
                wlog("fail|{$goodsId}|商品id为{$goodsId}的商品图片路径{$fileFullName}不存在", 'addGoodsImage');
            }
        }
    }

    public function createGoodsImage()
    {
        //指定获取第二张图片的商品id集合
        $secondImageGoodsIds = [];
        $sql = "SELECT * FROM goods WHERE g_salesId=:salesId AND g_status=3 AND is_own_shop=1 AND FIND_IN_SET(g_categoryId,'40,41,42,43,44,45,46,47,48,49,52')";
        $goodsData = app('mysqlbxd_mall_user')->select($sql, [':salesId' => '648535783']); //410792830
        if ($goodsData) {
            foreach ($goodsData as $goodsItem) {
                $sql = 'SELECT * FROM goods_image WHERE g_id=:gid ORDER BY gi_sort';
                $goodsImageData = app('mysqlbxd_mall_common')->select($sql, [':gid' => $goodsItem['g_id']]);
                if (empty($goodsImageData)) {
                    wlog("fail|{$goodsItem['g_id']}|商品id为{$goodsItem['g_id']}的商品不存在图片，跳过", 'createGoodsImage');
                    continue;
                }
                if (in_array($goodsItem['g_id'], $secondImageGoodsIds)) {
                    if (count($goodsImageData) < 2) {
                        wlog("fail|{$goodsItem['g_id']}|商品id为{$goodsItem['g_id']}的商品指定获取第2张图片，但商品总图片小于2张，跳过", 'createGoodsImage');
                        continue;
                    } else {
                        $imagePath = $goodsImageData[1]['gi_img'];
                    }
                } else {
                    $imagePath = $goodsImageData[0]['gi_img'];
                }

                $sql = "select ga_value from goods_attr where g_id=:gid AND ga_key='尺寸'";
                $goodsImageSize = app('mysqlbxd_mall_common')->fetchColumn($sql, [':gid' => $goodsItem['g_id']]);
                if($goodsImageSize) {
                    $goodsImageSize = str_replace(['厘米', 'cm', 'CM'], '', $goodsImageSize);
                    $goodsImageSize = rtrim($goodsImageSize);
                }

                $createImageData[] = [
                    'goods_image_url' => FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images'),
                    'goods_id' => $goodsItem['g_id'],
                    'goods_name' => $goodsItem['g_name'],
                    'author_realname' => $goodsItem['gu_realname'],
                    'goods_image_size' => ($goodsImageSize ? $goodsImageSize : ($goodsItem['g_width'] . '*' . $goodsItem['g_high'])) . 'cm',
                    'goods_inspiration' => $goodsItem['g_inspiration'],
                ];
            }

            $this->createImages($createImageData);
        }
        $count = count($goodsData);
        wlog("商品总数为{$count}", 'createGoodsImage');
        echo "商品总数为{$count}";
    }

    private function createImages($createImageData)
    {
        //带装裱模板路径、不带装裱模板路径、商品图片x坐标、商品图片y坐标（左上角）、商品图片宽度、商品图片高度
        $templateData = [
            'hf61' => ['/template/横九尺条_有装裱.jpg', '/template/横九尺条_无装裱.jpg', 45, 159, 659, 114],
            'hf41' => ['/template/48-180横幅_带装裱.jpg', '/template/48-180横幅_无装裱.jpg', 64, 114, 618, 197],
            'hf21' => ['/template/68-136横幅_带装裱.jpg', '/template/68-136横幅_无装裱.jpg', 133, 114, 503, 240],
            'df' => ['/template/35-35_带装裱.jpg', '/template/35-35_无装裱.jpg', 205, 120, 354, 354],
            'sf12' => ['/template/138-69竖幅_带装裱.jpg', '/template/138-69竖幅_无装裱.jpg', 227, 128, 298, 397],
            'sf14' => ['/template/68-136竖幅_带装裱.jpg', '/template/68-136竖幅_无装裱.jpg', 257, 76, 250, 510],
            'sf16' => ['/template/48-180竖版_带装裱.jpg', '/template/48-180竖版_无装裱.jpg', 308, 57, 127, 435],
        ];
        foreach ($createImageData as $createImageItem) {
            $decorationType = $createImageItem['goods_inspiration'] == '已装裱' ? 1 : 0;
            //$decorationType = 1;
            $decorationTypeName = $decorationType ? '轴装裱' : '无';
            $offset = $decorationType ? 0 : 20;
            //商品图片
            list ($imageWidth, $imageHeight, $imageType) = getimagesize($createImageItem['goods_image_url']);
            $proportion = $imageWidth / $imageHeight;
            if ($proportion > 4) {
                //横幅6：1左右
                $template = $templateData['hf61'];
            } elseif ($proportion > 2) {
                //横幅4：1左右
                $template = $templateData['hf41'];
            } elseif ($proportion > 1.5) {
                //横幅2：1左右
                $template = $templateData['hf21'];
            } elseif ($proportion >= 1 / 1.2) {
                //斗方 大于1：1.5且小于1.5：1
                $template = $templateData['df'];
            } elseif ($proportion >= 1 / 1.5) {
                //竖幅1：1.3左右
                $template = $templateData['sf12'];
            } elseif ($proportion >= 1 / 2.5) {
                //竖幅1：2左右
                $template = $templateData['sf14'];
            } else {
                //竖幅1：4左右
                $template = $templateData['sf16'];
            }
            //$template = $templateData['hf41'];
            $mainImageRes = imagecreatefromjpeg(__DIR__ . ($decorationType ? $template[0] : $template[1]));
            $imageRes = $this->createImage($createImageItem['goods_image_url'], $imageType);
            //目标图片资源、源图片资源、目标位置x、目标位置y、源图片x、源图片y、目标图片宽度、目标图片高度、源图片宽度、源图片高度
            @imagecopyresampled($mainImageRes, $imageRes, $template[2], $template[3], 0, 0, $template[4], $template[5], $imageWidth, $imageHeight);
            //字体颜色
            $fontColor = imagecolorallocate($mainImageRes, 51, 51, 51);
            //字符文件路径
            $fontFile = __DIR__ . "/../../../Data/msyh.ttf";
            //将文字写在相应的位置
            imagettftext($mainImageRes, 19, 0, 320, 1061 + $offset, $fontColor, $fontFile, $createImageItem['goods_name']);
            imagettftext($mainImageRes, 19, 0, 320, 1109 + $offset, $fontColor, $fontFile, $createImageItem['goods_image_size']);
            imagettftext($mainImageRes, 19, 0, 320, 1153 + $offset, $fontColor, $fontFile, $createImageItem['author_realname']);
            imagettftext($mainImageRes, 19, 0, 374, 1197 + $offset, $fontColor, $fontFile, $decorationTypeName);
            $newImagePath = __DIR__ . '/images/' . $createImageItem['goods_id'] . '.jpg';
            imagejpeg($mainImageRes, $newImagePath,100);
            //销毁图像
            imagedestroy($mainImageRes);
            wlog("success|{$createImageItem['goods_id']}|商品id为{$createImageItem['goods_id']}的商品图片生成成功，路径为：" . $newImagePath, 'createGoodsImage');
        }
    }

    private function createImage($url, $imageType)
    {
        $img = null;
        switch ($imageType) {
            //png
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($url);
                break;
            //jpg
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($url);
                break;
            //gif
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($url);
                break;
        }

        return $img;
    }
}
