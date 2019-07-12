<?php
/**
 * 商品评论
 * @author Administrator
 *
 */

namespace Controller\Mall\Goods;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;
use Lib\Common\SessionKeys;

use Lib\Common\QueueManager;

class Comment extends BaseController
{

    private $goodsCommentModel = null;

    private $goodsCommentLikeLogModel = null;

    private $goodsLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsCommentModel = new \Model\Mall\GoodsComment();
        $this->goodsCommentLikeLogModel = new \Model\Mall\GoodsCommentLikeLog();
        $this->goodsLib = new \Lib\Mall\Goods();
    }

    /**
     * 新增评论,针对商品的评论
     *
     * @throws ModelException
     */
    public function add()
    {
        $this->checkUserStatus($this->uid);
        $g_id = app()->request()->params('goodsId');
        $content = app()->request()->params('content');
        $gc_pid = app()->request()->params('goodsCommentPid', 0);
        if (!$g_id) {
            throw new ParamsInvalidException("商品Id必须");
        }
        if (!$content) {
            throw new ParamsInvalidException("评论内容必须");
        }
        $check_content = filter_words(cutstr_html($content));
        if ($check_content) {
            throw new ParamsInvalidException("评论内容包含敏感词");
        }
        $uid = $this->uid;
        if ($gc_pid) {
            $pCommentRow = $this->goodsCommentModel->oneById($gc_pid);
            if (!$pCommentRow) {
                throw new ServiceException("引用的评论不存在");
            }
        }
        $itemInfo = $this->goodsLib->getGoodsInfo([
            'id' => $g_id
        ]);

        if (empty($itemInfo)) {
            throw new ServiceException("商品不存在");
        } else {
            //非回复其他商品评论时，商品发布者不能对商品进行主动评论
            if (!$gc_pid) {
                $goodsCreator = $itemInfo['g_salesId'];
                if ($this->uid == $goodsCreator) {
                    throw new ServiceException("不能对自己发布的商品进行主动评论");
                }
            }
        }

        $this->goodsCommentModel->beginTransaction();
        try {
            $id = $this->goodsCommentModel->add($uid, $g_id, $content, $gc_pid);
            if (!$id) {
                throw new ModelException("保存评论失败");
            }
            $this->goodsCommentModel->commit();
        } catch (\Exception $e) {
            $this->goodsCommentModel->rollback();
            throw $e;
        }
        //商品评论加积分
        (new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_GOODS_COMMENT_ADD);

        $queurType = $gc_pid ? 2 : 1;
        $queueAppid = config('app.queue_common_params.appid');
        QueueManager::queue($id, $queurType, '', $queueAppid);

        $this->responseJSON(array(
            'goods_comment_id' => $id
        ));
    }

    /**
     * 点赞
     *
     * @throws ModelException
     */
    public function like()
    {
        $gc_id = app()->request()->params('gc_id');
        if (!$gc_id) {
            throw new ParamsInvalidException("缺少参数");
        }
        $u_id = $this->uid;
        $commentInfo = $this->goodsCommentModel->oneById($gc_id);
        if (!$commentInfo) {
            throw new ServiceException("评论已失效");
        }
        $this->goodsCommentLikeLogModel->beginTransaction();
        try {
            $this->goodsCommentLikeLogModel->add($u_id, $gc_id);
            // $this->goodsCommentModel->commentLike($gc_id);
            $this->goodsCommentLikeLogModel->commit();
        } catch (\Exception $e) {
            $this->goodsCommentLikeLogModel->rollback();
            throw $e;
        }
        $this->responseJSON(true);
    }

    /**
     * 取消点赞
     *
     * @throws ModelException
     */
    public function unlike()
    {
        $gc_id = app()->request()->params('gc_id');
        if (!$gc_id) {
            throw new ParamsInvalidException("缺少参数");
        }
        $u_id = $this->uid;
        $commentInfo = $this->goodsCommentModel->oneById($gc_id);
        if (!$commentInfo) {
            throw new ServiceException("评论已失效");
        }
        $this->goodsCommentLikeLogModel->beginTransaction();
        try {
            $this->goodsCommentLikeLogModel->remove($u_id, $gc_id);
            // $this->goodsCommentModel->commentLike($gc_id, false);
            $this->goodsCommentLikeLogModel->commit();
        } catch (\Exception $e) {
            $this->goodsCommentLikeLogModel->rollback();
            throw $e;
        }
        $this->responseJSON(true);
    }

    /**
     * 评论删除
     *
     * @throws ServiceException
     */
    public function delete()
    {
        $gc_id = app()->request()->params('gc_id');
        if (!$gc_id) {
            throw new ParamsInvalidException("缺少参数");
        }
        $commentInfo = $this->goodsCommentModel->oneById($gc_id);
        if (!$commentInfo) {
            throw new ParamsInvalidException("数据错误");
        }
        if ($this->uid != $commentInfo['u_id']) {
            throw new ServiceException("无删除权限");
        }
        $this->goodsCommentModel->beginTransaction();
        try {
            $this->goodsCommentModel->remove($gc_id);
            $this->goodsCommentModel->commit();
        } catch (\Exception $e) {
            $this->goodsCommentModel->rollback();
            throw $e;
        }

        $this->responseJSON(true);
    }

    /**
     * 分页查询
     *
     * @throws ModelException
     */
    public function lists()
    {
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        $params = array();
        $params['u_id'] = app()->request()->params('gc_uid', '');
        $params['gc_pid'] = app()->request()->params('gc_pid', '');
        $params['g_id'] = app()->request()->params('g_id', '');
        $params['o_id'] = app()->request()->params('o_id', '');

        list ($rows, $totalCount) = $this->goodsCommentModel->lists($params, $page, $pageSize);
        if ($rows && is_array($rows) && !empty($rows)) {
            foreach ($rows as &$row) {
                $row['isCurrentUserLiked'] = 0;
                $row['canDelete'] = 0;
                if ($row['u_id'] == $this->uid) {
                    $row['canDelete'] = 1;
                    if ($this->goodsCommentLikeLogModel->findByUidGcId($row['u_id'], $row['gc_id'])) {
                        $row['isCurrentUserLiked'] = 1;
                    }
                }
                $time = strtotime($row['gc_time']);
                $row['displayTime'] = date_format_to_display($time);
            }
            $userLib = new \Lib\User\User();
            $rows = $userLib->extendUserInfos2Array($rows, 'u_id',
                array(
                    'u_nickname' => 'u_nickname',
                    'u_realname' => 'u_realname',
                    'u_avatar' => 'u_avatar',
                    'u_provinceCode' => 'u_provinceCode',
                    'u_cityCode' => 'u_cityCode',
                    'u_areaCode' => 'u_areaCode'
                ));
            foreach ($rows as &$row) {
                $row['u_provinceName'] = \Lib\Common\Region::getRegionNameByCode($row['u_provinceCode']);
                $row['u_cityName'] = \Lib\Common\Region::getRegionNameByCode($row['u_cityCode']);
                $row['u_areaName'] = \Lib\Common\Region::getRegionNameByCode($row['u_areaCode']);
            }
        }
        $this->responseJSON(array(
            'rows' => $rows,
            'totalCount' => $totalCount
        ));
    }

    /**
     * @Summary :获取评价个数
     * @Author yyb update at 2018/6/26 11:44
     */
    public function getGoodComment()
    {
        //获取商品的评论数大于3条的商品id
        $goodsCommentSql = "SELECT g_id FROM `goods_comment` GROUP BY g_id HAVING count(gc_id)>=3";
        $goodsCommentList = app('mysqlbxd_app')->select("{$goodsCommentSql}");
        $goodsCommentIdList = array_column($goodsCommentList,'g_id');

        //获取待评论的商品id
        $sql = 'select * from goods where (g_status = 3 or g_status=4) AND NOT FIND_IN_SET(g_id,:goodsIds)';
        $goodsList = app('mysqlbxd_mall_user')->select($sql, [':goodsIds' => implode(',', $goodsCommentIdList)]);
        $goodsIdList = array_column($goodsList, 'g_id');
        $goodsIdStr = implode(',', $goodsIdList);

        //组装待评论的商品评论数据（已评论的用户id集合，已评论的伪评论模板id集合）
        $goodsCommentData = [];
        $goodsCommentList = app('mysqlbxd_app')->select('SELECT * FROM `goods_comment` WHERE FIND_IN_SET(g_id,:goodsIds)', [':goodsIds' => $goodsIdStr]);
        foreach ($goodsCommentList as $goodsCommentItem) {
            if ($goodsCommentItem['gcf_id']) {
                if (isset($goodsCommentData[$goodsCommentItem['g_id']])) {
                    $goodsCommentData[$goodsCommentItem['g_id']]['userIds'][] = $goodsCommentItem['u_id'];
                    $goodsCommentData[$goodsCommentItem['g_id']]['gcfIds'][] = $goodsCommentItem['gcf_id'];
                } else {
                    $goodsCommentData[$goodsCommentItem['g_id']] = [
                        'userIds' => [$goodsCommentItem['u_id']],
                        'gcfIds' => [$goodsCommentItem['gcf_id']]
                    ];
                }
            }
        }

        //为目标商品添加评论
        foreach ($goodsList as $goodsItem) {
            //获取所有伪用户
            $forgeryUser = $this->forgeryUser();
            //获取该商品类别下的所有伪评论模板
            $commentsAll = $this->getCommentsByCategory($goodsItem['g_categoryId']);
            if(isset($goodsCommentData[$goodsItem['g_id']])) {
                //去除已对该商品评论的用户
                $forgeryUser = array_diff($forgeryUser, $goodsCommentData[$goodsItem['g_id']]['userIds']);
                $forgeryUser = array_values($forgeryUser);
                //去除现有的伪评论模板
                $gcfIds = $goodsCommentData[$goodsItem['g_id']]['gcfIds'];
                foreach ($gcfIds as $gcfId) {
                    array_splice($commentsAll, $gcfId, 1);
                }
            }

            //为这个商品添加评论，使用去除后的用户的列表，评论列表
            $comNum = rand(1, 3);
            for ($i = 0; $i < $comNum; $i++) {
                if (count($forgeryUser) >= 1 && count($commentsAll) >= 1) {
                    //选取用户
                    $userKey = rand(0, count($forgeryUser) - 1);
                    $forgeryUserNow = $forgeryUser[$userKey];
                    array_splice($forgeryUser, $userKey, 1);//删除

                    //选取评论
                    $comArr = array_values($commentsAll);
                    $comKey = rand(0, count($comArr) - 1);
                    $forgeryComNow = $comArr[$comKey];
                    array_splice($commentsAll, $comKey, 1);//删除

                    //添加评论
                    $comAdd['gc_pid'] = 0;
                    $comAdd['u_id'] = $forgeryUserNow;
                    $comAdd['g_id'] = $goodsItem['g_id'];
                    $comAdd['gc_content'] = $forgeryComNow['gcf_content'];
                    $comAdd['gc_title'] = '';
                    $comAdd['o_id'] = 0;
                    $comAdd['gcf_id'] = $forgeryComNow['gcf_id'];
                    $comAdd['gc_time'] = date('Y-m-d H:i:s');
                    app('mysqlbxd_app')->insert('goods_comment', $comAdd);
                }
            }
        }

        $this->responseJSON(['ok']);
    }

    //内部员工uid
    public function forgeryUser()
    {
        $userStr = "627338172,926962363,731971113,525294154,346344080,326692375,833253571,431060962,379610486,415449662,717277753,736586597,431952458,626014261,491547310";
        return explode(',', $userStr);
    }

    // 获取指定类别的评论
    //"1741": {
    //"gcf_id": "1741",
    //"gcf_content": "感觉还挺大气秀美的，屋里放上它应该会更加有气场吧！",
    //"gc_category": null,
    //"gc_category_parentId": "11",
    //"gcf_type": "1"
    //},

    public function getCommentsByCategory($category)
    {
        //$category = app()->request()->params('category', 11);
        $commentForgeryInfoSql = "select * from goods_comment_forgery where gc_category =:categoryId";
        $commentForgeryInfo = app('mysqlbxd_app')->select($commentForgeryInfoSql,[':categoryId'=>$category]);

        if (empty($commentForgeryInfo)) {
            $getCategoryIdSql = "SELECT c_parentId FROM category WHERE c_id=:categoryId";
            $category = app('mysqlbxd_mall_common')->fetchColumn($getCategoryIdSql, [':categoryId' => $category]);
            if ($category) {
                $commentForgeryInfo = app('mysqlbxd_app')->select($commentForgeryInfoSql,[':categoryId'=>$category]);
            }
        }

        if (count($commentForgeryInfo) < 1) {
            return [];
        }

        //组合数据结构
        foreach ($commentForgeryInfo as $item) {
            $commentForgeryInfoNew[$item['gcf_id']] = $item;
        }

        return $commentForgeryInfoNew;
    }
}
