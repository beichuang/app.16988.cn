<?php

namespace Controller\Mall\Activity;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;

class Activity extends BaseController {

    public function __construct() {
        parent::__construct();
    }
    /**
 * 活动列表
 */
    public function lists() {
        $activity_type=app()->request()->params('type');
        //0所有、1进行中，-1已结束
        $isActive=app()->request()->params('isActive');
        $params = ['page' => 1, 'pageSize' => 20];
        if(isset($activity_type)){
            $params['activity_type']=intval($activity_type);
        }
        if(isset($isActive)){
            $params['isActive']=intval($isActive);
        }
        $params['activity_state']=1;
        $activity_list = api_request($params, 'mall/activity');
        foreach ($activity_list['list'] as &$val) {
            $val['activity_start_date'] = date('Y-m-d H:i:s', $val['activity_start_date']);
            $val['activity_end_date'] = date('Y-m-d H:i:s', $val['activity_end_date']);
            $val['activity_banner'] = FileHelper::getFileUrl($val['activity_banner'], 'mall_activity_images');
        }
        $this->responseJSON($activity_list);
    }

    /**
     * 活动详情
     */
    public function details() {
        $params = app()->request()->params();
        $page = app()->request()->params('page',1);
        $pageSize = app()->request()->params('pageSize',10);
        $page=intval($page)?intval($page):1;
        $page=($page<1)?1:$page;
        $pageSize=intval($pageSize)?intval($pageSize):10;
        if (!$params['aid']) {
            throw new ParamsInvalidException("活动id必须");
        }
        $result = api_request(['activity_id' => $params['aid']], 'mall/activity');
        $activity_info = $result['count'] > 0 ? $result['list'][0] : [];
        if (!empty($activity_info)) {
            $activity_info['activity_start_date'] = date('Y-m-d H:i:s', $activity_info['activity_start_date']);
            $activity_info['activity_end_date'] = date('Y-m-d H:i:s', $activity_info['activity_end_date']);
            $activity_info['activity_banner'] = FileHelper::getFileUrl($activity_info['activity_banner'], 'mall_activity_images');
            $filteredIds=array_slice(array_filter(explode(',',$activity_info['activity_goods_ids'])),($page-1)*$pageSize,$pageSize);

            $activity_info['activity_goods'] = [];
			if($filteredIds){
				$result_goods = api_request(
                ['ids' => implode(',',$filteredIds),
                    'pageSize' => 100
                ], 'mall/item/list');

            if($result_goods['count'] > 0){
                $goodsLikeLogModel = new \Model\Mall\GoodsLikeLog();
                foreach ($result_goods['lists'] as &$list){
                    $itemCurrentUserLikeInfo = $goodsLikeLogModel->findByUidGcId($this->uid, $list['g_id']);
                    $list['itemCurrentUserLikeInfo'] = empty($itemCurrentUserLikeInfo) ? null : $itemCurrentUserLikeInfo;
                }
                $activity_info['activity_goods'] = $result_goods['lists'];
            }
			}

        }
        $this->responseJSON($activity_info);
    }

}
