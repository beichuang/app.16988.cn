<?php

namespace Lib\Mall;

use Model\User\Invite;
use Rest\Mall\Facade\VoucherTemplateManager;

class Goods extends MallBase {

    /**
     * 商品列表
     */
    public function lists($params) {
        $resMall = $this->passRequest2Mall($params, 'mall/item/list');
        return $resMall;
    }

    /**
     * 查询商品信息
     */
    public function itemQuery($params) {
        return $this->passRequest2Mall($params, 'mall/item/query');
    }

    /**
     * 查询精选产品信息
     */
    public function itemHandpickQuery($params) {
        return $this->passRequest2Mall($params, 'mall/item/Handpick/query');
    }


    /**
     * 查询秒杀产品信息
     */
    public function itemSecKillQuery($params) {
        return $this->passRequest2Mall($params, 'mall/item/seckill/query');
    }

    /**
     * 存储砍价用户信息,openid
     */
    public function itemOpenidQuery($params) {
        return $this->passRequest2Mall($params, 'mall/item/openid/query');
    }

    /**
     * 查询砍价产品信息
     */
    public function itemCutQuery($params) {
        return $this->passRequest2Mall($params, 'mall/item/cut/query');
    }

    /**
     * 发起砍价
     */
    public function itemCutPost($params) {

        return $this->passRequest2Mall($params, 'mall/item/cut/post');
    }

    /**
     * 发起砍价
     */
    public function itemHelpCutPost($params) {
        return $this->passRequest2Mall($params, 'mall/item/help/cut/post');
    }

    /**
     * 查询推荐产品信息
     */
    public function itemRecommendQuery($params) {
        return $this->passRequest2Mall($params, 'mall/item/recommend');
    }

    /**
     * 新增/修改商品
     */
    public function itemPost($params) {
        return $this->passRequest2Mall($params, 'mall/item/post');
    }

    /**
     * 新增/修改多规格商品
     */
    public function itemCollectionPost($params) {
        return $this->passRequest2Mall($params, 'mall/item/collection/save');
    }

    /**
     * 查询详细
     */
    public function detailGet($params) {
        return $this->passRequest2Mall($params, 'mall/item/detail/get');
    }

    /**
     * 更改收藏次数
     *
     */
    public function favoriteTimesChange($params) {
        return $this->passRequest2Mall($params, 'mall/item/favorite/times/change');
    }

    /**
     * 更新状态
     */
    public function updateStatus($params) {
        return $this->passRequest2Mall($params, 'mall/items/status/update');
    }

    /**
     * 更新状态
     */
    public function updateGoodsBox($params) {
        return $this->passRequest2Mall($params, 'mall/items/goods/box/update');
    }

    /**
     * 查询商品详情
     *
     * @param array $params            
     * @return boolean
     */
    public function getGoodsInfo($params) {
        $mallRes = $this->itemQuery($params);
        if (!isset($mallRes['count']) || !$mallRes['count'] || !isset($mallRes['list']) || !isset(
                        $mallRes['list'][0])) {
            return false;
        }
        return $mallRes['list'][0];
    }

    /** 临时使用，更改图片宽高
     * @param $params
     * @return mixed
     *
     */
    public function once($params) {
        return $this->passRequest2Mall($params, 'mall/item/once');
    }

    /**  更换用户的作品数量
     * @param $params
     * @return mixed
     *
     */
    public function changeGoodsNum($params) {
        return $this->passRequest2Mall($params, 'mall/item/change/goodsnum');
    }

    /** 商品 点赞/取消点赞
     * @param $params
     */
    public function goodsLike($params) {
        return $this->passRequest2Mall($params, 'mall/item/goods/like');
    }

    /** 关闭订单
     * 仅供cli脚本使用
     * @param $params
     */
    public function cliChangeGoodsNum($params){
        return $this->cliPassRequest2Mall($params, 'mall/item/change/goodsnum');
    }

    /** 我的业绩
     * @param $params
     * @return mixed
     */
    public function performance($params) {
        return $this->passRequest2Mall($params, 'mall/performance');
    }

    public function getCommissionRate($userId, $userInviteModel= null)
    {
        if (empty($userInviteModel)) {
            $userInviteModel = new Invite();
        }

        if ($userId) {
            $inviteCount = $userInviteModel->getCount($userId);
            if ($inviteCount >= 15) {
                $rate = 10;
            } elseif ($inviteCount >= 12) {
                $rate = 9;
            } elseif ($inviteCount >= 9) {
                $rate = 8;
            } elseif ($inviteCount >= 6) {
                $rate = 7;
            } elseif ($inviteCount >= 3) {
                $rate = 6;
            } else {
                $rate = 5;
            }
        } else {
            $rate = 5;
        }

        return $rate;
    }

    /**
     * 获取订单评论的商品id
     * @param $o_id
     */
    public function getOrderCommentGoodsIds($o_id)
    {
        $commentGoods=[];
        if($commentGoodsIds=app('mysqlbxd_app')->select('select g_id from goods_comment where o_id='.$o_id)){
            if($commentGoodsIds){
                $commentGoods=array_column($commentGoodsIds,'g_id');
            }
        }
        return $commentGoods;
    }

    /**
     * 从属性中取尺寸
     * @param $attrs
     * @return string
     */
    public function getAttrFromGoodsAttrs($attrs,$attrName)
    {
        $findAttrs=[];
        if($attrName && $attrs && is_array($attrs)){
            foreach ($attrs as $attr){
                if($attr['ga_key']==$attrName){
                    $findAttrs[]=$attr['ga_value'];
                }
            }
        }
        return $findAttrs;
    }
    /**
     * 获取商品有效优惠券类型
     * @param int $goodId 商品id
     * @return array
     */
    public function getVoucherType($goodId)
    {
        $result = [];
        //有效期内（领取有效期+使用有效期）
        $condition['validityPeriod'] = 1;
        //指定商品可用
        $condition['scope'] = 1;
        //指定商品id
        $condition['goodsId'] = $goodId;
        //领取方式-免费领取
        $condition['getType'] = 4;
        $list = VoucherTemplateManager::getTemplateList($condition);
        if ($list) {
            foreach ($list as $item) {
                if (!isset($result[$item['v_t_type']])) {
                    $result[] = $item['v_t_type'];
                }
            }
        }

        return $result;
    }

    /**
     * 获取商品可领取优惠券
     * @param int $goodId 商品id
     * @return mixed
     */
    public function getCanReceiveVoucher($goodId)
    {
        //领取有效期内
        $condition['receiveValidityPeriod'] = 1;
        //指定商品可用
        $condition['scope'] = 1;
        //指定商品id
        $condition['goodsId'] = $goodId;
        //领取方式-免费领取
        $condition['getType'] = 4;
        $list = VoucherTemplateManager::getTemplateList($condition);
        return $list;
    }
}
