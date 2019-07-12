<?php

/**
 * 订单自动关闭 => 买家确认收货后（自动确认+手动确认），7天后订单自动关闭，卖家的冻结金额转入正常账户
 */
namespace Cli\Worker;

use Lib\Mall\Order;

class OrderClose
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
                //var_dump($orders);die;
                foreach ($orders as $k => $v) {
                    $params['uid'] = $v['u_id'];
                    $params['osn'] = $v['o_sn'];
                    $resMall = $orderLib->cliClose($params);
                    if(app('mysqlbxd_mall_user')->inTransaction()){
                        app('mysqlbxd_mall_user')->rollBack();
                    }
                }
            }
            exitTask('03:00:00', '03:02:00');
            sleep(180);
        }
    }

    private function fetchData()
    {
        $sql = "select * from `order` where o_status = 3 and UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(o_receivedDate) >= 604800 and o_createDate>='2018-02-01 00:00:00'";
        $orders = $this->db->select($sql);
        return $orders;
    }





}
