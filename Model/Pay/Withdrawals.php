<?php

namespace Model\Pay;

use Lib\Base\BaseModel;

/**
 * 钱包提现
 */
class Withdrawals extends BaseModel {
    const USER_WALLET = 1; //用户钱包
    const USER_DISTRIBUTION_WALLET = 2; //用户分销钱包
    protected $table = 'withdrawals';
    protected $id = 'wd_id';
    protected $field = array(
        "u_id",
        "wd_amount",
        "wd_type",
        "wd_accountRemark",
        "wd_bankBranch",
        "wd_account",
        "wd_name",
        "wd_tel",
        "wd_ctime",
        "wd_status",
        "wd_utime",
        "wd_balance",
    );

    public function __construct() {
        parent::__construct($this->table, $this->id, 'mysqlbxd_pay_center');
    }

    /**
     * 添加提现申请
     */
    public function add($data) {
        foreach ($data as $key => $value) {
            if (isset($this->field[$key])) {
                unset($data[$key]);
            }
        }
        return $this->insert($data);
    }

    public function getList($conditions)
    {
        $sql = 'SELECT * FROM withdrawals WHERE 1=1';
        $sqlParams = [];
        if ($conditions['u_id']) {
            $sql .= ' AND u_id=:u_id';
            $sqlParams[':u_id'] = $conditions['u_id'];
        }

        if ($conditions['wallet_type']) {
            $sql .= ' AND wd_wallet_type=:wd_wallet_type';
            $sqlParams[':wd_wallet_type'] = $conditions['wallet_type'];
        }

        if ($conditions['status']) {
            if(!is_array($conditions['status'])){
                $sql .= ' AND wd_status=:wd_status';
                $sqlParams[':wd_status'] = $conditions['status'];
            }else{
                $sql  .= "  AND  wd_status  in  ('".implode("','",$conditions['status'])."') ";
            }
        }
        return app('mysqlbxd_pay_center')->select($sql, $sqlParams);
    }
}
