<?php

/**
 * 拍品
 * @author Administrator
 *
 */

namespace Controller\Mall\Auction;

use Exception\ServiceException;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Rest\Mall\Facade\ItemManager;

class Item extends BaseController
{

    private $auctionLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->auctionLib = new \Lib\Mall\Auction();
    }

    /**
     * 获取首页热门拍卖
     */
    public function getHotList()
    {
        //推荐的拍品
        $params['is_recommend'] = 1;
        //未结束
        $params['notFinished'] = 1;
        //最多4条
        $params['pageSize'] = 4;
        $recommendData = $this->auctionLib->lists($params);

        $recommendCount = empty($recommendData['lists']) ? 0 : count($recommendData['lists']);
        $otherCount = 4 - $recommendCount;
        $params = [];
        $params['orderBy'] = json_encode([['column' => 'a_startPrice', 'type' => 'ASC']]);
        $params['is_recommend'] = 0;
        $params['auditStatus'] = 1;
        $params['notFinished'] = 1;
        $params['pageSize'] = $otherCount;
        $otherData = $this->auctionLib->lists($params);
        $otherCount = empty($otherData['lists']) ? 0 : count($otherData['lists']);

        $data['count'] = $recommendCount + $otherCount;
        $data['lists'] = array_merge($recommendData['lists'], $otherData['lists']);
        foreach ($data['lists'] as &$item) {
            //查询是否有鉴定图片
            $sql              =  "select ai_img from auction_image where a_id={$item['a_id']} and ai_imageType=2 ";
            $ai_img           =   app('mysqlbxd_mall_common')->fetchColumn($sql);
            $ai_img_status    =   $ai_img?1:0;
            $item = [
                'a_id' => $item['a_id'],
                'a_name' => $item['a_name'],
                'a_startDate' => $item['a_startDate'],
                'a_startPrice' => intval($item['a_startPrice']),
                'a_currentPrice' => intval($item['a_currentPrice']),
                'a_bidCount' => $item['a_bidCount'],
                'a_status' => $item['a_status'],
                'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                'ai_img_status'=> (string)$ai_img_status
            ];
        }
        $this->responseJSON($data);
    }

    //------商品同类别推荐  -------
    public function  similarRecommend(){
        $a_id = app()->request()->params('id','');
        if (empty($a_id)) {
            throw new ServiceException("拍品id必须存在");
        }
        //获取同类拍品
        $now_time = date("Y-m-d H:i:s",time());
        //1：获取同类拍品商品
        $a_categoryId_sql = "select a_categoryId from auction where a_id={$a_id}";
        $a_categoryId     =  app('mysqlbxd_mall_common')->fetchColumn($a_categoryId_sql);
        $sql  = "select a_id,a_name,a_surfaceImg,a_currentPrice,1 as `type`   from  auction  where a_categoryId ={$a_categoryId} 
                 AND  ('{$now_time}'>a_startDate and '{$now_time}'<a_endDate ) AND a_auditStatus=1 AND a_id!={$a_id} ORDER BY  a_startDate DESC limit 0,3";
        $auction_list =  app('mysqlbxd_mall_common')->select($sql);
        if($auction_list){
            foreach ($auction_list as &$v){
                //圈子图片
                if ($v['a_surfaceImg']) {
                    $v['a_surfaceImg'] = FileHelper::getFileUrl($v['a_surfaceImg'], 'mall_auction_images');
                }
            }
        }
        // 2：同类拍品不够  显示同类别的商品 最新的几个  (审核通过   按照上架时间降序排序)
        $limit_step =4-count($auction_list);
        $goods_list = [];
        if($limit_step>0){
             $sql_goods_list  =  "select g_id as a_id,g_name as a_name,g_surfaceImg as a_surfaceImg,g_price as a_currentPrice, 0 as `type` from goods where  g_status=3 AND g_categoryId={$a_categoryId}   AND  g_stock>0  order by g_onShowDate desc limit 0,{$limit_step}";
             $goods_list      =   app('mysqlbxd_mall_user')->select($sql_goods_list);
             $limit_step      -= count($goods_list);
        }

        if($limit_step>0){
            $sql_goods_list  =   "select g_id as a_id,g_name as a_name,g_surfaceImg as a_surfaceImg,g_price as a_currentPrice,  0 as `type`    from goods where  g_status=3 AND g_categoryId!={$a_categoryId}   AND  g_stock>0  order by g_onShowDate desc limit 0,{$limit_step}";
            $goods_list_s    =   app('mysqlbxd_mall_user')->select($sql_goods_list);
            $goods_list      = array_merge($goods_list,$goods_list_s);
        }
        if($goods_list){
            foreach ($goods_list  as  $k=>&$v){
                $images =ItemManager::getItemImageById($v['a_id']);
                //取其中  商品一个图片
                $images = $images?$images[0]:'';
                $surfaceImg = "";
                if ($v['a_surfaceImg']) {
                    $surfaceImg = json_decode(stripslashes($v['a_surfaceImg']), true);
                    if (!empty($surfaceImg['gi_img'])) {
                        $surfaceImg['gi_img'] = FileHelper::getFileUrl($surfaceImg['gi_img'], 'mall_goods_attr_images');
                    }
                }
                if(empty($surfaceImg)) {
                    do {
                       $surfaceImg = $images;
                        //image 存在 但是  $surfaceImg不存在
                    } while ($images && (!$surfaceImg || !isset($surfaceImg['gi_img']) || !$surfaceImg['gi_img']));
                }
                $v['a_surfaceImg'] = isset($surfaceImg['gi_img'])?$surfaceImg['gi_img']:'';
            }
        }
        $list  = array_merge($auction_list,$goods_list);
        $this->responseJSON($list);
    }

    /**
     * 获取作品列表
     */
    public function getList()
    {
        $params = app()->request()->params();
        if (!isset($params['status'])) {
            throw new ParamsInvalidException("缺少参数status");
        }
        $params['auditStatus'] = 1;
        $data = $this->auctionLib->lists($params);

        $status = intval($params['status']);
        $lists = [];
        foreach ($data['lists'] as $item) {
            switch ($status) {
                //未开始
                case 0:
                    $lists[] = [
                        'a_id' => $item['a_id'],
                        'a_name' => $item['a_name'],
                        'a_startDate' => $item['a_startDate'],
                        'a_startPrice' => intval($item['a_startPrice']),
                        'a_currentPrice' => intval($item['a_currentPrice']),
                        'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                    ];
                    break;
                //已结束
                case 2:
                    $lists[] = [
                        'a_id' => $item['a_id'],
                        'a_name' => $item['a_name'],
                        'a_startPrice' => intval($item['a_startPrice']),
                        'a_finalPrice' => intval($item['a_finalPrice']),
                        'a_bidCount' => $item['a_bidCount'],
                        'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                    ];
                    break;
                default:
                    $lists[] = [
                        'a_id' => $item['a_id'],
                        'a_name' => $item['a_name'],
                        'a_startPrice' => intval($item['a_startPrice']),
                        'a_currentPrice' => intval($item['a_currentPrice']),
                        'a_bidCount' => $item['a_bidCount'],
                        'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                        'a_endDate' => $this->formatTime(time(), $item['a_endDate'])
                    ];
                    break;
            }
        }

        $data['lists'] = $lists;
        $this->responseJSON($data);
    }

    /**
     * 我参与的拍品
     */
    public function getInvolveList()
    {
        $params = app()->request()->params();
        $params['userId'] = $this->uid;
        $data = $this->auctionLib->involveLists($params);

        $type = intval($params['type']);
        $lists = [];
        foreach ($data['lists'] as $item) {
            switch ($type) {
                //进行中
                case 1:
                    $lists[] = [
                        'a_id' => $item['a_id'],
                        'a_name' => $item['a_name'],
                        'a_endDate' => $item['a_endDate'],
                        'a_currentPrice' => intval($item['a_currentPrice']),
                        'a_bidCount' => $item['a_bidCount'],
                        'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                    ];
                    break;
                //已结束
                case 2:
                    $lists[] = [
                        'a_id' => $item['a_id'],
                        'a_name' => $item['a_name'],
                        'a_endDate' => $item['a_endDate'],
                        'a_finalPrice' => intval($item['a_finalPrice']),
                        'a_bidCount' => $item['a_bidCount'],
                        'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                    ];
                    break;
                //已成交
                case 3:
                    $lists[] = [
                        'a_id' => $item['a_id'],
                        'a_name' => $item['a_name'],
                        'a_finalPrice' => intval($item['a_finalPrice']),
                        'a_orderCreateDate' => $item['a_orderCreateDate'],
                        'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                        'a_orderSn' => $item['a_orderSn'],
                        'a_orderStatus' => isset($item['orderData']['o_status']) ? $item['orderData']['o_status'] : 0,
                        'a_payRemainingSeconds' => $this->getSpanSeconds(strtotime("{$item['a_orderCreateDate']}+3 day"), time())
                    ];
                    break;
            }
        }

        $data['lists'] = $lists;
        $this->responseJSON($data);
    }

    /**
     * 我发布的拍品
     */
    public function getMyList()
    {
        $params = app()->request()->params();
        $params['salesId'] = $this->uid;
        $data = $this->auctionLib->lists($params);

        $lists = [];
        foreach ($data['lists'] as $item) {
            $lists[] = [
                'a_id' => $item['a_id'],
                'a_name' => $item['a_name'],
                'a_startPrice' => intval($item['a_startPrice']),
                'a_currentPrice' => intval($item['a_currentPrice']),
                'a_finalPrice' => intval($item['a_finalPrice']),
                'a_bidCount' => $item['a_bidCount'],
                'a_startDate' => $item['a_startDate'],
                'a_endDate' => $item['a_endDate'],
                'a_surfaceImg_url' => $item['a_surfaceImg_url'],
                'a_status' => $item['a_status'],
                'a_auditStatus' => $item['a_auditStatus'],
                'a_auditFailedReason' => $item['a_auditFailedReason'],
                'a_orderCreateStatus' => $item['a_orderCreateStatus'],
                'a_startRemainingSeconds' => $this->getSpanSeconds($item['a_startDate'], time()),
                'a_endRemainingSeconds' => $this->getSpanSeconds($item['a_endDate'], time())
            ];
        }

        $data['lists'] = $lists;
        $this->responseJSON($data);
    }

    /**
     * 拍品详情
     */
    public function detail()
    {
        $params = app()->request()->params();
        if (empty($params['id'])) {
            throw new ParamsInvalidException("拍品id必须");
        }
        $auctionInfo = $this->auctionLib->detailGet($params);
        $data['a_id'] = $auctionInfo['a_id'];
        $data['a_name'] = $auctionInfo['a_name'];
        $data['a_categoryId'] = $auctionInfo['a_categoryId'];
        $data['a_categoryName'] = $auctionInfo['categoryName'];
        if (!empty($auctionInfo['a_images'])) {
            foreach ($auctionInfo['a_images'] as $item) {
                if ($item['ai_imageType'] == 2) { //鉴定证书
                    $data['a_certImages'][] = $item;
                } elseif ($item['ai_imageType'] == 3) { //封面图片
                    $data['a_surfaceImage'] = $item;
                } else { //描述图片
                    $data['a_auctionImages'][] = $item;
                }
            }
        }
        $data['a_desc'] = $auctionInfo['a_desc'];
        $data['a_startPrice'] = intval($auctionInfo['a_startPrice']);
        $data['a_currentPrice'] = intval($auctionInfo['a_currentPrice']);
        $data['a_ratePrice'] = intval($auctionInfo['a_ratePrice']);
        $data['a_startDate'] = $auctionInfo['a_startDate'];
        $data['a_endDate'] = $auctionInfo['a_endDate'];
        $data['a_canReturn'] = $auctionInfo['a_canReturn'];
        $data['a_status'] = $auctionInfo['a_status'];
        $data['a_auditStatus'] = $auctionInfo['a_auditStatus'];
        $userInfo = (new \Lib\User\User())->getUserInfo([$auctionInfo['a_salesId']], '', 0);
        //获取拍品用户标签
        $ue_celebrityTitle  = app("mysqlbxd_user")->fetchColumn("select ue_celebrityTitle from user_extend where u_id={$auctionInfo['a_salesId']}");
        $data['ue_celebrityTitle'] = $ue_celebrityTitle?$ue_celebrityTitle:'';
        //作者昵称、头像、个人简介
        $data['a_salesId'] = $auctionInfo['a_salesId'];
        $friendsModel = new \Model\Friends\Friends();
        $data['attention'] = $friendsModel->isAttention($this->uid,$data['a_salesId'])?1:0;
        $data['u_nickname'] = current($userInfo)['u_nickname'];
        $data['u_avatar'] = current($userInfo)['u_avatar'];
        $data['gu_authorIntroduction'] = (new \Model\User\Setting())->settingGetValue($auctionInfo['a_salesId'], 'introduction');
        $data['a_startRemainingSeconds'] = $this->getSpanSeconds($auctionInfo['a_startDate'], time());
        $data['a_endRemainingSeconds'] = $this->getSpanSeconds($auctionInfo['a_endDate'], time());
        $data['pledge'] = $auctionInfo['pledge'];//保证金金额
        $this->responseJSON($data);
    }

    /**
     * 新增/编辑拍品
     */
    public function save()
    {
        $params = app()->request()->params();
        $certModel = new \Model\User\Certification();
        $certificationInfo = $certModel->getInfo($this->uid);

        if (empty($certificationInfo['uce_IDNo']) && $certificationInfo['uce_isCelebrity'] != 2) {
            throw new ServiceException("还没有通过实名认证");
        }
        if (empty($certificationInfo['uce_IDNo']) && $certificationInfo['uce_isCelebrity'] == 2 && $certificationInfo['uce_status'] != 1) {
            throw new ServiceException("机构认证中");
        }
        //卖家id为当前用户id
        $params['salesId'] = $this->uid;
        $resMall = $this->auctionLib->itemSave($params);
        $auctionId = empty($resMall) ? '' : $resMall;
        $this->responseJSON(array(
            'a_id' => $auctionId
        ));
    }

    /**
     * 获取拍品分享信息
     */
    public function share()
    {
        $uid = app()->request()->params('uid');
        $a_id = app()->request()->params('id');
        if (empty($a_id)) {
            throw new ServiceException("拍品id必须");
        }

        $resMall = $this->auctionLib->detailGet(['id' => $a_id]);
        if (empty($resMall)) {
            throw new ServiceException("拍品信息不存在");
        }

        //返回分享商品需要的信息
        $base_url_res = conf('app.CDN.BASE_URL_RES');
        $base_url = conf('app.request_url_schema_x_forwarded_proto_default');
        if ($resMall['a_surfaceImg']) {
            $image = FileHelper::getFileUrl($resMall['a_surfaceImg'], 'mall_auction_images');
        } else {
            $image = $base_url . ':' . $base_url_res . '/html/images/fenxianglogo.jpg';
        }

        $data['share_info'] = [
            'title' => "大家猜一下这个“{$resMall['a_name']}”能拍到多少钱？",
            'image' => $image,
            'url' => get_request_url_schema() . '://' . config('app.baseDomain') . '/html/apph5/auction.html#/auctionDetail?id=' . $a_id
        ];
        $data['share_info']['content'] = $resMall['a_desc'] ? mb_substr($resMall['a_desc'], 0, 30, 'utf-8') : "我在这里找到了自己喜欢的艺术品，你也试试吧~";
        $this->responseJSON($data);
    }

    private function formatTime($time1, $time2)
    {
        $formatText = '';
        if (is_string($time1)) {
            $time1 = strtotime($time1);
        }
        if (is_string($time2)) {
            $time2 = strtotime($time2);
        }

        $res = timediff($time1, $time2);
        if ($res['day'] > 0) {
            $formatText .= $res['day'] . '天';
        }
        if ($res['hour'] > 0) {
            $formatText .= $res['hour'] . '小时';
        }
        if ($res['day'] <= 0 && $res['min'] > 0) {
            $formatText .= $res['min'] . '分';
        }
        if ($res['day'] <= 0 && $res['hour'] <= 0 && $res['sec'] > 0) {
            $formatText .= $res['sec'] . '秒';
        }

        return $formatText;
    }

    private function getSpanSeconds($time1, $time2)
    {
        if (is_string($time1)) {
            $time1 = strtotime($time1);
        }
        if (is_string($time2)) {
            $time2 = strtotime($time2);
        }

        return $time1 - $time2;
    }
}
