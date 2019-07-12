<?php

/**
 * 实名认证控制器
 * @author Administrator
 *
 */

namespace Controller\User;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Exception\ModelException;
use Lib\Common\AppMessagePush;


class Certification extends BaseController {

    private $certificationModel = null;

    public function __construct() {
        parent::__construct();
        $this->certificationModel = new \Model\User\Certification();
    }

    /**
     * 机构类型
     */
    public function enterpriseType() {
        $data = [];
        $Enterprise_arr = conf('Enterprise.EnterpriseType');
        foreach ($Enterprise_arr as $key => $val) {
            $data[] = ['id' => $key, 'name' => $val];
        }
        $data_arr['typeList'] = $data;
        $this->responseJSON($data_arr);
    }

    /**
     * 银行类型
     */
    public function BankType() {
        $data = [];
        $Enterprise_arr = conf('Enterprise.BankType');
        foreach ($Enterprise_arr as $key => $val) {
            $data[] = ['id' => $key, 'name' => $val];
        }
        $data_arr['typeList'] = $data;
        $this->responseJSON($data_arr);
    }

    private function getCertIsMatch($name,$cardno)
    {
        return (new \Lib\User\IdcardCertification())->getCertIsMatch($name,$cardno);
    }

    /**
     * 艺术家认证
     * @throws ParamsInvalidException
     * @throws ServiceException
     * @throws ModelException
     */
    public function addArtist() {
        $uid = $this->uid;
        $realName = app()->request()->params('realName');
        $IDNo = app()->request()->params('IDNo');
        $phone = app()->request()->params('phone');
        $celebrity = app()->request()->params('celebrity', '');
        $photoCertificate = app()->request()->params('photoCertificate', '');
        //$photoLicence       = app()->request()->params('photoLicence','');
        //$photoStorefront    = app()->request()->params('photoStorefront','');
        $uce_bankCardNo = '';  //app()->request()->params('bankCardNo');
        $uce_bankCardType = '';  //app()->request()->params('bankCardType');

        if (!$phone) {
            throw new ParamsInvalidException("银行预留手机必须");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        /* if(!$uce_bankCardNo || !$uce_bankCardType){
          throw new ParamsInvalidException("银行卡必须");
          }
          if (! $realName || ! $IDNo) {
          throw new ParamsInvalidException("缺少参数");
          } */
        // if (!$celebrity)
        // {
        //     throw new ParamsInvalidException("选择认证类型");
        // }

        if (!$photoCertificate) {
            $celebrity = 0;
        }

        $userInfo = $this->certificationModel->getInfo($uid);
        if ($userInfo) {
            if ($photoCertificate) {  // 艺术家认证
                if ($userInfo['uce_isCelebrity'] == 1 && $userInfo['uce_status'] == 1) {
                    throw new ServiceException("已认证");
                } else if ($userInfo['uce_isCelebrity'] == 1 && $userInfo['uce_status'] == 0) {
                    throw new ServiceException("认证中");
                }
            } else {
                if ($userInfo['uce_status'] == 1) {
                    throw new ServiceException("已认证");
                }
            }
        }

        $certificationInfo = array($realName, $IDNo, $uce_bankCardNo, $phone);
        $certificationInfo = json_encode($certificationInfo);
        $ctLog = new \Model\User\CertificationLog();
        $errorLogList = $ctLog->getAll(array('u_id' => $uid));
        if ($errorLogList && is_array($errorLogList)) {
            foreach ($errorLogList as $loginfo) {
                if ($loginfo['desc'] == $certificationInfo) {
                    throw new ServiceException($loginfo['errormsg']);
                }
            }
        }

        // 实名认证+银行卡信息验证
        /* $realNameClass = new \realName\realName();
          $ret = $realNameClass->authentication($realName,$IDNo,$uce_bankCardNo,$phone);
          if(!isset($ret['code']) || $ret['code']>0)
          {
          $addLog = array(
          'u_id' => $uid,
          'desc' => $certificationInfo,
          'errormsg' => $ret['desc'],
          'create_time' => date('Y-m-d H:i:s'),
          );
          $ctLog->insert( $addLog );
          throw new ServiceException($ret['desc']);
          } */

        //调用身份认证接口
        $certData = $this->getCertIsMatch($realName,$IDNo);
        if(!$certData['isMatch']) {
            throw new ServiceException($certData['message']);
        }
        //身份证号+姓名 认证通过，直接实名认证通过
        $uce_status=0;
        if($celebrity==0){
            $uce_status=1;
        }

        $id = $this->certificationModel->apply($uid, $realName, $phone, $IDNo, $uce_bankCardNo, $uce_bankCardType, $photoCertificate, $celebrity, $uce_status);
        if (!$id) {
            throw new ModelException("保存实名申请失败");
        }

        //实名认证送积分
        (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_CERTIFICATION_ADD);

        $userLib = new \Lib\User\User();
        $userLib->updateUserInfo($uid, array('realname' => $realName));

        $userMapModel = new \Model\User\Map();
        $mapInfo = ['uce_isCelebrity' => $celebrity];

        $userMapModel->updateUserMap($uid, $mapInfo);

        $configKey = 'user_certification';
        $this->responseJSON(array(
            'certification_id' => $id,
            'photoFrontHandHold' => FileHelper::getFileUrl($photoCertificate, $configKey),
            'IDNo' => $IDNo,
            'uce_isCelebrity' => $celebrity,
            'uce_status' => $uce_status,
            'realName' => $realName
        ));
    }

    /**
     * 添加玩友转换艺术家
     */
    public function addFriendArtist() {
        $uid = $this->uid;

        $celebrity = app()->request()->params('celebrity', 0);
        $photoCertificate = app()->request()->params('photoCertificate', '');

        if (!$celebrity) {
            throw new ParamsInvalidException("选择认证类型");
        }

        $currentStatus = $this->certificationModel->getCertInfo($uid);

        if ($currentStatus['uce_status'] === '0') {
            throw new ServiceException("认证中");
        } else if ($currentStatus['uce_isCelebrity'] > 0 && $currentStatus['uce_status'] === '1') {
            throw new ServiceException("已认证");
        }
        //如果是艺术家  只用判断photoCertificate
        //如果是机构  只用判断photoLicence和photoStorefront

        $id = $this->certificationModel->applyArtist($uid, $photoCertificate, $celebrity);

        if (!$id) {
            throw new ModelException("保存申请失败");
        }
        //实名认证送积分
        (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_CERTIFICATION_ADD);
//        $userLib = new \Lib\User\User();
//        $userLib->updateUserInfo($uid, array('type' => $celebrity));

        $userMapModel = new \Model\User\Map();
        $mapInfo = ['uce_isCelebrity' => $celebrity];

        $userMapModel->updateUserMap($uid, $mapInfo);
        $configKey = 'user_certification';
        $this->responseJSON(array(
            'certification_id' => $id,
            'uce_photoCertificate' => FileHelper::getFileUrl($photoCertificate, $configKey),
            'uce_isCelebrity' => $celebrity,
            'uce_status' => 0,
        ));
    }

    /**
     * 机构认证
     * @throws ParamsInvalidException
     * @throws ServiceException
     * @throws ModelException
     */
    public function addCompany() {
        $uid = $this->uid;
        $realName = app()->request()->params('realName');
        $phone = app()->request()->params('phone');
        $celebrity = app()->request()->params('celebrity', '');
        //$photoCertificate   = app()->request()->params('photoCertificate','');
        $enterpriseName = app()->request()->params('enterpriseName', '');
        $licenceNO = app()->request()->params('licenceNO', '');
        $enterpriseType = app()->request()->params('enterpriseType', 0);
        $address = app()->request()->params('address', '');

        $photoLicence = app()->request()->params('photoLicence', '');
        $photoStorefront = app()->request()->params('photoStorefront', '');
        $uce_bankCardNo = '';  //app()->request()->params('bankCardNo');
        $uce_bankCardType = '';  //app()->request()->params('bankCardType');
        $isNeedPhotoStorefront  = app()->request()->params('isNeedPhotoStorefront', 1);


        if (!$phone) {
            throw new ParamsInvalidException("银行预留手机必须");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        /* if(!$uce_bankCardNo || !$uce_bankCardType){
          throw new ParamsInvalidException("银行卡必须");
          } */
        if (!$realName || !$licenceNO || !$address || !$enterpriseName) {
            throw new ParamsInvalidException("缺少参数");
        }
        $Enterprise_arr = conf('Enterprise.EnterpriseType');
        if (!isset($Enterprise_arr[$enterpriseType])) {
            throw new ParamsInvalidException("选择机构认证类型");
        }
        if (!$celebrity) {
            throw new ParamsInvalidException("选择认证类型");
        }

        if ((!$photoLicence || !$photoStorefront)&&($isNeedPhotoStorefront==1)) {
            throw new ParamsInvalidException("缺少图片");
        }

        //pc官网  只判断 机构证书
        if(!$photoLicence&&($isNeedPhotoStorefront!=1)){
            throw new ParamsInvalidException("缺少图片");
        }


        $certificationInfo = $this->certificationModel->getInfo($uid);
        if ($certificationInfo['uce_isCelebrity'] == 2) {
            if ($certificationInfo['uce_status'] === '0') {
                throw new ServiceException("认证中");
            } else if ($certificationInfo['uce_status'] === '1') {
                throw new ServiceException("已认证");
            }
        }
        //如果是艺术家  只用判断photoCertificate
        //如果是机构  只用判断photoLicence和photoStorefront
        /*
          $realNameClass = new \realName\realName();
          $ret = $realNameClass->authentication($realName,'',$uce_bankCardNo,$phone);

          if(!isset($ret['code']) || $ret['code']>0)
          {
          throw new ServiceException($ret['desc']);
          }
         */
        $id = $this->certificationModel->apply($uid, $realName, $phone, '', $uce_bankCardNo, $uce_bankCardType, '', $celebrity, 0, $enterpriseName, $licenceNO, $enterpriseType, $address, $photoLicence, $photoStorefront);
        if (!$id) {
            throw new ModelException("保存实名申请失败");
        }
        //实名认证送积分
        (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_CERTIFICATION_ADD);
        /*
          $userLib = new \Lib\User\User();

          $userLib->updateUserInfo($uid,array('type'=>$celebrity));
         */
        $userMapModel = new \Model\User\Map();

        $mapInfo = ['uce_isCelebrity' => $celebrity];

        $userMapModel->updateUserMap($uid, $mapInfo);

        $configKey = 'user_certification';

        $this->responseJSON(array(
            'certification_id' => $id,
            'photoLicence' => FileHelper::getFileUrl($photoLicence, $configKey),
            'photoStorefront' => FileHelper::getFileUrl($photoStorefront, $configKey),
            'uce_status' => 0,
            'realName' => $realName
        ));
    }

    /**
     * 上传实名认证图片
     */
    public function uploadCredenticationImages() {
        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 2 * 1024 * 1024;
        $ftpConfigKey = 'user_certification';
        $filesData = FileHelper::uploadFiles($ftpConfigKey, $size, $types);
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

    /**
     * 新增实名认证
     *
     * @throws ModelException
     */
    public function apply() {
        $uid = $this->uid;
        // $uid = '510484725';
        $realName = app()->request()->params('realName');
        $IDNo = app()->request()->params('IDNo');
        $uce_bankCardNo = app()->request()->params('bankCardNo','');
        $uce_bankCardType = app()->request()->params('bankCardType','');
        $phone = app()->request()->params('phone','');
        if (!$realName || !$IDNo) {
            throw new ParamsInvalidException("姓名及证件号必须");
        }
        $currentStatus = $this->certificationModel->getStatus($uid);
        if ($currentStatus === '0') {
            throw new ServiceException("认证中");
        } else if ($currentStatus === '1') {
            throw new ServiceException("已认证");
        }
        //调用身份认证接口
        $certData = $this->getCertIsMatch($realName,$IDNo);
        if(!$certData['isMatch']) {
            throw new ServiceException($certData['message']);
        }
        $id = $this->certificationModel->apply($uid, $realName,$phone, $IDNo,$uce_bankCardNo,$uce_bankCardType, '', 0, 1);
        if (!$id) {
            throw new ModelException("保存实名申请失败");
        }else {
            //推送系统消息
            //AppMessagePush::push($uid, '实名认证通过', "恭喜您已通过实名认证，可以开始销售您的商品，或发布拍卖", [], AppMessagePush::PUSH_TYPE_CERTITICATION_STATUS_CHANGE);
        }
//        $configKey = 'user_certification';
        //实名认证送积分
        (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_CERTIFICATION_ADD);
        $this->responseJSON(array(
            'certification_id' => $id,
            'photoFrontHandHold' => '',
            'photoFront' => '',
            'photoBack' => '',
            'IDNo' => $IDNo,
            'realName' => $realName
        ));
    }

    /**
     * 查询实名认证信息
     *
     * @throws ModelException
     */
    public function query() {
        $uid = $this->uid;
        $info = $this->certificationModel->getCertInfo($uid);
        if (empty($info)) {
            $info = (object) [];
        }
        //print_r($info);exit;
        //$info= '';
        $exists = 0;
        if ($info && is_array($info) && ($info['uce_IDNo'] || $info['uce_status'] == 1 )) {
            $configKey = 'user_certification';
            //$info['photoCertificate']   = ftp_get_visit_url($configKey, $info['uce_photoCertificate']);
            //$info['photoLicence']       = ftp_get_visit_url($configKey, $info['uce_photoLicence']);
            //$info['photoStorefront']    = ftp_get_visit_url($configKey, $info['uce_photoStorefront']);
            $Enterprise_arr = conf('Enterprise.BankType');
            //print_r($Enterprise_arr);exit;
            $info['bankName'] = $Enterprise_arr[$info['uce_bankCardType']];
            $exists = 1;
        }
        $this->responseJSON(array(
            'exists' => $exists,
            'info' => $info
        ));
    }

    /**
     * app消息推送
     *
     * @param unknown $uid
     * @param unknown $type
     *            uce_status,uce_isCelebrity
     * @param string $previewContent
     * @param string $content
     */
    public function appMessagePush() {
        //         $uceStatus = [
        //             0 => '正在实名认证',
        //             1 => '通过实名认证',
        //             2 => '没有通过实名认证'
        //         ];
        //         $uceIsCelebrity = [
        //             1 => '通过名家认证',
        //             2 => '没有通过名家认证'
        //         ];
        //         $greetings = [
        //             0 => '',
        //             1 => '恭喜您',
        //             2 => '很遗憾'
        //         ];
        //         $opDesc = $typeDesc = '';
        //         switch ($type) {
        //             case 'uce_status':
        //                 $typeDesc = "实名认证";
        //                 $opDesc = $uceStatus[$status];
        //                 break;
        //             case 'uce_isCelebrity':
        //                 $typeDesc = "名家认证";
        //                 $opDesc = $uceIsCelebrity[$status];
        //                 break;
        //         }
        //         $messageTitle = "尊敬的{$uName}，{$greetings[$status]}{$opDesc}{$typeDesc}";
        $messageTitle = '尊敬的客户您好，实名认证已经成功!';
        $previewContent = $messageTitle . "，" . date('Y-m-d H:i:s');
        $a = AppMessagePush::push($this->uid, $messageTitle, $previewContent, $previewContent, AppMessagePush::PUSH_TYPE_CERTITICATION_STATUS_CHANGE);
        var_dump($a);
    }

}
