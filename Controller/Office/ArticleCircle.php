<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/30
 * Time: 16:17
 */

namespace Controller\Office;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;

class ArticleCircle  extends BaseController
{

    public function __construct() {
        parent::__construct();
        parent::pcSiteTitle();
    }

    /**
     * 圈子列表  对外访问
     */
    public function index() {
        $page      = app()->request()->params('page', 1);
        $pageSize  = app()->request()->params('pagesize', 6);
        $page      =intval($page);
        list($data['circle_list'], $data['count']) = $this->circle_list($page, $pageSize);
        $this->responseJSON($data);
    }


    /**
     * 艺术圈列表搜索
     * @param $page
     * @param $pageSize
     * @param $search
     * @param $type
     * @return mixed
     */
    public static  function circle_list_search($search,$page,$pageSize,$type="default"){
        list($data['circle_list'], $data['count']) = self::circle_list($page, $pageSize,$search);
        if($type=="default"){
            return $data['circle_list'];
        }
        return $data;
    }



    /**
     * 活跃圈友
     */
    public function activeFriend(){
        $active_list  = $this->active_circle();
        $this->responseJSON($active_list);
    }

    /**
     * @param $page
     * @param $pageSize
     * @param $search      //  t_desc 发布圈子内容模糊搜索
     * @return array
     */
    private static  function circle_list($page, $pageSize,$search='') {
        $condition  = [];
        $search?$condition['t_desc'] = trim($search):null;
        $condition['t_status'] = 0;
        $TreasureModel = new \Model\Treasure\Treasure();
        $result = $TreasureModel->lists($condition, $page, $pageSize);
        $topicModel=new \Model\Treasure\TreasureTopic();
        if ($result && is_array($result[0])&&!empty($result[0])&& $result[1] > 0) {
            $user_list = api_request(['uids' => implode(',', array_column($result[0], 'u_id')), 'needExtend' => 1], 'user/get');
            $treasureImgModel = new \Model\Treasure\TreasureImage();
            foreach ($result[0] as &$val) {
                $val['t_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $val['t_nickname'] = $user_list[$val['u_id']]['u_nickname'];
                list ($pic, ) = $treasureImgModel->lists(['t_id' => $val['t_id']], 1, 9);
                $val['t_pictures'] = $pic;
                $val['displayTime'] = date_format_to_display(strtotime($val['t_createDate']));
                //头条类型圈子处理
                if ($val['t_type'] == 2 && !empty($val['t_business_id'])) {
                    self::setShareData($val);
                }
                //关联的话题
                $val['topics']=$topicModel->getTreasureRefTopics($val['t_id']);
            }
        }
        return $result;
    }


    /**
     * @return array
     */
    private function active_circle() {
        $TreasureModel = new \Model\Treasure\Treasure();
        $result = $TreasureModel->getActiveLists(['t_status' => 0], 1, 6);
        $circle_list = [];
        $topicModel=new \Model\Treasure\TreasureTopic();
        if ($result && is_array($result[0]) && $result[1] > 0) {
            $circle_list = $result[0];
            $user_list = api_request(['uids' => implode(',', array_column($circle_list, 'u_id')), 'needExtend' => 1], 'user/get');

            $treasureImgModel = new \Model\Treasure\TreasureImage();
            $firendsModel = new \Model\Friends\Friends();
            foreach ($circle_list as $key => &$val) {
                $circle_list[$key]['t_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $circle_list[$key]['t_nickname'] = $user_list[$val['u_id']]['u_nickname'];
                list ($pic, ) = $treasureImgModel->lists(['t_id' => $val['t_id']], 1, 10);
                $circle_list[$key]['t_pictures'] = $pic;
                $circle_list[$key]['displayTime'] = date_format_to_display(strtotime($val['t_createDate']));
                $circle_list[$key]['goodsNum'] = isset($user_list[$val['u_id']]['user_extend']['ue_goodsNum']) ? $user_list[$val['u_id']]['user_extend']['ue_goodsNum'] : 0;

                //关注和粉丝数
                list(, $fansNum) = $firendsModel->lists(['fri_friendId' => $val['u_id']]);
                list(, $attentionNum) = $firendsModel->lists(['fri_userId' => $val['u_id']]);
                $circle_list[$key]['fansNum'] = $fansNum;
                $circle_list[$key]['attentionNum'] = $attentionNum;
                //关联的话题
                $val['topics']=$topicModel->getTreasureRefTopics($val['t_id']);
            }
        }
        return $circle_list;
    }

    private static  function setShareData(&$value)
    {
        $schema = config('app.request_url_schema_x_forwarded_proto_default');
        $cdnBaseUrl = config('app.CDN.BASE_URL_RES');
        $baseDomain = config('app.CDN.SITE_URL');

        $business_id = $value['t_business_id'];
        //获取头条信息
        $newsModel = new \Model\News\News();
        $newsMessage = $newsModel->one('n_status = 0 AND n_id=:n_id', [':n_id' => $business_id]);
        if ($newsMessage) {
            $imagePath = $newsMessage['n_picurl'];
            if (empty($imagePath)) {
                $newImages = $newsModel->getImg($business_id, 1);
                if (!empty($newImages[0]['ni_img'])) {
                    $imagePath = $newImages[0]['ni_img'];
                }
            }

            if (!empty($imagePath)) {
                $value['t_share_picture'] = FileHelper::getFileUrl($imagePath, 'news_images');
            } else {
                $value['t_share_picture'] = $schema . ':' . $cdnBaseUrl . '/html/images/fenxianglogo.jpg';
            }
            $value['t_share_title'] = $newsMessage['n_title'];
            $value['t_share_url'] = $schema . '://' . $baseDomain . "/toutiao/{$business_id}.html";
        } else {
            $value['t_share_picture'] = $schema . ':' . $cdnBaseUrl . '/html/images/fenxianglogo.jpg';
            $value['t_share_title'] = '';
            $value['t_share_url'] = '';
        }
    }


    //发布圈子



}