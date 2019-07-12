<?php
namespace Model\Mall;

use Lib\Base\BaseModel;

class GoodsLikeLog extends BaseModel
{

    protected $table = 'goods_like_log';

    protected $id = 'gll_id';

    /**
     * 点赞
     *
     * @param int $u_id
     * @param int $g_id
     * @throws \Exception\ModelException
     */
    public function add($u_id, $g_id)
    {
        if (! $u_id || ! $g_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $log = $this->findByUidGcId($u_id, $g_id);
        if ($log) {
            throw new \Exception\ServiceException("已点赞");
        }
        $data = array(
            'u_id' => $u_id,
            'g_id' => $g_id,
            'gll_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 取消点赞
     *
     * @param int $u_id
     * @param int $g_id
     * @throws \Exception\ModelException
     */
    public function remove($u_id, $g_id)
    {
        if (! $u_id || ! $g_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $log = $this->findByUidGcId($u_id, $g_id);
        if (! $log) {
            throw new \Exception\ServiceException("已取消");
        }
        $gll_id = $log['gll_id'];
        return $this->delete($gll_id);

    }

    /**
     * 根据用户id、商品id查询点赞记录
     *
     * @param int $u_id
     * @param int $g_id
     * @return multitype:
     */
    public function findByUidGcId($u_id, $g_id)
    {
        $row = $this->one("g_id=:g_id and u_id=:u_id",
            array(
                'g_id' => $g_id,
                'u_id' => $u_id
            ));
        return $row;
    }




    /**
     * 查询搜索列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function lists($params, $page = 1, $pagesize = 50)
    {
        $whereArr = $bindData = [];

        if (isset($params['g_id']) && $params['g_id'] != '') {
            $whereArr[] = 'gc.g_id = :g_id';
            $bindData[':g_id'] = $params['g_id'];
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT gc.* FROM `{$this->table}` gc
                $where ORDER BY gc.gll_id DESC ";
        $rows = $this->mysql->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` gc $where";
        $count = $this->mysql->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }



}
