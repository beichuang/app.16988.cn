<?php

/**
 * PC 首页
 */

namespace Controller;

use Lib\Base\BaseController;

class Index extends BaseController {

    public function __construct() {
        parent::__construct();
        parent::pcSiteTitle();
    }

    /**
     * PC首页展示
     */
    public function index() {
        // 推荐头条
        $data['headlines'] = $this->headlines();

        //推荐艺术家
        $data['artist_list'] = $this->artist();

        //寻宝
        $data['goods_list'] = $this->goods_list();

        //圈子
        $data['circle_list'] = $this->circle_list();
        app()->render('PC/Index', $data);
    }

    /**
     * 头条数据
     */
    private function headlines() {
        $new_lib = new \Lib\News\News();
        $new_list = [];
        $result = $new_lib->getList(['is_index' => 1, 'addTime' => 1], 1, 3);
        if ($result && $result['count'] > 0) {
            $new_list = $result['list'];
            foreach ($new_list as $key => $val) {
                $new_list[$key]['displayTime'] = date_format_to_display(strtotime($val['n_update_date']));
                $new_img = $new_lib->newsImg($val['n_id'], 1);
                $new_list[$key]['hostImg'] = is_array($new_img) && !empty($new_img) ? $new_img[0]['ni_img'] : '';
            }
        }
        return $new_list;
    }

    /**
     * 艺术家数据
     */
    private function artist() {
        $params['goodsNum'] = 1;
        $params['lastUploadTime'] = 1;
        $params['likeNum'] = 1;
        $params['type'] = 1;
        $params['page'] = 1;
        $params['pageSize'] = 8;

        $result = api_request($params, 'user/query/recommend');
        $artist_list = [];
        if ($result && $result['count'] > 0) {
            $artist_list = $result['list'];
            $user_list = api_request(['uids' => implode(',', array_column($artist_list, 'u_id')), 'needExtend' => 1], 'user/get');
            foreach ($artist_list as $key => $val) {
                $artist_list[$key]['u_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $realname = $user_list[$val['u_id']]['u_realname'];
                $artist_list[$key]['u_realname'] = empty($realname) ? $user_list[$val['u_id']]['u_nickname'] : $realname;
                $artist_list[$key]['goodsNum'] = $user_list[$val['u_id']]['user_extend']['ue_goodsNum'];
            }
        }
        return $artist_list;
    }

    /**
     * 寻宝商品数据
     */
    private function goods_list() {
        $result = api_request(['status' => 3, 'isHaveStock' => 1, 'pageSize' => 4], 'mall/item/query');
        $goods_list = [];
        if ($result && $result['count'] > 0) {
            $goods_list = $result['list'];
            $user_list = api_request(['uids' => implode(',', array_column($goods_list, 'g_salesId')), 'needExtend' => 1], 'user/get');
            foreach ($goods_list as $key => $val) {
                $goods_list[$key]['u_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $realname = $user_list[$val['u_id']]['u_realname'];
                $goods_list[$key]['u_realname'] = empty($realname) ? $user_list[$val['u_id']]['u_nickname'] : $realname;
                if ($val['g_width'] == 0 || $val['g_high'] == 0) {
                    $goods_list[$key]['goods_size'] = $val['itemAttr'][0]['ga_value'];
                } else {
                    $goods_list[$key]['goods_size'] = $val['g_width'] . 'x' . $val['g_high'];
                }
                $goods_list[$key]['goods_image'] = empty($val['g_surfaceImg']['gi_img']) ? $val['image'][0]['gi_img'] : $val['g_surfaceImg']['gi_img'];
            }
        }
        return $goods_list;
    }

    /**
     * 圈子数据列表
     */
    private function circle_list() {
        $params['t_status'] = 0;
        $TreasureModel = new \Model\Treasure\Treasure();
        $result = $TreasureModel->lists($params, 1, 3);
        $circle_list = [];
        if ($result && is_array($result[0]) && $result[1] > 0) {
            $circle_list = $result[0];
            $user_list = api_request(['uids' => implode(',', array_column($circle_list, 'u_id')), 'needExtend' => 1], 'user/get');

            $treasureImgModel = new \Model\Treasure\TreasureImage();
            foreach ($circle_list as $key => $val) {
                $circle_list[$key]['u_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $circle_list[$key]['u_nickname'] = $user_list[$val['u_id']]['u_nickname'];
                list ($pic, ) = $treasureImgModel->lists(['t_id' => $val['t_id']], 1, 10);
                $circle_list[$key]['t_pictures'] = $pic[0]['ti_img'];
                $circle_list[$key]['displayTime'] = date_format_to_display(strtotime($val['t_createDate']));
            }
        }
        return $circle_list;
    }

}
