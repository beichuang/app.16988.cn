<?php

/**
 * 拍品订单自动取消 => 拍品订单生成后三天内未付款
 */
namespace Cli\Worker;

use Lib\Mall\Order;

class AuctionOrderCancle
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }
    public function run()
    {
        while (true) {
            $orders = $this->fetchData();
            $orderLib = new Order();

            if ($orders) {
                foreach ($orders as $v) {
                    $params['uid'] = $v['u_id'];
                    $params['id'] = $v['o_id'];
                    $params['reason'] = '拍品自动下单后，三天内未付款，订单自动取消！';
                    $resMall = $orderLib->cliCancel($params);
                }
            }
            exitTask('03:00:00', '03:02:00');
            sleep(180);
        }
    }

    private function fetchData()
    {
        $timeSpan = 259200; // 3天 = 60 * 60 * 60 * 24 * 3;
        $sql = "select * from `order` where o_status = 0 and g_type = 4 and UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(o_createDate) >= {$timeSpan}";
        $orders = $this->db->select($sql);
        return $orders;
    }
}
