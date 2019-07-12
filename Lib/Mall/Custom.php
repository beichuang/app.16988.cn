<?php

namespace Lib\Mall;

use Exception\ParamsInvalidException;
use Lib\Base\BaseModel;

class Custom extends BaseModel
{
    protected $table = 'custom';

    protected $id = 'c_id';

    public function save($customId, $data, $images)
    {
        if ($customId) {
            app('mysqlbxd_mall_common')->update('custom', $data, [
                'c_id' => $customId
            ]);
            app('mysqlbxd_mall_common')->delete('custom_image', [
                'c_id' => $customId,
                'ci_imageType' => 1
            ]);
        } else {
            list(, $customId) = app('mysqlbxd_mall_common')->insert('custom', $data);
        }

        if ($images) {
            foreach ($images as $imageItem) {
                $imageItem['c_id'] = $customId;
                $imageItem['ci_imageType'] = 1;
                app('mysqlbxd_mall_common')->insert('custom_image', $imageItem);
            }
        }

        return $customId;
    }

    public function saveCustomGoods($data, $images)
    {
        list(, $customGoodsId) = app('mysqlbxd_mall_common')->insert('custom_goods', $data);
        if ($images) {
            foreach ($images as $imageItem) {
                $imageItem['c_id'] = $customGoodsId;
                $imageItem['ci_imageType'] = 2;
                app('mysqlbxd_mall_common')->insert('custom_image', $imageItem);
            }
        }

        return $customGoodsId;
    }

    /**
     * 获取定制列表
     * @param $pageIndex
     * @param $pageSize
     * @param $condition
     * @return array
     */
    public function getList($pageIndex, $pageSize, $condition)
    {
        if(intval($pageSize)>100){
            throw new ParamsInvalidException('pageSize过大');
        }

        $subQuery = 'SELECT *,CASE WHEN `c_status`=30 THEN CASE WHEN `c_submit_endDate` > NOW() THEN 31 WHEN `c_submit_endDate` <= NOW() AND `c_submitCount` =0 THEN 37 WHEN date_add(`c_submit_endDate`, INTERVAL 3 DAY) > NOW() AND `c_submitCount` >0  THEN 35 ELSE 39 END ELSE `c_status` END AS c_status_format FROM custom';
        $where = [];
        $s = 'SELECT * FROM ({$subQuery}) t WHERE 1=1';
        $sc = 'SELECT COUNT(*) as count FROM ({$subQuery}) t WHERE 1=1';
        $sql = '';

        $s = str_replace('{$subQuery}', $subQuery, $s);
        $sc = str_replace('{$subQuery}', $subQuery, $sc);

        //定制类别
        if (isset($condition['c_first_level_categoryId'])) {
            $sql .= ' AND c_first_level_categoryId = :c_first_level_categoryId';
            $where[':c_first_level_categoryId'] = $condition['c_first_level_categoryId'];
        }
        //发布用户
        if (isset($condition['c_createUserId'])) {
            $sql .= ' AND c_createUserId = :c_createUserId';
            $where[':c_createUserId'] = $condition['c_createUserId'];
        }
        //定制状态
        if (isset($condition['c_status'])) {
            if (is_array($condition['c_status'])) {
                $sql .= ' AND FIND_IN_SET(c_status_format, :c_status)';
                $where[':c_status'] = implode(',', $condition['c_status']);
            } else {
                $sql .= ' AND c_status_format = :c_status';
                $where[':c_status'] = $condition['c_status'];
            }
        }
        //定制名称
        if (isset($condition['c_title'])) {
            $sql .= ' AND c_title LIKE CONCAT("%", :c_title , "%")';
            $where[':c_title'] = $condition['c_title'];
        }
        //定制id
        if (isset($condition['c_id'])) {
            $sql .= ' AND c_id = :c_id';
            $where[':c_id'] = $condition['c_id'];
        }
        //排序
        if(!empty($condition['orderBy'])) {
            $orderBy = ' ORDER BY '. implode(',', $condition['orderBy']);
        }else {
            $orderBy = ' ORDER BY c_createDate DESC';
        }
        $sql .= $orderBy;

        $s .= $sql . ' limit ' . $pageIndex * $pageSize . ',' . $pageSize;
        $sc .= $sql;

        $list = app('mysqlbxd_mall_common')->select($s, $where);
        $count = app('mysqlbxd_mall_common')->fetchColumn($sc, $where);

        return [$list, $count];
    }

