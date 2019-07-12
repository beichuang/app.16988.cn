<?php
/**
 * 用户收藏
 * @author Administrator
 *
 */
namespace Controller\User;

use Framework\Helper\DateHelper;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;
use Exception\InternalException;
use Lib\Common\Region;
use Lib\Mall\Custom;

class Favorite extends BaseController
{

    private $favModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->favModel = new \Model\User\Favorite();
    }

    /**
     * 新增收藏
     *
     * @throws ModelException
     */
    public function add()
    {
        $type = app()->request()->params('type', 0);
        $content = app()->request()->params('content');
        $title = app()->request()->params('title', '');
        if (! isset($type)) {
            throw new ParamsInvalidException("收藏类型必须");
        }
        if (! $content) {
            throw new ParamsInvalidException("收藏内容必须");
        }
        if (! isset($title)) {
            throw new ParamsInvalidException("收藏标题必须");
        }
        if (strlen($title) > 255) {
            throw new ParamsInvalidException("收藏标题过长");
        }
        $objKey = $saveContent =  '';
        switch ($type) {
            case \Lib\User\Favorite::TYPE_GOODS:   // 商品
                $objKey = $content;
                $this->checkFavoriteGoods($objKey);
                break;
            case \Lib\User\Favorite::TYPE_URL:
            case \Lib\User\Favorite::TYPE_TEXT:
            case \Lib\User\Favorite::TYPE_JSON:
                $saveContent = $content;
                break;
            case \Lib\User\Favorite::TYPE_NEWS:   // 头条
                $objKey = $content;
                $this->checkFavoriteNews($objKey);
                break;
            case \Lib\User\Favorite::TYPE_TREASURE:   // 艺术圈
                $objKey = $content;
                $this->checkFavoriteTreasure($objKey);
                break;
            case \Lib\User\Favorite::TYPE_CUSTOMIZE: //定制
                $objKey = $content;
                $this->checkFavoriteCustomize($objKey);
                break;
            default:
                throw new ParamsInvalidException("收藏类型错误");
                break;
        }
        $id = $this->favModel->add($this->uid, $type, $saveContent, $title, $objKey);

        if (! $id) {
            throw new ModelException("保存收藏失败");
        }
        if($type==\Lib\User\Favorite::TYPE_TREASURE){
            app('mysqlbxd_app')->query("UPDATE `treasure`  SET `t_favTimes`= `t_favTimes`+1 where t_id=:t_id ",[
                't_id'=>$objKey,
            ]);
        }
        $this->responseJSON(array(
            'favorite_id' => $id
        ));
    }

    /**
     * 收藏商品
     */
    private function checkFavoriteGoods($g_id)
    {
        if (strlen($g_id) > 50) {
            throw new ParamsInvalidException("不是合法的商品sn编号");
        }
        $goodsLib = new \Lib\Mall\Goods();
        // $goodsId = $g_id;
        $goodsDetail = $goodsLib->detailGet(array(
            'id' => $g_id
        ));
        if (! $goodsDetail || ! isset($goodsDetail['item']) || ! $goodsDetail['item']) {
            throw new ServiceException("goods不存在");
        }
        $favInfo = $this->favModel->oneByUfavObjectId($this->uid, $g_id,\Lib\User\Favorite::TYPE_GOODS);
        if ( $favInfo ) {
            throw new ParamsInvalidException("已收藏");
        }
    }

    /**
     * 收藏头条
     */
    private function checkFavoriteNews($n_id)
    {
        $data = array();
        if (strlen($n_id) > 50) {
            throw new ParamsInvalidException("不是合法的头条sn编号");
        }

        $newsMod = new \Model\News\News();
        $newsMessage = $newsMod->getOneLine(array('n_id'=>$n_id, 'n_status'=>0));
        if ( empty($newsMessage) ) {
            throw new ParamsInvalidException("该资讯不存在");
        }
        $favInfo = $this->favModel->oneByUfavObjectId($this->uid, $n_id,\Lib\User\Favorite::TYPE_NEWS);
        if ( $favInfo ) {
            throw new ParamsInvalidException("已收藏");
        }
    }

    /**
     * 检查收藏定制
     * @param $id
     * @throws ParamsInvalidException
     */
    private function checkFavoriteCustomize($id)
    {
        if(!app('mysqlbxd_mall_common')->fetchColumn('select count(*) c from custom where c_id='.intval($id))){
            throw new ParamsInvalidException("该条定制信息不存在");
        }
        $favInfo = $this->favModel->oneByUfavObjectId($this->uid, $id,\Lib\User\Favorite::TYPE_CUSTOMIZE);
        if ( $favInfo ) {
            throw new ParamsInvalidException("已收藏");
        }
    }
    /**
     * 收藏艺术圈
     */
    private function checkFavoriteTreasure($t_id)
    {
        $treasureMod = new \Model\Treasure\Treasure();
        $treasureMessage = $treasureMod->selectOne($t_id);
        if ( empty($treasureMessage) ) {
            throw new ParamsInvalidException("该条艺术圈消息不存在");
        }
        $favInfo = $this->favModel->oneByUfavObjectId($this->uid, $t_id,\Lib\User\Favorite::TYPE_TREASURE);
        if ( $favInfo ) {
            throw new ParamsInvalidException("已收藏");
        }
    }

    /**
     * 移除收藏
     *
     * @throws ModelException
     */
    public function remove($id=null)
    {
        $ufav_id = app()->request()->params('ufav_id',$id);
        if (! $ufav_id) {
            throw new ParamsInvalidException("收藏Id必须");
        }

        $ufav_id_list = explode(',', $ufav_id);
        foreach ($ufav_id_list as $value) {
            if ( $value ) {
                $this->dealRemove($value);
            }
        }

        $this->responseJSON(true);
    }

    private function dealRemove($ufav_id)
    {
        $isCurrentUser = $this->favModel->isSameUser($this->uid, $ufav_id);
        if (! $isCurrentUser) {
            throw new ServiceException("该收藏不是当前用户的");
        }
        $goodsLib = new \Lib\Mall\Goods();
        $favInfo = $this->favModel->oneById($ufav_id);
        if ($favInfo && is_array($favInfo) && isset($favInfo['ufav_type']) && $favInfo['ufav_type'] === '0') {
            $g_id = $favInfo['ufav_objectKey'];
            try {
                $goodsLib->favoriteTimesChange(
                    [
                        'id' => $g_id,
                        'isAdd' => 0
                    ]);
            } catch (\Exception $e) {}
        }
        $rCount = $this->favModel->delete($ufav_id);

        if (! $rCount) {
            throw new ModelException("移除失败");
        }
        if($favInfo['ufav_type']==\Lib\User\Favorite::TYPE_TREASURE){
            app('mysqlbxd_app')->query("UPDATE `treasure`  SET `t_favTimes`= `t_favTimes`-1 where t_id=:t_id ",[
                't_id'=>$favInfo['ufav_objectKey'],
            ]);
        }

        return true;
    }

    /**
     * 分页查询
     *
     * @throws ModelException
     */
    public function queryByPage()
    {
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        $params = array();
        $params['u_id'] = $this->uid;
        $params['ufav_type'] = app()->request()->params('favoriteType', '');
        $params['ufavTimeStart'] = app()->request()->params('favoriteDateStart', '');
        $params['ufavTimeEnd'] = app()->request()->params('favoriteDateEnd', '');

        list ($rows, $totalCount) = $this->favModel->lists($params, $page, $pageSize);

        $goodsLib = new \Lib\Mall\Goods();
        $newsMod = new \Model\News\News();
        $newsLib = new \Lib\News\News();
        $userLib = new \Lib\User\User();
        $customLib = new Custom();
        $treasureMod = new \Model\Treasure\Treasure();
        foreach ($rows as $key => &$v) {
            $v['ufav_time'] = date('Y-m-d', strtotime($v['ufav_time']));
            switch ( $v['ufav_type'] ) {
                case \Lib\User\Favorite::TYPE_GOODS:
                    $goodsModel = new \Model\Mall\Goods();
                    $is_have = $goodsModel->getIsExists($v['ufav_objectKey']);
                    if (!$is_have){  //删除收藏
                        $this->remove($v['ufav_id']);
                    }else{
                        $goodsDetail = $goodsLib->detailGet(array('id' => $v['ufav_objectKey']));
                        if ( is_array($goodsDetail) ) {
                            $userLib->extendUserInfos2Array($goodsDetail['item'],'g_salesId', array(
                                'u_nickname'=>'g_nickname',
                                'u_realname'=>'g_realname',
                                'u_avatar'=>'g_avatar',
                                'ue_imId' => 'ue_imId',
                                'ue_imPassword' => 'ue_imPassword',
                            ));
                            $v['ufav_content'] = $goodsDetail;
                            $v['ufav_treasure_content'] = (object)array();
                        }
                    }
                    break;
                case \Lib\User\Favorite::TYPE_NEWS:
                    $where = array(
                            'n_id' => $v['ufav_objectKey'],
                            'n_status' => 0,
                        );
                    $newsLine = $newsMod->getOneLine($where);
                    $order = array("\r\n","\n","\r","\t");
                    $newsLine['n_subtitle'] = mb_substr(str_replace($order,'',strip_tags($newsLine['n_content'])),0,120,'utf-8');
                    unset($newsLine['n_content']);
                    if ( $newsLine ) {
                        $time = strtotime($newsLine['n_update_date']);
                        $newsLine['displayTime'] = date_format_to_display($time);
                        $newsLine['img']  = $newsLib->newsImg($newsLine['n_id'],6);
                        $v['ufav_content'] = $newsLine;
                        $v['ufav_treasure_content'] = (object)array();
                    }
                    break;
                case \Lib\User\Favorite::TYPE_TREASURE:
                    $treasureLine = $treasureMod->oneById($v['ufav_objectKey']);
                    if ( $treasureLine ) {
                        $commentModel = new \Model\Treasure\TreasureComment();
                        $likeLogModel = new \Model\Treasure\TreasureLikeLog();
                        $treasureImgModel = new \Model\Treasure\TreasureImage();
                        $treasureLine['is_oneself'] = $this->uid==$treasureLine['u_id']?1:0;
                        $treasureLine['is_like'] = $likeLogModel->treasureIsLikeLogInfo($this->uid, $treasureLine['t_id']);
                        $treasureLine['is_comment'] = $commentModel->isComment($this->uid, $treasureLine['t_id']);
                        $treasureLine['t_provinceName'] = Region::getRegionNameByCode($treasureLine['t_provinceCode']);
                        $treasureLine['t_cityName'] = Region::getRegionNameByCode($treasureLine['t_cityCode']);
                        $treasureLine['t_areaName'] = Region::getRegionNameByCode($treasureLine['t_areaCode']);
                        list ($pic, $picTotalCount) = $treasureImgModel->lists(
                            array(
                                't_id' => $treasureLine['t_id']
                            ), 1, 10);
                        $treasureLine['t_pictures'] = $pic;

                        $arr[0] = &$treasureLine;
                        $userLib->extendUserInfos2Array($arr,'u_id', array(
                            'u_nickname'=>'t_nickname',
                            'u_realname'=>'t_realname',
                            'u_avatar'=>'t_avatar',
                            ));

                        $time = strtotime($treasureLine['t_createDate']);
                        $treasureLine['displayTime'] = date_format_to_display($time);

                        // 是否认证
                        $certificationModel = new \Model\User\Certification();
                        $treasureLine['certification'] = $certificationModel->getType($treasureLine['u_id']);

                        //头条类型圈子处理
                        if($treasureLine['t_type'] == 2 && !empty($treasureLine['t_business_id'])) {
                            $this->setShareData($treasureLine);
                        }

                        $v['ufav_content'] = (object)array();
                        $v['ufav_treasure_content'] = $treasureLine;
                    } else {
                        unset($rows[$key]);
                        // $v['ufav_content'] = (object)array();
                        // $v['ufav_treasure_content'] = (object)array();
                    }
                    break;
                case \Lib\User\Favorite::TYPE_CUSTOMIZE:
                    //定制列表信息
                    $c_id = $v['ufav_objectKey'];
                    $customData = $customLib->getOneById($c_id);
                    if (empty($customData)) {
                        unset($rows[$key]);
                    } else {
                        $userInfo = $userLib->getUserInfo([$customData['c_createUserId']]);
                        $v['ufav_custom_content'] = [
                            'c_id' => $customData['c_id'],
                            'c_title' => $customData['c_title'],
                            'c_desc' => $customData['c_desc'],
                            'c_price' => $customData['c_trusteeship_amount'],
                            'c_status' => $customData['c_status_format'],
                            'c_createUserNickname' => empty($userInfo[$customData['c_createUserId']]) ? '' : $userInfo[$customData['c_createUserId']]['u_nickname'],
                            'c_createUserAvatar' => empty($userInfo[$customData['c_createUserId']]) ? '' : $userInfo[$customData['c_createUserId']]['u_avatar'],
                            'c_createDateFormat' => DateHelper::getTimeSpanFormat($customData['c_createDate'], time())
                        ];
                        $v['ufav_content'] = (object)[];
                    }
                    break;
            }
        }

        $this->responseJSON(array(
            'rows' => array_values($rows),
            'totalCount' => $totalCount
        ));
    }

    private function setShareData(&$value)
    {
        $schema = config('app.request_url_schema_x_forwarded_proto_default');
        $cdnBaseUrl = config('app.CDN.BASE_URL_RES');
        $baseDomain = config('app.baseDomain');

        $business_id = $value['t_business_id'];
        //获取头条信息
        $newsModel = new \Model\News\News();
        $newsMessage = $newsModel->one('n_status = 0 AND n_id=:n_id', [':n_id' => $business_id]);
        if ($newsMessage) {
            $imagePath = $newsMessage['n_picurl'];
            if (empty($imagePath)) {
                $newImages = $newsModel->getImg($business_id, 1);
                if (!empty($newImages[0]['ni_img'])) {
                    $imagePath = $newImages[0]['ni_img'];
                }
            }

            if (!empty($imagePath)) {
                $value['t_share_picture'] = FileHelper::getFileUrl($imagePath, 'news_images');
            } else {
                $value['t_share_picture'] = $schema . ':' . $cdnBaseUrl . '/html/images/fenxianglogo.jpg';
            }
            $value['t_share_title'] = $newsMessage['n_title'];
            $value['t_share_url'] = $schema . '://' . $baseDomain . '/html/infoDetail.html?n_id=' . $business_id;
        } else {
            $value['t_share_picture'] = $schema . ':' . $cdnBaseUrl . '/html/images/fenxianglogo.jpg';
            $value['t_share_title'] = '';
            $value['t_share_url'] = '';
        }
    }
}
