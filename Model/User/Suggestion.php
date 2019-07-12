<?php
namespace Model\User;

use Lib\Base\BaseModel;

class Suggestion extends BaseModel
{

    protected $table = 'user_suggestion';

    protected $id = 'usug_id';

    /**
     * 新增建议
     *
     * @param int $uid            
     * @param string $content            
     * @param string $title            
     * @param string $type            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($uid, $content, $title = '', $type = '')
    {
        if (! $uid || ! $content) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $uid,
            'usug_title' => $title,
            'usug_type' => $type,
            'usug_content' => $content,
            'usug_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 根据用户id查询最近的一次建议
     *
     * @param int $uid            
     * @return array boolean
     */
    public function getLastSuggestionByUid($uid)
    {
        $sql = "select * from {$this->table} where u_id=:u_id order by `{$this->id}` desc limit 1";
        $row = $this->mysql->fetch($sql, array(
            'u_id' => $uid
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
    public function lists($params, $page, $pagesize,$order='')
    {
        // usug_id,u_id,usug_title,usug_type,usug_content,usug_time
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'usug.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['usug_type']) && $params['usug_type'] != '') {
            $whereArr[] = 'usug.usug_type = :usug_type';
            $bindData[':usug_type'] = $params['usug_type'];
        }
        if (isset($params['usug_pid']) && $params['usug_pid'] != '') {
            $whereArr[] = 'usug.usug_pid = :usug_pid';
            $bindData[':usug_pid'] = $params['usug_pid'];
        }
        
        if (isset($params['usugTimeStart']) && $params['usugTimeStart'] != '') {
            $whereArr[] = '`usug`.usug_time >= :usugTimeStart';
            $bindData[':usugTimeStart'] = $params['usugTimeStart'];
        }
        
        if (isset($params['usugTimeEnd']) && $params['usugTimeEnd'] != '') {
            $whereArr[] = '`usug`.usug_time <= :usugTimeEnd';
            $bindData[':usugTimeEnd'] = $params['usugTimeEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT usug.* FROM `{$this->table}` usug
                $where ";
        if($order){
            $sql.=" ORDER BY {$order} ";
        }else{
            $sql.=" ORDER BY usug.usug_id DESC ";
        }
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` usug $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
