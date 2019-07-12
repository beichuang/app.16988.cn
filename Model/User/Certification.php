<?php
namespace Model\User;

use Lib\Base\BaseModel;
use Exception\ServiceException;

/**
 * 实名认证数据库操作类
 */
class Certification extends BaseModel
{

    protected $table = 'user_certification';

    protected $id = 'uce_id';

    /**
     * 新增实名认证
     *
     * @param int $uid            
     * @param string $realName            
     * @param string $IDNo            
     * @param string $uce_bankCardNo            
     * @param string $uce_bankCardType            
     * @param number $provinCode            
     * @param number $cityCode            
     * @param number $areaCode            
     * @param number $IDType            
     * @param number $status            
     * @param number $isCelebrity            
     * @throws \Exception\ParamsInvalidException
     * @throws ServiceException
     * @return multitype:
     */
    
    //$uid, $realName,$phone,$photoCertificate,$celebrity,$IDNo,$uce_bankCardNo,$uce_bankCardType
    public function apply($uid, $realName,$phone, $IDNo='', $uce_bankCardNo, $uce_bankCardType,$photoCertificate='', 
        $isCelebrity = 0, $status = 0,$enterpriseName='',$licenceNO='',$enterpriseType=0,$address='',$photoLicence='',
        $photoStorefront='',$provinCode = 0, $cityCode = 0, $areaCode = 0, $IDType = 0)
    
    {
        if (! $uid || ! $realName ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $uid,
            //'uce_IDType' => $IDType,
            // 'uce_IDNo' => $IDNo,
            'uce_status' => $status,
            'uce_isCelebrity' => $isCelebrity,
            'uce_photoCertificate' => $photoCertificate,
            'uce_updateDate' => date('Y-m-d H:i:s'),
            'uce_realName' => $realName,
            'uce_bankPhone'=>$phone,
            'uce_provinceCode' => $provinCode,
            'uce_cityCode' => $cityCode,
            'uce_areaCode' => $areaCode,
            'uce_bankCardNo' => $uce_bankCardNo,
            'uce_bankCardType' => $uce_bankCardType,
            'uce_enterpriseType' => $enterpriseType,
            'uce_enterpriseName' => $enterpriseName,
            'uce_licenceNO' => $licenceNO,
            'uce_address' => $address,
            'uce_photoLicence' => $photoLicence,
            'uce_photoStorefront' => $photoStorefront,
        );
        if ($IDNo) {
            $data['uce_IDNo'] = $IDNo;
        }
       
        if ($isCelebrity!=2)
        {
            if ($this->isCelebrityExists($IDNo, $IDType, $uid)) {
                throw new ServiceException("该证件已提交过认证");
            }
        }
        $cer = $this->getInfo($uid);
        //同时更新user表
        if($realName){
            app('mysqlbxd_user')->update('user', [
                'u_realname' => $realName
            ], ['u_id' => $uid]);
        }
        if ($cer) {
            $uce_id = $cer['uce_id'];
            $oldStatus = $cer['uce_status'];
            $oldCelebrity = $cer['uce_isCelebrity'];
            if ($oldCelebrity == $isCelebrity) {
                if ($oldStatus === '1') {
                    throw new ServiceException("已通过认证");
                } else if ($oldStatus === '0') {
                    throw new ServiceException("正在认证");
                }
            }
            $rows = $this->update($uce_id, $data);
            if ($rows) {
                return $uce_id;
            } else {
                return false;
            }
        } else {
            $data['uce_createDate'] = date('Y-m-d H:i:s');
            list ($count, $id) = $this->insert($data);
            return $id;
        }
    }

    /**
     * 新增实名认证
     *
     * @param int $uid
     * @param string $realName
     * @param string $IDNo
     * @param string $uce_bankCardNo
     * @param string $uce_bankCardType
     * @param number $provinCode
     * @param number $cityCode
     * @param number $areaCode
     * @param number $IDType
     * @param number $status
     * @param number $isCelebrity
     * @throws \Exception\ParamsInvalidException
     * @throws ServiceException
     * @return multitype:
     */
    
