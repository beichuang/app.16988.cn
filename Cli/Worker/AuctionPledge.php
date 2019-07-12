<?php

/**
 * 拍品结拍后保证金处理
 */
namespace Cli\Worker;

use Framework\Helper\FileHelper;
use Lib\Common\AppMessagePush;
use Lib\User\UserSms;
use Rest\Mall\PledgeManager;

class AuctionPledge
{
    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_common');
    }

    public function run()
    {
//        while (true) {
            $auctionData = $this->fetchData();
            $k = 0;
//            var_dump($auctionData[$k]);die;
            PledgeManager::pledgeFinal($auctionData[$k]['a_id'], $auctionData[$k]['a_orderUserId']);
            die;

            if ($auctionData) {
                foreach ($auctionData as $key => $val) {
                    PledgeManager::pledgeFinal($val['a_id'], $val['a_orderUserId']);
                }
            }
            //wlog('保证金定时任务运行中', 'pledge');

//            exitTask('03:00:00', '03:02:00');
//            sleep(10);
//        }
    }


    //审核通过  已生成订单 已经结拍
    private function fetchData()
    {
        //$sql = "SELECT * FROM `auction` WHERE a_auditStatus = 1  AND `a_endDate` <= NOW() AND `a_endDate` >= DATE_SUB(NOW(), INTERVAL +30 MINUTE)";
        $sql = "SELECT * FROM `auction` WHERE a_auditStatus = 1  and a_orderCreateStatus = 1 AND `a_endDate` <= NOW()";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $key => $val) {
                $s = "SELECT * FROM `auction` WHERE a_auditStatus = 1  and a_orderCreateStatus = 1 AND `a_endDate` <= NOW()";
            }
        }
        return $data;
    }

}
