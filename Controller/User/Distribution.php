<?php

/**
 * 分销
 * @author Administrator
 *
 */

namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;

class Distribution extends BaseController {

    private $goodsLikeLogModel= null;
    public function __construct() {
        parent::__construct();
        $this->goodsLikeLogModel = new \Model\Mall\GoodsLikeLog();
    }

    /**
     * 获奖列表
     *
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function lists() {
        $params = app()->request()->params();
        $params['page'] = app()->request()->params('page',1);
        $params['pageSize'] = app()->request()->params('pageSize',10);
        $params['status'] = app()->request()->params('status',0);

        $uid = app()->request()->params('uid', $this->uid);
        if (empty($uid)) {
            throw new ParamsInvalidException("用户uid必须");
        }
        $params['uid'] = $uid;

        $user_lib = new \Lib\User\User();
        $goods_lib = new \Lib\Mall\Goods();

        $params['action'] = 'goodsIds';
        $result=[];
        if($g_ids = $user_lib->distribution($params)){
            $data['id'] = implode(',',$g_ids);
            $data['status'] = 3;
            $data['isHaveStock'] = 1;
            $data['page'] = $params['page'];
            $data['pageSize'] = $params['pageSize'];

            if($params['price']){
                $data['sortPrice'] = $params['price'];
            }
            if($params['likeCount']){
                $data['sortLike'] = $params['likeCount'];
            }
            if($params['browseTimes']){
                $data['browseTimes'] = $params['browseTimes'];
            }
            $goodsRet = $goods_lib->itemQuery($data);
            if($goodsRet['list']){
                foreach ($goodsRet['list'] as $row){
                    $isLike = $this->goodsLikeLogModel->findByUidGcId($uid, $row['g_id']);
                    $row['itemCurrentUserLikeInfo'] = empty($isLike) ? null : $isLike;
                    if($this->clientType==self::CLIENT_TYPE_ANDROID && $row['isSecKill']){
                        $tmpActivityPrice=$row['g_activityPrice'];
                        $row['g_activityPrice']=$row['g_price'];
                        $row['g_price']=$tmpActivityPrice;
                    }
                    $result[] = $row;
                }
            }
        }

        $this->responseJSON($result);
    }


    /**
     * 分销到我的店铺
     *
     * @throws ServiceException
     */
    public function post() {
        $params = app()->request()->params();

        if (empty($params['gid'])) {
            throw new ParamsInvalidException("商品ID必须");
        }

        $goods_lib = new \Lib\Mall\Goods();
        $data['status'] = 3;
        $data['isHaveStock'] = 1;
        $data['id'] = $params['gid'];
        $goodsRet = $goods_lib->itemQuery($data);
        if ( !isset($goodsRet['list']) || empty($goodsRet['list']) ) {
            throw new ParamsInvalidException("商品不存在");
        }
        $goodsInfo = current($goodsRet['list']);
        $sales_id = $goodsInfo['g_salesId'];
        if($sales_id == $this->uid){
            throw new ParamsInvalidException("不能分销自己的商品");
        }
        if($goodsInfo['is_own_shop'] != 1){
            throw new ParamsInvalidException("该商品不允许添加到‘我的店铺’");
        }
        $params['uid'] = $this->uid;

        $user_lib = new \Lib\User\User();
        $params['action'] = 'query';
        $resMall = $user_lib->distribution($params);
        if ($resMall['list']){
            throw new ParamsInvalidException("请勿重复上架");
        }

        $params['action'] = 'insert';
        $resMall2 = $user_lib->distribution($params);

        $this->responseJSON($resMall2);
    }

    /**
     * 商品管理(商品上下架)
     */
    public function goodsManage(){
        $params = app()->request()->params();
        $params['uid'] = $this->uid;

        $user_lib = new \Lib\User\User();

        $params['action'] = 'update';
        $resMall = $user_lib->distribution($params);

        $this->responseJSON($resMall);
    }

    /**
     * 一键归入历史
     */
    public function history(){
        $uid = $this->uid;
        if (empty($uid)) {
            throw new ParamsInvalidException("未登录");
        }
        $user_lib = new \Lib\User\User();

        $params['uid'] = $uid;
        $resMall = $user_lib->distribution($params);
        if($resMall['list']){
            $goods_lib = new \Lib\Mall\Goods();
            $gids = [];
            foreach($resMall['list'] as $val){
                $data['status'] = 3;
                $data['isHaveStock'] = 1;
                $data['id'] = $val['g_id'];
                $goodsRet = $goods_lib->itemQuery($data);
                if ( !isset($goodsRet['list']) || empty($goodsRet['list']) ) {
                    $gids[] = $val['g_id'];
                }
            }
        }

        if($gids){
            $params['status'] = 2;
            $params['gid'] = implode(',',$gids);
            $params['action'] = 'update';
            $resMall = $user_lib->distribution($params);
        }

        $this->responseJSON(true);
    }
}
