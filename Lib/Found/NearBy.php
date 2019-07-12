<?php
namespace Lib\Found;

use Exception\InternalException;
use Exception\ServiceException;
use Framework\Lib\Validation;
use Exception\ParamsInvalidException;
use Lib\Common\AppMessagePush;

class NearBy
{

    private $userApi = null;

    private $friendsModel = null;

    public function __construct()
    {
        $this->nearByModel = new \Model\Found\NearBy();
        $this->userApi = new \Lib\User\User();
    }

    /**
     * 用户附近玩友
     * @param string $u_id
     * @param string $geohash
     * @param number $page
     * @param number $pagesize
     */
    public function getUserMapListByPage($u_id,$params, $page, $pagesize)
    {
        $userMapInfo = [];
        if (! $u_id) {
            throw new \Exception\ParamsInvalidException("账号必须");
        }
        if (!$params['point_x'] || !$params['point_y']) {
            throw new ServiceException("坐标必须");
        }

        $maxTotal=100;
        if($maxTotal>100){
            return [];
        }

        $mapLib   = new \Model\User\Map();
        $geohashLib   = new \Lib\Map\mGeohash();
        //print_r($params);exit;
        $geohash_str = $geohashLib->encode($params['point_x'],$params['point_y']);
        $_wei = 5;//半径是2.4公里
        //查询所有合适的数据
        do{
            $params['geohash'] = substr($geohash_str, 0,$_wei);
            $findTotal=$mapLib->getUserMapList($params,$u_id,1);
            $findTotal=intval($findTotal);
            $_wei--;
        }while($findTotal<$maxTotal && $_wei>2);
//            var_dump([
//                '$params'=>$params,
//                '$_wei'=>$_wei,
//                '$findTotal'=>$findTotal,
//                '$maxTotal'=>$maxTotal,
//            ]);exit;
        if($findTotal==0){
            return [];
        }
        $userMapInfo=$mapLib->getUserMapList($params,$u_id,0,$maxTotal);

        if (!$userMapInfo)
        {
            return $userMapInfo;
        }
        
        //计算距离
        foreach ($userMapInfo as $key=>$val)
        {
            $um_distance = $geohashLib->getDistance($params['point_y'], $params['point_x'], $val['um_lat'], $val['um_lon']);
            $userMapInfo[$key]['um_distance'] = round($um_distance, 1);
        }
        //print_r($userMapInfo);exit;
        $userMapInfo = $geohashLib->mySortASC('um_distance', $userMapInfo);
//         print_r($userMapInfo);exit;
        //分页处理
        if ($page){
            $userMapInfo = array_slice($userMapInfo,($page-1)*$pagesize,$pagesize);
        }
        
        $this->userApi->extendUserInfos2Array($userMapInfo,'u_id',
          array(
              'u_avatar'=>'u_avatar',
              'u_nickname'=>'u_nickname',
              'u_realname' => 'u_realname',
              'ue_imId' => 'ue_imId',
              //'u_provinceCode'=>'u_provinceCode',
              //'u_cityCode'=>'u_cityCode',
              //'u_areaCode'  => 'u_areaCode',
          ) 
        );
       
        //var_dump($userMapInfo);exit;
        return $userMapInfo;
 
    }
    
    
}
