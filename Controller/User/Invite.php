<?php

/**
 * 分享邀请
 * @author Administrator
 *
 */

namespace Controller\User;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ModelException;

use Framework\Lib\Validation;
use Lib\User\UserFrom;
use Model\User\User;
use QRcode;

class Invite extends BaseController {

    private $inviteModel = null;
    private $integralService = null;
    protected $userApi = null;

    public function __construct() {
        parent::__construct();
        $this->userApi = get_api_client('User');
        $this->inviteModel = new \Model\User\Invite();
        $this->integralService = new \Lib\User\UserIntegral();
        $this->certificationModel = new \Model\User\Certification();
    }

    /** 邀请记录
     * @param int $page
     * @param int $pageSize
     * @throws ModelException
     */
    public function lists()
    {
//        $uid = app()->request()->params('uid');
//        if (!$uid) {
//            throw new \Exception\ParamsInvalidException("未登录");
//        }
        $uid = $this->uid;
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        $regOnly = app()->request()->params('regOnly', '');
        $params['u_id'] = $uid;
        $result = $this->inviteModel->lists($params, $page, $pageSize);
        if ($result[1]) {
            $data['count'] = $result[1];
            $data['reg_count'] = $result[2];
            $userLib = new \Lib\User\User();
            $reg_phones = $userLib->queryUserIsRegisterByPhones(array_column($result[0], 'uil_phone'));
            foreach ($result[0] as &$info) {
                if ($info['uil_phone']) {
                    $info['des'] = in_array($info['uil_phone'], $reg_phones) ? '已受邀已注册' : '已受邀未注册';
                    $info['uil_phone'] = substr_replace($info['uil_phone'], '****', 3, 4);
                }
            }
            if ($regOnly) {
                if (strtolower(app()->request()->headers('Source')) == 'ios') {
                    $data['list'] = array_filter($result[0], function ($i) {
                        return $i['des'] == '已受邀已注册';
                    });
                } else {
                    foreach ($result[0] as $row) {
                        if ($row['des'] == '已受邀已注册') {
                            $data['list'][] = $row;
                        }
                    }
                }
            } else {
                $data['list'] = $result[0];
            }

            $userInfo = $userLib->getUserInfo(array($uid));
            if ($userInfo) {
                $data['u_integral'] = $userInfo[$uid]['u_integral'] ? $userInfo[$uid]['u_integral'] : 0;
            }
        } else {
            $data['count'] = 0;
            $data['reg_count'] = 0;
            $data['list'] = [];
        }
        $this->responseJSON($data);
    }

