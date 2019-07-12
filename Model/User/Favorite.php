<?php
namespace Model\User;

use Lib\Base\BaseModel;
use Exception\ParamsInvalidException;
use Exception\ServiceException;

class Favorite extends BaseModel
{

    protected $table = 'user_favorite';

    protected $id = 'ufav_id';

    /**
     * 新增收藏
     *
     * @param int $uid            
     * @param int $type            
     * @param string $content            
     * @param string $title            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($uid, $type, $content, $title = '', $ufav_objectKey = '')
    {
        if (! $uid || ! isset($type)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if ( in_array($type, [0, 4]) && $ufav_objectKey) {
            if ($this->oneByUfavObjectId($uid, $ufav_objectKey)) {
                throw new ServiceException("已收藏");
            }
        }
        $data = array(
            'u_id' => $uid,
            'ufav_type' => $type,
            'ufav_content' => $content,
            'ufav_title' => ($title ? $title : ''),
            'ufav_time' => date('Y-m-d H:i:s'),
            'ufav_objectKey' => $ufav_objectKey
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 根据ufav_objectKey查询收藏信息
     * 
     * @param String $ufav_objectKey            
     * @return boolean multitype:
     */
    public function oneByUfavObjectId($uid, $ufav_objectKey, $type='')
    {
        if (! $uid || ! $ufav_objectKey) {
            return false;
        }
        $where = "u_id=:u_id and ufav_objectKey=:ufav_objectKey";
        $bindData = array(
                'u_id' => $uid,
                'ufav_objectKey' => $ufav_objectKey
            );

        if ( is_numeric($type) ) {
            $where .= " and ufav_type=:type ";
            $bindData['type'] = $type;
        }

        return $this->one($where, $bindData);
    }

    /**
     * uid是否和收藏id关联的一致
     *
     * @param int $uid            
     * @param int $ufav_id            
     * @throws \Exception\ModelException
     * @return boolean
     */
    public function isSameUser($uid, $ufav_id)
    {
        if (! $uid || ! $ufav_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $fav = $this->oneById($ufav_id);
        if (! $fav || ! isset($fav['u_id'])) {
            throw new \Exception\ServiceException("没有找到对应的收藏信息");
        }
        if ($fav['u_id'] != $uid) {
            return false;
        }
        return true;
    }

    /**
     * uid是否和收藏id关联的一致
     *
     * @param int $uid
     * @param int $ufav_id
     * @throws \Exception\ModelException
     * @return boolean
     */
    public function isNoSameUser($uid, $ufav_id)
    {
        if (! $uid || ! $ufav_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $fav = $this->oneById($ufav_id);
        if (! $fav || ! isset($fav['u_id'])) {
            return false;
        }
        if ($fav['u_id'] != $uid) {
            return false;
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
    public function lists($params, $page, $pagesize)
    {
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'ufav.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['ufav_type']) && $params['ufav_type'] != '') {
            $whereArr[] = 'ufav.ufav_type = :ufav_type';
            $bindData[':ufav_type'] = $params['ufav_type'];
        }
        
        if (isset($params['ufavTimeStart']) && $params['ufavTimeStart'] != '') {
            $whereArr[] = '`ufav`.ufav_time >= :ufavTimeStart';
            $bindData[':ufavTimeStart'] = $params['ufavTimeStart'];
        }
        
        if (isset($params['ufavTimeEnd']) && $params['ufavTimeEnd'] != '') {
            $whereArr[] = '`ufav`.ufav_time <= :ufavTimeEnd';
            $bindData[':ufavTimeEnd'] = $params['ufavTimeEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT ufav.* FROM `{$this->table}` ufav
                $where ORDER BY ufav.ufav_id DESC";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` ufav $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
