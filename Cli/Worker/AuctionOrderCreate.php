<?php

/**
 * 拍品订单自动生成 => 结拍后，自动给出价最高的用户生成订单
 */
namespace Cli\Worker;

use Lib\Mall\Order;

class AuctionOrderCreate
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_common');
    }

    public function run()
    {
        while (true) {
            $auctionData = $this->getAuctionData();
            $orderLib = new Order();

            if ($auctionData) {
                foreach ($auctionData as $item) {
                    $params['aid'] = $item['a_id'];
                    $resMall = $orderLib->auctionOrderCreate($params);
                }
            }
            exitTask('03:00:00', '03:02:00');
            sleep(180);
        }
    }

    private function getAuctionData()
    {
        $sql = "SELECT * FROM `auction` WHERE a_orderCreateStatus=0 AND a_auditStatus =1 AND `a_endDate`< NOW();";
        $data = $this->db->select($sql);
        return $data;
    }
}
