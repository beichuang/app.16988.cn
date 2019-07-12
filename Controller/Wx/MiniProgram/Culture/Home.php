<?php
/**
 * 掌玩文化首页接口
 */

namespace Controller\Wx\MiniProgram\Culture;


use Framework\Helper\WxHelper;
use Lib\Base\BaseController;

class Home extends BaseController
{
    private $goodsLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsLib = new \Lib\Mall\Goods();
    }

    /**
     * 首页商品列表，搜索，筛选
     */
    public function goods()
    {
        $params=app()->request()->params();
        $params['isHaveStock']=1;
        $params['status']=3;
        $resMall = $this->goodsLib->itemQuery($params);
        $this->responseJSON($resMall);
    }

}