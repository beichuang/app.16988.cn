<?php
namespace Model\User;

use Lib\Base\BaseModel;

class Distribution extends BaseModel
{

    protected $table = 'user_distribution';

    protected $id = 'ud_id';

    public function __construct()
    {
        parent::__construct($table = null, $id = null, $mysqlDbFlag = 'mysqlbxd_user');
    }

    public function getCount($uid, $gid)
    {
        return (int)$this->rowCount('u_id=:uid AND g_id=:gid', [':uid' => $uid, ':gid' => $gid]);
    }

    public function getListByUid($uid, $status = null)
    {
        $sql = 'SELECT * FROM user_distribution WHERE u_id=:uid';
        $params = [':uid' => $uid];
        if ($status !== null) {
            if (is_array($status)) {
                $sql .= ' AND FIND_IN_SET(ud_status,:status)';
                $params[':status'] = implode(',', $status);
            } else {
                $sql .= ' AND ud_status=:status';
                $params[':status'] = $status;
            }
        }

        $sql .= ' ORDER BY ud_topDate DESC';
        return app('mysqlbxd_user')->select($sql, $params);
    }

    public function getOne($uid, $gid)
    {
        $sql = 'SELECT * FROM user_distribution WHERE u_id=:uid AND g_id=:gid;';
        return app('mysqlbxd_user')->fetch($sql, [':uid' => $uid, ':gid' => $gid]);
    }

    public function add($uid, $gid)
    {
        $data['u_id'] = $uid;
        $data['g_id'] = $gid;
        $this->insert($data);
    }

    public function updateData($ud_id, $status)
    {
        $this->update($ud_id, ['ud_status' => $status]);
    }
}
