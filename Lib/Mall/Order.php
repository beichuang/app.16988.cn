<?php
namespace Lib\Mall;

class Order extends MallBase
{



    /**
     * 新增订单,钱包充值
     */
    public function recharge($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/recharge');
    }

    /**
     * 新增订单,打赏
     */
    public function gratuity($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/gratuity');
    }

    /**
     * 打赏推送
     */
    public function sendmsg($params)
    {
        return $this->passRequest2Mall($params, 'pay/send/msg');
    }

    /**
     * 新增订单,宝贝
     */
    public function add($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/add');
    }

    /** 多名家多商品购买下单
     * @param $params
     * @return mixed
     */
    public function addmore($params){
        return $this->passRequest2Mall($params, 'mall/order/add/more');
    }

    /**
     * 订单列表
     */
    public function lists($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/list');
    }

    /**
     * 取消订单
     */
    public function cancel($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/cancel');
    }

    /**
     * 取消订单
     * 仅供cli脚本使用
     */
    public function cliCancel($params)
    {
        return $this->cliPassRequest2Mall($params, 'mall/order/cancel');
    }

    /**
     * 申请退款
     */
    public function refund($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/refund');
    }

    /**
     * 卖确认退款
     */
    public function confirmRefund($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/confirm/refund');
    }



    /**
     * 订单详情
     */
    public function detail($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/detail/get');
    }

    /**
     * 更新物流信息
     */
    public function update($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/update');
    }

    /**
     * 查询订单
     */
    public function query($params)
    {
        return $this->passRequest2Mall($params, 'mall/order/query');
    }

    /**
     * 订单完成，确认收货
     */
    public function finish($params)
    {
        $res= $this->passRequest2Mall($params, 'mall/order/finish');
        return $res;
    }

    /**
     * 订单完成，确认收货
     * 仅供cli脚本使用
     */
    public function cliFinish($params)
    {
        $res= $this->cliPassRequest2Mall($params, 'mall/order/finish');
        return $res;
    }

    /** 删除订单
     * @param $params
     */
    public function delete($params){
        return $this->cliPassRequest2Mall($params, 'mall/order/delete');
    }

    /** 关闭订单
     * 仅供cli脚本使用
     * @param $params
     */
    public function cliClose($params){
         return $this->cliPassRequest2Mall($params, 'mall/order/close');
    }

    /** 补单
     * @param $params
     * @return mixed
     */
    public function replace($params){
        return $this->passRequest2Mall($params, 'pay/order/replace');
    }

    /** 记录订单的支付渠道
     * @param $params
     * @return mixed
     */
    public function orderExtend($params){
        return $this->passRequest2Mall($params, 'mall/order/extend/post');
    }

    /**
     * 拍品自动生成订单
     * @param $params
     * @return mixed
     */
    public function auctionOrderCreate($params){
        return $this->cliPassRequest2Mall($params, 'mall/auction/order/add');
    }

    /**
     * 订单保存收货地址
     * @param $params
     * @return mixed
     */
    public function orderSaveAddress($params){
        return $this->passRequest2Mall($params, 'mall/order/address/save');
    }

    /**
     * 新增定制订单
     * @param $params
     * @return mixed
     */
    public function addCustomOrder($params){
        return $this->passRequest2Mall($params, 'mall/custom/order/add');
    }

    /**
     * 更新定制订单
     * @param $params
     * @return mixed
     */
    public function updateCustomOrder($params){
        return $this->passRequest2Mall($params, 'mall/custom/order/save');
    }
}
