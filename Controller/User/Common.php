<?php

/**
 * 用户公共模块，登录注册等
 * @author Administrator
 *
 */

namespace Controller\User;

use Framework\Helper\SessionHelper;
use Framework\Helper\FileHelper;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Lib\Common\SessionKeys;

use Lib\User\UserFrom;
use Lib\User\UserVoucher;
use Lib\WxMini\WXLoginHelper;
use Model\Common\Activity;
use Model\Common\searchWord;
use Model\User\Certification;
use Rest\User\Facade\UserDVipInviteManager;
use Rest\User\Facade\UserManager;

class Common extends BaseController
{

    /**
     * 绑定第三方账号
     */
    public function thirdBind()
    {
        $phone = app()->request()->params('phone');
        $captcha = app()->request()->params('captcha', '');
        if (!$phone) {
            throw new ParamsInvalidException("请输入手机号");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!preg_match("/\d{6}/", $captcha)) {
            throw new ParamsInvalidException("验证码格式错误");
        }
        $userLib = new \Lib\User\User();
        // 检查手机号是否已存在
        $uid = $userLib->queryUserIdByPhone($phone);
        // 如果不存在，注册新用户
        if (!$uid) {
            $password = '';
            //  注册送1000券
            $regRes = $userLib->regNewUser($phone, $captcha, $password,UserFrom::ZW_APP_THIRDBIND);
            if ($regRes->code != 200) {
                throw new ServiceException($regRes->data);
            }
            $userInfo = $regRes->data;
            $uid = $userInfo['u_id'];
            //新用户注册送积分
            (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);
        } else {
            $userLib->checkCaptcha($phone, $captcha);
        }
        $dataStr = app()->request()->params('data', '');
        $sns_info = $this->thirdDataCheck($dataStr);
        $thirdLogin = new \Lib\User\ThirdLoginOAuth();
        $userInfo = $thirdLogin->bindThirdUser($uid, $sns_info);
        if (!$userInfo) {
            throw new ServiceException("绑定失败");
        }
        $this->regSession($userInfo);
        SessionHelper::sessionRegenerateId();
        $this->responseJSON($this->parseUserInfo($userInfo));
    }

    /**
     * 第三方登录
     *
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function thirdLogin()
    {
        $dataStr = app()->request()->params('data', '');
        $sns_info = $this->thirdDataCheck($dataStr);
        $thirdLogin = new \Lib\User\ThirdLoginOAuth();
        $userInfo = $thirdLogin->queryThirdSnsInfo($sns_info);
        if (!$userInfo) {
            throw new ServiceException("登录失败");
        }
        $this->regSession($userInfo);
        SessionHelper::sessionRegenerateId();
        $this->responseJSON($this->parseUserInfo($userInfo));
    }

    /**
     * 检查第三方sns数据格式
     * @param unknown $dataStr
     * @throws ParamsInvalidException
     * @return multitype:unknown mixed
     */
    private function thirdDataCheck($dataStr)
    {
        if (!$dataStr) {
            throw new ParamsInvalidException("缺少参数");
        }
        $data = json_decode($dataStr, true);
        if (json_last_error()) {
            throw new ParamsInvalidException("数据格式错误");
        }
        if (!$data || !is_array($data) || empty($data)) {
            throw new ParamsInvalidException("参数错误");
        }
        if (!isset($data['openid']) || !isset($data['nick']) || !isset($data['channel']) || !isset($data['gender']) ||
            !isset($data['avatar']) || !$data['openid'] || !$data['nick'] || !$data['channel'] || !$data['gender']) {
            throw new ParamsInvalidException("缺少参数");
        }
        $sns_info = [
            'openid' => $data['openid'],
            'channel' => $data['channel'],
            'nick' => $data['nick'],
            'gender' => $data['gender'],
            'avatar' => $data['avatar'],
            'unionid' => $data['unionid']
        ];
        return $sns_info;
    }

    /**
     * 用户登录
     *
     * @throws ServiceException
     */
    public function login()
    {
        $request = app()->request();
        $phone = $request->params('phone');
        $password = $request->params('password', '');
        $captcha = $request->params('captcha', '');
        //首次登录来源
        $channel = $request->params('channel', '');
        //用户来源字段
        $userFrom = $request->params('userFrom', '');
        //小程序Login code
        $mpLoginCode = $request->params('mpLoginCode', '');


        //0元活动  前部逻辑 参数验证
        if(app()->request()->params('zeroMoney','')){
            $insert_arr['name']             =  app()->request()->params('name','');
            $insert_arr['phone']            = $phone?$phone:'';
            $insert_arr['receive_address'] =  app()->request()->params('receive_address','');
            if(!$insert_arr['name']||!$insert_arr['phone']||!$insert_arr['receive_address']){
                throw new ServiceException("参数缺省(name,phone,receive_address)");
            }
        }
        //520活动（送地铁卡）
        if(app()->request()->params('sw520','')){
            $insert_arr['name']             =  app()->request()->params('name','');
            $insert_arr['phone']            = $phone?$phone:'';
            $insert_arr['receive_address'] =  app()->request()->params('receive_address','');
            if(!$insert_arr['name']||!$insert_arr['phone']||!$insert_arr['receive_address']){
                throw new ServiceException("参数缺省(name,phone,receive_address)");
            }
        }

        if(!$phone || ! preg_match('/1\d{10}/',$phone)){
            throw new ServiceException("手机号格式错误");
        }
        $retryTimes=app('mysqlbxd_app')->fetchColumn('select count(*) c from user_login_retry_log where `l_phone`=:l_phone and l_time>:l_time',[
            'l_time'=>date('Y-m-d H:i:s',strtotime("- 60 seconds")),
            'l_phone'=>$phone
        ]);
        if($retryTimes>2){
            throw new ServiceException("请稍候再试");
        }
        $userInfo = array();
        $userLib = new \Lib\User\User();

        $loginRes = $userLib->userLogin($phone, $captcha, $password, $channel);
        if ($loginRes->code == 8009 && $captcha) {
            $userFrom = $userFrom ? $userFrom : UserFrom::CAPTCHA_LOGIN_AUTO_REGISTER;
            $regRes = $userLib->regNewUser($phone, $captcha,'',$userFrom);
            if ($regRes->code != 200) {
                throw new ServiceException($regRes->data);
            }
            $userInfo = $regRes->data;
            $showList = $this->parseUserInfo($userInfo);
            $showList['newRegister'] = 1;
            //检测注册用户是否是邀请过来的
            $inviteModel = new \Model\User\Invite();
            $inviteUser = $inviteModel->getInfoByPhone($phone);
            if ($inviteUser) {
                //检测注册用户是否是邀请过来的
                $this->registerNewUserInviteProc($inviteUser['u_id'], $phone);
            }
            //新用户注册成功赠送积分
            (new \Lib\User\UserIntegral())->addIntegral($userInfo['u_id'],\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);
            $showList['u_integral']=\Lib\User\UserIntegral::$integralValue[\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER][0];

        }else if($loginRes->code == 200){
            $userInfo = $loginRes->data;
            $showList = $this->parseUserInfo($userInfo);
            $showList['newRegister'] = 0;
            //识别用户身份，如果为APP登录则推送问候消息，否则不推送
            $appVersion = app()->request->headers('appVersion');
            if (!empty($appVersion)) {
                $aliIm = new \AliIm\AliIm();
                $im_id = $showList['ue_imId'];
                $imIds = ["{$im_id}"];
                $aliIm->sendImMsgPush([], $imIds,
                    "客官多日不见，小女子这厢有礼了~店内上新了众多好物，您且慢慢欣赏。为答谢客官厚爱，特推出以下活动，邀请5个新人注册即送精美花插~https://app.16988.cn/html/invite.html");
            }
        }else{
            app('mysqlbxd_app')->insert('user_login_retry_log',[
                'l_time'=>date('Y-m-d H:i:s'),
                'l_phone'=>$phone
            ]);
            throw new ServiceException($loginRes->data);
        }

        if($mpLoginCode) {
            //处理小程序登录后，将openid存入session
            $wxLoginHelper = new \Lib\WxMini\WXLoginHelper('wx_mini_yszz');
            $wxLoginHelper->checkLogin($mpLoginCode);
        }

        app('mysqlbxd_app')->delete('user_login_retry_log',[
            'l_phone'=>$phone
        ]);
        $this->regSession($userInfo);
        SessionHelper::sessionRegenerateId();
        //如果是保险岛APP过来的用户，并且是第一次登陆，发送代金券
        if ($userInfo['u_from'] == UserFrom::BXD_APP && $userInfo['u_createDate'] == $userInfo['u_updateDate'] && empty($password)) {
            $showList['newRegister'] = 1;
            UserVoucher::sendVoucherUserRegisterBxd($userInfo['u_id']);
        }
        // 数据插入逻辑 （0元体验官活动）
        if(app()->request()->params('zeroMoney','')){
            //  生成随机验证码
            $insert_arr['code']             =  $this->generateCode();
            //  生成uid
            $insert_arr['uid']              =  isset($showList['u_id'])&&$showList['u_id']?$showList['u_id']:'';
//            //  生成openid
//            $insert_arr['openid']           =  '';
            if(!$insert_arr['uid']){
                throw new ServiceException("uid不能为空");
            }
            app('mysqlbxd_app')->insert('activity_zero',$insert_arr);
        }
        //520 送地铁卡数据录入逻辑
        if(app()->request()->params('sw520','')){
            //  生成随机验证码
            $insert_arr['code']             =  $this->generateCode('activity_sw2019520');
            //  生成uid
            $insert_arr['uid']              =  isset($showList['u_id'])&&$showList['u_id']?$showList['u_id']:'';
            if(!$insert_arr['uid']){
                throw new ServiceException("uid不能为空");
            }
            app('mysqlbxd_app')->insert('activity_sw2019520',$insert_arr);
        }

        $this->responseJSON($showList);
    }


