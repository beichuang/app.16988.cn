<?php
namespace Model\Mall;

use Lib\Base\BaseModel;

class GoodsComment extends BaseModel
{

    protected $table = 'goods_comment';

    protected $id = 'gc_id';

    /**
     * 新增商品评论
     *
     * @param int $u_id
     * @param int $g_id
     * @param int $o_id
     * @param string $gc_content
     * @param int $gc_pid
     * @param number $gc_likeTimes
     * @param string $gc_title
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($u_id, $g_id, $gc_content, $gc_pid = 0, $gc_likeTimes = 0, $gc_title = '',$o_id=0)
    {
        if (! $u_id  || ! $g_id || ! $gc_content) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $data = array(
            'u_id' => $u_id,
            'g_id' => $g_id,
            'o_id' => $o_id,
            'gc_content' => $gc_content,
            'gc_title' => ($gc_title ? $gc_title : ''),
            'gc_time' => date('Y-m-d H:i:s'),
            'gc_pid' => $gc_pid,
            'gc_likeTimes' => $gc_likeTimes
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 按u_id,o_id,g_id查询评论的条数
     *
     * @param int $o_id
     * @param int $g_id
     * @param int $u_id
     */
    public function rowCountByOidUidGid($o_id, $g_id, $u_id)
    {
        return $this->rowCount("u_id=:u_id and g_id=:g_id and o_id=:o_id",
            array(
                'u_id' => $u_id,
                'g_id' => $g_id,
                'o_id' => $o_id
            ));
    }

    /**
     * 用户id是否和评论Id关联的一致
     *
     * @param int $u_id
     * @param int $gc_id
     * @throws \Exception\ModelException
     * @return boolean
     */
    public function isSameUser($u_id, $gc_id)
    {
        if (! $u_id || ! $gc_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $row = $this->oneById($gc_id);
        if (! $row || ! isset($row['u_id'])) {
            throw new \Exception\ServiceException("没有对应的评论信息");
        }
        if ($row['u_id'] != $u_id) {
            return false;
        }
        return true;
    }

    /**
     * 评论信息
     *
     * @param int $gc_id
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function commentInfo($gc_id)
    {
        $row = $this->oneById($gc_id);
        if (! $row) {
            throw new \Exception\ServiceException("没有对应的评论信息");
        }
        return $row;
    }

    /**
     * 更新评论内容
     *
     * @param int $gc_id
     * @param string $content
     * @param string $gc_title
     * @throws \Exception\ModelException
     * @return number
     */
    public function updateContent($gc_id, $content, $gc_title = '')
    {
        if (! $content || ! $gc_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $row = $this->commentInfo($gc_id);
        $data = array(
            'gc_content' => $content,
            'gc_title' => ($gc_title ? $gc_title : '')
        );
        return $this->update($gc_id, $data);
    }

    /**
     * 给评论点赞，点赞操作应该调用 GoodsCommentLikeLog对应的方法
     *
     * @param int $gc_id
     * @param string $like
     * @throws \Exception\ModelException
     * @return int
     */
    public function commentLike($gc_id, $like = true)
    {
        if (! $gc_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $row = $this->commentInfo($gc_id);
        $likes = $row['gc_likeTimes'];
        if ($like) {
            $likes ++;
        } else {
            $likes --;
            if ($likes < 0) {
                throw new \Exception\ServiceException("已取消点赞");
            }
        }
        $data = array(
            'gc_likeTimes' => $likes
        );
        $this->update($gc_id, $data);
        return $likes;
    }

    /**
     * 删除评论
     */
    public function remove($gc_id)
    {
        if ( !$gc_id ) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }

        return $this->delete($gc_id);
    }

    /**
     * 查询搜索列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function lists($params, $page, $pagesize)
    {
        $whereArr = $bindData = [];
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'gc.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['gc_pid']) && $params['gc_pid'] != '') {
            $whereArr[] = 'gc.gc_pid = :gc_pid';
            $bindData[':gc_pid'] = $params['gc_pid'];
        }
        if (isset($params['g_id']) && $params['g_id'] != '') {
            $whereArr[] = 'gc.g_id = :g_id';
            $bindData[':g_id'] = $params['g_id'];
        }
        if (isset($params['o_id']) && $params['o_id'] != '') {
            $whereArr[] = 'gc.o_id = :o_id';
            $bindData[':o_id'] = $params['o_id'];
        }
        if (isset($params['gcTimeStart']) && $params['gcTimeStart'] != '') {
            $whereArr[] = '`gc`.gc_time >= :gcTimeStart';
            $bindData[':gcTimeStart'] = $params['gcTimeStart'];
        }

        if (isset($params['gcTimeEnd']) && $params['gcTimeEnd'] != '') {
            $whereArr[] = '`gc`.gc_time <= :gcTimeEnd';
            $bindData[':gcTimeEnd'] = $params['gcTimeEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT gc.* FROM `{$this->table}` gc
                $where ORDER BY gc.gc_id DESC ";
        $rows = $this->mysql->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` gc $where";
        $count = $this->mysql->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
