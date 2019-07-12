<?php

namespace Controller\Common;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Model\User\Setting;
use Rest\Mall\Facade\ItemManager;

class Ad extends BaseController {

    private $adLib = null;

    public function __construct() {
        parent::__construct();
        $this->adLib = new \Lib\Mall\Ad();
    }

    /**
     * 广告列表
     */
    public function lists()
    {
        $params = app()->request()->params();
        $params['status'] = 1; //审核通过
        $params['mark'] = (isset($params['mark']) && $params['mark']) ? $params['mark'] : 1; //广告位置
        $params['isInDate'] = 1; //有效期内
        $data = $this->adLib->lists($params);

        $newsLib = new \Lib\News\News();
        $favModel = new \Model\User\Favorite();
        $myUid = $this->uid ? $this->uid : '';
        $showData = [];
        if (!empty($data['list'])) {
            foreach ($data['list'] as $key => $value) {
                $item = [
                    'type' => $value['a_type'],
                    'link' => $value['a_link'],
                    'sort' => $value['a_sort'],
                    'endDate' => strtotime($value['a_endDate']),
                    'img' => $value['a_image'],
                    'a_image' => $value['a_image'],
                    'imageColor' => $value['a_image_color'],
                    'title' => $value['a_text'],
                    'desc' => ''
                ];
                //首页商品分类位置的广告，标题字段做特殊处理，拆分为两部分
                if ($value['a_mark'] == 3) {
                    $text = explode('-', $value['a_text']);
                    $item['title'] = $text[0];
                    $item['n_title'] = $text[1];
                }

                switch ($value['a_type']) {
                    //1:商品 2:资讯 3:图文链接 4:首页分类 6:文本 7:小程序内部页面 8:艺术家
                    case '1': //商品
                        if (empty($item['a_image'])) {
                            $goodsId = $value['a_link']; //商品类型的广告，a_link字段存储商品id
                            $goodsImage = ItemManager::getItemImageById($goodsId);
                            $item['a_image'] = $item['img'] = current($goodsImage)['gi_img'];
                        }
                        break;
                    case '2': //资讯
                        $newsId = $value['a_link']; //资讯类型的广告，a_link字段存储资讯id
                        //如果咨询类型的广告图为空时，取咨询的第一张图片
                        if (empty($value['a_image'])) {
                            //获取资讯图片
                            $imageList = $newsLib->newsImg($newsId, 1);
                            if ($imageList && is_array($imageList)) {
                                $imageUrl = current($imageList)['ni_img'];
                                $item['a_image'] = $item['img'] = $imageUrl;
                            }
                        }
                        //获取当前用户收藏情况
                        $favInfo = $favModel->oneByUfavObjectId($myUid, $newsId, 4);
                        if ($favInfo) {
                            $item['favStatus'] = 1;
                            $item['favId'] = $favInfo['ufav_id'];
                        } else {
                            $item['favStatus'] = 0;
                            $item['favId'] = '';
                        }
                        break;
                    case '4':   //商品分类
                        $categoryId = '';
                        $imageUrl = '';
                        if (!empty($value['a_data'])) {
                            $categoryData = json_decode($value['a_data'], true);
                            $adImageType = isset($categoryData['adImageType']) && $categoryData['adImageType'] == 2 ? 2 : 1;
                            if ($adImageType == 2) {
                                $categoryImageData = isset($categoryData['goodsCategoryImages']) ? $categoryData['goodsCategoryImages'] : [];
                                //获取用户的喜好分类，如果设置则在喜好分类中随机显示一个分类，否则在所有分类中随机显示一个分类
                                if ($this->uid) {
                                    $hobbyData = (new Setting())->settingGetValue($this->uid, Setting::KEY_HOBBY);
                                }
                                if (!empty($hobbyData)) {
                                    $randIndex = rand(0, count($hobbyData) - 1);
                                    $categoryId = $hobbyData[$randIndex]['c_id'];
                                    $imagePath = isset($categoryImageData[$categoryId]) ? $categoryImageData[$categoryId] : '';
                                } else {
                                    $categoryId = array_rand($categoryImageData);
                                    $imagePath = $categoryImageData[$categoryId];
                                }
                                $imageUrl = empty($imagePath) ? '' : FileHelper::getFileUrl($imagePath, 'mall_ad_images');
                            }
                        }
                        $item['link'] = $imageUrl ? $categoryId : $value['a_link'];
                        $item['a_image'] = $item['img'] = $imageUrl ?: $value['a_image'];
                        break;
                    case '8': //
                        if (!empty($value['a_link'])) {
                            $userId = $value['a_link']; //艺术家类型的广告，a_link字段存储艺术家用户id
                            $introduction = (new Setting())->settingGetValue($userId, 'introduction');
                            $item['desc'] = $introduction;
                        }
                        break;
                }
                isset($item['link'])&&$item['link']?$item['link']=urldecode($item['link']):null;
                $showData[] = $item;
            }
        }

        $this->responseJSON($showData);
    }