    //$uid, $realName,$phone,$photoCertificate,$celebrity,$IDNo,$uce_bankCardNo,$uce_bankCardType
    public function applyArtist($uid,$photoCertificate='',$isCelebrity = 0, $status = 0,$provinCode = 0, $cityCode = 0, $areaCode = 0, $IDType = 0)
    
    {
        if (! $uid ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $uid,
            //'uce_IDType' => $IDType,
            'uce_status' => $status,
            'uce_isCelebrity' => $isCelebrity,
            'uce_photoCertificate' => $photoCertificate,
            'uce_provinceCode' => $provinCode,
            'uce_cityCode' => $cityCode,
            'uce_areaCode' => $areaCode,
            'uce_updateDate' => date('Y-m-d H:i:s'),
        );
        
        $cer = $this->getInfo($uid);
        
        if ($cer) {
            $uce_id = $cer['uce_id'];
            $oldStatus = $cer['uce_status'];
            if ($oldStatus === '1' && $cer['uce_isCelebrity']>0) {
                throw new ServiceException("已通过认证");
            } else if ($oldStatus === '0') {
                throw new ServiceException("正在认证");
            }
            $rows = $this->update($uce_id, $data);
            if ($rows) {
                return $uce_id;
            } else {
                return false;
            }
        } else {
            $data['uce_createDate'] = date('Y-m-d H:i:s');
            list ($count, $id) = $this->insert($data);
            return $id;
        }
    }
    
    
    /**
     * 保存省市区信息
     * 
     * @param unknown $uid            
     * @param unknown $provinceCode            
     * @param unknown $cityCode            
     * @param unknown $areaCode            
     */
    public function saveRegion($uid, $provinceCode, $cityCode, $areaCode = 0)
    {
        $info = $this->one("u_id=:u_id", [
            'u_id' => $uid
        ]);
        if ($info && is_array($info) && ! empty($info)) {
            $id = $info['uce_id'];
            $data = [];
            $data['uce_provinceCode'] = (int) $provinceCode;
            $data['uce_cityCode'] = (int) $cityCode;
            $data['uce_areaCode'] = (int) $areaCode;
            return $this->update($id, $data);
        }
    }

