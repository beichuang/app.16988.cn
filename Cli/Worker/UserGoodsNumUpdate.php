<?php

/**
 * 修改用户的商品数量
 */
namespace Cli\Worker;


use Lib\Mall\Goods;
use Lib\User\User;

class UserGoodsNumUpdate
{
    private $goodsLib = null;
    public function __construct()
    {
        $this->goodsLib = new Goods();
        $this->userLib = new User(false);
        $this->goodsModel = new \Model\Mall\Goods();
    }
    public function run()
    {
        while (true) {
            $goodsInfo = $this->fetchData();
            if ($goodsInfo) {
                foreach ($goodsInfo as $k => $v) {
                    var_dump($v);
                    $params['uid'] = $v['g_salesId'];
                    $params['count'] = $v['count'];
                    $browse_num = $this->goodsModel->getBrowseNum($v['g_salesId']);
                    $params['browseNum'] = $browse_num;
                    $like_num = $this->goodsModel->getLikeNum($v['g_salesId']);
                    $params['likeNum'] = $like_num;
                    $res = $this->userLib->cliUpdateUserGoodsNum($params);
                }
            }
            exitTask('03:00:00', '03:02:00');
            //6个小时执行一次
            sleep(60 * 60 * 6);
        }
    }

    private function fetchData()
    {
        $goodsInfo = $this->goodsLib->cliChangeGoodsNum([]);
        return $goodsInfo;
    }





}
