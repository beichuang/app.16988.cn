<?php

namespace Lib\Mall;

use Exception\ParamsInvalidException;
use Rest\Pay\Facade\DistributionWalletManager;

class Withdrawals {

    /**
     * 订单处理
     */
    public function dealApply($data)
    {
        $walletMod = new \Model\Pay\Wallet();
        $wdMod = new \Model\Pay\Withdrawals();
        $walletLog = new \Model\Pay\WalletPayLog();

        $walletMod->beginTransaction();
        try {
            wlog(['data'=>$data],'申请提现记录日志001',4);

            if ($data['wd_wallet_type'] == 2) {
                //分销钱包扣钱操作
                $retWallet = DistributionWalletManager::updateBalance($data['u_id'], DistributionWalletManager::APPLY_TYPE_REDUCE_BALANCE,
                    $data['wd_amount']);

            } else {
                //钱包扣钱操作
                $retWallet = $walletMod->apply($data['u_id'], $data['wd_amount'], false);
            }

//            if ($data['wd_wallet_type'] == 1) {
//
//                //钱包扣钱操作
//                $retWallet = $walletMod->apply($data['u_id'], $data['wd_amount'], false);
//            } else {
//                //分销钱包扣钱操作
//                $retWallet = DistributionWalletManager::updateBalance($data['u_id'], DistributionWalletManager::APPLY_TYPE_REDUCE_BALANCE,
//                    $data['wd_amount']);
//            }
            //钱包申请提现
            wlog(['data'=>$data,'retWallet'=>$retWallet],'申请提现记录日志',4);
            $data['wd_balance'] = $retWallet;//这里要处理app提现，用$retWallet[1]，参考线上
            if ($data['wd_wallet_type'] != 2) {//表示app提现时候,记录剩余金额
                $data['wd_balance'] = $retWallet[1];
                }

            $retApply = $wdMod->add($data);
            if ($data['wd_wallet_type'] != 2) {//表示app提现时候
                //钱包明细流水
                $walletLog->write($data['u_id'], $data['wd_amount'], $retWallet[1], '钱包提现', 100, $retApply[1], false);
            }

            $walletMod->commit();
        } catch (\Exception $e) {
            $walletMod->rollback();
            throw $e;
        }

        return $retApply;
    }

}
