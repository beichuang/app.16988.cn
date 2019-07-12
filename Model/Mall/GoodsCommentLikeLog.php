<?php
namespace Model\Mall;

use Lib\Base\BaseModel;
use Model\Mall\GoodsComment;

class GoodsCommentLikeLog extends BaseModel
{

    protected $table = 'goods_comment_like_log';

    protected $id = 'gcll_id';

    /**
     * 点赞
     *
     * @param int $u_id
     * @param int $gc_id
     * @throws \Exception\ModelException
     */
    public function add($u_id, $gc_id)
    {
        if (! $u_id || ! $gc_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $log = $this->findByUidGcId($u_id, $gc_id);
        if ($log) {
            throw new \Exception\ServiceException("已点赞");
        }
        $data = array(
            'u_id' => $u_id,
            'gc_id' => $gc_id,
            'gcll_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        $comment = new GoodsComment();
        return $comment->commentLike($gc_id);
    }

    /**
     * 取消点赞
     *
     * @param int $u_id
     * @param int $gc_id
     * @throws \Exception\ModelException
     */
    public function remove($u_id, $gc_id)
    {
        if (! $u_id || ! $gc_id) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $log = $this->findByUidGcId($u_id, $gc_id);
        if (! $log) {
            throw new \Exception\ServiceException("已取消");
        }
        $gcll_id = $log['gcll_id'];
        $this->delete($gcll_id);
        $comment = new GoodsComment();
        return $comment->commentLike($gc_id, false);
    }

    /**
     * 根据用户id、评论id查询点赞记录
     *
     * @param int $u_id
     * @param int $gc_id
     * @return multitype:
     */
    public function findByUidGcId($u_id, $gc_id)
    {
        $row = $this->one("gc_id=:gc_id and u_id=:u_id",
            array(
                'gc_id' => $gc_id,
                'u_id' => $u_id
            ));
        return $row;
    }
}
