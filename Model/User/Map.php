<?php
namespace Model\User;

use Lib\Base\BaseModel;

class Map extends BaseModel
{

    protected $table = 'user_map';

    protected $id = 'um_id';
        
    /**
     * 查询用户是否有坐标
     */
    public function isUserMap($uid)
    {
        if (! $uid) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        
        $user_map_num = $this->one('u_id=:u_id',[':u_id'=>$uid]);
        if (! $user_map_num) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 新增用户坐标
     *
     * @param array $data
     * @param number $targetPlatform
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function addUserMap($data)
    {
        if ( empty($data)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        
        list ($count, $id) = $this->insert($data);
        return $id;
    }
    /**
     * 修改用户坐标
     */
    public function updateUserMap($u_id, $data)
    {
        if (! $u_id || empty($data)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        
        $row = $this->mysql->update($this->table,$data,array('u_id'=>$u_id));
        
        return $row;
    }
    /**
     * 用户附近玩友
     * @param integer $u_id
     * @param array $params
     * @param number $page
     * @param number $pagesize
     * @return unknown
     */
    public function getUserMapListByPage($u_id,$params,$page, $pagesize)
    {
        
        $num  = 50;//默认50条数据
        
        $whereArr = $bindData = [];
        if (isset($params['uids']) && $params['uids'] != '') {
            $uids=[];
            $tmp=explode(',', $params['uids']);
            if($tmp && is_array($tmp)){
                foreach ($tmp as $s){
                    if($s && is_numeric($s)){
                        $uids[]=$s;
                    }
                }
            }
            if(!empty($uids)){
                $whereArr[] = ' u_id in ('.implode(',', $uids).') ';
            }
        }
    
        if (isset($params['geohash']) && $params['geohash'] != '') {
    
            $whereArr[] = "um_geohash like '{$params['geohash']}%'";
//            $bindData['um_geohash'] = $params['geohash'];
        }
        if (isset($params['type']) ) {
        
            $whereArr[] = "uce_isCelebrity = {$params['type']}";
//            $bindData['uce_isCelebrity'] = $params['type'];
        }
        
        $whereArr[] = "u_id <> '{$u_id}'";
        
        $where = implode(' AND ', $whereArr);
        if ($where != '') {
            $where = ' WHERE ' . $where;
        }
        $sql = "SELECT u_id,um_lon,um_lat FROM {$this->table}  {$where} ";

        $rows = $this->mysql->selectPage($sql,$page,$pagesize, $bindData);
        
        return $rows;
    }

    public function getUserMapList($params,$excludeUid,$countOnly=0,$maxTotal=100)
    {
        $whereArr = $bindData = [];
        if (isset($params['geohash']) && $params['geohash'] != '') {

            $whereArr[] = "um_geohash like '{$params['geohash']}%'";
        }
        if (isset($params['type']) ) {

            $whereArr[] = "uce_isCelebrity = {$params['type']}";
        }
        if(isset($excludeUid)){
            $whereArr[] = "u_id != '{$excludeUid}'";
        }
        $where = implode(' AND ', $whereArr);
        if ($where != '') {
            $where = ' WHERE ' . $where;
        }
        if($countOnly){
            return $this->mysql->fetchColumn("SELECT count(*) c FROM {$this->table}  {$where}");
        }else{
            return $this->mysql->select("SELECT u_id,um_lon,um_lat FROM {$this->table}  {$where} order by um_updateDate desc limit {$maxTotal}");
        }
    }
   
}
