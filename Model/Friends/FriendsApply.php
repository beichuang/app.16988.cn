<?php
namespace Model\Friends;

use Lib\Base\BaseModel;

class FriendsApply extends BaseModel
{

    protected $table = 'friends_apply';

    protected $id = 'fria_id';

    /**
     * 新增申请
     *
     * @param int $u_id            
     * @param int $fria_friendId            
     * @param string $fria_message            
     * @param number $fria_status            
     * @throws \Exception\ModelException
     * @return unknown multitype:
     */
    public function add($u_id, $fria_friendId, $fria_message = '', $fria_status = 0, $fria_group = '', $fria_nickname = '', 
        $fria_realname = '', $fria_avatar = '', $fria_provinceCode = 0, $fria_cityCode = 0, $fria_areaCode = 0)
    {
        if (! $u_id || ! $fria_friendId) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'fria_userId' => $u_id,
            'fria_friendId' => $fria_friendId,
            'fria_message' => $fria_message,
            'fria_applyTime' => date('Y-m-d H:i:s'),
            'fria_status' => $fria_status,
            'fria_operateTime' => date('Y-m-d H:i:s'),
            'fria_nickname' => $fria_nickname,
            'fria_realname' => $fria_realname,
            'fria_avatar' => $fria_avatar,
            'fria_provinceCode' => $fria_provinceCode,
            'fria_cityCode' => $fria_cityCode,
            'fria_areaCode' => $fria_areaCode
        );
        $row = $this->oneByUidFirendId($u_id, $fria_friendId);
        if ($row) {
            $id = $row['fria_id'];
            $this->update($id, $data);
            return $id;
        } else {
            list ($count, $id) = $this->insert($data);
            return $id;
        }
    }

    /**
     * 根据uid、申请的好友id查询申请记录
     *
     * @param int $u_id            
     * @param int $friendId            
     * @return multitype:
     */
    public function oneByUidFirendId($u_id, $friendId)
    {
        $row = $this->one("fria_userId = :fria_userId and fria_friendId = :fria_friendId", 
            array(
                'fria_userId' => $u_id,
                'fria_friendId' => $friendId
            ));
        return $row;
    }

    /**
     * 更新状态
     *
     * @param int $fria_id            
     * @param int $status            
     * @throws \Exception\ModelException
     * @return number
     */
    public function updateStatus($fria_id, $status)
    {
        if (! $fria_id || ! $status) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (! in_array($status, [
            1,
            2,
            3
        ])) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }
        $row = $this->oneById($fria_id);
        if ($row) {
            $id = $row['fria_id'];
            if ($row['fria_status'] !== '0') {
                throw new \Exception\ServiceException("已处理");
            }
            return $this->update($id, array(
                'fria_status' => $status
            ));
        } else {
            throw new \Exception\ServiceException("已失效！");
        }
    }

    /**
     * 查询搜索列表
     *
     * @param array $params            
     * @param int $page            
     * @param int $pagesize            
     * @return array $List
     */
    public function lists($params, $page, $pagesize)
    {
        $whereArr = $bindData = [];
        if (isset($params['fria_userId']) && $params['fria_userId'] != '') {
            $whereArr[] = 'fria.fria_userId = :fria_userId';
            $bindData[':fria_userId'] = $params['fria_userId'];
        }
        if (isset($params['fria_status']) && $params['fria_status'] != '') {
            $whereArr[] = 'fria.fria_status = :fria_status';
            $bindData[':fria_status'] = $params['fria_status'];
        }
        if (isset($params['fria_friendId']) && $params['fria_friendId'] != '') {
            $whereArr[] = 'fria.fria_friendId = :fria_friendId';
            $bindData[':fria_friendId'] = $params['fria_friendId'];
        }
        if (isset($params['applyTimeStart']) && $params['applyTimeStart'] != '') {
            $whereArr[] = '`fria`.fria_applyTime >= :applyTimeStart';
            $bindData[':applyTimeStart'] = $params['applyTimeStart'];
        }
        if (isset($params['applyTimeEnd']) && $params['applyTimeEnd'] != '') {
            $whereArr[] = '`fria`.fria_applyTime <= :applyTimeEnd';
            $bindData[':applyTimeEnd'] = $params['applyTimeEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT fria.* FROM `{$this->table}` fria
                $where ORDER BY fria.fria_id DESC  ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` fria $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
