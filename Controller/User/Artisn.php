<?php

/**
 * 匠心记
 * @author Administrator
 *
 */

namespace Controller\User;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Lib\User\User;

class Artisn extends BaseController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * 获奖列表
     *
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function lists() {
        $page = app()->request()->params('page',1);
        $page=intval($page)<1?1:intval($page);
        $pageSize=10;
        $offset=($page-1)*$pageSize;
        $lists=app('mysqlbxd_app')->select("select u_id,ua_thumb,ua_title,ua_subtitle from user_artisan where ua_isShow=1 order by ua_sort limit {$offset},{$pageSize}");
        if($lists){
            $userLib=new User();
            $userLib->extendUserInfos2Array($lists,'u_id',[
                'u_realname' => 'u_nickname',
//                'u_realname' => 'u_realname',
                'u_avatar' => 'u_avatar',
                'ue_celebrityTitle' => 'ue_celebrityTitle',
            ]);
            foreach ($lists as &$row){
                $row['ua_thumb']=FileHelper::getFileUrl($row['ua_thumb']);
            }
        }else{
            $lists=[];
        }
        $this->responseJSON($lists);
    }

    /**
     * 添加获奖经历
     *
     * @throws ServiceException
     */
    public function detail() {
        $uid = app()->request()->params('uid');
        $info=[];
        if($uid){
            $data=app('mysqlbxd_app')->select("select * from user_artisan where u_id=:u_id",[
                'u_id'=>$uid
            ]);
            if($data){
                $user_lib = new \Lib\User\User();
                $user_lib->extendUserInfos2Array($data,'u_id',[
                    'u_realname' => 'u_nickname',
                    'u_avatar' => 'u_avatar',
                    'ue_celebrityTitle' => 'ue_celebrityTitle',
                ]);
                $info=$data[0];
                $info['ua_thumb']=FileHelper::getFileUrl($info['ua_thumb']);
                $info['ua_bgImg']=FileHelper::getFileUrl($info['ua_bgImg']);
                $info['goods_list']=[];
                $info['ua_detail']=(new \Lib\News\News())->parseVideo($info['ua_detail']);
                $info['ua_detail'] = htmlspecialchars_decode($info['ua_detail']);
                $goodsLib = new \Lib\Mall\Goods();
                $res = $goodsLib->itemQuery([
                    'id' => $info['ua_goods']
                ]);
                if ($res['count']) {
                    foreach ($res['list'] as $goodsInfo){
                        $info['goods_list'][]=[
                            'g_id'=>$goodsInfo['g_id'],
                            'g_name'=>$goodsInfo['g_name'],
                            'g_price'=>$goodsInfo['isSecKill']?$goodsInfo['g_activityPrice']:$goodsInfo['g_price'],
                            'g_activityPrice' => $goodsInfo['isSecKill']?$goodsInfo['g_activityPrice']:$goodsInfo['g_price'],
                            'categoryName2'=>$goodsInfo['categoryName2'],
                            'g_surfaceImg'=>$goodsInfo['g_surfaceImg'],
                            'isSecKill'=>$goodsInfo['isSecKill'],
                            'size'=>implode(' ',$goodsLib->getAttrFromGoodsAttrs($goodsInfo['itemAttr'],'尺寸')),
                        ];
                    }
                }
            }

        }
        $this->responseJSON($info);
    }

}
