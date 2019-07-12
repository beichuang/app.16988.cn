<?php
/**
 * 作品集
 * @author Administrator
 *
 */
namespace Controller\Mall\Goods;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Box extends BaseController
{

    private $goodsBoxModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsBoxModel = new \Model\Mall\GoodsBox();
    }

    /**
     * 新增作品集
     *
     * @throws ModelException
     */
    public function add()
    {
        $gb_name = app()->request()->params('gb_name');
        if (! $gb_name) {
            throw new ParamsInvalidException("缺少参数");
        }
        $u_id = $this->uid;

        $resMall = $this->goodsBoxModel->add($u_id, $gb_name);
        $this->responseJSON($resMall);
    }

    /**
     * 删除作品集
     *
     * @throws ModelException
     */
    public function remove()
    {
        $gb_id = app()->request()->params('gb_id');
        if (! $gb_id) {
            throw new ParamsInvalidException("缺少参数");
        }
        $u_id = $this->uid;
        $resMall = $this->goodsBoxModel->remove($u_id, $gb_id);

        $goodsLib = new \Lib\Mall\Goods();
        $data = array(
                "g_goodsBox" => -1,
                "where_g_goodsBox" => $gb_id,
                "where_g_salesId" => $u_id,
            );

        $ret = $goodsLib->updateGoodsBox($data);

        $this->responseJSON($resMall);
    }

    /**
     * 查询作品集
     */
    public function lists()
    {
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 20);
        $u_id = app()->request()->params('u_id', $this->uid);
        if (!$u_id) {
            throw new ParamsInvalidException("缺少参数");
        }
        $params = array();
        $params['u_id'] = $u_id;
        $params['gb_id'] = app()->request()->params('gb_id', '');
        $resMall = $this->goodsBoxModel->lists($params,$page,$pageSize);
        $this->responseJSON($resMall);
    }
}
