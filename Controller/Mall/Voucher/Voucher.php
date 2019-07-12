<?php

/**
 * 优惠券
 * @author Administrator
 *
 */

namespace Controller\Mall\Voucher;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Rest\Mall\Facade\ItemManager;
use Rest\Mall\Facade\VoucherManager;
use Rest\Mall\Facade\VoucherTemplateManager;
use Rest\User\Facade\UserManager;

class Voucher extends BaseController
{
    /**
     * 获取店铺优惠券列表
     */
    public function getShopVoucherList()
    {
        $data = [];
        $params = app()->request()->params();
        if (!empty($params['status']) && $params['status'] == 2) {
            $status = 2; //即将开始
        } else {
            $status = 1; //当前可领
        }
        $page = isset($params['page']) ? $params['page'] : 1;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 3;
        $condition['receiveValidityPeriod'] = $status; //领取有效期
        $condition['scope'] = 4; //指定商家可用
        $condition['getType'] = 4; //领取方式-免费领取
        $list = VoucherTemplateManager::getTemplateList($condition, $pageIndex, $pageSize);
        if ($list) {
            foreach ($list as $item) {
                if ($item['v_t_limit_ids']) {
                    $shopId = explode(',', $item['v_t_limit_ids'])[0];
                    $userInfo = UserManager::getUserInfoByUid($shopId);
                    list($status, $remainingTimes) = VoucherManager::getVoucherReceiveStatus($item, $this->uid);
                    $realname = empty($userInfo) ? '' : $userInfo['u_realname'];
                    $data[] = [
                        'v_t_id' => $item['v_t_id'],
                        'v_t_desc' => $item['v_t_desc'],
                        'v_t_price' => $item['v_t_price'],
                        'v_t_status' => $status,
                        'remainingTimes' => $remainingTimes,
                        'u_id' => $shopId,
                        'u_realname' => $realname,
                        'u_shopName' => $realname ? $realname : $userInfo['u_nickname'] . '的店铺',
                        'v_t_use_desc' => $this->getVoucherUseDesc($item)
                    ];
                }
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 获取商品优惠券列表
     */
    public function getGoodsVoucherList()
    {
        $data = [];
        $params = app()->request()->params();
        if (!empty($params['status']) && $params['status'] == 2) {
            $status = 2; //即将开始
        } else {
            $status = 1; //当前可领
        }
        $page = isset($params['page']) ? $params['page'] : 1;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 4;
        $condition['receiveValidityPeriod'] = $status; //领取有效期
        $condition['scope'] = 1; //指定商品可用
        $condition['getType'] = 4; //领取方式-免费领取
        $list = VoucherTemplateManager::getTemplateList($condition, $pageIndex, $pageSize);
        if ($list) {
            foreach ($list as &$item) {
                if ($item['v_t_limit_ids']) {
                    $isMultipleGoods = 0;
                    $surfaceImgUrl = '';
                    $goodsIdArray = explode(',', $item['v_t_limit_ids']);
                    if (count($goodsIdArray) > 1) {
                        $isMultipleGoods = 1;
                        $surfaceImgUrl = 'https://zhangwan-static.oss-cn-hangzhou.aliyuncs.com/app/images/voucher_template.png';
                    }
                    $goodsId = $goodsIdArray[0];
                    $goodsInfoList = ItemManager::getItemById($goodsId);
                    $goodsInfo = is_array($goodsInfoList) ? $goodsInfoList[0] : [];
                    if (empty($goodsInfo)) {
                        $data[] = [
                            'v_t_id' => ''
                        ];
                        continue;
                    }
                    if (!$isMultipleGoods && $goodsInfo['g_surfaceImg']) {
                        $surfaceImgData = json_decode($goodsInfo['g_surfaceImg'], true);
                        $surfaceImg = $surfaceImgData['gi_img'];
                        $surfaceImgUrl = FileHelper::getFileUrl($surfaceImg, 'mall_goods_attr_images');
                    }
                    list($status, $remainingTimes) = VoucherManager::getVoucherReceiveStatus($item, $this->uid);
                    $data[] = [
                        'v_t_id' => $item['v_t_id'],
                        'v_t_desc' => $item['v_t_desc'],
                        'v_t_price' => $item['v_t_price'],
                        'v_t_status' => $status,
                        'remainingTimes' => $remainingTimes,
                        'g_id' => $goodsInfo['g_id'],
                        'g_name' => $goodsInfo['g_name'],
                        'g_price' => $goodsInfo['g_price'],
                        'g_surfaceImg' => $surfaceImgUrl,
                        'v_t_use_desc' => $this->getVoucherUseDesc($item),
                        'is_multiple_goods' => $isMultipleGoods
                    ];
                } else {
                    $data[] = [
                        'v_t_id' => ''
                    ];
                }
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 获取是否有待领取的优惠券
     */
    public function getToBeReceive()
    {
        $result = false;
        $condition['receiveValidityPeriod'] = 1; //领取有效期
        $condition['scope'] = [1, 4]; //指定商品、店铺可用
        $condition['getType'] = 4; //领取方式-免费领取
        $list = VoucherTemplateManager::getTemplateList($condition);
        if ($list) {
            foreach ($list as $item) {
                list($status) = VoucherManager::getVoucherReceiveStatus($item, $this->uid);
                if ($status == 1) {
                    $result = true;
                    break;
                }
            }
        }
        $this->responseJSON($result);
    }

    /**
     * 获取优惠券使用描述
     * @param $item
     * @return string
     */
    private function getVoucherUseDesc($item)
    {
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

        return $useDesc;
    }
}
