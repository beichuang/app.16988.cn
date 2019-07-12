<?php
namespace Model\User;

use Lib\Base\BaseModel;

class Invite extends BaseModel
{

    protected $table = 'user_invite_log';

    protected $id = 'uil_id';

    /**
     * 新增分享邀请
     *
     * @param int $uid            
     * @param string $phone
     * @param number $targetPlatform            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($uid, $phone, $targetPlatform = 1)
    {
        if (! $uid || ! $phone || ! $targetPlatform) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $uid,
            'uil_phone' => $phone,
            'uil_desc' => "分享邀请",
            'uil_targetPlatform' => $targetPlatform,
            'uil_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        return $id;
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
            $whereArr[] = 'uil.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['uil_targetPlatform']) && $params['uil_targetPlatform'] != '') {
            $whereArr[] = 'uil.uil_targetPlatform = :uil_targetPlatform';
            $bindData[':uil_targetPlatform'] = $params['uil_targetPlatform'];
        }
        if (isset($params['uil_is_register']) && $params['uil_is_register'] != '') {
            $whereArr[] = 'uil.uil_is_register = :uil_is_register';
            $bindData[':uil_is_register'] = $params['uil_is_register'];
        }
        
        if (isset($params['uilTimeStart']) && $params['uilTimeStart'] != '') {
            $whereArr[] = '`uil`.uil_time >= :uilTimeStart';
            $bindData[':uilTimeStart'] = $params['uilTimeStart'];
        }
        
        if (isset($params['uilTimeEnd']) && $params['uilTimeEnd'] != '') {
            $whereArr[] = '`uil`.uil_time <= :uilTimeEnd';
            $bindData[':uilTimeEnd'] = $params['uilTimeEnd'];
        }

        if (isset($params['month']) && !empty($params['month'])) {
            $whereArr[] = "date_format(`uil_time`,'%Y-%m')= :month ";
            $bindData[':month'] = $params['month'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT uil.* FROM `{$this->table}` uil
                $where ORDER BY uil.uil_id DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` uil $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);

        $reg_countSql = "SELECT COUNT(0) FROM `{$this->table}` uil $where"." and uil_is_register=1";
        $reg_count = app('mysqlbxd_app')->fetchColumn($reg_countSql, $bindData);
        return [
            $rows,
            $count,
            $reg_count
        ];
    }

    /**
     * 查询手机号是否已经被邀请
     */
    public function getInfoByPhone($phone){
         $cer = $this->one("uil_phone=:uil_phone", array(
            'uil_phone' => $phone
        ));
        return $cer;
    }

    /** 修改uil_is_register
     * @param $id
     */
    public function updateRegisterStatus($id){
        $data['uil_is_register'] = 1;
        $data['uil_time'] = date('Y-m-d H:i:s',time());
        $rows = $this->update($id, $data);
        if ($rows) {
            return $id;
        } else {
            return false;
        }
    }

    public function getCount($uid)
    {
        return (int)$this->rowCount('u_id=:uid AND uil_is_register=1', [':uid' => $uid]);
    }
}
