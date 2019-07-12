<?php

// 活动管理

namespace Controller\Activity;

use Exception\ParamsInvalidException;
use Exception\ServiceException;
use Framework\Helper\FileHelper;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;

class Index extends BaseController
{
    //活动id
    const ACTIVITY_ID = 1;
    //活动开始时间
    const ACTIVITY_BEGIN_TIME = '2018-6-1';
    //活动结束时间
    const ACTIVITY_END_TIME = '2018-6-26 23:59:59';
    //活动主页面地址
    const MAIN_PAGE = '/html/apph5/handWriting.html#/index ';
    //活动详情页地址
    const DETAIL_PAGE = '/html/apph5/handWriting.html#/detail';
    //活动报名页地址
    const REGISTER_PAGE = '/html/apph5/handWriting.html#/signin';
    //线下比赛签到页面地址
    const SIGN_ON_PAGE = '/html/apph5/exhiBition.html#/finalsign';
    //生成登机牌页面地址
    const CREATE_BOARDING_PASS_PAGE ='/html/apph5/biArtifact.html';
    //趣味海报页面地址
    const INTERESTING_POSTER_PAGE ='/html/apph5/duStory.html';
    //活动缓存前缀
    const CACHE_PREFIX = 'activity_sf_';
    //书法打卡页面地址
    const CALLIGRAPHY_PAGE ='/html/apph5/calCarding.html#/home';
    //书法打卡活动开始时间
    const CALLIGRAPHY_BEGIN_TIME = '2018-12-1';
    //书法打卡活动结束时间
    const CALLIGRAPHY_END_TIME = '2019-1-31 23:59:59';
    /**
     * 首页
     */
    public function mainPage()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            //增加活动访问量
            $this->updateTotalView();
            app()->redirect(self::MAIN_PAGE);
        }
    }

    /**
     * 参赛者详情页
     */
    public function detailPage()
    {
        $id = app()->request()->params('id');
        if (empty($id)) {
            app()->redirect(self::MAIN_PAGE);
            return;
        }

        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            //增加活动访问量
            $this->updateTotalView();
            app()->redirect(self::DETAIL_PAGE . '?id=' . $id);
        }
    }

    /**
     * 报名页面
     */
    public function registerPage()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            //增加活动访问量
            $this->updateTotalView();
            app()->redirect(self::REGISTER_PAGE);
        }
    }

    /**
     * 获取首页统计数据
     */
    public function getAllTotal()
    {
        $contestantTotal = $this->getContestantTotal();
        $voteTotal = $this->getVoteTotal();
        $viewTotal = $this->getViewTotal();
        $data = [
            'contestantTotal' => $contestantTotal,
            'voteTotal' => $voteTotal,
            'viewTotal' => $viewTotal
        ];

        return $this->responseJSON($data);
    }

    /**
     * 获取最新榜单
     */
    public function getNewList()
    {
        $condition = [];
        $params = app()->request()->params();
        $page = empty($params['page']) ? 1 : $params['page'];
        $pageSize = empty($params['pageSize']) ? 0 : $params['pageSize'];
        if (isset($params['key'])) {
            if (strval(intval($params['key'])) == $params['key']) {
                $condition['id'] = $params['key'];
            } else {
                $condition['name'] = $params['key'];
            }
        }
        list($list, $count) = $this->getData('new', $condition, $page, $pageSize);

        return $this->responseJSON(['list' => $list, 'count' => $count]);
    }

    /**
     * 获取排行榜
     */
    public function getRankingList()
    {
        $params = app()->request()->params();
        $page = empty($params['page']) ? 1 : $params['page'];
        $pageSize = empty($params['pageSize']) ? 0 : $params['pageSize'];
        list($list, $count) = $this->getData('ranking', $params, $page, $pageSize);

        return $this->responseJSON(['list' => $list, 'count' => $count]);
    }

    /**
     * 获取参赛者详情信息
     */
    public function getContestantDetail()
    {
        $params = app()->request()->params();
        if (empty($params['id'])) {
            return $this->responseJSON([], 1, 1, '参数不正确');
        }
        $id = $params['id'];
        $contestantData = $this->getContestantById($id);
        if ($contestantData) {
            $contestantImageData = $this->getContestantImages($id);
            if ($contestantImageData) {
                $contestantData['ac_surfaceImg_url'] = FileHelper::getFileUrl($contestantData['ac_surfaceImg'], 'activity_images', '', 300);
                foreach ($contestantImageData as $item) {
                    $item['aci_img_url'] = FileHelper::getFileUrl($item['aci_img'], 'activity_images');
                    $contestantData['images'][] = $item;
                }
            }
            //是否已投票
            $openId = WxHelper::getCookieOpenId();
            $contestantData['ac_is_voted'] = self::getIsVoted($openId, $id);
        }

        return $this->responseJSON($contestantData);
    }

    /**
     * 投票
     */
    public function vote()
    {
        $params = app()->request()->params();
        if (empty($params['id'])) {
            return $this->responseJSON([], 1, 1, '参数不正确');
        }
        $id = $params['id'];
        if (!WxHelper::isWeiXinPortal()) {
            return $this->responseJSON([], 1, 1, '请在微信客户端打开此页面');
        }
        //投票者微信openid
        $openId = WxHelper::getOpenId();
        if (empty($openId)) {
            return $this->responseJSON([], 1, 1, '请在微信客户端打开此页面');
        }
        //是否在活动日期
        $activityStatus = $this->getActivityStatus();
        if ($activityStatus === 0) {
            return $this->responseJSON([], 1, 1, '活动暂未开始');
        } elseif ($activityStatus == 2) {
            return $this->responseJSON([], 1, 1, '活动已结束');
        }

        //当天是否已超过最大投票数
        $userVoteKey = self::getUserVoteKey($openId);
        //当天是否已超过给某个参赛者最大投票数
        $userVoteContestantKey = self::getUserVoteContestantKey($openId, $id);
        /** @var \Redis $redis */
        $redis = app('redis');
        $userVoteTotal = 0;
        $userVoteContestantTotal = 0;
        if ($redis->exists($userVoteKey)) {
            $userVoteTotal = $redis->get($userVoteKey);
        }
        if ($userVoteTotal >= 3) {
            return $this->responseJSON([], 1, 1, '你已达到当天最大投票数');
        }

        if ($redis->exists($userVoteContestantKey)) {
            $userVoteContestantTotal = $redis->get($userVoteContestantKey);
        }
        if ($userVoteContestantTotal >= 1) {
            return $this->responseJSON([], 1, 1, '今天已经给该参赛者投过票了');
        }

        $this->addVote($id, $openId);
        //添加缓存
        $redis->setex($userVoteKey, 86400, $userVoteTotal + 1);
        $redis->setex($userVoteContestantKey, 86400, $userVoteContestantTotal + 1);

        return $this->responseJSON([], 0, 0, '投票成功');
    }

    /**
     * @Summary :添加用户
     * @Author yyb update at 2018/6/4 9:37
     */
    public function addContestant()
    {
        if (!WxHelper::isWeiXinPortal()) {
            return $this->responseJSON([], 1, 1, '请在微信客户端打开此页面');
        }

        $params = app()->request()->params();
        $requireParams = ['ac_name', 'ac_phone', 'ac_sex', 'ac_age', 'ac_category', 'ac_img'];
        foreach ($requireParams as $paramName) {
            if (empty($params[$paramName])) {
                return $this->responseJSON([], 1, 2, '缺少参数' . $paramName);
            }
        }
        $images = empty($params['ac_img']) ? [] : json_decode($params['ac_img'], true);
        if (count($images) < 2) {
            return $this->responseJSON([], 1, 1, '请至少上传2张图片');
        } elseif (count($images) > 5) {
            return $this->responseJSON([], 1, 1, '最多上传5张图片');
        }

        //获取openid
        if (empty($params['ac_openid'])) {
            $params['ac_openid'] = WxHelper::getOpenId();
        }
        $openId = $params['ac_openid'];
        if (empty($openId)) {
            return $this->responseJSON([], 1, 1, '请在微信客户端打开此页面');
        }
        //判断是否关注公众号
        $isSubscribe = WxHelper::getIsSubscribe($openId);
        if (empty($isSubscribe)) {
            return $this->responseJSON(['isSubscribe' => false], 1, 2, '未关注公众号');
        }

        //是否在活动日期
        $activityStatus = $this->getActivityStatus();
        if ($activityStatus === 0) {
            return $this->responseJSON([], 1, 1, '活动暂未开始');
        } elseif ($activityStatus == 2) {
            return $this->responseJSON([], 1, 1, '活动已结束');
        }

        foreach ($images as $key => $value) {
            if ($value['aci_imageType'] == 1) {
                $data['ac_surfaceImg'] = $value['aci_img'];
                break;
            }
        }

        $data['ac_name'] = $params['ac_name'];;
        $data['ac_phone'] = $params['ac_phone'];;
        $data['ac_sex'] = $params['ac_sex'];;
        $data['ac_age'] = $params['ac_age'];;
        $data['ac_organization_name'] = empty($params['ac_organization_name']) ? '' : $params['ac_organization_name'];
        $data['ac_category'] = $params['ac_category'];;
        $data['ac_openid'] = $openId;
        $data['ac_createTime'] = date('Y-m-d H:i:s');
        $data['ac_voteNumber'] = 0;
        $data['ac_auditStatus'] = 0;

        //存储
        $res = $this->insertContestant($data);
        $ac_id = $res[1];

        foreach ($images as $key => $value) {
            $dataImg['ac_id'] = $ac_id;
            $dataImg['aci_img'] = $value['aci_img'];
            $dataImg['aci_sort'] = $value['aci_sort'];
            $dataImg['aci_width'] = $value['aci_width'];
            $dataImg['aci_height'] = $value['aci_height'];
            $dataImg['aci_imageType'] = isset($value['aci_imageType']) && $value['aci_imageType'] == 1 ? 1 : 2;
            $dataImg['aci_createDate'] = date('Y-m-d H:i:s');
            $this->insertContestantImage($dataImg);
        }

        return $this->responseJSON($res[1], 0, 0, '报名成功');
    }

    /**
     * 线下比赛签到页面初始化
     */
    public function signOnInit180701()
    {
        $list = app('mysqlbxd_app')->select('select ac_id user_id,
ac_offlineSignTime sign_time ,ac_category as category ,ac_name as `name`
from activity_contestant where ac_isOffline=1 order by ac_category,ac_id');
        if (empty($list)) {
            $list = [];
        }
        $data = [
            '1' => [
                'list' => []
            ],
            '2' => [
                'list' => []
            ]
        ];

        foreach ($list as $user) {
            $data[$user['category']]['list'][] = $user;
        }
        $data['is_wx_subscribe'] = WxHelper::getIsSubscribe();
        $this->responseJSON($data);
    }

    /**
     * 线下比赛签到页面
     */
    public function signOn180701()
    {
        if (app()->request->isPost()) {
            $params = app()->request->params();
            $user_ids_str = isset($params['user_ids']) ? $params['user_ids'] : '';
            $user_ids = explode(',', $user_ids_str);
            $user_ids = array_filter($user_ids);
            if ($user_ids) {
                $user_ids_sql_str = implode(',', $user_ids);
                $sql = "update activity_contestant set ac_offlineSignTime=:ac_offlineSignTime 
 where ac_id in ({$user_ids_sql_str}) and ac_offlineSignTime is null 
 and ac_isOffline=:ac_isOffline and ac_auditStatus=:ac_auditStatus";
                if (!app('mysqlbxd_app')->query($sql, [
                    'ac_offlineSignTime' => date('Y-m-d H:i:s'),
                    'ac_isOffline' => 1,//参与线下比赛
                    'ac_auditStatus' => 1,//通过审核
                ])
                ) {
                    throw new \Exception("签到失败，请稍候再试");
                }
                if (app('mysqlbxd_app')->getEffectRowCount() == 0) {
                    throw new \Exception("已签到");
                }
            } else {
                throw new \Exception("请选择一个选手");
            }
            $this->responseJSON([$user_ids_sql_str], 0, 0, 'success');
        } else {
            $openId = WxHelper::getOpenId();
            if (!empty($openId)) {
                app()->redirect(self::SIGN_ON_PAGE);
            }
        }
    }

    /**
     * 未排号的选手
     */
    public function usersNoSort180701()
    {
        $list = app('mysqlbxd_app')->select('select ac_id user_id, ac_offlineSignMobile user_mobile, 
ac_offlineSignTime sign_time ,ac_category as category ,ac_name as `name` ,ac_offlineNo
from activity_contestant where ac_isOffline=1 and ac_offlineSignTime is not null ');
        if (empty($list)) {
            $list = [];
        }
        $data = [
            '1' => [
                'is_sort' => 0,
                'list' => []
            ],
            '2' => [
                'is_sort' => 0,
                'list' => []
            ]
        ];

        foreach ($list as $user) {
            $data[$user['category']]['list'][] = $user;
            if (!$data[$user['category']]['is_sort'] && $user['ac_offlineNo'] > 0) {
                $data[$user['category']]['is_sort'] = 1;
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 给选手排号
     */
    public function sortUsers180701()
    {
//        $key='fjewfndsalFHjk43r98432fHFff564w6f4z3g7czx34DH';
//        if(app()->request->params('key','')!=$key){
//            if(app()->request->isAjax()){
//                $this->reponseJSON([],1,1,'未授权访问');
//            }
//        }
        $userids_json = app()->request->params('user_ids');
        $category = app()->request->params('category');
        if (!in_array($category, [1, 2])) {
            throw new \Exception("category只能是1：毛笔,2：硬笔");
        }
        $userids = json_decode($userids_json);
        if (json_last_error() != 0) {
            throw new \Exception("user_ids不是有效的json");
        }
        if (!is_array($userids)) {
            throw new \Exception("参数错误");
        }
        app('mysqlbxd_app')->beginTransaction();
        try {
            app('mysqlbxd_app')->query('update activity_contestant set ac_offlineNo=0 where ac_category=:ac_category', [
                'ac_category' => $category,
            ]);
            $useridsStr = implode(',', $userids);
            $uidsInDb = app('mysqlbxd_app')->select("select ac_id from activity_contestant 
where ac_category=:ac_category and ac_id in({$useridsStr}) and ac_offlineSignTime is not null ", [
                'ac_category' => $category,
            ]);
            if ($uidsInDb && is_array($uidsInDb)) {
                $uidsInDbCol = array_column($uidsInDb, 'ac_id');
                $i = 1;
                foreach ($userids as $uid) {
                    if (in_array($uid, $uidsInDbCol)) {
                        app('mysqlbxd_app')->update('activity_contestant', [
                            'ac_offlineNo' => $i
                        ], [
                            'ac_id' => $uid
                        ]);
                        $i++;
                    }
                }
            }
            app('mysqlbxd_app')->commit();
        } catch (\Exception $e) {
            app('mysqlbxd_app')->rollback();
            throw $e;
        }
        $this->responseJSON([], 0, 0, 'success');
    }

    /**
     * 获取活动状态
     * @return int
     */
    private function getActivityStatus()
    {
        $time = time();
        $beginTime = strtotime(self::ACTIVITY_BEGIN_TIME);
        $endTime = strtotime(self::ACTIVITY_END_TIME);

        if ($time < $beginTime) {
            $status = 0;
        } elseif ($time > $endTime) {
            $status = 2;
        } else {
            $status = 1;
        }

        return $status;
    }

    /**
     * 获取参赛列表数据
     * @param $type string 列表类型
     * @param $conditions array 筛选数据
     * @param $page int 页码
     * @param $pageSize int 每页记录数
     * @return array
     */
    private function getData($type, $conditions, $page, $pageSize = 0)
    {
        $openId = WxHelper::getCookieOpenId();
        if ($page >= 1) {
            $pageIndex = $page - 1;
        } else {
            $pageIndex = 0;
        }

        if ($type == 'ranking') {
            $pageSize = empty($pageSize) ? 12 : $pageSize;
            list($list, $count) = $this->queryData($conditions, 'ac_voteNumber', $pageIndex, $pageSize);
        } else {
            $pageSize = empty($pageSize) ? 10 : $pageSize;
            list($list, $count) = $this->queryData($conditions, 'ac_createTime', $pageIndex, $pageSize);
        }

        if (!empty($list)) {
            /** @var \Redis $redis */
            $redis = app('redis');
            foreach ($list as $index => &$item) {
                $item['ac_index'] = ($pageIndex * $pageSize) + $index + 1;
                if ($item['ac_surfaceImg']) {
                    $item['ac_surfaceImg_url'] = FileHelper::getFileUrl($item['ac_surfaceImg'], 'activity_images', '', 480);
                } else {
                    $item['ac_surfaceImg_url'] = '';
                }
                if (empty($item['ac_category'])) {
                    $item['ac_category_name'] = '';
                } elseif ($item['ac_category'] == 1) {
                    $item['ac_category_name'] = '毛笔';
                } elseif ($item['ac_category'] == 2) {
                    $item['ac_category_name'] = '硬笔';
                }
                //是否已投票
                $item['ac_is_voted'] = self::getIsVoted($openId, $item['ac_id'], $redis);
            }
        }

        return [$list, $count];
    }

    private function queryData($conditions, $orderBy, $pageIndex, $pageSize)
    {
        if (intval($pageSize) > 100) {
            throw new ParamsInvalidException('pageSize过大');
        }

        $listSql = 'SELECT * FROM `activity_contestant` WHERE ac_auditStatus=1';
        $countSql = 'SELECT COUNT(*) FROM `activity_contestant` WHERE ac_auditStatus=1';
        $whereSql = '';
        $orderBySql = '';
        $params = [];

        if (isset($conditions['name'])) {
            $whereSql .= ' AND ac_name LIKE :name';
            $params[':name'] = '%' . $conditions['name'] . '%';
        }
        if (isset($conditions['id'])) {
            $whereSql .= ' AND ac_id = :id';
            $params[':id'] = $conditions['id'];
        }

        if ($orderBy == 'ac_voteNumber') {
            $orderBySql = ' ORDER BY ac_voteNumber DESC';
        } elseif ($orderBy == 'ac_createTime') {
            $orderBySql = ' ORDER BY ac_createTime DESC';
        }

        $listSql = $listSql . $whereSql . $orderBySql . ' limit ' . $pageIndex * $pageSize . ',' . $pageSize;
        $countSql = $countSql . $whereSql;

        $list = app('mysqlbxd_app')->select($listSql, $params);
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $params);

        return [$list, $count];
    }

    private function updateTotalView()
    {
        $sql = 'UPDATE `activity_info` SET ai_viewTotal = ai_viewTotal +1 WHERE	ai_id=' . self::ACTIVITY_ID;
        app('mysqlbxd_app')->query($sql);
    }

    /**
     * 获取参赛者总数量
     * @return mixed
     */
    private function getContestantTotal()
    {
        $sql = 'SELECT COUNT(*) FROM `activity_contestant` WHERE ac_auditStatus=1';
        return app('mysqlbxd_app')->fetchColumn($sql);
    }

    /**
     * 获取投票总数量
     * @return mixed
     */
    private function getVoteTotal()
    {
        $sql = 'SELECT COUNT(*) FROM `activity_vote_record`';
        return app('mysqlbxd_app')->fetchColumn($sql);
    }

    /**
     * 获取查看总数量
     * @return mixed
     */
    private function getViewTotal()
    {
        $sql = 'SELECT `ai_viewTotal` FROM `activity_info` WHERE ai_id=' . self::ACTIVITY_ID;
        return app('mysqlbxd_app')->fetchColumn($sql);
    }

    private function getContestantById($id)
    {
        $sql = 'SELECT * FROM activity_contestant WHERE ac_auditStatus=1 AND ac_id=:id';
        return app('mysqlbxd_app')->fetch($sql, [':id' => $id]);
    }

    private function getContestantImages($id)
    {
        $sql = 'SELECT * FROM activity_contestant_image WHERE ac_id=:id ORDER BY aci_sort';
        return app('mysqlbxd_app')->select($sql, [':id' => $id]);
    }

    private function addVote($id, $openId)
    {
        $date = date('Y-m-d');
        $sql = 'INSERT INTO `activity_vote_record`(`ac_id`,`avr_openid`,`avr_createDate`) VALUES(:ac_id,:avr_openid,:avr_createDate);UPDATE	`activity_contestant` SET ac_voteNumber = ac_voteNumber + 1	WHERE `ac_id`=:ac_id ';
        $params = [
            ':ac_id' => $id,
            ':avr_openid' => $openId,
            ':avr_createDate' => $date
        ];
        app('mysqlbxd_app')->query($sql, $params);
    }

    private function insertContestant($data)
    {
        return app('mysqlbxd_app')->insert('activity_contestant', $data);
    }

    private function insertContestantImage($data)
    {
        return app('mysqlbxd_app')->insert('activity_contestant_image', $data);
    }

    private function getUserVoteContestantKey($openId, $contestantId)
    {
        $currentDate = date('Ymd');
        //当天是否已超过给某个参赛者最大投票数,key:用户openid_当前日期_参赛选手号,value:票数
        return self::CACHE_PREFIX . $openId . '_' . $currentDate . '_' . $contestantId;
    }

    private function getUserVoteKey($openId)
    {
        $currentDate = date('Ymd');
        //当天是否已超过最大投票数,key:用户openid_当前日期,value:票数
        return self::CACHE_PREFIX . $openId . '_' . $currentDate;
    }

    private function getIsVoted($openId, $contestantId, $redis = null)
    {
        if (!$redis) {
            /** @var \Redis $redis */
            $redis = app('redis');
        }
        $userVoteContestantKey = self::getUserVoteContestantKey($openId, $contestantId);

        return $redis->exists($userVoteContestantKey) ? 1 : 0;
    }

    public function save618Join()
    {
        $type = (string)app()->request->params('type', '1');
        $activity_618_user_count = 1;

        /** @var \Redis $redis */
        $redis = app('redis');
        if ($redis->get('activity_618_user_count')) {
            $currentCount = $redis->get('activity_618_user_count');
            $activity_618_user_count = $currentCount + 1;
        }
        $redis->set('activity_618_user_count', $activity_618_user_count);

        //图片路径
        $base_url_res = conf('app.CDN.BASE_URL_RES');
        $base_url = conf('app.request_url_schema_x_forwarded_proto_default');
        $imagePath = $base_url . ':' . $base_url_res . "/html/618H5/png/{$type}.jpg";
        //$imagePath = 'http://app-loc.16988.cn/1.png';
        $img = imagecreatefromjpeg($imagePath);
        //字体颜色
        $fontColor = imagecolorallocate($img, 246, 58, 66);
        //字符文件路径
        $fontFile = __DIR__ . "/../../Data/msyh.ttf";
        //将文字写在相应的位置
        imagettftext($img, 22, 0, 435, 88, $fontColor, $fontFile, $activity_618_user_count);
        imagettftext($img, 18, 0, 435 + strlen($activity_618_user_count) * 20, 86, $fontColor, $fontFile, '位');
        ob_start(); // Let's start output buffering.
        imagejpeg($img); //This will normally output the image, but because of ob_start(), it won't.
        $contents = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        $base64Image = "data:image/jpeg;base64," . base64_encode($contents);

        //销毁图像
        imagedestroy($img);
        $this->responseJSON(['userCount' => $activity_618_user_count, 'base64Image' => $base64Image]);
    }

    /**
     * 展会专题页
     */
    public function exhiBition()
    {
        app()->redirect("https://app.16988.cn/html/apph5/exhiBition.html#/index?id=1");
    }


    /**
     * 首页
     */
    public function jiazhuang20180801()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            app()->redirect('/html/apph5/myShop.html#/homeFurnish');
        }
    }

    /**
     * 家装专题商品列表
     * @throws ParamsInvalidException
     */
    public function jiazhuangGoods20180801()
    {
        $scene = $payChannel = app()->request()->params('scene', 0);
        $categoty = $payChannel = app()->request()->params('category', 0);
        $scene = intval($scene);
        $categoty = intval($categoty);
        //1客厅,2卧室,3书房,4餐厅,5玄关,6盥(guan)洗室
        $scenes = [1, 2, 3, 4, 5, 6];
        //1国画,2油画
        $categotys = [1, 2];
        if ($scene && !in_array($scene, $scenes)) {
            throw new ParamsInvalidException('场景参数错误');
        }
        if ($categoty && !in_array($categoty, $categotys)) {
            throw new ParamsInvalidException('类别参数错误');
        }
        $list = [
            //国画
            [1790, 1, 1],
            [5574, 1, 1],
            [5992, 1, 1],
            [5983, 1, 1],
            [5976, 1, 1],
            [5979, 1, 1],
            [5701, 1, 1],
            [5685, 1, 1],
            [5959, 1, 1],
            [5876, 1, 1],
            [5957, 1, 1],
            [5955, 1, 1],
            [5962, 1, 1],
            [5968, 1, 1],
            [5965, 1, 1],//客厅
            [4580, 2, 1],
            [5498, 2, 1],
            [5204, 2, 1],
            [5110, 2, 1],
            [4716, 2, 1],
            [4963, 2, 1],
            [4591, 2, 1],
            [5964, 2, 1],//卧室
            [5469, 3, 1],
            [4963, 3, 1],
            [4586, 3, 1],
            [4563, 3, 1],//书房
            [4543, 4, 1],
            [4551, 4, 1],
            [4905, 4, 1],
            [4569, 4, 1],
            [5779, 4, 1],
            [5850, 4, 1],
            [5851, 4, 1],//餐厅
            [4591, 5, 1],
            [4571, 5, 1],
            [5691, 5, 1],
            [5621, 5, 1],//玄关
            [4147, 6, 1],
            [4083, 6, 1],
            [1300, 6, 1],
            [4072, 6, 1],
            [5953, 6, 1],
            [5970, 6, 1],//盥(guan)洗室

            //油画
            [2363, 1, 2],
            [2515, 1, 2],
            [5223, 1, 2],
            [2877, 1, 2],
            [5398, 1, 2],
            [3012, 1, 2],
            [2795, 1, 2],
            [814, 1, 2],//客厅
            [2900, 2, 2],
            [2474, 2, 2],
            [768, 2, 2],
            [2366, 2, 2],
            [2364, 2, 2],
            [2365, 2, 2],
            [2482, 2, 2],
            [2514, 2, 2],//卧室
            [2485, 3, 2],
            [2472, 3, 2],
            [2473, 3, 2],
            [2358, 3, 2],
            [2357, 3, 2],
            [2359, 3, 2],
            [2878, 3, 2],
            [2996, 3, 2],
            [814, 3, 2],//书房
            [2361, 4, 2],
            [2516, 4, 2],
            [2481, 4, 2],
            [2484, 4, 2],
            [5984, 4, 2],
            [6011, 4, 2],
            [2899, 4, 2],
            [2483, 4, 2],//餐厅
            [2897, 5, 2],
            [5428, 5, 2],
            [2370, 5, 2],
            [2823, 5, 2],
            [5429, 5, 2],
            [5486, 5, 2],
            [2938, 5, 2],
            [2353, 5, 2],//玄关
            [2941, 6, 2],
            [5801, 6, 2],
            [5802, 6, 2],
            [5818, 6, 2],
            [5799, 6, 2],
            [5807, 6, 2],
            [5800, 6, 2],
            [5798, 6, 2],
            [6010, 6, 2],//盥(guan)洗室
        ];
        if (app()->getMode() == 'development') {
            $list = [
                [10087645, 1, 1],
                [10087643, 2, 1],
                [10087642, 3, 1],
                [10087640, 4, 1],
                [10087639, 5, 1],
                [10087638, 6, 1],
                [10087633, 1, 2],
                [10087630, 2, 2],
                [10087629, 3, 2],
                [10087628, 4, 2],
                [10087625, 5, 2],
                [10087624, 6, 2],
                [10087637, 1, 1],
                [10087582, 2, 1],
            ];
        }
        $g_ids = [];
        foreach ($list as $row) {
            if ($scene && $categoty) {
                if ($scene == $row[1] && $categoty == $row[2]) {
                    $g_ids[] = $row[0];
                }
            } else {
                if ($scene) {
                    if ($scene == $row[1]) {
                        $g_ids[] = $row[0];
                    }
                } else {
                    if ($categoty) {
                        if ($categoty == $row[2]) {
                            $g_ids[] = $row[0];
                        }
                    } else {
                        $g_ids[] = $row[0];
                    }
                }
            }
        }
        $res = [];
        if ($g_ids) {
            $goodsLib = new \Lib\Mall\Goods();
            $res = $goodsLib->itemQuery([
                'id' => implode(',', $g_ids)
            ]);
            if ($this->uid) {
                if ($userCartGoods = app('mysqlbxd_app')->select('select g_id from  `user_cart` where u_id=' . $this->uid . ' and g_id in(' . implode(',',
                        $g_ids) . ')')
                ) {
                    $userCartGoods = array_column($userCartGoods, 'g_id');
                }
            } else {
                $userCartGoods = [];
            }
            if ($res['count']) {
                foreach ($res['list'] as &$item) {
                    $item['isInCart'] = 0;
                    if ($userCartGoods && in_array($item['g_id'], $userCartGoods)) {
                        $item['isInCart'] = 1;
                    }
                }
            }
        }
        $this->responseJSON($res);
    }

    /**
     * 教师节专题
     */
    public function teachersDay20180907()
    {
        //$openId = WxHelper::getOpenId();
        //if (!empty($openId)) {
        app()->redirect('/html/apph5/myShop.html#/teacherDay');
        //}
    }

    /**
     * 教师节专题发代金券
     */
    public function teachersDayVoucherGet20180907()
    {
        $isHave = 0;
        $vLists = app('mysqlbxd_mall_user')->select("select `v_t_id` from voucher_template where v_t_prefix='20180907te' ");
        if ($vLists) {
            $tids = array_column($vLists, 'v_t_id');
            $haveCount = app('mysqlbxd_mall_user')->fetchColumn("select count(*) c  from voucher where `v_t_id` in(" . implode(',',
                    $tids) . ") and u_id='{$this->uid}' ");
            $action = app()->request()->params('action', '');
            if ($haveCount == 0 && $action == 'get') {
                $voucherLib = new \Lib\Mall\Voucher();
                $voucherLib->receive(['uid' => $this->uid, 'tids' => implode(',', $tids)]);
                $isHave = 1;
            } else {
                if ($haveCount > 0) {
                    $isHave = 1;
                }
            }
        } else {
            throw new ServiceException("代金券未设置");
        }

        $this->responseJSON($isHave);
    }

    /**
     * 教师节专题商品列表
     * @throws ParamsInvalidException
     */
    public function teachersDayGoods20180907()
    {
        $g_ids = app('mysqlbxd_mall_user')->fetchColumn("select v_t_limit_ids from voucher_template where v_t_prefix='20180907te' limit 1");
        if (!$g_ids) {
            throw new ServiceException("商品未设置");
        }

        $list = array_filter(explode(',', $g_ids));
        $res = [];
        $goodsLib = new \Lib\Mall\Goods();
        $res = $goodsLib->itemQuery([
            'id' => implode(',', $list)
        ]);
        if ($this->uid) {
            if ($userCartGoods = app('mysqlbxd_app')->select('select g_id from  `user_cart` where u_id=' . $this->uid . ' and g_id in(' . implode(',',
                    $list) . ')')
            ) {
                $userCartGoods = array_column($userCartGoods, 'g_id');
            }
        } else {
            $userCartGoods = [];
        }
        if ($res['count']) {
            $listTmp1 = [];
            $listTmp2 = [];
            foreach ($res['list'] as &$item) {
                $item['isInCart'] = 0;
                if ($userCartGoods && in_array($item['g_id'], $userCartGoods)) {
                    $item['isInCart'] = 1;
                }
                $listTmp1[$item['g_id']] = $item;
            }
            $listTmp1 = array_column($res['list'], null, 'g_id');
            foreach ($list as $g_id) {
                if (isset($listTmp1[$g_id])) {
                    $listTmp2[] = $listTmp1[$g_id];
                }
            }
            $res['list'] = $listTmp2;
        }
        $this->responseJSON($res);
    }


    /**
     * 2018国庆节微信首页
     */
    public function nationalDayWx201810()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            //增加活动访问量
            $this->updateTotalView();
            app()->redirect('/html/apph5/midAutumn.html');
        }
    }

    /**
     * 国庆节专题商品列表
     * @throws ParamsInvalidException
     */
    public function nationalDayGoods201810()
    {
        $goods = [
            'secKill' => [],
            'gift' => [
                'friends' => [],
                'spouse' => [],
                'family' => []
            ]
        ];
        $secKillDaysStart = '2018-09-20';
        $secKillDaysEnd = '2018-09-25';
        //初始化数据
        $nextDay = $secKillDaysStart;
        $goods['secKill'][$secKillDaysStart] = [];
        do {
            $nextDay = date('Y-m-d', strtotime('+1 days', strtotime($nextDay)));
            $goods['secKill'][$nextDay] = [];
        } while ($nextDay < $secKillDaysEnd);

        //根据商品id查询商品
        $gidsGiftArr = $this->nationalDayGoodsIdsGift201810();
        $gidsSecKill = $this->nationalDayGoodsIdsSecKill201810($secKillDaysStart, $secKillDaysEnd);
        $allIds = array_merge($gidsSecKill, $gidsGiftArr['friends'], $gidsGiftArr['spouse'], $gidsGiftArr['family']);
        if ($allIds) {
            $userCartGoods = [];
            if ($this->uid && $userCartGoods = app('mysqlbxd_app')->select('select g_id from  `user_cart` where u_id=' . $this->uid . ' and g_id in(' . implode(',',
                        $allIds) . ')')
            ) {
                $userCartGoods = array_column($userCartGoods, 'g_id');
            }
            $goodsLib = new \Lib\Mall\Goods();
            $allGoods = $goodsLib->itemQuery([
                'id' => implode(',', $allIds),
                'pageSize' => count($allIds),
            ]);
            //填充数据
            if ($allGoods['count']) {
                foreach ($allGoods['list'] as $item) {
                    $item['isInCart'] = 0;
                    if ($userCartGoods && in_array($item['g_id'], $userCartGoods)) {
                        $item['isInCart'] = 1;
                    }

                    if (in_array($item['g_id'], $gidsSecKill)) {
                        if (isset($item['g_secKillStart']) && $item['g_secKillStart'] && date('H:i', strtotime($item['g_secKillStart'])) == '10:00') {
                            $time = date('Y-m-d H:i:s');
                            if ($time < $item['g_secKillStart']) {//未开始
                                $item['secKillStatus'] = 0;
                                $item['remainTime'] = 0;
                            } else {
                                if ($time >= $item['g_secKillStart'] && $time <= $item['g_secKillEnd']) {//进行中
                                    $item['secKillStatus'] = 1;
                                    $item['remainTime'] = strtotime($item['g_secKillEnd']) - time();
                                } else {//已结束
                                    $item['secKillStatus'] = 2;
                                    $item['remainTime'] = 0;
                                }
                            }
                            $goods['secKill'][substr($item['g_secKillStart'], 0, 10)][] = $item;
                        }
                    } else {
                        if (in_array($item['g_id'], $gidsGiftArr['friends'])) {
                            $goods['gift']['friends'][] = $item;
                        } else {
                            if (in_array($item['g_id'], $gidsGiftArr['spouse'])) {
                                $goods['gift']['spouse'][] = $item;
                            } else {
                                $goods['gift']['family'][] = $item;
                            }
                        }
                    }
                }
            }
        }
        $this->responseJSON($goods);
    }

    public function createBoardingPassPage()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            app()->redirect(self::CREATE_BOARDING_PASS_PAGE);
        }
    }

    public function createBoardingPassImage()
    {
        $data = ['result' => false, 'message' => '', 'base64Image' => ''];
        $request = app()->request();
        $name = $request->params('name');
        $city = $request->params('city');
        $date = $request->params('date');
        $formatDate = '';

        if (empty($name)) {
            $data['message'] = '姓名不能为空';
        } elseif (mb_strlen($name) > 60) {
            $data['message'] = '姓名过长';
        }
        if (empty($city)) {
            $data['message'] = '目的地不能为空';
        } elseif (mb_strlen($name) > 60) {
            $data['message'] = '目的地过长';
        }
        if (empty($date)) {
            $data['message'] = '日期不能为空';
        } else {
            $formatDate = date('dM', strtotime($date));
            if (empty($formatDate)) {
                $data['message'] = '日期格式不正确';
            }
            $formatDate = strtoupper($formatDate);
        }

        if (!empty($data['message'])) {
            $this->responseJSON($data);
            return false;
        }

        //记录数据
        $sql = 'INSERT INTO activity_create_boarding_pass(`name`,`city`,`date`,`openid`) VALUES(:name,:city,:date,:openid)';
        $sqlParams = [':name' => $name, ':city' => $city, ':date' => $date, ':openid' => WxHelper::getOpenId()];
        app('mysqlbxd_app')->query($sql, $sqlParams);

        //登机牌模板路径
        $templateImageUrl = 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/h5/BoardingPass.jpg';
        $mainImageRes = imagecreatefromjpeg($templateImageUrl);

        //将文字写在相应的位置
        //字体颜色
        $fontColor = imagecolorallocate($mainImageRes, 2, 0, 0);
        //字符文件路径
        $fontFile = __DIR__ . "/../../Data/msyh.ttf";
        imagettftext($mainImageRes, 24, 2, 55, 220, $fontColor, $fontFile, $name);
        imagettftext($mainImageRes, 18, 2, 280, 210, $fontColor, $fontFile, $city);
        imagettftext($mainImageRes, 16, 2, 543, 200, $fontColor, $fontFile, $formatDate);

        //imagejpeg($mainImageRes, 'a.jpg');
        //exit;
        ob_start(); // Let's start output buffering.
        imagejpeg($mainImageRes, null, 90); //This will normally output the image, but because of ob_start(), it won't.
        $contents = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        $base64Image = "data:image/jpeg;base64," . base64_encode($contents);
        $data['result'] = true;
        $data['base64Image'] = $base64Image;
        //销毁图像
        imagedestroy($mainImageRes);
        $this->responseJSON($data);
    }

    public function interestingPosterPage()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            app()->redirect(self::INTERESTING_POSTER_PAGE);
        }
    }

    public function interestingPosterImage()
    {
        $data = ['result' => false, 'message' => '', 'base64Image' => ''];
        $request = app()->request();
        $name = $request->params('name');
        $openId = $request->params('openId');

        if (empty($name)) {
            $data['message'] = '姓名不能为空';
        } elseif (mb_strlen($name) > 60) {
            $data['message'] = '姓名过长';
        }
        if (empty($openId)) {
            $data['message'] = '请在微信中打开链接';
        }

        if (!empty($data['message'])) {
            $this->responseJSON($data);
            return false;
        }
        //获取当前用户最近一次生成的海报信息
        $sql = 'SELECT * FROM activity_interesting_poster_record WHERE openid=:openid ORDER BY create_at DESC LIMIT 1;';
        $posterRecordData = app('mysqlbxd_app')->fetch($sql, [':openid' => $openId]);
        $lastPosterId = 0;
        if ($posterRecordData) {
            $lastPosterId = $posterRecordData['poster_id'];
        }
        //获取海报模板
        $sql = 'SELECT * FROM activity_interesting_poster';
        $posterData = app('mysqlbxd_app')->select($sql);
        if ($posterData) {
            if ($lastPosterId && count($posterData) >= 2) {
                //去除最近一次生成的海报模板
                $posterData = array_column($posterData, null, 'id');
                unset($posterData[$lastPosterId]);
                $posterId = array_rand($posterData, 1);
                $posterItem = $posterData[$posterId];
            } else {
                $posterItem = $posterData[0];
            }
        }else {
            $data['message'] = '生成海报时发生异常，请稍后重试';
            $this->responseJSON($data);
            return false;
        }

        //模板路径
        $templateImageUrl = FileHelper::getFileUrl($posterItem['template_image']);
        list (,,$imageType) = getimagesize($templateImageUrl);
        $mainImageRes = $this->createImage($templateImageUrl, $imageType);

        //将文字写在相应的位置
        //字体颜色
        $fontColor = imagecolorallocate($mainImageRes, 255, 204, 28);
        $fontSize = 30;
        //字符文件路径
        $fontFile = __DIR__ . "/../../Data/msyh.ttf";
        $tmpArray = imagettfbbox($fontSize, 0, $fontFile, $name);
        imagettftext($mainImageRes, $fontSize, 0, (585 - (empty($tmpArray) ? 80 : $tmpArray[2])) / 2, 90, $fontColor, $fontFile, $name);

//        imagejpeg($mainImageRes, 'a.jpg');
//        exit;

        ob_start(); // Let's start output buffering.
        imagejpeg($mainImageRes, null, 80); //This will normally output the image, but because of ob_start(), it won't.
        $contents = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        $base64Image = "data:image/jpeg;base64," . base64_encode($contents);
        $data['result'] = true;
        $data['base64Image'] = $base64Image;
        //销毁图像
        imagedestroy($mainImageRes);

        //写生成海报记录数据
        $sql = 'INSERT INTO activity_interesting_poster_record(`name`, `poster_id`,`openid`) VALUES(:name, :poster_id, :openid)';
        $sqlParams = [':name' => $name, ':poster_id' => $posterItem['id'], ':openid' => $openId];
        app('mysqlbxd_app')->query($sql, $sqlParams);

        //返回数据
        $this->responseJSON($data);
    }

    /**
     * 生成趣味对联活动 2018.12
     */
    public function interestingCouplet()
    {
        $data = ['result' => false, 'message' => '', 'base64Image' => ''];
        $request = app()->request();
        $content = $request->params('content');
        //$content = '{"top":"我是横批","left":"我是上联啊","right":"我是下联文啊"}';

        if (empty($content)) {
            $data['message'] = '要生成的对联内容不能为空';
            return $this->responseJSON($data);
        }
        $contentArray = json_decode($content, true);
        $top = empty($contentArray['top']) ? '' : $contentArray['top'];
        $left = empty($contentArray['left']) ? '' : $contentArray['left'];
        $right = empty($contentArray['right']) ? '' : $contentArray['right'];
        if (empty($top)) {
            $data['message'] = '横批不能为空';
            return $this->responseJSON($data);
        }
        if (empty($left)) {
            $data['message'] = '上联不能为空';
            return $this->responseJSON($data);
        }
        if (empty($right)) {
            $data['message'] = '下联不能为空';
            return $this->responseJSON($data);
        }
        if (mb_strlen($top) > 5) {
            $data['message'] = '横批不能超过5个字符';
            return $this->responseJSON($data);
        }
        if (mb_strlen($left) < 5 || mb_strlen($left) > 9) {
            $data['message'] = '上联应该为5到9个字符';
            return $this->responseJSON($data);
        }
        if (mb_strlen($right) < 5 || mb_strlen($right) > 9) {
            $data['message'] = '下联应该为5到9个字符';
            return $this->responseJSON($data);
        }

        //模板路径 模板750*1130
        $templateImageUrl = app()->baseDir.'/Data/Cache/coupletTemplate.jpg';
        if(!file_exists($templateImageUrl)){
            file_put_contents($templateImageUrl,file_get_contents('https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/h5/coupletTemplate.jpg'));
        }
        list (, , $imageType) = getimagesize($templateImageUrl);
        $mainImageRes = $this->createImage($templateImageUrl, $imageType);

        //将文字写在相应的位置
        //字体颜色
        $fontColor = imagecolorallocate($mainImageRes, 0, 0, 0);
        $fontSize = 33;
        //字符文件路径
        $fontFile = app()->baseDir."/Data/hwxk.ttf";
        $tmpArray = imagettfbbox($fontSize, 0, $fontFile, $top);
        imagettftext($mainImageRes, $fontSize, 0, (750 - $tmpArray[2]) / 2, 172, $fontColor, $fontFile, $top);

        $this->drawVerticalText($mainImageRes, $left, 78, 280, $fontColor, $fontFile, $fontSize);
        $this->drawVerticalText($mainImageRes, $right, 626, 280, $fontColor, $fontFile, $fontSize);

//        imagejpeg($mainImageRes, 'a.jpg');
//        exit;

        ob_start(); // Let's start output buffering.
        imagejpeg($mainImageRes, null, 90); //This will normally output the image, but because of ob_start(), it won't.
//        header('content-type:image/jpg');
//        echo $mainImageRes;exit;
        $contents = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        $base64Image = "data:image/jpeg;base64," . base64_encode($contents);
        $data['result'] = true;
        $data['base64Image'] = $base64Image;
        //销毁图像
        imagedestroy($mainImageRes);

        //写生成海报记录数据
        $sql = 'INSERT INTO activity_interesting_couplet(`content`) VALUES(:content)';
        $sqlParams = [':content' => $content];
        app('mysqlbxd_app')->query($sql, $sqlParams);

        //返回数据
        return $this->responseJSON($data);
    }

    //书法打卡活动-模板图片数据
    private $calligraphyTemplateImages = [
        't_1' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t1.png',
        't_2' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t2.png',
        't_3' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t3.png',
        't_4' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t4.png',
        't_5' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t5.png',
        't_6' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t6.png',
        't_7' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t7.png',
        't_8' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t8.png',
        't_9' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t9.png',
        't_10' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t10.png',
        't_11' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t11.png',
        't_12' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t12.png',
        't_13' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t13.png',
        't_14' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t14.png',
        't_15' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t15.png',
        't_16' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t16.png',
        't_17' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t17.png',
        't_18' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t18.png',
        't_19' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t19.png',
        't_20' => 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/activity_calligraphy_t20.png'
    ];

    /**
     * 书法打卡活动页面
     */
    public function calligraphyPage()
    {
        $openId = WxHelper::getOpenId(2);
        if (!empty($openId)) {
            app()->redirect(self::CALLIGRAPHY_PAGE);
        }
        //$openId = 'oxmlr1QhOwyfJBZU-QlFXu2r6YQU';
        //将用户信息存入数据库中
        $userRes = WxHelper::getUserInfo(WxHelper::getAccessToken(), $openId);
        if ($userRes && isset($userRes['openid'])) {
            $data['uo_subscribe'] = $userRes['subscribe'];
            $data['uo_openId'] = $openId;
            $data['uo_nickname'] = isset($userRes['nickname']) ? $userRes['nickname'] : '';
            $data['uo_sex'] = isset($userRes['sex']) ? $userRes['sex'] : '';
            $data['uo_headimgurl'] = isset($userRes['headimgurl']) ? $userRes['headimgurl'] : '';
            $data['uo_subscribe_time'] = isset($userRes['subscribe_time']) ? date('Y-m-d H:i:s', $userRes['subscribe_time']) : '';

            $db = app('mysqlbxd_mall_user');
            $item = $db->fetch('select * from `user_openId` where `uo_openId`=:uo_openId', [
                'uo_openId' => $openId,
            ]);
            if ($item) {
                $db->update('user_openId', $data, ['uo_id' => $data['uo_id']]);
            } else {
                $db->insert('user_openId', $data);
            }
        }
    }

    /**
     * 获取书法打卡情况
     */
    public function getCalligraphyList()
    {
        $data = [
            'result' => false,
            'message' => '',
            'data' => ['beginTime' => self::CALLIGRAPHY_BEGIN_TIME, 'endTime' => self::CALLIGRAPHY_END_TIME, 'totalCount' => 0, 'list' => []]
        ];
        $openId = app()->request()->params('openid');
        if(empty($openId)) {
            $openId = WxHelper::getOpenId(2);
        }
        $sql = 'SELECT * FROM `activity_calligraphy` WHERE `ac_status`=1 AND `ac_openid`=:openid AND ac_create_at BETWEEN :beginTime AND :endTime';
        $list = app('mysqlbxd_app')->select($sql,
            [':openid' => $openId, ':beginTime' => self::CALLIGRAPHY_BEGIN_TIME, ':endTime' => self::CALLIGRAPHY_END_TIME]);
        if ($list) {
            foreach ($list as &$item) {
                $item['ac_image_url'] = FileHelper::getFileUrl($item['ac_image']);
                unset($item['ac_openid']);
            }
            $data['data']['totalCount'] = count($list);
            $data['data']['list'] = $list;
        }
        $data['result'] = true;
        return $this->responseJSON($data);
    }

    /**
     * 书法打卡预览
     */
    public function getCalligraphyPreview()
    {
        $data = ['result' => false, 'message' => '', 'data' => []];
        $openId = app()->request()->params('openid');
        if(empty($openId)) {
            $openId = WxHelper::getOpenId(2);
        }
        $currentDate = date('Y-m-d');
        $request = app()->request();
        $imagePath = $request->params('imagePath');
        if (empty($imagePath)) {
            $data['message'] = '请先上传图片后进行预览';
            return $this->responseJSON($data);
        }

        $sql = 'SELECT COUNT(*) FROM `activity_calligraphy` WHERE `ac_status`=1 AND `ac_openid`=:openid AND ac_create_at BETWEEN :beginTime AND :endTime';
        $totalCount = (int)app('mysqlbxd_app')->fetchColumn($sql, [':openid' => $openId, ':beginTime' => self::CALLIGRAPHY_BEGIN_TIME, ':endTime' => self::CALLIGRAPHY_END_TIME]);
        //用户打卡次数
        $data['data']['totalCount'] = $totalCount;
        //打卡日期
        $data['data']['date'] = $currentDate;
        //打卡图片路径
        $data['data']['imageUrl'] = FileHelper::getFileUrl($imagePath);
        //模板
        $templateImageCode = array_rand($this->calligraphyTemplateImages, 1);
        $data['data']['templateImageCode'] = $templateImageCode;
        $data['data']['templateImageUrl'] = $this->calligraphyTemplateImages[$templateImageCode];
        //打卡人微信头像昵称
        $item = app('mysqlbxd_mall_user')->fetch('select * from `user_openId` where `uo_openId`=:uo_openId', [
            'uo_openId' => $openId,
        ]);
        if ($item) {
            $userInfo = [
                'nickname' => $item['uo_nickname'],
                'headimgurl' => $item['uo_headimgurl']
            ];
        } else {
            $userRes = WxHelper::getUserInfo(WxHelper::getAccessToken(), $openId);
            if ($userRes && isset($userRes['openid'])) {
                $userInfo = [
                    'nickname' => $userRes['nickname'],
                    'headimgurl' => $userRes['headimgurl']
                ];
            }
        }
        if (!empty($userInfo)) {
            $data['data']['userInfo'] = $userInfo;
        } else {
            $data['data']['userInfo'] = [];
        }
        $data['result'] = true;
        return $this->responseJSON($data);
    }

    /*
     * 书法打卡保存
     */
    public function addCalligraphy()
    {
        $data = ['result' => false, 'message' => ''];
        $request = app()->request();
        $imagePath = $request->params('imagePath');
        $templateImageCode = $request->params('templateImageCode');
        if (empty($imagePath)) {
            $data['message'] = '请先上传图片后进行打卡';
            return $this->responseJSON($data);
        }
        $currentDate = date('Y-m-d');
        $openId = WxHelper::getOpenId(2);

        $sql = 'SELECT * FROM `activity_calligraphy` WHERE `ac_status`=1 AND `ac_openid`=:openid AND `ac_createDate`=:createDate;';
        $currentData = app('mysqlbxd_app')->fetch($sql, [':openid' => $openId, ':createDate' => $currentDate]);
        if ($currentData) {
            $data['message'] = '你今天已经打过卡了，明天再来吧';
            return $this->responseJSON($data);
        }
        //模板url
        if (empty($templateImageCode) || !isset($this->calligraphyTemplateImages[$templateImageCode])) {
            $templateImageCode = array_rand($this->calligraphyTemplateImages, 1);
            $templateImageUrl = $this->calligraphyTemplateImages[$templateImageCode];
        } else {
            $templateImageUrl = $this->calligraphyTemplateImages[$templateImageCode];
        }
        //$sql = 'INSERT INTO `activity_calligraphy`(`ac_openid`,`ac_image`,`ac_template_image_url`,`ac_createDate`) VALUES(:openid,:image,:templateImageUrl,:createDate);';
        list(, $id) = app('mysqlbxd_app')->insert('activity_calligraphy',
            ['ac_openid' => $openId, 'ac_image' => $imagePath, 'ac_template_image_url' => $templateImageUrl, 'ac_createDate' => $currentDate]);

        $data['result'] = true;
        $data['data']['id'] = $id;
        return $this->responseJSON($data);
    }

    /**
     * 书法打卡单次详情
     */
    public function getCalligraphyDetail()
    {
        $data = ['result' => false, 'message' => '', 'data' => []];
        $request = app()->request();
        $id = $request->params('id');
        if (empty($id)) {
            $data['message'] = '参数不正确';
            return $this->responseJSON($data);
        }
        $sql = 'SELECT * FROM `activity_calligraphy` WHERE `id`=:id;';
        $calligraphyData = app('mysqlbxd_app')->fetch($sql, [':id' => $id]);
        if ($calligraphyData) {
            $imageUrl = FileHelper::getFileUrl($calligraphyData['ac_image']);
            $templateImageUrl = $calligraphyData['ac_template_image_url'];
            $openId = $calligraphyData['ac_openid'];
            $date = $calligraphyData['ac_createDate'];
        } else {
            $data['message'] = '打卡信息不存在';
            return $this->responseJSON($data);
        }
        //打卡人微信头像昵称
        $item = app('mysqlbxd_mall_user')->fetch('select * from `user_openId` where `uo_openId`=:uo_openId', [
            'uo_openId' => $openId,
        ]);
        if ($item) {
            $userInfo = [
                'nickname' => $item['uo_nickname'],
                'headimgurl' => $item['uo_headimgurl']
            ];
        } else {
            $userInfo = [];
        }
        $sql = 'SELECT COUNT(*) FROM `activity_calligraphy` WHERE `ac_status`=1 AND `ac_openid`=:openid AND ac_create_at BETWEEN :beginTime AND :endTime AND ac_createDate<=:date';
        $totalCount = (int)app('mysqlbxd_app')->fetchColumn($sql, [
            ':openid' => $openId,
            ':beginTime' => self::CALLIGRAPHY_BEGIN_TIME,
            ':endTime' => self::CALLIGRAPHY_END_TIME,
            ':date' => $date
        ]);

        $data['result'] = true;
        $data['data'] = [
            'userInfo' => $userInfo,
            'totalCount' => $totalCount,
            'date' => $date,
            'imageUrl' => empty($imageUrl) ? '' : $imageUrl,
            'templateImageUrl' => empty($templateImageUrl) ? '' : $templateImageUrl
        ];
        return $this->responseJSON($data);
    }

    /**
     * 修改书法打卡状态
     */
    public function updateCalligraphyStatus()
    {
        $data = ['result' => false, 'message' => '', 'data' => []];
        $request = app()->request();
        $id = $request->params('id');
        if (empty($id)) {
            $data['message'] = '参数不正确';
            return $this->responseJSON($data);
        }

        $sql = 'UPDATE `activity_calligraphy` SET `ac_status`=1 WHERE `id`=:id;';
        app('mysqlbxd_app')->query($sql, [':id' => $id]);

        $data['result'] = true;
        return $this->responseJSON($data);
    }

    private function drawVerticalText($mainImageRes, $content, $x, $y, $fontColor, $fontFile, $fontSize)
    {
        $contentArray = preg_split('/(?<!^)(?!$)/u', $content);
        $charCount = count($contentArray);
        foreach ($contentArray as $char) {
            imagettftext($mainImageRes, $fontSize, 0, $x, $y, $fontColor, $fontFile, $char);
            switch ($charCount) {
                case 5:
                    $y += 110;
                    break;
                case 6:
                    $y += 95;
                    break;
                case 7:
                    $y += 80;
                    break;
                case 8:
                    $y += 70;
                    break;
                case 9:
                    $y += 60;
                    break;
                default:
                    $y += 90;
                    break;
            }
        }
    }

    private function nationalDayGoodsIdsSecKill201810($secKillDaysStart,$secKillDaysEnd)
    {
        $secKillGoodsIdsList=app('mysqlbxd_mall_user')->select("select g_id from goods where  
g_secKillStart >= '{$secKillDaysStart}' and g_secKillEnd<='{$secKillDaysEnd}' and g_type=1 and g_status=3 and is_own_shop=1");
        $secKillGoodsIds=$secKillGoodsIdsList?array_column($secKillGoodsIdsList,'g_id'):[];
        return $secKillGoodsIds;
    }
    private function nationalDayGoodsIdsGift201810()
    {
        $goodsIdKeys=['nationalDayGoods201810_friends','nationalDayGoods201810_spouse','nationalDayGoods201810_family'];
        $goodsIdKeysIdsArr=[];
        $giftGoodsIdsList=app('mysqlbxd_mall_common')->select("select skey,svalue from setting where  skey in('".implode("','",$goodsIdKeys)."')");
        $giftGoodsIdsMap=$giftGoodsIdsList?array_column($giftGoodsIdsList,null,'skey'):[];
        foreach ($goodsIdKeys as $goodsIdKey){
            if(isset($giftGoodsIdsMap[$goodsIdKey]['svalue']) && $giftGoodsIdsMap[$goodsIdKey]['svalue']){
                $goodsIdKeysIdsArr[substr($goodsIdKey,23)]=explode(',',$giftGoodsIdsMap[$goodsIdKey]['svalue']);
            }else{
                $goodsIdKeysIdsArr[substr($goodsIdKey,23)]=[];
            }
        }
        return $goodsIdKeysIdsArr;
    }

    private function createImage($url, $imageType)
    {
        $img = null;
        switch ($imageType) {
            //png
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($url);
                break;
            //jpg
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($url);
                break;
            //gif
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($url);
                break;
        }

        return $img;
    }
}