  // 用户登录（多数针对 扩展第三方活动）
    public function activityLogin()
    {
        $allParams = app()->request()->params();
        $request = app()->request();
        $phone = $request->params('phone');
        $password = $request->params('password', '');
        $captcha = $request->params('captcha', '');
        //首次登录来源
        $channel = $request->params('channel', '');
        //用户来源字段  （默认是活动来源）
        $userFrom = $request->params('userFrom', 22);
        //小程序Login code
        $mpLoginCode = $request->params('mpLoginCode', '');
        $activityCode = $request->params('activityCode', '');
        if (!$activityCode) {
            throw new ServiceException('活动码不能为空');
        }
        switch ($activityCode) {
            // 书法预报名活动
            case 1:
                $name = $request->params('name', '');
                Activity::handwritingSign(['phone' => $phone, 'name' => $name]);
                $showList = $this->userLoginLogic($phone, $password, $captcha, $channel, $userFrom, $mpLoginCode);
                $this->responseJSON($showList);
                break;
            //书画艺术大赛报名
            case 2:
                $showList = $this->userLoginLogic($phone, $password, $captcha, $channel, $userFrom, $mpLoginCode);
                //用户同步
                $allParams['u_id'] = $showList['u_id'];
                $result = Activity::handwriting_match_enroll($allParams);
                if (!$result) {
                    throw new ServiceException('报名失败,请重试！');
                }
                //发送优惠券
                $redis = app('redis');
                if (!$redis->get('handwriting_activity_template_id')) {
                    $templateIds = app('mysqlbxd_mall_common')->fetchColumn("select svalue from setting where skey='handwriting_activity_template_id'");
                    $templateIds = explode(',', $templateIds);
                    $redis->set('handwriting_activity_template_id', json_encode($templateIds));
                } else {
                    $templateIds = json_decode($redis->get('handwriting_activity_template_id'), true);
                }
                $send_result = UserVoucher::sendVoucherPublic($this->uid, [$templateIds[2]], 'one');
                $this->responseJSON($showList);
                break;
            default:
                throw new ServiceException('没有对应的活动码！');
                break;
        }
    }




  //用户登录核心逻辑
  private function userLoginLogic($phone,$password,$captcha,$channel,$userFrom,$mpLoginCode){
   if(!$phone || ! preg_match('/1\d{10}/',$phone)){
       throw new ServiceException("手机号格式错误");
   }
      $retryTimes=app('mysqlbxd_app')->fetchColumn('select count(*) c from user_login_retry_log where `l_phone`=:l_phone and l_time>:l_time',[
          'l_time'=>date('Y-m-d H:i:s',strtotime("- 60 seconds")),
          'l_phone'=>$phone
      ]);
      if($retryTimes>2){
          throw new ServiceException("请稍候再试");
      }
      $userLib = new \Lib\User\User();
      $loginRes = $userLib->userLogin($phone, $captcha, $password, $channel);
      if ($loginRes->code == 8009 && $captcha) {
          $userFrom = $userFrom ? $userFrom : UserFrom::CAPTCHA_LOGIN_AUTO_REGISTER;
          $regRes = $userLib->regNewUser($phone, $captcha,'',$userFrom);
          if ($regRes->code != 200) {
              throw new ServiceException($regRes->data);
          }
          $userInfo = $regRes->data;
          $showList = $this->parseUserInfo($userInfo);
          $showList['newRegister'] = 1;
          //检测注册用户是否是邀请过来的
          $inviteModel = new \Model\User\Invite();
          $inviteUser = $inviteModel->getInfoByPhone($phone);
          if ($inviteUser) {
              //检测注册用户是否是邀请过来的
              $this->registerNewUserInviteProc($inviteUser['u_id'], $phone);
          }
          //新用户注册成功赠送积分
          (new \Lib\User\UserIntegral())->addIntegral($userInfo['u_id'],\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);
          $showList['u_integral']=\Lib\User\UserIntegral::$integralValue[\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER][0];

      }else if($loginRes->code == 200){
          $userInfo = $loginRes->data;
          $showList = $this->parseUserInfo($userInfo);
          $showList['newRegister'] = 0;
          //识别用户身份，如果为APP登录则推送问候消息，否则不推送
          $appVersion = app()->request->headers('appVersion');
          if (!empty($appVersion)) {
              $aliIm = new \AliIm\AliIm();
              $im_id = $showList['ue_imId'];
              $imIds = ["{$im_id}"];
              $aliIm->sendImMsgPush([], $imIds,
                  "客官多日不见，小女子这厢有礼了~店内上新了众多好物，您且慢慢欣赏。为答谢客官厚爱，特推出以下活动，邀请5个新人注册即送精美花插~https://app.16988.cn/html/invite.html");
          }
      }else{
          app('mysqlbxd_app')->insert('user_login_retry_log',[
              'l_time'=>date('Y-m-d H:i:s'),
              'l_phone'=>$phone
          ]);
          throw new ServiceException($loginRes->data);
      }

      if($mpLoginCode) {
          //处理小程序登录后，将openid存入session
          $wxLoginHelper = new \Lib\WxMini\WXLoginHelper('wx_mini_yszz');
          $wxLoginHelper->checkLogin($mpLoginCode);
      }

      app('mysqlbxd_app')->delete('user_login_retry_log',[
          'l_phone'=>$phone
      ]);
      $this->regSession($userInfo);
      SessionHelper::sessionRegenerateId();
      //如果是保险岛APP过来的用户，并且是第一次登陆，发送代金券
      if ($userInfo['u_from'] == UserFrom::BXD_APP && $userInfo['u_createDate'] == $userInfo['u_updateDate'] && empty($password)) {
          $showList['newRegister'] = 1;
          UserVoucher::sendVoucherUserRegisterBxd($userInfo['u_id']);
      }

      return $showList;
  }













 //-----------------------招募0元体验活动------------
//    private function insertZero($params){
//        app('mysqlbxd_app')->insert('activity_zero',$params);
//    }
    //生成随机码函数
    public function generateCode($table='activity_sw2019520'){
        $randCode = '';
        for ($i=1;$i>0;$i++){
            $randCode  = $this->checkCode(8,$table);
            //生成唯一code 跳出循环
            if($randCode){
                 break;
            }
        }
       return $randCode;
    }

    //检测code是否生成
    private function checkCode($length=8,$table='activity_sw2019520'){
        $randCode =  getRandChar($length);
        $sql  = "select code from {$table} where code = :code ";
        $code=app('mysqlbxd_app')->fetch($sql,['code'=>$randCode]);
        if($code){
            $randCode =  '';
        }
        return $randCode;
    }

    //判断是否参加过活动
    public function isHaveJoin(){
        //活动名称
        $activity  =  app()->request()->params('activity', 'activity_zero');
        $phone  =  app()->request()->params('phone', '');
        if(!trim($activity)){
            throw new ServiceException("请上传活动名称");
        }
        if(!$phone){
            throw new ServiceException("手机号不能为空");
        }
        $sql  = "select `name`,phone,receive_address,code from {$activity} where  ";
        $condition_arr = [];
        $condition = '';
        if($phone){
            $condition                .= '  phone=:phone  ';
            $condition_arr['phone']   =  $phone;
        }
        $sql .= $condition;
        $user=app('mysqlbxd_app')->fetch($sql,$condition_arr);
        $endData  = ['type'=>$user?1:0,'user'=>$user?$user:''];
        $this->responseJSON($endData);
    }
    //-----------------------招募0元体验活动end------------




    /**
     * 微信公众号注册或绑定手机
     * 将用户的公众号openid和对应的账户建立绑定关系
     * 如果账户不存在，则创建账户后绑定
     *
     * @throws ServiceException
     */
    public function wxMpReg()
    {
        $phone = app()->request()->params('phone');
        $mpOpenid = app()->request()->params('mpOpenid', '');
        $captcha = app()->request()->params('captcha', '');
        if(!$phone || ! preg_match('/1\d{10}/',$phone)){
            throw new ServiceException("手机号格式错误");
        }
        if(!$mpOpenid){
            throw new ServiceException("mpOpenid必须");
        }
        $userInfo = array();
        $userLib = new \Lib\User\User();
        $loginRes = $userLib->userLogin($phone, $captcha, '', '');
        if ($loginRes->code == 8009 && $captcha) {
            $regRes = $userLib->regNewUser($phone, $captcha,'',UserFrom::WXMP_H5);
            if ($regRes->code != 200) {
                throw new ServiceException($regRes->data);
            }
            $userInfo = $regRes->data;
            //新用户注册成功赠送积分
            (new \Lib\User\UserIntegral())->addIntegral($userInfo['u_id'],\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);

        }else if($loginRes->code == 200){
            $userInfo = $loginRes->data;
        }else{
            throw new ServiceException($loginRes->data);
        }

        $isBindMobile=app('mysqlbxd_user')->fetchColumn("select count(*) from user_thirdparty_account where uta_thirdpartyId='WX_MP_10000' and uta_thirdpartyAccountId='{$mpOpenid}' and uta_status=0");
        if($isBindMobile==0){
            $bindData=[
                'uid'=>$userInfo['u_id'],
                'thirdpartyId'=>'WX_MP_10000',
                'thirdpartyAccountId'=>$mpOpenid,
            ];
            $wxUser=WxHelper::getUserInfo(WxHelper::getAccessToken(),$mpOpenid);
            if(isset($wxUser['unionid']) && $wxUser['unionid']){
                $bindData['wxunionid']=$wxUser['unionid'];
            }
            api_request($bindData,'user/thirdparty/bind');
        }
        $this->regSession($userInfo);
        SessionHelper::sessionRegenerateId();
        $this->responseJSON('OK');
    }

