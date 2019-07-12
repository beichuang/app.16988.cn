<?php
namespace Model\User;

use Lib\Base\BaseModel;

class User extends BaseModel
{
    protected $table = 'user';
    protected $id = 'u_id';

    public function getUserIdList($phoneList)
    {
        $sql = 'select u_id from `user` WHERE FIND_IN_SET(u_phone,:phoneList)';
        $data = app('mysqlbxd_user')->select($sql, [':phoneList' => implode(',', $phoneList)]);
        if ($data) {
            return array_column($data, 'u_id');
        }

        return [];
    }

    public function getUser($userId)
    {
        $sql = 'select * from `user` WHERE u_id=:uid';
        return app('mysqlbxd_user')->fetch($sql, [':uid' => $userId]);
    }



}
