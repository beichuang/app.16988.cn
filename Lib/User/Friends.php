<?php
namespace Lib\User;

use Exception\InternalException;
use Exception\ServiceException;
use Framework\Lib\Validation;
use Exception\ParamsInvalidException;
use Lib\Common\AppMessagePush;

class Friends
{

    private $userApi = null;

    private $friendsModel = null;

    public function __construct()
    {
        $this->friendsModel = new \Model\Friends\Friends();
        $this->userApi = new \Lib\User\User();
    }

    /**
     * 是否是好友
     *
     * @param int $u_id            
     * @param int $friendId            
     * @return boolean
     */
    public function isFriends($u_id, $friendId)
    {
        return $this->friendsModel->isFriends($u_id, $friendId);
    }

    /**
     * 将两个人加成好友
     *
     * @param int $uid            
     * @param int $targetFriendUid            
     * @param string $message            
     * @param string $friendRemark            
     * @param string $userRemark            
     * @param number $sourceFrom            
     * @return array [申请人的好友id，被申请人好友id，好友状态处理是否ok]
     */
    public function makeFriends($uid, $targetFriendUid, $message = '', $friendRemark = '', $userRemark = '', $sourceFrom = 0)
    {
        list ($idApplyUser, $idTargetUser) = $this->apply($uid, $targetFriendUid, $message, $friendRemark, $sourceFrom);
        $isOk = $this->dealApply($targetFriendUid, $uid, 1, $userRemark);
        return array(
            $idApplyUser,
            $idTargetUser,
            $isOk
        );
    }

    /**
     * 申请添加好友
     *
     * @param int $uid            
     * @param int $targetFriendUid
     *            对方聚派账号，uid
     * @param string $message
     *            验证消息
     * @param string $friendRemark
     *            对方备注，默认为昵称
     * @param number $sourceFrom
     *            好友来源(默认0)，0聚派APP内查找
     * @throws \Exception\ParamsInvalidException
     * @throws \Exception\ServiceException
     */
    public function apply($uid, $targetFriendUid, $message = '', $friendRemark = '', $sourceFrom = 0)
    {
        if (! $targetFriendUid) {
            throw new \Exception\ParamsInvalidException("对方账号必须");
        }
        if ($uid == $targetFriendUid) {
            throw new ServiceException("不能加自己好友");
        }
        $applyInfo = $this->friendsModel->oneByUidFirendId($uid, $targetFriendUid);
        $targetApplyInfo = $this->friendsModel->oneByUidFirendId($targetFriendUid, $uid);
        if ($targetApplyInfo && $applyInfo && ((string) $applyInfo['fri_applyStatus'] === '2') &&
             ((string) $targetApplyInfo['fri_applyStatus'] === '2')) {
            throw new \Exception\ServiceException("对方已经是好友");
        }
        
        $friendExtraData = $this->getUserExtraDataFromUserApi($targetFriendUid);
        if (! $friendRemark) {
            $friendRemark = isset($friendExtraData['u_nickname']) ? $friendExtraData['u_nickname'] : '';
        }
        $idApplyUser = $this->friendsModel->add($uid, $targetFriendUid, $friendRemark, '', '申请加对方好友', 0, 0);
        
        $myExtraData = $this->getUserExtraDataFromUserApi($uid);
        $myRemark = isset($myExtraData['u_nickname']) ? $myExtraData['u_nickname'] : '';
        $idTargetUser = $this->friendsModel->add($targetFriendUid, $uid, $myRemark, '', $message, 0, 1);
        return array(
            $idApplyUser,
            $idTargetUser
        );
    }

    /**
     * 处理好友申请
     *
     * @throws ModelException
     */
    public function dealApply($uid, $targetFriendUid, $state, $friendRemark = '')
    {
        $state = (string) $state;
        if (! isset($state)) {
            throw new \Exception\ParamsInvalidException("回复状态必须");
        }
        if (! in_array($state, [
            1,
            2,
            3
        ])) {
            throw new \Exception\ParamsInvalidException("回复状态错误");
        }
        if (! $targetFriendUid) {
            throw new \Exception\ParamsInvalidException("对方账号必须");
        }
        $applyInfo = $this->friendsModel->oneByUidFirendId($uid, $targetFriendUid);
        $beAppliedInfo = $this->friendsModel->oneByUidFirendId($targetFriendUid, $uid);
        if (! $applyInfo || ! $beAppliedInfo) {
            throw new ServiceException("申请已失效");
        }
        $applyStatus = (string) $applyInfo['fri_applyStatus'];
        $beAppliedStatus = (string) $beAppliedInfo['fri_applyStatus'];
        if ($applyStatus === '2' && $beAppliedStatus === '2') {
            throw new ServiceException("已是好友");
        }
        if ($state !== '1') {
            $this->friendsModel->delete($applyInfo['fri_id']);
            $this->friendsModel->delete($beAppliedInfo['fri_id']);
        } else {
            if ($applyStatus !== '1' && $beAppliedStatus !== '0') {
                throw new ServiceException("申请状态异常");
            }
            $this->friendsModel->updateStatus($applyInfo['fri_id'], 2);
            $this->friendsModel->updateStatus($beAppliedInfo['fri_id'], 2);
        }
        return true;
    }

    /**
     * 获取好友扩展详细信息
     *
     * @param int $uid            
     * @throws \Exception\ServiceException
     * @return multitype:unknown number Ambigous <string, unknown>
     */
    private function getUserExtraDataFromUserApi($uid)
    {
        $friendsInfos = $this->userApi->getUserInfo([
            $uid
        ], '', 1);
        if (! isset($friendsInfos[$uid])) {
            throw new \Exception\ServiceException("uid:{$uid}，账号不存在");
        }
        $friendsInfo = $friendsInfos[$uid];
        $fri_sex = 0;
        if (isset($friendsInfo['user_extend']) && isset($friendsInfo['user_extend']['ue_gender'])) {
            $fri_sex = $friendsInfo['user_extend']['ue_gender'];
        }
        $extraData = array(
            'fri_nickname' => isset($friendsInfo['u_nickname']) ? $friendsInfo['u_nickname'] : '',
            'fri_realname' => isset($friendsInfo['u_realname']) ? $friendsInfo['u_realname'] : '',
            'fri_avatar' => isset($friendsInfo['u_avatar']) ? $friendsInfo['u_avatar'] : '',
            'fri_provinceCode' => isset($friendsInfo['u_provinceCode']) ? $friendsInfo['u_provinceCode'] : '',
            'fri_cityCode' => isset($friendsInfo['u_cityCode']) ? $friendsInfo['u_cityCode'] : '',
            'fri_areaCode' => isset($friendsInfo['u_areaCode']) ? $friendsInfo['u_areaCode'] : '',
            'fri_sex' => $fri_sex
        );
        return $extraData;
    }

    /**
     * 通知用户对方已添加自己为好友
     * 
     * @param unknown $noticeUid
     *            通知的用户
     */
    private function pushMakeFriendsMessage($noticeUid)
    {
        $noticeUser = $this->getUserExtraDataFromUserApi($targetFriendUid);
        $u_nick=$noticeUser['fri_nickname'];
        $u_name=$noticeUser['fri_realname'];
        $u_avatar=$noticeUser['fri_avatar'];
        $title=$content="{$u_nick}同意加您为好友";
        $type = AppMessagePush::PUSH_TYPE_MAKE_FRIENDS;
        
        AppMessagePush::push($uids, $title, $content, $noticeUser, $type);
    }
}
