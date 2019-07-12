<?php
namespace Model\Message;

use Lib\Base\BaseModel;

class DialogUser extends BaseModel
{

    protected $table = 'message_dialog_user';

    protected $id = ' ';

    public function save($msgd_id, $u_id)
    {
        if (! $msgd_id || ! $u_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $rel = $this->one("msgd_id=:msgd_id and u_id=:u_id", 
            [
                'msgd_id' => $msgd_id,
                'u_id' => $u_id
            ]);
        if ($rel) {
            $this->mysql->update($this->table, 
                [
                    'msgdu_updateDate' => date('Y-m-d H:i:s')
                ], [
                    'u_id' => $u_id,
                    'msgd_id' => $msgd_id
                ]);
        } else {
            $data = array(
                'u_id' => $u_id,
                'msgd_id' => $msgd_id,
                'msgdu_updateDate' => date('Y-m-d H:i:s')
            );
            list ($count, ) = $this->insert($data);
        }
        return true;
    }

    /**
     * 查询搜索列表
     *
     * @param array $params            
     * @param int $page            
     * @param int $pagesize            
     * @return array $List
     */
    public function lists($params, $page, $pagesize,$needCount=false)
    {
        $whereArr = $bindData = [];
        if (isset($params['msgd_id']) && $params['msgd_id'] != '') {
            $whereArr[] = 'msgd_id = :msgd_id';
            $bindData[':msgd_id'] = $params['msgd_id'];
        }
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['msgdu_updateDateStart']) && $params['msgdu_updateDateStart'] != '') {
            $whereArr[] = 'msgdu_updateDateStart >= :msgdu_updateDateStart';
            $bindData[':msgdu_updateDateStart'] = $params['msgdu_updateDateStart'];
        }
        if (isset($params['msgdu_updateDateEnd']) && $params['msgdu_updateDateEnd'] != '') {
            $whereArr[] = '`dialogContent`.msgdc_msgdu_updateDate <= :msgdu_updateDateEnd';
            $bindData[':msgdu_updateDateEnd'] = $params['msgdu_updateDateEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT * FROM `{$this->table}` 
                $where ORDER BY msgdu_updateDate DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` dialogContent $where";
        $count=0;
        if($needCount){
            $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        }
        return [
            $rows,
            $count
        ];
    }
}
