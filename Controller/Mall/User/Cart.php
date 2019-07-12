<?php

/**
 * 用户购物车
 * @author Administrator
 *
 */

namespace Controller\Mall\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;
use Framework\Lib\Validation;
use Rest\Mall\Facade\CommonManager;
use Rest\Mall\Facade\ItemManager;

class Cart extends BaseController
{

    private $uCartModel = null;
    private $goodsLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->uCartModel = new \Model\Mall\UserCart();
        $this->goodsLib = new \Lib\Mall\Goods();
    }

    /**
     * 增加商品到购物车
     *
     * @throws ModelException
     */
    public function add()
    {
        $g_id = app()->request()->params('g_id');
        $goodsNum = app()->request()->params('goodsNum', 1);
        //$goodsNum = 1;
        if (!$g_id) {
            throw new ParamsInvalidException("拍品Id必须");
        }
        if (!Validation::checkNumber($goodsNum)) {
            throw new ParamsInvalidException("goodsNum:参数错误");
        }
        $uid = $this->uid;
        $goodsInfo = $this->goodsLib->getGoodsInfo(array(
            'id' => $g_id
        ));
        if (!$goodsInfo) {
            throw new ServiceException("拍品不存在");
        }
        if ($uid == $goodsInfo['g_salesId']) {
            throw new ServiceException("不能加入自己的商品");
        }
        if ($goodsInfo['g_stock'] < $goodsNum) {
            throw new ServiceException("库存不足");
        }

        //处于秒杀价时:控制数量，改变价格
        $nowDate = date('Y-m-d H:i:s');
        if ($goodsInfo['g_secKillStart'] < $nowDate && $goodsInfo['g_secKillEnd'] > $nowDate) {
            //如果提交的数量大于该商品可以允许单次购买的数量
            if ($goodsNum > $goodsInfo['g_secKillNum']) {
                throw new ServiceException("超出限购数量");
            }

            //把秒杀商品价格存入购物车
            $goodsInfo['g_price'] = $goodsInfo['g_activityPrice'];
            $goodsInfo['isSecKill'] = 1;
        }else{
            $goodsInfo['isSecKill'] = 0;
        }

        $uCartItem = $this->uCartModel->add($uid, $g_id, $goodsInfo['g_sn'], $goodsInfo['g_name'], $goodsInfo['g_type'], $goodsNum, $goodsInfo['g_price'], $goodsInfo['g_salesId'], $goodsInfo['categoryName'], $goodsInfo['g_madeTime'], $goodsInfo['g_high'], $goodsInfo['g_width']);
        if (!$uCartItem) {
            throw new ModelException("保存到购物车失败");
        }
        $this->responseJSON($uCartItem);
    }

    /**
     * 购物车内商品删除
     */
    public function delete()
    {
        $g_id_str = app()->request()->params('g_id');
        if (!$g_id_str) {
            throw new ParamsInvalidException("拍品Id必须");
        }
        $data['g_id'] = explode(',', $g_id_str);
        $data['g_id'] = array_filter($data['g_id']);
        if (empty($data['g_id'])) {
            throw new ParamsInvalidException("商品id必须");
        }
        $data['u_id'] = $this->uid;

        $ret = $this->uCartModel->batchDelete($data);
        if (!$ret) {
            throw new ParamsInvalidException("删除商品失败");
        }

        $this->responseJSON('');
    }

    /**
     * 更改购物车商品数量
     *
     * @throws ModelException
     */
    public function updateNum()
    {
        $g_id = app()->request()->params('g_id');
        $goodsNum = app()->request()->params('goodsNum');
        $uid = $this->uid;
        if (!$g_id) {
            throw new ParamsInvalidException("拍品Id必须");
        }
        if (!is_numeric($goodsNum)) {
            throw new ParamsInvalidException("goodsNum:参数错误");
        }
        $goodsInfo = $this->goodsLib->getGoodsInfo(array(
            'id' => $g_id
        ));
        if (!$goodsInfo) {
            throw new ServiceException("拍品不存在");
        }
        if ($uid == $goodsInfo['g_salesId']) {
            throw new ServiceException("不能加入自己的商品");
        }
        if ($goodsInfo['g_stock'] < $goodsNum) {
            throw new ServiceException("库存不足");
        }

        //处于秒杀价时:控制数量，改变价格
        $nowDate = date('Y-m-d H:i:s');
        if ($goodsInfo['g_secKillStart'] < $nowDate && $goodsInfo['g_secKillEnd'] > $nowDate) {
            //如果提交的数量大于该商品可以允许单次购买的数量
            if(\Rest\Mall\Facade\OrderManager::isOverSecKillBuyNum($uid,$g_id,$goodsNum,$goodsInfo['g_secKillStart'],$goodsInfo['g_secKillEnd'],$goodsInfo['g_secKillNum'])){
                throw new ServiceException("每个用户最多购买{$goodsInfo['g_secKillNum']}件");
            }
            //如果提交的数量大于该商品可以允许单次购买的数量
            if ($goodsNum > $goodsInfo['g_secKillNum']) {
                throw new ServiceException("单次购买数量过多");
            }
        }

        $cartItemInfo = $this->uCartModel->oneByUidGid($uid, $g_id);
        if (!$cartItemInfo) {
            throw new ServiceException("购物车中无此项目");
        }
        $cItemId = $cartItemInfo['ucart_id'];
        $newNum = $this->uCartModel->updateNum($cItemId, $uid, $goodsNum);
        $this->responseJSON(array(
            'currentNum' => $newNum
        ));
    }

    /**
     * 分页查询
     *
     * @throws ModelException
     */
    public function queryByPage()
    {
        $act = app()->request()->params('act', '');
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 100);
        $params = array();
        $params['u_id'] = $this->uid;
        $params['ucart_goodsSaleUid'] = app()->request()->params('salesUid', '');
        $orderDistributionType = app()->request()->params('orderDistributionType', 0);

        list ($rows, $totalCount) = $this->uCartModel->lists($params, $page, $pageSize);

        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($rows, 'ucart_goodsSaleUid', array(
            'u_nickname' => 'ucart_nickname',
            'u_realname' => 'ucart_realname',
            'u_avatar' => 'ucart_avatar',
        ));

        $goods_ids_tmp = [];
        $voucherLib = new \Lib\Mall\Voucher();
        $t_list = $voucherLib->templateLists(['v_t_state' => 1, 'pageSize' => 100]);
        foreach ($t_list['list'] as $val) {
            if (!empty($val['v_t_limit_ids'])) {
                $goods_ids_tmp = array_merge($goods_ids_tmp, explode(',', $val['v_t_limit_ids']));
            }
        }
        $goods_ids_arr = array_unique($goods_ids_tmp);
        //获取购物车减价商品信息
        $reductionPriceData = $this->getReductionPriceData();
        $data = array();
        $arr = [];
        //用户分销传艺人身份
        $userExtendData = \Rest\User\Facade\UserManager::getOneUserExtend($this->uid);
        $userDistributionType = empty($userExtendData['u_distribution_type']) ? 0 : 1;
        foreach ($rows as $k => $v) {
            $goodsInfo = $this->goodsLib->getGoodsInfo(array('id' => $v['g_id']));
            if (!$goodsInfo['g_stock']) {
                $v['ucart_goodsNum'] = 0;
            }
            //非会员价
            $v['g_price'] = CommonManager::getOrderGoodsPrice($goodsInfo, 0 ,$orderDistributionType);
            //会员价
            $v['vipPrice'] = CommonManager::getOrderGoodsPrice($goodsInfo, 1 ,$orderDistributionType);
            //会员折扣
            $v['goodsDiscountAmount'] = CommonManager::getGoodsDiscountAmount($goodsInfo, $userDistributionType, $orderDistributionType);
            $v['g_stock'] = $goodsInfo['g_stock'];
            $v['itemAttr'] = $goodsInfo['itemAttr'];
            if (in_array($v['g_id'], $goods_ids_arr)) {
                $v['is_use_voucher'] = 1;
            }
            $v['is_own_shop'] = (int)$goodsInfo['is_own_shop'];
            $certificationModel = new \Model\User\Certification();
            $v['certification'] = $certificationModel->getType($v['ucart_goodsSaleUid']);
            $v['image'] = $goodsInfo['image'];
            $v['g_surfaceImg']=$goodsInfo['g_surfaceImg'];
            $v['g_freightFee']=$goodsInfo['g_freightFee'];
            $v['g_isFreightFree']=$goodsInfo['g_isFreightFree'];
            $v=$this->updateCartGoodsPrice($v,$goodsInfo);
            //降价幅度
            if (isset($reductionPriceData['reductionPriceGoods'][$v['g_id']])) {
                $v['reductionPriceAmount'] = $reductionPriceData['reductionPriceGoods'][$v['g_id']];
            } else {
                $v['reductionPriceAmount'] = 0;
            }
            if ($act == 'new') {
                if ($v['is_own_shop'] == 1) {  //自营商品单独拿出来放到最上面
                    $v['ucart_realname'] = '掌玩自营';
                    $arr[] = $v;
                } else {
                    $data[$v['ucart_goodsSaleUid']][] = $v;
                }
            } else {
                $data[] = $v;
            }
        }

        if ($arr) {
            array_unshift($data, $arr);
        }

        unset($reductionPriceData['reductionPriceGoods']);
        $this->responseJSON(array(
            'rows' => array_values($data),
            'totalCount' => $totalCount,
            'reductionPriceData' =>$reductionPriceData
        ));
    }
    private function updateCartGoodsPrice($ucartInfo,$goodsInfo)
    {
        if($goodsInfo && is_array($goodsInfo)){
            //处于秒杀价时:控制数量，改变价格
            $nowDate = date('Y-m-d H:i:s');
            if ($goodsInfo['g_secKillStart'] < $nowDate && $goodsInfo['g_secKillEnd'] > $nowDate) {
                //把秒杀商品价格存入购物车
                $goodsInfo['g_price'] = $goodsInfo['g_activityPrice'];
            }
        }
        $ucartInfo['ucart_goodsPrice']=$goodsInfo['g_price'];
        app('mysqlbxd_app')->update('user_cart',[
            'ucart_goodsPrice'=>$ucartInfo['ucart_goodsPrice'],
        ],[
            'ucart_id'=>$ucartInfo['ucart_id']
        ]);
        return $ucartInfo;
    }

    /**
     * 获取购物车中降价商品统计信息
     * @return array
     */
    private function getReductionPriceData()
    {
        $data = ['reductionPriceTotalNumber' => 0, 'reductionPriceTotalAmount' => 0];
        $reductionPriceGoods = [];
        $cartList = $this->uCartModel->listsByUid($this->uid);
        if ($cartList) {
            $cartGoodsData = array_column($cartList, null, 'g_id');
            $goodsIds = array_column($cartList, 'g_id');
            $goodsList = ItemManager::getItemById(implode(',', $goodsIds));
            if ($goodsList) {
                foreach ($goodsList as $goodsItem) {
                    if (isset($cartGoodsData[$goodsItem['g_id']])) {
                        $cartGoodsItem = $cartGoodsData[$goodsItem['g_id']];
                        if ($cartGoodsItem['ucart_joinGoodsPrice'] > $goodsItem['g_price']) {
                            $data['reductionPriceTotalNumber']++;
                            $reductionPrice = round($cartGoodsItem['ucart_joinGoodsPrice'] - $goodsItem['g_price'], 2);
                            $data['reductionPriceTotalAmount'] += $reductionPrice;
                            $reductionPriceGoods[$goodsItem['g_id']] = $reductionPrice;
                        }
                    }
                }
            }
        }
        $data['reductionPriceGoods'] = $reductionPriceGoods;

        return $data;
    }
}
