<?php

/**
 * 代金券到期
 */
namespace Cli\Worker;

use Lib\Mall\Voucher;

class VoucherClose
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }
    public function run()
    {
        while (true) {
            $vouchers = $this->fetchData();
            $voucherLib = new Voucher();

            if ($vouchers) {
                foreach ($vouchers as $k => $v) {
                    var_dump($v);
                    $params['vid'] = $v['v_id'];
                    $params['status'] = 2;
                    $resMall = $voucherLib->cliClose($params);
                }
            }
            exitTask('03:00:00', '03:02:00');
            sleep(180);
        }
    }

    private function fetchData()
    {
        $sql = "select * from `voucher` where v_status = 0 and UNIX_TIMESTAMP(now())> UNIX_TIMESTAMP(v_endDate)";
        $vouchers = $this->db->select($sql);
        return $vouchers;
    }





}
