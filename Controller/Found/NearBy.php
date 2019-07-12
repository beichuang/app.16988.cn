<?php

/**
 * 发现
 * @author Administrator
 *
 */

namespace Controller\Found;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ModelException;
use Exception\ServiceException;
use Lib\Common\Region;
use Model\Friends\Friends;

class NearBy extends BaseController
{

    protected $userApi = null;

    public function __construct()
    {
        parent::__construct();
        $this->userApi = get_api_client('User');
    }

    /**
     * 发现频道
     *
     * @throws ModelException
     */
    public function userFoundAll()
    {
        $data = [];
        $uid = $this->uid;
        $point_x = app()->request()->params('point_x');
        $point_y = app()->request()->params('point_y');

        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (!$point_x || !$point_y) {
            throw new \Exception\ParamsInvalidException("坐标必须");
        }

        $this->handleData($point_x, 'str');
        $this->handleData($point_y, 'str');

        $mapLib = new \Lib\Found\NearBy();
        $friendsModel = new Friends();

        $params = [
            'point_x' => $point_x,
            'point_y' => $point_y,
//            'type' => 0,
        ];
        //附近玩友
        $data['near_play_friends'] = $mapLib->getUserMapListByPage($uid, $params, $page, $pageSize);
        //附近机构（已废弃，暂时保留字段）
        $data['near_trusts'] = [];
        //推荐艺术家（已废弃，暂时保留字段）
        $data['artist_list'] = [];

        if ($data['near_play_friends'] && is_array($data['near_play_friends'])) {
            foreach ($data['near_play_friends'] as &$row) {
                $row['is_friends'] = $friendsModel->relation($this->uid, $row['u_id']);
                //附近玩友返回玩友的人气值（粉丝数*100）
                $fansCount = $friendsModel->lists(['fri_friendId' => $row['u_id']])[1];
                $row['popularity'] = $fansCount * 100;
            }
        }

        $this->responseJSON($data);
    }

    /**
     * 推荐机构
     */
    public function recommendBodies()
    {
        $data = [];

        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        //推荐艺术家
        $userLib = new \Lib\User\User();

        $itemController = new \Controller\Mall\Goods\Item();

        $recommedUid = $userLib->queryUserIsRecommend(2, $page, $pageSize); //推荐机构

        $count = $recommedUid['count'];
        $recommedUid = $recommedUid['list'];

        $userLib->extendUserInfos2Array($recommedUid, 'u_id', array(
                'u_avatar' => 'u_avatar',
                'u_nickname' => 'u_nickname',
                'u_provinceCode' => 'u_provinceCode',
                'u_cityCode' => 'u_cityCode',
                'u_areaCode' => 'u_areaCode',
                'u_realname' => 'uce_realName',
            )
        );

        $certificationModel = new \Model\User\Certification();

        $Enterprise_arr = conf('Enterprise.EnterpriseType');

        $friendsModel = new \Model\Friends\Friends();

        //print_r($recommedUid);exit;
        foreach ($recommedUid as $key => $value) {
            $recommedUid[$key]['u_cityName'] = \Lib\Common\Region::getRegionNameByCode($value['u_cityCode']);
            $recommedUid[$key]['is_friends'] = $friendsModel->relation($this->uid, $value['u_id']);
            $recommedUid[$key]['is_oneself'] = $this->uid == $value['u_id'] ? 1 : 0;
            //print_r($value['u_id']);exit;
            $certificationArr = $certificationModel->getCertInfo($value['u_id']);
            // $recommedUid[$key]['uce_realName']          = $certificationArr['uce_realName'];
            $recommedUid[$key]['uce_enterpriseTypeName'] = $Enterprise_arr[$certificationArr['uce_enterpriseType']];

            $recommedUid[$key]['list'] = $this->getGoodInfo($value['u_id']); //获取用户商品

            $goodsNum = $userLib->getUserExtend($value['u_id']);
            $recommedUid[$key]['goodsNum'] = $goodsNum['list']['ue_goodsNum']; // 作品数量
        }
        $data['bodies_list'] = $recommedUid;
        $data['count'] = $count;
        $this->responseJSON($data);
    }

