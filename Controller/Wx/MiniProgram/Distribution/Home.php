<?php
/**
 * 艺术赚赚小程序
 */
namespace Controller\Wx\MiniProgram\Distribution;

use Exception\ParamsInvalidException;
use Exception\ServiceException;
use Framework\Helper\FileHelper;
use Lib\User\User;
use Model\User\Certification;
use Model\User\Invite;
use Model\User\Setting;
use Model\User\UserShop;
use Rest\Mall\Facade\SettingManager;
use Rest\User\Facade\UserDVipInviteManager;
use Rest\User\Facade\UserManager;

class Home extends Common
{
    private $goodsModel = null;
    private $specialDistributionGoods = [100,200];

    public function __construct()
    {
        parent::__construct();
        $this->goodsModel = new \Model\Mall\Goods();
    }

    /**
     * 首页可分销的商品列表
     */
    public function getList()
    {
        $params = app()->request()->params();
        $pageIndex = 0;
        $pageSize = 10;
        if (isset($params['page'])) {
            $page = intval($params['page']);
            if ($page >= 1) {
                $pageIndex = $page - 1;
            }
        }
        if (isset($params['pageSize'])) {
            $pageSize = intval($params['pageSize']);
        }
        //新人专享 isOwnShop=1&newUserExclusive=1
        if (isset($params['newUserExclusive']) && $params['newUserExclusive'] == 1) {
            $newUserExclusiveGoods = SettingManager::querySetting('wx_mp_new_user_exclusive_goods');
            if ($newUserExclusiveGoods) {
                $condition['goodsIds'] = explode(',', $newUserExclusiveGoods);
            } else {
                $condition['goodsIds'] = [];
            }
        }
        //按浏览量倒序、价格倒序、价格升序
        if (isset($params['orderBy']) && in_array($params['orderBy'], ['hot', 'priceDesc', 'priceAsc'])) {
            if ($params['orderBy'] == 'hot') {
                $condition['orderBy'] = [['g_browseTimes', 'DESC']];
            } elseif ($params['orderBy'] == 'priceDesc') {
                $condition['orderBy'] = [['g_distribution_price', 'DESC']];
            } elseif ($params['orderBy'] == 'priceAsc') {
                $condition['orderBy'] = [['g_distribution_price', 'ASC']];
            }
        } else {
            //设置为分销商品的时间
            $condition['orderBy'] = [['g_set_distribution_date', 'DESC']];
        }

        //匠心优选 isOwnShop=1   （是否为平台自营 0=非自营，1=自营）
        if(isset($params['isOwnShop']) && $params['isOwnShop'] == 1) {
            $condition['isOwnShop'] = 1;
        }
        //分销状态：0=非分销商品，1=分销商品
        $condition['g_distribution_status'] = 1;
        //商品库存量大于0
        $condition['inStock'] = 1;
        $res = $this->goodsModel->getList($condition, $pageIndex, $pageSize);
        $list = [];
        $uid = $this->uid;
        //获取用户分销身份   （0普通用户；1传艺人）
        $userDistributionType = 0;
        if ($uid) {
            $userExtendData = UserManager::getOneUserExtend($uid);
            if ($userExtendData) {
                $userDistributionType = $userExtendData['u_distribution_type'];
            }
        }
        if (!empty($res[0])) {
            foreach ($res[0] as $item) {
                $goodsItem = [];
                $goodsItem['goodsId'] = $item['g_id'];
                $goodsItem['goodsName'] = $item['g_name'];
                $goodsItem['slogan'] = $item['g_slogan'];
                if (!empty($item['g_surfaceImg'])) {
                    $surfaceImg = json_decode(stripslashes($item['g_surfaceImg']), true);
                    $surfaceImgPath = $surfaceImg['gi_img'];
                } else {
                    $images = $this->goodsModel->getImagesById($item['g_id']);
                    $surfaceImgPath = empty($images[0]['gi_img']) ? '' : $images[0]['gi_img'];
                }
                $goodsItem['surfaceImage'] = $surfaceImgPath ? FileHelper::getFileUrl($surfaceImgPath, 'mall_goods_attr_images') : '';
                $goodsItem['distributionPrice'] = $item['g_distribution_price'];
                $goodsItem['discountAmount'] = round($item['g_distribution_price'] * 15 / 100, 2);
                $list[] = $goodsItem;
            }
        }
        $data['userDistributionType'] = $userDistributionType;
        $data['list'] = $list;
        $data['count'] = empty($res[1]) ? 0 : $res[1];

        $this->responseJSON($data);
    }

