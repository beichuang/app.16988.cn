<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/7/4
 * Time: 11:35
 */

namespace Controller\Wx\MiniProgram\Distribution;


use Lib\Base\BaseController;
use Lib\Mall\Goods;
use Rest\User\Facade\UserManager;

class Common extends BaseController
{
    private $goodsLib = null;

    public function __construct()
    {
        $this->goodsLib = new Goods();
        parent::__construct();
    }

    protected function getCommission($userId, $price, $userInviteModel = null)
    {
        $rate = $this->goodsLib->getCommissionRate($userId, $userInviteModel);
        return number_format($price * $rate * 0.01, 2);
    }

    protected function getUserDistributionType($uid)
    {
        $userDistributionType = 0;
        if ($uid) {
            $userExtendData = UserManager::getOneUserExtend($uid);
            if ($userExtendData) {
                $userDistributionType = $userExtendData['u_distribution_type'];
            }
        }

        return $userDistributionType;
    }

    protected function getGoodsDiscountAmount($price)
    {
        return round($price * 15 / 100, 2);
    }

    protected function getGoodsVipPrice($price)
    {
        return round($price * 85 / 100, 2);
    }
}