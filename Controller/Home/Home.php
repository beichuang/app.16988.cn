<?php

/**
 * 首页
 * @author Administrator
 *
 */

namespace Controller\home;

use Lib\Base\BaseController;
use Exception\ModelException;


class Home extends BaseController {

    protected $userApi = null;
    private $goodsLikeLogModel = null;

    public function __construct() {
        parent::__construct();
        $this->userApi = get_api_client('User');
        $this->goodsLikeLogModel = new \Model\Mall\GoodsLikeLog();
    }

    /**
     * 发现频道
     * @param $act
     * @throws ModelException
     */
    public function homeAll() {
        $data = [];

        //推荐机构
        $userLib = new \Lib\User\User();

        $bodiesUid = $userLib->queryUserIsRecommend(2, 1, 10); //推荐机构
        if ($bodiesUid && isset($bodiesUid['list'])) {
            $bodiesUid = $bodiesUid['list'];

            $userLib->extendUserInfos2Array($bodiesUid, 'u_id', array(
                'u_avatar' => 'u_avatar',
                'u_nickname' => 'u_nickname',
                'u_provinceCode' => 'u_provinceCode',
                'u_cityCode' => 'u_cityCode',
                'u_areaCode' => 'u_areaCode',
                'u_realname' => 'uce_realName',
                    )
            );
            $friendsModel = new \Model\Friends\Friends();
            foreach ($bodiesUid as $key => $value) {
                if (isset($value['u_cityCode']) && $value['u_cityCode']) {
                    $bodiesUid[$key]['u_cityName'] = \Lib\Common\Region::getRegionNameByCode($value['u_cityCode']);
                }
                $bodiesUid[$key]['is_friends'] = $friendsModel->relation($this->uid, $value['u_id']);
                $bodiesUid[$key]['is_oneself'] = $this->uid == $value['u_id'] ? 1 : 0;
                $bodiesUid[$key]['list'] = $this->getGoodInfo($value['u_id']); //获取用户商品
            }
        } else {
            $bodiesUid = array();
        }
        $data['bodies_list'] = $bodiesUid;

        //推荐艺术家
        $artist['goodsNum'] = 1;
        $artist['lastUploadTime'] = 1;
        $artist['likeNum'] = 1;
        $recommedUid = $userLib->queryUserIsRecommend(1, 1, 10, $artist); //推荐艺术家uid
        if ($recommedUid && isset($recommedUid['list'])) {
            $recommedUid = $recommedUid['list'];
            $userLib->extendUserInfos2Array($recommedUid, 'u_id', array(
                'u_avatar' => 'u_avatar',
                'u_nickname' => 'u_nickname',
                'u_realname' => 'uce_realName',
                    )
            );

            foreach ($recommedUid as $key => $value) {
                $recommedUid[$key]['is_friends'] = $friendsModel->relation($this->uid, $value['u_id']);
                $recommedUid[$key]['is_oneself'] = $this->uid == $value['u_id'] ? 1 : 0;
                $recommedUid[$key]['list'] = $this->getGoodInfo($value['u_id']); //获取用户商品

                $goodsNum = [];
                $goodsNum = $userLib->getUserExtend($value['u_id']);
                $recommedUid[$key]['goodsNum'] = $goodsNum['list']['ue_goodsNum'];  //作品数量
            }
        } else {
            $recommedUid = array();
        }
        $data['artist_list'] = $recommedUid;

        // 推荐头条
        $newsLib = new \Lib\News\News();
        $favModel = new \Model\User\Favorite();
        $myuid = isset($this->uid) && $this->uid ? $this->uid : 0;

        $newsList = $newsLib->getList(array('addTime' => 1));
        if ($newsList && isset($newsList['list'])) {
            foreach ($newsList['list'] as &$val) {
                $val['n_title']=htmlspecialchars_decode($val['n_title']);
                $time = strtotime($val['n_update_date']);
                $val['displayTime'] = $this->dateFormatToDisplay($time);
                $val['img'] = $newsLib->newsImg($val['n_id'], 6);
                $val['hostImg'] = is_array($val['img']) && !empty($val['img']) ? $val['img'][0]['ni_img'] : '';

                $favInfo = $favModel->oneByUfavObjectId($myuid, $val['n_id']);
                $val['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
                $val['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
            }
            $data['news_list'] = $newsList['list'];
        } else {
            $data['news_list'] = array();
        }

        //优惠券广告
        $adLib = new \Lib\Mall\Ad();
        $params['status'] = 1;
        $params['mark'] = 5;
        $params['isInDate'] = 1;
        $ads = $adLib->lists($params);

        $params['type'] = 5;
        $ads2 = $adLib->lists($params);
        $headerImage = current($ads2['list'])['a_image'] ? current($ads2['list'])['a_image'] : '';

        $showData = array();
        foreach ($ads['list'] as $key => $value) {
            if ($value['a_type'] != 5) {
                $showData[$key]['a_image'] = $value['a_image'];
                $showData[$key]['a_status'] = $value['a_status'];
                $showData[$key]['headerImage'] = $headerImage;
            }
        }
        $data['ad_list'] = $showData;

        $this->responseJSON($data);
    }

    private function dateFormatToDisplay($time) {
        $nowtime = time();
        $difference = $nowtime - $time;
        $msg = '';
        if ($difference <= 3600) {
            $msg = '刚刚';
        } else if ($difference > 3600 && $difference <= 86400) {
            $msg = '1小时前';
        } else if ($difference > 86400) {   //1-7天
            $msg = '1天前';
        }
        return $msg;
    }
    /**
     * 总的列表页入口
     */
    public function getGoodLists()
    {
        $params = app()->request()->params();
        $tabType = isset($params['tabType']) ? $params['tabType'] : '';
        if (!$tabType) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }

        //tabType  1=精品书画, 2=仿古瓷器, 3=文玩杂项, 4=特卖
        //兼容历史版本（tabType=4为特卖，单独处理；tabType在数组中存在为专场标识，否则为商品分类）
        $tabTypeCategories = [
            1 => [11, 20, 31],
            2 => 37,
            3 => 38,
        ];
        $categoryIds = [];
        if ($tabType == 4) {
            //特卖
            $params['highPrice'] = '200';
            $this->getGoods($params);
        } elseif (isset($tabTypeCategories[$tabType])) {
            //专场
            $categoryIds = $tabTypeCategories[$tabType];
        } else {
            //分类
            $categoryIds = $tabType;
        }

        //获取商品分类的下级分类id
        $category = new \Lib\Mall\GoodsCategory();
        $childrenCategoryData = $category->getListByParentId(['parentIds' => $categoryIds]);
        if ($childrenCategoryData) {
            $childrenCategoryIds = array_column($childrenCategoryData, 'c_id');
            $categoryIds = is_array($categoryIds) ? $categoryIds : [$categoryIds];
            $categoryIds = array_merge($childrenCategoryIds, $categoryIds);
            $params['categoryId'] = implode(',', $categoryIds);
        } else {
            $params['categoryId'] = $categoryIds;
        }

        //控制非优选商品也展示
        $params['isShowAll'] = 1;
        return $this->handpickGoods($params);
    }

    /** 根据条件筛选商品
     * @param array $params
     */
    private function getGoods($params = []) {
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 6);

        $params['browseTimes'] = 1;  //1 按浏览量倒序desc， 2 按浏览量正序asc

        $itemController = new \Controller\Mall\Goods\Item();

        $goodList = $itemController->getLists($params);

        $userLib = new \Lib\User\User();

        $userLib->extendUserInfos2Array($goodList['list'], 'g_salesId', array(
            'u_realname' => 'u_realname',
            'u_avatar' => 'u_avatar'
                )
        );
        foreach ($goodList['list'] as &$val) {
            $wnum = $val['g_browseTimes'] / 10000;
            if ($wnum >= 1) {
                $val['g_browseTimes'] = intval($wnum) . '万';
            }
        }
        $data['good_list'] = $goodList['list'];
        $data['count'] = $goodList['count'];

        $this->responseJSON($data);
    }

