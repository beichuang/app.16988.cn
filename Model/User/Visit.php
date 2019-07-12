<?php
namespace Model\User;

use Lib\Base\BaseModel;

class Visit extends BaseModel
{
    protected $table = 'visit_log';
    protected $id = 'id';

	public function add($uid, $target, $type=1)
	{
        if (!$uid || !$target) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        $data = array(
            'u_id' => $uid,
            'target' => $target,
            'type' => $type,
            'vtime' => date('Y-m-d H:i:s')
        );
        $changeNum = $this->replace($data);
        return $changeNum;
	}

	/**
	 * 搜索/打印 访问列表
	 */
	public function lists($target, $page=0, $pagesize=10)
	{
        if ( !$target ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");        	
        }

        $sql = "select * from `{$this->table}` ";
        $whereArr = $bindData = [];

        $whereArr[] = 'target = :target';
        $bindData[':target'] = $target;
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql .= $where;
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);

        //$countSql = "SELECT COUNT(0) FROM `{$this->table}` {$where}";
        //$count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData2);

        return $rows && is_array($rows) ? $rows : array();
	}

	public function getVisitCount($uid)
    {
        $visitCount = 0;
        if ($uid) {
            $sql = "select COUNT(*) from `{$this->table}` WHERE target = :target and u_id!=:u_id";
            $visitCount = app('mysqlbxd_app')->fetchColumn($sql, [
                'target' => $uid,
                'u_id' => $uid,
            ]);
            $visitCount = intval($visitCount);
        }

        return $visitCount;
    }
}