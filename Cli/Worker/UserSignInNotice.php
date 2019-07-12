<?php

/**
 * 用户签到提醒通知
 */
namespace Cli\Worker;


use Lib\Common\AppMessagePush;
use Lib\User\UserIntegral;

class UserSignInNotice
{
    private $db = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->db = app('mysqlbxd_user');
    }

    public function run()
    {
        //每天10：00提醒未签到的用户
        $users = $this->getUsers();
        $this->sendMessage($users);
    }

    private function sendMessage($users)
    {
        if ($users) {
            $userIdArray = array_column($users, 'u_id');
            //$userIdArray = array_intersect($userIdArray, ['369646729', '627338172', '631024613', '1030010131']);
            $userIds = implode(',', $userIdArray);
            $messageTitle = '今日您还未签到领取积分～';
            $messageContent = '每天保持签到好习惯，攒够积分换好礼。';
            $type = AppMessagePush::PUSH_TYPE_SIGN_IN;

            if($userIds) {
                //用户id集合、消息标题、推送内容预览、消息内容、消息类型
                AppMessagePush::push($userIds, $messageTitle, $messageContent, ['sid' => '', 'utype' => $type,], $type);
            }
        }
    }

    private function getUsers()
    {
        //获取当天已签到的所有用户
        $today = date("Y-m-d");
        $sql = 'SELECT u_id FROM user_integral_log WHERE uil_type=:type AND uil_createDate>:today';
        $users = $this->db->select($sql, [':type' => UserIntegral::ACTIVITY_SIGNIN_ADD, ':today' => $today]);

        //获取当天未签到的所有用户
        $userIdArray = array_column($users, 'u_id');
        $userIds = $userIdArray ? implode(',', $userIdArray) : '';
        $sql = 'SELECT * FROM `user` WHERE u_status=0 AND u_isGrabage=0';
        $sqlParams = [];
        if ($userIds) {
            $sql .= ' AND NOT FIND_IN_SET(u_id,:uids)';
            $sqlParams[':uids'] = $userIds;
        }
        $data = $this->db->select($sql, $sqlParams);
        return $data;
    }
}
