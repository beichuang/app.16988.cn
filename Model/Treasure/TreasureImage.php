<?php
namespace Model\Treasure;

use Framework\Helper\FileHelper;
use Lib\Base\BaseModel;

class TreasureImage extends BaseModel
{

    protected $table = 'treasure_image';

    protected $id = 'ti_id';

    /**
     * 新增评论
     *
     * @param int $t_id
     * @param string $ti_image
     * @param number $ti_sort
     * @param number $ti_weight
     * @param number $ti_height
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($t_id, $ti_img, $ti_sort, $ti_width, $ti_height)
    {
        if (! $t_id || ! $ti_img || ! $ti_width || ! $ti_height) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        $data = array(
            't_id' => $t_id,
            'ti_img' => $ti_img,
            'ti_width' => $ti_width,
            'ti_height' => $ti_height,
            'ti_sort' => $ti_sort
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 删除评论
     *
     * @param int $u_id
     * @param int $t_id
     * @throws \Exception\ModelException
     * @return Ambigous <number, \Framework\Route, \Framework\Route>
     */
    public function treasureCommentRemove($u_id, $t_id)
    {
        $row = $this->treasureCommentInfo($u_id, $t_id);
        if (! $row) {
            throw new \Exception\ServiceException("已删除");
        }

        $id = $row['tc_id'];
        return $this->delete($id);
    }

    /**
     * 根据用户id、获取到评论的信息
     *
     * @param int $u_id
     * @param int $t_id
     * @return multitype:
     */
    public function treasureCommentInfo($u_id, $t_id)
    {
        $row = $this->one("t_id =:t_id and u_id=:u_id",
            array(
                't_id' => $t_id,
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
    public function lists($params, $page, $pagesize, $tidorder=0)
    {
        // tc_pid,t_id,u_id,tc_title,tc_content,tc_time
        $whereArr = $bindData = [];
        if (isset($params['ti_id']) && $params['ti_id'] != '') {
            $whereArr[] = 'tc.ti_id = :ti_id';
            $bindData[':ti_id'] = $params['ti_id'];
        }
        if (isset($params['t_id']) && $params['t_id'] != '') {
            $whereArr[] = 'tc.t_id = :t_id';
            $bindData[':t_id'] = $params['t_id'];
        }
        if (isset($params['ti_img']) && $params['ti_img'] != '') {
            $whereArr[] = 'tc.ti_img = :ti_img';
            $bindData[':ti_img'] = $params['ti_img'];
        }
        if (isset($params['ti_width']) && $params['ti_width'] != '') {
            $whereArr[] = 'tc.ti_width = :ti_width';
            $bindData[':ti_width'] = $params['ti_width'];
        }
        if (isset($params['ti_height']) && $params['ti_height'] != '') {
            $whereArr[] = 'tc.ti_height = :ti_height';
            $bindData[':ti_height'] = $params['ti_height'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $orderType = $tidorder == 0 ? "" : " DESC ";
        $sql = "SELECT tc.* FROM `{$this->table}` tc
                $where ORDER BY tc.ti_id $orderType ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        if ($rows) {
            foreach ($rows as &$row) {
                $row['ti_img'] = $this->parsePicsForUrlVisit($row['ti_img']);
            }
        }
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` tc $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }

    /**
     * 将图片处理成适合url访问的图片链接
     *
     * @param string $picsStr
     * @return array
     */
    private function parsePicsForUrlVisit($picsStr)
    {
        $picsProcessed = FileHelper::getFileUrl($picsStr, 'treasure');
        return $picsProcessed;
    }
}