    /**
     * 推荐艺术家
     */
    public function recommendArtist()
    {
        $is_own_shop = app()->request()->params('is_own_show');
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 10);
        if (empty($is_own_shop)) {
            $params['type'] = 1;
        } else {
            $params['is_own_shop'] = 1;
        }
        $params['goodsNum']=1;
        $params['goodsNumMin']=3;
        $result = api_request($params, 'user/query/recommend');
        $artist_list=[];
        if ($result && $result['count'] > 0) {
            $friendsModel = new \Model\Friends\Friends();
            $user_list = api_request(['uids' => implode(',', array_column($result['list'], 'u_id')), 'needExtend' => 1], 'user/get');
            foreach ($result['list'] as $val) {
                $val['u_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $val['u_provinceCode'] = $user_list[$val['u_id']]['u_provinceCode'];
                $val['u_cityCode'] = $user_list[$val['u_id']]['u_cityCode'];
                $val['u_areaCode'] = $user_list[$val['u_id']]['u_areaCode'];
                $u_realname = $user_list[$val['u_id']]['u_realname'];
                $val['u_realname'] = $u_realname;
                $val['u_nickname'] = empty($u_realname) ? $user_list[$val['u_id']]['u_nickname'] : $u_realname;

                $val['u_cityName'] = \Lib\Common\Region::getRegionNameByCode($val['u_cityCode']);
                $val['is_friends'] = $friendsModel->relation($this->uid, $val['u_id']);
                $val['is_oneself'] = $this->uid == $val['u_id'] ? 1 : 0;
                $val['goodsNum'] = $user_list[$val['u_id']]['user_extend']['ue_goodsNum'];
                $val['is_own_shop'] = $user_list[$val['u_id']]['user_extend']['is_own_shop'];
                $val['ue_celebrityTitle'] = $user_list[$val['u_id']]['user_extend']['ue_celebrityTitle'];
                $val['list'] = $this->getGoodInfo($val['u_id']); //获取用户商品
                $len=count($val['list']);
                if($len<3){
                    continue;
//                    $val['goodsNum']=count($val['list']);
//                    //没有商品就不推荐
//                    if($len==0){
//                        continue;
//                    }
//                    for (;$len<3;$len++){
//                        $val['list'][]=[
//                            "gi_id"=>"0",
//                            "g_id"=>"0",
//                            "gi_img"=>"",//https://cdn.16988.cn/res/html/pc/images/foot-logo.png
//                            "gi_sort"=>$len+1,
//                            "gi_width"=>"0",
//                            "gi_height"=>"0",
//                            "gi_img_path"=>""
//                        ];
//                    }
                }
                $artist_list[]=$val;
            }
        }
        $this->responseJSON(['count' => $result['count'], 'artist_list' => $artist_list]);
    }

    /**
     * 新加入艺术家
     */
    public function newArtist()
    {
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 5);

