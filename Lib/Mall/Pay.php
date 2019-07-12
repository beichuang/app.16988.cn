<?php
namespace Lib\Mall;

use Exception\ParamsInvalidException;

class Pay extends MallBase
{

    /**
     * 收银台信息
     */
    public function payGet($params)
    {
        return $this->passRequest2Mall($params, 'pay/get');
    }

    /**
     * 发起支付
     */
    public function payInit($params)
    {
        return $this->passRequest2Mall($params, 'pay/init');
    }


    /**
     * 钱包支付通知
     */
    public function payWallet($params)
    {
        return $this->passRequest2Mall($params, 'pay/notify/wallet');
    }

    /**
     * 退款
     */
    public function refundMoney($params)
    {
        wlog('退款 refundMoney', 'pledge');

        return $this->passRequest2Mall($params, 'pay/refundmoney');
    }


    /**
     * 退款结果查询
     */
    public function refundRet($params)
    {
        return $this->passRequest2Mall($params, 'pay/refund/query');
    }
}