    /**
     * 启动页
     */
    public function startAppAd() {
        $params = app()->request()->params();
        $params['status'] = 1;
        $params['mark'] = 2;
        $params['isInDate'] = 1;
        //var_dump($params);
        $data = $this->adLib->lists($params);

        $newsLib = new \Lib\News\News();
        $newsMod = new \Model\News\News();
        $favModel = new \Model\User\Favorite();
        $goodsLib = new \Lib\Mall\Goods();
        $myuid = $this->uid ? $this->uid : '';

        $showData = array();
        //foreach ($data['list'] as $key => $value) {
        if ($data['list']) {
            $ad_arr = $data['list'][0];
            switch ($ad_arr['a_type']) {
                case '1':
                    $showData = array('type' => $ad_arr['a_type'], 'link' => $ad_arr['a_link'], 'sort' => $ad_arr['a_sort']);
                    //$goodsInfo = $goodsLib->detailGet(array('id'=>$ad_arr['a_link']));
                    //$showData['img'] = current($goodsInfo['itemImage'])['gi_img'];
                    $showData['endDate'] = strtotime($ad_arr['a_endDate']);
                    $showData['a_image'] = $ad_arr['a_image'];
//                     $favInfo = $favModel->oneByUfavObjectId($myuid, $ad_arr['a_link'], 0);
//                     $showData['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
//                     $showData['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
                    break;
                case '2':
                    $showData = array('type' => $ad_arr['a_type'], 'link' => $ad_arr['a_link'], 'sort' => $ad_arr['a_sort']);
                    $showData['a_image'] = $ad_arr['a_image'];
                    $showData['endDate'] = strtotime($ad_arr['a_endDate']);
                    //$imageList  = $newsLib->newsImg($ad_arr['a_link'],1);
                    //if ( $imageList && is_array($imageList) ) {
                    //    $imageUrl = current($imageList)['ni_img'];
//                         $showData['img'] = $imageUrl;
//                     } else {
//                         $showData['img'] = '';
//                     }
                    //$favInfo = $favModel->oneByUfavObjectId($myuid, $ad_arr['a_link'], 4);
                    //$showData['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
                    //$showData['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
                    break;
                case '3':
                    //$showData[$key] = $value;

                    $showData = array('id' => $ad_arr['a_id'], 'type' => $ad_arr['a_type'], 'link' => $ad_arr['a_link'], 'sort' => $ad_arr['a_sort']);
                    $showData['a_image'] = $ad_arr['a_image'];
                    $showData['endDate'] = strtotime($ad_arr['a_endDate']);

                    //$imageList  = $newsLib->newsImg($ad_arr['a_link'],1);
                    //$showData['img'] = '';
                    //$favInfo = $favModel->oneByUfavObjectId($myuid, $ad_arr['a_link'], 4);
                    //$showData['favStatus'] = 0;
                    //$showData['favId'] = '';
                    break;
                default:
                    # code...
                    break;
            }
        }
        //}

        if (empty($showData)) {
            $showData = null;
        }

        $this->responseJSON($showData);
    }

    /**
     * 首页弹出框
     */
    public function dialog() {
        $myuid = app()->request()->params('uid', $this->uid);
        $params['status'] = 1;
        $params['mark'] = 4;
        $params['isInDate'] = 1;
        $data = $this->adLib->lists($params);

        $newsLib = new \Lib\News\News();
        $newsMod = new \Model\News\News();
        $favModel = new \Model\User\Favorite();
        $goodsLib = new \Lib\Mall\Goods();

        $showData = array();
        foreach ($data['list'] as $key => $value) {
            if ($value['a_type'] != 5) {
                switch ($value['a_type']) {
                    case '1':
                        $showData[$key] = array('type' => $value['a_type'], 'link' => $value['a_link'], 'sort' => $value['a_sort']);
                        $goodsInfo = $goodsLib->detailGet(array('id' => $value['a_link']));
                        $showData[$key]['img'] = current($goodsInfo['itemImage'])['gi_img'];
                        $showData[$key]['endDate'] = strtotime($value['a_endDate']);
                        $showData[$key]['a_image'] = $value['a_image'];
                        $favInfo = $favModel->oneByUfavObjectId($myuid, $value['a_link'], 0);
                        $showData[$key]['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
                        $showData[$key]['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
                        break;
                    case '2':
                        $showData[$key] = array('type' => $value['a_type'], 'link' => $value['a_link'], 'sort' => $value['a_sort']);
                        $showData[$key]['a_image'] = $value['a_image'];
                        $showData[$key]['endDate'] = strtotime($value['a_endDate']);
                        $imageList = $newsLib->newsImg($value['a_link'], 1);
                        if ($imageList && is_array($imageList)) {
                            $imageUrl = current($imageList)['ni_img'];
                            $showData[$key]['img'] = $imageUrl;
                        } else {
                            $showData[$key]['img'] = $value['a_image'];
                        }
                        if (!$showData[$key]['img']) {
                            $showData[$key]['img'] = $value['a_image'];
                        }
                        $favInfo = $favModel->oneByUfavObjectId($myuid, $value['a_link'], 4);
                        $showData[$key]['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
                        $showData[$key]['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
                        break;
                    case '3':
                        //$showData[$key] = $value;

                        $showData[$key] = array('type' => $value['a_type'], 'link' => $value['a_link'], 'sort' => $value['a_sort']);
                        $showData[$key]['a_image'] = $value['a_image'];
                        $showData[$key]['endDate'] = strtotime($value['a_endDate']);

                        $imageList = $newsLib->newsImg($value['a_link'], 1);

                        $showData[$key]['img'] = $value['a_image'];

                        $favInfo = $favModel->oneByUfavObjectId($myuid, $value['a_link'], 4);
                        $showData[$key]['favStatus'] = 0;
                        $showData[$key]['favId'] = '';
                        break;
                    default:
                        # code...
                        break;
                }

                $showData[$key]['a_id'] = $value['a_id'];
                if ($myuid) {
                    $adLog['uid'] = $myuid;
                    $adLog['aid'] = $value['a_id'];
                    $adLog['type'] = $value['a_type'];
                    $result = $this->adLib->adLog($adLog);
                    $showData[$key]['status'] = $result;
                }
            }
        }

        $this->responseJSON($showData);
    }

}