    /**
     * 购买指定商品 成为传艺人
     * @throws \Model\Mall\ParamsInvalidException
     */
    public function getSpecialDistributionGoods()
    {
        $data = [];
        $condition['goodsIds'] = conf('config.specialDistributionGoods');
        $condition['g_distribution_status'] = 1;
        $condition['inStock'] = 1;
        $res = $this->goodsModel->getList($condition, 0, 10);
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
                $goodsItem['distributionPrice'] = $item['g_distribution_price'];
                $data[] = $goodsItem;
            }
        }

        $this->responseJSON($data);
    }

    /**
     * 绑定邀请人
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function bindUserInvite()
    {
        $params = app()->request()->params();
        $inviteUserId = $params['inviteUserId'];
        if (empty($inviteUserId)) {
            throw  new ParamsInvalidException('参数错误');
        }

        $userExtend = UserManager::getOneUserExtend($this->uid);
        if ($userExtend && $userExtend['u_distribution_type'] == 1) {
            throw  new ServiceException('当前用户已经是传艺人');
        }
        $userExtend = UserManager::getOneUserExtend($inviteUserId);
        if (empty($userExtend) || $userExtend['u_distribution_type'] != 1) {
            throw  new ServiceException('邀请人不是传艺人');
        }

        $userDVipInviteData = UserDVipInviteManager::getByUid($this->uid);
        if (!empty($userDVipInviteData)) {
            throw  new ServiceException('当前用户已绑定邀请人');
        }

        $result = UserDVipInviteManager::add(['u_id' => $this->uid, 'vi_invite_uid' => $inviteUserId]);
        return $this->responseJSON($result);
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo()
    {
        $data = [];
        $params = app()->request()->params();
        if (!empty($params['uid'])) {
            $uid = $params['uid'];
        } else {
            $uid = $this->uid;
        }

        if ($uid) {
            $userModel = new User();
            $userInfoList = $userModel->getUserInfo([$uid], '', 1);
            if ($userInfoList) {
                $userInfo = current($userInfoList);
                $data['uid'] = $uid;
                $data['nickName'] = $userInfo['u_nickname'];
                $data['realName'] = $userInfo['u_realname'];
                $data['phone'] = $userInfo['u_phone'];
                $data['avatar'] = $userInfo['u_avatar'];
                $data['userDistributionType'] = $userInfo['user_extend']['u_distribution_type'];
                $certInfo = (new Certification())->getInfo($this->uid);
                $data['userCertStatus'] = empty($certInfo) ? 0 : 1;
                $settingModel = new Setting();
                $data['introduction'] = $settingModel->settingGetValue($uid, 'introduction');
            }
        }
        //根据uid 获取   邀请人id
         $dataS = app('mysqlbxd_user')->fetch('select vi_invite_uid from `user_distribution_vip_invite` where u_id = :u_id', [':u_id' => $uid]);
         $data['vi_invite_uid'] = isset($dataS['vi_invite_uid'])&&$dataS['vi_invite_uid']?$dataS['vi_invite_uid']:'';;
         $this->responseJSON($data);
    }

    /**
     * 修改用户店铺介绍
     */
    public function updateShopDes()
    {
        $params = app()->request()->params();
        if (!isset($params['shopDes'])) {
            throw new \Exception\ParamsInvalidException("店铺介绍参数不能为空！");
        }
        $settingModel = new Setting();
        $settingModel->settingSet($this->uid, 'u_store_des', $params['shopDes']);
        $this->responseJSON(['result' => true]);
    }

    /**
     * 获取用户邀请信息
     */
    public function getUserInviteInfo()
    {
        $data = [];
        $uid = $this->uid;
        $inviteModel = new Invite();
        $userModel = new \Model\User\User();
        $goodsLib = new \Lib\Mall\Goods();

        $params = [
            'u_id' => $uid,
            'uil_is_register' => 1
        ];
        $inviteData = $inviteModel->lists($params, 1, 100);
        $list = $inviteData[0];
        if($list) {
            foreach ($list as &$item) {
                if ($item['uil_time']) {
                    $item['uil_time'] = date('Y-m-d', strtotime($item['uil_time']));
                    $item['uil_phone'] = substr_replace($item['uil_phone'], '****', 3, 4);
                }
            }
        }
        $regCount = $inviteData[1];
        $inviteListPhone = array_column($list, 'uil_phone');
        $inviteUserIdList = $userModel->getUserIdList($inviteListPhone);
        $salesAmount = 0;
        foreach ($inviteUserIdList as $inviteUserId) {
            $params['uid'] = $inviteUserId;
            $params['status'] = 2; //已入账
            $params['action'] = 'query';
            $performanceData = $goodsLib->performance($params);
            if(!empty($performanceData['list'])) {
                foreach ($performanceData['list'] as $item) {
                    $salesAmount += $item['o_price'];
                }
            }
        }

        $data['inviteCount'] = $regCount;
        $data['salesAmount'] = $salesAmount;
        $data['inviteList'] = $list;
        $this->responseJSON($data);
    }
}