    public function getMyFitList($pageIndex, $pageSize, $designatedUserId, array $categoryIds)
    {
        $sql = '';
        $where = [];
        $s = 'SELECT *, CASE WHEN `c_submit_endDate` > NOW() THEN 0 ELSE 1 END AS is_submit_end FROM custom WHERE c_status >= 30 AND c_status !=100';
        $sc = 'SELECT COUNT(*) as count FROM custom WHERE c_status >= 30 AND c_status !=100';

        //定制用户 && 擅长领域用户
        if ($designatedUserId && $categoryIds) {
            $sql .= ' AND (c_designatedUserId = :c_designatedUserId OR FIND_IN_SET(c_first_level_categoryId, :categoryIds)) AND c_createUserId !=:c_designatedUserId';
            $where[':c_designatedUserId'] = $designatedUserId;
            $where[':categoryIds'] = implode(',', $categoryIds);
        } elseif ($designatedUserId) {
            $sql .= ' AND c_designatedUserId = :c_designatedUserId AND c_createUserId !=:c_designatedUserId';
            $where[':c_designatedUserId'] = $designatedUserId;
        }

        $s .= $sql . ' ORDER BY is_submit_end ASC, c_submit_endDate ASC limit ' . $pageIndex * $pageSize . ',' . $pageSize;
        $sc .= $sql;

        $list = app('mysqlbxd_mall_common')->select($s, $where);
        $count = app('mysqlbxd_mall_common')->fetchColumn($sc, $where);

        return [$list, $count];
    }

    public function getOneById($id)
    {
        $s = 'SELECT *,CASE WHEN `c_status`=30 THEN CASE WHEN `c_submit_endDate` > NOW() THEN 31 WHEN `c_submit_endDate` <= NOW() AND `c_submitCount` =0 THEN 37 WHEN date_add(`c_submit_endDate`, INTERVAL 3 DAY) > NOW() AND `c_submitCount` >0  THEN 35 ELSE 39 END ELSE `c_status` END AS c_status_format FROM custom where c_id = :id ';
        return app('mysqlbxd_mall_common')->fetch($s, [
            ':id' => $id
        ]);
    }

    public function update($customId, $data)
    {
        app('mysqlbxd_mall_common')->update('custom', $data, [
            'c_id' => $customId
        ]);
    }

    public function customGoodsUpdate($customGoodsId, $data)
    {
        app('mysqlbxd_mall_common')->update('custom_goods', $data, [
            'cg_id' => $customGoodsId
        ]);
    }

