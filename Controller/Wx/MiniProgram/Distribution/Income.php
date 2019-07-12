<?php
/**
 * 艺术赚赚小程序
 */
namespace Controller\Wx\MiniProgram\Distribution;

use Model\Pay\Withdrawals;
use Rest\Mall\Facade\DistributionManager;
use Rest\Pay\Facade\DistributionWalletManager;
use Rest\User\Facade\UserManager;

class Income extends Common
{
    private $goodsModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsModel = new \Model\Mall\Goods();
    }

    public function getMyIncomeStatistics()
    {
        $data = [];
        $uid = $this->uid;
        $distributionWalletData = DistributionWalletManager::getByUid($uid);
        if ($distributionWalletData) {
            $withdrawalsModel = new Withdrawals();
            //待审核的分销提现记录
            $applyingWithdrawals = 0;
            $params['u_id'] = $uid;
            $params['wallet_type'] = $withdrawalsModel::USER_DISTRIBUTION_WALLET;
            //申请中参数
            $params['status']       = [1,2];
            $applyingWithdrawalsList = $withdrawalsModel->getList($params);
            if ($applyingWithdrawalsList) {
                foreach ($applyingWithdrawalsList as $applyingWithdrawalsItem) {
                    $applyingWithdrawals += $applyingWithdrawalsItem['wd_amount'];
                }
            }
            //已审核的分销提现记录
            $auditedWithdrawals = 0;
            $params['u_id'] = $uid;
            $params['wallet_type'] = $withdrawalsModel::USER_DISTRIBUTION_WALLET;
            $params['status'] = 4;
            $auditedWithdrawalsList = $withdrawalsModel->getList($params);
            if ($auditedWithdrawalsList) {
                foreach ($auditedWithdrawalsList as $auditedWithdrawalsItem) {
                    $auditedWithdrawals += $auditedWithdrawalsItem['wd_amount'];
                }
            }
            //用户可用余额（当前剩余可提现）
            $data['balance'] = $this->convertUnit($distributionWalletData['dw_balance']);
            //销售收入（商品分销收入）
            $data['goodsDistributionAmount'] = $this->convertUnit($distributionWalletData['dw_goods_d_amount']);
            //拉新收入（拉新收入）
            $data['newVipAmount'] = $this->convertUnit($distributionWalletData['dw_new_vip_amount']);
            //申请中的提现
            $data['applyingWithdrawals'] = $this->convertUnit($applyingWithdrawals);
            //当前剩余可提
            $data['remainingWithdrawals'] = $data['balance'] >= 10 ? $data['balance'] : 0;      // 大于10元   不能提现
            //拉新在途收益
            $data['newVipFreezing'] = $this->convertUnit($distributionWalletData['dw_new_vip_freezing']);
            //销售在途收益
            $data['goodsDistributionFreezing'] = $this->convertUnit($distributionWalletData['dw_goods_d_freezing']);
            //累计提现
            $data['auditedWithdrawals'] = $this->convertUnit($auditedWithdrawals);
            //累计收益
            $data['totalIncome'] = $this->convertUnit($distributionWalletData['dw_goods_d_amount'] + $distributionWalletData['dw_new_vip_amount']);
        }

        return $this->responseJSON($data);
    }

    public function getGoodsDistributionData()
    {
        $data = [];
        $uid = $this->uid;
        $distributionWalletData = DistributionWalletManager::getByUid($uid);
        if ($distributionWalletData) {
            //订单总金额
            $orderTotalAmount = 0;
            //订单商品总数量
            $orderTotalGoodsNumber = 0;
            //分销明细记录
            $params['d_uid'] = $uid;
            $params['d_type'] = 1;
            $params['dl_status'] = 2;
            list(, $list) = DistributionManager::query($params);
            $listData = [];
            foreach ($list as $item) {
                $orderTotalAmount += $item['o_price'];
                $orderTotalGoodsNumber += $item['dl_goods_num'];
                $listData[] = [
                    'order_sn' => $item['o_sn'],                             //订单编号
                    'order_price' => $item['o_price'],                     //订单价格
                    'distribution_commission' => $this->convertUnit($item['dl_price'])  //佣金
                ];
            }
            //所得佣金
            $data['goodsDistributionAmount'] = $this->convertUnit($distributionWalletData['dw_goods_d_amount']);
            //销售额
            $data['orderTotalAmount'] = $orderTotalAmount;
            //销售量
            $data['orderTotalGoodsNumber'] = $orderTotalGoodsNumber;
            $data['list'] = $listData;
        }
        return $this->responseJSON($data);
    }

    public function getInviteNewVipData()
    {
        $data = [];
        $uid = $this->uid;
        $distributionWalletData = DistributionWalletManager::getByUid($uid);
        if ($distributionWalletData) {
            //分销明细记录
            $params['d_uid'] = $uid;
            $params['d_type'] = 2;
            $params['dl_status'] = 2;
            list($inviteNewVipTotalNumber, $list) = DistributionManager::query($params);
            $listData = [];
            if ($list) {
                $uidArray = array_column($list, 'u_id');
                $userInfoList = UserManager::getUserInfoByUserIds(implode(',', $uidArray));
                $userInfoList = array_column($userInfoList, null, 'u_id');
                foreach ($list as $item) {
                    $listData[] = [
                        'time' => $item['dl_updateDate'],
                        'phone' => isset($userInfoList[$item['u_id']]['u_phone']) ? $userInfoList[$item['u_id']]['u_phone'] : '',
                        'invite_commission' => $this->convertUnit($item['dl_price'])
                    ];
                }
            }
            //拉新传艺人收入
            $data['inviteNewVipAmount'] = $this->convertUnit($distributionWalletData['dw_new_vip_amount']);
            //邀请总人数
            $data['inviteNewVipTotalNumber'] = $inviteNewVipTotalNumber;
            $data['list'] = $listData;
        }

        return $this->responseJSON($data);
    }

    private function convertUnit($value)
    {
        return round($value / 100, 2);
    }
}
