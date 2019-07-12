<?php

/**
 * 拍品出价记录
 * @author Administrator
 *
 */

namespace Controller\Mall\Auction;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class BidRecord extends BaseController
{

    private $auctionLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->auctionLib = new \Lib\Mall\Auction();
    }

    /**
     * 新增出价记录
     *
     * @throws ModelException
     */
    public function add()
    {
        $params = app()->request()->params();

        $params['abr_userId'] = $this->uid;
        if (empty($params['a_id'])) {
            throw new ServiceException("a_id为必须");
        }
        if (empty($params['price'])) {
            throw new ServiceException("price为必须");
        }

        $resMall = $this->auctionLib->addBidRecord($params);
        if ($resMall) {
            throw new ServiceException($resMall);
        }
        $this->responseJSON([$resMall]);
    }

    /**
     * 查询出价记录
     */
    public function getList()
    {
        $data = ['lists' => [], 'currentData' => null];
        $params = app()->request()->params();
        if (empty($params['a_id'])) {
            throw new ParamsInvalidException("缺少参数a_id");
        }
        $bidRecordData = $this->auctionLib->getBidRecord($params);

        if (!empty($bidRecordData['list']) || !empty($bidRecordData['maxData'])) {
            $listUserIds = [];
            if(!empty($bidRecordData['list'])) {
                $listUserIds = array_column($bidRecordData['list'], 'abr_userId');
            }
            if(!empty($bidRecordData['maxData'])) {
                $listUserIds[] = $bidRecordData['maxData']['abr_userId'];
            }
            $userIds = array_unique($listUserIds);
            if($userIds) {
                $userInfo = (new \Lib\User\User())->getUserInfo($userIds, '');

                foreach ($bidRecordData['list'] as &$item) {
                    if (isset($userInfo[$item['abr_userId']])) {
                        $item['u_nickname'] = $userInfo[$item['abr_userId']]['u_nickname'];
                        $item['u_avatar'] = $userInfo[$item['abr_userId']]['u_avatar'];
                    } else {
                        $item['u_nickname'] = '';
                        $item['u_avatar'] = '';
                    }
                    $item['abr_createDate'] = strtotime($item['abr_createDate']);
                    $item['abr_createDate_format'] = $this->formatTime($item['abr_createDate'], time());
                    $item['abr_price'] = intval($item['abr_price']);
                }

                if (isset($userInfo[$bidRecordData['maxData']['abr_userId']])) {
                    $bidRecordData['maxData']['u_nickname'] = $userInfo[$bidRecordData['maxData']['abr_userId']]['u_nickname'];
                    $bidRecordData['maxData']['u_avatar'] = $userInfo[$bidRecordData['maxData']['abr_userId']]['u_avatar'];
                } else {
                    $bidRecordData['maxData']['u_nickname'] = '';
                    $bidRecordData['maxData']['u_avatar'] = '';
                }
                $bidRecordData['maxData']['abr_createDate'] = strtotime($bidRecordData['maxData']['abr_createDate']);
                $bidRecordData['maxData']['abr_createDate_format'] = $this->formatTime($bidRecordData['maxData']['abr_createDate'], time());
                $bidRecordData['maxData']['abr_price'] = intval($bidRecordData['maxData']['abr_price']);

                $data['lists'] = $bidRecordData['list'];
                $data['currentData'] = $bidRecordData['maxData'];
            }
        }

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
            $formatText = $res['day'] . '天';
            return $formatText;
        }
        if ($res['hour'] > 0) {
            $formatText .= $res['hour'] . '小时';
            return $formatText;
        }
        if ($res['min'] > 0) {
            $formatText .= $res['min'] . '分';
            return $formatText;
        }
        if ($res['sec'] > 0) {
            $formatText .= $res['sec'] . '秒';
            return $formatText;
        }

        return '';
    }
}