    /**
     * 生成邀请链接
     */
    public function invite() {
        $uid = app()->request()->params('uid');
        $type = app()->request()->params('type',0);
        if (!$uid) {
            throw new \Exception\ParamsInvalidException("未登录");
        }

        $baseUrlSchema = config('app.request_url_schema_x_forwarded_proto_default');
        $baseDomain = config('app.baseDomain');
        $domain = $baseUrlSchema .'://'.$baseDomain;
        $uid = $this->encode($uid);

        //$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        switch ($type){
            case '1':
                $url = $domain . '/html/apph5/invite_beboss_m.html?uid=' . $uid;
                break;
            case '2':
                $url = $domain . '/html/apph5/inviteFriends.html#/invitem?uid=' . $uid;
                break;
            default:
                $url = $domain . '/html/invitem.html?uid=' . $uid;
        }
        $this->responseJSON([
            'url' => $url
        ]);
    }
    /**
     * 新增邀请
     *
     * @throws ModelException
     */
    public function addByPhone() {
        $uid = app()->request()->params('uid');
        $phone = app()->request()->params('phone');
        $captcha = app()->request()->params('captcha');
        if (!($uid && $phone && $captcha)) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        if (!Validation::checkMobile($phone)) {
            throw new \Exception\ParamsInvalidException("手机号格式错误");
        }
        if(!preg_match("@\w{4,8}@",$captcha)){
            throw new \Exception\ParamsInvalidException("验证码格式错误");
        }

        $uid = $this->decode($uid);

        /* $userInfo = $this->certificationModel->getInfo($uid);
          if ( !$userInfo ) {
          throw new \Exception\ParamsInvalidException("用户不存在");
          } */

        $inviteUser = $this->inviteModel->getInfoByPhone($phone);
        if ($inviteUser) {
            throw new \Exception\ParamsInvalidException("已经被邀请");
        }

        $userLib = new \Lib\User\User();
        $users = $userLib->getUserInfo([], $phone);
        if ($users) {
            throw new \Exception\ParamsInvalidException("已经注册过");
        }
        $id = $this->inviteModel->add($uid, $phone);
        if (!$id) {
            throw new ModelException("保存失败");
        }

        $userLib=new \Lib\User\User();
        $res=$userLib->regNewUser($phone, $captcha, '',UserFrom::INVITE_REGISTER);
        if ($res->code != 200) {
            throw new ServiceException($res->data, $res->code);
        }
        $userInfoReg=$res->data;

        //送邀请人积分
        $this->integralService->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_INVITE_ADD);
        //獲取目前積分
        $userInfo = $userLib->getUserInfo(array($uid), '');
        $currentIntegral = 0;
        if ($userInfo) {
            $currentIntegral = $userInfo[$uid]['u_integral'];
        }

        $this->responseJSON(
            array(
                'invite_id' => $id,
                'currentIntegral' => $currentIntegral
            ));
    }
    /**
     * 新增邀请
     *
     * @throws ModelException
     */
    public function add() {
        $uid = app()->request()->params('uid');
        $phone = app()->request()->params('phone');
        if (!($uid && $phone)) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        if (!Validation::checkMobile($phone)) {
            throw new \Exception\ParamsInvalidException("手机号格式错误");
        }

        $uid = $this->decode($uid);
        /* $userInfo = $this->certificationModel->getInfo($uid);
          if ( !$userInfo ) {
          throw new \Exception\ParamsInvalidException("用户不存在");
          } */

        $inviteUser = $this->inviteModel->getInfoByPhone($phone);
        if ($inviteUser) {
            throw new \Exception\ParamsInvalidException("已经被邀请");
        }

        $userLib = new \Lib\User\User();
        $users = $userLib->getUserInfo([], $phone);
        if ($users) {
            throw new \Exception\ParamsInvalidException("已经注册过");
        }

        $id = $this->inviteModel->add($uid, $phone);
        if (!$id) {
            throw new ModelException("保存失败");
        }

        //獲取目前積分
        $userInfo = $userLib->getUserInfo(array($uid), '');
        $currentIntegral = 0;
        if ($userInfo) {
            $currentIntegral = $userInfo[$uid]['u_integral'];
        }

        $this->responseJSON(
                array(
                    'invite_id' => $id,
                    'currentIntegral' => $currentIntegral
        ));
    }

    /** uid 加密
     * @param string $string
     * @param string $skey
     * @return mixed
     */
    private function encode($string = '', $skey = 'zw_zhangwan') {
        $strArr = str_split(base64_encode($string));
        $strCount = count($strArr);
        foreach (str_split($skey) as $key => $value)
            $key < $strCount && $strArr[$key] .= $value;
        return str_replace(array('=', '+', '/'), array('O0O0O', 'o000o', 'oo00o'), join('', $strArr));
    }

    /** 解密
     * @param string $string
     * @param string $skey
     * @return bool|string
     */
    private function decode($string = '', $skey = 'zw_zhangwan') {
        $strArr = str_split(str_replace(array('O0O0O', 'o000o', 'oo00o'), array('=', '+', '/'), $string), 2);
        $strCount = count($strArr);
        foreach (str_split($skey) as $key => $value)
            $key <= $strCount && isset($strArr[$key]) && $strArr[$key][1] === $value && $strArr[$key] = $strArr[$key][0];
        return base64_decode(join('', $strArr));
    }

