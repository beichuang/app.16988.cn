<?php
namespace Model\Pay;

use Lib\Base\BaseModel;
use Exception\ServiceException;

/**
 * 钱包
 */
class Wallet extends BaseModel
{

    protected $table = 'wallet';

    protected $id = 'w_id';


    public function __construct()
    {
        parent::__construct($table = 'wallet', $id = 'w_id', $mysqlDbFlag = 'mysqlbxd_pay_center');
    }

    /**
     * 更新钱包
     *
     * @param int $uid
     * @param int $money
     * @return multitype:
     */
    public function apply($uid, $money, $add = true)
    {
        if (! $uid || ! $money) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $uid,
            'w_utime' => date('Y-m-d H:i:s')
        );

        $wallet = $this->getWallet($uid);
        if ($wallet) {
            $wid = $wallet['w_id'];

            if ($add) {
                $data['w_balance'] = $wallet['w_balance'] + $money;
            } else {
                $data['w_balance'] = $wallet['w_balance'] - $money;
                if ($data['w_balance'] < 0) {
                     throw new \Exception\ParamsInvalidException("钱包余额不足!");
                }
            }

            $rows = $this->update($wid, $data);
            if ($rows) {
                return [$wid, $data['w_balance']];
            } else {
                return false;
            }
        } else {
            if (!$add) {
                throw new \Exception\ParamsInvalidException("钱包余额不足!!");
            }
            $data['w_balance'] = $money;
            $data['w_ctime'] = date('Y-m-d H:i:s');
            list ($count, $id) = $this->insert($data);
            if ($id) {
                return [$id, $data['w_balance']];
            } else {
                return false;
            }


        }
    }

    /**
     * 保存余额
     *
     * @param int $uid
     * @param int $money
     */
    public function saveMoney($uid, $money)
    {
        $info = $this->one("u_id=:u_id", [
            'u_id' => $uid
        ]);
        if ($info && is_array($info) && ! empty($info)) {
            $wid = $info['w_id'];
            $data = [];
            $data['w_balance'] = (int) $money;
            $data['w_utime'] = date("Y-m-d H:i:s");
            return $this->update($wid, $data);
        }
    }

    /**
     * 更新状态
     *
     * @param string $u_id
     * @param int $status
     * @throws \Exception\ModelException
     * @return number
     */
    public function updateStatus($u_id, $status)
    {
        if (! $u_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (! is_numeric($status)) {
            throw new \Exception\ParamsInvalidException("状态只能是数字");
        }
        if ($status < 0 || $status > 9) {
            throw new \Exception\ParamsInvalidException("状态只能是0~9的数字");
        }
        return $this->update($u_id, array(
            'w_status' => $status
        ));
    }


    /**
     * 获取钱包信息
     *
     * @param int $u_id
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function getWallet($u_id)
    {
        $wallet = $this->one("u_id=:u_id", array(
            'u_id' => $u_id
        ));
        if (!$wallet) {
            $data = [
            'u_id' => $u_id,
            'w_balance' => 0,
            'w_utime' => date("Y-m-d H:i:s"),
            'w_ctime' => date("Y-m-d H:i:s"),
            'w_status' => 1,
            ];
            $res =$this->insert($data);
            $data['w_id'] = $res[1];
            return $data;
        }
        return $wallet;
    }

    /**
     * 查询状态
     *
     * @param int $u_id
     * @return int
     */
    public function getStatus($u_id)
    {
        $cer = $this->getWallet($u_id);
        $status = isset($cer['w_status']) ? $cer['w_status'] : 0;
        return $status;
    }


}
