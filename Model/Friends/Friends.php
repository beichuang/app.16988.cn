<?php
namespace Model\Friends;

use Lib\Base\BaseModel;
use Exception\ParamsInvalidException;

class Friends extends BaseModel
{

    protected $table = 'friends';

    protected $id = 'fri_id';

    /**
     * 根据uid、好友id查询好友信息
     *
     * @param int $u_id            
     * @param int $friendId            
     * @return multitype:
     */
    public function oneByUidFirendId($u_id, $friendId)
    {
        $row = $this->one("fri_userId = :fri_userId and fri_friendId = :fri_friendId", 
            array(
                'fri_userId' => $u_id,
                'fri_friendId' => $friendId
            ));
        return $row;
    }

    /**
     * 是否关注
     */
    public function isAttention($u_id, $friendId)
    {
        $row = $this->oneByUidFirendId($u_id, $friendId);

        if ( $row && is_array($row) ) {
            return true;
        }

        return false;
    }

    /**
     * @return 0没关系 1粉丝 2关注 3好友
     */
    public function relation($u_id, $friendId)
    {
        $attention = $this->oneByUidFirendId($u_id, $friendId);
        $fans = $this->oneByUidFirendId($friendId, $u_id);

        if ( $attention && is_array($attention) ) {
            if ( $fans && is_array($fans) ) {
                return 3;
            }
            return 2;
        } else {
            if ( $fans && is_array($fans) ) {
                return 1;
            }
            return 0;
        }
    }

    /**
     * 关注
     */
    public function addAttention($u_id, $friendId)
    {
        if ( !$u_id || !$friendId ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $userLib = new \Lib\User\User();
        $userInfos = $userLib->getUserInfo([$friendId]);
        if ( !$userInfos ) {
            throw new ParamsInvalidException("用户不存在");
        }

        $data = array();
        $data['fri_userId'] = $u_id;
        $data['fri_friendId'] = $friendId;
        $data['fri_friendRemark'] = $userInfos[$friendId]['u_nickname'];
        $data['fri_applyTime'] = date('Y-m-d H:i:s');

        list ($count, $id) = $this->insert($data);

        return $id;
    }

    /**
     * 取消关注
     */
    public function cancelAttention($u_id, $friendId)
    {
        if ( !$u_id || !$friendId ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        $row = $this->oneByUidFirendId($u_id, $friendId);
        if ($row) {
            return $this->delete($row['fri_id']);
        }

        return 0;
    }

    /**
     * 查询关注/粉丝数
     */
    public function lists($params, $page=1, $pagesize=10)
    {
        $whereArr = $bindData = [];
        if (isset($params['fri_userId']) && $params['fri_userId'] != '') {
            $whereArr[] = 'fri.fri_userId = :fri_userId';
            $bindData[':fri_userId'] = $params['fri_userId'];
        }
        if (isset($params['fri_friendId']) && $params['fri_friendId'] != '') {
            $whereArr[] = 'fri.fri_friendId = :fri_friendId';
            $bindData[':fri_friendId'] = $params['fri_friendId'];
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT fri.* FROM `{$this->table}` fri $where ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` fri {$where}";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count,
        ];
    }

    /**
     * 用户好友模糊查询
     */
    public function searchList($uid, $name='')
    {
        $sql = "SELECT * FROM `{$this->table}` where ( `fri_userId`='{$uid}' or `fri_friendId`='{$uid}' ) ";
        if ($name) {
            $sql .= " and fri_friendRemark like '%{$name}%'";
        }
        $rows = app('mysqlbxd_app')->select($sql);
        $num = count($rows);
        return $rows;
    }

    /**
     * 查询互粉的数量
     */
    public function mutual($uid){
        $sql = "SELECT * FROM `{$this->table}` where ( `fri_userId`='{$uid}' or `fri_applyStatus`='3' ) ";

        $rows = app('mysqlbxd_app')->select($sql);
        $num = count($rows);
        return $num;

    }

}
