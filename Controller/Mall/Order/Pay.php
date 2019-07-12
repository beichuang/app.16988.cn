<?php

/**
 * 支付
 * @author Administrator
 *
 */

namespace Controller\Mall\Order;

use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Exception\ModelException;
use Lib\WxMini\WXLoginHelper;
use Rest\Pay\Facade\DistributionWalletManager;
use Rest\User\Facade\UserDVipInviteManager;
use Rest\User\Facade\UserManager;

class Pay extends BaseController {

    private $payLib = null;

    public function __construct() {
        parent::__construct();
        $this->payLib = new \Lib\Mall\Pay();
    }

    /**
     * 获取支付方式
     */
    public function get() {
        $params = app()->request()->params();
        $resPay = $this->payLib->payGet($params);
        $this->responseJSON($resPay);
    }

    /**
     * 发起支付
     */
    public function init() {
        $params = app()->request()->params();
        $act = app()->request()->params('act');

        $trade = new \Model\Pay\Trade();
        $config = load_row_configs_trim_prefix('api.Mall');
        $tradeId = $config['appId'] . '-' . $params['tradeId'];
        //if(isset($act) && ($act == 'new')){
        $tradeInfo = $trade->getCartTrade($tradeId);
        if (!$tradeInfo) {
            $trade_sn = $trade->getTrade($tradeId);
            if (!$trade_sn) {
                throw new ParamsInvalidException("交易信息不存在");
            }
            $tradeInfo[] = $trade_sn;
        }

        //支付方式为小程序的单独调用
        $payChannel=app()->request()->params('payChannel');
        //支付方式为艺术转转小程序
        if($payChannel==12){
            //获取openid
            $openid    = WXLoginHelper::getWxSession(WXLoginHelper::OPENID);
            $openid    = is_string($openid)?$openid:$openid[1]['openid'];
            if(!$openid){
                $openid = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : app()->request()->params('openid');
            }
            $params['openId'] = $openid;
            //获取其它附属信息
            $params['buyer_uid']=empty($this->uid)?$params['buyer_uid']:$this->uid;
            if (isset($params['orderDistributionType']) && $params['orderDistributionType'] == 1) {
                //分销订单，获取分销类型（商品分销或者拉新，分享人id）
                list($d_uid, $d_type) = $this->getDistributionData($params);
            } else {
                $d_uid = '';
                $d_type = 0;
            }
            $params['d_uid'] = $d_uid;
            $params['d_type'] = $d_type;
            if (empty($params['buyer_uid']) || empty($openid)) {
                throw new \Exception\ParamsInvalidException("小程序参数不全");
            }
        }
        //掌玩文化  小程序
        //if($payChannel==7){
        if(in_array($payChannel, [6, 7])){
            $openid    = WXLoginHelper::getWxSession(WXLoginHelper::OPENID);
            if(!$openid){
                $openid = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : app()->request()->params('openid');
            }
            $params['openId'] = $openid;
        }



        $orderLib = new \Lib\Mall\Order();
        $tr_amount = 0;
        foreach ($tradeInfo as $val) {
            if ($val['tr_status'] > 1) {
                throw new ParamsInvalidException("该支付已完成");
            }
            $tr_amount += $val['tr_amount'];
            $tr_subject = $val['tr_subject'];
        }
        /* }else {
          $tradeInfo = $trade->getTrade($tradeId);
          if ($tradeInfo['tr_status'] > 1) {
          throw new ParamsInvalidException("该支付已完成");
          }
          } */

        //记录该订单的支付方式
        $extend['tradeId'] = $tradeId;
        $extend['payChannel'] = $params['payChannel'];
        $orderLib->orderExtend($extend);

        if ($params['payChannel'] == 99) {
            $wallet = new \Model\Pay\Wallet();
            $walletInfo = $wallet->getWallet($this->uid);

            $resPay['balance'] = $walletInfo['w_balance'];
            //if(isset($act) && ($act == 'new')){
            $resPay['subject'] = $tr_subject;
            $resPay['totalFee'] = $tr_amount;
            foreach ($tradeInfo as $val) {
                $res = $trade->updateStatus($val['tr_id'], 1);
                if (!$res) {
                    throw new ModelException("交易状态改变失败!");
                }
            }
            /* }else {
              $resPay['subject'] = $tradeInfo['tr_subject'];
              $resPay['totalFee'] = $tradeInfo['tr_amount'];
              $res = $trade->updateStatus($tradeInfo['tr_id'], 1);
              if (!$res) {
              throw new ModelException("交易状态改变失败!");
              }
              } */
        } else {
            $resPay = $this->payLib->payInit($params);
        }
//        if($params['payChannel']==3){
//            echo $resPay['payInfo3'];
//        }else{
            $this->responseJSON($resPay);
//        }
    }