    /**
     * 用户获取验证码
     *
     * @throws ServiceException
     */
    public function getCaptcha()
    {
        //加密验证
        $sign = app()->request()->params('sign');
        if (!$sign) {
            throw new ParamsInvalidException("请更新最新版本~~");
        }
        $params = app()->request()->params();
        unset($params['sign']);
        $params['appsecret'] = config('app.private_key');
        ksort($params);
        $arr = [];
        foreach ($params as $key => $value) {
            $arr[] = $key . '=' . $value;
        }
        $mySign = md5(implode('&', $arr));
        if (strtolower($sign) !== $mySign) {
            throw new ParamsInvalidException("参数错误");
        }
        if(!$params['timeStamp']){
            throw new ParamsInvalidException("参数错误");
        }
        if(abs(time()-intval(preg_replace('/^(\d+)\d{3}$/','$1',$params['timeStamp'])))>1500){
            throw new ParamsInvalidException("当前设备的时间和服务器相差过大");
        }
        $phone = app()->request()->params('phone');
        $type = app()->request()->params('type', 0);
        if (!$phone) {
            $phone=SessionHelper::get(SessionKeys::USER_PHONE);
            if(!$phone){
                throw new ParamsInvalidException("请输入手机号");
            }
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!$type && $type !== '0') {
            if (!SessionHelper::exists(SessionKeys::USER_ID)) {
                throw new ServiceException("需要先登录");
            }
        }

        $userLib = new \Lib\User\User();

        $phoneExist = app()->request()->params('phoneExist', 0);
        if ($phoneExist != 1) {
            if ($type == 3) {
                $uid = $userLib->queryUserIdByPhone($phone);
                if ($uid) {
                    throw new ServiceException("该手机号码已经注册，请返回登陆");
                }
            } elseif ($type == 2) {
                $uid = $userLib->queryUserIdByPhone($phone);
                if (!$uid) {
                    throw new ServiceException("该手机号尚未注册");
                }
            }
        }

        $isOk = $userLib->sendCaptcha($phone, $type);
        $this->responseJSON($isOk);
    }

    /**
     * @Summary :发送模板消息验证码
     * @Author yyb update at 2018/6/7 17:11
     */
    public function sendCaptchaInfo()
    {

        $phone = app()->request()->params('phone');
        $type = app()->request()->params('type', 0);
        $param = app()->request()->params('product', 0);

        $userLib = new \Lib\User\User();

        $isOk = $userLib->sendCaptchaInfo($phone, $type, $param);
        $this->responseJSON($isOk);
    }

    /**
     * 用户获取验证码
     *
     * @throws ServiceException
     */
    public function getPayCaptcha()
    {
        //加密验证
        $sign = app()->request()->params('sign');
        if (!$sign) {
            throw new ParamsInvalidException("请更新最新版本~~");
        }
        $params = app()->request()->params();
        unset($params['sign']);
        $params['appsecret'] = config('app.private_key');
        ksort($params);
        $arr = [];
        foreach ($params as $key => $value) {
            $arr[] = $key . '=' . $value;
        }
        $mySign = md5(implode('&', $arr));
        if (strtolower($sign) !== $mySign) {
            throw new ParamsInvalidException("参数错误");
        }

        if(!$params['timeStamp']){
            throw new ParamsInvalidException("参数错误");
        }
        if(abs(time()-intval(preg_replace('/^(\d+)\d{3}$/','$1',$params['timeStamp'])))>300){
            throw new ParamsInvalidException("当前设备的时间和服务器相差过大");
        }

        if (!$this->uid) {
            throw new ParamsInvalidException("请先登录");
        }
        $userLib = new \Lib\User\User();
        $userInfo = current($userLib->getUserInfo([$this->uid]));
        if (!$userInfo) {
            throw new ServiceException("用户信息出错");
        }

        $phone = $userInfo['u_phone'];
        $type = app()->request()->params('type', 4);  //验证码类型:0无，1登录，2找回密码，3注册，4设置支付密码，5忘记支付密码.默认0
        if (!$phone) {
            throw new ParamsInvalidException("请输入手机号");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!$type && $type !== '0') {
            if (!SessionHelper::exists(SessionKeys::USER_ID)) {
                throw new ServiceException("需要先登录");
            }
        }

        $phoneExist = app()->request()->params('phoneExist', 0);
        if ($phoneExist != 1) {
            if ($type == 5) {
                $name = app()->request()->params('name');
                $IDCard = app()->request()->params('IDCard');
                if (empty($name) || empty($IDCard)) {
                    throw new ParamsInvalidException("验证参数不全");
                }

                $certificationModel = new \Model\User\Certification();
                $info = $certificationModel->getCertInfo($this->uid);
                if (!(($info['uce_status'] == 1) && ($info['uce_realName'] == $name) && ($info['uce_IDNo'] == $IDCard))) {
                    throw new ParamsInvalidException("信息验证失败");
                }
            }
        }

        $isOk = $userLib->sendPayPwdCaptcha($phone, $type);
        $this->responseJSON($isOk);
    }

    /**
     * 检查验证码
     *
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function checkCaptcha()
    {
        $captcha = app()->request()->params('captcha');
        $type = app()->request()->params('type', 0);
        $phone = SessionHelper::get(SessionKeys::USER_PHONE);
        if (!$phone) {
            throw new ParamsInvalidException("没有查询到用户手机号，重新登陆后再试");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!$captcha) {
            throw new ParamsInvalidException("验证码必须");
        }
        if (!SessionHelper::exists(SessionKeys::USER_ID)) {
            throw new ServiceException("需要先登录");
        }
        $userLib = new \Lib\User\User();
        $isOk = $userLib->checkCaptcha($phone, $captcha, $type);
        $this->responseJSON($isOk);
    }

    /**
     * 用户退出登录
     *
     * @throws ServiceException
     */
    public function logout()
    {
        if (!SessionHelper::exists(SessionKeys::USER_ID)) {
            throw new ServiceException("未登录");
        }
        $this->destroySession();
        $this->responseJSON(true);
    }

    /**
     * 注册新用户
     *
     * @throws ServiceException
     */
    public function register()
    {
        $phone = app()->request()->params('phone');
        $password = app()->request()->params('password', '');
        $captcha = app()->request()->params('captcha', '');
        $from = app()->request()->params('from', UserFrom::ZW_APP_REGISTER);
        if(!$from || !in_array($from,array_keys(UserFrom::$USER_FROM))){
            $from='';
        }
        $userLib = new \Lib\User\User();
        $regRes = $userLib->regNewUser($phone, $captcha, $password,$from);
        if ($regRes->code != 200) {
            throw new ServiceException($regRes->data);
        }
        $userInfo = $regRes->data;
        $this->regSession($userInfo);
        //新用户注册送积分
        (new \Lib\User\UserIntegral())->addIntegral($userInfo['u_id'],\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);

        //活动页邀请，送邀请人100优惠券
        if($from==UserFrom::ACTIVITY_H5_2){
            //推荐人
            $recommender = app()->request()->params('recommender', '');
            if($recommender && is_numeric($recommender) && app('mysqlbxd_user')->fetchColumn("select count(*) c from `user` where u_id=:u_id",['u_id'=>$recommender])){
                //写邀请记录
                $someoneInvited=app('mysqlbxd_app')->fetchColumn("select u_id from `user_invite_log` where uil_phone=:uil_phone and uil_is_register=:uil_is_register",['uil_is_register'=>1,'uil_phone'=>$phone]);
                if($someoneInvited){
                    wlog([
                        'register/recommender：错误，已注册',
                        '$phone'=>$phone,
                        '$password'=>$password,
                        '$from'=>$from,
                        '$recommender'=>$recommender,
                    ]);
                }else{
                    \Lib\User\UserVoucher::sendVoucherUserRecommender($userInfo['u_id'],$recommender);
                    $date=date('Y-m-d H:i:s');
                    app('mysqlbxd_app')->insert('user_invite_log',[
                        'u_id'=>$recommender,
                        'uil_desc'=>'活动页邀请，送邀请人100优惠券',
                        'uil_targetPlatform'=>4,
                        'uil_time'=>$date,
                        'uil_phone'=>$phone,
                        'uil_is_register'=>1,
                        'uil_registerDate'=>$date,
                        'uil_uid'=>$userInfo['u_id'],
                    ]);
                }
            }
        }
        $this->responseJSON($this->parseUserInfo($userInfo));
    }
    /**
     * 新用户注册，处理用户邀请
     * @param $shareUid 邀请人uid
     * @param $phone 注册人的手机号
     * @throws \Exception
     */
    private function registerNewUserInviteProc($shareUid,$phone)
    {
        //检测注册用户是否是邀请过来的
        $inviteModel = new \Model\User\Invite();
        if($shareUid) {
            $currentTime = date('Y-m-d H:i:s');
            $inviteData = [
                'u_id' => $shareUid,
                'uil_phone' => $phone,
                'uil_desc' => "分享邀请",
                'uil_targetPlatform' => 1,
                'uil_time' => $currentTime,
                'uil_is_register' => 1,
                'uil_registerDate' => $currentTime
            ];
            $inviteModel->insert($inviteData);
            //邀请人赠送积分
            (new \Lib\User\UserIntegral())->addIntegral($shareUid,\Lib\User\UserIntegral::ACTIVITY_INVITE_ADD);
        }else {
            $inviteUser = $inviteModel->getInfoByPhone($phone);
            if ($inviteUser) {
                //将邀请记录的uil_is_register字段改为1
                $inviteModel->updateRegisterStatus($inviteUser['uil_id']);
                //赠送邀请人积分
                (new \Lib\User\UserIntegral())->addIntegral($inviteUser['u_id'],\Lib\User\UserIntegral::ACTIVITY_INVITE_ADD);
            }
        }
    }

    /**
     * 更新用户所有信息（万能接口）
     *
     * @throws ServiceException
     */
    public function updateInfoAll()
    {
        //$data = [];

        $params = app()->request()->params();

        $provinceCode = app()->request()->params('provinceCode', '0');
        $cityCode = app()->request()->params('cityCode', '0');
        $areaCode = app()->request()->params('areaCode', '0');

        $hobby = app()->request()->params('hobby', '');
        $uid = $this->uid;

        if ($provinceCode) {
            $params['provinceCode'] = $provinceCode;
            $params['cityCode'] = $cityCode;
            $params['areaCode'] = $areaCode;
        }
        //print_r($params);exit;
        $userLib = new \Lib\User\User();

        if ($_FILES && $_FILES['file']) {
            $targetFile = $fileExt = '';
            $allowTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/x-png' => 'png'
            ];
            foreach ($_FILES as $upFile) {
                if ($upFile['error']) {
                    throw new ParamsInvalidException("上传失败[{$upFile['error']}]");
                }
                $fileSize = $upFile['size'];
                $targetFile = $upFile['tmp_name'];
                if (!in_array($upFile['type'], array_keys($allowTypes))) {
                    throw new ParamsInvalidException("只支持jpg、png格式的图片");
                }
                $fileExt = $allowTypes[$upFile['type']];
                if ($fileSize > (1024 * 1024 * 2)) {
                    throw new ParamsInvalidException("上传头像大于2MB");
                }
                break;
            }
            if (!$targetFile || !$fileExt) {
                throw new ParamsInvalidException("上传错误");
            }

            $updRes = $userLib->updateAvatar($uid, $targetFile, $fileExt);

            //$this->updateSessionUserInfo();
            //$_SESSION[SessionKeys::USER_AVATAR];
        }

