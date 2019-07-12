<?php

/**
 * 晒宝
 * @author Administrator
 *

 */

namespace Controller\Treasure;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;
use Lib\Common\Region;
use Lib\Common\QueueManager;
use Model\Common\searchWord;


class Treasure extends BaseController
{

    private $TreasureModel = null;
    private $treasureImagesFtpConfigKey = 'treasure';

    public function __construct()
    {
        parent::__construct();
        $this->TreasureModel = new \Model\Treasure\Treasure();
    }

    public function uploadTreasureImages()
    {
        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 40 * 1024 * 1024;
        $ftpConfigKey = 'treasure';
        $filesData = FileHelper::uploadFiles($ftpConfigKey, $size, $types);
        if ($filesData) {
            if (empty($filesData['result'])) {
                $this->responseJSON(empty($filesData['data']) ? [] : $filesData['data'], 1, 1,
                    empty($filesData['message']) ? '' : $filesData['message']);
            } else {
                $this->responseJSON($filesData['data']);
            }
        } else {
            $this->responseJSON([], 1, 1, '上传文件时发生异常');
        }
    }

    /**
     * 新增晒宝
     *
     * @throws ModelException
     */
    public function add()
    {
        $u_id = $this->uid;
        $this->checkUserStatus($u_id);
        $t_title = app()->request()->params('t_title');
        isset($t_title) ? $t_title : "";
        $t_desc = $this->handleData(app()->request()->params('t_desc'), 'str');
        $t_pictures = app()->request()->params('t_pictures');
        $t_category = app()->request()->params('t_category');
        $t_type = app()->request()->params('t_type', 1);
        $t_business_id = app()->request()->params('t_business_id', '');
        isset($t_category) ? $t_category : "";
        $t_provinceCode = app()->request()->params('t_provinceCode', 0);
        $t_cityCode = app()->request()->params('t_cityCode', 0);
        $t_areaCode = app()->request()->params('t_areaCode', 0);
        $ti_pictures = json_decode($t_pictures, true);

        if (empty($t_desc) && empty($ti_pictures)) {
            throw new ParamsInvalidException("请正确填写信息");
        }
        $check_content = filter_words(cutstr_html($t_desc));
        if ($check_content) {
            throw new ParamsInvalidException("内容包含敏感词");
        }
        $this->checkUser($this->uid);

        $treasureImgModel = new \Model\Treasure\TreasureImage();
        $id = $this->TreasureModel->add($u_id, $t_title, $t_desc, $t_type, $t_business_id, $t_provinceCode, $t_cityCode, $t_areaCode);
        if ($id) {
            //赠送积分
            (new \Lib\User\UserIntegral())->addIntegral($u_id,\Lib\User\UserIntegral::ACTIVITY_TREASURE_ADD);
        }
        if (is_array($ti_pictures)) {
            foreach ($ti_pictures as $k => $v) {
                $treasureImgModel->add($id, $v['filePath'], $k, $v['width'], $v['height']);
            }
        }
        //圈子话题
        $topics = app()->request()->params('topics', '');
        $topicModel=new \Model\Treasure\TreasureTopic();
        if(!$topics){
            $topics=$topicModel->getRequiredTopicIds();
        }else{
            $topics=array_filter(array_unique(explode(',',$topics)));
            //校验数据
            $topicModel->isTopicNos($topics);
        }
        if($topics){
            $topicModel->updateTreasureTopicRef($topics,$id);
        }

        $this->responseJSON('');
    }