    /**
     * 推荐商品
     */
    public function recommendGoods($params = []) {
        if ((app()->request()->params('cid'))) {
            $params['categoryId'] = app()->request()->params('cid');
        }
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 6);

        //$params['browseTimes'] = 1;  //1 按浏览量倒序desc， 2 按浏览量正序asc
        $params['grGroup'] = 1; //1 按分组从大到小， 2 按分组从小到大
        $params['grSort'] = 1; //1 按排序从大到小， 2 按排序从小到大

        //$goodList = $itemController->getLists($params);
        $goodList = $this->getRecommendLists($params);

        $userLib = new \Lib\User\User();

        $userLib->extendUserInfos2Array($goodList['list'], 'g_salesId', array(
            'u_realname' => 'u_realname',
            'u_avatar' => 'u_avatar'
                )
        );
        foreach ($goodList['list'] as &$val) {
            $wnum = $val['g_browseTimes'] / 10000;
            if ($wnum >= 1) {
                $val['g_browseTimes'] = intval($wnum) . '万';
            }

            $itemCurrentUserLikeInfo = $this->goodsLikeLogModel->findByUidGcId($this->uid, $val['g_id']);
            $val['itemCurrentUserLikeInfo'] = empty($itemCurrentUserLikeInfo) ? null : $itemCurrentUserLikeInfo;

            if($this->clientType==self::CLIENT_TYPE_ANDROID && $val['isSecKill']){
                $tmpActivityPrice=$val['g_activityPrice'];
                $val['g_activityPrice']=$val['g_price'];
                $val['g_price']=$tmpActivityPrice;
            }

        }
        $data['good_list'] = $goodList['list'];
        $data['count'] = $goodList['count'];

