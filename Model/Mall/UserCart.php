<?php

namespace Model\Mall;

use Lib\Base\BaseModel;

class UserCart extends BaseModel {

    protected $table = 'user_cart';
    protected $id = 'ucart_id';

    /**
     * 向购物车添加商品
     *
     * @param int $u_id
     * @param int $g_id
     * @param string $g_sn
     * @param string $g_name
     * @param string $g_type
     * @param int $ucart_goodsNum
     * @param number $ucart_goodsPrice
     * @param int $ucart_goodsSaleUid
     * @throws \Exception\ModelException
     * @return unknown multitype:
     */
    public function add($u_id, $g_id, $g_sn, $g_name, $g_type, $ucart_goodsNum, $ucart_goodsPrice, $ucart_goodsSaleUid, $ucart_goodsCategoryName, $ucart_goodsMadeTime, $ucart_goodsHigh, $ucart_goodsWidth) {
        if (!$u_id || !$g_id || !$g_name || !$ucart_goodsNum || !isset($ucart_goodsPrice) ||
                !isset($ucart_goodsSaleUid)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $row = $this->oneByUidGid($u_id, $g_id);
        $data = array(
            'u_id' => $u_id,
            'g_id' => $g_id,
            'g_sn' => $g_sn,
            'g_name' => $g_name,
            'g_type' => $g_type,
            'ucart_goodsNum' => $ucart_goodsNum,
            'ucart_goodsPrice' => $ucart_goodsPrice,
            'ucart_joinGoodsPrice' => $ucart_goodsPrice,
            'ucart_goodsSaleUid' => $ucart_goodsSaleUid,
            'ucart_goodsCategoryName' => $ucart_goodsCategoryName,
            'ucart_goodsMadeTime' => $ucart_goodsMadeTime,
            'ucart_goodsHigh' => $ucart_goodsHigh,
            'ucart_goodsWidth' => $ucart_goodsWidth,
        );
        if ($row) {
            $id = $row['ucart_id'];
            $data['ucart_time'] = date("Y-m-d H:i:s");
            // $data['ucart_goodsNum'] += $row['ucart_goodsNum'];
            $this->update($id, $data);
            // $row['ucart_goodsNum'] = $data['ucart_goodsNum'];
            return $row;
        } else {
            list ($count, $id) = $this->insert($data);
            $data['ucart_id']=$id;
            return $data;
        }
    }

    /**
     * 查询购物车商品
     *
     * @param int $u_id
     * @param int $g_id
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function oneByUidGid($u_id, $g_id) {
        if (!$u_id || !$g_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $row = $this->one("u_id=:u_id and g_id=:g_id", array(
            'u_id' => $u_id,
            'g_id' => $g_id
        ));
        return $row;
    }

    /** 检测购物车中信息是否正确
     * @param $u_id
     * @param $c_id
     * @return array
     * @throws \Exception\ParamsInvalidException
     */
    public function checkCart($u_id, $c_id) {
        if (!$u_id || !$c_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $row = $this->one("u_id=:u_id and ucart_id=:ucart_id", array(
            'u_id' => $u_id,
            'ucart_id' => $c_id
        ));
        return $row;
    }

    /**
     * 更新购物车商品数量
     *
     * @param int $ucart_id
     * @param int $u_id
     * @param int $num
     * @throws \Exception\ModelException
     * @return number
     */
    public function updateNum($ucart_id, $u_id, $num) {
        if (!$ucart_id || !$num) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $row = $this->oneById($ucart_id);
        if (!$row || $row['u_id'] != $u_id) {
            throw new \Exception\ServiceException("已从购物车移除");
        }

        if ($num < 1) {
            $this->delete($ucart_id);
            $num = 0;
        } else {
            $this->update($ucart_id, array(
                'ucart_goodsNum' => $num
            ));
        }
        return $num;
    }

    /**
     * 批量删除
     */
    public function batchDelete($data) {
        $sql = "delete from `{$this->table}` where ";
        $where = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $temp = implode(',', $value);
                $where[] = " `$key` in({$temp}) ";
            } else {
                $where[] = " `$key`='{$value}' ";
            }
        }
        if (empty($where)) {
            return false;
        }
        $sql .= implode(' and ', $where);
        return $this->mysql->query($sql);
    }

