<?php
/**
 * 2018-8
 * 年会活动，签到，送券
 */

namespace Controller\Activity;


use Exception\ParamsInvalidException;
use Exception\ServiceException;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;

class AnnualMeeting extends BaseController
{
    const SIGN_ON_HOME_PATE='/html/apph5/annualConfer.html';
    private $openId=null;

    public function __construct()
    {
        parent::__construct();
        $this->openId=WxHelper::getOpenId();
//        $this->openId='y1534993015';//'y'.time();
//        $this->uid=1;
        if(!$this->openId){
            if (!app()->request->isAjax()) {
                app()->redirect(static::SIGN_ON_HOME_PATE);
            }else{
                throw new ServiceException('查询openid失败');
            }
        }
    }
    /**
     * 签到首页
     */
    public function signHome()
    {
        app()->redirect(static::SIGN_ON_HOME_PATE);
    }

    public function signInfo()
    {
        $amUser=app('mysqlannual_meeting')->fetch("select * from `am_user` where am_wxopenid=:am_wxopenid",[
            'am_wxopenid'=>$this->openId
        ]);
        if(!$amUser){
            $amUser=[
                'am_is_sign'=>0,
            ];
        }
        $amUser['isLogin']=$this->uid?1:0;
        $this->responseJSON($amUser);
    }
    /**
     * 员工签到
     */
    public function signOnStaff()
    {
        $am_job_alias= app()->request()->params('am_job_alias', '');
        $am_job_number= app()->request()->params('am_job_number', '');
        if(!$am_job_alias || !$am_job_number){
            throw new ParamsInvalidException('花名及工号必须');
        }
        if(!preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]{2,40}$/u",$am_job_alias)){
            throw new ParamsInvalidException('花名非法');
        }
        if(!preg_match("/^9\d{6}$/",$am_job_number)){
            throw new ParamsInvalidException('工号非法');
        }
        $wxInfo=WxHelper::getUserInfo(WxHelper::getAccessToken(),$this->openId);
//        $wxInfo['subscribe']=1;
//        $wxInfo['headimgurl']='https://tvax1.sinaimg.cn/crop.0.0.512.512.180/006GBi9Nly8fqjmnt46ztj30e80e8mxi.jpg';
        if(!isset($wxInfo['subscribe'])){
            throw new ServiceException('获取微信用户信息错误');
        }
        if($wxInfo['subscribe']!=1){
            throw new ServiceException('需要先关注公众号');
        }
        $isWxSign=app('mysqlannual_meeting')->fetchColumn("select am_is_sign from `am_user` where am_wxopenid=:am_wxopenid",[
            'am_wxopenid'=>$this->openId
        ]);
        if($isWxSign==1){
            throw new ServiceException('该微信账号已签到');
        }
        $amUser=app('mysqlannual_meeting')->fetch("select am_uid,am_is_sign from `am_user` where am_is_guest=0 and am_job_number=:am_job_number",[
            'am_job_number'=>$am_job_number,
        ]);
        if($amUser===false){
            throw new ServiceException('用户不存在');
        }else if($amUser['am_is_sign']==1){
            throw new ServiceException('已签到');
        }else{
            if($amUser['am_job_alias']!=$am_job_alias){
                throw new ParamsInvalidException('花名错误');
            }
            $data=[
                "am_avatar"=>$wxInfo['headimgurl'],
//                "am_job_number"=>$am_job_number,
//                "am_job_alias"=>$am_job_alias,
//                "am_is_guest"=>0,
                "am_is_sign"=>1,
                "am_is_sign_time"=>time(),
                "am_wxopenid"=>$this->openId,
            ];

            $rc=app('mysqlannual_meeting')->update('am_user',$data,[
                'am_uid'=>$amUser['am_uid']
            ]);
        }
        $this->responseJSON('ok');
    }
    /**
     * 宾客签到
     */
    public function signOnGuest()
    {
        $am_realname= app()->request()->params('am_realname', '');
        if(!$am_realname){
            throw new ParamsInvalidException('姓名必须');
        }
        if(!preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]{2,40}$/u",$am_realname)){
            throw new ParamsInvalidException('姓名是2个以上的汉字');
        }
        $wxInfo=WxHelper::getUserInfo(WxHelper::getAccessToken(),$this->openId);
//        $wxInfo['subscribe']=1;
//        $wxInfo['headimgurl']='https://tvax1.sinaimg.cn/crop.0.0.512.512.180/006GBi9Nly8fqjmnt46ztj30e80e8mxi.jpg';
        if(!isset($wxInfo['subscribe'])){
            throw new ServiceException('获取微信用户信息错误');
        }
        if($wxInfo['subscribe']!=1){
            throw new ServiceException('需要先关注公众号');
        }
        $isWxSign=app('mysqlannual_meeting')->fetchColumn("select am_is_sign from `am_user` where am_wxopenid=:am_wxopenid",[
            'am_wxopenid'=>$this->openId
        ]);
        if($isWxSign==1){
            throw new ServiceException('该微信账号已签到');
        }
        $amUsers=app('mysqlannual_meeting')->select("select am_uid,am_is_sign from `am_user` where am_is_sign=0 and am_realname=:am_realname",[
            'am_realname'=>$am_realname,
        ]);
        if(!$amUsers){
            throw new ServiceException('用户不存在');
        }else{
            foreach ($amUsers as $amUser){
                if($amUser['am_is_sign']==0){
                    $data=[
                        "am_avatar"=>$wxInfo['headimgurl'],
                        "am_is_sign"=>1,
                        "am_is_sign_time"=>time(),
                        "am_wxopenid"=>$this->openId,
                    ];
                    $rc=app('mysqlannual_meeting')->update('am_user',$data,[
                        'am_realname'=>$am_realname,
                        'am_is_guest'=>1,
                    ]);
                }
                $this->responseJSON('ok');
                return;
            }
            throw new ServiceException('已签到');
        }
    }
    /**
     * 领优惠券
     */
    public function getSignVoucher()
    {
        if(!$this->uid){
            throw new \Exception\AccessException('未登录', \Exception\AccessException::CODE_USER_NOT_LOGIN);
        }
        $isGetVoucher=app('mysqlannual_meeting')->fetchColumn("select am_is_coin from `am_user` where am_wxopenid=:am_wxopenid",[
            'am_wxopenid'=>$this->openId,
        ]);
        if($isGetVoucher===false){
            throw new ServiceException('请先签到');
        }else if($isGetVoucher==1){
            throw new ServiceException('已领取优惠券');
        }else{
            if(date(Ymd)>'20180902'){
                throw new ServiceException('活动已结束');
            }
            $tids = api_request(['skey' => '201808xj10am_voucher_template_id'], 'mall/setting');
            if ($tids) {
                $voucherLib = new \Lib\Mall\Voucher();
                $voucherLib->receive(['uid' => $this->uid, 'tids' => $tids]);
            }
            app('mysqlannual_meeting')->update('am_user',[
                'am_is_coin'=>1,
                'am_is_coin_time'=>time(),
            ],[
                'am_wxopenid'=>$this->openId,
            ]);
            $this->responseJSON('ok');
        }
    }
}