        if (isset($hobby) && $hobby) {
            $goodsCategoryLib = new \Lib\Mall\GoodsCategory();

            $hobby_arr = json_decode($hobby);
            $hobby_cid = implode(',', $hobby_arr);

            $hobby_cid_arr = ['id' => 0, 'cid' => $hobby_cid];

            $resMall = $goodsCategoryLib->getHobbyCategoryList($hobby_cid_arr);

            if (!$resMall) {
                throw new ParamsInvalidException("选择喜好有误");
            }
            if (count($hobby_arr) != count($resMall)) {
                throw new ParamsInvalidException("参数错误");
            }

            $settingModel = new \Model\User\Setting();
            //{"hobby":"a"}
            $arr['hobby'] = $hobby_cid;

            $settingModel->settingSets($this->uid, $arr);

            unset($params['hobby']);

            //$data['hobby'] = $resMall;
        }
        if ($params) {
            $updRes = $userLib->updateUserInfo($uid, $params);
            if ($updRes->code != 200) {
                throw new ServiceException($updRes->data, $updRes->code);
            }
            if ($provinceCode) {
                $certificationModel = new \Model\User\Certification();
                $certificationModel->saveRegion($uid, $provinceCode, $cityCode, $areaCode);
            }
        }
        $this->updateSessionUserInfo();

        $data = true;