//    /** 分享商品或头条
//     * @throws ModelException
//     * @throws \Exception\ParamsInvalidException
//     */
//    public function share() {
//        //TODO 暂缓开发
//        $content = app()->request()->params('content');
//
//        if (!$content) {
//            throw new \Exception\ParamsInvalidException("分享内容必须");
//        }
//        $id = $this->inviteModel->add($this->uid, $content);
//        if (!$id) {
//            throw new ModelException("保存失败");
//        }
//        $this->integralService->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_SHARE_ADD);
//        $this->responseJSON(
//                array(
//                    'invite_id' => $id,
//                    'currentIntegral' => $this->integralService->getCurrentUserIntegral()
//        ));
//    }

    /** 新版邀请列表
     * @throws \Exception\ParamsInvalidException
     */
    public function inviteLists() {
        $uid = app()->request()->params('uid', $this->uid);
        if (!$uid) {
            throw new \Exception\ParamsInvalidException("未登录");
        }
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        $params['month'] = app()->request()->params('month',date('Y-m',time()));

        $params['u_id'] = $uid;
        $result = $this->inviteModel->lists($params, $page, $pageSize);
        $count = $num = 0;
        $list = [];

        $userLib = new \Lib\User\User();
        if ($result[1]) {
            $reg_phones=$userLib->queryUserIsRegisterByPhones(array_column($result[0],'uil_phone'));
            foreach ($result[0] as &$info) {
                if ($info['uil_phone']) {
                    $info['uil_phone'] = substr_replace($info['uil_phone'], '****', 3, 4);
                    $info['des'] = in_array($info['uil_phone'],$reg_phones)? '是':'否';
                }
            }
            $count = $result[1];
            $list = $result[0];
        }

        $userInfo = $userLib->getUserInfo(array($uid));
        if ($userInfo){
            $data['u_integral'] = $userInfo[$uid]['u_integral'] ? $userInfo[$uid]['u_integral'] : 0;
        }

        $data['count'] = $count;
        $data['has_register'] = $result[2];
        $data['list'] = $list;

        $this->responseJSON($data);
    }

    /**
     * 批量修改已注册用户的uil_is_register字段
     */
    public function updateStatus(){
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 50);

        $result = $this->inviteModel->lists([], $page, $pageSize);
        if ($result[0]) {
            $userLib = new \Lib\User\User();
            foreach ($result[0] as &$info) {
                if ($info['uil_phone']) {
                    $users = $userLib->getUserInfo([], $info['uil_phone']);
                    if ($users){
                        $this->inviteModel->updateRegisterStatus($info['uil_id']);
                    }
                }
            }
            $res = true;
        }else {
            $res = false;
        }
        $this->responseJSON($res);
    }

    /**
     * 获得用户邀请海报图片
     */
    public function getUserInviteImage()
    {
        //生成二维码文件
        include __DIR__ . '/../../Modules/php-qrcode/phpqrcode.php';

        $base64Image = '';
        $uid = $this->uid;
        $userInfo = (new User())->getUser($uid);
        if ($userInfo) {
            //邀请图片模板路径
            $inviteImageUrl = 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/h5%2FinviteUser.png';
            //邀请链接图片路径
            $baseUrlSchema = config('app.request_url_schema_x_forwarded_proto_default');
            $baseUrlRes = config('app.baseDomain');
            $encodeUid = $this->encode($uid);
            $userInviteUrl = $baseUrlSchema . '://' . $baseUrlRes . '/html/apph5/inviteFriends.html#/invitem?uid=' . $encodeUid;
            $mainImageRes = imagecreatefrompng($inviteImageUrl);

            //将文字写在相应的位置
            if(!empty($userInfo['u_nickname'])) {
                //字体颜色
                $fontColor = imagecolorallocate($mainImageRes, 255, 255, 255);
                $nickname_font_size = 32;
                //字符文件路径
                $fontFile = __DIR__ . "/../../Data/msyh.ttf";
                $inviteTitle = '我是' . $userInfo['u_nickname'];
                $tmpArray = imagettfbbox($nickname_font_size, 0, $fontFile, $inviteTitle);
                imagettftext($mainImageRes, 32, 0, 430 - (empty($tmpArray) ? 200 : $tmpArray[2]) / 2, 170, $fontColor, $fontFile, $inviteTitle);
            }

            //生成用户邀请链接二维码
            $userInviteName = $uid . '.png';
            QRcode::png($userInviteUrl, $userInviteName, QR_ECLEVEL_H, 3, 2);
            list ($imageWidth, $imageHeight) = getimagesize($userInviteName);
            $imageRes = imagecreatefrompng($userInviteName);
            //目标图片资源、源图片资源、目标位置x、目标位置y、源图片x、源图片y、目标图片宽度、目标图片高度、源图片宽度、源图片高度
            @imagecopyresampled($mainImageRes, $imageRes, 296, 240, 0, 0, 262, 262, $imageWidth, $imageHeight);

            //用户头像
            $userAvatarUrl = FileHelper::getFileUrl($userInfo['u_avatar'], 'user_avatar');
            list ($imageWidth, $imageHeight, $imageType) = getimagesize($userAvatarUrl);
            $userAvatarImageRes = $this->createImage($userAvatarUrl, $imageType);
            //目标图片资源、源图片资源、目标位置x、目标位置y、源图片x、源图片y、目标图片宽度、目标图片高度、源图片宽度、源图片高度
            @imagecopyresampled($mainImageRes, $userAvatarImageRes, 396, 340, 0, 0, 62, 62, $imageWidth, $imageHeight);

            ob_start(); // Let's start output buffering.
            imagejpeg($mainImageRes, null, 90); //This will normally output the image, but because of ob_start(), it won't.
            $contents = ob_get_contents(); //Instead, output above is saved to $contents
            ob_end_clean(); //End the output buffer.
            $base64Image = "data:image/jpeg;base64," . base64_encode($contents);

            //销毁图像
            unlink($userInviteName);
            imagedestroy($userAvatarImageRes);
            imagedestroy($imageRes);
            imagedestroy($mainImageRes);
        }
        $this->responseJSON(['base64Image' => $base64Image]);
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
