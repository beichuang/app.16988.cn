<?php
namespace Controller\Common;

use Exception\ParamsInvalidException;
use Framework\Helper\OssHelper;
use Lib\Base\BaseController;

class Test extends BaseController
{

    private $adLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->adLib = new \Lib\Mall\Ad();
    }

    public function uploadImages()
    {
        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 2 * 1024 * 1024;
        $ftpConfigKey = 'treasure';
        $fileInfos = \Framework\Helper\File::ftpUploadFileFromPost($ftpConfigKey, 
            function ($fileField)
            {
                return "_" . $fileField . "_" . $this->generateRequestId();
            }, $types, $size);
        $this->responseJSON($fileInfos);
    }
    public function unbindthirdUser()
    {
        $phone=app()->request()->params('phone');
        $third=app()->request()->params('third','WX_MINI_ZWWH_10000');
        if(!preg_match('/^1\d{10}$/',$phone)){
            throw new ParamsInvalidException('手机号格式错误');
        }
        $uid=app('mysqlbxd_user')->fetchColumn("select u_id from `user` where u_phone='{$phone}'");
        if(!$uid){
            throw new ParamsInvalidException('uid不存在');
        }
        $upd=app('mysqlbxd_user')->update('user',[
            'u_wx_unionID'=>''
        ],[
            'u_id'=>$uid
        ]);
        $del=app('mysqlbxd_user')->delete('user_thirdparty_account',[
            'u_id'=>$uid,
            'uta_thirdpartyId'=>$third
        ]);
        echo "upd:{$upd},del:{$del}";
    }

    public function test(){
         $responseData = OssHelper::upload('mall_goods_attr_images', '123', 'https://www.huajia.cc/photo/hall/h1/2.jpg');
         dd($responseData);
    }





}
