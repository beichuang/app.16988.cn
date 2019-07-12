<?php

/**
 * 用户好友
 * @author Administrator
 *
 */

namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ModelException;
use Exception\ParamsInvalidException;

class Friends extends BaseController {

    private $friendsModel = null;
    private $friendsLib = null;

    public function __construct() {
        parent::__construct();
        $this->friendsModel = new \Model\Friends\Friends();
    }

    /**
     * 关注好友
     *
     * @throws ModelException
     */
    public function attention() {
        $targetFriendUid = app()->request()->params('targetFriendUid');
        if (!$targetFriendUid) {
            throw new ParamsInvalidException("好友Uid必须");
        }
        if ($targetFriendUid == $this->uid) {
            throw new ParamsInvalidException("不能关注自己");
        }
        $userLib = new \Lib\User\User();
        $userInfos = $userLib->getUserInfo([$targetFriendUid]);
        if (!$userInfos) {
            throw new ParamsInvalidException("用户不存在");
        }

        $retIsAttention = $this->friendsModel->isAttention($this->uid, $targetFriendUid);
        if ($retIsAttention) {
            throw new ParamsInvalidException("已关注");
        }

        // $nick_remark = $userInfos[][]
        $attentionId = $this->friendsModel->addAttention($this->uid, $targetFriendUid);
        if (!$attentionId) {
            throw new ParamsInvalidException("添加关注失败");
        }

        $this->responseJSON(array(
            'attentionId' => $attentionId
        ));
    }

    /**
     * 取消关注
     *
     * @throws ModelException
     */
    public function cancelAttention() {
        $targetFriendUid = app()->request()->params('targetFriendUid');
        if (!$targetFriendUid) {
            throw new ParamsInvalidException("好友Uid必须");
        }

        $userLib = new \Lib\User\User();
        $userInfos = $userLib->getUserInfo([$targetFriendUid]);
        if (!$userInfos) {
            throw new ParamsInvalidException("用户不存在");
        }

        $retIsAttention = $this->friendsModel->isAttention($this->uid, $targetFriendUid);
        if (!$retIsAttention) {
            throw new ParamsInvalidException("当前没有关注该好友");
        }

        $retCancel = $this->friendsModel->cancelAttention($this->uid, $targetFriendUid);
        if (!$retCancel) {
            throw new ParamsInvalidException("取消关注失败");
        }

        $this->responseJSON($retCancel);
    }

    /**
     * 是否关注该用户
     */
    public function getRelation() {
        $targetFriendUid = app()->request()->params('targetFriendUid');
        if (!$targetFriendUid) {
            throw new ParamsInvalidException("好友Uid必须");
        }
        if ($targetFriendUid == $this->uid) {
            throw new ParamsInvalidException("数据错误");
        }
        // 验证好友是否存在
        $userLib = new \Lib\User\User();
        $userInfos = $userLib->getUserInfo([$targetFriendUid]);
        if (!$userInfos) {
            throw new ParamsInvalidException("用户不存在");
        }

        $retRelation = $this->friendsModel->relation($this->uid, $targetFriendUid);

        $this->responseJSON($retRelation);
    }

    /**
     * @param int $status 1粉丝，2关注，3互粉
     * @param int $uid
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws ParamsInvalidException
     */
    public function getRelateList($status = 0, $uid = 0, $page = 1, $pageSize = 10) {
        empty($status) && $status = app()->request()->params('status');
        if (empty($uid)) {
            $uid = $this->uid;
        }

        if (in_array($status, [1, 2])) {
            $responseData = $this->relateList($status, $uid, $page, $pageSize);
        } else if ($status == '3') {
            $responseData = $this->mutualFansNum($uid, $page, $pageSize);
        } else {
            throw new ParamsInvalidException("数据错误");
        }

        return $responseData;
    }

    /**
     * 我关注用户列表
     * @param  int  status  1关注2粉丝3互粉
     */
    public function showRelateList() {
        $this->responseJSON($this->getRelateList());
    }

    /**
     * 获得关注列表
     * @throws ParamsInvalidException
     */
    public function showAllRelateList() {
        $status = app()->request()->params('status');
        $uid = app()->request()->params('uid', $this->uid);
        if (!$status) {
            throw new ParamsInvalidException("status必传");
        }

        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);

        if(!in_array($status,[1,2,3])){
            throw new ParamsInvalidException('状态只能是1,2,3');
        }
        $list1 = $this->getRelateList(1, $uid, $page, $pageSize);
        $list2 = $this->getRelateList(2, $uid, $page, $pageSize);
        $list3 = $this->getRelateList(3, $uid, $page, $pageSize);

        $listname = 'list' . $status;
        $showlist = $$listname;
        $userLib = new \Lib\User\User();
        $userInfos = empty($showlist['list']) ? [] : $userLib->getUserInfo($showlist['list']);

        // 是否认证
        $certificationModel = new \Model\User\Certification();

