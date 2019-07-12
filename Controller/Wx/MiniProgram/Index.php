<?php
/**
 * 艺术赚赚小程序
 */
namespace Controller\Wx\MiniProgram;

use Curl\Curl;
use Framework\Helper\FileHelper;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;
use Model\Mall\Goods;
use Model\User\User;

class Index extends BaseController
{
    public function getGoodsShareImage()
    {
        $params    = app()->request()->params();
        if (empty($params['goodsId'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $page = 'pages/goods_detail';
        $uid  =$params['uid']?$params['uid']:'';
        $goodsId = $uid?"{$params['goodsId']},{$uid}":$params['goodsId'];
        $templateImageUrl = 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/miniprogram/goods_share_template.jpg';
        //获取请求客户端  是掌玩文化  还是艺术转转
        $type =isset($params['type'])&&$params['type']?$params['type']:'mp_zwwh';
        $wxCodeUrl = $this->getWxCode($page,$goodsId,$type);
        $goodsData = $this->getGoodsData($goodsId);
        $params = [
            'templateImageUrl' => $templateImageUrl,
            'businessData' => $goodsData,
            'wxCodeUrl' => $wxCodeUrl
        ];

        $base64Image = $this->getBase64Image('goods', $params);
        header('content-type:image/jpg');
        echo $base64Image;
        exit;
        //$this->responseJSON(['base64Image' => $base64Image]);
    }

    public function getUserShopShareImage()
    {
        $params = app()->request()->params();
        if (empty($params['userId'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $page = 'pages/shoplist/shoplist';
        //$page = 'pages/goods_detail';
        $userId = $params['userId'];
        $templateImageUrl = 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/miniprogram/user_shop_share_template.jpg';
        $wxCodeUrl = $this->getWxCode($page, $userId, 'mp_yszz');
        //$wxCodeUrl = $this->getWxCode($page, $userId);
        $userData = $this->getUserData($userId);
        $params = [
            'templateImageUrl' => $templateImageUrl,
            'businessData' => $userData,
            'wxCodeUrl' => $wxCodeUrl
        ];
        $base64Image = $this->getBase64Image('userShop', $params);
        header('content-type:image/jpg');
        echo $base64Image;
        exit;
        //$this->responseJSON(['base64Image' => $base64Image]);
    }

    public function getUserDInviteShareImage()
    {
        $params = app()->request()->params();
        if (empty($params['uid'])) {
            throw new \Exception\ParamsInvalidException("缺少参数uid！");
        }
        $uid    = $params['uid'];
        $page   = 'pages/callup';
        $userData = $this->getUserData($uid);
        $templateImageUrl = 'http://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/miniprogram/invitation.jpg';
        if(empty($userData)){
            throw new \Exception\ParamsInvalidException("用户不存在！");
        }
        //头像不存在  修正头像图片
        if(!isset($userData['image'])||empty($userData['image'])){
            $userData['image'] ='https://cdn.16988.cn/res/html/pc/images/morentouxiang.png';
          //  $userData['image']   = 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/miniprogram/user_shop_share_template.jpg';
        }
        $wxCodeUrl = $this->getWxCode($page, "{$uid},{$userData['phone']}", 'mp_yszz');
        $params = [
            'templateImageUrl' => $templateImageUrl,
            'businessData' => $userData,
            'wxCodeUrl' => $wxCodeUrl
        ];
        $base64Image = $this->getBase64Image('userDInvite', $params);
        header('content-type:image/jpg');
        echo $base64Image;
        exit;
        //$this->responseJSON(['base64Image' => $base64Image]);
    }

    private function getBase64Image($type,$params)
    {
        $base64Image = null;
        $imageRes = null;
        switch ($type) {
            case 'goods':
                $imageRes = $this->getGoodsImageRes($params['templateImageUrl'], $params['businessData'], $params['wxCodeUrl']);
                break;
            case 'userShop':
                $imageRes = $this->getUserShopImageRes($params['templateImageUrl'], $params['businessData'], $params['wxCodeUrl']);
                break;
            case 'userDInvite':
                $imageRes = $this->getUserDInviteImageRes($params['templateImageUrl'], $params['businessData'], $params['wxCodeUrl']);

        }
        if ($imageRes) {
            //imagejpeg($imageRes, __DIR__ . "/../../../Data/abc.jpg");

            ob_start(); // Let's start output buffering.
            imagejpeg($imageRes); //This will normally output the image, but because of ob_start(), it won't.
            $contents = ob_get_contents(); //Instead, output above is saved to $contents
            ob_end_clean(); //End the output buffer.
            //$base64Image = "data:image/jpeg;base64," . base64_encode($contents);

            //销毁图像
            imagedestroy($imageRes);
        }
        return $contents;
    }

    private function getGoodsImageRes($templateImageUrl,$businessData,$wxCodeUrl)
    {
        $mainImageRes = imagecreatefromjpeg($templateImageUrl);
        //商品图片
        list ($imageWidth, $imageHeight, $imageType) = getimagesize($businessData['image']);
        $imageRes = $this->createImage($businessData['image'], $imageType);
        //目标图片资源、源图片资源、目标位置x、目标位置y、源图片x、源图片y、目标图片宽度、目标图片高度、源图片宽度、源图片高度
        @imagecopyresampled($mainImageRes, $imageRes, 58, 59, 0, 0, 466, 350, $imageWidth, $imageHeight);
        //字体颜色
        $fontColor = imagecolorallocate($mainImageRes, 49, 26, 7);
        //字符文件路径
        $fontFile = __DIR__ . "/../../../Data/msyh.ttf";
        $x = (582 - mb_strlen($businessData['name']) * 24) / 2;
        //商品名称（目标图片资源、文字大小、文字顺序、位置x、位置y、颜色、字体、文字内容）
        imagettftext($mainImageRes, 20, 0, $x, 460, $fontColor, $fontFile, $businessData['name']);
        //商品价格
        $fontColor = imagecolorallocate($mainImageRes, 255, 78, 78);
        imagettftext($mainImageRes, 20, 0, 238, 530, $fontColor, $fontFile, '￥' . $businessData['price']);
        //小程序码
        list ($imageWidth, $imageHeight, $imageType) = getimagesize($wxCodeUrl);
        $wxCodeImageRes = $this->createImage($wxCodeUrl, $imageType);
        @imagecopyresampled($mainImageRes, $wxCodeImageRes, 233, 560, 0, 0, 116, 116, $imageWidth, $imageHeight);

        imagedestroy($imageRes);
        imagedestroy($wxCodeImageRes);
        return $mainImageRes;
    }

    private function getUserShopImageRes($templateImageUrl,$businessData,$wxCodeUrl)
    {
        $mainImageRes = imagecreatefromjpeg($templateImageUrl);
        //头像
        list ($imageWidth, $imageHeight, $imageType) = getimagesize($businessData['image']);
        $imageRes = $this->createImage($businessData['image'], $imageType);
        //生成圆形
        //$imageRes = $this->getRoundImage($imageRes,$imageWidth,$imageHeight);
        @imagecopyresampled($mainImageRes, $imageRes, 216, 70, 0, 0, 92, 92, $imageWidth, $imageHeight);

        //字体颜色
        $fontColor = imagecolorallocate($mainImageRes, 206, 160, 104);
        //字符文件路径
        $fontFile = __DIR__ . "/../../../Data/msyh.ttf";
        $x = (525 - mb_strlen($businessData['name']) * 21) / 2;
        //将文字写在相应的位置
        imagettftext($mainImageRes, 18, 0, $x, 200, $fontColor, $fontFile, $businessData['name']);
        //小程序码
        list ($imageWidth, $imageHeight, $imageType) = getimagesize($wxCodeUrl);
        $wxCodeImageRes = $this->createImage($wxCodeUrl, $imageType);
        //生成圆形
        //$wxCodeImageRes = $this->getRoundImage($wxCodeImageRes,$imageWidth,$imageHeight);
        @imagecopyresampled($mainImageRes, $wxCodeImageRes, 140, 310, 0, 0, 240, 240, $imageWidth, $imageHeight);

        imagedestroy($imageRes);
        imagedestroy($wxCodeImageRes);
        return $mainImageRes;
    }

    private function getUserDInviteImageRes($templateImageUrl,$businessData,$wxCodeUrl)
    {
        $mainImageRes = imagecreatefromjpeg($templateImageUrl);
        //没有头像   增加默认头像
        $businessData['image'] = isset($businessData['image'])&&$businessData['image']?$businessData['image']:'http://cdn.16988.cn/res/html/pc/images/morentouxiang.png';
        list ($imageWidth, $imageHeight, $imageType) = getimagesize($businessData['image']);
        $imageRes = $this->createImage($businessData['image'], $imageType);
        //自动生成圆形图片
       //  $imageRes = $this->test($imageRes);
        //$imageRes  = $this->getRoundImage($imageRes,800,800);
        //目标图片资源、源图片资源、目标位置x、目标位置y、源图片x、源图片y、目标图片宽度、目标图片高度、源图片宽度、源图片高度
        @imagecopyresampled($mainImageRes, $imageRes, 310, 72, 0, 0, 126, 126,$imageWidth, $imageHeight);
        //字体颜色
        $fontColor = imagecolorallocate($mainImageRes, 206, 160, 104);
        //字符文件路径
        $fontFile = __DIR__ . "/../../../Data/msyh.ttf";
        $x = (750 - mb_strlen($businessData['name']) * 21) / 2;
        //商品名称（目标图片资源、文字大小、文字顺序、位置x、位置y、颜色、字体、文字内容）
        imagettftext($mainImageRes, 20, 0,$x ,270, $fontColor, $fontFile, $businessData['name']);
        //小程序码
        list ($imageWidth, $imageHeight, $imageType) = getimagesize($wxCodeUrl);
        $wxCodeImageRes = $this->createImage($wxCodeUrl, $imageType);
        //生成圆形
        @imagecopyresampled($mainImageRes, $wxCodeImageRes,222,533,0,0, 309,309, $imageWidth, $imageHeight);
        imagedestroy($imageRes);
        imagedestroy($wxCodeImageRes);
        return $mainImageRes;
    }

    private function createImage($url,$imageType)
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

    /**
     * 生成圆形图片
     * @param $imageRes
     * @param $imageWidth
     * @param $imageHeight
     * @return resource
     */
    private function getRoundImage($imageRes,$imageWidth,$imageHeight)
    {
        $img = imagecreatetruecolor($imageWidth, $imageHeight);
        //这一句一定要有
        imagesavealpha($img, true);
        //拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $bg);
        $r = $imageWidth / 2; //圆半径
        for ($x = 0; $x < $imageWidth; $x++) {
            for ($y = 0; $y < $imageHeight; $y++) {
                $rgbColor = imagecolorat($imageRes, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }

        return $img;
    }

    private function getWxCode($page, $scene, $key = 'mp_zwwh')
    {
        $accessToken = WxHelper::getAccessToken($key);
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$accessToken}";
        $params = [
            'scene' => $scene,
            'page' => $page,
            'auto_color'=>false,
            'is_hyaline'=>true
        ];
        $curl = new Curl();
        $res = $curl->post($url, json_encode($params));
        if(is_object($res) || is_array($res)){
            wlog(['error:',$url,$params,$res],'wx-get-mini-qrcode');
        }else{
            wlog(['success:',$url,$params],'wx-get-mini-qrcode');
        }
        $base64Image = "data:image/jpeg;base64," . base64_encode($res);
        return $base64Image;
    }

    private function getGoodsData($goodsId)
    {
        $data = [];
        $goodsModel = new Goods('', '', 'mysqlbxd_mall_user');
        $goodsData = $goodsModel->oneById($goodsId);
        if ($goodsData) {
            $data['name'] = $goodsData['g_name'];
            if (!empty($goodsData['g_surfaceImg'])) {
                $surfaceImg = json_decode(stripslashes($goodsData['g_surfaceImg']), true);
                $data['image'] = $surfaceImg['gi_img'];
            } else {
                $images = $goodsModel->getImagesById($goodsData['g_id']);
                $data['image'] = empty($images[0]['gi_img']) ? '' : $images[0]['gi_img'];
            }
            $data['image'] = $data['image'] ? FileHelper::getFileUrl($data['image'], 'mall_goods_attr_images') : '';

            $nowDate = date('Y-m-d H:i:s');
            if ($goodsData['g_secKillStart'] < $nowDate && $goodsData['g_secKillEnd'] > $nowDate) {
                $data['price'] = $goodsData['g_activityPrice'];
            } else {
                $data['price'] = $goodsData['g_price'];
            }

        }
        return $data;
    }

    private function getUserData($userId)
    {
        $data = [];
        $userModel = new User('', '', 'mysqlbxd_user');
        $userData = $userModel->oneById($userId);
        if ($userData) {
            $data['phone'] = $userData['u_phone'];
            $data['name'] = $userData['u_nickname'];
            $data['name'] = empty($userData['u_nickname'])?'您还没有昵称哦':$userData['u_nickname'];
            if (!empty($userData['u_avatar'])) {
                $data['image'] = FileHelper::getFileUrl($userData['u_avatar'], 'user_avatar');
            } else {
                $data['image'] = '';
            }
        }

        return $data;
    }
}
