<?php

/**
 * 代金券到期
 */
namespace Cli\Worker;



class DataChange
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }
    public function run()
    {
        //查找所有待审核艺术家
        $countSql = 'SELECT COUNT(*) FROM `user` AS u LEFT JOIN `user_extend` AS  ue ON u.u_id=ue.u_id WHERE 1=1  AND u.u_type = 1 ';
        $count = app('mysqlbxd_user')->fetchColumn($countSql);
        $pageNum = ceil($count/50);
        for($i=0;$i<=$pageNum;$i++){
            //获取艺术家   识别认证否还不知道
            $uIds =  $this->getUserExtendList($i);
            if(empty($uIds)){
                dd("执行成功");
            }
            //获取实名认证通过的艺术家
            $uids = "(".implode(',',$uIds).")";
            $rzsql = "select u_id from user_certification where uce_status=1 and u_id in {$uids}  ";
            $list = app('mysqlbxd_app')->select($rzsql);
            $list_uids = array_column($list,"u_id");
            $list_uids = "(".implode(',',$list_uids).")";
            //批量修改 对应字段
            $update_uid_status_sql = " update user_extend set ue_status=1 where u_id in  {$list_uids}  ";
            $updateAll   = app('mysqlbxd_user')->query($update_uid_status_sql);
            sleep(1.5);
        }
    }

    private  function getUserExtendList($pageIndex = 0, $pageSize = 50)
    {
        $listSql = 'SELECT u.u_id FROM `user` AS u LEFT JOIN `user_extend` AS  ue ON u.u_id=ue.u_id WHERE 1=1   and u.u_type = 1 ';
        $listSql .= ' ORDER BY u.u_createDate DESC LIMIT ' . $pageIndex * $pageSize . ',' . $pageSize;
        $list = app('mysqlbxd_user')->select($listSql);
        $list_uids = array_column($list,"u_id");
        return $list_uids;
    }



}