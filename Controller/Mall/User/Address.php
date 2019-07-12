<?php
/**
 * 用户收货地址
 * @author Administrator
 *
 */
namespace Controller\Mall\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Address extends BaseController
{

    private $userAddressLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->userAddressLib = new \Lib\Mall\UserAddress();
    }

    /**
     * 新增或修改收货地址
     */
    public function post()
    {
        $params = app()->request()->params();
        $params['uid'] = app()->request()->params('uid',$this->uid);

        //新增时判断
        if(!isset($params['id']) || !$params['id']){

            $provinces=\Lib\Common\Region::getProvinces();
            $provinceCode = app()->request()->params('provinceCode', '');
            if (! $provinceCode) {
                throw new \Exception("收件人所在省份必须！");
            }
            if(!isset($provinces[$provinceCode])){
                throw new \Exception("收件人所在省份编码错误！");
            }
            $citys=\Lib\Common\Region::getCities($provinceCode);
            $cityCode = app()->request()->params('cityCode', '');
            if (is_array($citys)) {
                if (! $cityCode) {
                    throw new \Exception("收件人所在市必须！");
                }
                if(!isset($citys[$cityCode])){
                    throw new \Exception("收件人所在市编码错误！");
                }
                $areas=\Lib\Common\Region::getAreas($cityCode);
                $areaCode = app()->request()->params('areaCode', '');
                // if (is_array($areas)) {
                //     if(!isset($areas[$areaCode])){
                //         throw new \Exception("收件人所在区县编码错误！");
                //     }
                // }
            }
        }

        $resMall = $this->userAddressLib->add($params);
        $aid = $resMall;
        $this->responseJSON([
            'aid' => $aid
        ]);
    }

    /**
     * 新增或修改收货地址，根据微信收货地址
     */
    public function addByName()
    {
        $name=app()->request()->params('name','');
        $phone=app()->request()->params('phone','');
        $provinceName=app()->request()->params('provinceName','');
        $cityName=app()->request()->params('cityName','');
        $areaName=app()->request()->params('areaName','');
        $address=app()->request()->params('address','');
        $isDefault=app()->request()->params('isDefault','');
        if(!$provinceName || !$cityName){
            throw new \Exception("收件人所在省市必须！");
        }
        if(!$address ){
            throw new \Exception("收件人地址必须！");
        }
        if(!$phone ){
            throw new \Exception("收件人手机号必须！");
        }
        if(!preg_match('/^1\d{10}$/',$phone) ){
            throw new \Exception("收件人手机号格式错误！");
        }
        $region=\Lib\Common\Region::getRegionCode($provinceName,$cityName,$areaName);
        $provinceCode=$region[0];
        $cityCode=$region[1];
        $areaCode=$region[2];
        //新增时判断
        $provinces=\Lib\Common\Region::getProvinces();
        if (! $provinceCode) {
            throw new \Exception("收件人所在省份错误！");
        }
        if(!isset($provinces[$provinceCode])){
            throw new \Exception("收件人所在省份编码错误！");
        }
        $citys=\Lib\Common\Region::getCities($provinceCode);
        if (is_array($citys)) {
            if (! $cityCode) {
                throw new \Exception("收件人所在市错误！");
            }
            if(!isset($citys[$cityCode])){
                throw new \Exception("收件人所在市编码错误！");
            }
        }
        $data=app('mysqlbxd_mall_user')->fetch("select a_id aid from address where 
            u_id=:u_id    and a_name=:a_name    and a_phone=:a_phone    and a_provinceCode=:a_provinceCode
            and a_cityCode=:a_cityCode    and a_areaCode=:a_areaCode    and a_address=:a_address",[
            'u_id'=>$this->uid,
            'a_name'=>$name,
            'a_phone'=>$phone,
            'a_provinceCode'=>$provinceCode,
            'a_cityCode'=>$cityCode,
            'a_areaCode'=>$areaCode,
            'a_address'=>$address]);
        if(!$data){
            $params=[
                'uid'=>$this->uid,
                'name'=>$name,
                'phone'=>$phone,
                'provinceCode'=>$provinceCode,
                'cityCode'=>$cityCode,
                'address'=>$address,
                'isDefault'=>$isDefault,
            ];
            if($areaCode)$params['areaCode']=$areaCode;

            $resMall = $this->userAddressLib->add($params);
            $aid = $resMall;
            $data=[
                'aid'=>$aid
            ];
        }else{
            if($isDefault){
                app('mysqlbxd_mall_user')->update('address',[
                    'a_isDefault'=>0
                ],[
                    'u_id'=>$this->uid
                ]);
                app('mysqlbxd_mall_user')->update('address',[
                    'a_isDefault'=>1,
                    'a_isDelete'=>0,
                ],[
                    'a_id'=>$data['aid'],
                    'u_id'=>$this->uid,
                ]);
            }else{
                app('mysqlbxd_mall_user')->update('address',[
                    'a_isDelete'=>0,
                ],[
                    'a_id'=>$data['aid'],
                    'u_id'=>$this->uid
                ]);
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 查询收货地址列表
     */
    public function lists()
    {
        $params = app()->request()->params();
        $isDefault = app()->request()->params('isDefault', 1);
        $params['uid'] = app()->request()->params('uid', '');
        $params['uid']=empty($params['uid'])?$this->uid:$params['uid'];
        $params['isDefault'] = $isDefault;
        $resMall = $this->userAddressLib->lists($params);
        foreach ($resMall as $k => $v) {
            $resMall[$k]['a_provinceName'] = \Lib\Common\Region::getRegionNameByCode($v['a_provinceCode']);
            $resMall[$k]['a_cityName'] = \Lib\Common\Region::getRegionNameByCode($v['a_cityCode']);
            $resMall[$k]['a_areaName'] = \Lib\Common\Region::getRegionNameByCode($v['a_areaCode']);
        }

        $this->responseJSON($resMall);
    }

    /**
     * 获取省市区code编码
     */
    public function getCode(){
        $cityCode = app()->request()->params('cityCode', '');
        $areaCode = app()->request()->params('areaCode', '');
        if ($areaCode){
            $lists=\Lib\Common\Region::getAreas($areaCode);
        }else if ($cityCode){
            $lists=\Lib\Common\Region::getCities($cityCode);
        }else{
            $lists = \Lib\Common\Region::getProvinces();
        }
        $this->responseJSON($lists);
    }

}