    /**
     * 根据用户id查询购物车内容
     *
     * @param int $u_id
     * @return array
     */
    public function listsByUid($u_id) {
        $sql = "select * from `{$this->table}` where u_id=:u_id";
        $lists = $this->mysql->select($sql, array(
            'u_id' => $u_id
        ));
        return $lists;
    }

    /**
     * 按id查询购物车内容，适用结算的场景
     *
     * @param array $ucart_ids
     * @param int $u_id
     * @throws \Exception\ModelException
     * @return array
     */
    public function listsByUcartIds($ucart_ids, $u_id) {
        if (!$ucart_ids) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }
        foreach ($ucart_ids as $id) {
            if (!$id || !is_numeric($id)) {
                throw new \Exception\ParamsInvalidException("参数错误");
            }
        }
        $ids = implode(',', $ucart_ids);
        $sql = "select * from `{$this->table}` where {$this->id} in ({$ids}) and u_id=:u_id";
        $lists = $this->mysql->select($sql, array(
            'u_id' => $u_id
        ));
        return $lists;
    }

    /**
     * 查询搜索列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function lists($params, $page, $pagesize) {
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'ucart.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['ucart_goodsSaleUid']) && $params['ucart_goodsSaleUid'] != '') {
            $whereArr[] = 'ucart.ucart_goodsSaleUid = :ucart_goodsSaleUid';
            $bindData[':ucart_goodsSaleUid'] = $params['ucart_goodsSaleUid'];
        }
        if (isset($params['g_id']) && $params['g_id'] != '') {
            $whereArr[] = 'ucart.g_id = :g_id';
            $bindData[':g_id'] = $params['g_id'];
        }
//        //增加商品分类   自营且商城
//        if(isset($params['zysc'])&&$params['zysc']!=''){
//            //1:获取购物车 商品id
//            $where1 = implode(' AND ', $whereArr);
//            $where1 = $this->where($where1);
//            $sql1 = "SELECT ucart.g_id  FROM `{$this->table}` ucart $where1";
//            $g_ids = app('mysqlbxd_app')->fetchColumn($sql1, $bindData);
//            //2:将购物车商品id  过滤为 商城且自营的  商品id
//            $end_g_ids = app('mysqlbxd_mall_user')->fetchColumn("select g_id from goods where ' and g_id in(' . implode(',',
//                $g_ids) . ')')");
//
//            $goods=app('mysqlbxd_mall_user')->fetchColumn("select g_id from goods where g_id in(".implode(',',$ids).") and g_type=1 and g_status=3 and g_stock>0");
//
//
////            $result = app('mysqlbxd_mall_user')->fetchColumn('select g_id from goods where g_id=:id', [
////                ':id' => $id
////            ]);
//        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT ucart.* FROM `{$this->table}` ucart
                $where ORDER BY ucart.ucart_time DESC";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` ucart $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }

    /**
     * 删除某人的购物车中商品
     */
    public function deleteByOrder($params) {
        $sql = "delete from `{$this->table}` where ";

        $whereArr = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'u_id = ' . $params['u_id'];
        }
        if (isset($params['g_id']) && $params['g_id'] != '') {
            $whereArr[] = 'g_id = ' . $params['g_id'];
        }

        if (empty($whereArr)) {
            return false;
        }
        $sql .= implode(' and ', $whereArr);
        return $this->mysql->query($sql);
    }

    /** 根据cid删除购物车数据
     * @param $params
     */
    public function deleteByCid($params) {
        $id = $params['c_id'];
        if (!$id) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        $sql = "delete from `{$this->table}` where ";
        $whereArr = [];
        if (isset($params['c_id']) && $params['c_id'] != '') {
            $whereArr[] = 'ucart_id = ' . $params['c_id'];
        }

        if (empty($whereArr)) {
            return false;
        }
        $sql .= implode(' and ', $whereArr);
        return $this->mysql->query($sql);
    }
    /**
     * 查询购物车商品总数
     */
    public function totalGoodsCount($uid)
    {
        $sql = "SELECT sum(ucart_goodsNum) as ucart_goodsNumTotal FROM `{$this->table}` WHERE `u_id` = :u_id LIMIT 1";

        $result = $this->mysql->fetch($sql, [
            'u_id' => $uid
        ]);
        $count=isset($result['ucart_goodsNumTotal'])?$result['ucart_goodsNumTotal']:0;
        return $count;
    }
}