    /**
     * 更新状态
     *
     * @param string $uce_id            
     * @param int $status            
     * @throws \Exception\ModelException
     * @return number
     */
    public function updateStatus($uce_id, $status)
    {
        if (! $uce_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (! is_numeric($status)) {
            throw new \Exception\ParamsInvalidException("状态只能是数字");
        }
        if ($status < 0 || $status > 9) {
            throw new \Exception\ParamsInvalidException("状态只能是0~9的数字");
        }
        return $this->update($uce_id, array(
            'uce_status' => $status
        ));
    }

    /**
     * 设置是否是名家
     *
     * @param string $uce_id            
     * @param number $isCelebrity            
     * @throws \Exception\ModelException
     * @return number
     */
    public function setIsCelebrity($uce_id, $isCelebrity)
    {
        if (! $uce_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (! in_array($isCelebrity, [
            0,
            1
        ])) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }
        return $this->update($uce_id, array(
            'uce_isCelebrity' => $isCelebrity
        ));
    }

    /**
     * 认证是否存在
     *
     * @param string $IDNo            
     * @param number $IDType            
     * @param number $exceludeUid            
     * @throws \Exception\ModelException
     * @return boolean
     */
    public function isCelebrityExists($IDNo, $IDType = 0, $exceludeUid = 0)
    {
        if (! $IDNo) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $where = " uce_IDType=:uce_IDType and uce_IDNo=:uce_IDNo ";
        $data = array(
            'uce_IDType' => $IDType,
            'uce_IDNo' => $IDNo
        );
        if ($exceludeUid) {
            $where .= " and u_id != :u_id ";
            $data['u_id'] = $exceludeUid;
        }
        $cer = $this->one($where, $data);
        if (! $cer) {
            return false;
        }
        return true;
    }

    /**
     * 获取认证信息
     *
     * @param int $u_id            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function getInfo($u_id)
    {
        return $cer = $this->one("u_id=:u_id", array(
            'u_id' => $u_id
        ));
    }

    public function getCertListByUserIds($userIds)
    {
        $sql = 'SELECT * FROM `user_certification` WHERE FIND_IN_SET(u_id, :userIds)';
        return app('mysqlbxd_app')->select($sql, [':userIds' => implode(',', $userIds)]);
    }

    /**
     * 获取认证某些信息
     *
     * @param int $u_id
     * @throws \Exception\ModelException
     * @return array:
     */
    public function getCertInfo($u_id)
    {
        $sql = "SELECT uce_isCelebrity,uce_status,uce_IDNo,uce_realName,uce_bankCardType,uce_bankPhone,uce_bankCardNo,uce_photoCertificate,uce_enterpriseType FROM `{$this->table}` uce
               where u_id=:u_id ";
        $rows = app('mysqlbxd_app')->select($sql, array(
                'u_id' => $u_id
            ));
        return isset($rows[0]) ? $rows[0] : [];
    }

    /**
     * 查询状态
     *
     * @param int $u_id            
     * @return int
     */
    public function getStatus($u_id)
    {
        $cer = $this->getInfo($u_id);
        wlog(['u_id'=>$u_id,'cer'=>$cer],'用户身份',4);
        $status = isset($cer['uce_status']) ? $cer['uce_status'] : - 1;
        return $status;
    }

    public function getType($u_id)
    {
        $cer = $this->getInfo($u_id);
        if ( isset($cer['uce_status']) && $cer['uce_status']==1 ) {
            return $cer['uce_isCelebrity'];
        }
        return 0;
    }
    
    /**
     * 查询附近信息
     *
     * @param int $u_id
     * @return int
     */
    public function getNearInfo($u_id)
    {
        $data = [];
        
        $cer = $this->getInfo($u_id);
        $data['uce_status']             = isset($cer['uce_status']) ? $cer['uce_status'] : - 1;
        $data['uce_photoStorefront']    = $cer['uce_photoStorefront'];
        $data['uce_address']            = $cer['uce_address'];
        
        return $data;
    }

    /**
     * 查询搜索列表
     *
     * @param array $params            
     * @param int $page            
     * @param int $pagesize            
     * @return array $List
     */
    public function lists($params, $page, $pagesize, $all=false)
    {
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'uce.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['uce_IDType']) && $params['uce_IDType'] != '') {
            $whereArr[] = 'uce.uce_IDType = :uce_IDType';
            $bindData[':uce_IDType'] = $params['uce_IDType'];
        }
        
        if (isset($params['uce_IDNo']) && $params['uce_IDNo'] != '') {
            $whereArr[] = 'uce.uce_IDNo = :uce_IDNo';
            $bindData[':uce_IDNo'] = $params['uce_IDNo'];
        }
        if (isset($params['uceTimeStart']) && $params['uceTimeStart'] != '') {
            $whereArr[] = '`uce`.uce_time >= :uceTimeStart';
            $bindData[':uceTimeStart'] = $params['uceTimeStart'];
        }
        
        if (isset($params['uceTimeEnd']) && $params['uceTimeEnd'] != '') {
            $whereArr[] = '`uce`.uce_time <= :uceTimeEnd';
            $bindData[':uceTimeEnd'] = $params['uceTimeEnd'];
        }
        if (isset($params['uce_provinceCode']) && $params['uce_provinceCode'] != '') {
            $whereArr[] = 'uce.uce_provinceCode = :uce_provinceCode';
            $bindData[':uce_provinceCode'] = $params['uce_provinceCode'];
        }
        if (isset($params['uce_cityCode']) && $params['uce_cityCode'] != '') {
            $whereArr[] = 'uce.uce_cityCode = :uce_cityCode';
            $bindData[':uce_cityCode'] = $params['uce_cityCode'];
        }
        if (isset($params['uce_areaCode']) && $params['uce_areaCode'] != '') {
            $whereArr[] = 'uce.uce_areaCode = :uce_areaCode';
            $bindData[':uce_areaCode'] = $params['uce_areaCode'];
        }
        if (isset($params['uce_isCelebrity']) && $params['uce_isCelebrity'] != '') {
            $whereArr[] = 'uce.uce_isCelebrity = :uce_isCelebrity';
            $bindData[':uce_isCelebrity'] = $params['uce_isCelebrity'];
        }
        if (isset($params['uce_updateDate'])  && $params['uce_updateDate'] != '')
        {
            $bindData[':uce_updateDate'] = $params['uce_updateDate'];
            $whereArr[] = 'uce.uce_updateDate > :uce_updateDate';
        }
        
        if (isset($params['certTime']) && $params['certTime'])
        {
            $order = ' uce.uce_updateDate desc';
        }
        else{
            $order = ' uce.uce_id desc';
        }
        
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $skip = ($page - 1) * $pagesize;

        if ($all){
            $sql = "SELECT uce.* FROM `{$this->table}` uce
                $where ORDER BY $order ";
            $rows = app('mysqlbxd_app')->select($sql, $bindData);

            $countSql = "SELECT COUNT(0) FROM `{$this->table}` uce $where";
            $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
            return [
                $rows,
                $count
            ];
        }


        $sql = "SELECT uce.* FROM `{$this->table}` uce
                $where ORDER BY $order";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` uce $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }

    /**
     * 根据拥有的商品的分类搜索查询的数据
     *
     * @param array $params
     * @param int $type
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function listsByType($params, $type, $page, $pagesize)
    {
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'uce.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['uce_isCelebrity']) && $params['uce_isCelebrity'] != '') {
            $whereArr[] = 'uce.uce_isCelebrity = :uce_isCelebrity';
            $bindData[':uce_isCelebrity'] = $params['uce_isCelebrity'];
        }
        if ($params['uce_updateDate']  && $params['uce_updateDate'] != '')
        {
            $bindData[':uce_updateDate'] = $params['uce_updateDate'];
            $whereArr[] = 'uce.uce_updateDate > :uce_updateDate';
        }
        if (isset($type) && $type != '') {
            $whereArr[] = 'g.g_categoryId = :g_categoryId';
            $bindData[':g_categoryId'] = $type;
        }
        if (isset($params['certTime']) && $params['certTime'])
        {
            $order = ' uce.uce_updateDate desc';
        }
        else{
            $order = ' uce.uce_id desc';
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "select * from jp_api_mall_user.goods g join jp_app.user_certification uce on g.g_salesId = uce.u_id
                $where GROUP by g.g_salesId  ORDER BY $order ";
        echo $sql;
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
var_dump($rows);die;
        $countSql = "SELECT * from jp_api_mall_user.goods g join jp_app.user_certification uce on g.g_salesId = uce.u_id
                $where GROUP by g.g_salesId";
        $count = app('mysqlbxd_app')->select($countSql, $bindData);

        return [
            $rows,
            count($count)
        ];
    }

    /**
     * 获取认证某些信息
     *
     * @param int $u_id
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function getCertInfoNew($u_id)
    {
        $sql = "SELECT u_id,uce_isCelebrity,uce_status,uce_IDNo,uce_realName,uce_bankCardType,uce_bankPhone,uce_bankCardNo,uce_photoCertificate,uce_enterpriseType FROM `{$this->table}` uce
               where u_id=:u_id ";
        $rows = app('mysqlbxd_app')->select($sql,
            array(
                'u_id' => $u_id
            ));

        //$sql2 = "SELECT * FROM jp_api_user.user where u_id=:u_id ";
        //$rows2 = app('mysqlbxd_app')->select($sql2,
        //    array(
        //        'u_id' => $u_id
        //    ));

        return $rows[0];
    }

}
