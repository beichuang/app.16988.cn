<?php

/**
 * 订单自动取消 => 买家下单后，半小时内未付款
 */

namespace Cli\Worker;

use Lib\Mall\Order;

class AddCutSales
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }

    // 增加砍价商品销量：每小时增加1-3个
    public function run()
    {
        while (true) {
            $rand = mt_rand(1, 5);
            $sql = "UPDATE goods SET g_cutSales=g_cutSales + {$rand} WHERE g_type=6";
            $orders = $this->db->query($sql);
            echo $orders;
            sleep(7200);
        }
    }
}
