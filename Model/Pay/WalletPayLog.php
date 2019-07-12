<?php
namespace Model\Pay;

use Lib\Base\BaseModel;
use Exception\ServiceException;

/**
 * 钱包支付明细
 */
class WalletPayLog extends BaseModel
{

    protected $table = 'wallet_pay_log';

    protected $id = 'wpl_id';

    public function __construct()
    {
        parent::__construct($table = 'wallet_pay_log', $id = 'wpl_id', $mysqlDbFlag = 'mysqlbxd_pay_center');
    }


    /**
     * undocumented function summary.
     *
     * Undocumented function long description
     *
     * @param type var Description
     *
     * @return return type
     */
    public function write($uid, $totalFee, $balance, $desc, $type, $tradeId, $add, $orderSn='', $payType=0, $incomeType=0)
    {
        $data['u_id'] = $uid;
        $data['wpl_type'] = $add ? 1: 2;
        $data['wpl_log_type'] = $type;
        $data['wpl_tradeId'] = $tradeId ? $tradeId : $this->getSn();
        $data['wpl_amount'] = $totalFee;
        $data['wpl_balance'] = $balance;
        $data['wpl_desc'] = $desc;
        $data['wpl_time'] = date('Y-m-d H:i:s');
        $data['wpl_orderSn'] = $orderSn;
        $data['wpl_payType'] = $payType;
        $data['wpl_income_type'] = $incomeType;
        list ($count, $id) = $this->insert($data);
        if ($id) {
        	return [$id, $data];
        } else {
        	return false;
        }
    }

    public function lists($u_id)
    {
        $whereArr = $bindData = [];

        if ($u_id) {
            $whereArr[] = 'u_id = :u_id';
            $bindData[':u_id'] = $u_id;
        } else {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "select * from {$this->table} {$where} order by wpl_time desc";
        $rows = $this->mysql->select($sql, $bindData);
        if($rows) {
            $wdMod = new \Model\Pay\Withdrawals();
            foreach ($rows as &$row) {
                if ($row['wpl_log_type'] == 100) {
                    $withdrawals = $wdMod->oneById($row['wpl_tradeId']);
                    if ($withdrawals) {
                        $row['withdrawals_status'] = $withdrawals['wd_status'];
                    } else {
                        $row['withdrawals_status'] = 0;
                    }
                }
            }
        }
        return $rows;
    }

    private function getSn()
    {
        $time = ceil(microtime(true) * 1000);
        $rand = rand(1000, 9999);
        $str = $time . $rand;

        return strrev($str);
    }

    /**
     * 查询
     */
    public function findByTradeId($tradeId)
    {
        $sql = "select * from {$this->table} where wpl_tradeId = :wpl_tradeId";
        $bindData = [
            'wpl_tradeId' => $tradeId,
        ];
        $rows = $this->mysql->select($sql, $bindData);
        return $rows ?: false;
    }


}
