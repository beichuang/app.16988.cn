<?php
namespace Model\User;

use Lib\Base\BaseModel;

class UserShop extends BaseModel
{
    protected $table = 'user_miniprogram_shop';

    protected $id = 'uc_id';

    public function __construct()
    {
        parent::__construct($table = null, $id = null, $mysqlDbFlag = 'mysqlbxd_mall_user');
    }

    public function getOne($uid)
    {
        return $this->one('uc_uid=:uid', [':uid' => $uid]);
    }
}