        $this->responseJSON($data);
    }

    /**
     * 更新用户信息
     *
     * @throws ServiceException
     */
    public function updateInfo()
    {
        $params = app()->request()->params();
        $provinceCode = app()->request()->params('provinceCode', '0');
        $cityCode = app()->request()->params('cityCode', '0');
        $areaCode = app()->request()->params('areaCode', '0');
        $uid = $this->uid;
        if ($provinceCode) {
            $params['provinceCode'] = $provinceCode;
            $params['cityCode'] = $cityCode;
            $params['areaCode'] = $areaCode;
        }

        $userLib = new \Lib\User\User();
        $updRes = $userLib->updateUserInfo($uid, $params);
        if ($updRes->code != 200) {
            throw new ServiceException($updRes->data, $updRes->code);
        }
        if ($provinceCode) {
            $certificationModel = new \Model\User\Certification();
            $certificationModel->saveRegion($uid, $provinceCode, $cityCode, $areaCode);
        }
        $this->updateSessionUserInfo();
        $this->responseJSON(true);
    }

    /**
     * 修改头像
     *
     * @throws ServiceException
     */
    public function updateAvatar()
    {
        $uid = $this->uid;
        // $uid=510484725;
        if (!$_FILES) {
            throw new ParamsInvalidException("没有上传");
        }
        $targetFile = $fileExt = '';
        $allowTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        foreach ($_FILES as $upFile) {
            if ($upFile['error']) {
                throw new ParamsInvalidException("上传失败[{$upFile['error']}]");
            }
            $fileSize = $upFile['size'];
            $targetFile = $upFile['tmp_name'];
            if (!in_array($upFile['type'], array_keys($allowTypes))) {
                throw new ParamsInvalidException("只支持jpg、png格式的图片");
            }
            $fileExt = $allowTypes[$upFile['type']];
            if ($fileSize > (1024 * 1024 * 40)) {
                throw new ParamsInvalidException("上传头像大于5MB");
            }
            break;
        }
        if (!$targetFile || !$fileExt) {
            throw new ParamsInvalidException("上传错误");
        }
        $userLib = new \Lib\User\User();
        $updRes = $userLib->updateAvatar($uid, $targetFile, $fileExt);
        $this->updateSessionUserInfo();
        $this->responseJSON([
            'userAvatar' => SessionHelper::get(SessionKeys::USER_AVATAR)
        ]);
    }

    /**
     * 通过手机验证码找回密码
     *
     * @throws ServiceException
     */
    public function findPasswordByPhone()
    {
        $phone = app()->request()->params('phone');
        $captcha = app()->request()->params('captcha', '');
        $password = app()->request()->params('password', 0);
        if (!$phone || !$captcha || !$password) {
            throw new ParamsInvalidException("缺少参数");
        }
        $this->passwordFiltration($password);

        $userLib = new \Lib\User\User();

        $uid = $userLib->queryUserIdByPhone($phone);
        if (!$uid) {
            throw new ParamsInvalidException("该手机号尚未注册");
        }

        $updRes = $userLib->findPasswordByCaptcha($phone, $captcha, $password);
        if ($updRes->code != 200) {
            throw new ServiceException($updRes->data);
        }
        $this->responseJSON(true);
    }

    /**
     * 我的个人信息
     */
    public function myInfo()
    {
        $uid=$this->uid;
        $userLib = new \Lib\User\User();
        $userInfo = current($userLib->getUserInfo(['uid' => $uid],'',1));

        $voucher_count = 0;
//        $is_receive_voucher = 0;  //1=新用户；0=老用户
        if ($userInfo) {
            $uid = $userInfo['u_id'];
            //$userExtend = new \Model\User\Extend();
            //$userExtend->change($this->uid);
            $inviteTodayCount = $this->getTodayInviteTime($uid);
            if ($inviteTodayCount['count'] < 1) {

            }
            $voucherLib = new \Lib\Mall\Voucher();
            $voucher_lists1 = $voucherLib->lists(['uid' => $uid, 'status' => 0]);
            if ($voucher_lists1) {
                $voucher_count = $voucher_lists1['count'];
            }
        }
        $this->regSession($userInfo);
        $json = $this->parseUserInfo($userInfo);
        $json['isSign'] = ($inviteTodayCount['count'] < 1) ? "0" : "1";   //0=没有签到，1=签到了
        $json['voucher_count'] = $voucher_count;   //可用优惠券数量
        $json['is_receive_voucher'] = $voucher_count>0?1:0;   //新注册用户是否领取代金券
        //新注册用户1000券是否已领
        $json['is_receive_voucher_1000']=$this->isReceiveVoucher1000($uid);
        $settingModel = new \Model\User\Setting(); //店铺简介
        $json['u_store_des'] = $settingModel->settingGetValue($uid, 'u_store_des');

        $wallet = new \Model\Pay\Wallet();
        $walletInfo = $wallet->getWallet($uid);
        $json['w_d_balance'] = ($walletInfo['w_d_balance'] + $walletInfo['w_d_freezing']) ? ($walletInfo['w_d_balance'] + $walletInfo['w_d_freezing']) : 0;
        $json['w_d_freezing'] = $walletInfo['w_d_freezing'] ? $walletInfo['w_d_freezing'] : 0;
        $this->responseJSON($json);
    }

    /**
     * 获取用户身份信息
     */
    public function getMyIdentityInfo()
    {
        $data = [];
        $uid = $this->uid;
        $userInfo = UserManager::getUserInfoByUid($uid);
        if ($userInfo) {
            //用户扩展信息
            $userExtendInfo = UserManager::userExtendQuery(['u_id' => $uid])[0];
            //用户认证信息
            $userCertInfo = (new Certification())->getInfo($uid);
            $data['uid'] = $uid;
            $data['u_avatar'] = FileHelper::getFileUrl($userInfo['u_avatar'], 'user_avatar');
            $data['u_nickname'] = $userInfo['u_nickname'];
            $data['u_integral'] = (int)$userInfo['u_integral'];
            $data['u_identity'] = (int)$userInfo['u_integral'];
            if ($userCertInfo && is_array($userCertInfo)) {
                $data['u_identity'] = 1;
                $data['buyer'] = [
                    'level' => 'V1',
                    'start' => 1
                ];
                if (in_array($userCertInfo['uce_isCelebrity'], [1, 2]) && $userCertInfo['uce_status'] == 1) {
                    if ($userExtendInfo['is_own_shop'] == 1) {
                        //签约艺术家或机构
                        $data['seller'] = [
                            'level' => 'V3',
                            'type' => $userCertInfo['uce_isCelebrity'],
                            'start' => 3
                        ];
                    } else {
                        //认证艺术家或机构
                        $data['seller'] = [
                            'level' => 'V2',
                            'type' => $userCertInfo['uce_isCelebrity'],
                            'start' => 2
                        ];
                    }
                } else {
                    //实名认证
                    $data['seller'] = [
                        'level' => 'V1',
                        'start' => 1
                    ];
                }
            } else {
                $data['u_identity'] = 0;
                //买家身份
                $data['buyer'] = [
                    'level' => 'V0',
                    'start' => 0
                ];
                //卖家身份
                $data['seller'] = [
                    'level' => 'V0',
                    'start' => 0
                ];
            }
        }

        $this->responseJSON($data);
    }


    /**
     * 用户获取的代金券列表
     * @param type $page
     * @param type $pageSize
     * @throws ParamsInvalidException
     */
    public function newRegVoucherInfo() {
        $uid = $this->uid;
        $data=[
            'u_id'=>$uid,
            'v_t_ids'=>[],
            'is_new_reg'=>0,
            'reg_date'=>'',
            'received_vouchers'=>[]
        ];
        $vtIds=app('mysqlbxd_mall_user')->select('select v_t_id from `voucher_template` where `v_t_prefix`=\'regRegard\'');
        if($vtIds){
            $vtIds=array_column($vtIds,'v_t_id');
            $data['v_t_ids']=$vtIds;
            $voucherReceivedList=app('mysqlbxd_mall_user')->select("select * from `voucher` where u_id='{$uid}' and `v_t_id` in(".implode(',',$vtIds).")" );
            if($voucherReceivedList){
                $data['received_vouchers']=$voucherReceivedList;
            }
        }
        $regDate=app('mysqlbxd_user')->fetchColumn("select u_createDate from `user` where `u_id`='{$uid}'");
        $data['reg_date']=$regDate;
        if($regDate>'2018-09-16 00:00:00'){
            $data['is_new_reg']=1;
        }
        $this->responseJSON($data);
    }
    //新注册用户1000券是否已领
    private function isReceiveVoucher1000($uid)
    {
        $vtIds=app('mysqlbxd_mall_user')->select('select v_t_id from `voucher_template` where `v_t_prefix`=\'regRegard\'');
        if($vtIds){
            $vtIds=array_column($vtIds,'v_t_id');
            if($v_id=app('mysqlbxd_mall_user')->fetchColumn('select count(*) c from `voucher` where u_id='.$uid.' and `v_t_id` in('.implode(',',$vtIds).') limit 1 ')){
                return 1;
            }
        }
        return 0;
    }
    /**
     * 查询今天签到的次数
     *
     * @param int $uid
     * @return number
     */
    private function getTodayInviteTime($uid)
    {
        $integralService = new \Lib\User\UserIntegral();

        //type=3 签到
        $logs = $integralService->getHistoryLogs($uid, 3, 1, 1, '', '');

        if (isset($logs['totalCount'])) {  //有签到记录
            $lasted = $logs['rows'][0];
            $time = $lasted['uil_createDate'];

            //今日已签到
            if ((date('Y-m-d 00:00:00') < $time) && ($time < date('Y-m-d 23:59:59'))) {
                return ['count' => 1];
            } else if ((date("Y-m-d 00:00:00", strtotime("-1 day")) < $time) && ($time < date('Y-m-d 00:00:00'))) {//签到记录为昨天的
                return ['count' => 0, 'isContinue' => 1];
            } else {
                return ['count' => 0, 'isContinue' => 0];
            }
        }

        return ['count' => 0];
    }

    /**
     * 用户坐标
     */
    public function myPoint()
    {
        $uid = $this->uid;

        $point_x = app()->request()->params('point_x', '');
        $point_y = app()->request()->params('point_y', '');
        if (!$uid && !$point_x && !$point_y) {
            throw new ParamsInvalidException("缺少参数");
        }

        $mapLib = new \Lib\Map\mGeohash();

        $geohash = $mapLib->encode($point_x, $point_y);

        $_wei = 5; //半径是2.4公里

        $geohash = substr($geohash, 0, $_wei);

        $userModel = new \Model\User\Map();

        $isUserMap = $userModel->isUserMap($uid);

        $mapInfo = [];
        $mapInfo['um_lon'] = $point_x;
        $mapInfo['um_lat'] = $point_y;
        $mapInfo['um_geohash'] = $geohash;
        $mapInfo['um_updateDate'] = date('Y-m-d H:i:s');

        if ($isUserMap) {
            $userModel->updateUserMap($uid, $mapInfo);
        } else {

            $mapInfo['u_id'] = $uid;

            $mapInfo['um_createDate'] = date('Y-m-d H:i:s');

            $userModel->addUserMap($mapInfo);
        }

        $this->responseJSON(true);
    }

    public function fillDetail($uid = 0, $phone = 0)
    {
        if (!$uid && !$phone) {
            throw new ParamsInvalidException("缺少参数");
        }
        $userLib = new \Lib\User\User();
        $userInfos = $userLib->getUserInfo([
            $uid
        ], $phone, 1);
        $userInfo = [];
        if (is_array($userInfos) && !empty($userInfos)) {
            foreach ($userInfos as $tmpUserInfo) {
                $userInfo = $tmpUserInfo;
                break;
            }
        }
        if ($userInfo) {
            $userInfo = $this->parseUserInfo($userInfo);
            $uid = $userInfo['u_id'];
            $treasureModel = new \Model\Treasure\Treasure();
            list ($treasuresList,) = $treasureModel->lists(array(
                'u_id' => $uid
            ), 1, 1);
            $userInfo['last_treasure_pictures'] = [];
            if (is_array($treasuresList) && !empty($treasuresList)) {
                $last_treasure = $treasuresList[0];
                $userInfo['last_treasure_pictures'] = empty($last_treasure['t_pictures']) ? [] : $last_treasure['t_pictures'];
            }
            $userInfo['remark'] = $userInfo['u_nickname'];
            $userInfo['sourceFrom'] = '掌玩APP';
            //返回机构类型用户的营业执照
            $sql = "select uce_photoLicence from user_certification where u_id=:uid AND uce_status=1 AND uce_isCelebrity=2";
            $licenceImagePath = app('mysqlbxd_app')->fetchColumn($sql, [':uid' => $uid]);
            if (!empty($licenceImagePath)) {
                $userInfo['uce_photoLicence_url'] = FileHelper::getFileUrl($licenceImagePath, 'user_certification', '', '',
                    '!watermark_organization');
            } else {
                $userInfo['uce_photoLicence_url'] = '';
            }
        }
        // 好友关系
        $friendsModel = new \Model\Friends\Friends();
        $userInfo['relation'] = $friendsModel->relation($this->uid, $uid);
        // 是否认证
        $certificationModel = new \Model\User\Certification();
        $userInfo['certification'] = $certificationModel->getType($uid);
        // 作品数量
        $user_extend_info = $userLib->getUserExtend($uid);
        $userInfo['goodsNum'] = $user_extend_info['list']['ue_goodsNum'];
        $userInfo['is_own_shop'] = (int)$user_extend_info['list']['is_own_shop'];

        return $userInfo;
    }

    /**
     * 根据用户id获取用户的营业执照
     */
    public function getUserLicence()
    {
        $licenceUrl = '';
        $uid = app()->request()->params('uid');
        if ($uid) {
            //返回机构类型用户的营业执照
            $sql = "select uce_photoLicence from user_certification where u_id=:uid AND uce_status=1 AND uce_isCelebrity=2";
            $licenceImagePath = app('mysqlbxd_app')->fetchColumn($sql, [':uid' => $uid]);
            if (!empty($licenceImagePath)) {
                $licenceUrl = FileHelper::getFileUrl($licenceImagePath, 'user_certification', '', '', '!watermark_organization');
            }
        }
        $this->responseJSON($licenceUrl);
    }

    /**
     * 详情
     *
     * @throws ServiceException
     */
    public function detail()
    {
        $uid = app()->request()->params('uid');
        $phone = app()->request()->params('phone');
        $userInfo = $this->fillDetail($uid, $phone);
        $this->responseJSON($userInfo);
    }

    public function userResume()
    {
        $uid = app()->request()->params('uid');
        $phone = app()->request()->params('phone');

        $userInfo = $this->fillDetail($uid, $phone);
        if (($userInfo['u_phone'] == "12300000000") && empty($userInfo['ue_imId'])) {
            $userInfo['ue_imId'] = config('app.kefu_imId');
        }

        $treasureModel = new \Model\Treasure\Treasure();
        $treasureImgModel = new \Model\Treasure\TreasureImage();
        $data['u_id'] = $uid;
        $userInfo['t_pictures'] = array();
        $retTreasure = $treasureModel->lists($data, 1, 5);
        if ($retTreasure[0]) {
            $num = 3;
            $picList = array();
            foreach ($retTreasure[0] as $value) {
                list ($pic, $picTotalCount) = $treasureImgModel->lists(
                    array(
                        't_id' => $value['t_id']
                    ), 1, 10, 1);
                if (count($pic) >= $num) {
                    $picList = array_merge($picList, array_slice($pic, 0, $num));
                    break;
                } else {
                    $picList = array_merge($picList, $pic);
                    $num -= count($pic);
                }
            }
            $userInfo['t_pictures'] = $picList;
        }

        $this->responseJSON($userInfo);
    }

    /**
     * 注册session
     *
     * @param array $userInfo
     */
    private function regSession($userInfo)
    {
        SessionHelper::set(SessionKeys::USER_INFO, $userInfo);
        SessionHelper::set(SessionKeys::USER_ID, $userInfo['u_id']);
        SessionHelper::set(SessionKeys::USER_NAME, $userInfo['u_realname']);
        SessionHelper::set(SessionKeys::USER_NICKNAME, $userInfo['u_nickname']);
        SessionHelper::set(SessionKeys::USER_AVATAR, $userInfo['u_avatar']);
        SessionHelper::set(SessionKeys::USER_PHONE, $userInfo['u_phone']);
        $ue_gender = 0;
        if (isset($userInfo['user_extend']) && isset($userInfo['user_extend']['ue_gender'])) {
            $ue_gender = $userInfo['user_extend']['ue_gender'];
        }
        SessionHelper::set(SessionKeys::USER_GENDER, $ue_gender);
    }

    /**
     * 更新session中的用户信息
     */
    private function updateSessionUserInfo()
    {
        $userLib = new \Lib\User\User();
        $uid = SessionHelper::get(SessionKeys::USER_ID);
        $rows = $userLib->getUserInfo([
            $uid
        ], '', 1);
        $userInfo = $rows[$uid];
        $this->regSession($userInfo);
        return $userInfo;
    }

    /**
     * 销毁session
     *
     * @param array $userInfo
     */
    private function destroySession()
    {
        if (!session_destroy()) {
            throw new ServiceException("注销失败");
        }
    }

    /**
     * 格式用户信息，给app使用
     * @param unknown $userInfo
     * @return multitype:NULL unknown string
     */
    private function parseUserInfo($userInfo)
    {
        $settingModel = new \Model\User\Setting();
        //获取用户认证信息
        $certificationModel = new \Model\User\Certification();

        //获取 关注/粉丝数 信息
        $friendsModel = new \Model\Friends\Friends();

        $uid = $userInfo['u_id'];

        $certificationInfo = $certificationModel->getCertInfo($uid);

//        d($certificationInfo,'$certificationInfo');
//        d($userInfo,'$userInfo');

        $resInfo = array(
            'u_id' => $userInfo['u_id'],
            'u_phone' => $userInfo['u_phone'],
            'u_nickname' => $userInfo['u_nickname'],
            'u_avatar' => $userInfo['u_avatar'],
            'u_provinceCode' => $userInfo['u_provinceCode'],
            'u_cityCode' => $userInfo['u_cityCode'],
            'u_areaCode' => $userInfo['u_areaCode'],
            'u_provinceName' => \Lib\Common\Region::getRegionNameByCode($userInfo['u_provinceCode']),
            'u_cityName' => \Lib\Common\Region::getRegionNameByCode($userInfo['u_cityCode']),
            'u_areaName' => \Lib\Common\Region::getRegionNameByCode($userInfo['u_areaCode']),
            'ue_gender' => $userInfo['user_extend']['ue_gender'],
            'ue_imId' => $userInfo['user_extend']['ue_imId'],
            'ue_imPassword' => $userInfo['user_extend']['ue_imPassword'],
            'u_integral' => $userInfo['u_integral'],
            'ue_continueNum' => $userInfo['user_extend']['ue_continueNum'],
            'ue_birthday' => $userInfo['user_extend']['ue_birthday'],
            'user_setting_hobby' => $settingModel->settingGetValue($uid, 'hobby'),
            'user_setting_adept' => $settingModel->settingGetValue($uid, 'adept'),
            'user_setting_introduction' => $settingModel->settingGetValue($uid, 'introduction'),
            'u_attention' => $friendsModel->lists(array('fri_userId' => $uid))[1], //关注
            'u_fun' => $friendsModel->lists(array('fri_friendId' => $uid))[1], //粉丝
            'u_store_des' => empty($userInfo['u_store_des']) ? '' : $userInfo['u_store_des']
        );

        $treasur_background = $settingModel->settingGetValue($uid, 'treasur_background');
        $resInfo['user_setting_treasur_background'] = empty($treasur_background) ? '' : FileHelper::getFileUrl($treasur_background, 'user_background');
        $host_background = $settingModel->settingGetValue($uid, 'host_background');
        $resInfo['user_setting_host_background'] = empty($host_background) ? '' : FileHelper::getFileUrl($host_background, 'user_background');
        //如果有密码则为1，没有就为0
        $resInfo['is_possword'] = $userInfo['u_password'] ? 1 : 0;

        $resInfo['exists'] = 0;
        if (isset($userInfo['u_realname']) && $userInfo['u_realname']) {
            $resInfo['u_realname'] = $userInfo['u_realname'];
        } else {
            $userLib = new \Lib\User\User();
            $userInfos = $userLib->getUserInfo([$userInfo['u_id']]);
            if ($userInfos && is_array($userInfos)) {
                $userList = current($userInfos);
                $resInfo['u_realname'] = $userList['u_realname'];
            } else {
                $resInfo['u_realname'] = '';
            }
        }
        $resInfo['uce_status'] = '';
        $resInfo['uce_isCelebrity'] = '';
        $resInfo['certification'] = -1;
        $resInfo['exists'] = 0;
        if ($certificationInfo && is_array($certificationInfo) && isset($certificationInfo['uce_status'])) {
            $resInfo['exists'] = $certificationInfo['uce_IDNo'] || $certificationInfo['uce_status'] == 1 ? 1 : 0;
            // $resInfo['u_realname'] = $certificationInfo['uce_realName'];
            $resInfo['uce_status'] = $certificationInfo['uce_status'];
            $resInfo['uce_isCelebrity'] = $certificationInfo['uce_isCelebrity'];

            if ($certificationInfo['uce_status'] == 1) {
                $resInfo['certification'] = $certificationInfo['uce_isCelebrity'];
            }
        }

        //访客数量
        $visitModel = new \Model\User\Visit();
        $resInfo['visitCount'] = $visitModel->getVisitCount($uid);

        //我发布的商品的数量
        $myGoods = new \Lib\Mall\Goods();
//        $params['status'] = app()->request()->params('status', [0, 1, 2, 3, 4]);
        $params['salesId'] = app()->request()->params('salesId', $uid);
        $countRes = $myGoods->lists($params);
        $resInfo['myGoodsCount'] = $countRes['count'] ? $countRes['count'] : 0;

        $resInfo['u_phone'] = substr_replace($resInfo['u_phone'], '****', 3, 4);

        return $resInfo;
    }

    // private function updateCookie()
    // {
    // $name=config('app.session.dsa')
    // $this->app->response->setCookie($name, $value);
    // }

    /**
     * 全局搜索用户
     */
    public function allSearchUser()
    {
        $searchText = app()->request()->params('searchText');
        if (!$searchText) {
            throw new ParamsInvalidException("搜索的内容必须");
        }
        //搜索内容收录
        searchWord::keywordsCollect($searchText);
        $data['nickname'] = $searchText;
        $data['realname'] = $searchText;
        $data['status']='0';
//        if (is_numeric($searchText) && 11 == strlen($searchText)) {
//            $data['phone'] = $searchText;
//        }
        $data['page'] = app()->request()->params('page', 0);    // 默认不启用分页
        $data['pagesize'] = app()->request()->params('pageSize', 0);
        $data['needExtend'] = app()->request()->params('needExtend', 0);
        $u_type = app()->request()->params('u_type');
        if (isset($u_type)) {
            $data['u_type'] = $u_type;
        }

        $userLib = new \Lib\User\User();
        $userInfos = $userLib->fuzzySearch($data);
        if($userList = $userInfos[0]){
            $friendsModel = new \Model\Friends\Friends();
            $certificationModel = new \Model\User\Certification();
            foreach ($userList as &$value) {
                // 好友关系
                $value['relation'] = $friendsModel->relation($this->uid, $value['u_id']);
                // 是否认证
                $value['certification'] = (string)$certificationModel->getType($value['u_id']);
                // 作品数量
                $goodsNum = [];
                $goodsNum = $userLib->getUserExtend($value['u_id']);
                $value['goodsNum'] = $goodsNum['list']['ue_goodsNum'];
                if($value['u_realname']  && ($value['u_realname'] != $value['u_nickname'])){
                    $value['u_nickname'].="（{$value['u_realname']}）";
                }
            }
        }else{
            $userList=[];
        }


        $this->responseJSON(array_values($userList));
    }

    public function changePassword()
    {
        $oldPassword = app()->request()->params('oldPassword');
        $password = app()->request()->params('password');
        if (!$oldPassword || !$password) {
            throw new ParamsInvalidException("内容缺失");
        }
        $this->passwordFiltration($password);

        $userLib = new \Lib\User\User();
        $retChange = $userLib->changeUserPassword($this->uid, $oldPassword, $password);

        $this->responseJSON('');
    }

    private function passwordFiltration($password)
    {
        if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)/', $password)) {
            throw new ParamsInvalidException("密码必须同时含有数字字母");
        }
        $len = strlen($password);
        if ($len < 6 || $len > 18) {
            throw new ParamsInvalidException("密码长度限制6-18个字符");
        }
    }

    /**
     * 设置密码
     */
    public function setPassword()
    {
        $password = app()->request()->params('password');
        if (!$password) {
            throw new ParamsInvalidException("内容缺失");
        }
        $this->passwordFiltration($password);

        $userLib = new \Lib\User\User();
        $retChange = $userLib->setUserPassword($this->uid, $password);

        $this->responseJSON('');
    }

    /**
     * 用户中心——用户基本信息
     */
    public function getCommonInfo()
    {
        $uid = app()->request()->params('uid');
        if (empty($uid)) {
            throw new ParamsInvalidException("用户uid必须");
        }

        $userInfo = $this->fillDetail($uid);
        if (($userInfo['u_phone'] == "12300000000") && empty($userInfo['ue_imId'])) {
            $userInfo['ue_imId'] = config('app.kefu_imId');
        }
        $data['userInfo'] = $userInfo;

        $friendsCtl = new \Controller\User\Friends();
        $fans = $friendsCtl->getRelateList(1, $uid);
        $attention = $friendsCtl->getRelateList(2, $uid);
        $mutualFans = $friendsCtl->getRelateList(3, $uid);

        $data['fansNum'] = $fans['num'];
        $data['attentionNum'] = $attention['num'];
        $data['mutualFansNum'] = $mutualFans['num'];

        $visitModel = new \Model\User\Visit();
        $page = app()->request()->params('page', 1);    // 默认不启用分页
        $pageSize = app()->request()->params('pagesize', 10);
        $retList = $visitModel->lists($uid, $page, $pageSize);

        $userLib = new \Lib\User\User();
        foreach ($retList as $key => $value) {
            $userInfos = $userLib->getUserInfo([$value['u_id']]);
            if ($userInfos && is_array($userInfos)) {
                $temp = current($userInfos);
                $data['visit'][] = [
                    'u_id' => $temp['u_id'],
                    'u_avatar' => $temp['u_avatar'],
                ];
            }
        }

        $this->responseJSON($data);
    }

    public function updateIm()
    {
        $userLib = new \Lib\User\User();
        $ret = $userLib->updateUserIm();

        $this->responseJSON($ret);
    }

    /**
     * 用户积分记录列表
     */
    public function integralLists()
    {
        $uid = app()->request()->params('uid');
        if (!$uid) {
            throw new ServiceException("未登录");
        }

        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);

        $integralService = new \Lib\User\UserIntegral();

        $logs = $integralService->getHistoryLogs($uid, 0, $page, $pageSize, '', '');

        $this->responseJSON($logs);
    }
    /**
     * 每日积分完成情况
     */
    public function integralTaskLists()
    {
        $integralService = new \Lib\User\UserIntegral();
        $list=$integralService->integralTaskList($this->uid);
        $this->responseJSON($list);
    }
    /**
     * 积分兑换商品
     */
    public function integralExchange()
    {
        $uid = $this->uid;
        $type = app()->request()->params('type');
        if (empty($type)) {
            throw new ParamsInvalidException("请选择商品兑换");
        }
        $goods=[
            '7'=>[100,'自营通用1元立减券'],
            '8'=>[1000,'自营通用满100减10'],
            '9'=>[5000,'自营通用满300减50'],

            '6'=>[5000,'手工艺彩绘花插'],
            '1'=>[10000,'太行崖柏素竹手串'],
            '3'=>[20000,'私人定制钧瓷杯'],
            '4'=>[50000,'法国原瓶进口红酒1瓶'],
            '5'=>[30000,'茗丰堂汝窑功夫茶具一套'],
        ];
        if(!isset($goods[$type])){
            throw new ParamsInvalidException("错误的type参数");
        }
        $goodsInfo=$goods[$type];
        $integral=$goodsInfo[0];
        $name=$goodsInfo[1];
        $integralService = new \Lib\User\UserIntegral();
        $current_integral = $integralService->getCurrentUserIntegral($uid);
        if ($integral > $current_integral) {
            throw new ParamsInvalidException("积分不足");
        }
        //发代金券
        if(in_array($type,[7,8,9])){
            $tid=app('mysqlbxd_mall_user')->fetchColumn('select v_t_id from `voucher_template` where `v_t_prefix`=:v_t_prefix',[
                'v_t_prefix'=>'integral'.$type
            ]);
            if(!$tid){
                throw new ParamsInvalidException("代金券未设置");
            }
            (new \Lib\Mall\Voucher())->receive(['uid' => $uid, 'tids' => $tid]);
            //记录兑换记录
            $ie_status=1;
        }else{
            //记录兑换记录
            $ie_status=0;
        }
        //兑换操作
        $res=$integralService->change($uid, -($integral), \Lib\User\UserIntegral::ACTIVITY_EXCHANGE_REDUCE, '积分兑换:' . $name, "-" . $integral . \Lib\User\UserIntegral::NAME);
        $exchange_log=[
            'u_id'=>$uid,
            'ie_costIntegral'=>$integral,
            'ie_goodsType'=>$type,
            'ie_desc'=>'积分兑换:' . $name,
            'ie_time'=>date('Y-m-d H:i:s'),
            'ie_status'=>$ie_status,
        ];
        app('mysqlbxd_app')->insert('integral_exchange_log',$exchange_log);
        $this->responseJSON(
            array(
                'currentIntegral' => $res['integral']
            ));
    }

    /**
     * 获取在线客服的imId
     */
    public function service()
    {
        $phone = '13598823779';
        $userLib = new \Lib\User\User();
        // 检查手机号是否已存在
        $uid = $userLib->queryUserIdByPhone($phone);
        if ($uid) {
            $userInfo = $userLib->queryUserIm($uid);
        }
        $this->responseJSON($userInfo);
    }

    /**
     * 修改用户积分
     *
     */
    public function updateIntegral()
    {
        $integralService = new \Lib\User\UserIntegral();
        $datas = $integralService->updateIntegral([]);
        if ($datas) {
            $users = [];
            foreach ($datas as $value) {
                $integral = 0;
                $logs = $integralService->getHistoryLogs($value['u_id'], 0);
                if ($logs['rows']) {
                    foreach ($logs['rows'] as $val) {
                        if ($val['uil_type'] != 9) {
                            $integral += $val['uil_change'];
                        } else {
                            $integral = $integral - $val['uil_change'];
                        }
                        $users[$val['u_id']] = $integral;
                    }
                }
            }
        }

        if ($users) {
            foreach ($users as $key => $integral) {
                $param['uid'] = $key;
                $param['change'] = $integral;
                $return = $integralService->updateIntegral($param);
            }
        }
        $this->responseJSON(['ok']);
    }

    /**
     * 通过imid获取uid
     */
    public function getUid()
    {
        $imId = app()->request()->params('imId');
        if (empty($imId)) {
            throw new ParamsInvalidException("用户imId必须");
        }

        $userLib = new \Lib\User\User();
        $result = $userLib->getUidByIm($imId);

        $this->responseJSON($result);
    }

    /** 设置支付密码
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function setPayPassword()
    {
        if (!$this->uid) {
            throw new ParamsInvalidException("请先登录");
        }
        $userLib = new \Lib\User\User();
        $userInfo = current($userLib->getUserInfo([$this->uid]));
        if (!$userInfo) {
            throw new ServiceException("用户信息出错");
        }

        $phone = $userInfo['u_phone'];
        $captcha = app()->request()->params('captcha', '');
        $password = app()->request()->params('password', 0);
        $type = app()->request()->params('type', 4);
        if (!$phone || !$captcha || !$password) {
            throw new ParamsInvalidException("缺少参数");
        }
        $this->payPasswordFiltration($password);

        $updRes = $userLib->setPayPassword($phone, $captcha, $password, $type);
        if ($updRes->code != 200) {
            throw new ServiceException($updRes->data);
        }
        $this->responseJSON(true);
    }

    private function payPasswordFiltration($password)
    {
        if (!preg_match('/^[0-9]*$/', $password)) {
            throw new ParamsInvalidException("密码必须为数字");
        }
        $len = strlen($password);
        if ($len != 6) {
            throw new ParamsInvalidException("密码长度限制6个字符");
        }
    }

    /** 检测支付密码
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function checkPayPwd()
    {
        $password = app()->request()->params('password');
        $uid = $this->uid;
        if (!$password) {
            throw new ParamsInvalidException("缺少参数");
        }
        $this->payPasswordFiltration($password);

        $userLib = new \Lib\User\User();
        $isOk = $userLib->checkPayPwd($password, $uid);
        $this->responseJSON($isOk);
    }

    /** 修改支付密码（通过密码修改）
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function updatePayPassword()
    {
        $uid = $this->uid;
        if (!$uid) {
            throw new ParamsInvalidException("请先登录");
        }

        $password = app()->request()->params('password');
        $new_password = app()->request()->params('new_password');
        $type = app()->request()->params('type', 5);
        if (!$password || !$new_password) {
            throw new ParamsInvalidException("缺少参数");
        }
        $this->payPasswordFiltration($password);

        $userLib = new \Lib\User\User();
        $updRes = $userLib->updatePayPassword($uid, $password, $new_password, $type);
        if ($updRes->code != 200) {
            throw new ServiceException($updRes->data);
        }
        $this->responseJSON(true);
    }

    /**
     * 用户签约
     *
     */
    public function userSign()
    {
        $params = app()->request()->params();
        //必须要有“推荐人的手机号”
        if (isset($params['is_necessary']) && $params['is_necessary'] == 1) {
            if (empty($params['sign_phone_recommend'])) {
                throw new ParamsInvalidException("推荐人的手机号必填!");
            }
        }
        $params['action'] = 'insert';
        api_request($params, 'user/sign');
        $this->responseJSON(true);
    }

    /**
     * 用户签约作品上传
     */
    public function uploadSignImages()
    {
        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 10 * 1024 * 1024;
        $ftpConfigKey = 'user_sign_images';
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
     * 检测手机号是否已经注册
     */
    public function checkPhone()
    {
        $phone = app()->request()->params('phone');
        if (!$phone) {
            throw new ParamsInvalidException("缺少参数");
        }

        $userLib = new \Lib\User\User();
        $users = $userLib->getUserInfo([], $phone);
        if (empty($users)) {
            $ret = false;
        } else {
            $ret = true;
        }

        $this->responseJSON($ret);
    }

    /**
     * 保险岛APP 手机号注册
     *
     * @throws ServiceException
     */
    public function thirdRegister()
    {
        $phone = app()->request()->params('phone');
        //1保险岛app，2保险岛活动页
        $from = app()->request()->params('from');
        $from = empty($from) ? UserFrom::BXD_APP : UserFrom::BXD_H5;
        $userLib = new \Lib\User\User();
        $regRes = $userLib->regUser($phone, -1, $from);
        if ($regRes->code != 200) {
            throw new ServiceException($regRes->data);
        }

        $userInfo = $regRes->data;
        //新用户注册送积分
        (new \Lib\User\UserIntegral())->addIntegral($userInfo['u_id'],\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);
        $this->responseJSON(true);
    }

    /** 通过手机号拿到验证码（仅内部用）
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function queryCaptcha()
    {
        $phone = app()->request()->params('phone');
        $type = app()->request()->params('type', 0);
        if (!$phone) {
            throw new ParamsInvalidException("手机号必须");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }

        $userLib = new \Lib\User\User();
        $res = $userLib->queryCaptcha($phone, $type);
        $this->responseJSON($res);
    }
    /**
     * 小程序登录
     *
     * @throws ParamsInvalidException
     * @throws \Exception\ServiceException
     *
     */
    public function WxMinilogin()
    {
        $code=app()->request()->params('code','');
        $userInfoStr=app()->request()->params('userInfo','');
        $clientType=app()->request()->headers('client-type','');
        if(!$code){
            throw new ParamsInvalidException("code必须");
        }
        $clientTypes=[
            'wx_mini_zwwh'=>2,
            'wx_mini_yszz'=>3,
        ];
        if(!in_array($clientType,array_keys($clientTypes))){
            throw new ParamsInvalidException('client-type格式错误');
        }
        $userInfo=empty($userInfoStr)?[]:json_decode($userInfoStr,true);
        if(!$userInfo || empty($userInfo['rawData']) || empty($userInfo['signature'])
            ||empty($userInfo['encryptedData']) || empty($userInfo['iv']))
        {
            throw new ParamsInvalidException('userInfo参数错误');
        }
        $userLib=new \Lib\User\User();
        $wxLoginHelper=new \Lib\WxMini\WXLoginHelper($clientType);
        $wxLoginHelper->checkLogin($code);
        $wxUserInfoDe=$wxLoginHelper->getUserInfo($userInfo['rawData'],$userInfo['signature'],$userInfo['encryptedData'],$userInfo['iv']);
        if(!isset($wxUserInfoDe['unionId'])){
            throw new ServiceException('参赛错误');
        }
        $unionid=$wxUserInfoDe['unionId'];
//        $openid=$sessionInfo['openid'];
        //用户不存在
        if(!$uid=$userLib->queryUserIdByWxUnionId($unionid)){
            throw new ServiceException('用户不存在',100);
        }
        $userInfo=$userLib->getUserInfo([$uid],'',1);
        if(!$userInfo||empty($userInfo[$uid])){
            throw new ServiceException('获取用户信息失败');
        }
        $this->regSession($userInfo[$uid]);
        SessionHelper::sessionRegenerateId();
        $this->responseJSON($this->parseUserInfo($userInfo[$uid]));
    }
    private function getUserRegisterFromByClientType($clientType)
    {
        //来源   1保险岛app，2保险岛活动页， 11=掌玩app注册，12=掌玩app三方绑定注册，13艺术赚赚小程序，14掌玩文化小程序，15保险岛app引流，16后台管理员添加
        switch ($clientType){
            case 'wx_mini_zwwh':
                return UserFrom::WXMINI_ZWWH;
            case 'wx_mini_yszz':
                return UserFrom::WXMINI_YSZZ;
        }
        return '';
    }
    /**
     * 小程序注册
     * @throws ParamsInvalidException
     * @throws \Exception\ServiceException
     *
     */
    public function WxMiniReg()
    {
        $phone=app()->request()->params('phone','');
        $captcha=app()->request()->params('captcha','');
        $phoneInfoStr=app()->request()->params('phoneInfo','');
        $phoneInfo=empty($phoneInfoStr)?[]:json_decode($phoneInfoStr,true);
        $clientType=app()->request()->headers('client-type','');
        $shareUid = app()->request()->params('shareUid','');
        if (!($phone && $captcha) && !$phoneInfo) {
            throw new ParamsInvalidException("缺少参数");
        }
        $clientTypes=[
            'wx_mini_zwwh'=>2,
            'wx_mini_yszz'=>3,
        ];
        if(!in_array($clientType,array_keys($clientTypes))){
            throw new ParamsInvalidException('clientType格式错误');
        }
        $userLib=new \Lib\User\User();
        $wxLoginHelper=new \Lib\WxMini\WXLoginHelper($clientType);
        $isUserExists=1;
        if($captcha && $phone){
            if($userInfoOld=$userLib->getUserInfoByPhone($phone)){
                $uid=$userInfoOld['u_id'];
                if(!$userLib->checkCaptcha($phone,$captcha)){
                    throw new ServiceException('验证码错误');
                }
            }else{
                $isUserExists=0;
                $from=$this->getUserRegisterFromByClientType($clientType);
                //  注册送1000券
                $regRes = $userLib->regNewUser($phone, $captcha, '',$from);
                if ($regRes->code != 200) {
                    throw new ServiceException($regRes->data);
                }
                $regUserInfo=$regRes->data;
                //检测注册用户是否是邀请过来的
                $this->registerNewUserInviteProc($shareUid,$phone);
                $uid=$regUserInfo['u_id'];
                //新用户注册送积分
                (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);
            }
        }else{
            $decryptedData=$wxLoginHelper->decryptData($phoneInfo['encryptedData'],$phoneInfo['iv']);
            $phone=$decryptedData['phoneNumber'];
            if($userInfoOld=$userLib->getUserInfoByPhone($phone)){
                $uid=$userInfoOld['u_id'];
            }else{
                $isUserExists=0;
                $from=$this->getUserRegisterFromByClientType($clientType);
                //  注册送1000券
                $regRes=$userLib->regUser($phone,-2,$from);
                if ($regRes->code != 200) {
                    throw new ServiceException($regRes->data);
                }
                $regUserInfo=$regRes->data;
                //检测注册用户是否是邀请过来的
                $this->registerNewUserInviteProc($shareUid,$phone);
                $uid=$regUserInfo['u_id'];
                //新用户注册送积分
                (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_USER_REGISTER);
            }
        }
        $wxUserInfoDe=SessionHelper::get([SessionKeys::USER_WX_MINI_SESSIONKEY,WXLoginHelper::LOGIN_USER_INFO_DESCRYPTED]);
        if(!$wxUserInfoDe){
            throw new ServiceException('获取UserInfo错误');
        }
        //检查老用户，同一个手机，绑定不同的微信用户
        if($isUserExists && $userInfoOld){
            if($userInfoOld['u_wx_unionID']
                && $userInfoOld['u_wx_unionID']!=$wxUserInfoDe['unionId']
            ){
                throw new ServiceException('手机号已绑定其他微信账户');
            }
        }
        $sns_info = [
            'channel'=>$clientType,
            'openid'=>$wxUserInfoDe['openId'],
            'wxunionid'=>$wxUserInfoDe['unionId'],
            'nick'=>$wxUserInfoDe['nickName'],
            'gender' => $wxUserInfoDe['gender']==1?'m':$wxUserInfoDe['gender']==2?'f':0,
            'avatar'=> $wxUserInfoDe['avatarUrl'],
        ];
        $thirdLogin = new \Lib\User\ThirdLoginOAuth();
        $userInfo = $thirdLogin->bindThirdUser($uid, $sns_info);
        if (!$userInfo) {
            throw new ServiceException("绑定失败");
        }
        $this->regSession($userInfo);
        $userInfoData = $this->parseUserInfo(SessionHelper::get(SessionKeys::USER_INFO));
        $userInfoData['isUserExists'] = $isUserExists;
        SessionHelper::sessionRegenerateId();
        $this->responseJSON($userInfoData);
    }

    public function phoneModify()
    {
        $phone=app()->request()->params('phone','');
        $captcha=app()->request()->params('captcha','');
        if(!preg_match('/^1\d{10}$/',$phone)){
            throw new ParamsInvalidException('手机号格式错误');
        }
        if(!preg_match('/^\d+$/',$captcha)){
            throw new ParamsInvalidException('验证码格式错误');
        }
        if(!SessionHelper::get(SessionKeys::MODIFY_PHONE_STEP_STATUS)){
            throw new ServiceException('需要先验证原手机号');
        }
        $userLib = new \Lib\User\User();
        $userLib->updateUserPhone($this->uid,$phone,$captcha);
        $this->updateSessionUserInfo();
        $this->responseJSON(true);
    }
    public function phoneModifyCheck()
    {
        $phone=app()->request()->params('phone','');
        $captcha=app()->request()->params('captcha','');
        SessionHelper::set(SessionKeys::MODIFY_PHONE_STEP_STATUS,0);
        $userPhone=SessionHelper::get(SessionKeys::USER_PHONE);
        if (!$userPhone) {
            throw new ParamsInvalidException("没有查询到用户手机号，重新登陆后再试");
        }
        //校验原手机号
        if($phone){
            if(!preg_match('/^1\d{10}$/',$phone)){
                throw new ParamsInvalidException('手机号格式错误');
            }
            if($userPhone!=$phone){
                throw new ParamsInvalidException('手机号不匹配');
            }
            SessionHelper::set(SessionKeys::MODIFY_PHONE_STEP_STATUS,1);
        }else{
            //校验原手机号+验证码
            if(!preg_match('/^\d+$/',$captcha)){
                throw new ParamsInvalidException('验证码格式错误');
            }
            $userLib = new \Lib\User\User();
            $isOk = $userLib->checkCaptcha($userPhone, $captcha);
            SessionHelper::set(SessionKeys::MODIFY_PHONE_STEP_STATUS,1);
        }
        $this->responseJSON(true);

    }

    /**
     * 根据图形码发短信
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function getImgCaptchaSms()
    {
        $imgCaptcha=app()->request()->params('imgCaptcha', 0);
        $imgCaptchaFlag=app()->request()->params('imgCaptchaFlag', '');
        if(!$imgCaptcha){
            throw new ParamsInvalidException("图形码必须");
        }
        $captchaLib=new \Captcha\tpcaptcha\Captcha();
        if(!$captchaLib->check($imgCaptcha,$imgCaptchaFlag)){
            throw new ParamsInvalidException("图形码错误");
        }

        $phone = app()->request()->params('phone');
        $type = app()->request()->params('type', 0);
        if (!$phone) {
            $phone=SessionHelper::get(SessionKeys::USER_PHONE);
            if(!$phone){
                throw new ParamsInvalidException("请输入手机号");
            }
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        $userLib = new \Lib\User\User();
        $phoneExist = app()->request()->params('phoneExist', 0);
        if ($phoneExist != 1) {
            if ($type == 3) {
                $uid = $userLib->queryUserIdByPhone($phone);
                if ($uid) {
                    throw new ServiceException("该手机号码已经注册");
                }
            } elseif ($type == 2) {
                $uid = $userLib->queryUserIdByPhone($phone);
                if (!$uid) {
                    throw new ServiceException("该手机号尚未注册");
                }
            }
        }
        $isOk = $userLib->sendCaptcha($phone, $type);
        $this->responseJSON($isOk);
    }

    /**
     * 发送验证码
     */
    public function sendCaptcha()
    {
        $phone = app()->request()->params('phone');
        $type  = app()->request()->params('type');
        if (!$phone) {
            $phone=SessionHelper::get(SessionKeys::USER_PHONE);
            if(!$phone){
                throw new ParamsInvalidException("请输入手机号");
            }
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!$type) {
               throw new ParamsInvalidException("发送类型不能为空");
        }
        $userLib = new \Lib\User\User();
        $isOk = $userLib->sendCaptcha($phone, 1);
        $this->responseJSON($isOk);
    }

    /**
     * 获取图形码
     */
    public function getImgCaptcha()
    {
        $id=app()->request()->params('flag','');
        $isBase64Encode=app()->request()->params('isBase64Encode','');
        $captcha=new \Captcha\tpcaptcha\Captcha([
            'useNoise'=>true,
            'useCurve'=>false,
            'codeSet'=>'1234567890',
            'bg'=>[248,248,248],
            'length'=>4,
        ]);
        $content=$captcha->entry($id);
        if($isBase64Encode){
            $content='data:image/png;base64,'.base64_encode($content);
            header('Content-Length:' . strlen($content));
            header('Content-Type:text/text' . '; charset=' . 'utf-8');
            echo $content;
            exit;
        }else{
            header('Content-Length:' . strlen($content));
            header('Content-Type:image/png' . '; charset=' . 'utf-8');
            echo $content;
            exit;
        }
    }

    /**
     * 新用户领红包
     */
    public function getNewRegVoucher()
    {
        $uid=$this->uid;
        $data=\Lib\User\UserVoucher::sendVoucherUserRegister($uid);
        $this->responseJSON($data);
    }

    /**
     * 获取签约艺术家
     */
    public function getSignUser()
    {
        $data = ['list' => [], 'count' => 0];
        $request = $this->app->request;
        $page = $request->params('page');
        $pageSize = $request->params('pageSize');
        $page = empty($page) ? 1 : (int)$page;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = empty($pageSize) ? 10 : (int)$pageSize;
        list($userList, $userCount) = UserManager::getUserList(['is_own_shop' => 1, 'orderBy' => 'ue_createDate ASC'], $pageIndex, $pageSize);
        if ($userList) {
            foreach ($userList as $item) {
                $data['list'][] = [
                    "u_id" => $item['u_id'],
                    "u_avatar" => FileHelper::getFileUrl($item['u_avatar'], 'user_avatar'),
                    "u_realname" => $item['u_realname'],
                ];
            }
        }
        $data['count'] = $userCount;
        $this->responseJSON($data);
    }
}