        $data = $this->getAllLists($page, $pageSize);
        $this->responseJSON($data);
    }

    private function getAllLists($page = 1, $pageSize = 5)
    {
        $data['artist_list'] = [];
        $data['count'] = 0;
        $userLib = new \Lib\User\User();
        $friendsModel = new \Model\Friends\Friends();

        $resData = $userLib->getUserArtList($page, $pageSize);
        if (!empty($resData) && !empty($resData['list'])) {
            $userArtList = $resData['list'];
            $userArtCount = $resData['count'];
            foreach ($userArtList as $item) {
                $userArt = [];
                $userGoods = $this->getGoodInfo($item['u_id']); //获取用户商品
                $userArt['u_id'] = $item['u_id'];
                $userArt['u_avatar'] = $item['u_avatar'];
                $userArt['u_nickname'] = empty($item['u_nickname']) ?  ("玩友" . substr($item['u_phone'], - 4, 4)) : $item['u_nickname'];
                $userArt['uce_realName'] = $item['u_realname'];
                $userArt['u_cityName'] = \Lib\Common\Region::getRegionNameByCode($item['u_cityCode']);
                $userArt['is_friends'] = $friendsModel->relation($this->uid, $item['u_id']);
                $userArt['is_oneself'] = $this->uid == $item['u_id'] ? 1 : 0;
                //对 gi_sort 做特殊处理
                if(!empty($userGoods)){
                    foreach($userGoods as &$userGood){
                        !isset($userGood["gi_sort"])?$userGood["gi_sort"]=0:null;
                    }
                }
                $userArt['list'] = $userGoods;
                $userArt['isRecommend'] = $item['ue_isRecommend'];
                $userArt['isRecommendSort'] = $item['ue_isRecommendSort'];
                $userArt['goodsNum'] = $item['ue_goodsNum']; // 作品数量
                $userArt['lastUploadTime'] = $item['ue_lastUploadTime']; //最后上传作品的时间
                $userArt['g_browseNum'] = $item['ue_browseNum'];   //所有商品的浏览总量
                $userArt['g_likeNum'] = $item['ue_likeNum'];    //所有商品的点赞总数
                $userArt['uce_isCelebrity'] = $item['u_type'];    //是否艺术家，0不是，1 是艺术家，2是机构
                $userArt['ue_celebrityTitle'] = $item['ue_celebrityTitle'];   //头衔，标签

                $data['artist_list'][] = $userArt;
            }

            $data['count'] = $userArtCount;
        }

        return $data;
    }

    /**
     * 新增附近玩友
     *
     * @throws ModelException
     */
    public function userNearList()
    {
        $uid = $this->uid;
        //echo $uid;exit;
        $point_x = app()->request()->params('point_x');
        $point_y = app()->request()->params('point_y');
        $type = app()->request()->params('type', '');

        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (!$point_x || !$point_y) {
            throw new \Exception\ParamsInvalidException("坐标必须");
        }
        $point_x = $this->handleData($point_x, 'str');

        $point_y = $this->handleData($point_y, 'str');

        $params = [
            'point_x' => $point_x,
            'point_y' => $point_y,
        ];
        if($type!==''){
            $type = $this->handleData($type, 'int');
            $params['type']=$type; //0是玩友，1是艺术家，2是机构
        }

        $mapLib = new \Lib\Found\NearBy();

        $userInfo = $mapLib->getUserMapListByPage($uid, $params, $page, $pageSize);
        if ($type == 2) {
            $certificationModel = new \Model\User\Certification();
            $configKey = 'user_certification';
            foreach ($userInfo as &$v) {
                $certificationArr = $certificationModel->getNearInfo($v['u_id']);
                $v['uce_status'] = $certificationArr['uce_status'];
                $v['uce_photoStorefront'] = FileHelper::getFileUrl($certificationArr['uce_photoStorefront'], $configKey);
                $v['uce_address'] = $certificationArr['uce_address'];
            }
        }
        $this->responseJSON(array_values($userInfo));
    }

    /**
     * 格式用户信息，给app使用
     * @param unknown $userInfo
     * @return multitype:NULL unknown string
     */
    private function getGoodInfo($uid)
    {
        $resInfo = [];
        $item_arr = [
            'salesId' => $uid,
            'page' => 1,
            'pageSize' => 3,
            'updateTime'=>1,
        ];
        $itemController = new \Controller\Mall\Goods\Item();

        $item_List = $itemController->getLists($item_arr);
        if ($item_List['list']) {
            foreach ($item_List['list'] as $key => $val) {
//                $row=[
//                    "gi_id"=>"0",
//                    "g_id"=>"0",
//                    "gi_img"=>"",//https://cdn.16988.cn/res/html/pc/images/foot-logo.png
//                    "gi_sort"=>$key+1,
//                    "gi_width"=>"0",
//                    "gi_height"=>"0",
//                    "gi_img_path"=>""
//                ];
                if ($val['g_surfaceImg']) {
                    $row['g_id']=$val['g_id'];
                    $row=array_merge($row,$val['g_surfaceImg']);
                    $resInfo[]=$row;
                }else if ($val['image']) {
                    $row['g_id']=$val['g_id'];
                    $row=array_merge($row,$val['image'][0]);
                    $resInfo[]=$row;
                }
            }
        }
        return array_values($resInfo);
    }

    /**
     * 附近藏友
     * @deprecated 已弃用
     * @throws ModelException
     */
    public function userList()
    {
        $provinceCode = app()->request()->params('provinceCode');
        $cityCode = app()->request()->params('cityCode');
        $areaCode = app()->request()->params('areaCode');
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (!$provinceCode) {
            throw new \Exception\ParamsInvalidException("省份必须");
        }
        $this->handleData($provinceCode, 'int');
        $this->userApi->chooseRequest('user/get/by/region', 1)->setParam('provinceCode', $provinceCode);
        if (isset($cityCode) && $cityCode) {
            $this->handleData($cityCode, 'int');
            $this->userApi->setParam('cityCode', $cityCode);
        }
        if (isset($areaCode)) {
            $this->handleData($areaCode, 'int');
            $this->userApi->setParam('areaCode', $areaCode);
        }
        if (isset($page)) {
            $this->handleData($page, 'int');
            $this->userApi->setParam('page', $page);
        }
        $this->userApi->setParam('pageSize', $pageSize);
        $regRes = $this->userApi->execRequest();
        if ($regRes->code != 200) {
            throw new ServiceException($regRes->data);
        } else {
            $userInfo = $regRes->data;
        }

        $treasuresModel = new \Model\Treasure\Treasure();
        $treasuresLikeModel = new \Model\Treasure\TreasureLikeLog();
        $settingModel = new \Model\User\Setting();
        $friendsModel = new \Model\Friends\Friends();
        $certificationModel = new \Model\User\Certification();
        foreach ($userInfo[0] as &$v) {
            list ($treasures, $treasuresTotalCount) = $treasuresModel->lists(
                array(
                    'u_id' => $v['u_id']
                ), $page, $pageSize);
            $v['treasuresTotal'] = $treasuresTotalCount;
            list ($treasuresLikes, $treasuresLikesTotalCount) = $treasuresLikeModel->lists(
                array(
                    'u_id' => $v['u_id']
                ), $page, $pageSize);
            $v['treasuresLikesTotal'] = $treasuresLikesTotalCount;
            $v['hobby'] = $settingModel->settingGetValue($v['u_id'], 'hobby');
            $v['isFriends'] = $friendsModel->oneByUidFirendId($this->uid, $v['u_id']);
            $v['u_provinceName'] = Region::getRegionNameByCode($v['u_provinceCode']);
            $v['u_cityName'] = Region::getRegionNameByCode($v['u_cityCode']);
            $v['u_areaName'] = Region::getRegionNameByCode($v['u_areaCode']);
            $v['uce_status'] = $certificationModel->getStatus($v['u_id']);
        }
        $this->responseJSON(array_values($userInfo[0]));
    }

    /**
     * 新增附近商品
     *
     * @throws ModelException
     */
    public function goodsList()
    {
        $provinceCode = app()->request()->params('provinceCode');
        $cityCode = app()->request()->params('cityCode');
        $areaCode = app()->request()->params('areaCode');
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);

        $mallApi = get_api_client('Mall');
        if (!$provinceCode) {
            throw new \Exception\ParamsInvalidException("省份必须");
        }
        $this->handleData($provinceCode, 'int');
        $mallApi->chooseRequest('mall/get/goods/list', 1)->setParam('provinceCode', $provinceCode);
        if (isset($cityCode) && $cityCode) {
            $this->handleData($cityCode, 'int');
            $mallApi->setParam('cityCode', $cityCode);
        }
        if (isset($areaCode)) {
            $this->handleData($areaCode, 'int');
            $mallApi->setParam('areaCode', $areaCode);
        }

        $regRes = $mallApi->execRequest();
        if ($regRes->code != 200) {
            throw new ServiceException($regRes->data, $regRes->code);
        } else {
            $userInfo = $regRes->data;
        }
        $this->responseJSON($userInfo[0]);
    }

    /**
     * 新增附近名家
     *
     * @throws ModelException
     */
    public function celebrity()
    {
        $model = new \Model\User\Certification();
        $provinceCode = app()->request()->params('provinceCode');
        $cityCode = app()->request()->params('cityCode');
        $areaCode = app()->request()->params('areaCode');
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);

        if (!$provinceCode) {
            throw new \Exception\ParamsInvalidException("省份必须");
        }
        $this->handleData($provinceCode, 'int');
        if (isset($cityCode) && $cityCode) {
            $this->handleData($cityCode, 'int');
        }
        if (isset($areaCode) && $areaCode) {
            $this->handleData($areaCode, 'int');
        }
        $params = array(
            'uce_provinceCode' => $provinceCode,
            'uce_cityCode' => isset($cityCode) ? $cityCode : "",
            'uce_areaCode' => isset($areaCode) ? $areaCode : "",
            'uce_isCelebrity' => 1
        );
        // var_dump($param);die;
        list ($lists, $count) = $model->lists($params, $page, $pageSize);
        $uids = array();
        $users = array();
        if ($count > 0) {
            foreach ($lists as $r) {
                $uids[] = $r['u_id'];
            }
        }
        if (!empty($uids)) {
            $this->userApi->chooseRequest('user/get', 1)->setParam('uids', implode(',', $uids));
            // ->setParam('needExtend', 1);
            $res = $this->userApi->execRequest();
            if ($res->code != 200) {
                throw new ServiceException($res->data);
            } else {
                $users = $res->data;
            }
        }
        $treasuresModel = new \Model\Treasure\Treasure();
        $treasuresLikeModel = new \Model\Treasure\TreasureLikeLog();
        $settingModel = new \Model\User\Setting();
        $friendsModel = new \Model\Friends\Friends();
        foreach ($users as &$v) {
            list ($treasures, $treasuresTotalCount) = $treasuresModel->lists(
                array(
                    'u_id' => $v['u_id']
                ), $page, $pageSize);
            $v['treasuresTotal'] = $treasuresTotalCount;
            list ($treasuresLikes, $treasuresLikesTotalCount) = $treasuresLikeModel->lists(
                array(
                    'u_id' => $v['u_id']
                ), $page, $pageSize);
            $v['treasuresLikesTotal'] = $treasuresLikesTotalCount;
            $v['hobby'] = $settingModel->settingGetValue($v['u_id'], 'hobby');
            $v['isFriends'] = $friendsModel->oneByUidFirendId($this->uid, $v['u_id']);
            $v['u_provinceName'] = Region::getRegionNameByCode($v['u_provinceCode']);
            $v['u_cityName'] = Region::getRegionNameByCode($v['u_cityCode']);
            $v['u_areaName'] = Region::getRegionNameByCode($v['u_areaCode']);
        }
        $this->responseJSON(array_values($users));
    }

    /**
     * 对数据进行初级过滤
     *
     * @param string $data
     *            要处理的数据
     * @param string $filter
     *            过滤的方式
     * @return mix
     */
    private function handleData($data = '', $filter = '')
    {
        switch ($filter) {
            case 'int':
                return abs(intval($data));
                break;

            case 'str':
                return trim(htmlspecialchars(strip_tags($data)));
                break;

            case 'float':
                return floatval($data);
                break;

            case 'arr':
                return (array)$data;
                break;
        }

        return '';
    }

}
