<?php

namespace Model\User;

use Lib\Base\BaseModel;

class Voucher extends BaseModel {

    protected $table = 'voucher';
    protected $id = 'v_id';

    public function save($u_id, $v_id) {
        if (!$u_id || !$v_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = ['u_id' => $u_id];

        $result = $this->update($v_id, $data);
        return $result;
    }

    /**
     * 查询搜索列表
     * @param type $params
     * @param type $page
     * @param type $pagesize
     * @return type
     */
    public function lists($params, $page, $pagesize) {
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['v_status']) && $params['v_status'] != '') {
            $whereArr[] = 'v_status = :v_status';
            $bindData[':v_status'] = $params['v_status'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT * FROM `{$this->table}` $where ORDER BY v_id DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [$rows, $count];
    }

    /**
     * 代金券是否已领取
     * @param type $u_id
     * @param type $sn
     * @return type
     */
    public function getInfoBySn($u_id, $sn) {
        $cer = $this->one("u_id=:u_id and v_status=:v_status and v_sn=:v_sn", ['u_id' => $u_id, 'v_status' => 1, 'v_sn' => $sn]);
        return $cer;
    }

}
