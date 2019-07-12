<?php

/**
 * 订单自动完成 => 调用第三方物流接口，签收后7天变为自动确认收货
 */
namespace Cli\Worker;

use kdNiao\kdNiao;
use Lib\Mall\Order;

class OrderFinish
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }

    public function run()
    {
        while (true) {
            //1.将待收货的订单更新签收时间
            $tobeReceivedOrders = $this->getTobeReceivedOrder();
            if ($tobeReceivedOrders) {
                $kdNiaoClass = new kdNiao();
                foreach ($tobeReceivedOrders as $tobeReceivedOrder) {
                    if (!empty($tobeReceivedOrder['o_expressSn']) && !empty($tobeReceivedOrder['o_expressCompany'])) {
                        $logisticsJsonData = $kdNiaoClass->orderTracesSubByJson($tobeReceivedOrder['o_expressSn'],
                            $tobeReceivedOrder['o_expressCompany']);
                        $logisticsData = json_decode($logisticsJsonData, true);
                        if ($logisticsData && !empty($logisticsData['Success']) && !empty($logisticsData['State']) && $logisticsData['State'] == 3) {
                            $this->updateOrderDeliveryDate($tobeReceivedOrder['o_id']);
                        }
                    }
                }
            }

            //2.更新签收时间7天后的订单为已收货
            $orders = $this->fetchData();
            if ($orders) {
                $orderLib = new Order();
                if ($orders) {
                    foreach ($orders as $k => $v) {
                        $params['uid'] = $v['u_id'];
                        $params['osn'] = $v['o_sn'];
                        $resMall = $orderLib->cliFinish($params);
                    }
                }
            }
            exitTask('03:00:00', '03:02:00');
            sleep(1440);
        }
    }

    /**
     * 获取待收货且未签收的订单
     */
    private function getTobeReceivedOrder()
    {
        //发货时间距离现在30天，防止历史数据一直不签收，一直请求物流接口
        $sql = "select * from `order` where o_status = 2 and o_deliveryDate IS NULL AND UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(o_shippingDate) < 2592000";
        return $this->db->select($sql);
    }

    /**
     * 更新订单签收时间
     * @param $oid
     */
    private function updateOrderDeliveryDate($oid)
    {
        $deliveryDate = date('Y-m-d H:i:s');
        $sql = 'UPDATE `order` SET o_deliveryDate=:deliveryDate WHERE o_id=:oid';
        $this->db->query($sql, [':deliveryDate' => $deliveryDate, ':oid' => $oid]);
    }

    /**
     * 获取待收货且签收时间超过7天的订单
     * @return mixed
     */
    private function fetchData()
    {
        $sql = "select * from `order` where o_status = 2 and o_deliveryDate IS NOT NULL AND UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(o_deliveryDate) >= 604800";
        $orders = $this->db->select($sql);
        return $orders;
    }
}
