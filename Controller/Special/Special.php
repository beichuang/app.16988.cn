<?php

namespace Controller\Special;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ModelException;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Lib\Common\Region;
use Rest\Mall\Facade\VoucherManager;
use Rest\Mall\Facade\VoucherTemplateManager;

class Special extends BaseController
{

    private $adLib = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取专题
     * @throws ModelException
     */
    public function query()
    {
        $params = app()->request()->params();
        if (empty($params['cs_id'])) {
            throw new ParamsInvalidException("活动id必须");
        }

        $data_arr = [];

        $cs_id = $params['cs_id'];

        $specialLib = new \Lib\Special\Special();

        $data = $specialLib->getOne(['cs_id' => $cs_id]);

        //$favModel = new \Model\User\Favorite();
        //$myuid = isset($this->uid) && $this->uid ? $this->uid : 0;
        $summary_arr = $wonderful_arr = $video_arr = $report_arr = $celebrity_arr = $backdrop_arr = $merchant_arr = [];

        if ($data) {
            $cs_summary_str = explode(',', $data['cs_summary']);
            //var_dump($cs_summary_str);
            foreach ($cs_summary_str as $key => $val) {
                if ($key < 1) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $summary_arr[$key]['nid'] = $newsMessage['n_id'];
                        $summary_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $summary_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $summary_arr[$key]['title'] = $newsMessage['n_title'];
                        $summary_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 90, 'utf-8');
                    }
                }
            }
            //精彩瞬间
            $cs_wonderful_str = explode(',', $data['cs_wonderful']);
            //var_dump($cs_summary_str);
            foreach ($cs_wonderful_str as $key => $val) {
                if ($key <= 3) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $wonderful_arr[$key]['nid'] = $newsMessage['n_id'];
                        $wonderful_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $wonderful_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $wonderful_arr[$key]['title'] = $newsMessage['n_title'];
                        $wonderful_arr[$key]['describe'] = $newsMessage['n_describe'];
                    }
                }

            }
            //视频
            $cs_video_str = explode(',', $data['cs_video']);
            //var_dump($cs_summary_str);
            foreach ($cs_video_str as $key => $val) {
                if ($key < 1) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $video_arr[$key]['nid'] = $newsMessage['n_id'];
                        $video_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $video_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $video_arr[$key]['title'] = $newsMessage['n_title'];
                        $video_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 20, 'utf-8');
                    }
                }
            }
            //文字报道
            $cs_report_str = explode(',', $data['cs_report']);
            //var_dump($cs_summary_str);
            foreach ($cs_report_str as $key => $val) {
                if ($key < 1) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $report_arr[$key]['nid'] = $newsMessage['n_id'];
                        $report_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $report_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $report_arr[$key]['title'] = $newsMessage['n_title'];
                        $report_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 20, 'utf-8');
                    }
                }

            }
            //名家专访
            $cs_celebrity_str = explode(',', $data['cs_celebrity']);
            //var_dump($cs_summary_str);
            foreach ($cs_celebrity_str as $key => $val) {
                if ($key <= 1) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $celebrity_arr[$key]['nid'] = $newsMessage['n_id'];
                        $celebrity_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $celebrity_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $celebrity_arr[$key]['title'] = $newsMessage['n_title'];
                        $celebrity_arr[$key]['describe'] = $newsMessage['n_describe'];
                    }
                }

            }
            //推荐商户
            $cs_merchant_str = explode(',', $data['cs_merchant']);
            //var_dump($cs_summary_str);
            foreach ($cs_merchant_str as $key => $val) {
                if ($key <= 1) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $merchant_arr[$key]['nid'] = $newsMessage['n_id'];
                        $merchant_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $merchant_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $merchant_arr[$key]['title'] = $newsMessage['n_title'];
                        $merchant_arr[$key]['describe'] = $newsMessage['n_describe'];
                    }
                }

            }
            //活动背景
            $cs_backdrop_str = explode(',', $data['cs_backdrop']);
            //var_dump($cs_summary_str);
            foreach ($cs_backdrop_str as $key => $val) {
                if ($key < 1) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $backdrop_arr[$key]['nid'] = $newsMessage['n_id'];
                        $backdrop_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $backdrop_arr[$key]['img'] = $img_arr[0]['ni_img'];

                        $backdrop_arr[$key]['title'] = $newsMessage['n_title'];
                        $backdrop_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 20, 'utf-8');
                    }
                }
            }
        }
        $data_arr['summary'] = $summary_arr;
        $data_arr['wonderful'] = $wonderful_arr;
        $data_arr['video'] = $video_arr;
        $data_arr['report'] = $report_arr;
        $data_arr['celebrity'] = $celebrity_arr;
        $data_arr['merchant'] = $merchant_arr;
        $data_arr['backdrop'] = $backdrop_arr;

        $this->responseJSON($data_arr);
    }

    public function queryNew()
    {
        $params = app()->request()->params();
        if (empty($params['ss_id'])) {
            throw new ParamsInvalidException("活动id必须");
        }

        $ss_id = $params['ss_id'];
        $data = $this->_getSpecialInfo($params['ss_id']);
        $data['ss_desc_bg_img'] = FileHelper::getFileUrl(json_decode($data['ss_desc_bg_img'], 1)['img'], '');
        $data['ss_banner_img'] = FileHelper::getFileUrl(json_decode($data['ss_banner_img'], 1)['img'], '');
        $data['ss_share_img'] = FileHelper::getFileUrl(json_decode($data['ss_share_img'], 1)['img'], '');
        $data['anchors']=[];
        if ($data) {
            //获取模块内容
            $sql = 'SELECT * FROM show_special_column WHERE ss_id=:ss_id order by sc_sort asc';
            $columnList = app('mysqlbxd_app')->select($sql, ['ss_id' => $ss_id]);

            //整理模块内容
            if (count($columnList)) {
                foreach ($columnList as $key => &$item) {
                    $columnList[$key]['sc_img'] = FileHelper::getFileUrl(json_decode($columnList[$key]['sc_img'], 1)['img']);
                    if ($item['sc_type'] == 3) {
                        //文章列表
                        $n_ids = $item['sc_business_ids'];
                        $sql = "SELECT * FROM news WHERE n_id in({$n_ids})";
                        $columnRes = app('mysqlbxd_app')->select($sql);

                        foreach ($columnRes as $keyIn => $itemIn) {
                            $columnList[$key]['businessData'][$keyIn]['title'] = $itemIn['n_title'];
                            $newsLib = new \Lib\News\News();
                            $columnList[$key]['businessData'][$keyIn]['nid'] = $itemIn['n_id'];
                            $img_arr = $newsLib->newsImg($itemIn['n_id'], 1);
                            $columnList[$key]['businessData'][$keyIn]['img'] = empty($img_arr) ? '' : $img_arr[0]['ni_img'];
                        }
                    } elseif ($item['sc_type'] == 2) {
                        //商品列表
                        $item['businessData'] = $this->getGoodsListIndex($item['sc_business_ids']);
                    } elseif ($item['sc_type'] == 4) {
                        //优惠券列表
                        $item['businessData'] = $this->getVoucherList($item['sc_business_ids']);
                    }
                    if(trim($item['sc_anchor'])){
                        $data['anchors'][]=[
                            'title'=>trim($item['sc_anchor']),
                            'anchor'=>$key,
                        ];
                    }
                }
            }

            $listResult = $data;
            $listResult['column'] = $columnList;
            $this->responseJSON($listResult);
        } else {
            $error['msg'] = "无数据";
            $this->responseJSON($error, 1, 1);
        }
    }


    /**
     * 专题列表
     *
     * @throws ModelException
     */
    public function lists()
    {
        $data_arr = [];

        $cs_id = 3;

        $type = app()->request()->params('type');
        if (!$type) {
            throw new ParamsInvalidException("类型必须");
        }


        $page = app()->request()->params('page', 1);
        $pagesize = app()->request()->params('pagesize', 6);

        $specialLib = new \Lib\Special\Special();

        $data = $specialLib->getOne(['cs_id' => $cs_id]);

        $summary_arr = $wonderful_arr = $video_arr = $report_arr = $celebrity_arr = $backdrop_arr = $merchant_arr = [];

        switch ($type) {
            case 'summary':
                $cs_summary_str = explode(',', $data['cs_summary']);
                //var_dump($cs_summary_str);
                foreach ($cs_summary_str as $key => $val) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $summary_arr[$key]['nid'] = $newsMessage['n_id'];
                        $summary_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $summary_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $summary_arr[$key]['title'] = $newsMessage['n_title'];
                        $summary_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 90, 'utf-8');
                    }
                }

                if ($page) {
                    $summary_arr = array_slice($summary_arr, ($page - 1) * $pagesize, $pagesize);
                }

                $data_arr['summary'] = $summary_arr;

                break;
            case 'wonderful':
                //精彩瞬间
                $cs_wonderful_str = explode(',', $data['cs_wonderful']);
                //var_dump($cs_summary_str);
                foreach ($cs_wonderful_str as $key => $val) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $wonderful_arr[$key]['nid'] = $newsMessage['n_id'];
                        $wonderful_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $wonderful_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $wonderful_arr[$key]['title'] = $newsMessage['n_title'];
                        $wonderful_arr[$key]['describe'] = $newsMessage['n_describe'];
                    }
                }

                if ($page) {
                    $wonderful_arr = array_slice($wonderful_arr, ($page - 1) * $pagesize, $pagesize);
                }

                $data_arr['wonderful'] = $wonderful_arr;

                break;
            case 'video':
                //视频
                $cs_video_str = explode(',', $data['cs_video']);
                //var_dump($cs_summary_str);
                foreach ($cs_video_str as $key => $val) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $video_arr[$key]['nid'] = $newsMessage['n_id'];
                        $video_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $video_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $video_arr[$key]['title'] = $newsMessage['n_title'];
                        $video_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 20, 'utf-8');
                    }
                }
                if ($page) {
                    $video_arr = array_slice($video_arr, ($page - 1) * $pagesize, $pagesize);
                }

                $data_arr['video'] = $video_arr;

                break;
            case 'report':
                //文字报道
                $cs_report_str = explode(',', $data['cs_report']);
                //var_dump($cs_summary_str);
                foreach ($cs_report_str as $key => $val) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $report_arr[$key]['nid'] = $newsMessage['n_id'];
                        $report_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $report_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $report_arr[$key]['title'] = $newsMessage['n_title'];
                        $report_arr[$key]['describe'] = mb_substr($newsMessage['n_describe'], 0, 20, 'utf-8');
                    }
                }

                if ($page) {
                    $report_arr = array_slice($report_arr, ($page - 1) * $pagesize, $pagesize);
                }

                $data_arr['report'] = $report_arr;

                break;
            case 'celebrity':
                //名家专访
                $cs_celebrity_str = explode(',', $data['cs_celebrity']);
                //var_dump($cs_summary_str);
                foreach ($cs_celebrity_str as $key => $val) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $celebrity_arr[$key]['nid'] = $newsMessage['n_id'];
                        $celebrity_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $celebrity_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $celebrity_arr[$key]['title'] = $newsMessage['n_title'];
                        $celebrity_arr[$key]['describe'] = $newsMessage['n_describe'];
                    }
                }

                if ($page) {
                    $celebrity_arr = array_slice($celebrity_arr, ($page - 1) * $pagesize, $pagesize);
                }


                $data_arr['celebrity'] = $celebrity_arr;

                break;
            case 'merchant':
                //推荐商户
                $cs_merchant_str = explode(',', $data['cs_merchant']);
                //var_dump($cs_summary_str);
                foreach ($cs_merchant_str as $key => $val) {
                    $condition['n_id'] = $val;
                    $condition['n_status'] = 0;

                    $newsMod = new \Model\News\News();

                    $newsMessage = $newsMod->getOneLine($condition);
                    //var_dump($newsMessage);exit;
                    if ($newsMessage) {
                        $newsLib = new \Lib\News\News();
                        $time = strtotime($newsMessage['n_update_date']);
                        $merchant_arr[$key]['nid'] = $newsMessage['n_id'];
                        $merchant_arr[$key]['displayTime'] = date_format_to_display($time);

                        $img_arr = $newsLib->newsImg($newsMessage['n_id'], 1);
                        $merchant_arr[$key]['img'] = $img_arr[0]['ni_img'];
                        $merchant_arr[$key]['title'] = $newsMessage['n_title'];
                        $merchant_arr[$key]['describe'] = $newsMessage['n_describe'];
                    }
                }

                if ($page) {
                    $merchant_arr = array_slice($merchant_arr, ($page - 1) * $pagesize, $pagesize);
                }

                $data_arr['merchant'] = $merchant_arr;
                break;
            default:
                break;
        }

        $this->responseJSON($data_arr);
    }

    /**
     * 专题报名
     */
    public function showSignUp()
    {
        $params = app()->request()->params();
        if (empty($params['ssu_name']) || empty($params['ssu_phone']) || empty($params['ssu_item'])) {
            throw new ParamsInvalidException("缺少参数");
        }

        $res = app('mysqlbxd_app')->insert('show_sign_up', $params);
        $this->responseJSON($res);
    }

    /**
     * 栏目列表
     */
    public function listNew()
    {
        $page = app()->request()->params('page', 1);
        $pagesize = app()->request()->params('pagesize', 6);
        $params = app()->request()->params();
        if (empty($params['sc_id'])) {
            throw new ParamsInvalidException("缺少参数");
        }
        $sc_id = $params['sc_id'];

        $sql = 'SELECT * FROM show_special_column WHERE sc_id=:sc_id';
        $columnInfo = app('mysqlbxd_app')->fetch($sql, ['sc_id' => $sc_id]);
        $data = [];
        $columnList = [];
        if($columnInfo) {
            $specialId = $columnInfo['ss_id'];
            $specialData = $this->_getSpecialInfo($specialId);
            $data['ss_banner_img'] = FileHelper::getFileUrl(json_decode($specialData['ss_banner_img'], 1)['img'], '');
            $data['sc_title_img'] = FileHelper::getFileUrl(json_decode($columnInfo['sc_img'], 1)['img']);
            if ($columnInfo['sc_type'] == 3) {
                //文章列表
                //
                $n_ids = $columnInfo['sc_business_ids'];
                $sql = "SELECT * FROM news WHERE n_id in({$n_ids})";
                $columnRes = app('mysqlbxd_app')->select($sql);

                $newsLib = new \Lib\News\News();
                foreach ($columnRes as $keyIn => $itemIn) {
                    $columnList[$keyIn]['title'] = $itemIn['n_title'];
                    $columnList[$keyIn]['nid'] = $itemIn['n_id'];
                    $img_arr = $newsLib->newsImg($itemIn['n_id'], 1);
                    $columnList[$keyIn]['img'] = isset($img_arr[0]['ni_img']) ? $img_arr[0]['ni_img'] : '';
                }
            } elseif ($columnInfo['sc_type'] == 2) {
                $columnList = $this->getGoodsList($columnInfo['sc_business_ids']);
            }
        }
        $data['count'] = count($columnList);
        if ($page) {
            $columnList = array_slice($columnList, ($page - 1) * $pagesize, $pagesize);
        }
        $data['businessData'] = $columnList;
        $data['ss_background_color'] = $this->_getSpecialInfo($sc_id)['ss_background_color'];

        $this->responseJSON($data);
    }

    private function getVoucherList($ids)
    {
        $columnList = [];
        if (!$ids) {
            return $columnList;
        }
        $pageIndex = 0;
        $pageSize = 99;
        $condition['receiveValidityPeriod'] = 1; //领取有效期
        $condition['v_t_id'] = explode(',', $ids);
        $condition['getType'] = 4; //领取方式-免费领取
        $list = VoucherTemplateManager::getTemplateList($condition, $pageIndex, $pageSize);
        if ($list) {
            foreach ($list as $item) {
                list($status) = VoucherManager::getVoucherReceiveStatus($item, $this->uid);
                $columnList[] = [
                    'v_t_id' => $item['v_t_id'],
                    'v_t_desc' => $item['v_t_desc'],
                    'v_t_price' => $item['v_t_price'],
                    'v_t_status' => $status,
                    'v_t_use_desc' => $this->getVoucherUseDesc($item)
                ];
            }
        }

        return $columnList;
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

    private function getGoodsListIndex($g_ids)
    {
        $columnList = [];
        if(!$g_ids){
            return $columnList;
        }
        $goodsRet=(new \Lib\Mall\Goods())->itemQuery([
            'id'=>$g_ids,
            'pageSize'=>100,
        ]);
        if($goodsRet['list']){
            $goodsMap=array_column($goodsRet['list'],null,'g_id');
            $gids=explode(',',$g_ids);
            foreach ($gids as $gid){
                if($gid && isset($goodsMap[$gid])){
                    $itemIn=$goodsMap[$gid];
                    $columnListItem=[];
                    $columnListItem['g_name'] = $itemIn['g_name'];
                    $columnListItem['g_id'] = $itemIn['g_id'];
                    $columnListItem['g_width'] = $itemIn['g_width'];
                    $columnListItem['g_high'] = $itemIn['g_high'];
                    $columnListItem['g_price'] = $itemIn['g_price'];
                    $columnListItem['isSecKill'] = $itemIn['isSecKill'];
                    $columnListItem['g_activityPrice'] = $itemIn['g_activityPrice'];
                    $columnListItem['categoryName'] = $itemIn['categoryName2'];
                    $columnListItem['img'] = $itemIn['g_surfaceImg']['gi_img'];
                    $columnListItem['size'] = '';
                    if(isset($itemIn['itemAttr']) && is_array($itemIn['itemAttr'])){
                        foreach ($itemIn['itemAttr'] as $subRow){
                            if($subRow['ga_key']=='尺寸'){
                                $columnListItem['size'] = $subRow['ga_value'];
                                break;
                            }
                        }
                    }
                    $columnList[]=$columnListItem;
                }
            }
        }

        return $columnList;
    }

    private function getGoodsList($g_ids)
    {
        //临时取固定数据
        $columnList = [
            [
                'g_name' => '福禄祥和',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/1548452159/A721.png'
            ],
            [
                'g_name' => '虎',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/1555215566/C161.png'
            ],
            [
                'g_name' => '渡荆门送别',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/1555385439/C168.png'
            ],
            [
                'g_name' => '春宫怨',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/1556039658/C211.png'
            ],
            [
                'g_name' => '春风得意',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/1556187975/A1-62.png'
            ],
            [
                'g_name' => '鱼乐图',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/1556327537/A1-172.png'
            ],
            [
                'g_name' => '尊贤容众',
                'g_id' => 0,
                'img' => 'https://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/2113047665/7.png'
            ],

            [
                'g_name' => '龙',
                'g_id' => 0,
                'img' => 'https://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/2113047665/8.png'
            ],
            [
                'g_name' => '精进修持',
                'g_id' => 0,
                'img' => 'http://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/2113047665/9.png'
            ],
            [
                'g_name' => '岳阳楼记',
                'g_id' => 0,
                'img' => 'https://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/2113047665/11.png'
            ],
            [
                'g_name' => '书斋题联（上）',
                'g_id' => 0,
                'img' => 'https://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/2113047665/10.png'
            ],
            [
                'g_name' => '书斋题联（下）',
                'g_id' => 0,
                'img' => 'https://zhangwan-picture-prod.oss-cn-hangzhou.aliyuncs.com/aliyun_oss/mall_goods_attr_images/201806/15/2113047665/12.png'
            ]
        ];

        return $columnList;

        $columnList = [];
        //$g_ids = $columnInfo['sc_business_ids'];
        $sql = "SELECT * FROM goods WHERE g_id in({$g_ids})";
        $columnRes = app('mysqlbxd_mall_user')->select($sql);
        if ($columnRes) {
            foreach ($columnRes as $keyIn => $itemIn) {
                $columnList[$keyIn]['g_name'] = $itemIn['g_name'];
                $columnList[$keyIn]['g_id'] = $itemIn['g_id'];
                $columnList[$keyIn]['img'] = $this->getGoodsImageUrl($itemIn['g_id']);
            }
        }

        return $columnList;
    }

    private function getGoodsImageUrl($goodsId)
    {
        $imageUrl = '';
        $image = app('mysqlbxd_mall_common')->fetch('SELECT * FROM goods_image WHERE g_id=:gid ORDER BY gi_sort LIMIT 1', [':gid' => $goodsId]);
        if ($image) {
            $imageUrl = FileHelper::getFileUrl($image['gi_img'], 'mall_goods_attr_images');
        }

        return $imageUrl;
    }

    private function _getSpecialInfo($ss_id)
    {
        $sql = 'SELECT * FROM show_special WHERE ss_is_show=1 AND ss_id=:ss_id';
        $data = app('mysqlbxd_app')->fetch($sql, ['ss_id' => $ss_id]);

        return $data;
    }
}
