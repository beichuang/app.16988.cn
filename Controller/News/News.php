<?php
/**
 * 首页
 * @author Administrator
 *
 */

namespace Controller\News;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ModelException;
use Exception\ParamsInvalidException;
use Model\Common\searchWord;

class News extends BaseController
{

    protected $userApi = null;

    public function __construct()
    {
        parent::__construct();
        $this->userApi = get_api_client('User');
    }

    public function detail()
    {

        $n_id = app()->request()->params('n_id');
        $n_id=intval($n_id);
        $data=[
            'CDN_BASE_URL_RES' => config('app.CDN.BASE_URL_RES'),
            'n_title'=>'资讯详情',
            'displayTime'=>'--',
            'n_click_rate'=>'--',
            'n_content'=>'--',
        ];
        if ($n_id) {
            $news=app('mysqlbxd_app')->fetch('select * from news where n_id=:n_id and n_status=0',[
                'n_id'=>$n_id
            ]);
            if($news){
                //增加点击量
                app('mysqlbxd_app')->update('news',[
                    'n_click_rate' => $news['n_click_rate'] + 1
                ],[
                    'n_id'=>$n_id
                ]);
                $time = strtotime($news['n_update_date']);
                $news['displayTime'] = date_format_to_display($time);
                $content = htmlspecialchars_decode($news['n_content']);
                $news['n_content']=(new \Lib\News\News())->parseVideo($news['n_content']);
                $news['n_content']=(new \Lib\News\News())->parseEmbed($news['n_content']);
//                $str = '<video src="$1" controls="controls" style="width: 100%;background-color: #000;" poster="https://zhangwan-video.oss-cn-hangzhou.aliyuncs.com/img/video_poster.jpg"></video>';
//                $news['n_content'] = preg_replace("/\[\[\[(.*?)\]\]\]/is", $str, $content);
                // 阅读量等于 真实阅读量 + 默认初始阅读量
                $news['n_click_rate'] = $news['n_click_rate'] +  $news['n_default_click_rate'];
                $data['n_title']=$news['n_title'];
                $data['displayTime']=$news['displayTime'];
                $data['n_click_rate']=$news['n_click_rate'];
                $data['n_content']=$news['n_content'];
                $data['n_subtitle']=$news['n_subtitle'];
                $data['n_picurl']=FileHelper::getFileUrl($news['n_picurl'],'news_images');
            }
        }
        app()->render('html/infoDetail', $data);
    }
    /**
     * 获取新闻列表
     *
     * @throws ModelException
     */
    public function query()
    {
        $params = app()->request()->params();
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 20);

        $params['is_index'] = 0;
        $params['addTime'] = 1;
        $params['recommend'] = 0;
        //模糊词收录
        if(isset($params['n_title'])&&trim($params['n_title'])){
            searchWord::keywordsCollect($params['n_title']);
        }
        $newsLib = new \Lib\News\News();

        $data = $newsLib->getList($params);

        $favModel = new \Model\User\Favorite();
        $myuid = isset($this->uid) && $this->uid ? $this->uid : 0;

