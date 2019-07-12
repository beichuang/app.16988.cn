<?php
namespace Model\Mall;

use Lib\Base\BaseModel;

class GoodsBox extends BaseModel
{

    protected $table = 'goods_box';

    protected $id = 'gb_id';

    /**
     * 添加作品集
     *
     * @param int $u_id
     * @param int $gb_name
     * @throws \Exception\ModelException
     */
    public function add($u_id, $gb_name)
    {
        if (! $u_id || ! $gb_name) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }

        $data = array(
            'u_id' => $u_id,
            'gb_name' => $gb_name,
            'gb_createDate' => date('Y-m-d H:i:s'),
            'gb_updateDate' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);

        return $id;
    }

    /**
     * 删除作品集
     *
     * @param int $u_id
     * @param int $gb_id
     * @throws \Exception\ModelException
     */
    public function remove($u_id, $gb_id)
    {
        if (! $u_id || ! $gb_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }

        $row = $this->oneById($gb_id);
        if (! $row || $row['u_id'] != $u_id) {
            throw new \Exception\ServiceException("已移除");
        }
        return $this->delete($gb_id);
    }


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
                $where ORDER BY gb.gb_id DESC";

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


}
