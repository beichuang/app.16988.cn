<?php
namespace Model\Treasure;

use Lib\Base\BaseModel;

class TreasureComment extends BaseModel
{

    protected $table = 'treasure_comment';

    protected $id = 'tc_id';

    /**
     * 新增评论
     *
     * @param int $u_id            
     * @param int $t_id            
     * @param string $tc_title            
     * @param string $tc_content            
     * @param number $tc_pid            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($u_id, $t_id, $tc_title, $tc_content, $tc_pid = 0)
    {
        if (! $u_id || ! $t_id || ! $tc_content) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (strlen($tc_title) > 200) {
            throw new \Exception\ParamsInvalidException("标题过长");
        }
        $data = array(
            'u_id' => $u_id,
            'tc_pid' => $tc_pid,
            't_id' => $t_id,
            'tc_title' => $tc_title ? $tc_title : '',
            'tc_content' => $tc_content,
            'tc_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 删除评论
     *
     * @param int $u_id            
     * @param int $t_id            
     * @throws \Exception\ModelException
     * @return Ambigous <number, \Framework\Route, \Framework\Route>
     */
    public function treasureCommentRemove($u_id, $t_id)
    {
        $row = $this->treasureCommentInfo($u_id, $t_id);
        if (! $row) {
            throw new \Exception\ServiceException("已删除");
        }
        
        $id = $row['tc_id'];
        return $this->delete($id);
    }

    /**
     * 根据用户id、获取自身有没有评论
     *
     * @param int $u_id            
     * @param int $t_id            
     * @return multitype:
     */
    public function isComment($u_id, $t_id)
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
     * 根据用户id、获取到评论的信息
     *
     * @param int $u_id            
     * @param int $t_id            
     * @return multitype:
     */
    public function treasureCommentInfo($u_id, $t_id)
    {
        $row = $this->one("t_id =:t_id and u_id=:u_id", 
            array(
                't_id' => $t_id,
                'u_id' => $u_id
            ));
        return $row;
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
        if (isset($params['tc_pid']) && $params['tc_pid'] != '') {
            $whereArr[] = 'tc.tc_pid = :tc_pid';
            $bindData[':tc_pid'] = $params['tc_pid'];
        }
        
        if (isset($params['timeStart']) && $params['timeStart'] != '') {
            $whereArr[] = '`tc`.tc_time >= :timeStart';
            $bindData[':timeStart'] = $params['timeStart'];
        }
        if (isset($params['timeEnd']) && $params['timeEnd'] != '') {
            $whereArr[] = '`tc`.tc_time <= :timeEnd';
            $bindData[':timeEnd'] = $params['timeEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT tc.* FROM `{$this->table}` tc
                $where ORDER BY tc.tc_time ASC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` tc $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
