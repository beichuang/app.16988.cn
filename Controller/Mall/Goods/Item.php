<?php

/**
 * 商品评论
 * @author Administrator
 *
 */

namespace Controller\Mall\Goods;

use Controller\Wx\MiniProgram\Distribution\Common;
use Controller\Wx\Wx;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;
use Lib\Common\Region;
use Lib\Mall\GoodsCategory;
use Model\Common\searchWord;
use Model\Mall\Goods;
use Model\Mall\GoodsMake;
use Model\User\Setting;
use Model\User\User;
use Rest\Mall\Facade\CategoryManager;
use Rest\Mall\Facade\CommonManager;
use Rest\Mall\Facade\GoodsCollectionManager;
use Rest\Mall\Facade\ItemManager;
use Rest\Mall\Facade\UserManager;
use Rest\Mall\Facade\VoucherManager;


class Item extends BaseController
{

    private $goodsLib = null;
    private $goodsLikeLogModel = null;
    private $friendsModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsLib = new \Lib\Mall\Goods();
        $this->goodsLikeLogModel = new \Model\Mall\GoodsLikeLog();
    }

    /**
     * 商品分享信息
     *
     * 参数:
     *  id 商品id
     */
    public function share_info()
    {
        // $data = [];
        $uid = app()->request()->params('uid', $this->uid);
        $g_id = app()->request()->params('id');
        if (!$g_id) {
            throw new ServiceException("商品id必须");
        }

        $resMall = $this->goodsLib->detailGet(['id' => $g_id]);
        //print_r($resMall);exit();
        if (!$resMall['item']) {
            throw new ServiceException("商品信息不存在");
        }

        $resMall = $resMall['item'][0];
        //自营商品自动上架并可以参与分销
        if (isset($this->uid) && $this->uid) {
            $sales_id = $resMall['g_salesId'];
            if (($sales_id != $this->uid) && ($resMall['is_own_shop'] == 1)) {
                $params['gid'] = $g_id;
                $params['uid'] = $this->uid;

                $user_lib = new \Lib\User\User();
                $params['action'] = 'query';
                $res = $user_lib->distribution($params);
                if (!$res['list']) {
                    $params['action'] = 'insert';
                    $user_lib->distribution($params);
                }
            }
        }

        //返回分享商品需要的信息
        $base_url_res = conf('app.CDN.BASE_URL_RES');
        $base_url = conf('app.request_url_schema_x_forwarded_proto_default');
        if ($resMall['g_surfaceImg']) {
            $image = $resMall['g_surfaceImg']['gi_img'];
        } else {
            $image = $base_url . ':' . $base_url_res . '/html/images/fenxianglogo.jpg';
        }
        $data['share_info'] = [
            'title' => $resMall['g_name'],
            'image' => $image,
            //'url' => $uid ? '/html/apph5/myShop.html?uid='.$uid.'#/goodsDetail?id=' . $g_id : '/html/apph5/myShop.html#/goodsDetail?id=' . $g_id,
           // 'url' => $uid ? '/wx/wx/auth?uid=' . $uid . '&type=goods&id=' . $g_id : '/wx/wx/auth?type=goods&id=' . $g_id
            'url' => '/wx/wx/auth?type=goods&id=' . $g_id
        ];
        /* $data['share_info'] = [
          'title' => $resMall['g_name'],
          'image' => $base_url.':'.$base_url_res.'/html/images/fenxianglogo.jpg',
          'url' => '/html/goodsInfo.html?id='.$g_id,
          ]; */
        $data['share_info']['content'] = $resMall['g_inspiration'] ? $resMall['g_inspiration'] : "我在这里找到了自己喜欢的艺术品，你也试试吧~";

        $this->responseJSON($data);
    }

    /**
     * 分享商品
     * @throws ServiceException
     */
    public function share()
    {
        $g_id = app()->request()->params('g_id');
        if (!$g_id) {
            throw new ServiceException("商品id必须");
        }
        $data=(new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_GOODS_SHARE_ADD);
        $this->responseJSON($data);
    }
    public function uploadImages()
    {
        $filesData = FileHelper::uploadFiles('mall_goods_attr_images');
        if ($filesData) {
            if (empty($filesData['result'])) {
                $this->responseJSON(empty($filesData['data']) ? [] : $filesData['data'], 1, 1,
                    empty($filesData['message']) ? '' : $filesData['message']);
            } else {
                $this->responseJSON($filesData['data']);
            }
        } else {
            $this->responseJSON([], 1, 1, '上传文件时发生异常');
        }
    }

    /**
     * 新增/编辑商品
     *
     * @throws ModelException
     */
    public function add()
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
        $goodsId = isset($params['id']) ? $params['id'] : 0;
        //商品发布者id
        $params['salesId'] = $this->uid;
        //是否免邮费
        $params['isFreightFree'] = 1;
        //编辑商品时不改这个字段，此字段暂时用于秒杀的原价显示 @author Houbotao
        if ($goodsId) {
            $params['marketPrice'] = $params['price'];
        }
        //作品集
        if (isset($params['goodsBox']) && $params['goodsBox'] != -1) {
            $goodBox = new \Model\Mall\GoodsBox();
            $boxParams = [
                'u_id' => $this->uid,
                'gb_id' => $params['goodsBox']
            ];
            $retBox = $goodBox->lists($boxParams);
            if (!$retBox[1]) {
                throw new ServiceException("你还未创建该作品集");
            }
        }
        //多规格商品集（多规格商品集-根据规格数量同时生成多个商品）
        //代表app的请求来源
        $params['request_from']  = 1;
        if (!empty($params['goodsCollection'])) {
            $resMall = $this->goodsLib->itemCollectionPost($params);
        } else {
            $resMall = $this->goodsLib->itemPost($params);
        }

        if ($resMall) {
            //user_extend表记录最后一次上传作品的时间
            $userLib = new \Lib\User\User();
            $userLib->lastUploadTime($this->uid);

            //发布作品加积分
            (new \Lib\User\UserIntegral())->addIntegral($this->uid, \Lib\User\UserIntegral::ACTIVITY_ISSUE_GOODS_ADD);
        }

        $goodsId = $resMall;
        $this->responseJSON(array(
            'goods_id' => $goodsId
        ));
    }

    /**
     * 秒杀商品
     */
    public function secKillGoods($params)
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }

        return $this->goodsLib->itemQuery($params);
    }

    /**
     * 查询商品
     */
    public function query()
    {
        $params = app()->request()->params();
        if(isset($params['name']) && $params['name']){
            $params['keyword']=$params['name'];
            //关键词 搜录
            searchWord::keywordsCollect( $params['keyword']);
            unset($params['name']);
        }
        $resMall = $this->getLists($params);

        $userLib = new \Lib\User\User();

        $userLib->extendUserInfos2Array($resMall['list'], 'g_salesId', array(
                'u_realname' => 'u_realname',
                'u_nickname' => 'u_nickname',
                'u_avatar' => 'u_avatar',
            )
        );

        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['list'] as &$value) {
            if ($value['g_goodsBox'] == -1) {
                $value['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($value['g_goodsBox']);
                $value['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }

            if($this->clientType==self::CLIENT_TYPE_ANDROID && $value['isSecKill']){
                $tmpActivityPrice=$value['g_activityPrice'];
                $value['g_activityPrice']=$value['g_price'];
                $value['g_price']=$tmpActivityPrice;
            }
            //返回商品省份
            $value['g_provinceName'] = empty($value['g_provinceCode']) ? '' : Region::getRegionNameByCode($value['g_provinceCode']);
            //格式化点赞量
            $value['likeCount'] = $this->formatLikeCount($value['likeCount']);
        }

        $this->responseJSON($resMall);
    }

    /**
     * 格式化点赞量
     * @param int $likeCount 点赞量
     * @return float|int|string
     */
    private function formatLikeCount($likeCount)
    {
        if (empty($likeCount) || $likeCount < 0) {
            $result = 0;
        } elseif ($likeCount < 100) {
            $result = $likeCount;
        } elseif ($likeCount < 1000) {
            $result = $likeCount / 1000;
            $result = round($result, 1) . 'k';
        } else {
            $result = $likeCount / 10000;
            $result = round($result, 1) . 'w';
        }

        return $result;
    }

    /**
     * 查询商品
     */
    public function secKillGoodsList()
    {
        $params = app()->request()->params();
        $resMall = $this->getListsSecKill($params);

        $userLib = new \Lib\User\User();

        $userLib->extendUserInfos2Array($resMall['list'], 'g_salesId', array(
                'u_realname' => 'u_realname',
            )
        );

        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['list'] as &$value) {
            if ($value['g_goodsBox'] == -1) {
                $value['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($value['g_goodsBox']);
                $value['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }
            // $value['gu_realname'] = $value['u_realname'];
        }

        $this->responseJSON($resMall);
    }

    /**
     * @Summary :砍价商品列表
     * 含砍价商品相对于
     * @throws ServiceException
     * @Author yyb update at 2018/5/2 16:30
     */
    public function cutGoodsList()
    {
        $params = app()->request()->params();
        $resMall = $this->getListsCut($params);
        $openId = $params['openId'];
        if (empty($openId)) {
            throw new ServiceException("openId不能为空");
        }

        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($resMall['list'], 'g_salesId', array(
                'u_realname' => 'u_realname',
            )
        );

        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['list'] as &$value) {
            if ($value['g_goodsBox'] == -1) {
                $value['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($value['g_goodsBox']);
                $value['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }
        }

        $this->responseJSON($resMall);
    }

    /**
     * 获取秒杀商品列表
     * @param unknown $params
     */
    public function getListsSecKill($params)
    {
        $params['status'] = app()->request()->params('status', 3);
//        $params['type'] = app()->request()->params('type', 4);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        $params['seck_order']  = app()->request()->params('seck_order', true);
        if (isset($params['recentDay'])) {
            $params['onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }
       
        return $this->goodsLib->itemSecKillQuery($params);
//        return $this->goodsLib->itemQuery($params);
    }

    /**
     * 获取商品列表
     * @param unknown $params
     */
    public function getListsCut($params)
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['type'] = app()->request()->params('type', 6);
        $params['openId'] = app()->request()->params('openId', 0);
//        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }

        return $this->goodsLib->itemCutQuery($params);
    }

    /**
     * 获取帮助砍价列表
     */
    public function getListsOpenidCut()
    {
        $params['ugc_id'] = app()->request()->params('ugc_id', 0);
        $params['isList'] = app()->request()->params('isList', 1);

        $this->responseJSON($this->goodsLib->itemHelpCutPost($params));
    }

    /**
     * 新增或修改收货地址
     */
    public function openIdAddress()
    {
        $params = app()->request()->params();
        $params['isOpenId'] = 1;
        //新增时判断
        if (!isset($params['id'])) {

            $provinces = \Lib\Common\Region::getProvinces();
            $provinceCode = app()->request()->params('provinceCode', '');
            if (!$provinceCode) {
                throw new \Exception("收件人所在省份必须！");
            }
            if (!isset($provinces[$provinceCode])) {
                throw new \Exception("收件人所在省份编码错误！");
            }
            $citys = \Lib\Common\Region::getCities($provinceCode);
            $cityCode = app()->request()->params('cityCode', '');
            if (is_array($citys)) {
                if (!$cityCode) {
                    throw new \Exception("收件人所在市必须！");
                }
                if (!isset($citys[$cityCode])) {
                    throw new \Exception("收件人所在市编码错误！");
                }
            }
        }

        $userAddressLib = new \Lib\Mall\UserAddress();
        $resMall = $userAddressLib->add($params);
        $aid = $resMall;
        $this->responseJSON([
            'aid' => $aid
        ]);
    }

    /**
     * @Summary :免费拿（发起砍价）
     * 1：是否关注
     * 2：是否可以参与
     * 3：参与：增加砍价记录，并注意砍价记录的状态
     * @Author yyb update at 2018/5/3 15:10
     */
    public function joinCutGoods()
    {
        $params['openId'] = app()->request()->params('openId', 0);
        $params['g_id'] = app()->request()->params('g_id', 0);
        $params['isReset'] = app()->request()->params('isReset', 0);

        $res = $this->goodsLib->itemCutPost($params);

        $this->responseJSON($res);
    }

    /**
     * @Summary :免费拿（发起砍价）
     * 1：是否关注
     * 2：是否可以参与
     * 3：参与：增加砍价记录，并注意砍价记录的状态
     * @Author yyb update at 2018/5/3 15:10
     */
    public function joinHelpCutGoods()
    {
        $params['openId'] = app()->request()->params('openId', 0);
        $params['g_id'] = app()->request()->params('g_id', 0);
        $params['ugc_id'] = app()->request()->params('ugc_id', 1);

        $logCount=app('mysqlbxd_mall_user')->fetchColumn('select count(*) c from `user_help_cut` where g_id=:g_id and uo_openId=:uo_openId ',[
            'g_id'=>intval($params['g_id']),
            'uo_openId'=>$params['openId'],
        ]);
        if($logCount>0){
            throw new ServiceException('您已砍过此商品');
        }
        $res = $this->goodsLib->itemHelpCutPost($params);

        //发送模板消息
        $Wx = new Wx();
        $info['openId'] = $params['openId'];
        $info['g_name'] = $res['g_name'];
        $info['g_id'] = $res['g_id'];
        $info['ugc_id'] = $res['ugc_id'];
        $info['g_marketPrice'] = $res['g_marketPrice'];
        $info['uo_nickname'] = $res['uo_nickname'];
        $info['nicknameBegin'] = $res['nicknameBegin'];
        $info['ugc_nowPrice'] = $res['ugc_nowPrice'];
        $type = null;

        if (isset($res['isSendPrice']) && $res['isSendPrice'] == 1) {
            //如果达到分水岭，才发送消息
            $type = 'activity';
            $info['openId'] = $res['openIdBegin'];
            $Wx->sendHelpCutInfo($info, $type);

        } else if (isset($res['isEnd']) && $res['isEnd'] == 1) {
            $type = 'end';
            $info['openIdAll'] = $res['openIdAll'];
            $info['openId'] = $res['openIdBegin'];

            $Wx->sendHelpCutInfo($info, $type);
        }
        $this->responseJSON($res);
    }

    /**
     * 看了又看
     */
    public function seeAgainQuery()
    {
        $params = app()->request()->params();
        $is_own_shop = app()->request()->params('is_own_shop', 0);
        $categoryId = app()->request()->params('categoryId', 0);
        $g_id = app()->request()->params('g_id', 0);
        if ($categoryId) {
            $categoryLib = new \Lib\Mall\GoodsCategory();
            $c_info = $categoryLib->lists(['id' => $categoryId]);
            if (is_array($c_info['info'])) {
                $categoryId = $c_info['info'][0]['c_parentId'] ? $c_info['info'][0]['c_parentId'] : $categoryId;
            }
        }
        if ($is_own_shop) {  //自营商品
            $params['categoryId'] = $categoryId;
            $resMall = $this->getLists($params);
        } else {
            $params['pageSize'] = 3;
            $params['categoryId'] = $categoryId;

            $params['is_own_shop'] = 1;
            $resMall2 = $this->getLists($params);

            $params['is_own_shop'] = 0;
            $resMall3 = $this->getLists($params);
            $resMall['list'] = array_merge($resMall2['list'], $resMall3['list']);
        }

        $userLib = new \Lib\User\User();

        $userLib->extendUserInfos2Array($resMall['list'], 'g_salesId', array(
                'u_realname' => 'u_realname',
            )
        );

        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['list'] as $key => &$value) {
            if ($value['g_goodsBox'] == -1) {
                $value['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($value['g_goodsBox']);
                $value['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }
            if (!empty($g_id) && $value['g_id'] == $g_id) {
//                unset($resMall['list'][$key]);
                array_splice($resMall['list'], $key, 1);
                $resMall['count']--;
            }
            // $value['gu_realname'] = $value['u_realname'];
        }

        $this->responseJSON($resMall);
    }

    /**
     * 获取作品列表，并对数据进行处理
     */
    public function getGoodsList()
    {
        $params = app()->request()->params();
        $retList = $this->getLists($params);

        $showList = array('count' => $retList['count']);
        foreach ($retList['list'] as $key => $value) {
            if ($value['image']) {
                $flag = 0;
                foreach ($value['image'] as $vimg) {
                    if ($vimg['gi_sort'] == 0) {
                        $showList['list'][] = $vimg;
                        $flag = 1;
                        break;
                    }
                }
                if ($flag == 0) {
                    $showList['list'][] = current($value['image']);
                }
            }
        }

        $this->responseJSON($showList);
    }

    /**
     * 获取秒杀商品列表
     * @author Houbotao
     */
    public function getSeckillGoodsList()
    {
        $goods_ids = api_request(['skey' => 'seckill_goods_ids'], 'mall/setting');
        $retList = [];
        if ($goods_ids) {
            $params['id'] = app()->request()->params('id', $goods_ids);
            $retList = $this->getLists($params);
        }

        $this->responseJSON($retList);
    }

    /**
     * 获取商品列表
     * @param unknown $params
     */
    public function getLists($params)
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }

        return $this->goodsLib->itemQuery($params);
    }

    /**
     * 获取秒杀时间
     * @author Houbotao
     */
    public function getSeckillTime()
    {
        $flag = 0;
        $cur_time = time();
        $week = date('w');
        $thu = strtotime('+' . 4 - $week . ' days');
        $sat = strtotime('+' . 6 - $week . ' days');
        $next_thu = strtotime('+' . 11 - $week . ' days');

        $star_time = mktime(10, 0, 0, date('m', $thu), date('d', $thu), date('Y', $thu));
        $end_time = mktime(0, 0, 0, date('m', $sat), date('d', $sat), date('Y', $sat));
        $next_star_time = mktime(10, 0, 0, date('m', $next_thu), date('d', $next_thu), date('Y', $next_thu));

        $time = $star_time - $cur_time;
        if ($cur_time >= $star_time && $cur_time <= $end_time) {
            $flag = 1;
            $time = $end_time - $cur_time;
        }
        //时间结束后显示下期开始时间
        if (($end_time - $cur_time) <= 0) {
            $flag = 0;
            $time = $next_star_time - $cur_time;
        }

        $result = ['flag' => $flag, 'time' => $time];
        $this->responseJSON($result);
    }

    /**
     * 获取作品列表，并对数据进行处理
     */
    public function getNewGoodsList()
    {
        $params = app()->request()->params();
        //控制列表中只显示多规格指定在列表中显示的商品
        $params['list_show_status'] = 1;
        $retList = $this->getLists($params);

        $this->responseJSON($retList);
    }

    /**
     * 作品集查询
     */
    public function boxGoods()
    {
        $goodsBoxModel = new \Model\Mall\GoodsBox();

        $salesId = app()->request()->params('salesId');
        if (!$salesId) {
            throw new ServiceException("卖家id必须");
        }
        $paramsBox['u_id'] = $salesId;

        $gb_id = app()->request()->params('gb_id', '');
        if ($gb_id) {
            $paramsBox['gb_id'] = $gb_id;
        }
        $resMall = $goodsBoxModel->lists($paramsBox);

        $params = app()->request()->params();
        foreach ($resMall as $value) {
            if ($gb_id && $value['gb_id'] != $gb_id) {
                continue;
            }
            $params['goodsBox'] = $value['gb_id'];
            $boxGoodsInfo = $this->getLists($params);
            $value['goodList'] = $boxGoodsInfo && isset($boxGoodsInfo['list']) ? $boxGoodsInfo['list'] : array();
            $value['count'] = $boxGoodsInfo && isset($boxGoodsInfo['count']) ? $boxGoodsInfo['count'] : 0;
            $boxGoodsList[] = $value;
        }

        $this->responseJSON($boxGoodsList);
    }

    /**
     * 修改宝贝所属作品集
     * @param  where_g_id  需修改的商品id
     * @param  g_goodsbox
     */
    public function updateGoodsBox()
    {
        $goodsBox = app()->request()->params('g_goodsBox');
        if (!$goodsBox) {
            throw new ServiceException("作品集必须");
        }
        $data['g_goodsBox'] = $goodsBox;

        $where_g_id = app()->request()->params('where_g_id');
        if (!$where_g_id) {
            throw new ServiceException("作品id必须");
        }
        $data['where_g_id'] = $where_g_id;

        $goodsLib = new \Lib\Mall\Goods();
        $ret = $goodsLib->updateGoodsBox($data);
        $this->responseJSON($ret);
    }

    //我发布的商品
    public function my()
    {
        $data = ['list' => [], 'count' => 0];
        $params = app()->request()->params();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $pageIndex = $page <= 1 ? 0 : $page - 1;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] : 20;
        //作品类型
        $g_goods_type = isset($params['g_goods_type'])&&!empty($params['g_goods_type'])?$params['g_goods_type']:'';
        $condition['uid']    =  $this->uid;
        $g_goods_type?$condition['g_goods_type']    =  $g_goods_type:null;
        $myGoodsList = ItemManager::getMyList($pageIndex, $pageSize, $condition);

        if ($myGoodsList) {
            foreach ($myGoodsList as $goodsData) {
                $item = [];
                $item['type'] = $goodsData['type'];
                $item['g_id'] = $goodsData['g_id'];
                $item['g_name']= $goodsData['g_name'];
                $item['g_goods_type']  = isset($goodsData['g_goods_type'])?$goodsData['g_goods_type']:2;
                $item['g_is_sell']  = isset($goodsData['g_is_sell'])?$goodsData['g_is_sell']:1;
                $item['g_price_type']  = isset($goodsData['g_price_type'])?$goodsData['g_price_type']:1;
                $item['g_is_make']  = isset($goodsData['g_is_make'])?$goodsData['g_is_make']:0;
                $surfaceImgJson = $goodsData['g_surfaceImg'];
                $surfaceImgData = json_decode($surfaceImgJson, true);
                $imagePath = empty($surfaceImgData['gi_img']) ? '' : $surfaceImgData['gi_img'];
                $item['g_surfaceImg'] = [
                    'gi_img' => FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images'),
                    'gi_width' => $surfaceImgData['gi_width'],
                    'gi_height' => $surfaceImgData['gi_height']
                ];
                $item['g_status'] = $goodsData['g_status'];

                if ($goodsData['type'] == 2) {
                    $isExistsNotPass = false;
                    //多规格商品集
                    $item['categoryName'] = '';
                    $goodsTypeList = ItemManager::getGoodsList(['gc_id' => $goodsData['g_id']]);
                    if (empty($goodsTypeList)) {
                        $data['g_price'] = '';
                    } else {
                        $goodsTypePriceArray = array_column($goodsTypeList, 'g_price');
                        sort($goodsTypePriceArray);
                        if ($goodsTypePriceArray[0] == end($goodsTypePriceArray)) {
                            $item['g_price'] = $goodsTypePriceArray[0];
                        } else {
                            $item['g_price'] = $goodsTypePriceArray[0] . '~' . end($goodsTypePriceArray);
                        }
                        //获取多规格商品集下，是否有审核不通过的商品
                        foreach ($goodsTypeList as $goodsTypeItem) {
                            if ($goodsTypeItem['g_status'] == 2) {
                                $isExistsNotPass = true;
                                break;
                            }
                        }
                    }
                    $item['isExistsNotPass'] = $isExistsNotPass;
                } else {
                    $item['categoryName'] = CategoryManager::getNameByCategoryId($goodsData['g_categoryId']);
                    $item['g_price'] = $goodsData['g_price'];
                    $item['isExistsNotPass'] = $goodsData['g_status'] == 2;
                    $item['g_not_pass_reason'] = $goodsData['g_not_pass_reason'];
                }

                $data['list'][] = $item;
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 列表商品，用于首页
     */
    public function lists()
    {
        $params = app()->request()->params();
        $resMall = $this->goodsLib->lists($params);

        if ($resMall) {

            $userLib = new \Lib\User\User();

            $userLib->extendUserInfos2Array($resMall, 'g_salesId', array(
                    'u_realname' => 'u_realname',
                )
            );

            foreach ($resMall as $k => &$v) {
                if ($this->uid && $v['g_id']) {
                    $isLike = false;
                    $isLike = $this->goodsLikeLogModel->findByUidGcId($this->uid, $v['g_id']);
                    $v['itemCurrentUserLikeInfo'] = empty($isLike) ? null : $isLike;

                    $wnum = $v['g_browseTimes'] / 10000;
                    if ($wnum >= 1) {
                        $v['g_browseTimes'] = intval($wnum) . '万';
                    }
                }
            }
        }

        $this->responseJSON($resMall);
    }

    /**
     * 列表商品，用于微信公众号首页
     */
    public function listsWxH5()
    {
        $params = app()->request()->params();
        $params['pageSize'] = 10;
        $params['page'] = app()->request()->params('page', 1);
//        $params['sortPrice'] = app()->request()->params('sortPrice', '');
//        sortPrice lowPrice highPrice
        //从分类查询商品
        $data=[];
        if(isset($params['categoryGroupIds']) && $params['categoryGroupIds']){
            $cgIds=explode(',',$params['categoryGroupIds']);
            $cgIdsArr=[];
            foreach ($cgIds as $tmpcid){
                $tmpcid=intval($tmpcid);
                if($tmpcid && $tmpcid>0){
                    $cgIdsArr[]=$tmpcid;
                }
            }
            if($cgIdsArr){
                if($goodsIdsList=app('mysqlbxd_mall_common')->select("select g_id from category_group_goods where cg_id in(".implode(',',$cgIdsArr).") order by cgg_sort")){
                    $goodsIdsTmp = array_unique(array_column($goodsIdsList,'g_id'));
                    if($availableList=app('mysqlbxd_mall_user')->select("select g_id from goods  where g_status=3  and g_type=1 and is_own_shop=1  and g_stock>0 and g_id in(".implode(',',$goodsIdsTmp).")")){
                        $availableGids=array_column($availableList,'g_id');
                        $params['id']=implode(',',$availableGids);
                        $pagedGoodsIds=[];
                        if(!isset($params['sortPrice'])){
                            foreach ($goodsIdsTmp as $tmpi=>$tmpgid){
                                if(!in_array($tmpgid,$availableGids)){
                                    unset($goodsIdsTmp[$tmpi]);
                                }
                            }
                            $goodsIdsTmp=array_values($goodsIdsTmp);
                            $params['id']=implode(',',$goodsIdsTmp);
                        }
//                        var_dump($pagedGoodsIds);exit;
                        $resMall = $this->goodsLib->itemQuery($params);
                        if ($resMall && $resMall['count']>0) {
                            foreach ($resMall['list'] as &$v) {
                                if ($v['g_id']) {
                                    $wnum = $v['g_browseTimes'] / 10000;
                                    if ($wnum >= 1) {
                                        $v['g_browseTimes'] = intval($wnum) . '万';
                                    }
                                    $data[]=[
                                        'g_id' => $v['g_id'],
                                        'g_name' => $v['g_name'],
                                        'g_activityPrice' => $v['g_activityPrice'],
                                        'g_slogan' => $v['g_slogan'],
                                        'g_surfaceImg' => $v['g_surfaceImg'],
                                        'is_own_shop' => $v['is_own_shop'],
                                        'g_price'=>$v['g_price'],
                                        'categoryName2'=>$v['categoryName2'],
                                        'isSecKill'=>$v['isSecKill'],
                                    ];
                                }
                            }
                            if($pagedGoodsIds){
                                $data=array_column($data,null,'g_id');
                                $data2=[];
                                foreach ($pagedGoodsIds as $tmpGid){
                                    if(isset($data[$tmpGid])){
                                        $data2[]=$data[$tmpGid];
                                    }
                                }
                                $data=$data2;
                            }
                        }
                    }
                }
            }
        }
        $this->responseJSON($data);
    }
    /**
     * 商品详情
     */
    public function detail()
    {
        $params = app()->request()->params();
        if (!$params['id']) {
            throw new ParamsInvalidException("商品id必须");
        }
        $g_id = $params['id'];
        $resMall = $this->goodsLib->detailGet($params);
        if (empty($resMall['item'][0]['gu_authorIntroduction'])) {
            $settingModel = new \Model\User\Setting();
            $resMall['item'][0]['gu_authorIntroduction'] = $settingModel->settingGetValue($resMall['item'][0]['g_salesId'], 'introduction');
        }
        $isCurrentUserFavoite = $isCurrentUserAttention = $itemCurrentUserLikeInfo = false;
        if ($this->uid && $g_id) {
            $favModel = new \Model\User\Favorite();
            $friendsModel = new \Model\Friends\Friends();
            $isCurrentUserFavoite = $favModel->oneByUfavObjectId($this->uid, $g_id);

            if ($isCurrentUserFavoite && $isCurrentUserFavoite['ufav_type'] == 0) {
                $isCurrentUserFavoite['ufav_content'] = json_decode($isCurrentUserFavoite['ufav_content'], true);
            }
            $itemCurrentUserLikeInfo = $this->goodsLikeLogModel->findByUidGcId($this->uid, $g_id);

            $isCurrentUserAttention = $friendsModel->relation($this->uid, $resMall['item'][0]['g_salesId']);
        }

        $resMall['item'][0]['g_provinceName'] = \Lib\Common\Region::getRegionNameByCode($resMall['item'][0]['g_provinceCode']);
        $resMall['item'][0]['g_cityName'] = \Lib\Common\Region::getRegionNameByCode($resMall['item'][0]['g_cityCode']);
        $resMall['item'][0]['g_areaName'] = \Lib\Common\Region::getRegionNameByCode($resMall['item'][0]['g_areaCode']);

        $resMall['itemCurrentUserFavoiteInfo'] = empty($isCurrentUserFavoite) ? null : $isCurrentUserFavoite;
        $resMall['itemCurrentUserAttentionInfo'] = ($isCurrentUserAttention === false) ? null : $isCurrentUserAttention;
        $resMall['itemCurrentUserLikeInfo'] = empty($itemCurrentUserLikeInfo) ? null : $itemCurrentUserLikeInfo;

        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['item'] as &$v) {
            $time = strtotime($v['g_onShowDate']);
            $v['displayTime'] = date_format_to_display($time);
            if ($v['g_goodsBox'] == -1) {
                $v['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($v['g_goodsBox']);
                $v['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }
            if(!$v['g_surfaceImg'] || !isset($v['g_surfaceImg']['gi_img']) || !$v['g_surfaceImg']['gi_img']){
                foreach ($resMall['itemImage'] as $iKey=>$img){
                    if($img['g_id']==$v['g_id']){
                        $v['g_surfaceImg']=$img;
//                        unset($resMall['itemImage'][$iKey]);
                        break;
                    }
                }
                $resMall['itemImage']=array_filter(array_values($resMall['itemImage']));
            }
        }

        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($resMall['item'], 'g_salesId', array(
            'u_nickname' => 'g_nickname',
            'u_realname' => 'g_realname',
            'u_avatar' => 'g_avatar',
            'ue_imId' => 'ue_imId',
            'ue_imPassword' => 'ue_imPassword',
            'u_phone' => 'u_phone',
//            'uce_photoContract' => 'uce_photoContract',
        ));
        if (empty($resMall['item'][0]['ue_imId']) && ($resMall['item'][0]['u_phone'] == '12300000000')) {
            $resMall['item'][0]['ue_imId'] = conf('app.kefu_imId');
        }

        //资质证书
        $Certification = new \Model\User\Certification();
        $CertificationInfo = $Certification->getInfo($resMall['item'][0]['g_salesId']);
        $resMall['item'][0]['uce_photoContract'] = $CertificationInfo['uce_photoContract'] . 'abc';

        list($likesRow, $likesCount) = $this->goodsLikeLogModel->lists(['g_id' => $g_id]);
        // 作品数量
        $goodsNum = $userLib->getUserExtend($resMall['item'][0]['g_salesId']);
        $resMall['item'][0]['saler_goodsNum'] = $goodsNum['list']['ue_goodsNum'];
        $resMall['item'][0]['ue_is_own_shop'] = $goodsNum['list']['is_own_shop'];

        $fans = new \Controller\User\Friends();
        $retFans = $fans->getRelateList(1, $resMall['item'][0]['g_salesId']);
        $resMall['item'][0]['saler_fans'] = $retFans['num'];

        //作家认证信息
        $certificationModel = new \Model\User\Certification();
        $certificationInfo = $certificationModel->getCertInfo($resMall['item'][0]['g_salesId']);
        $resMall['item'][0]['saler_certification'] = empty($certificationInfo) ? null : $certificationInfo['uce_isCelebrity'];

        //处于秒杀价时控制数量
        $nowDate = date('Y-m-d H:i:s');
        if ($resMall['item'][0]['g_secKillStart'] < $nowDate && $resMall['item'][0]['g_secKillEnd'] > $nowDate) {
            $resMall['item'][0]['isSecKill'] = 1;
            $resMall['item'][0]['remainTime'] = date('d天H小时i分钟', strtotime($resMall['item'][0]['g_secKillEnd']) - strtotime(date('Y-m-d H:i:s')));
        } else {
            $resMall['item'][0]['isSecKill'] = 0;
        }

        //判断是否可以分销
        $user_lib = new \Lib\User\User();
        $params['uid'] = $this->uid;
        $params['gid'] = $resMall['item'][0]['g_id'];
        $params['action'] = 'query';
        $resMallUser = $user_lib->distribution($params);
        if ($resMallUser['list']) {
            //已上架
            $resMall['item'][0]['isShowInShop'] = 1;
        } else {
            //未上架
            $resMall['item'][0]['isShowInShop'] = 0;
        }
        //得到当前登录用户的分销比例
        $resMall['item'][0]['commissionRate'] = $this->goodsLib->getCommissionRate($this->uid);
        $resMall['typeGoodsList'] = [];
        //多规格商品集显示价格区间
        $resMall['typeGoodsListPrice'] = '';
        //多规格商品集商品
        if (!empty($resMall['item'][0]['gc_id'])) {
            //规格描述
            $GoodsTypeData = GoodsCollectionManager::getGoodsCollectionGoodsOne($g_id);
            $resMall['item'][0]['goodsTypeDesc'] = $GoodsTypeData['gcg_typeDesc'];
            //多规格
            $goodsTypeList = GoodsCollectionManager::getGoodsList($resMall['item'][0]['gc_id'], ['g_status' => 3]);
            if ($goodsTypeList) {
                if ($resMall['item'][0]['isSecKill']) {
                    $minPrice = $maxPrice = $resMall['item'][0]['g_activityPrice'];
                } else {
                    $minPrice = $maxPrice = $resMall['item'][0]['g_price'];
                }
                foreach ($goodsTypeList as $goodsTypeItem) {
                    $surfaceImgJson = $goodsTypeItem['g_surfaceImg'];
                    $surfaceImgData = json_decode($surfaceImgJson, true);
                    $imagePath = empty($surfaceImgData['gi_img']) ? '' : $surfaceImgData['gi_img'];
                    $resMall['typeGoodsList'][] = [
                        'typeName' => $goodsTypeItem['gcg_typeName'],
                        'typeGoodsId' => $goodsTypeItem['g_id'],
                        'typeImage' => FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images'),
                    ];
                    if ($goodsTypeItem['g_secKillStart'] < $nowDate && $goodsTypeItem['g_secKillEnd'] > $nowDate) {
                        $currentTypeGoodsPrice = $goodsTypeItem['g_activityPrice'];
                    } else {
                        $currentTypeGoodsPrice = $goodsTypeItem['g_price'];
                    }
                    if ($currentTypeGoodsPrice < $minPrice) {
                        $minPrice = $currentTypeGoodsPrice;
                    }
                    if ($currentTypeGoodsPrice > $maxPrice) {
                        $maxPrice = $currentTypeGoodsPrice;
                    }
                }
                $resMall['typeGoodsListPrice'] = ($minPrice == $maxPrice ? '' : ('￥' . $minPrice . '-' . $maxPrice));
            }
        }
        //优惠券标识
        $resMall['item'][0]['voucherType'] = array_values(array_unique($this->goodsLib->getVoucherType($g_id)));
        $userLib->extendUserInfos2Array($likesRow, 'u_id', array(
            'u_avatar' => 'gll_avatar',
        ));
        //是否显示商品参数
        $categoryListData = CategoryManager::getCategoryByCategoryId($resMall['item'][0]['g_categoryId']);
        if (in_array($resMall['item'][0]['g_categoryId'], GoodsCategory::SHOW_GOODS_ATTR_IDS)
            || (!empty($categoryListData[0]['c_parentId']) && in_array($categoryListData[0]['c_parentId'], GoodsCategory::SHOW_GOODS_ATTR_IDS))
        ) {
            $resMall['item'][0]['isShowGoodsAttr'] = 1;
        } else {
            $resMall['item'][0]['isShowGoodsAttr'] = 0;
        }

        $resMall['itemLikes']['list'] = $likesRow;
        $resMall['itemLikes']['count'] = $likesCount;

        $this->responseJSON($resMall);
    }

    /**
     * 获取商品可领取优惠券
     * @throws ParamsInvalidException
     */
    public function getCanReceiveVoucher()
    {
        $data = [];
        $goodsId = app()->request()->params('goodsId');
        if (empty($goodsId)) {
            throw new ParamsInvalidException("商品id必须");
        }
        $list = $this->goodsLib->getCanReceiveVoucher($goodsId);
        if ($list) {
            foreach ($list as $item) {
                //可领取
                $status = 1;
                //剩余可领取次数
                $remainingTimes = $item['v_t_eachlimit'];
                if ($this->uid) {
                    if ($item['v_t_total'] <= $item['v_t_giveout']) {
                        //已抢光
                        $status = 0;
                        $remainingTimes = 0;
                    } else {
                        $voucherList = VoucherManager::getVoucherList(['v_t_id' => $item['v_t_id'], 'u_id' => $this->uid]);
                        if ($voucherList) {
                            $voucherCount = count($voucherList);
                            $remainingTimes = $item['v_t_eachlimit'] - $voucherCount;
                            $remainingTimes = $remainingTimes <= 0 ? 0 : $remainingTimes;
                            if ($remainingTimes == 0) {
                                //已领取
                                $status = 2;
                            }
                        }
                    }
                }
                //优惠券使用描述
                if ($item['v_t_type'] == 1) {
                    $useDesc = '立减' . intval($item['v_t_price']) . '元';
                } elseif ($item['v_t_type'] == 2) {
                    $useDesc = '满' . intval($item['v_t_limit']) . '元可用';
                } elseif ($item['v_t_type'] == 3) {
                    $useDesc = '立减券';
                } else {
                    $useDesc = '优惠券';
                }
                //优惠券有效期
                if (!empty($item['v_t_expiry_date']) && $item['v_t_expiry_date'] > 0) {
                    $validityPeriodDesc = '自领取之日起' . $item['v_t_expiry_date'] . '天内有效';
                } else {
                    $validityPeriodDesc = date('Y-m-d', $item['v_t_start_date']) . ' - ' . date('Y-m-d', $item['v_t_end_date']);
                }
                $data[] = [
                    'v_t_id' => $item['v_t_id'],
                    'v_t_title' => $item['v_t_title'],
                    'v_t_desc' => $item['v_t_desc'],
                    'v_t_use_desc' => $useDesc,
                    'v_t_price' => $item['v_t_price'],
                    'v_t_type' => $item['v_t_type'],
                    'v_t_status' => $status,
                    'remainingTimes' => $remainingTimes,
                    'validityPeriodDesc' => $validityPeriodDesc
                ];
            }
        }

        $this->responseJSON($data);
    }

    /**
     * 商品状态更新
     */
    public function updateStatus()
    {
        $uid = $this->uid;
        $status = app()->request()->params('status');
        if (!in_array($status, [
            0,
            3
        ])) {
            throw new ServiceException("status类型不符合标准" . $status);
        }
        $g_ids_str = app()->request()->params('ids');
        $g_ids = explode(',', $g_ids_str);
        $g_ids = array_filter($g_ids);
        foreach ($g_ids as $g_id) {
            $gQueryInfo = $this->goodsLib->itemQuery(
                array(
                    'id' => $g_id,
                    'salesId' => $uid
                ));
            if (!isset($gQueryInfo['count']) || $gQueryInfo['count'] < 1) {
                throw new ServiceException("id:{$g_id},商品不存在");
            }
        }
        $params = app()->request()->params();
        $resMall = $this->goodsLib->updateStatus($params);

        //$userExtend = new \Model\User\Extend();
        //$userExtend->change($this->uid);

        $this->responseJSON($resMall);
    }

    /** 删除商品（伪删除）
     * @throws ServiceException
     */
    public function delete()
    {
        $uid = $this->uid;
        $g_ids_str = app()->request()->params('ids');
        $g_ids = explode(',', $g_ids_str);
        $g_ids = array_filter($g_ids);

        foreach ($g_ids as $g_id) {
            $gQueryInfo = $this->goodsLib->itemQuery(
                array(
                    'id' => $g_id,
                    'salesId' => $uid
                ));

            if (!isset($gQueryInfo['count']) || $gQueryInfo['count'] < 1) {
                throw new ServiceException("id:{$g_id},商品不存在");
            }
            if ($gQueryInfo['list'][0]['g_status'] == 4) {
                throw new ServiceException("id:{$g_id},商品已售出，不能删除");
            }
        }
        $params = app()->request()->params();
        $params['status'] = 5;
        $resMall = $this->goodsLib->updateStatus($params);

        //$userExtend = new \Model\User\Extend();
        //$userExtend->change($this->uid);

        $this->responseJSON($resMall);
    }

    /**
     * 点赞
     *
     * @throws ModelException
     */
    public function like()
    {
        $g_id = app()->request()->params('id');
        if (!$g_id) {
            throw new ParamsInvalidException("缺少参数id");
        }

        $u_id = $this->uid;
        $info = $this->goodsLib->itemQuery(['id' => $g_id]);

        if (is_array($info)) {
            if ($info['count'] <= 0) {
                throw new ServiceException("商品已失效");
            }
            $like = $info['list'][0]['g_likeCount'] + 1;
        } else {
            throw new ServiceException("内部服务错误:点赞时获取商品信息失败");
        }
        $this->goodsLikeLogModel->add($u_id, $g_id);
        $this->goodsLib->goodsLike(['id' => $g_id, 'likeCount' => $like]);
        //点赞加积分
        (new \Lib\User\UserIntegral())->addIntegral($u_id,\Lib\User\UserIntegral::ACTIVITY_GOODS_LIKE_ADD);
        $this->responseJSON(true);
    }

    /**
     * 取消点赞
     *
     * @throws ModelException
     */
    public function unlike()
    {
        $g_id = app()->request()->params('id');
        if (!$g_id) {
            throw new ParamsInvalidException("缺少参数");
        }
        $u_id = $this->uid;
        $info = $this->goodsLib->itemQuery(['id' => $g_id]);

        if (is_array($info)) {
            if ($info['count'] <= 0) {
                throw new ServiceException("商品已失效");
            }
            $like = $info['list'][0]['g_likeCount'];

            if ($like > 0) {
                $like = $like - 1;
            }
        } else {
            throw new ServiceException("内部服务错误:点赞时获取商品信息失败");
        }
        $this->goodsLikeLogModel->remove($u_id, $g_id);
        $this->goodsLib->goodsLike(['id' => $g_id, 'likeCount' => $like]);
        $this->responseJSON(true);
    }

    /**
     * 商品证书
     */
    public function goodsCredential()
    {
        $showData = array();
        $g_id = app()->request()->params('gid');
        if (!$g_id) {
            throw new ParamsInvalidException("缺少参数");
        }

        $data['id'] = $g_id;
        $resMall = $this->goodsLib->detailGet($data);
        if ($resMall) {
            $goodsMsg = current($resMall['item']);

            $showData['g_id'] = $goodsMsg['g_id'];
            $showData['g_sn'] = $goodsMsg['g_sn'];
            $showData['g_name'] = $goodsMsg['g_name'];
            $showData['gu_realname'] = $goodsMsg['gu_realname'];
            $showData['g_width'] = $goodsMsg['g_width'];
            $showData['g_high'] = $goodsMsg['g_high'];
            $showData['g_madeTime'] = $goodsMsg['g_madeTime'];
            $showData['itemImage'] = current($resMall['itemImage']);

            $orderLib = new \Lib\Mall\Order();
            $orderInfo = $orderLib->query(array('orderSn' => $goodsMsg['g_sn']));
            $showData['o_receivedDate'] = isset($orderInfo['list']) && !empty($orderInfo['list']) ? $orderInfo['list']['o_receivedDate'] : '';
        }

        $this->responseJSON($showData);
    }

    /**
     * 获取精选的商品
     */
    public function getHandpickLists($params)
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['g_onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }

        return $this->goodsLib->itemHandpickQuery($params);

        /* $params = app()->request()->params();
          $params['status'] = app()->request()->params('status', 3);
          $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
          $resMall = $this->goodsLib->itemQuery($params);
          foreach ($resMall['list'] as &$value) {
          $value['g_provinceName'] = \Lib\Common\Region::getRegionNameByCode($value['g_provinceCode']);
          $value['g_areaName'] = \Lib\Common\Region::getRegionNameByCode($value['g_areaCode']);
          $value['g_cityName'] = \Lib\Common\Region::getRegionNameByCode($value['g_cityCode']);
          }

          $this->responseJSON($resMall); */
    }

    /**
     * 获取秒杀的商品
     */
    public function getSecKillLists($params)
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['type'] = app()->request()->params('type', 4);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['g_onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }

        return $this->goodsLib->itemSecKillQuery($params);

        /* $params = app()->request()->params();
          $params['status'] = app()->request()->params('status', 3);
          $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
          $resMall = $this->goodsLib->itemQuery($params);
          foreach ($resMall['list'] as &$value) {
          $value['g_provinceName'] = \Lib\Common\Region::getRegionNameByCode($value['g_provinceCode']);
          $value['g_areaName'] = \Lib\Common\Region::getRegionNameByCode($value['g_areaCode']);
          $value['g_cityName'] = \Lib\Common\Region::getRegionNameByCode($value['g_cityCode']);
          }

          $this->responseJSON($resMall); */
    }

    /**
     * 更换商品宽高（使用一次接口）
     */
    public function once()
    {

        $this->goodsLib->once();
    }

    /**
     * 更换用户的作品数量
     */
    public function changeGoodsNum()
    {
        $goodsNum = $this->goodsLib->changeGoodsNum([]);
        if ($goodsNum) {
            //批量修改用户作品数
            $userLib = new \Lib\User\User();
            $userModel = new \Model\Mall\Goods();
            foreach ($goodsNum as $info) {
                $params['uid'] = $info['g_salesId'];
                $params['count'] = $info['count'];
                if ($info['count'] > 0) {
                    $userLib->updateUserGoodsNum($params);
                }
                $browse_num = $userModel->getBrowseNum($info['g_salesId']);
                $params['browseNum'] = $browse_num;
                $like_num = $userModel->getLikeNum($info['g_salesId']);
                $params['likeNum'] = $like_num;

                $userLib->updateUserGoodsNum($params);
            }
        }
        $this->responseJSON(["ok"]);
    }

    /**
     * 商品描述举例
     */
    public function goodsDes()
    {
        $info = config('goodsDes');
        if ($info) {
            $list = array();
            foreach ($info as $key => $value) {
                $arr['category'] = $key;
                $arr['description'] = $value;
                $list[] = $arr;
            }
        }

        $this->responseJSON($list);
    }

    /**
     * 单商品购买时确认商品信息的接口
     */
    public function singleGoodsConfirm()
    {
        $gid = app()->request()->params('gid', '');
        $count = app()->request()->params('count', 1);
        $orderDistributionType = app()->request()->params('orderDistributionType', 0);
        if (!$gid) {
            throw new ParamsInvalidException("缺少参数");
        }
        $params['id'] = $gid;
        $goodsInfo = $this->goodsLib->detailGet($params);
        if($goodsInfo && $orderDistributionType == 1 && $goodsInfo['item'][0]['g_distribution_status'] !=1 ) {
           // throw  new ServiceException('此商品为非分销商品');
        }

        $itemAttr = $goodsInfo['itemAttr'];
        $image = $goodsInfo['itemImage'];
        if ($goodsInfo['item']) {
            $userLib = new \Lib\User\User();
            $userLib->extendUserInfos2Array($goodsInfo['item'], 'g_salesId', array(
                'u_nickname' => 'ucart_nickname',
                'u_realname' => 'ucart_realname',
                'u_avatar' => 'ucart_avatar',
            ));
        }

        $goods_ids_tmp = [];
        $voucherLib = new \Lib\Mall\Voucher();
        $t_list = $voucherLib->templateLists(['v_t_state' => 1, 'pageSize' => 100]);
        foreach ($t_list['list'] as $val) {
            if (!empty($val['v_t_limit_ids'])) {
                $goods_ids_tmp = array_merge($goods_ids_tmp, explode(',', $val['v_t_limit_ids']));
            }
        }
        $goods_ids_arr = array_unique($goods_ids_tmp);
        //用户分销传艺人身份
        $userExtendData = \Rest\User\Facade\UserManager::getOneUserExtend($this->uid);
        $userDistributionType = empty($userExtendData['u_distribution_type']) ? 0 : 1;
        $info = $goodsInfo['item'];

        foreach ($info as $v) {
            $data = array();
            if($orderDistributionType == 1) {
                    //价格是0  (未设置分销价格)
                    if($v['g_distribution_price']==0){
                        $data['ucart_goodsPrice'] = $v['g_price'];
                    }else{
                        //传艺人(VIPPrice)           取商品促销价格的85%
                        if ($userDistributionType == 1) {
                            $data['ucart_goodsPrice']   =  round($v['g_distribution_price'] * 85 / 100, 2);
                        }else{
                         //非传艺人 按照 分销价
                            $data['ucart_goodsPrice']   =  $v['g_distribution_price'];
                        }
                    }

//                $v['g_price'] = $v['g_distribution_price'];
                $data['goodsDiscountAmount'] = CommonManager::getGoodsDiscountAmount($goodsInfo, $userDistributionType, $orderDistributionType);
            }else {
                //处于秒杀价时:控制数量，改变价格
                $nowDate = date('Y-m-d H:i:s');
                if ($v['g_secKillStart'] < $nowDate && $v['g_secKillEnd'] > $nowDate) {
                    //如果提交的数量大于该商品可以允许单次购买的数量
                    if ($count > $v['g_secKillNum']) {
                        throw new ServiceException("超出限购数量");
                    }

                    //把秒杀商品价格存入购物车
                    $v['g_price'] = $v['g_activityPrice'];
                    $data['isSecKill'] = 1;
                } else {
                    $data['isSecKill'] = 0;
                }
                $data['goodsDiscountAmount'] = 0;
                $data['ucart_goodsPrice'] = $v['g_price'];
            }

            $data['g_freightFee'] = $v['g_freightFee'];
            $data['g_isFreightFree'] = $v['g_isFreightFree'];
            $data['u_id'] = $this->uid;
            $data['g_id'] = $v['g_id'];
            $data['g_sn'] = $v['g_sn'];
            $data['g_name'] = $v['g_name'];
            $data['g_type'] = $v['g_type'];
            $data['g_stock'] = $v['g_stock'];
            if (!$v['g_stock']) {
                $data['ucart_goodsNum'] = 0;
            } else {
                $data['ucart_goodsNum'] = $count;
            }


            $data['ucart_goodsSaleUid'] = $v['g_salesId'];
            $data['ucart_time'] = $v['g_createDate'];
            $data['ucart_goodsCategoryName'] = $v['categoryName'] ? $v['categoryName'] : '';
            $data['ucart_goodsHigh'] = $v['g_high'];
            $data['ucart_goodsWidth'] = $v['g_width'];
            $data['ucart_goodsMadeTime'] = $v['g_madeTime'];
            $data['ucart_nickname'] = $v['ucart_nickname'];
            $data['ucart_realname'] = $v['ucart_realname'];
            $data['ucart_avatar'] = $v['ucart_avatar'];
            if (in_array($v['g_id'], $goods_ids_arr)) {
                $data['is_use_voucher'] = 1;
            }
            $data['is_own_shop'] = (int)$v['is_own_shop'];
            $certificationModel = new \Model\User\Certification();
            $data['certification'] = $certificationModel->getType($v['g_salesId']);
            $data['image'] = $image;
            $data['itemAttr'] = $itemAttr;
        }
        $this->responseJSON(array(
            'rows' => ($data),
            'totalCount' => 1
        ));
    }

    /**
     * 自营商品专区
     */
    public function ownShopGoods()
    {
        $params = app()->request()->params();
        $params['is_own_shop'] = 1;
        $params['list_show_status'] = 1;
        $resMall = $this->getLists($params);

        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($resMall['list'], 'g_salesId', ['u_realname' => 'u_realname']);
        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['list'] as &$value) {
            if ($value['g_goodsBox'] == -1) {
                $value['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($value['g_goodsBox']);
                $value['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }
            if($this->clientType==self::CLIENT_TYPE_ANDROID && $value['isSecKill']){
                $tmpActivityPrice=$value['g_activityPrice'];
                $value['g_activityPrice']=$value['g_price'];
                $value['g_price']=$tmpActivityPrice;
            }
            $itemCurrentUserLikeInfo = $this->goodsLikeLogModel->findByUidGcId($this->uid, $value['g_id']);
            $value['itemCurrentUserLikeInfo'] = empty($itemCurrentUserLikeInfo) ? null : $itemCurrentUserLikeInfo;
        }
        $this->responseJSON($resMall);
    }

    /**
     * 推荐商品
     */
    public function recommendGoods()
    {
        $goods_ids = api_request(['skey' => 'user_register_recommend_goods_ids'], 'mall/setting');
        $retList = [];
        if ($goods_ids) {
            $params['id'] = app()->request()->params('id', $goods_ids);
            $params['sortPrice'] = 2;
            $retList = $this->getLists($params);
        }
        $voucher=new \Lib\Mall\Voucher();
        if($retList['count']){
            foreach ($retList['list'] as &$v){
                $v['minVoucherPrice'] = $voucher->getMinVoucherPrice($v['g_id'],$v['g_price']);
            }
        }
        $this->responseJSON($retList);
    }

    /** 修改商品的虚拟点赞次数
     * @throws ParamsInvalidException
     * @throws ServiceException
     * @throws \Exception
     */
    public function virtualLikeCount()
    {
        $g_id = app()->request()->params('gid');
        $min = app()->request()->params('min');
        $max = app()->request()->params('max');
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 100);

        $u_id = $this->uid;

        if ($g_id) {
            $count = rand($min, $max);
            $this->queryVirtual($g_id, $count, $u_id);
        } else {
            $goodsModel = new \Model\Mall\Goods();
            $params['isHaveStock'] = 1;
            $params['status'] = 3;
            list($list, $g_count) = $goodsModel->getGoods($params, $page, $pageSize);
            if ($list) {
                foreach ($list as $good) {
                    $count = rand($min, $max);
                    $this->queryVirtual($good['g_id'], $count, '');
                }
            } else {
                throw new ServiceException("没有要修改的信息");
            }
        }

        $this->responseJSON(true);
    }

    //获取多规格商品集下所有规格商品
    public function getGoodsCollectionTypes()
    {
        $data = [];
        $goodsCollectionId = app()->request()->params('goodsCollectionId');
        if (empty($goodsCollectionId)) {
            throw new ParamsInvalidException("商品集id必须");
        }
        $goodsCollectionData = GoodsCollectionManager::getGoodsCollectionById($goodsCollectionId);
        if (empty($goodsCollectionData)) {
            throw new ParamsInvalidException("商品集不存在");
        }
        $typeGoodsData = GoodsCollectionManager::getGoodsList($goodsCollectionId);
        if ($typeGoodsData) {
            foreach ($typeGoodsData as $typeGoodsItem) {
                $surfaceImgJson = $typeGoodsItem['g_surfaceImg'];
                $surfaceImgData = json_decode($surfaceImgJson, true);
                $imagePath = empty($surfaceImgData['gi_img']) ? '' : $surfaceImgData['gi_img'];
                $data[] = [
                    'g_id' => $typeGoodsItem['g_id'],
                    'g_name' => $typeGoodsItem['gcg_typeName'],
                    'g_price' => $typeGoodsItem['g_price'],
                    'categoryName' => CategoryManager::getNameByCategoryId($typeGoodsItem['g_categoryId']),
                    'g_status' => $typeGoodsItem['g_status'],
                    'g_not_pass_reason' => $typeGoodsItem['g_not_pass_reason'],
                    'g_surfaceImg' => [
                        'gi_img' => FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images'),
                        'gi_width' => $surfaceImgData['gi_width'],
                        'gi_height' => $surfaceImgData['gi_height']
                    ]
                ];
            }
        }

        $this->responseJSON($data);
    }

    //获取商品信息
    public function getGoodsData()
    {
        $data = [];
        //是否为商品集新增规格时获取数据
        $isAddGoodsType = false;
        $goodsId = app()->request()->params('id');
        $goodsCollectionId = app()->request()->params('goodsCollectionId');
        if (empty($goodsId) && empty($goodsCollectionId)) {
            throw new ParamsInvalidException("商品id和商品集id至少有一个");
        }
        //商品集新增规格时，商品信息取最后更新的商品信息
        if (empty($goodsId)) {
            $lastUpdateGoods = GoodsCollectionManager::getGoodsCollectionGoodsList($goodsCollectionId);
            if (!empty($lastUpdateGoods)) {
                $goodsId = $lastUpdateGoods[0]['g_id'];
                $isAddGoodsType = true;
            }
        }
        $goodsData = ItemManager::getItemInfo(['g_id' => $goodsId]);
        if (empty($goodsData)) {
            throw new ParamsInvalidException("商品不存在");
        }

        $data['g_id'] = $goodsId;
        $data['g_categoryId'] = $goodsData['g_categoryId'];
        //是否显示商品参数
        $categoryListData = CategoryManager::getCategoryByCategoryId($goodsData['g_categoryId']);
        if (in_array($goodsData['g_categoryId'], GoodsCategory::SHOW_GOODS_ATTR_IDS)
            || (!empty($categoryListData[0]['c_parentId']) && in_array($categoryListData[0]['c_parentId'], GoodsCategory::SHOW_GOODS_ATTR_IDS))){
            $data['isShowGoodsAttr'] = 1;
        } else {
            $data['isShowGoodsAttr'] = 0;
        }
        $data['categoryName'] = empty($categoryListData[0]['c_name']) ? '' : $categoryListData[0]['c_name'];
        $data['gu_realname'] = $goodsData['gu_realname'];
        $data['g_provinceCode'] = $goodsData['g_provinceCode'];
        $data['g_cityCode'] = $goodsData['g_cityCode'];
        $data['g_areaCode'] = $goodsData['g_areaCode'];
        $data['g_width'] = $goodsData['g_width'];
        $data['g_high'] = $goodsData['g_high'];
        $data['g_provinceName'] = \Lib\Common\Region::getRegionNameByCode($goodsData['g_provinceCode']);
        $data['g_cityName'] = \Lib\Common\Region::getRegionNameByCode($goodsData['g_cityCode']);
        $data['g_areaName'] = \Lib\Common\Region::getRegionNameByCode($goodsData['g_areaCode']);
        $data['tags'] = $goodsData['g_tags'] ? explode(',', $goodsData['g_tags']) : null;
        $data['g_inspiration'] = $goodsData['g_inspiration'];
        $data['itemImage'] = ItemManager::getItemImageById($goodsId);
        $data['itemCarousel'] = ItemManager::getItemCarouselById($goodsId);
        $data['gc_id'] = $goodsData['gc_id'];
        $data['g_slogan'] = $goodsData['g_slogan'];
        $data['gu_ismyself'] = $goodsData['gu_ismyself'];
        $data['g_goods_type'] = $goodsData['g_goods_type'];
        $data['g_is_sell'] = $goodsData['g_is_sell'];
        $data['g_price_type'] = $goodsData['g_price_type'];
        $data['g_is_make'] = $goodsData['g_is_make'];
        $data['g_long'] = $goodsData['g_long'];
        //处理单规格与多规格商品差异
        if (empty($goodsData['gc_id'])) {
            $surfaceImgJson = $goodsData['g_surfaceImg'];
            //商品名称取商品集名称
            $data['g_name'] = $goodsData['g_name'];
            $data['g_typeName'] = null;
            $data['g_price'] = $goodsData['g_price'];
            $data['g_stock'] = $goodsData['g_stock'];
            $data['itemAttr'] = ItemManager::getItemAttrById($goodsId);
            $data['g_typeDesc'] = null;
            $data['g_typeImage'] = null;
        } else {
            //多规格商品集商品封面图片取商品集图片
            $goodsCollectionData = GoodsCollectionManager::getGoodsCollectionById($goodsData['gc_id']);
            $typeGoodsData = GoodsCollectionManager:: getGoodsCollectionGoodsOne($goodsId);
            $surfaceImgJson = $goodsCollectionData['gc_surfaceImg'];
            //商品名称取商品集名称
            $data['g_name'] = $goodsCollectionData['gc_name'];
            //规格名称、价格、库存、尺寸、规格描述、规格图片
            if ($isAddGoodsType) {
                $data['g_typeName'] = null;
                $data['g_price'] = null;
                $data['g_stock'] = null;
                $data['itemAttr'] = null;
                $data['g_typeDesc'] = null;
                $data['g_typeImage'] = null;
            } else {
                $data['g_typeName'] = $typeGoodsData['gcg_typeName'];
                $data['g_price'] = $goodsData['g_price'];
                $data['g_stock'] = $goodsData['g_stock'];
                $data['itemAttr'] = ItemManager::getItemAttrById($goodsId);
                $data['g_typeDesc'] = $typeGoodsData['gcg_typeDesc'];;
                //规格图片取商品表封面图字段
                $typeImageJson = $goodsData['g_surfaceImg'];
                $typeImageData = json_decode($typeImageJson, true);
                $imagePath = empty($typeImageData['gi_img']) ? '' : $typeImageData['gi_img'];
                $data['g_typeImage'] = [
                    'gi_img' => FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images'),
                    'gi_img_path' => $imagePath,
                    'gi_width' => $typeImageData['gi_width'],
                    'gi_height' => $typeImageData['gi_height']
                ];
            }
        }
        $surfaceImgData = json_decode($surfaceImgJson, true);
        $imagePath = empty($surfaceImgData['gi_img']) ? '' : $surfaceImgData['gi_img'];
        $data['g_surfaceImg'] = [
            'gi_img' => FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images'),
            'gi_img_path' => $imagePath,
            'gi_width' => $surfaceImgData['gi_width'],
            'gi_height' => $surfaceImgData['gi_height']
        ];

        $this->responseJSON($data);
    }

    /**
     * 微信公众号首页推荐商品
     */
    public function getWxRecommendGoods()
    {
        $data = [];
        //推荐总数量
        $recommendTotalCount = 100;
        //默认推荐商品id集合
        $defaultRecommendGoodsIds = [];
        if($recommendIdsStr = api_request(['skey'=>'wxH5_baiduMini_youxuan_201901'], 'mall/setting')){
            $defaultRecommendGoodsIds=array_filter(array_unique(explode(',',$recommendIdsStr)));
        }

        $goodsModel = new Goods();

        //1.获取用户喜好分类下的商品信息
        $goodsListData = [];
//        if ($this->uid) {
//            $hobbyData = (new Setting())->settingGetValue($this->uid, Setting::KEY_HOBBY);
//            if ($hobbyData) {
//                $hobbyCategoryData = array_slice($hobbyData, 0, $recommendTotalCount);
//                //获取分类下的商品
//                $condition = ['inStock' => 1, 'isOwnShop' => 1, 'mallGoodsStatus'=> 1];
//                foreach ($hobbyCategoryData as $hobbyCategoryItem) {
//                    //获取分类下的二级分类
//                    $childrenCategoryData = CategoryManager::getCategoryByParentId($hobbyCategoryItem['c_id']);
//                    if ($childrenCategoryData) {
//                        $childrenCategoryIds = array_column($childrenCategoryData, 'c_id');
//                        array_unshift($childrenCategoryIds, $hobbyCategoryItem['c_id']);
//                        $condition['g_categoryId'] = $childrenCategoryIds;
//                    } else {
//                        $condition['g_categoryId'] = $hobbyCategoryItem['c_id'];
//                    }
//                    list($tempGoodsListData) = $goodsModel->getList($condition, 0, 1);
//                    if ($tempGoodsListData) {
//                        $goodsListData[] = $tempGoodsListData[0];
//                    }
//                }
//            }
//        }

        //2.获取默认推荐的商品信息
        $defaultRecommendCount = $recommendTotalCount - count($goodsListData);
        if ($defaultRecommendCount >= 1) {
            //有库存、自营、指定商品id
            $condition = [
                'inStock' => 1,
                'isOwnShop' => 1,
                'mallGoodsStatus'=> 1,
                'goodsIds' => $defaultRecommendGoodsIds,
//                'excludeOnSaleGoodsIds' => array_column($goodsListData, 'g_id')
            ];
            list($defaultRecommendData) = $goodsModel->getList($condition, 0, $defaultRecommendCount);
            if ($defaultRecommendData) {
                $defaultRecommendData=array_column($defaultRecommendData,null,'g_id');
                $i=count($goodsListData);
                foreach ($defaultRecommendGoodsIds as $tmpGid){
                    if(isset($defaultRecommendData[$tmpGid]) && $i<$recommendTotalCount){
                        $goodsListData[]=$defaultRecommendData[$tmpGid];
                        $i++;
                    }
                }
            }
        }

        //3.获取价格最低的商品信息
//        $priceRecommendCount = $recommendTotalCount - count($goodsListData);
//        if ($priceRecommendCount >= 1) {
//            $condition = [
//                'inStock' => 1,
//                'isOwnShop' => 1,
//                'mallGoodsStatus'=> 1,
//                'excludeOnSaleGoodsIds' => array_column($goodsListData, 'g_id'),
//                'orderBy' => [['g_price', 'ASC'], ['g_id', 'DESC']]
//            ];
//            list($tempGoodsListData) = $goodsModel->getList($condition, 0, $priceRecommendCount);
//            $goodsListData = array_merge($goodsListData, $tempGoodsListData);
//        }

        foreach ($goodsListData as $goodsItem) {
            $data[] = $this->getGoodsItemData($goodsItem);
        }

        $this->responseJSON($data);
    }

    /**
     * 微信公众号签约艺术家（名家）的商品
     */
    public function getOwnUserGoods()
    {
        $data = ['count' => 0, 'goods_list' => []];
        $params = app()->request()->params();
        $page = isset($params['page']) ? $params['page'] : 1;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 10;
        list($count, $goodsList) = ItemManager::getOwnUserGoods($pageIndex, $pageSize);
        if ($goodsList) {
            $data['count'] = $count;
            foreach ($goodsList as $goodsItem) {
                $data['goods_list'][] = $this->getGoodsItemData($goodsItem);
            }
        }

        $this->responseJSON($data);
    }

    private function havealookGoodIds($num)
    {
        $num=intval($num);
        $ids=$gids=[];
        $maxminInfo=app('mysqlbxd_mall_user')->fetch("select max(g_id) max_gid,min(g_id) min_gid,count(*) c from goods");
//        for($i=0;$i<1000;$i++){
//            $ids[]=floor(random_int($maxminInfo['min_gid'],$maxminInfo['max_gid']));
//        }
//        if(app()->getMode()=='development'){
            $goods=app('mysqlbxd_mall_user')->select("select g_id from goods where g_type=1 and g_status=3 and g_stock>0 limit ".mt_rand(1,$maxminInfo['c']-$num).",{$num}");
//        }else{
//            $goods=app('mysqlbxd_mall_user')->select("select g_id from goods where g_id in(".implode(',',$ids).") and g_type=1 and g_status=3 and g_stock>0");
//        }
        if($goods){
            $gids=array_unique(array_merge($gids,array_column($goods,'g_id')));
        }
        while (count($gids)<$num){
            $gids=array_merge($gids,$this->havealookGoodIds($num));
        }
        return $gids;
    }

//    /**
//     * 瞅一瞅商品列表
//     */
//        public function havealookList()
//    {
//        $num=3;
//        $gIds=$this->havealookGoodIds($num);
//        $params=[
//            'id'=>implode(',',$gIds),
//            'page'=>1,
//            'pageSize'=>$num,
//        ];
//        $resMall = $this->goodsLib->itemQuery($params);
//        foreach ($resMall['list'] as $k => &$v) {
//            if ($this->uid && $v['g_id']) {
//                $isLike = false;
//                $isLike = $this->goodsLikeLogModel->findByUidGcId($this->uid, $v['g_id']);
//                $v['itemCurrentUserLikeInfo'] = empty($isLike) ? null : $isLike;
//
//                $wnum = $v['g_browseTimes'] / 10000;
//                if ($wnum >= 1) {
//                    $v['g_browseTimes'] = intval($wnum) . '万';
//                }
//            }
//        }
//        $this->responseJSON($resMall);
//    }

    private function havealookLikeData($gid)
    {
        if(!$ghal=app('mysqlbxd_app')->fetch("select g_id,ghl_like,ghl_unlike  from goods_havealook where g_id={$gid}")){
            $ghal=[
                'g_id'=>$gid,
                'ghl_like'=>0,
                'ghl_unlike'=>0,
            ];
        }
        return $ghal;
    }
    /**
     * 瞅一瞅商品点赞或踩
     * @throws ParamsInvalidException
     */
    public function havealookLike()
    {
        $goodsId = app()->request()->params('gid');
        //like unlike
        $option = app()->request()->params('option');
        if($option && !in_array($option,['like','unlike'])){
            throw new ParamsInvalidException("option格式错误");
        }
        $goodsId=intval($goodsId);
        $op='';
        if($goodsId){
            if(app('mysqlbxd_mall_user')->fetchColumn("select g_id from goods where g_id={$goodsId}")){
                if($ghal=app('mysqlbxd_app')->fetch("select g_id,ghl_like,ghl_unlike  from goods_havealook where g_id={$goodsId}")){
                    if($option=='like'){
                        $ghal['ghl_like']+=1;
                    }elseif ($option=='unlike') {
                        $ghal['ghl_unlike'] += 1;
                    }
                    if(app('mysqlbxd_app')->update('goods_havealook',$ghal,[
                        'g_id'=>$goodsId
                    ])){
                        $op='upd';
                    }
                }else{
                    $ghal=[
                        'g_id'=>$goodsId,
                        'ghl_like'=>$option=='like'?1:0,
                        'ghl_unlike'=>$option=='unlike'?1:0,
                    ];
                    if(app('mysqlbxd_app')->insert('goods_havealook',$ghal)){
                        $op='ins';
                    }
                }
            }
        }

        $ghal=(isset($ghal)&& $ghal)?$ghal:[
            'g_id'=>$goodsId,
            'ghl_like'=>0,
            'ghl_unlike'=>0
        ];
        $num=1;
        $gIds=$this->havealookGoodIds($num);
        $params=[
            'id'=>implode(',',$gIds),
            'page'=>1,
            'pageSize'=>$num,
        ];
        $resMall = $this->goodsLib->itemQuery($params);
        $recommendList=[];
        if($resMall['count']){
            foreach ($resMall['list'] as $row){
                $recommendList['g_id']=$row['g_id'];
                $recommendList['g_surfaceImg']=$row['g_surfaceImg'];
                $recommendList['ghal']=$this->havealookLikeData($row['g_id']);
            }
        }
        $result=[
            'result'=>[
                'status'=>$op,
                'gid'=>$goodsId,
                'ghal'=>$ghal,
            ],
            'recommend'=>$recommendList,
        ];
        $this->responseJSON($result);
    }
    /**
     * 获取商品数据
     * @param $goodsItem
     * @return array
     */
    private function getGoodsItemData($goodsItem)
    {
        //封面图
        $surfaceImgJson = $goodsItem['g_surfaceImg'];
        $surfaceImgData = json_decode($surfaceImgJson, true);
        $imagePath = empty($surfaceImgData['gi_img']) ? '' : $surfaceImgData['gi_img'];
        $surfaceImgData['gi_img'] = FileHelper::getFileUrl($imagePath, 'mall_goods_attr_images');
        $goodsItemData = [
            'g_id' => $goodsItem['g_id'],
            'g_name' => $goodsItem['g_name'],
            'g_price' => $goodsItem['g_price'],
            'g_activityPrice' => $goodsItem['g_activityPrice'],
            'g_slogan' => $goodsItem['g_slogan'],
            'g_surfaceImg' => $surfaceImgData,
            'is_own_shop' => $goodsItem['is_own_shop']
        ];

        // 秒杀
        $nowDate = date('Y-m-d H:i:s');
        if ($goodsItem['g_secKillStart'] < $nowDate && $goodsItem['g_secKillEnd'] > $nowDate) {
            $goodsItemData['isSecKill'] = 1;
        } else {
            $goodsItemData['isSecKill'] = 0;
        }

        return $goodsItemData;
    }

    private function queryVirtual($g_id, $count, $u_id)
    {
        $info = $this->goodsLib->itemQuery(['id' => $g_id]);

        if (is_array($info)) {
            if ($info['count'] <= 0) {
                throw new ServiceException("商品已失效");
            }
            $like = $info['list'][0]['g_likeCount_virtual'] + $count;
        } else {
            throw new ServiceException("内部服务错误:点赞时获取商品信息失败");
        }

        if ($u_id) {
            $this->goodsLikeLogModel->add($u_id, $g_id);
        }
        $this->goodsLib->goodsLike(['id' => $g_id, 'virtual_like' => $like]);
        return true;
    }

    /**
     * @throws ParamsInvalidException
     */
    public function makeGoods(){
        $params         = app()->request()->params();
        $goods_id       = app()->request()->params('goods_id','');
        $goods_explain  = app()->request()->params('goods_explain','');
        $money          = app()->request()->params('money','');
        $deliver_time   = app()->request()->params('deliver_time','');
        if(!($goods_id&&$goods_explain&&$money&&$deliver_time)){
            throw new ParamsInvalidException("参数有误");
        }
        $insert   = GoodsMake::insertMakeGoods($params);
        if(!$insert){
            throw new ServiceException("提交失败,请重新提交或联系客服");
        }
        $this->responseJSON('提交成功');
    }

    /**
     * 商品询价
     * @throws ParamsInvalidException
     */
    public function askGoodsPrice(){
        $goods_id       = app()->request()->params('goods_id',0);
        if(!$goods_id){
            throw new ParamsInvalidException("商品id不能为空");
        }
        //获取用户信息
         $user         = app('mysqlbxd_user')->fetch('select u_phone,u_nickname,u_realname from  user  where u_id=:u_id',[':u_id'=>$this->uid]);
        //获取用户真实姓名
         $userRealName = app('mysqlbxd_app')->fetchColumn('select uce_realName from  user_certification  where u_id=:u_id',[':u_id'=>$this->uid]);
        //插入数据录入
         $params['g_id']        = $goods_id;
         $params['phone']       = $user['u_phone']?$user['u_phone']:'';
         $params['nick_name']   = $user['u_nickname']?$user['u_nickname']:'';
         $params['real_name']   = $userRealName?$userRealName:$user['u_realname'];
         $params['uid']          = $this->uid;
         $result  = app('mysqlbxd_mall_user')->insert('ask_goods_price',$params);
         if(isset($result[0])&&($result[0]==1)){
             $this->responseJSON('提交成功');
         }else{
             throw new ServiceException("提交失败,请重新提交");
         }
    }
}