        $this->responseJSON($data);
    }
    /**
     * 获取推荐的商品
     */
    private function getRecommendLists($params=[])
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['g_onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }

        return (new \Lib\Mall\Goods())->itemRecommendQuery($params);
    }
    /**
     * 精选商品
     */
    public function handpickGoods($params = []) {

        if ((app()->request()->params('cid'))) {
            $params['categoryId'] = app()->request()->params('cid');
        }
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 6);

        //$params['browseTimes'] = 1;  //1 按浏览量倒序desc， 2 按浏览量正序asc
        $params['ghGroup'] = 1; //1 按分组从大到小， 2 按分组从小到大
        $params['ghSort'] = 1; //1 按排序从大到小， 2 按排序从小到大

        $itemController = new \Controller\Mall\Goods\Item();

        $goodList = $itemController->getHandpickLists($params);
        //$goodList = $itemController->getLists($params);

        $userLib = new \Lib\User\User();

        $userLib->extendUserInfos2Array($goodList['list'], 'g_salesId', array(
            'u_realname' => 'u_realname',
            'u_avatar' => 'u_avatar',
            'u_nickname' => 'u_nickname',
                )
        );
        foreach ($goodList['list'] as &$val) {
            $wnum = $val['g_browseTimes'] / 10000;
            if ($wnum >= 1) {
                $val['g_browseTimes'] = intval($wnum) . '万';
            }
        }
        $data['good_list'] = $goodList['list'];
        $data['count'] = $goodList['count'];

        $this->responseJSON($data);
    }

    /**
     * 格式用户信息，给app使用
     * @param unknown $userInfo
     * @return multitype:NULL unknown string
     */
    private function getGoodInfo($uid) {
        $resInfo = [];
        $item_arr = [
            'salesId' => $uid,
            'page' => 1,
            'pageSize' => 5,
        ];
        $itemController = new \Controller\Mall\Goods\Item();

        $item_List = $itemController->getLists($item_arr);
        //return $item_List;
        if ($item_List['list']) {
            foreach ($item_List['list'] as $key => $val) {
                if ($val['image']) {
                    $resInfo[$key]['g_id'] = $val['g_id'];
                    $resInfo[$key] = $val['image'] ? $val['image'][0] : '';
                }
            }
        }
        return array_values($resInfo);
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
    private function handleData($data = '', $filter = '') {
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
                return (array) $data;
                break;
        }

        return '';
    }

}
