<?php

/**
 * 邀请用户是否已经注册
 */
namespace Cli\Worker;

use Controller\User\Invite;
use Lib\User\User;

class UserInviteIsRegister
{

    private $db = null;
    private $inviteModel = null;
    public function __construct()
    {
        $this->db = app('mysqlbxd_app');
        $this->inviteModel = new \Model\User\Invite();
    }
    public function run()
    {
        while (true) {
            $logs = $this->fetchData();
            $userLib = new User(false);
            if ($logs) {
                foreach ($logs as &$info) {
                    if ($info['uil_phone']) {
                        $users = $userLib->getUserInfo([], $info['uil_phone']);
                        if ($users){
                            $this->inviteModel->updateRegisterStatus($info['uil_id']);
                        }
                    }
                }
            }
            exitTask('03:00:00', '03:02:00');
            sleep(180);
        }
    }

    private function fetchData()
    {
        $time = date('Y-m-d',strtotime('-1 day')).' 00:00:00';
        $sql = "select * from `user_invite_log` where UNIX_TIMESTAMP('$time') < UNIX_TIMESTAMP(uil_time)";
        $logs = $this->db->select($sql);
        return $logs;
    }





}
