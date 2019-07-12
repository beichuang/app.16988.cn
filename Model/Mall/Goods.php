<?php
namespace Model\Mall;

use Lib\Base\BaseModel;

class Goods extends BaseModel
{

    protected $table = 'goods';

    protected $id = 'g_id';

    /**
     * 查询搜索列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function lists($params, $page = 1, $pagesize = 20)
    {

        if (! isset($params['u_id']) || empty($params['u_id'])) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }

        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'gb.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }

        if (isset($params['gb_id']) && $params['gb_id'] != '') {
            $whereArr[] = 'gb.gb_id = :gb_id';
            $bindData[':gb_id'] = $params['gb_id'];
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT gb.* FROM `{$this->table}` gb
                $where ORDER BY gb.gb_id DESC ";
        $rows = $this->mysql->selectPage($sql,$page,$pagesize, $bindData);

        // $countSql = "SELECT COUNT(*) FROM `{$this->table}` gb $where";
        // $count = $this->mysql->fetchColumn($countSql, $bindData);

        $default[] = [
                    'gb_id' => '-1',
                    'u_id' => $params['u_id'],
                    'gb_name' => '默认作品集',
                    'gb_createDate' => '2017-07-05 12:13:35',
                    'gb_updateDate' => '2017-07-05 12:13:35',
                    ];
        if ($rows) {
            return array_merge($rows, $default);
        }

        return $default;


    }

    /** 获得该用户所有商品的浏览量
     * @param $uid
     */
    public function getBrowseNum($uid){
        $sql = "select sum(g_browseTimes) from `{$this->table}` where g_salesId= ".$uid;
        $num = app('mysqlbxd_mall_user')->fetchColumn($sql);
        return empty($num) ? 0 : $num;
    }

    /** 获得该用户所有商品的点赞量
     * @param $uid
     */
    public function getLikeNum($uid){
        $sql = "select sum(g_likeCount) from `{$this->table}` where g_salesId= ".$uid;
        $num = app('mysqlbxd_mall_user')->fetchColumn($sql);
        return empty($num) ? 0 : $num;
    }

    /**
     * 查询搜索列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function listsNew($params, $page = 1, $pagesize = 20)
    {
        $whereArr = $bindData = [];
        if (isset($params['g_categoryId']) && $params['g_categoryId'] != '') {
            $whereArr[] = 'g.g_categoryId = :g_categoryId';
            $bindData[':g_categoryId'] = $params['g_categoryId'];
        }

        if (isset($params['g_status']) && $params['g_status'] != '') {
            $whereArr[] = 'g.g_status = :g_status';
            $bindData[':g_status'] = $params['g_status'];
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT g.* FROM `{$this->table}` g ".$where." GROUP by g.g_salesId ORDER BY g.g_id  DESC ";
        $rows = app('mysqlbxd_mall_user')->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT * FROM `{$this->table}` g ".$where." group by g.g_salesId ORDER BY g.g_id  DESC ";
        $count = app('mysqlbxd_mall_user')->select($countSql, $bindData);

        return [
            $rows,
            count($count)
        ];
    }

    public static function getIsExists($id)
    {
        $result = app('mysqlbxd_mall_user')->fetchColumn('select g_id from goods where g_id=:id', [
            ':id' => $id
        ]);

        return $result ? true : false;
    }

    public static function detailGet($id)
    {
        $result = app('mysqlbxd_mall_user')->fetch('select * from goods where g_id=:id', [
            ':id' => $id
        ]);
        return $result;
    }

    public function getGoods($params, $page, $pageSize){
        $whereArr = $bindData = [];
        if (isset($params['isHaveStock']) && $params['isHaveStock'] != '') {
            $whereArr[] = 'g_stock > :g_stock';
            $bindData[':g_stock'] = 0;
        }

        if (isset($params['status']) && $params['status'] != '') {
            $whereArr[] = 'g_status = :g_status';
            $bindData[':g_status'] = $params['status'];
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT * FROM `{$this->table}` ".$where." ORDER BY g_id  DESC ";
        $rows = app('mysqlbxd_mall_user')->selectPage($sql,$page,$pageSize, $bindData);

        $countSql = "SELECT * FROM `{$this->table}` ".$where." ORDER BY g_id  DESC ";
        $count = app('mysqlbxd_mall_user')->select($countSql, $bindData);

        return [
            $rows,
            count($count)
        ];
    }

    public function getList($condition, $pageIndex, $pageSize)
    {
        if(intval($pageSize)>100){
            throw new ParamsInvalidException('pageSize过大');
        }
        $listSql = 'select *,CASE WHEN `g_secKillStart` <= NOW() AND `g_secKillEnd` >= NOW() THEN 1 ELSE 0 END AS isSecKill from goods where g_status = 3 ';
        $countSql = 'select count(*) as count from goods where g_status = 3 ';
        $whereSql = '';
        $orderBySql = ' ORDER BY';
        $params = [];

        if (!empty($condition['g_categoryId'])) {
            if (is_array($condition['g_categoryId'])) {
                $whereSql .= ' and FIND_IN_SET(g_categoryId,:g_categoryId)';
                $params[':g_categoryId'] = implode(',', $condition['g_categoryId']);
            } else {
                $whereSql .= ' and g_categoryId = :g_categoryId';
                $params[':g_categoryId'] = $condition['g_categoryId'];
            }
        }

        if (isset($condition['inStock'])) {
            $whereSql .= ' and g_stock > 0';
        }
        if (isset($condition['isOwnShop'])) {
            $whereSql .= ' and is_own_shop = :is_own_shop';
            $params[':is_own_shop'] = $condition['isOwnShop'];
        }
        if (isset($condition['mallGoodsStatus'])) {
            $whereSql .= ' and g_mall_goods_status = :g_mall_goods_status';
            $params[':g_mall_goods_status'] = $condition['mallGoodsStatus'];
        }
        if (isset($condition['g_distribution_status'])) {
            $whereSql .= ' and g_distribution_status = :g_distribution_status';
            $params[':g_distribution_status'] = $condition['g_distribution_status'];
        }
        if(isset($condition['goodsIds'])) {
            $whereSql .= ' and FIND_IN_SET(g_id,:goodsIds)';
            $params[':goodsIds'] = implode(',', $condition['goodsIds']);
        }
        if(isset($condition['excludeOnSaleGoodsIds'])) {
            $whereSql .= ' and NOT (FIND_IN_SET(g_id,:excludeOnSaleGoodsIds) AND g_stock>0)';
            $params[':excludeOnSaleGoodsIds'] = implode(',', $condition['excludeOnSaleGoodsIds']);
        }

        if (isset($condition['orderBy'])) {
            foreach ($condition['orderBy'] as $i => $item) {
                if ($i == 0) {
                    $orderBySql .= ' ' . $item[0] . ' ' . $item[1];
                } else {
                    $orderBySql .= ',' . $item[0] . ' ' . $item[1];
                }
            }
        } else {
            $orderBySql .= ' g_onShowDate DESC, g_id DESC';
        }

        $listSql = $listSql . $whereSql . $orderBySql . ' limit ' . $pageIndex * $pageSize . ',' . $pageSize;
        $countSql = $countSql . $whereSql;
        $list = app('mysqlbxd_mall_user')->select($listSql, $params);
        $count = app('mysqlbxd_mall_user')->fetchColumn($countSql, $params);

        return [$list, $count];
    }

    public function getImagesById($id)
    {
        $s = 'select * from goods_image where g_id=:id order by gi_sort';

        return app('mysqlbxd_mall_common')->select($s, [
            ':id' => $id
        ]);
    }
}