        foreach ($userInfos as $key => &$value) {
            $value['relation'] = $this->friendsModel->relation($this->uid, $value['u_id']);
            $value['certification'] = $certificationModel->getType($value['u_id']);

            $goodsNum = [];
            $goodsNum = $userLib->getUserExtend($value['u_id']);
            $value['goodsNum'] = $goodsNum['list']['ue_goodsNum'];
        }

        $this->responseJSON([
            'list' => array_values($userInfos),
            'attentionNum' => $list2['num'],
            'fansNum' => $list1['num'],
            'mutualFansNum' => $list3['num'],
        ]);
    }

    /**
     * @param $status 1 粉丝，2关注
     * @param $uid
     * @param $page
     * @param $pageSize
     * @return array
     */
    private function relateList($status, $uid, $page=1, $pageSize=10) {
        $fieldList = ['2' => 'fri_userId', '1' => 'fri_friendId'];  // 搜索过滤的字段
        $fieldShow = ['2' => 'fri_friendId', '1' => 'fri_userId'];  // 查找输出的字段

        $data = [$fieldList[$status] => $uid];

        list($relateList, $num) = $this->friendsModel->lists($data, $page, $pageSize);
        $user_list = array_column($relateList, $fieldShow[$status]);

        return [
            'list' => $user_list,
            'num' => $num,
        ];
    }

    /**
     * 获取关联用户数
     */
    private function mutualFansNum($uid, $page, $pageSize) {
        list($attentionList) = $this->friendsModel->lists(['fri_userId' => $uid]);
        list($fansList) = $this->friendsModel->lists(['fri_friendId' => $uid]);

        $attentionId = array_column($attentionList, 'fri_friendId');
        $fansId = array_column($fansList, 'fri_userId');
        $mutualFans = array_intersect($attentionId, $fansId);

        if ($page && $pageSize) {
            $start = max(($page - 1) * $pageSize, 0);
            $portionList = array_slice($mutualFans, $start, $pageSize);
        } else {
            $portionList = $mutualFans;
        }

        return ["list" => $portionList, "num" => (string) count($mutualFans)];
    }

    public function relateSearch() {
        $friendName = app()->request()->params('friendName', '');

        $retSearch = $this->friendsModel->searchList($this->uid);
        $temp1 = array_column($retSearch, 'fri_friendId');
        $temp2 = array_column($retSearch, 'fri_userId');
        $mySearch = array_unique(array_merge($temp1, $temp1));

        $mykey = array_search($this->uid, $mySearch);
        if (false !== $mykey) {
            unset($mySearch[$mykey]);
        }

        $data = array();
        $data['u_id'] = $mySearch;
        $data['realname'] = $data['nickname'] = $friendName;

        $userLib = new \Lib\User\User();
        $retSearch = $userLib->fuzzySearch($data);
        $userInfos = current($retSearch);

        $certificationModel = new \Model\User\Certification();
        if ($userInfos) {
            foreach ($userInfos as &$value) {
                $value['relation'] = $this->friendsModel->relation($this->uid, $value['u_id']);
                // 是否认证
                $value['certification'] = $certificationModel->getType($value['u_id']);
                // 作品数
                $goodsNum = [];
                $goodsNum = $userLib->getUserExtend($value['u_id']);
                $value['goodsNum'] = $goodsNum['list']['ue_goodsNum'];
            }
        }

        $this->responseJSON(array_values($userInfos));
    }

    /**
     * 已关注的所有用户的商品列表
     */
    public function attentionGoodsLists() {
        $uid = $this->uid;
        $resMall = [];
        $showList = $this->getRelateList(2, $uid, 1, 50);
        if ($showList['list']) {
            $params['salesId'] = $showList['list'];
            $params['page'] = app()->request()->params('page', 1);
            $params['pageSize'] = app()->request()->params('pageSize', 10);
            $goodsLib = new \Lib\Mall\Goods();
            $resMalls = $goodsLib->lists($params);
            $resMall = $resMalls['lists'];

            if ($resMall) {
                $userLib = new \Lib\User\User();
                $userLib->extendUserInfos2Array($resMall, 'g_salesId', array(
                    'u_realname' => 'u_realname',
                        )
                );

                $goodsLikeLogModel = new \Model\Mall\GoodsLikeLog();
                foreach ($resMall as $k => &$v) {
                    if ($this->uid && $v['g_id']) {
                        $isLike = false;
                        $isLike = $goodsLikeLogModel->findByUidGcId($this->uid, $v['g_id']);
                        $v['itemCurrentUserLikeInfo'] = empty($isLike) ? null : $isLike;

                        $wnum = $v['g_browseTimes'] / 10000;
                        if ($wnum >= 1) {
                            $v['g_browseTimes'] = intval($wnum) . '万';
                        }
                    }
                    if($this->clientType==self::CLIENT_TYPE_ANDROID && $v['isSecKill']){
                        $tmpActivityPrice=$v['g_activityPrice'];
                        $v['g_activityPrice']=$v['g_price'];
                        $v['g_price']=$tmpActivityPrice;
                    }
                }
            }
        }
        $this->responseJSON($resMall);
    }

}
