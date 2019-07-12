<?php
namespace Model\User;

use Lib\Base\BaseModel;
use Rest\User\UserExtendQuery;

class Setting extends BaseModel
{

    /**
     * 爱好
     */
    const KEY_HOBBY='hobby2';
    /**
     * 擅长
     */
    const KEY_SPECIALITY='speciality';
    /**
     * 个人简介
     */
    const KEY_USER_INTRODUCTION='introduction';
    //用户性别
    const KEY_USER_GENDER = 'gender';

    protected $table = 'user_setting';

    protected $id = 'uset_id';

    /**
     * 所有个人设置
     *
     * @param int $uid            
     * @throws \Exception\ModelException
     */
    public function getAll($uid)
    {
        if (! $uid) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $sql = "select * from `{$this->table}` where u_id=:u_id ";
        $list= $this->mysql->select($sql, array(
            'u_id' => $uid
        ));
        if($list){
            foreach ($list as &$item) {
                switch ($item['uset_key']){
                    case self::KEY_HOBBY:
                    case self::KEY_SPECIALITY:
                        $item['uset_value']=[];
                        if($item['uset_value']){
                            $item['uset_value']=json_decode($item['uset_value']);
                        }
                        break;
                }
            }
        }
        return $list;
    }

    public function getOne($uid,$key)
    {
        return $this->one("u_id=:u_id and uset_key=:uset_key", array(
            'u_id' => $uid,
            'uset_key' => $key
        ));
    }

    /**
     * 获取某项设置
     *
     * @param int $uid            
     * @param string $key            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function settingGet($uid, $key)
    {
        if (!$uid || !$key) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        if ($key == self::KEY_SPECIALITY) {
            $data = (new \Model\User\UserSpeciality())->getUserSpeciality($uid);
            $res = ['uset_key' => 'speciality', 'uset_value' => $data];
        } else {
            $res = $this->getOne($uid, $key);
            if ($res) {
                switch ($key) {
                    case self::KEY_HOBBY:
                        if ($res && $res['uset_value']) {
                            $res['uset_value'] = json_decode($res['uset_value'], true);
                        }
                        break;
                    case self::KEY_USER_GENDER:
                        if ($res && $res['uset_value']) {
                            $res['uset_value'] = intval($res['uset_value']);
                        }
                        break;
                }
            } else {
                $res = ['uset_key' => $key, 'uset_value' => null];
            }
        }

        return $res;
    }

    /**
     * 查询用户个人设置的值
     *
     * @param int $uid            
     * @param string $key            
     * @return string
     */
    public function settingGetValue($uid, $key)
    {
        $value = "";
        $row = $this->settingGet($uid, $key);
        if ($row && is_array($row) && isset($row['uset_value'])) {
            return $row['uset_value'];
        }
        return $value;
    }

    /**
     * 设置某项设置
     *
     * @param int $uid            
     * @param string $key            
     * @param string $value            
     * @throws \Exception\ModelException
     */
    public function settingSet($uid, $key, $value)
    {
        if (! isset($value)) {
            throw new \Exception\ParamsInvalidException("{$key}:值未设置");
        }
        switch ($key){
            case self::KEY_HOBBY:
                $value=$value?$value:[];
                $value=json_encode($value);
                break;
            case self::KEY_SPECIALITY:
                $value=$value?$value:[];
                (new \Model\User\UserSpeciality())->setUserSpeciality($uid,$value);
                return true;
                break;
            case self::KEY_USER_GENDER:
                //修改用户性别
                if (in_array($value, [1, 2])) {
                    app('mysqlbxd_user')->update('user_extend', ['ue_gender' => $value], ['u_id' => $uid]);
                }
        }
        $row = 0;
        $info = $this->getOne($uid, $key);
        if ($info) {
            $info['uset_value'] = $value;
            $row = $this->replace($info);
        } else {
            list ($row, $lastId) = $this->insert(
                array(
                    'u_id' => $uid,
                    'uset_key' => $key,
                    'uset_value' => $value
                ));
        }
        if (! $row) {
            throw new \Exception\ServiceException("{$key}:设置失败");
        }
    }

    /**
     * 批量设置某项值
     *
     * @param int $uid            
     * @param array $params            
     */
    public function settingSets($uid, $params)
    {
        foreach ($params as $key => $value) {
            $this->settingSet($uid, $key, $value);
        }
    }

    public function getCategoryByPid($pid=0)
    {
        $pid=intval($pid);
        $list=app('mysqlbxd_mall_common')->select('select c_id,c_name from category where c_parentId=:c_parentId AND c_isDel=0 AND c_isShow=1 order by c_sort desc',[
            'c_parentId'=>$pid
        ]);
        return $list?$list:[];
    }
    public function getCategoryByIds($cids)
    {
        $list=[];
        if(is_array($cids) && $cids){
            $cids=array_filter(array_map(function($cid){return intval($cid);}));
            $list=app('mysqlbxd_mall_common')->select('select c_id,c_name from category where c_id in ('.implode(',',$cids).')' );
        }
        return $list?$list:[];

    }
}
