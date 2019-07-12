<?php

/**
 * 定制
 * @author Administrator
 *
 */

namespace Controller\Mall\Custom;

use Exception\ServiceException;
use Framework\Helper\DateHelper;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Lib\Mall\GoodsCategory;
use Lib\Mall\Order;
use Lib\User\User;
use Model\User\UserSpeciality;

class Custom extends BaseController
{
    //待支付5、待审核15、审核不通过20、征稿中31、选稿中35、征稿失败37、未选稿39、待生成订单40、已成交50、需求已关闭100
    private $customLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->customLib = new \Lib\Mall\Custom();
    }

    /**
     * 获取所有定制分类
     */
    public function getCategory()
    {
        //书法、国画、油画
        $ids = ['20', '11', '31'];
        $data = (new GoodsCategory())->getCategories(['ids' => $ids]);
        $this->responseJSON($data);
    }

    /**
     * 新增/编辑定制
     */
    public function save()
    {
        //检查是否实名认证
        $certModel = new \Model\User\Certification();
        $certificationInfo = $certModel->getInfo($this->uid);
        if (empty($certificationInfo['uce_IDNo']) && $certificationInfo['uce_isCelebrity'] != 2) {
            throw new ServiceException("还没有通过实名认证");
        }

        $data = [];
        $images = [];
        $customId = '';
        $params = app()->request()->params();
        if (isset($params['c_id'])) {
            $customId = $params['c_id'];
        }

        if ($customId) {
            $customData = $this->customLib->getOneById($customId);
            if (empty($customData)) {
                throw new ServiceException("当前定制不存在");
            }
            if (!in_array($customData['c_status'], ['5', '15', '20'])) {
                throw new ServiceException("当前定制状态不能进行修改");
            }
            if ($customData['c_status'] == 5 && isset($params['c_trusteeship_amount'])) {
                $data['c_trusteeship_amount'] = $params['c_trusteeship_amount'];
            }
            //审核不通过状态，再次编辑时将状态修改为审核中
            if ($customData['c_status'] == 20) {
                $data['c_status'] = 15;
            }
        } else {
            //新增时检查必填项
            $this->checkRequired($params, ['c_categoryId', 'c_title', 'c_submit_endDate', 'c_trusteeship_amount']);
            $data['c_trusteeship_amount'] = $params['c_trusteeship_amount'];
            //定制发布者id为当前用户id
            $data['c_createUserId'] = $this->uid;
        }

        if (isset($params['c_categoryId'])) {
            $data['c_categoryId'] = $params['c_categoryId'];
            $categoryData = (new GoodsCategory())->getOne($data['c_categoryId']);
            if (empty($categoryData)) {
                throw  new ParamsInvalidException('当前分类不存在');
            }
            $data['c_first_level_categoryId'] = $categoryData['c_parentId'];
        }
        if (isset($params['c_title'])) {
            $data['c_title'] = $params['c_title'];
        }
        if (isset($params['c_desc'])) {
            $data['c_desc'] = $params['c_desc'];
        }
        if (isset($params['c_designatedUserId'])) {
            $data['c_designatedUserId'] = $params['c_designatedUserId'];
        }
        if (isset($params['c_submit_endDate'])) {
            $data['c_submit_endDate'] = $params['c_submit_endDate'];
        }

        //检查图片
        if (isset($params['images'])) {
            $images = $params['images'];
            $images = json_decode($images, true);
            if (count($images) > 5) {
                throw  new ParamsInvalidException('图片最多上传5张');
            }
        }

        $customId = $this->customLib->save($customId, $data, $images);

        $this->responseJSON(array(
            'c_id' => $customId
        ));
    }

    /**
     * 获取我发布的定制列表
     */
    public function getMyList()
    {
        $params = app()->request()->params();
        if (!isset($params['status'])) {
            throw new ParamsInvalidException("缺少参数status");
        }
        $page = isset($params['page']) ? $params['page'] : 1;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 10;
        if ($params['status'] == 2) {
            //征稿失败、未选稿、已成交、需求关闭
            $params['c_status'] = [37, 39, 50, 100];
        } elseif ($params['status'] == 1) {
            $params['c_status'] = [5, 15, 20, 31, 35, 40];
        }
        $params['c_createUserId'] = $this->uid;

        list($list, $count) = $this->customLib->getList($pageIndex, $pageSize, $params);
        if ($list) {
            foreach ($list as &$item) {
                $isAllowExtendedSubmitEndDate = 0;
                //征稿中，未延长过，且征稿截止时间小于3天
                if ($item['c_status_format'] == 31 && $item['c_is_extended_submit_endDate'] == 0 && strtotime($item['c_submit_endDate']) <= strtotime("3 day")) {
                    $isAllowExtendedSubmitEndDate = 1;
                }
                $item = [
                    'c_id' => $item['c_id'],
                    'c_title' => $item['c_title'],
                    'c_createDate' => date('Y-m-d', strtotime($item['c_createDate'])),
                    'c_submitCount' => $item['c_submitCount'],
                    'c_price' => $item['c_trusteeship_amount'],
                    'c_status' => $item['c_status_format'],
                    'c_applyCloseStatus' => empty($item['c_applyCloseStatus']) ? 0 : 1,
                    'c_isAllowExtendedSubmitEndDate' => $isAllowExtendedSubmitEndDate
                ];
            }
        }
        $this->responseJSON(['list' => $list, 'count' => $count]);
    }

    /**
     * 申请关闭定制需求
     * @throws ParamsInvalidException
     */
    public function applyClose()
    {
        $params = app()->request()->params();
        if (!isset($params['c_id'])) {
            throw new ParamsInvalidException("缺少参数c_id");
        }
        $customId = $params['c_id'];
        $customData = $this->customLib->getOneById($customId);
        if ($customData['c_createUserId'] != $this->uid) {
            throw new ParamsInvalidException("你无权限进行此操作");
        }
        //待支付、审核不通过、征稿失败、未选稿 可以关闭需求
        if (!in_array($customData['c_status_format'], [5, 20, 37, 39])) {
            throw new ParamsInvalidException("当前定制状态不能进行此操作");
        }

        $updateData = [
            'c_applyCloseStatus' => 1,
            'c_applyCloseDate' => date('Y-m-d H:i:s')
        ];
        $this->customLib->update($customId, $updateData);

        $this->responseJSON(true);
    }

    /**
     * 延长征稿截止时间
     * @throws ParamsInvalidException
     */
    public function extendedSubmitDate()
    {
        $params = app()->request()->params();
        if (!isset($params['c_id'])) {
            throw new ParamsInvalidException("缺少参数c_id");
        }
        $customId = $params['c_id'];
        $customData = $this->customLib->getOneById($customId);
        if($customData['c_createUserId'] != $this->uid) {
            throw new ParamsInvalidException("你无权限进行此操作");
        }
        if ($customData['c_is_extended_submit_endDate']) {
            throw new ParamsInvalidException("不能多次延长征稿截止时间");
        }
        if ($customData['c_status_format'] != 31) {
            throw new ParamsInvalidException("当前定制状态不能进行此操作");
        }
        //征稿已截止
        if (strtotime($customData['c_submit_endDate']) < time()) {
            throw new ParamsInvalidException("当前定制征稿已截止，不能进行此操作");
        }
        //征稿截止时间距离当前超过3天
        if (strtotime($customData['c_submit_endDate']) > strtotime("3 day")) {
            throw new ParamsInvalidException("距离征稿截止3天内可以延长征稿截止时间");
        }
        $updateData = [
            'c_is_extended_submit_endDate' => 1,
            'c_submit_endDate' => date('Y-m-d H:i:s', strtotime("{$customData['c_submit_endDate']}+5 day"))
        ];
        $this->customLib->update($customId, $updateData);

        $this->responseJSON(true);
    }

    /**
     * 获取向我定制的列表
     */
    public function getDesignatedMyList()
    {
        //检查是否完成艺术家或机构认证
        $certModel = new \Model\User\Certification();
        $certificationInfo = $certModel->getInfo($this->uid);
        if ($certificationInfo['uce_status'] == 1 && in_array($certificationInfo['uce_isCelebrity'], [1, 2])) {
            $params = app()->request()->params();
            $page = isset($params['page']) ? $params['page'] : 1;
            $pageIndex = $page >= 1 ? $page - 1 : 0;
            $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 10;

            $uid = $this->uid;
            //找到当前用户擅长领域
            $categoryIds = [];
            $userSpecialityData = (new UserSpeciality())->getUserSpeciality($uid);
            if ($userSpecialityData) {
                $categoryIds = array_column($userSpecialityData, 'gc_id');
            }

            list($list, $count) = $this->customLib->getMyFitList($pageIndex, $pageSize, $uid, $categoryIds);
            if ($list) {
                foreach ($list as &$item) {
                    $span = timediff(strtotime($item['c_submit_endDate']), time());
                    $formatText = "{$span['day']},{$span['hour']},{$span['min']}";
                    $item = [
                        'c_id' => $item['c_id'],
                        'c_title' => $item['c_title'],
                        'is_submit_end' => $item['is_submit_end'],
                        'c_submit_endDate_format' => $formatText,
                        'c_price' => $item['c_trusteeship_amount'],
                        'type' => $item['c_designatedUserId'] == $uid ? 1 : 2,

                    ];
                }
            }
        } else {
            $list = [];
            $count = 0;
        }

        $this->responseJSON(['list' => $list, 'count' => $count]);
    }

    /**
     * 获取我的投稿列表
     */
    public function getMySubmitCustom()
    {
        $params = app()->request()->params();
        $status = isset($params['status']) ? $params['status'] : 1;
        $page = isset($params['page']) ? $params['page'] : 1;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 10;
        $uid = $this->uid;
        $data = [];
        list($list, $count) = $this->customLib->getMySubmitCustom($pageIndex, $pageSize, $uid, $status);
        if ($list) {
            foreach ($list as $item) {
                //投稿数据
                $submitCustomItem = [
                    'cg_createDate_format' => date('m-d', strtotime($item['cg_createDate'])),
                    'cg_desc' => $item['cg_desc'],
                    'images' => $this->getImages($item['cg_id'], 2),
                    'cg_isSelected' => $item['cg_isSelected'],
                    'cg_auditStatus' => $item['cg_auditStatus']
                ];
                if (key_exists($item['c_id'], $data)) {
                    $data[$item['c_id']]['submit_list'][] = $submitCustomItem;
                } else {
                    $data[$item['c_id']] = [
                        'c_id' => $item['c_id'],
                        'c_title' => $item['c_title'],
                        'c_price' => $item['c_trusteeship_amount'],
                        'c_status' => $item['c_status_format'],
                        'submit_list' => [$submitCustomItem]
                    ];
                }
            }
            $data = array_values($data);
        }

        $this->responseJSON(['list' => $data, 'count' => $count]);
    }

    /**
     * 保存定制投稿
     */
    public function saveCustomGoods()
    {
        //检查是否完成艺术家或机构认证
        $certModel = new \Model\User\Certification();
        $certificationInfo = $certModel->getInfo($this->uid);
        if ($certificationInfo['uce_status'] == 1 && in_array($certificationInfo['uce_isCelebrity'], [1, 2])) {
            //艺术家或机构认证通过
        } else {
            throw new ServiceException("需先完成艺术家或机构认证");
        }

        $data = [];
        $images = [];
        $params = app()->request()->params();
        if (isset($params['c_id'])) {
            $data['c_id'] = $params['c_id'];
        } else {
            throw new ParamsInvalidException("缺少参数c_id");
        }
        $customId = $params['c_id'];
        if (isset($params['cg_size'])) {
            $data['cg_size'] = $params['cg_size'];
        }
        if (isset($params['cg_desc'])) {
            $data['cg_desc'] = $params['cg_desc'];
        }

        $customData = $this->customLib->getOneById($customId);
        //判断是否是征稿中
        if ($customData['c_status_format'] != 31) {
            throw new ParamsInvalidException("当前定制状态不能进行此操作");
        }
        //判断是否是1对1定制
        if (!empty($customData['c_designatedUserId']) && $customData['c_designatedUserId'] != $this->uid) {
            throw new ParamsInvalidException("当前定制为一对一定制，只有指定用户可以投稿");
        }

        //检查图片
        if (isset($params['images'])) {
            $images = $params['images'];
            $images = json_decode($images, true);
            if (count($images) < 1) {
                throw  new ParamsInvalidException('至少上传一张图片');
            }
            if (count($images) > 5) {
                throw  new ParamsInvalidException('图片最多上传5张');
            }
        }
        //投稿发布者id为当前用户id
        $data['cg_createUserId'] = $this->uid;
        $customGoodsId = $this->customLib->saveCustomGoods($data, $images);

        $this->responseJSON(array(
            'cg_id' => $customGoodsId
        ));
    }

    /**
     * 获取定制最新动态
     */
    public function getNewestList()
    {
        $data = [];
        list($list) = $this->customLib->getList(0, 10, []);
        if ($list) {
            $userIds = array_column($list, 'c_createUserId');
            $userInfoArray = (new User())->getUserInfo($userIds);
            foreach ($list as $item) {
                $data[] = [
                    'c_createDate_format' => DateHelper::getTimeSpanFormat($item['c_createDate'], time()),
                    'c_createUser_nick' => isset($userInfoArray[$item['c_createUserId']]) ? $userInfoArray[$item['c_createUserId']]['u_nickname'] : '',
                    'c_price' => $item['c_trusteeship_amount']
                ];
            }
        }

        $this->responseJSON(['data' => $data]);
    }

    /**
     * 获取定制库列表
     */
    public function getList()
    {
        $params = app()->request()->params();
        $page = isset($params['page']) ? $params['page'] : 1;
        $pageIndex = $page >= 1 ? $page - 1 : 0;
        $pageSize = isset($params['pageSize']) ? $params['pageSize'] : 5;
        $condition = [];

        //按状态进行筛选 定制状态（待支付5、待审核15、审核不通过20、征稿中31、选稿中35、征稿失败37、已结束39、待生成订单40、已成交50、需求已关闭100）
        if (isset($params['type'])) {
            if ($params['type'] == 1) {
                //征稿中
                $condition['c_status'] = 31;
            } elseif ($params['type'] == 2) {
                //选稿中、征稿失败、未选稿、待生成订单、已成交
                $condition['c_status'] = [35, 37, 39, 40, 50];
            } else {
                //征稿中、选稿中、征稿失败、未选稿、待生成订单、已成交
                $condition['c_status'] = [31, 35, 37, 39, 40, 50];
            }
        }else {
            //征稿中、选稿中、征稿失败、未选稿、待生成订单、已成交
            $condition['c_status'] = [31, 35, 37, 39, 40, 50];
        }
        //按类别进行筛选
        if (!empty($params['categoryId'])) {
            $condition['c_first_level_categoryId'] = $params['categoryId'];
        }
        //按价格进行排序
        if (isset($params['orderByPrice']) && $params['orderByPrice'] !=='') {
            if ($params['orderByPrice'] == 0) {
                $condition['orderBy'] = ['c_trusteeship_amount ASC'];
            } else {
                $condition['orderBy'] = ['c_trusteeship_amount DESC'];
            }
        }
        list($list, $count) = $this->customLib->getList($pageIndex, $pageSize, $condition);
        if ($list) {
            $userLib = new User();
            foreach ($list as &$item) {
                $item = [
                    'c_id' => $item['c_id'],
                    'c_title' => $item['c_title'],
                    'c_desc' => $item['c_desc'],
                    'c_status' => $item['c_status_format'],
                    'c_createUserId' => $item['c_createUserId'],
                    'c_createDate_format' => DateHelper::getTimeSpanFormat($item['c_createDate'], time()),
                    'c_price' => $item['c_trusteeship_amount'],
                    'c_designatedUserId' => $item['c_designatedUserId']
                ];
                //获取投稿用户信息
                $customGoodsUserList = [];
                $customGoodsList = $this->customLib->getCustomGoodsList($item['c_id'], ['cg_auditStatus' => 1]);
                if ($customGoodsList) {
                    $customGoodsUserIds = array_column( $customGoodsList,'cg_createUserId');
                    $customGoodsUserInfoArray = $userLib->getUserInfo($customGoodsUserIds);
                    foreach ($customGoodsUserInfoArray as $customGoodsUserInfo) {
                        $customGoodsUserList[] = [
                            'u_id' => $customGoodsUserInfo['u_id'],
                            'u_nickname' => $customGoodsUserInfo['u_nickname'],
                            'u_avatar' => $customGoodsUserInfo['u_avatar']
                        ];
                    }
                }
                $item['customGoodsList'] = $customGoodsUserList;

                //获取定制发布者信息、指定用户信息
                $userIds[] = $item['c_createUserId'];
                if (!empty($item['c_designatedUserId'])) {
                    $userIds[] = $item['c_designatedUserId'];
                }
                $customGoodsUserInfoArray = $userLib->getUserInfo($userIds);
                if (isset($customGoodsUserInfoArray[$item['c_createUserId']])) {
                    $item['c_createUserNickname'] = $customGoodsUserInfoArray[$item['c_createUserId']]['u_nickname'];
                    $item['c_createUserAvatar'] = $customGoodsUserInfoArray[$item['c_createUserId']]['u_avatar'];
                } else {
                    $item['c_createUserNickname'] = '';
                    $item['c_createUserAvatar'] = '';
                }
                if (!empty($item['c_designatedUserId']) && isset($customGoodsUserInfoArray[$item['c_createUserId']])) {
                    $item['c_designatedUserNickname'] = $customGoodsUserInfoArray[$item['c_designatedUserId']]['u_nickname'];
                    $item['c_designatedUserAvatar'] = $customGoodsUserInfoArray[$item['c_designatedUserId']]['u_avatar'];
                } else {
                    $item['c_designatedUserNickname'] = '';
                    $item['c_designatedUserAvatar'] = '';
                }
            }
        }

        $this->responseJSON(['list' => $list, 'count' => $count]);
    }

    /**
     * 定制详情
     */
    public function detail()
    {
        $params = app()->request()->params();
        if (empty($params['id'])) {
            throw new ParamsInvalidException("定制id必须");
        }
        $customId = $params['id'];
        $customInfo = $this->customLib->getOneById($customId);
        //待支付、待审核、审核不通过、需求已关闭 只有发布者可以查看
        if ($customInfo['c_createUserId'] != $this->uid && in_array($customInfo['c_status_format'], [5, 15, 20, 100])) {
            throw new ParamsInvalidException("当前状态不能查看");
        }
        $userLib = new User();

        $data['c_id'] = $customInfo['c_id'];
        $data['c_title'] = $customInfo['c_title'];
        $data['c_categoryId'] = $customInfo['c_categoryId'];
        //定制类别
        $firstLevelCategoryData = (new GoodsCategory())->getOne($customInfo['c_first_level_categoryId']);
        $categoryData = (new GoodsCategory())->getOne($data['c_categoryId']);
        $firstLevelCategoryName = empty($firstLevelCategoryData['c_name']) ? '' : $firstLevelCategoryData['c_name'];
        $categoryName = empty($categoryData['c_name']) ? '' : $categoryData['c_name'];
        $data['c_categoryName'] = $firstLevelCategoryName . ($firstLevelCategoryName && $categoryName ? '-' : '') . $categoryName;
        $data['c_desc'] = $customInfo['c_desc'];
        $data['c_submitCount'] = $customInfo['c_submitCount'];
        $data['c_createDate_format'] = DateHelper::getTimeSpanFormat($customInfo['c_createDate'], time());
        $data['c_price'] = $customInfo['c_trusteeship_amount'];
        $data['c_status'] = $customInfo['c_status_format'];
        $data['c_submit_endDate'] = $customInfo['c_submit_endDate'];
        $data['c_submit_endDate_format'] = DateHelper::getTimeSpanFormat(time(), $customInfo['c_submit_endDate']);
        $data['c_select_goods_endDate_format'] = date('m-d', strtotime("{$customInfo['c_submit_endDate']}+3 day"));
        $data['c_is_extended_submit_endDate'] = $customInfo['c_is_extended_submit_endDate'];
        $data['images'] = $this->getImages($customId, 1);
        //获取定制发布者信息、指定用户信息
        $userIds[] = $customInfo['c_createUserId'];
        if (!empty($customInfo['c_designatedUserId'])) {
            $userIds[] = $customInfo['c_designatedUserId'];
        }
        $data['c_createUserId'] = $customInfo['c_createUserId'];
        $customGoodsUserInfoArray = $userLib->getUserInfo($userIds);
        if (isset($customGoodsUserInfoArray[$customInfo['c_createUserId']])) {
            $data['c_createUserNickname'] = $customGoodsUserInfoArray[$customInfo['c_createUserId']]['u_nickname'];
            $data['u_avatar'] = $customGoodsUserInfoArray[$customInfo['c_createUserId']]['u_avatar'];
        } else {
            $data['c_createUserNickname'] = '';
            $data['u_avatar'] = '';
        }
        $data['c_designatedUserId'] = $customInfo['c_designatedUserId'];
        if (!empty($customInfo['c_designatedUserId']) && isset($customGoodsUserInfoArray[$customInfo['c_createUserId']])) {
            $data['c_designatedUserNickname'] = $customGoodsUserInfoArray[$customInfo['c_designatedUserId']]['u_nickname'];
            $data['c_designatedUserAvatar'] = $customGoodsUserInfoArray[$customInfo['c_designatedUserId']]['u_avatar'];
        } else {
            $data['c_designatedUserNickname'] = '';
            $data['c_designatedUserAvatar'] = '';
        }
        $data['favoriteId'] = $this->getFavoriteId($this->uid, $data['c_id']);
        $data['c_orderSn'] = $customInfo['c_orderSn'];
        $isAllowExtendedSubmitEndDate = 0;
        //征稿中，未延长过，且征稿截止时间小于3天
        if ($customInfo['c_status_format'] == 31 && $customInfo['c_is_extended_submit_endDate'] == 0 && strtotime($customInfo['c_submit_endDate']) <= strtotime("3 day")) {
            $isAllowExtendedSubmitEndDate = 1;
        }
        $data['isAllowExtendedSubmitEndDate'] = $isAllowExtendedSubmitEndDate;

        $this->responseJSON($data);
    }

    private function getFavoriteId($uid, $customId)
    {
        $favoriteId = 0;
        if ($uid) {
            $favoriteId = app('mysqlbxd_app')->fetchColumn('select ufav_id from `user_favorite` where u_id=:u_id and ufav_type=6 and `ufav_objectKey`=:ufav_objectKey',
                [
                    'u_id' => $uid,
                    'ufav_objectKey' => $customId,
                ]);
            $favoriteId = intval($favoriteId);
        }
        return $favoriteId > 0 ? $favoriteId : 0;
    }

    /**
     * 获取投稿记录
     */
    public function getCustomGoods()
    {
        $params = app()->request()->params();
        if (empty($params['c_id'])) {
            throw new ParamsInvalidException("定制id必须");
        }
        $customId = $params['c_id'];
        $customData = $this->customLib->getOneById($customId);
        $list = $this->customLib->getCustomGoodsList($customId, ['cg_auditStatus' => 1]);

        if ($list) {
            $userIds = array_column($list, 'cg_createUserId');
            $userLib = new User();
            $userInfoArray = $userLib->getUserInfo($userIds, '', 1);
            foreach ($list as &$item) {
                $item = [
                    'cg_id' => $item['cg_id'],
                    'cg_size' => $item['cg_size'],
                    'cg_desc' => $item['cg_desc'],
                    'cg_createUserId' => $item['cg_createUserId'],
                    'cg_isSelected' => $item['cg_isSelected'],
                    'cg_createDate_format' => DateHelper::getTimeSpanFormat($item['cg_createDate'], time())
                ];

                //投稿者昵称、头像、是否签约
                if (isset($userInfoArray[$item['cg_createUserId']])) {
                    $item['c_createUserNickname'] = $userInfoArray[$item['cg_createUserId']]['u_nickname'];
                    $item['u_avatar'] = $userInfoArray[$item['cg_createUserId']]['u_avatar'];
                    $item['is_own_shop'] = $userInfoArray[$item['cg_createUserId']]['user_extend']['is_own_shop'];
                } else {
                    $item['c_createUserNickname'] = '';
                    $item['u_avatar'] = '';
                    $item['is_own_shop'] = 0;
                }
                //定制发布者、当前投稿用户、中标投稿 可以看到图片，其他情况不可见
                $images = $this->customLib->getImages($item['cg_id'], 2);
                if ($images) {
                    if ($this->uid == $customData['c_createUserId'] || $this->uid == $item['cg_createUserId'] || $item['cg_isSelected'] == 1) {
                        foreach ($images as &$image) {
                            $image['ci_img_url'] = FileHelper::getFileUrl($image['ci_img']);
                            unset($image['ci_img']);
                        }
                    } else {
                        foreach ($images as &$image) {
                            $image['ci_img_url'] = '';
                            unset($image['ci_img']);
                        }
                    }
                }
                $item['images'] = $images;
            }
        }

        $this->responseJSON($list);
    }

    /**
     * 选择投稿记录
     * @throws ParamsInvalidException
     */
    public function selectCustomGoods()
    {
        $params = app()->request()->params();
        if (!isset($params['c_id'])) {
            throw new ParamsInvalidException("缺少参数c_id");
        }
        if (!isset($params['cg_id'])) {
            throw new ParamsInvalidException("缺少参数cg_id");
        }
        $customId = $params['c_id'];
        $customGoodsId = $params['cg_id'];
        $customData = $this->customLib->getOneById($customId);
        if ($customData['c_createUserId'] != $this->uid) {
            throw new ParamsInvalidException("你无权限进行此操作");
        }
        if ($customData['c_status_format'] != 35) {
            throw new ParamsInvalidException("当前定制状态不能进行此操作");
        }
        $customGoodsData = $this->customLib->getOneCustomGoods($customGoodsId, $customId);
        if (empty($customGoodsData)) {
            throw new ParamsInvalidException("投稿记录不存在");
        } elseif ($customGoodsData['cg_auditStatus'] != 1) {
            throw new ParamsInvalidException("投稿记录非审核通过状态");
        }

        $customUpdateData = [
            'c_status' => 40,
            'c_selectedUserId'=>$customGoodsData['cg_createUserId']
        ];
        $customGoodsUpdateData = [
            'cg_isSelected' => 1
        ];
        $this->customLib->update($customId, $customUpdateData);
        $this->customLib->customGoodsUpdate($customId, $customGoodsUpdateData);

        $this->responseJSON(true);
    }

    /**
     * 新增定制订单
     * @throws ParamsInvalidException
     */
    public function addOrder()
    {
        $params = app()->request()->params();
        if (empty($params['customId'])) {
            throw  new ParamsInvalidException('缺少参数customId');
        }
        $resMall = (new Order())->addCustomOrder($params);
        $this->responseJSON(['order_id' => $resMall]);
    }

    /**
     * 修改定制订单
     * @throws ParamsInvalidException
     */
    public function updateOrder()
    {
        $params = app()->request()->params();
        if (empty($params['customId'])) {
            throw  new ParamsInvalidException('缺少参数customId');
        }
        if (!isset($params['customGoodsId'])) {
            throw new ParamsInvalidException("缺少参数customGoodsId");
        }
        $customData = $this->customLib->getOneById($params['customId']);
        if ($customData['c_createUserId'] != $this->uid) {
            throw new ParamsInvalidException("你无权限进行此操作");
        }

        $resMall = (new Order())->updateCustomOrder($params);
        $this->responseJSON(['order_id' => $resMall]);
    }

    private function checkRequired($data, $fields)
    {
        if (!empty($fields)) {
            foreach ($fields as $field) {
                if (!isset($data[$field])) {
                    throw  new ParamsInvalidException('参数：' . $field . '不能为空');
                }
            }
        }
    }

    private function getImages($id,$type)
    {
        $data = $this->customLib->getImages($id, $type);
        if ($data) {
            foreach ($data as &$item) {
                $item['ci_img_url'] = FileHelper::getFileUrl($item['ci_img']);
            }
        }

        return $data;
    }
}
