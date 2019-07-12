<?php

// 商品分类model
namespace Framework\Model;

use Framework\Lib\SimpleModel;

class NewsCategory extends SimpleModel
{

    /**
     * 从商品列表api接口获取分类树数组
     * 
     * @param integer $parentId
     *            上级分类id 默认0
     * @return array
     */
    public function getCategoryTree($parentId = 0)
    {
        $parentId = intval($parentId);

        $sql = "select * from news_category where nc_pid=".$parentId;
        $return = app('mysqlbxd_app')->select($sql);

        return $return;
    }


    /**
     * @param array $condition
     */
    public function getCategory($condition = []){
        $sql = "select * from news_category where 1=1";
        $where = [];
        if (isset($condition['parentId'])) {
            $sql .= ' and nc_pid=:parentId ';
            $where[':parentId'] = $condition['parentId'];
        }
        if (isset($condition['isShow'])) {
            $sql .= ' and nc_is_show=:isShow ';
            $where[':isShow'] = $condition['isShow'];
        }
        if (isset($condition['status'])) {
            $sql .= ' and nc_status = :status';
            $where[':status'] = $condition['status'];
        }

        $sql .= ' order by nc_sort asc ';

        $return = app('mysqlbxd_app')->select($sql , $where);

        return $return;

    }

    /** 获取分类
     * @param $type
     */
    public function getCategoryName($type){
        $sql = "select * from news_category where nc_id=".$type;

        $return = app('mysqlbxd_app')->select($sql);
        if ($return){
            return $return[0];
        }

        return $return;
    }
}