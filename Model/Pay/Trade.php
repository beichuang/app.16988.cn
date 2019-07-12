<?php
namespace Model\Pay;

use Lib\Base\BaseModel;
use Exception\ServiceException;

/**
 * 交易流水
 */
class Trade extends BaseModel
{

    protected $table = 'trade';

    protected $id = 'tr_id';


    public function __construct()
    {
        parent::__construct($table = 'trade', $id = 'tr_id', $mysqlDbFlag = 'mysqlbxd_pay_center');
    }

    /**
     * 获取交易信息
     *
     * @param int $tradeId
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function getTrade($tradeId)
    {
        $trade = $this->one("tr_tradeId=:tr_tradeId", array(
            'tr_tradeId' => $tradeId
        ));
        return $trade;
    }

    /** 购物车获取交易信息
     * @param $tradeId
     * @return mixed
     * @throws \Exception\ParamsInvalidException
     */
    public function getCartTrade($tradeId)
    {
        $whereArr = $bindData = [];

        if ($tradeId) {
            $whereArr[] = 'tr_uniqueSn = :tr_uniqueSn';
            $bindData[':tr_uniqueSn'] = $tradeId;
        } else {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "select * from {$this->table} {$where} order by tr_createDate asc";
        $rows = $this->mysql->select($sql, $bindData);

        return $rows;
    }


    /**
     * 更新状态
     *
     * @param string $tradeId
     * @param int $status
     * @throws \Exception\ModelException
     * @return number
     */
    public function updateStatus($tradeId, $status)
    {
        if (! is_numeric($status)) {
            throw new \Exception\ParamsInvalidException("状态只能是数字");
        }
        $data = array(
            'tr_status' => $status,
            'tr_updateDate' => date('Y-m-d H:i:s'),
        );

       return $this->update($tradeId, $data);

    }


}