    private function getDistributionData($params)
    {
        $d_uid = '';
        $d_type = 1;
        $userExtendData = UserManager::getOneUserExtend($this->uid);
        //非传艺人
        if (!empty($userExtendData) || $userExtendData['u_distribution_type'] != 1) {
            $isBuySpecialDistributionGoods = false;
            //分销类型（1：商品分销；2拉新）
            $d_type = isset($params['d_type']) ? $params['d_type'] : 1;
            if (!empty($params['gid']) && $d_type == 2) {
                $buyGoodsIds = $params['gid'];
                $specialDistributionGoodsIdArray = conf('config.specialDistributionGoods');
                if (in_array($buyGoodsIds, $specialDistributionGoodsIdArray)) {
                    $isBuySpecialDistributionGoods = true;
                }
            }

            if ($isBuySpecialDistributionGoods) {
                $d_type = 2; //拉新传艺人
                //是否绑定了邀请人
                $dVipInviteData = UserDVipInviteManager::getByUid($this->uid);
                if ($dVipInviteData && $dVipInviteData['vi_invite_uid']) {
                    $d_uid = $dVipInviteData['vi_invite_uid'];
                } else {
                    $d_uid = isset($params['uid']) ? $params['uid'] : '';
                }
            } else {
                $d_uid = isset($params['uid']) ? $params['uid'] : '';
                $d_type = 1;
            }
        }

        return [$d_uid, $d_type];
    }













    /**
     * 钱包确认支付
     */
    public function confirm() {
        $params = app()->request()->params();
        $act = $params['act'];
        $totalFee = $params['totalFee'];
        $balance = $params['balance'];
        $tradeId = $params['tradeId'];
        $password = $params['password'];
        if (!isset($totalFee) || !isset($balance) || empty($tradeId)) {
            throw new ParamsInvalidException("缺少参数,请检查.");
        }

        if (isset($password)) {
            $userLib = new \Lib\User\User();
            $userLib->checkPayPwd($password, $this->uid);
        }

        $trade = new \Model\Pay\Trade();
        $config = load_row_configs_trim_prefix('api.Mall');
        $tr_tradeId = $config['appId'] . '-' . $params['tradeId'];

        //if(isset($act) && ($act == 'new')){
        $tradeInfo = $trade->getCartTrade($tr_tradeId);
        if (!$tradeInfo) {
            $trade_sn = $trade->getTrade($tr_tradeId);
            if (!$trade_sn) {
                throw new ParamsInvalidException("交易信息不存在");
            }
            $tradeInfo[] = $trade_sn;
        }

        $tr_amount = $tr_status = 0;
        foreach ($tradeInfo as $val) {
            if ($val['tr_status'] > 1) {
                throw new ParamsInvalidException("该支付已完成");
            }
            $tr_amount += $val['tr_amount'];
            $tr_subject = $val['tr_subject'];
            $tr_status = $val['tr_status'];
            $tr_type = $val['tr_type'];
        }
        /* }else{
          $tradeInfo = $trade->getTrade($tr_tradeId);
          $tr_amount = $tradeInfo['tr_amount'];
          } */

        if ($tr_amount != $totalFee) {
            throw new ParamsInvalidException("非法操作，传递数据不对");
        }
        $wallet = new \Model\Pay\Wallet();
        $walletInfo = $wallet->getWallet($this->uid);
        if ($walletInfo['w_balance'] != $balance) {
            throw new ParamsInvalidException("非法操作，传递数据不对");
        }
        if ($walletInfo['w_balance'] < $tr_amount) {
            throw new ParamsInvalidException("余额不足，请充值或选择其它支付");
        }

        if ($tr_status != 1) {
            throw new ParamsInvalidException("该支付未被调起或已完成");
        }

        $wallet->beginTransaction();
        try {
            //钱包扣钱操作
            $retWallet = $wallet->apply($this->uid, $totalFee, false);

            //钱包明细流水
            $walletLog = new \Model\Pay\WalletPayLog();
            $retLog = $walletLog->write($this->uid, $totalFee, $retWallet[1], $tr_subject, $tr_type, '', false, $tradeId);

            $wallet->commit();
        } catch (\Exception $e) {
            $wallet->rollback();
            throw $e;
        }

        //通知订单状态修改及日志记录
        $notifyParams['tradeId'] = $tradeId; //订单sn 即order sn
        $notifyParams['outTradeId'] = $tr_tradeId; //交易流水号 即 tr_tradeId
        $notifyParams['tradeNo'] = $retLog[1]['wpl_tradeId']; // 支付成功通知流水号
        $notifyParams['uid'] = $this->uid; // 支付成功通知流水号

        $resPay = $this->payLib->payWallet($notifyParams);

        $this->responseJSON($resPay);
    }