    public function getMySubmitCustom($pageIndex, $pageSize, $uid, $status =1)
    {
        if(intval($pageSize)>100){
            throw new ParamsInvalidException('pageSize过大');
        }
        $where = '';
        if ($status == 1) {
            //$params['c_status'] = [5, 15, 20, 31, 35, 40];
            $where = " AND (FIND_IN_SET(c_status,'5, 15, 20') OR (c_status=30 AND `c_submit_endDate` > NOW()) OR (c_status=30 AND `c_submit_endDate` <= NOW() AND date_add(`c_submit_endDate`, INTERVAL 3 DAY) > NOW() AND `c_submitCount` >0))";
        } elseif ($status == 2) {
            //征稿失败、未选稿、已成交、需求关闭
            //$params['c_status'] = [37, 39, 50, 100];
            $where = " AND (FIND_IN_SET(c_status,'50,100') OR (c_status=30 AND `c_submit_endDate` <= NOW() AND `c_submitCount` =0) OR (c_status=30 AND date_add(`c_submit_endDate`, INTERVAL 3 DAY) < NOW()))";
        }

        $s = "SELECT `custom_goods`.`c_id` FROM `custom_goods` INNER JOIN `custom` ON `custom_goods`.`c_id`=`custom`.`c_id`
WHERE cg_createUserId=:uid {$where} GROUP BY `custom_goods`.`c_id` ORDER BY `custom_goods`.`cg_createDate` DESC";
$s .= ' LIMIT ' . $pageIndex * $pageSize . ',' . $pageSize;
        $sc = 'SELECT COUNT(DISTINCT `custom_goods`.`c_id`) FROM `custom_goods` INNER JOIN	`custom` ON	`custom_goods`.`c_id`=`custom`.`c_id`
WHERE cg_createUserId=:uid'. $where;
        $sqlParams = [':uid' => $uid];
        $list = app('mysqlbxd_mall_common')->select($s, $sqlParams);
        if ($list) {
            $customIds = array_column($list, 'c_id');
            $s = <<<sql
SELECT *,
CASE WHEN `c_status`=30 THEN 
  CASE WHEN `c_submit_endDate` > NOW() THEN 31 
  WHEN `c_submit_endDate` <= NOW() AND `c_submitCount` =0 THEN 37
  WHEN date_add(`c_submit_endDate`, INTERVAL 3 DAY) > NOW() AND `c_submitCount` >0  THEN 35 
  ELSE 39 END 
ELSE `c_status` END AS c_status_format 
FROM `custom_goods` INNER JOIN	`custom` ON	`custom_goods`.`c_id`=`custom`.`c_id`
WHERE FIND_IN_SET(`custom_goods`.`c_id`,:customIds) AND `custom_goods`.`cg_createUserId` =:uid ORDER BY FIND_IN_SET(`custom_goods`.`c_id`,:customIds) DESC
sql;
            $list = app('mysqlbxd_mall_common')->select($s, [':customIds' => implode(',', $customIds), ':uid' => $uid]);
        }
        $count = app('mysqlbxd_mall_common')->fetchColumn($sc, $sqlParams);
        return [$list, $count];
    }

    public function getCustomGoodsList($customId,$condition)
    {
        $s = 'SELECT * FROM custom_goods WHERE c_id=:id';
        $where = [':id' => $customId];
        $sql = '';

        //审核状态
        if (isset($condition['cg_auditStatus'])) {
            if (is_array($condition['cg_auditStatus'])) {
                $sql .= ' AND FIND_IN_SET(cg_auditStatus, :cg_auditStatus)';
                $where[':cg_auditStatus'] = implode(',', $condition['cg_auditStatus']);
            } else {
                $sql .= ' AND cg_auditStatus = :cg_auditStatus';
                $where[':cg_auditStatus'] = $condition['cg_auditStatus'];
            }
        }

        $sql .= ' ORDER BY cg_createDate DESC';
        $s .= $sql;

        $list = app('mysqlbxd_mall_common')->select($s, $where);

        return $list;
    }

    public function getOneCustomGoods($customGoodsId,$customId='')
    {
        $sql = 'SELECT * FROM custom_goods WHERE cg_id=:customGoodsId';
        $sqlParams = [':customGoodsId' => $customGoodsId];
        if (!empty($customId)) {
            $sql .= ' AND c_id=:customId';
            $sqlParams[':customId'] = $customId;
        }

        return app('mysqlbxd_mall_common')->fetch($sql, $sqlParams);
    }

    public function getImages($id, $type)
    {
        $sql = 'SELECT * FROM `custom_image` WHERE c_id=:id AND ci_imageType=:type;';
        $sqlParams = [
            ':id' => $id,
            ':type' => $type
        ];
        return app('mysqlbxd_mall_common')->select($sql, $sqlParams);
    }
}
