<?php
/**
 * 艺术赚赚小程序
 */
namespace Controller\Wx\MiniProgram\Distribution;

use Exception\ParamsInvalidException;
use Framework\Helper\FileHelper;
use Lib\Mall\GoodsCategory;
use Model\User\Certification;
use Model\User\Distribution;
use Rest\Mall\Facade\CategoryManager;
use Rest\Mall\Facade\ItemManager;
use Rest\User\Facade\UserManager;

class Goods extends Common
{
    private $goodsModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsModel = new \Model\Mall\Goods();
    }

    /**
     * 微信艺术转转小程序   商品详情
     * @throws ParamsInvalidException
     */
    public function wxyszz_detail()
    {
        $params = app()->request()->params();
        if (empty($params['id'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $goodsId = $params['id'];
        $goodsDataList = ItemManager::getItemById($goodsId);
        if (empty($goodsDataList)) {
            throw new \Exception\ParamsInvalidException("商品不存在！");
        }
        $goodsData = $goodsDataList[0];
        $data['g_name'] = $goodsData['g_name'];
        $data['categoryName'] = CategoryManager::getNameByCategoryId($goodsData['g_categoryId']);
        //尺寸
        $data['g_width'] = $goodsData['g_width'];
        $data['g_high'] = $goodsData['g_high'];
        $data['g_name'] = $goodsData['g_name'];
        $data['g_stock'] = $goodsData['g_stock'];
        //如果是非分销产品  分销价格等于原价  （非会员价）
        if($goodsData['g_distribution_price'] == 0){
            $goodsData['g_distribution_price'] = $goodsData['g_price'];
        }
        $data['distributionPrice'] = $goodsData['g_distribution_price'];
        //会员价
        $data['vipPrice'] = $this->getGoodsVipPrice($goodsData['g_distribution_price']);
        //优惠额度
        $data['discountAmount'] = $this->getGoodsDiscountAmount($goodsData['g_distribution_price']);
        $data['g_freightFee'] = $goodsData['g_freightFee'];
        //是否显示商品参数
        $categoryListData = CategoryManager::getCategoryByCategoryId($goodsData['g_categoryId']);
        if (in_array($goodsData['g_categoryId'],
                GoodsCategory::SHOW_GOODS_ATTR_IDS) || (!empty($categoryListData[0]['c_parentId']) && in_array($categoryListData[0]['c_parentId'],
                    GoodsCategory::SHOW_GOODS_ATTR_IDS))
        ) {
            $data['isShowGoodsAttr'] = 1;
        } else {
            $data['isShowGoodsAttr'] = 0;
        }
        //商品参数
        $data['itemAttr'] = ItemManager::getItemAttrById($goodsId);
        //卖家信息
        $salesId = $goodsData['g_salesId'];
        $userData = UserManager::getUserInfoByUid($salesId);
        $userExtendData = UserManager::getOneUserExtend($salesId);
        $data['seller_data']['avatar'] = $userData['u_avatar'] ? FileHelper::getFileUrl($userData['u_avatar'], 'user_avatar') : '';
        $data['seller_data']['nickname'] = $userData['u_nickname'];
        $certificationInfo = (new Certification())->getCertInfo($goodsData['g_salesId']);
        $data['seller_data']['certification_status'] = empty($certificationInfo) ? null : $certificationInfo['uce_isCelebrity'];
        $data['seller_data']['is_own_shop'] = empty($userExtendData['is_own_shop']) ? 0 : $userExtendData['is_own_shop'];
        //商品描述
        $data['g_inspiration'] = $goodsData['g_inspiration'];
        //轮播图片
        $data['itemCarousel'] = ItemManager::getItemCarouselById($goodsId);
        //商品详情图
        $data['itemImage'] = ItemManager::getItemImageById($goodsId);

        $this->responseJSON($data);
    }


    /**
     * 商品详情   (掌玩文化商品详情)
     */
    public function detail()
    {
        $data = [];
        $params = app()->request()->params();
        if (!$params['id']) {
            throw new ParamsInvalidException("商品id必须");
        }
        //商品id
        $goodsId = $params['id'];
        $goodsLib = new \Lib\Mall\Goods();
        $resMall = $goodsLib->detailGet($params);
        if ($resMall) {
            $goodsInfo = $resMall['item'][0];
            $data['g_id'] = $goodsInfo['g_id'];
            $data['g_name'] = $goodsInfo['g_name'];
            //是否自营
            $data['is_own_shop'] = $goodsInfo['is_own_shop'];
            $data['categoryName'] = $goodsInfo['categoryName'];
            $data['g_width'] = $goodsInfo['g_width'];
            $data['g_high'] = $goodsInfo['g_high'];
            $data['g_stock'] = $goodsInfo['g_stock'];
            $data['g_price'] = $goodsInfo['g_price'];
            $data['g_marketPrice'] = $goodsInfo['g_marketPrice'];
            $data['g_secKillPrice'] = $goodsInfo['g_activityPrice'];
            //是否秒杀
            $data['g_secKillNum']=$goodsInfo['g_secKillNum'];
            $nowDate = date('Y-m-d H:i:s');
            if ($goodsInfo['g_secKillStart'] < $nowDate && $goodsInfo['g_secKillEnd'] > $nowDate) {
                $data['isSecKill'] = 1;
            } else {
                $data['isSecKill'] = 0;
            }
            //分销赚
            $data['commission'] = $this->getCommission($this->uid, $data['isSecKill'] == 1 ? $data['g_secKillPrice'] : $data['g_price']);
            //商品详情介绍
            $data['g_inspiration'] = $goodsInfo['g_inspiration'];
            //卖家信息（头像、姓名、签约状态、认证状态）
            $userInfo = (new \Lib\User\User())->getUserInfo([$goodsInfo['g_salesId']], '', 1);
            $data['g_salesId'] = $goodsInfo['g_salesId'];
            $data['u_avatar'] = current($userInfo)['u_avatar'];
            $data['u_nickname'] = current($userInfo)['u_nickname'];
            $data['ue_is_own_shop'] = current($userInfo)['user_extend']['is_own_shop'];
            //卖家认证信息
            $certificationModel = new \Model\User\Certification();
            $certificationInfo = $certificationModel->getCertInfo($goodsInfo['g_salesId']);
            if ($certificationInfo) {
                if ($certificationInfo['uce_status'] == 1) {
                    $data['saler_certification'] = (int)$certificationInfo['uce_isCelebrity'];
                } else {
                    $data['saler_certification'] = 0;
                }
            } else {
                $data['saler_certification'] = -1;
            }
            //商品评论
            //是否已分销
            $distributionModel = new Distribution();
            $data['isInShop'] = $distributionModel->getCount($this->uid, $goodsId) >= 1 ? 1 : 0;
            //封面图片
            if (!empty($goodsInfo['g_surfaceImg'])) {
                $surfaceImg = $goodsInfo['g_surfaceImg']['gi_img'];
            } else {
                $surfaceImg = empty($resMall['itemImage'][0]['gi_img']) ? '' : $resMall['itemImage'][0]['gi_img'];
            }
            $data['surfaceImageUrl'] = $surfaceImg;
            //商品图片
            $data['images'] = $resMall['itemImage'];
            $data['itemAttr'] = $resMall['itemAttr'];
            $data['g_status'] = $goodsInfo['g_status'];
        }

        $this->responseJSON($data);
    }
    
    /**
     * 获取用户店铺分销商品里列表
     */
    public function getShopGoodsList()
    {
        $pageIndex = 0;
        $pageSize = 20;
        $uid = $this->uid;
        $params = app()->request()->params();
        if (!empty($params['uid'])) {
            $uid = $params['uid'];
        }
        if (empty($uid)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (isset($params['page'])) {
            $page = intval($params['page']);
            if ($page >= 1) {
                $pageIndex = $page - 1;
            }
        }
        if (isset($params['pageSize'])) {
            $pageSize = intval($params['pageSize']);
        }

        $data = $this->getListByUser($uid, 1, $pageIndex, $pageSize, isset($params['orderByPrice']) ? $params['orderByPrice'] : null);
        $this->responseJSON($data);
    }

    /**
     * 获取我的店铺管理分销商品列表
     */
    public function getMyGoodsList()
    {
        $pageIndex = 0;
        $pageSize = 20;
        $uid = $this->uid;
        $params = app()->request()->params();
        if (empty($uid)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (isset($params['page'])) {
            $page = intval($params['page']);
            if ($page >= 1) {
                $pageIndex = $page - 1;
            }
        }
        if (isset($params['pageSize'])) {
            $pageSize = intval($params['pageSize']);
        }
        if (isset($params['isOnSale'])) {
            $isOnSale = intval($params['isOnSale']);
        } else {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        $data = $this->getListByUser($uid, $isOnSale, $pageIndex, $pageSize);
        $this->responseJSON($data);
    }
    /**
     * @throws ParamsInvalidException
     *
     */
    public function setGoodsTop()
    {
        $uid = $this->uid;
        $params = app()->request()->params();
        if (empty($params['goodsId'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (!isset($params['topStatus'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $goodsId = $params['goodsId'];
        $topStatus = $params['topStatus'];
        $goodsData = \Model\Mall\Goods::detailGet($goodsId);
        if (empty($goodsData)) {
            throw new \Exception\ParamsInvalidException("当前商品不存在！");
        }

        $distributionModel = new Distribution();
        $distributionData = $distributionModel->getOne($uid, $goodsId);
        if (empty($distributionData)) {
            throw new \Exception\ParamsInvalidException("当前商品未加入店铺！");
        }
        $updateColumn['ud_isTop'] = $topStatus == 1 ? 1 : 0;
        $updateColumn['ud_topDate'] = date('Y-m-d H:i:s');
        $distributionModel->update($distributionData['ud_id'], $updateColumn);
        $this->responseJSON(['result' => true]);
    }

    /**
     * 上架/下架商品
     */
    public function setGoodsOnSale()
    {
        $uid = $this->uid;
        $params = app()->request()->params();
        if (empty($params['goodsId'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (!isset($params['onSaleStatus'])) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $goodsId = $params['goodsId'];
        $onSaleStatus = $params['onSaleStatus'];
        $onSaleStatus = $onSaleStatus == 1 ? 1 : 0;
        $goodsData = \Model\Mall\Goods::detailGet($goodsId);
        if (empty($goodsData)) {
            throw new \Exception\ParamsInvalidException("当前商品不存在！");
        }

        //商品库存
        if ($goodsData && $onSaleStatus == 1) {
            if ($goodsData['g_status'] != 3) {
                throw new \Exception\ParamsInvalidException("当前商品状态不能上架！");
            } elseif ($goodsData['g_stock'] <= 0) {
                throw new \Exception\ParamsInvalidException("当前商品没有库存不能上架！");
            }
        }

        $isFirstOnSale = true;
        $distributionModel = new Distribution();
        $distributionData = $distributionModel->getOne($uid, $goodsId);
        if (empty($distributionData)) {
            if ($distributionModel->getListByUid($this->uid)) {
                $isFirstOnSale = false;
            }
            $distributionModel->add($this->uid, $goodsId);
        } else {
            $distributionModel->updateData($distributionData['ud_id'], $onSaleStatus == 1 ? 0 : 1);
            $isFirstOnSale = false;
        }
        $this->responseJSON(['isFirstOnSale' => $isFirstOnSale]);
    }

    private function getListByUser($uid, $isOnSale, $pageIndex, $pageSize, $orderByPrice = null)
    {
        $data = [];
        $orderByParams = [];
        if ($orderByPrice != null) {
            $orderByParams = ['g_price', $orderByPrice == 1 ? 'DESC' : 'ASC'];
        }

        //根据用户id获取该用户的商品
        $distributionModel = new Distribution();
        $goodsData = $distributionModel->getListByUid($uid, $isOnSale ? 0 : null);
        if ($goodsData) {
            $topGoodsIdArray = []; //置顶的商品id
            $userGoodsIdArray = []; //用户的所有商品
            foreach ($goodsData as $item) {
                $userGoodsIdArray[] = $item['g_id'];
                if ($item['ud_isTop'] == 1) {
                    $topGoodsIdArray[] = $item['g_id'];
                }
            }

            //$condition['g_distribution_status'] = 1;
            if ($isOnSale == 1) {
                $condition['inStock'] = 1;
            }else {
                $onSaleGoodsData = $distributionModel->getListByUid($uid, 0);
                if ($onSaleGoodsData) {
                    $condition['excludeOnSaleGoodsIds'] = array_column($onSaleGoodsData, 'g_id');
                }
            }

            $condition['goodsIds'] = $userGoodsIdArray;
            if ($topGoodsIdArray) {
                $topGoodsIds = implode(',', $topGoodsIdArray);
                $orderBy = "CASE WHEN FIND_IN_SET(g_id,'{$topGoodsIds}') THEN 1 ELSE 0 END";
                $condition['orderBy'][] = [$orderBy, 'DESC'];
                $condition['orderBy'][] = ["FIND_IN_SET(g_id,'{$topGoodsIds}')", 'ASC'];
            }
            if ($orderByParams) {
                $condition['orderBy'][] = $orderByParams;
            }
            $res = $this->goodsModel->getList($condition, $pageIndex, $pageSize);
            $list = [];
            if (!empty($res[0])) {
                foreach ($res[0] as $item) {
                    $goodsItem = [];
                    $goodsItem['goodsId'] = $item['g_id'];
                    $goodsItem['goodsName'] = $item['g_name'];
                    if (!empty($item['g_surfaceImg'])) {
                        $surfaceImg = json_decode(stripslashes($item['g_surfaceImg']), true);
                        $surfaceImgPath = $surfaceImg['gi_img'];
                    } else {
                        $images = $this->goodsModel->getImagesById($item['g_id']);
                        $surfaceImgPath = empty($images[0]['gi_img']) ? '' : $images[0]['gi_img'];
                    }
                    $goodsItem['surfaceImage'] = $surfaceImgPath ? FileHelper::getFileUrl($surfaceImgPath, 'mall_goods_attr_images') : '';
                    $goodsItem['goodsStock'] = $item['g_stock'];
                    $goodsItem['isSecKill'] = $item['isSecKill'];
                    $goodsItem['goodsPrice'] = $item['g_price'];
                    $goodsItem['goodsSecKillPrice'] = $item['g_activityPrice'];
                    $goodsItem['isTop'] = in_array($item['g_id'], $topGoodsIdArray) ? 1: 0;
                    $list[] = $goodsItem;
                }
            }
            $data['list'] = $list;
            $data['count'] = empty($res[1]) ? 0 : $res[1];
        }

        return $data;
    }
}
