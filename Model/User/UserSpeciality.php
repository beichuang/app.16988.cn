<?php
namespace Model\User;

use Lib\Base\BaseModel;

class UserSpeciality extends BaseModel
{

    protected $table = 'user_speciality';
    protected $id = 'u_id';

    public function getUserSpeciality($uid)
    {
        $sql = "select * from `{$this->table}` where u_id=:u_id ";
        $list=$this->mysql->select($sql,[
            'u_id' => $uid
        ]);
        $list=$list?$list:[];
        return $list;
    }

    public function getAllByCategoryId($categoryId)
    {
        $sql = "select * from `{$this->table}` where gc_id=:gc_id ";
        return $this->mysql->select($sql, [
            'gc_id' => $categoryId
        ]);
    }

    public function setUserSpeciality($uid,$specialities)
    {
        if(!$uid){
            return false;
        }
        $c=$this->mysql->delete($this->table,[
            'u_id' => $uid
        ]);
        if($specialities && is_array($specialities)){
            $inserted=[];
            foreach ($specialities as $item){
                if($item && !isset($inserted[$item['c_id']])){
                    $this->mysql->insert($this->table,[
                        'u_id'=>$uid,
                        'gc_id'=>$item['c_id'],
                        'gc_name'=>$item['c_name'],
                        'us_time'=>date('Y-m-d H:i:s'),
                    ]);
                    $inserted[$item['c_id']]=true;
                }
            }
        }
        return true;
    }
}
