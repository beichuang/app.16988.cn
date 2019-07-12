<?php
namespace Model\Treasure;

use Lib\Base\BaseModel;

class TreasureLikeLog extends BaseModel
{

    protected $table = 'treasure_like_log';

    protected $id = 'tll_id';

    /**
     * 新增晒宝点赞
     *
     * @param int $u_id            
     * @param int $t_id            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($u_id, $t_id)
    {
        if (! $u_id || ! $t_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $row = $this->treasureLikeLogInfo($u_id, $t_id);
        if ($row) {
            throw new \Exception\ServiceException("已点赞");
        }
        $data = array(
            'u_id' => $u_id,
            't_id' => $t_id,
            'tll_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 根据用户id、获取自身有没有点赞
     *
     * @param int $u_id            
     * @param int $t_id            
     * @return multitype:
     */
    public function treasureIsLikeLogInfo($u_id, $t_id)
    {
        $row = $this->lists(array(
            't_id' => $t_id
        ), 1, 10);
        $uidArr = array();
        foreach ($row[0] as $v) {
            $uidArr[] .= $v['u_id'];
        }
        if (in_array($u_id, $uidArr)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 根据用户id、晒宝点赞信息
     *
     * @param int $u_id            
     * @param int $t_id            
     * @return multitype:
     */
    public function treasureLikeLogInfo($u_id, $t_id)
    {
        $row = $this->one("t_id =:t_id and u_id=:u_id", 
            array(
                't_id' => $t_id,
                'u_id' => $u_id
            ));
        return $row;
    }

    /**
     * 取消晒宝点赞
     *
     * @param int $u_id            
     * @param int $t_id            
     * @throws \Exception\ModelException
     * @return Ambigous <number, \Framework\Route, \Framework\Route>
     */
    public function treasureLikeLogRemove($u_id, $t_id)
    {
        $row = $this->treasureLikeLogInfo($u_id, $t_id);
        if (! $row) {
            throw new \Exception\ServiceException("已取消");
        }
        $id = $row['tll_id'];
        return $this->delete($id);
    }

    /**
     * 查询搜索列表
     *
     * @param array $params            
     * @param int $page            
     * @param int $pagesize            
     * @return array $List
     */
    public function lists($params, $page=0, $pagesize=10)
    {
        // tc_pid,t_id,u_id,tc_title,tc_content,tc_time
        $whereArr = $bindData = [];
        if (isset($params['t_id']) && $params['t_id'] != '') {
            $whereArr[] = 'tc.t_id = :t_id';
            $bindData[':t_id'] = $params['t_id'];
        }
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'tc.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        
        if (isset($params['tll_time']) && $params['tll_time'] != '') {
            $whereArr[] = '`tc`.tc_time >= :tll_time';
            $bindData[':tll_time'] = $params['tll_time'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT tc.* FROM `{$this->table}` tc
                $where ORDER BY tc.tll_id ASC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` tc $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