    /**
     * 晒宝列表
     *
     * @throws ModelException
     */
    public function lists()
    {
        $param = array(
            't_status' => 0
        );
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (!$page || !$pageSize) {
            throw new ParamsInvalidException('参数错误');
        }

        $t_desc = app()->request()->params('t_desc');
        if ($t_desc) {
            $param['t_desc'] = $t_desc;
            //模糊词收录
            searchWord::keywordsCollect($param['t_desc']);
        }

        $type = app()->request()->params('type');
        if ($type && !in_array($type, ['1', '2', '3', '4'])) { //1自己 2关注 3好友 4所有用户
            throw new ParamsInvalidException('参数错误');
        }

        $uid = app()->request()->params('uid');
        if ($type == 1) {
            $param['u_id'] = $uid ? $uid : $this->uid;
        } else {
            if (in_array($type, ['2', '3'])) {
                if (empty($this->uid)) {
                    throw new ParamsInvalidException('未登录');
                }
                $friendsCtl = new \Controller\User\Friends();
                $userLists = $friendsCtl->getRelateList($type);
                if (isset($userLists['list']) && $userLists['list']) {
                    $param['u_id'] = $userLists['list'];
                } else {
                    $param['u_id'] = 0;
                }
            }
        }
        $topic = app()->request()->params('topic');
        //latest最新的，morelike点赞多的
        $sort = app()->request()->params('sort');
        if($sort && in_array($sort,['latest','morelike'])){
            $param['sort']=$sort;
        }
        $topicModel=new \Model\Treasure\TreasureTopic();
        if($topic && ($topic=explode(',',$topic))){
            try{
                $topicModel->isTopicNos($topic);
                $param['topic']=$topic;
            }catch (\Exception $e){

            }
        }
        $rest = $this->TreasureModel->lists($param, $page, $pageSize);
        $res = $rest[0];

        if (is_array($res) && !empty($res)) {
            //在圈子第三个位置插入一个广告圈子
            $result_row = $this->TreasureModel->getTreasureAdvertise();
            if (isset($result_row['t_id']) && !empty($result_row)) {
                $new_result_row[] = $result_row;
                array_splice($res, 2, 0, $new_result_row);
                unset($result_row, $new_result_row);
            }

            $commentModel = new \Model\Treasure\TreasureComment();
            $likeLogModel = new \Model\Treasure\TreasureLikeLog();
            $treasureImgModel = new \Model\Treasure\TreasureImage();
            $certificationModel = new \Model\User\Certification();
            $userLib = new \Lib\User\User();
            $favModel = new \Model\User\Favorite();
            $friendsModel = new \Model\Friends\Friends();
            foreach ($res as &$value) {
                $value['is_like'] = $likeLogModel->treasureIsLikeLogInfo($this->uid, $value['t_id']);
                $value['is_comment'] = $commentModel->isComment($this->uid, $value['t_id']);
                $value['is_oneself'] = $this->uid == $value['u_id'] ? 1 : 0;
                $value['t_provinceName'] = Region::getRegionNameByCode($value['t_provinceCode']);
                $value['t_cityName'] = Region::getRegionNameByCode($value['t_cityCode']);
                $value['t_areaName'] = Region::getRegionNameByCode($value['t_areaCode']);
                list ($pic,) = $treasureImgModel->lists(['t_id' => $value['t_id']], 1, 10);
                $value['t_pictures'] = $pic;
                $time = strtotime($value['t_createDate']);
                $value['displayTime'] = date_format_to_display($time);

                // 收藏信息
                $favInfo = $favModel->oneByUfavObjectId($this->uid, $value['t_id']);
                $value['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
                $value['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';

                //点赞用户
                list ($likeLogs,) = $likeLogModel->lists(array('t_id' => $value['t_id']));
                $value['likeLogInfos'] = $likeLogs;
                $userLib->extendUserInfos2Array($value['likeLogInfos'], 'u_id', array('u_nickname' => 'tc_nickname', 'u_realname' => 't_realname',));

                //评论用户
                list ($comments,) = $commentModel->lists(array('t_id' => $value['t_id']));
                $commentUser = array();
                foreach ($comments as $v) {
                    $commentUser[$v['tc_id']] = $v['u_id'];
                }
                foreach ($comments as &$vu) {
                    $vu['p_u_id'] = $vu['tc_pid'] && isset($commentUser[$vu['tc_pid']]) ? $commentUser[$v['tc_id']] : '';
                }

                $userLib->extendUserInfos2Array($comments, 'p_u_id', array(
                    'u_nickname' => 'p_u_nickname',
                    'u_realname' => 'p_u_realname',
                ));
                $value['commentInfos'] = $comments;
                unset($likeLogs, $comments);
                // 是否认证
                $value['certification'] = $certificationModel->getType($value['u_id']);
                //头条类型圈子处理
                if ($value['t_type'] == 2 && !empty($value['t_business_id'])) {
                    $this->setShareData($value);
                }
                //定制类型圈子处理
                if ($value['t_type'] == 3 && !empty($value['t_business_id'])) {
                    $this->applyCustomizeInfo($value);
                }
                $userLib->extendUserInfos2Array($value['commentInfos'], 'u_id', array('u_nickname' => 'tc_nickname', 'u_realname' => 't_realname',));
                if (!empty($value['commentInfos'])) {
                    foreach ($value['commentInfos'] as &$value2) {
                        $time2 = strtotime($value2['tc_time']);
                        $value2['displayTime'] = date_format_to_display($time2);
                    }
                }
                //关联的话题
                $value['topics']=$topicModel->getTreasureRefTopics($value['t_id']);
                //是否关注
                $value['is_friends'] = $friendsModel->relation($this->uid, $value['u_id']);
            }
            $userLib->extendUserInfos2Array($res, 'u_id', array(
                'u_nickname' => 't_nickname',
                'u_avatar' => 't_avatar',
                'u_realname' => 't_realname',
            ));
        }
        $this->responseJSON($res);
    }

    /**
     *
     * @param $row
     */
    private function applyCustomizeInfo(&$row)
    {

        if(!$id=$row['t_business_id']) return;
        $customize=app('mysqlbxd_mall_common')->fetch('select `c_id`, `c_title` from custom where c_id ='.$id);
        $img=app('mysqlbxd_mall_common')->fetchColumn('select `ci_img` from `custom_image` where c_id ='.$id.' order by ci_id desc limit 1');
        $row['t_share_picture'] = $img?FileHelper::getFileUrl($img):('https:' . config('app.CDN.BASE_URL_RES') . '/html/images/fenxianglogo.jpg');
        $row['t_share_title'] = $customize['c_title'];
        $row['t_share_url'] = 'https:' . config('app.CDN.BASE_URL_RES') . '/res/zw2.5/index.html#/detail';
    }

    private function setShareData(&$value)
    {
        $schema = config('app.request_url_schema_x_forwarded_proto_default');
        $cdnBaseUrl = config('app.CDN.BASE_URL_RES');
        $baseDomain = config('app.baseDomain');

        $business_id = $value['t_business_id'];
        //获取头条信息
        $newsModel = new \Model\News\News();
        $newsMessage = $newsModel->one('n_status = 0 AND n_id=:n_id', [':n_id' => $business_id]);
        if ($newsMessage) {
            $imagePath = $newsMessage['n_picurl'];
            if (empty($imagePath)) {
                $newImages = $newsModel->getImg($business_id, 1);
                if (!empty($newImages[0]['ni_img'])) {
                    $imagePath = $newImages[0]['ni_img'];
                }
            }

            if (!empty($imagePath)) {
                $value['t_share_picture'] = FileHelper::getFileUrl($imagePath, 'news_images');
            } else {
                $value['t_share_picture'] = $schema . ':' . $cdnBaseUrl . '/html/images/fenxianglogo.jpg';
            }
            $value['t_share_title'] = $newsMessage['n_title'];
            $value['t_share_url'] = $schema . '://' . $baseDomain . '/html/infoDetail.html?n_id=' . $business_id;
        } else {
            $value['t_share_picture'] = $schema . ':' . $cdnBaseUrl . '/html/images/fenxianglogo.jpg';
            $value['t_share_title'] = '';
            $value['t_share_url'] = '';
        }
    }

    /**
     * 获取一条晒宝信息的点赞数量和评论数量
     *
     * @throws ModelException
     */
    public function likeCount()
    {
        $t_id = app('request')->params('t_id');
        if (!$t_id) {
            throw new \Exception\ParamsInvalidException("不存在的t_id");
        }
        $rest = $this->TreasureModel->selectOne($t_id);
        if (!$rest) {
            throw new ModelException("获取列表失败");
        }
        $this->responseJSON($rest);
    }

    /**
     * 获取一条晒宝信息的点赞数量和评论数量
     *
     * @throws ModelException
     */
    public function islikeCount()
    {
        $t_id = app('request')->params('t_id');
        $u_id = app('request')->params('u_id');
        $likeLogModel = new \Model\Treasure\TreasureLikeLog();
        $rest = $likeLogModel->treasureIsLikeLogInfo($t_id, $u_id);

        $this->responseJSON($rest);
    }

    /**
     * 获取详情
     *
     * @throws ModelException
     */
    public function detail()
    {
        $t_id = app('request')->params('t_id', '');
        if (!$t_id) {
            throw new ParamsInvalidException("艺术圈id必须");
        }
        $data = $this->TreasureModel->oneById($t_id);
        if (!$data || !is_array($data) || empty($data)) {
            throw new ServiceException("获取艺术圈信息已失效");
        }

        $commentModel = new \Model\Treasure\TreasureComment();
        $likeLogModel = new \Model\Treasure\TreasureLikeLog();
        $treasureImgModel = new \Model\Treasure\TreasureImage();
        $data['is_oneself'] = $this->uid == $data['u_id'] ? 1 : 0;
        $data['is_like'] = $likeLogModel->treasureIsLikeLogInfo($this->uid, $data['t_id']);
        $data['is_comment'] = $commentModel->isComment($this->uid, $data['t_id']);
        $data['t_provinceName'] = Region::getRegionNameByCode($data['t_provinceCode']);
        $data['t_cityName'] = Region::getRegionNameByCode($data['t_cityCode']);
        $data['t_areaName'] = Region::getRegionNameByCode($data['t_areaCode']);
        list ($pic, $picTotalCount) = $treasureImgModel->lists(['t_id' => $data['t_id']], 1, 10);
        $data['t_pictures'] = $pic;
        $time = strtotime($data['t_createDate']);
        $data['displayTime'] = date_format_to_display($time);
        $userLib = new \Lib\User\User();
        $favModel = new \Model\User\Favorite();

        //点赞用户
        list ($likeLogs, $likeLogsTotalCount) = $likeLogModel->lists(array('t_id' => $data['t_id']), 1, 10);
        $data['likeLogInfos'] = $likeLogs;

        $userLib->extendUserInfos2Array($data['likeLogInfos'], 'u_id', array('u_nickname' => 'tc_nickname', 'u_realname' => 't_realname',));

        //评论用户
        list ($comments, $commentTotalCount) = $commentModel->lists(array('t_id' => $data['t_id']), 1, 10);
        $data['commentInfos'] = $comments;

        // 是否认证
        $certificationModel = new \Model\User\Certification();
        $data['certification'] = $certificationModel->getType($data['u_id']);

        //收藏状态
        $favInfo = $favModel->oneByUfavObjectId($this->uid, $t_id);
        $data['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
        $data['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';

        //头条类型圈子处理
        if ($data['t_type'] == 2 && !empty($data['t_business_id'])) {
            $this->setShareData($data);
        }

        //定制类型圈子处理
        if ($data['t_type'] == 3 && !empty($data['t_business_id'])) {
            $this->applyCustomizeInfo($data);
        }
        $userLib->extendUserInfos2Array($data['commentInfos'], 'u_id', array('u_nickname' => 'tc_nickname', 'u_realname' => 't_realname',));
        if (!empty($data['commentInfos'])) {
            foreach ($data['commentInfos'] as &$value) {
                $time2 = strtotime($value['tc_time']);
                $value['displayTime'] = date_format_to_display($time2);
            }
        }

        $procData = [$data];

        $userLib->extendUserInfos2Array($procData, 'u_id', array(
            'u_nickname' => 't_nickname',
            'u_avatar' => 't_avatar',
            'u_realname' => 't_realname',
        ));
        $this->responseJSON($procData[0]);
    }

    /**
     * 删除晒宝
     *
     * @throws ModelException
     */
    public function delete()
    {
        $u_id = $this->uid;
        $t_id = app('request')->params('t_id');
        if (!$u_id) {
            throw new \Exception\ParamsInvalidException("不存在的用户");
        }
        $rest = $this->TreasureModel->removeTreasure($u_id, $t_id);
        if (!$rest) {
            throw new ModelException("艺术圈内容已移除");
        }else{
            //移除话题关联
            $topicModel=new \Model\Treasure\TreasureTopic();
            $topicModel->removeTreasureTopicRef($t_id);
        }
        $this->responseJSON('');
    }

    /**
     * 新增晒宝点赞
     *
     * @author Administrator
     *
     */
    public function likes()
    {
        $treaLikeModel = new \Model\Treasure\TreasureLikeLog();
        $u_id = $this->uid;
        $t_id = app('request')->params('t_id');
        if (!$u_id) {
            throw new \Exception\ParamsInvalidException("不存在的用户");
        }
        $rest = $treaLikeModel->add($u_id, $t_id);
        if ($rest) {
            $this->TreasureModel->treasureLikeAdd($t_id);
        } else {
            throw new ModelException("点赞失败");
        }
        if (!$this->isSelf($t_id, $u_id)) {
            $queueAppid = config('app.queue_common_params.appid');
            QueueManager::queue($rest, 6, '', $queueAppid);
        }

        $info = $treaLikeModel->oneById($rest);
        if (is_array($info)) {
            $userLib = new \Lib\User\User();
            $userInfo = $userLib->getUserInfo([$info['u_id']]);
            $userInfo = current($userInfo);
            $info['tc_nickname'] = $userInfo['u_nickname'];
            $info['t_realname'] = $userInfo['u_realname'];
            $info['tc_avatar'] = $userInfo['u_avatar'];
        }

        $this->responseJSON($info);
    }

    /**
     * 取消点赞
     *
     * @author Administrator
     *
     */
    public function unlikes()
    {
        $treaLikeModel = new \Model\Treasure\TreasureLikeLog();
        $u_id = $this->uid;
        $t_id = app('request')->params('t_id');
        if (!$u_id) {
            throw new \Exception\ParamsInvalidException("不存在的用户");
        }
        $rest = $treaLikeModel->treasureLikeLogRemove($u_id, $t_id);
        if ($rest) {
            $this->TreasureModel->treasureLikeAdd($t_id, 0);
        } else {
            throw new ModelException("取消点赞失败");
        }

        $likeLogModel = new \Model\Treasure\TreasureLikeLog();
        $userLib = new \Lib\User\User();

        list ($likeLogs, $likeLogsTotalCount) = $likeLogModel->lists(array('t_id' => $t_id));
        $data = $likeLogs;

        $userLib->extendUserInfos2Array($data, 'u_id', array('u_nickname' => 'tc_nickname', 'u_realname' => 't_realname',));

        $this->responseJSON($data);
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
    static public function handleData($data = '', $filter = '')
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

    /** 活跃圈友
     * @throws ParamsInvalidException
     */
    public function activeTreasure()
    {
        $param = array(
            't_status' => 0
        );
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (!$page || !$pageSize) {
            throw new ParamsInvalidException('参数错误');
        }

        $rest = $this->TreasureModel->getActiveLists($param, $page, $pageSize);
        $res = $rest[0];
        if (is_array($res) && !empty($res)) {
            $commentModel = new \Model\Treasure\TreasureComment();
            $likeLogModel = new \Model\Treasure\TreasureLikeLog();
            $treasureImgModel = new \Model\Treasure\TreasureImage();
            $certificationModel = new \Model\User\Certification();
            $userLib = new \Lib\User\User();
            $favModel = new \Model\User\Favorite();
            $friendsCtl = new \Controller\User\Friends();
            foreach ($res as &$value) {
                $value['is_like'] = $likeLogModel->treasureIsLikeLogInfo($this->uid, $value['t_id']);
                $value['is_comment'] = $commentModel->isComment($this->uid, $value['t_id']);
                $value['is_oneself'] = $this->uid == $value['u_id'] ? 1 : 0;
                $value['t_provinceName'] = Region::getRegionNameByCode($value['t_provinceCode']);
                $value['t_cityName'] = Region::getRegionNameByCode($value['t_cityCode']);
                $value['t_areaName'] = Region::getRegionNameByCode($value['t_areaCode']);
                list ($pic, $picTotalCount) = $treasureImgModel->lists(['t_id' => $value['t_id']], 1, 10);
                $value['t_pictures'] = $pic;
                $time = strtotime($value['t_createDate']);
                $value['displayTime'] = date_format_to_display($time);

                // 收藏信息
                $favInfo = $favModel->oneByUfavObjectId($this->uid, $value['t_id']);
                $value['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
                $value['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';

                // 是否认证
                $value['certification'] = $certificationModel->getType($value['u_id']);

                // 作品数量
                $goodsNum = [];
                $goodsNum = $userLib->getUserExtend($value['u_id']);
                $value['goodsNum'] = $goodsNum['list']['ue_goodsNum'];

                //关注和粉丝数
                $fans = $friendsCtl->getRelateList(1, $value['u_id']);
                $attention = $friendsCtl->getRelateList(2, $value['u_id']);
                $value['fansNum'] = $fans['num'];
                $value['attentionNum'] = $attention['num'];
            }
            $userLib->extendUserInfos2Array($res, 'u_id', array(
                'u_nickname' => 't_nickname',
                'u_avatar' => 't_avatar',
                'u_realname' => 't_realname',
            ));
        }

        $this->responseJSON($res);
    }

    /**
     * 是否是自身发布的圈子
     * @param $t_id
     * @param $u_id
     * @return bool
     */
    private function isSelf($t_id,$u_id)
    {
        $treasureModel = new \Model\Treasure\Treasure();
        $treasureData = $treasureModel->oneById($t_id);
        return !empty($treasureData) && $treasureData['u_id'] == $u_id;
    }
}
