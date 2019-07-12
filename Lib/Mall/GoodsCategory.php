<?php
namespace Lib\Mall;

class GoodsCategory extends MallBase
{
    //显示商品参数分类id集合：
    //  一级类目：国画11、书法20、油画31
    //  二级类目：折扇75、文房四宝77、版画80、水粉水彩81、意大利艺术品83、朝鲜艺术84、俄罗斯油画85、铁画99、剪纸90、烙画91、木版年画92、麦秆画95
    const SHOW_GOODS_ATTR_IDS = [11, 20, 31, 75, 77, 80, 81, 83, 84, 85, 99, 90, 91, 92, 95];

    /**
     * 新增或修改拍品分类信息
     */
    public function post($params)
    {
        return $this->passRequest2Mall($params, 'mall/category/post');
    }

    /**
     * 拍品分类信息列表
     */
    public function lists($params)
    {
        return $this->passRequest2Mall($params, 'mall/category/list');
    }
    /**
     * 获取喜好列表
     * @param unknown $parentId
     */
    public function getHobbyCategoryList($params)
    {
        return $this->passRequest2Mall($params, 'mall/hobby/category/list');
    }

    public function getCategories($params)
    {
        $list = $this->getList($params);
        if ($list) {
            $ids = array_column($list, 'c_id');
            $childrenList = $this->getListByParentId(['parentIds' => $ids]);
            if ($childrenList) {
                foreach ($list as &$item) {
                    foreach ($childrenList as $childrenItem) {
                        if ($childrenItem['c_parentId'] == $item['c_id']) {
                            $item['children'][] = $childrenItem;
                        }
                    }
                }
            }
        }

        return $list;
    }

    public function getOne($id)
    {
        $sql = 'select * from category where c_isDel=0 AND c_id = :id';
        $sqlParams = [':id' => $id];
        return app('mysqlbxd_mall_common')->fetch($sql, $sqlParams);
    }

    private function getList($params)
    {
        $sqlParams = [];
        $sql = 'select * from category where c_isDel=0 AND c_isShow=1';
        if (isset($params['ids'])) {
            if (is_array($params['ids'])) {
                $sql .= ' AND FIND_IN_SET(c_id,:ids)';
                $sqlParams[':ids'] = implode(',', $params['ids']);
            } else {
                $sql .= ' AND c_id = :ids';
                $sqlParams[':ids'] = $params['ids'];
            }
        }
        if (isset($params['parentIds'])) {
            if (is_array($params['parentIds'])) {
                $sql .= ' AND FIND_IN_SET(c_parentId,:parentIds)';
                $sqlParams[':parentIds'] = implode(',', $params['parentIds']);
            } else {
                $sql .= ' AND c_parentId = :parentIds';
                $sqlParams[':parentIds'] = $params['parentIds'];
            }
        }

        $sql .= ' order by c_sort desc';
        return app('mysqlbxd_mall_common')->select($sql, $sqlParams);
    }

    public function getListByParentId($params)
    {
        $sqlParams = [];
        $sql = 'select * from category where c_isDel=0 AND c_isShow=1';
        if (isset($params['parentIds'])) {
            if (is_array($params['parentIds'])) {
                $sql .= ' AND FIND_IN_SET(c_parentId,:parentIds)';
                $sqlParams[':parentIds'] = implode(',', $params['parentIds']);
            } else {
                $sql .= ' AND c_parentId = :parentIds';
                $sqlParams[':parentIds'] = $params['parentIds'];
            }
        }

        $sql .= ' order by c_sort desc';
        return app('mysqlbxd_mall_common')->select($sql, $sqlParams);
    }
}
