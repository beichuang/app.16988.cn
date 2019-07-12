<?php
/**
 * 退货
 *
 */

namespace Controller\Mall\Goods;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Refund extends BaseController
{

    private $rfMod = null;

    public function __construct()
    {
        parent::__construct();
        $this->rfMod = new \Model\Mall\Refund();
    }

    /**
     * 退货详情
     * @param  $o_sn  退货关联订单sn
     * @throws ModelException
     */
    public function getInfo()
    {
        $o_sn = app()->request()->params('o_sn');
        if (!$o_sn) {
            throw new ParamsInvalidException("订单id必须");
        }

        $retInfo = $this->rfMod->getRefund($o_sn, $this->uid);
        if ($retInfo) {
            if ($retInfo['r_images']) {
                $image_list = json_decode($retInfo['r_images']);
                $retInfo['r_images'] = '';
                foreach ($image_list as $value) {
                    $retInfo['r_images'][] = FileHelper::getFileUrl($value->r_img, 'mall_refund_images');
                }
            }

            $retProvince = \Lib\Common\Region::get(array('region_code' => $retInfo['recv_province']));
            $retInfo['recv_province'] = $retProvince ? current($retProvince)['region_name'] : '';

            $retProvince = \Lib\Common\Region::get(array('region_code' => $retInfo['recv_city']));
            $retInfo['recv_city'] = $retProvince ? current($retProvince)['region_name'] : '';

            $retProvince = \Lib\Common\Region::get(array('region_code' => $retInfo['recv_area']));
            $retInfo['recv_area'] = $retProvince ? current($retProvince)['region_name'] : '';

            if (in_array($retInfo['r_status'], [1, 2])) {
                $retInfo['r_flag'] = '提交申请';
            }

            if (in_array($retInfo['r_status'], [25])) {
                $retInfo['r_flag'] = '申请审核';
            }

            if (in_array($retInfo['r_status'], [35])) {
                $retInfo['r_flag'] = '商家收货';
            }
            if (in_array($retInfo['r_status'], [45])) {
                $retInfo['r_flag'] = '拒绝退货';
            }

            if (empty($retInfo['recv_linkman'])) {
                $retInfo['recv_linkman'] = '';
            }

            $temp = $retInfo['r_ctime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_ctime'];
            $retInfo['list'][] = array('name' => '提交申请', 'time' => $temp);

            $temp = $retInfo['r_agreetime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_agreetime'];
            if ($retInfo['r_status'] == 45) {
                $retInfo['list'][] = array('name' => '商家已拒绝', 'time' => $temp);
            } else if ($retInfo['r_status'] == 25) {
                $retInfo['list'][] = array('name' => '商家已同意', 'time' => $temp);
            } else {
                $retInfo['list'][] = array('name' => '申请审核', 'time' => $temp);
            }

            $temp = $retInfo['r_confirmtime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_confirmtime'];
            $retInfo['list'][] = array('name' => '商家收货', 'time' => $temp);
            $temp = $retInfo['r_cancletime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_cancletime'];
            $retInfo['list'][] = array('name' => '处理完成', 'time' => $temp);
//            unset($retInfo['r_ctime']);
            unset($retInfo['r_agreetime']);
            unset($retInfo['r_confirmtime']);
            unset($retInfo['r_cancletime']);

            // 查看退款是否完成
            if (in_array($retInfo['r_status'], [24, 35, 36])) {

                $payLib = new \Lib\Mall\Pay();
                $retList = $payLib->refundRet(['o_sn' => $retInfo['o_sn']]);
                if ($retList) {
                    $this->rfMod->updateStatus($o_sn, 100);
                    $retInfo['r_status'] = 100;
                }
            }
        } else {
            $retInfo = null;
        }

        $this->responseJSON($retInfo);
    }


    public function getInfoNew()
    {
        $o_sn = app()->request()->params('o_sn');
        if (!$o_sn) {
            throw new ParamsInvalidException("订单id必须");
        }

        $retInfo = $this->rfMod->getRefund($o_sn, $this->uid);
        if ($retInfo) {
            if ($retInfo['r_images']) {
                $image_list = json_decode($retInfo['r_images']);
                $retInfo['r_images'] = [];
                foreach ($image_list as $value) {
                    $retInfo['r_images'][] = FileHelper::getFileUrl($value->r_img, 'mall_refund_images');
                }
            }

            $retProvince = \Lib\Common\Region::get(array('region_code' => $retInfo['recv_province']));
            $retInfo['recv_province'] = $retProvince ? current($retProvince)['region_name'] : '';

            $retProvince = \Lib\Common\Region::get(array('region_code' => $retInfo['recv_city']));
            $retInfo['recv_city'] = $retProvince ? current($retProvince)['region_name'] : '';

            $retProvince = \Lib\Common\Region::get(array('region_code' => $retInfo['recv_area']));
            $retInfo['recv_area'] = $retProvince ? current($retProvince)['region_name'] : '';

            if (in_array($retInfo['r_status'], [1, 2])) {
                $retInfo['r_flag'] = '提交申请';
            }

            if (in_array($retInfo['r_status'], [25])) {
                $retInfo['r_flag'] = '申请审核';
            }

            if (in_array($retInfo['r_status'], [35])) {
                $retInfo['r_flag'] = '商家收货';
            }
            if (in_array($retInfo['r_status'], [45])) {
                $retInfo['r_flag'] = '拒绝退货';
            }

            if (empty($retInfo['recv_linkman'])) {
                $retInfo['recv_linkman'] = '';
            }

            $temp = $retInfo['r_ctime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_ctime'];
            $retInfo['list'][] = array('name' => '提交申请', 'time' => $temp);

            $temp = $retInfo['r_agreetime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_agreetime'];
            if ($retInfo['r_status'] == 45) {
                $retInfo['list'][] = array('name' => '商家已拒绝', 'time' => $temp);
            } else if ($retInfo['r_status'] == 25) {
                $retInfo['list'][] = array('name' => '商家已同意', 'time' => $temp);
            } else {
                $retInfo['list'][] = array('name' => '申请审核', 'time' => $temp);
            }

            $temp = $retInfo['r_confirmtime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_confirmtime'];
            $retInfo['list'][] = array('name' => '商家收货', 'time' => $temp);
            $temp = $retInfo['r_cancletime'] == '0000-00-00 00:00:00' ? '' : $retInfo['r_cancletime'];
            $retInfo['list'][] = array('name' => '处理完成', 'time' => $temp);
//            unset($retInfo['r_ctime']);
            unset($retInfo['r_agreetime']);
            unset($retInfo['r_confirmtime']);
            unset($retInfo['r_cancletime']);
        } else {
            //查询申请退款的时间
            $OrderModel = new \Lib\Mall\Order();
//            $params['type'] = 1;
//            $params['uid'] = $this->uid;
            $params['sn'] = $o_sn;
            $resOrder = $OrderModel->detail($params);
            if ($resOrder && $resOrder['refund_time']) {
                $retInfo['r_ctime'] = $resOrder['refund_time'];
            } else {
                $retInfo = null;
            }
        }
        $this->responseJSON($retInfo);
    }
}