        foreach ($data['list'] as &$val) {
            $val['n_title'] = htmlspecialchars_decode($val['n_title']);
            $time = strtotime($val['n_update_date']);
            $val['displayTime'] = date_format_to_display($time);
            $val['img'] = $newsLib->newsImg($val['n_id'], 6);
            $favInfo = $favModel->oneByUfavObjectId($myuid, $val['n_id']);
            $val['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
            $val['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
            $val['n_update_date'] = date('Y-m-d', strtotime($val['n_update_date']));
            $val['n_anthor'] = $val['n_anthor'] ? $val['n_anthor'] : $val['n_from'];
            // 阅读量等于 真实阅读量 + 默认初始阅读量
            $val['n_click_rate'] = $val['n_click_rate'] +  $val['n_default_click_rate'];
        }

        $this->responseJSON($data);
    }

    /**
     * 资讯分享信息
     *
     * 参数:
     *  id 商品id
     */
    public function share_info()
    {
        // $data = [];
        $n_id = app()->request()->params('n_id');
        if (!$n_id) {
            throw new ParamsInvalidException("资讯id必须");
        }

        $where[] = " n_id=:n_id ";
        $condition['n_id'] = $n_id;
        $where[] = " n_status=:n_status ";
        $condition['n_status'] = 0;
        $where = implode(' and ', $where);

        $newsMod = new \Model\News\News();
        $newsMessage = $newsMod->one($where, $condition);
        if (empty($newsMessage)) {
            throw new ParamsInvalidException("该资讯不存在");
        }
        $img = $newsMod->getImg($n_id, 1);

        $base_url_res = conf('app.CDN.BASE_URL_RES');
        $base_url = conf('app.request_url_schema_x_forwarded_proto_default');

        if ($img[0]){
            $image = FileHelper::getFileUrl($img[0]['ni_img'], 'news_images');
        }else {
            $image = $base_url.':'.$base_url_res.'/html/images/fenxianglogo.jpg';
        }
        $data['share_info'] = [
            'title' => $newsMessage['n_title'],
            'image' => $image,
            'url' => '/html/infoDetail.html?n_id=' . $newsMessage['n_id'],
        ];
        /*$data['share_info'] = [
            'title' => $newsMessage['n_title'],
            'image' => $base_url.':'.$base_url_res.'/html/images/fenxianglogo.jpg',
            'url' => '/html/infoDetail.html?n_id='.$newsMessage['n_id'],
        ];*/
        $data['share_info']['content'] = $newsMessage['n_subtitle'] ? $newsMessage['n_subtitle'] : "懂生活,更懂艺术。最新最热的艺术头条，尽在掌玩APP。";

        $this->responseJSON($data);
    }

    /**
     * 分享头条文章
     * @throws ParamsInvalidException
     */
    public function share()
    {
        $n_id = app()->request()->params('n_id');
        if (!$n_id) {
            throw new ParamsInvalidException("资讯id必须");
        }
        $data=(new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_SHARE_NEWS_ADD);
        $this->responseJSON($data);
    }
    /**
     * 获取新闻资讯详情
     *
     */
    public function newsInfo()
    {
        $n_id = app()->request()->params('n_id');
        if (!$n_id) {
            throw new ParamsInvalidException("资讯id必须");
        }
        $type = app()->request()->params('type', 0);

        $where[] = " n_id=:n_id ";
        $condition['n_id'] = $n_id;
        $where[] = " n_status=:n_status ";
        $condition['n_status'] = 0;
        $where = implode(' and ', $where);

        $newsMod = new \Model\News\News();
        $newsMessage = $newsMod->one($where, $condition);
        if (empty($newsMessage)) {
            throw new ParamsInvalidException("该资讯不存在");
        }
        //增加点击量
        $newsMod->update($n_id, ['n_click_rate' => $newsMessage['n_click_rate'] + 1]);

        $newsLib = new \Lib\News\News();
        $time = strtotime($newsMessage['n_update_date']);
        $newsMessage['displayTime'] = date_format_to_display($time);
        $newsMessage['img'] = $newsLib->newsImg($newsMessage['n_id'], 6);

        $myuid = isset($this->uid) && $this->uid ? $this->uid : 0;
        $favModel = new \Model\User\Favorite();
        $favInfo = $favModel->oneByUfavObjectId($myuid, $newsMessage['n_id']);
        $newsMessage['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
        $newsMessage['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';

        $content = htmlspecialchars_decode($newsMessage['n_content']);
        if ($type == 1) {  //pc端需要把https替换成http
            $content = str_replace('https://', 'http://', $content);
        }
        $str = '<video src="$1" controls="controls" style="width: 100%;background-color: #000;"></video>';
        $newsMessage['n_content'] = preg_replace("/\[\[\[(.*?)\]\]\]/is", $str, $content);
        // 阅读量等于 真实阅读量 + 默认初始阅读量
        $newsMessage['n_click_rate'] = $newsMessage['n_click_rate'] +  $newsMessage['n_default_click_rate'];

        $this->responseJSON($newsMessage);
    }

    /**
     * 对数据进行初级过滤
     *
     * @param string $data
     *            要处理的数据
     * @param string $filter
     *            过滤的方式
     * @return mix
     */
    private function handleData($data = '', $filter = '')
    {
        switch ($filter) {
            case 'int':
                return abs(intval($data));
                break;

            case 'str':
                return trim(htmlspecialchars(strip_tags($data)));
                break;

            case 'float':
                return floatval($data);
                break;

            case 'arr':
                return (array)$data;
                break;
        }

        return '';
    }

    /**
     * 热门头条
     *
     * @throws ModelException
     */
    public function hotNews()
    {
        $params = app()->request()->params();
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 10);
        $params['is_index'] = app()->request()->params('is_index', 0);//推荐首页

        //$params['addTime'] = 1;
        //$params['click_rate'] = 1;
        //$params['dianzan'] = 1;

        $newsLib = new \Lib\News\News();

        $data = $newsLib->getList($params);

        $favModel = new \Model\User\Favorite();
        $myuid = isset($this->uid) && $this->uid ? $this->uid : 0;

        foreach ($data['list'] as &$val) {
            $val['n_title'] = htmlspecialchars_decode($val['n_title']);
            $time = strtotime($val['n_update_date']);
            $val['displayTime'] = date_format_to_display($time);
            $val['img'] = $newsLib->newsImg($val['n_id'], 6);
            $favInfo = $favModel->oneByUfavObjectId($myuid, $val['n_id']);
            $val['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
            $val['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
            $val['n_update_date'] = date('Y-m-d', strtotime($val['n_update_date']));
            $val['n_anthor'] = $val['n_anthor'] ? $val['n_anthor'] : $val['n_from'];

            // 阅读量等于 真实阅读量 + 默认初始阅读量
            $val['n_click_rate'] = $val['n_click_rate'] +  $val['n_default_click_rate'];
        }

        $this->responseJSON($data);
    }

    /**
     * 经纪人课堂
     */
    public function classroom()
    {
        $params = app()->request()->params();
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 20);

        $params['form'] = 2;
        $params['addTime'] = 1;
        $params['recommend'] = 0;

        $newsLib = new \Lib\News\News();

        $data = $newsLib->getList($params);

        $favModel = new \Model\User\Favorite();
        $myuid = isset($this->uid) && $this->uid ? $this->uid : 0;

        foreach ($data['list'] as &$val) {
            $time = strtotime($val['n_update_date']);
            $val['displayTime'] = date_format_to_display($time);
            $val['img'] = $newsLib->newsImg($val['n_id'], 6);
            $favInfo = $favModel->oneByUfavObjectId($myuid, $val['n_id']);
            $val['favStatus'] = $favInfo && is_array($favInfo) ? 1 : 0;
            $val['favId'] = isset($favInfo['ufav_id']) ? $favInfo['ufav_id'] : '';
            $val['n_update_date'] = date('Y-m-d', strtotime($val['n_update_date']));
            $val['n_anthor'] = $val['n_anthor'] ? $val['n_anthor'] : $val['n_from'];
        }

        $this->responseJSON($data);
    }
}
