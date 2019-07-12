<?php

/**
 * 搜索
 * @author Administrator
 *
 */

namespace Controller\home;

use Exception\ParamsInvalidException;
use Lib\Base\BaseController;
use Exception\ModelException;
use Model\Common\searchWord;
use Rest\Mall\Facade\FileHelper;


class Search extends BaseController {

    /**
     * 搜索全部
     * @param $act
     * @throws ModelException
     */
    public function all() {
        $keyword = app()->request()->params('keyword');
        if (!$keyword) {
            throw new ParamsInvalidException("搜索的内容必须");
        }
        //搜索内容收录
        searchWord::keywordsCollect($keyword);
        //搜索商品
        $goods = (new \Lib\Mall\Goods())->itemQuery([
            'pageSize'=>2,
            'isHaveStock'=>1,
            'status'=>3,
            'keyword'=>$keyword,
        ]);

        $data = [];

        $data['goods']=empty($goods['list'])?[]:$goods['list'];
        foreach ($data['goods'] as &$row){
            if($this->clientType==self::CLIENT_TYPE_ANDROID && $row['isSecKill']){
                $tmpActivityPrice=$row['g_activityPrice'];
                $row['g_activityPrice']=$row['g_price'];
                $row['g_price']=$tmpActivityPrice;
            }
        }
        $data['artist']=$this->searchUser($keyword,'0,1');
        $data['organization']=$this->searchUser($keyword,'2');
        $data['treasure']=$this->searchTreasure($keyword);
        $data['news']=$this->searchNews($keyword);
        $this->responseJSON($data);
    }

    /**
     * 搜索用户
     * @param $keyword
     * @param $type
     * @return array
     */
    private function searchUser($keyword,$type)
    {
        $users=[];
        $userLib = new \Lib\User\User();
        $res = $userLib->fuzzySearch([
            'u_type'=>$type,
            'nickname'=>$keyword,
            'realname'=>$keyword,
            'page'=>1,
            'pagesize'=>2,
            'status'=>'0',
        ]);
        if($res && $res[0]){
            $friendsModel = new \Model\Friends\Friends();
            $certificationModel = new \Model\User\Certification();
            foreach ($res[0] as $row){
                // 好友关系
                $row['relation']=0;
                if($this->uid){
                    $row['relation'] = $friendsModel->relation($this->uid, $row['u_id']);
                }
                // 是否认证
                $row['certification'] = (string)$certificationModel->getType($row['u_id']);
                // 作品数量
                $goodsNum = [];
                $goodsNum = $userLib->getUserExtend($row['u_id']);
                $row['goodsNum'] = $goodsNum['list']['ue_goodsNum'];
                if($row['u_realname']  && ($row['u_realname'] != $row['u_nickname'])){
                    $row['u_nickname'] .= "（{$row['u_realname']}）";
                }
                $users[]=$row;
            }
        }
        return $users;
    }

    /**
     * 搜索圈子
     * @param $keyword
     * @return array
     */
    private function searchTreasure($keyword)
    {
        $treasure=[];
        //搜索圈子
        $res=(new \Model\Treasure\Treasure())->lists([
            't_desc'=>$keyword,
            't_type'=>'1',
            't_status'=>'0',
        ],1,2);
        $modelImg=new \Model\Treasure\TreasureImage();
        if($res && $res[0]){
            foreach ($res[0] as $row){
                $row['t_pictures']=[];
                list($imags,$imges_count)=$modelImg->lists(['t_id'=>$row['t_id']],1,3);
                if($imges_count){
                    $row['t_pictures']=$imags;
                }
                $time = strtotime($row['t_createDate']);
                $row['displayTime'] = date_format_to_display($time);
                $treasure[]=$row;
            }

            (new \Lib\User\User())->extendUserInfos2Array($treasure, 'u_id', array(
                'u_nickname' => 't_nickname',
                'u_avatar' => 't_avatar',
                'u_realname' => 't_realname',
            ));
        }
        return $treasure;
    }

    /**
     * 搜索头条
     * @param $keyword
     * @return array
     */
    private function searchNews($keyword)
    {
        $news=[];
        $newsLib = new \Lib\News\News();
        //搜索头条
        $newsList=$newsLib->getList([
            'n_title'=>$keyword,
            'n_status'=>'0',
            'addTime'=>'1',
            'is_index'=>'0',
            'recommend'=>'0',
        ],1,2);
        if($newsList && $newsList['list']){
            foreach ($newsList['list'] as $row){
                $row['img'] = $newsLib->newsImg($row['n_id'], 6);
                $row['img']=$row['img']?$row['img']:[];
                $row['displayTime'] = date_format_to_display(strtotime($row['n_update_date']));
                $news[]=$row;
            }
        }
        return $news;
    }
}
