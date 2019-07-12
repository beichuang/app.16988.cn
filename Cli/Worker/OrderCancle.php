<?php

/**
 * 订单自动取消 => 买家下单后，半小时内未付款
 */
namespace Cli\Worker;

use Lib\Mall\Order;

class OrderCancle
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }
    public function run()
    {
        $orders = $this->fetchData();
        $orderLib = new Order();
        if ($orders) {
            foreach ($orders as $k => $v) {
                $params['uid'] = $v['u_id'];
                $params['id'] = $v['o_id'];
                $params['reason'] = '买家下单后，半小时内未付款，订单自动取消！';
                $resMall = $orderLib->cliCancel($params);
            }
        }
    }

    private function fetchData()
    {
        $sql = "select * from `order` where o_status = 0 and g_type in (1,5,6) and UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(o_createDate) >= 1800 and o_createDate>='2017-08-01 00:00:00' ";
        $orders = $this->db->select($sql);
        return $orders;
    }

}