    public function lists() {
        $u_id = app()->request()->params('u_id', $this->uid);
        if (!$u_id) {
            throw new ParamsInvalidException("非法操作，传递数据不对");
        }

        $walletLog = new \Model\Pay\WalletPayLog();
        $payList = $walletLog->lists($u_id);

        $this->responseJSON($payList);
    }

    /**
     * 申请提现
     */
    public function applyWithdraw() {
        $params = app()->request()->params();
        if (!isset($params['wd_amount']) || ($params['wd_amount'] <= 0)) {
            throw new ParamsInvalidException("提现金额必须");
        }
        if (!isset($params['wd_type']) || empty($params['wd_type'])) {
            throw new ParamsInvalidException("帐户类型必须");
        }
        if (!isset($params['wd_account']) || empty($params['wd_account'])) {
            throw new ParamsInvalidException("提现帐号必须");
        }
        if (!isset($params['wd_name']) || empty($params['wd_name'])) {
            throw new ParamsInvalidException("账户姓名必须");
        }
        if (!isset($params['wd_tel']) || empty($params['wd_tel'])) {
            throw new ParamsInvalidException("电话号码必须");
        }
        //钱包类型
        $walletType = isset($params['wd_wallet_type']) ? $params['wd_wallet_type'] : 1;

        if ($walletType == 2) {//表示小程序分销钱包
            $distributionWallet = DistributionWalletManager::getByUid($this->uid);
            if ($params['wd_amount'] > $distributionWallet['dw_balance']) {
                throw new ParamsInvalidException("提现金额不能超出您的钱包余额");
            }
        } else {//表示钱包
            $wallet = new \Model\Pay\Wallet();
            $walletInfo = $wallet->getWallet($this->uid);

            if ($params['wd_amount'] > $walletInfo['w_balance']) {
                throw new ParamsInvalidException("提现金额不能超出您的钱包余额");
            }
        }

        //银行卡提现时，需要认证一些信息
        if ($params['wd_type'] == 1) {
            if (!isset($params['wd_bankBranch']) || empty($params['wd_bankBranch'])) {
                $params['wd_bankBranch'] = '无';
            }
            //获取用户的身份证id
            $certificationModel = new \Model\User\Certification();
            $userInfo = $certificationModel->getInfo($this->uid);
            //实名认证+银行卡信息验证
            $realNameClass = new \realName\realName();
//            $ret = $realNameClass->authentication($params['wd_name'], $userInfo['uce_IDNo'], $params['wd_account'], $params['wd_tel']);
//            if (!isset($ret['code']) || $ret['code'] > 0) {
//                throw new ParamsInvalidException($ret['desc']);
//            }
        }

        $params['u_id'] = $this->uid;
        $params['wd_ctime'] = date('Y-m-d H:i:s');

        $walletLib = new \Lib\Mall\Withdrawals();
        $retApply = $walletLib->dealApply($params);

        $this->responseJSON($retApply[1]);
    }

    /**
     *  我的钱包余额
     */
    public function getBalance() {
        $wallet = new \Model\Pay\Wallet();
        $walletInfo = $wallet->getWallet($this->uid);

        $act = app()->request()->params('act');
        if (isset($act) && $act == 'new') {
            $data['w_balance'] = $walletInfo['w_balance'];
            $data['w_freezing'] = $walletInfo['w_freezing'];
            $data['w_trusteeship_amount'] = $walletInfo['w_trusteeship_amount'];

            //是否设置了支付密码
            $userLib = new \Lib\User\User();
            $userInfo = $userLib->getUserInfo([$this->uid]);
            if ($userInfo) {
                $userList = current($userInfo);
                $data['isSet'] = $userList['u_payPassword'] ? "1" : "0";   //1=已经设置了支付密码；0=没有设置支付密码
            }

            //错误密码输入了几次
            $res = $userLib->checkPayPwdErrorCount($this->uid, 42);
            $data['isTrue'] = $res ? "1" : "0";   //1=错误没有超过5次，0=错误超过5次

            $this->responseJSON($data);
        } else {
            $this->responseJSON($walletInfo['w_balance']);
        }
    }

}
