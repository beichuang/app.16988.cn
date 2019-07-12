<?php
namespace Controller\Office;
use Framework\Helper\FileHelper;
use Framework\Helper\Loader;
use Framework\Lib\CommonFun;
use Lib\Base\BaseController;
use Model\Common\searchWord;
use Model\News\News;
use Model\News\NewsCategory;
use Model\News\Venue;
use Model\User\Artist;
use Model\User\Setting;
use Model\User\UserExtend;
use Lib\Mall\GoodsCategory;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/25
 * Time: 19:42
 */
class Auxiliary  extends  BaseController
{
   //-------------------------1:艺术头条------------------------
     //艺术头条详情
    public function  hotNewsDetail(){
        $newsId = app()->request()->params('newsId', '');
        $newsDetail    = News::newsDetail($newsId);
        if($newsDetail){
            //真实阅读量 +1
            app('mysqlbxd_app')->update("news",['n_click_rate'=>$newsDetail['n_click_rate']+1],['n_id'=>$newsId]);
            $newsLib = new \Lib\News\News();
            $needs_data = NewsCategory::getOneColumn('nc_name','nc_id',$newsDetail['n_type']);
            $needs_data = isset($needs_data['nc_name'])&&$needs_data['nc_name']?$needs_data['nc_name']:'';
            //类型
            $newsDetail['c_type'] = $needs_data;
            //标题
            $newsDetail['n_title'] = htmlspecialchars_decode($newsDetail['n_title']);
            //资讯图片   （根据新闻id  获取 新闻所属主图）
            $newsDetail['img'] = $newsLib->newsImg($newsDetail['n_id'], 6);
            //内容处理
            $newsDetail['n_content']=(new \Lib\News\News())->parseVideo($newsDetail['n_content']);
            $newsDetail['n_content']=(new \Lib\News\News())->parseEmbed($newsDetail['n_content']);
            // 几小时之前发布的
            $time = strtotime($newsDetail['n_update_date']);
            $newsDetail['displayTime'] = date_format_to_display($time);
            //发布时间
            $newsDetail['n_update_date'] = date('Y-m-d', strtotime($newsDetail['n_update_date']));
            //消息来源
            $newsDetail['n_anthor'] = $newsDetail['n_anthor'] ? $newsDetail['n_anthor'] : $newsDetail['n_from'];
            // 阅读量等于   真实阅读量 + 默认初始阅读量
            $newsDetail['n_click_rate'] = $newsDetail['n_click_rate'] +  $newsDetail['n_default_click_rate'];
        }
        $this->responseJSON($newsDetail);
    }
    /**
     * 热门艺术头条详情
     * @return array
     */
    public function hotHeadlines(){
            $NewsLib = new \Lib\News\News();
            $result = $NewsLib->getList(['page' => 1, 'pageSize' => 13,'pcOrder'=>1,'is_show_item'=>1]);
            $hot_new_list = $result['count'] > 0 ? $result['list'] : [];
            foreach ($hot_new_list as &$val) {
                $time = strtotime($val['n_update_date']);
                $val['displayTime'] = date_format_to_display($time);
                $new_img = $NewsLib->newsImg($val['n_id'], 1);
                $val['hostImg'] = is_array($new_img) && !empty($new_img) ? $new_img[0]['ni_img'] : '';
                $val['n_type_name'] = app('mysqlbxd_app')->fetchColumn("select nc_name from news_category where  nc_id={$val['n_type']}");
            }
            $this->responseJSON($hot_new_list);
    }

    /**
     * 头条分类
     * @return array
     */
    public function  newsCategory(){
        $params['parentId'] = 0;
        $params['isShow'] = 1;
        $params['status'] = 0;
        $newsCategory   = Loader::M('NewsCategory')->getCategory($params);
        $this->responseJSON($newsCategory);
    }

    /**
     * 发布头条
     * @throws \Exception\ParamsInvalidException
     */
    public function publishNews(){
        $infoId = app()->request()->params('infoId','');
        if($infoId){
            //查询头条信息是否存在
            $news   = app('mysqlbxd_app')->fetchColumn("select n_status from news where n_id={$infoId}");
            if(!$news){
                throw new \Exception\ParamsInvalidException("您要操作的头条不存在！");
            }
            if(!in_array($news,[-1,1])){
                throw new \Exception\ParamsInvalidException("已经发布成功了！");
            }
        }
        $params = app()->request()->params();
        $setList = $imageList  = array();
        $setList['n_form']    =  1;     //类型：1=头条，2=课堂   默认为头条
        $setList['publisher'] = $this->uid;
        $setList['n_status']  =  1;     //这个时候是待审核状态
        //头条标题
        if (isset($params['title'])&&$params['title']) {
            $setList['n_title'] = $params['title'];
        }else{
            throw new \Exception\ParamsInvalidException("标题不能为空！");
        }
        //头条类型
        if(isset($params['type'])&&$params['type']){
            $setList['n_type'] = $params['type'];
        }else{
            throw new \Exception\ParamsInvalidException("类型不能为空！");
        }
        //头条主图  设置
        if(isset($params['pic']) &&$params['pic']){
            $imageList['ni_img'] = $params['pic'];
        }else{
            throw new \Exception\ParamsInvalidException("请上传主图！");
        }
        //内容
        if (isset($params['content'])&&trim($params['content'])) {
            $setList['n_content']=(new \Framework\Model\NewsContent())->saveThirdNewsImages2Oss($params['content']);
            $setList['n_content'] = str_replace('http://', 'https://', $setList['n_content']);
        }else{
            throw new \Exception\ParamsInvalidException("内容不能为空！");
        }
        //来源
        $setList['n_anthor'] = isset($params['source']) ? $params['source'] : '';

        //录入人群来源
        $setList['dispatch_crowd'] = 2;
        //修改
        if($infoId){    //修改
            $setList['n_update_date'] = date("Y-m-d H:i:s");
            $upSt = app('mysqlbxd_app')->update('news', $setList, ['n_id' => $infoId]);
            $n_id = $infoId;
        }else{
            //时间录入
            $setList['n_update_date'] = date("Y-m-d H:i:s");
            $setList['n_create_date'] = date("Y-m-d H:i:s");
            $upSt = app('mysqlbxd_app')->insert('news', $setList);
            $n_id = $upSt?$upSt[1]:'';
        }

        if ($upSt) {
            //录入主图信息
            if ($imageList) {
                $imageUrl = FileHelper::getFileUrl($imageList['ni_img'], 'news_images');
                $imageInfo = getimagesize($imageUrl);
                $imageList['ni_sort'] = 0;
                $imageList['ni_width'] = $imageInfo[0];
                $imageList['ni_height'] = $imageInfo[1];
                $imageList['n_id'] = $n_id;
                $model_image = new \Framework\Lib\SimpleModel('news_image', app('mysqlbxd_app'));
                $listRs['img'] = $infoId?$model_image->findOne('ni_img', " n_id={$infoId} "):'';
                if ($listRs['img']) {    //主图 修改
                    app('mysqlbxd_app')->update('news_image', $imageList, ['n_id' => $infoId]);
                }else {                  // 主图 录入
                    app('mysqlbxd_app')->insert('news_image', $imageList);
                }
            }
            //更新网站地图   后期问题解决
//            $siteMapFullName = __DIR__.'/../../../pc.16988.cn/Public/sitemap/sitemap_all.txt';
//            $newsUrl = "https://www.16988.cn/toutiao/{$upSt[1]}.html";
//            FileHelper::writeFile($siteMapFullName, PHP_EOL . $newsUrl, false);
            $this->responseJSON(['status' => true,'msg' => '操作成功']);
        } else {
            throw new \Exception\ParamsInvalidException("操作失败！");
        }
    }

    /**
     * 我发布的头条
     * @throws \Exception\ParamsInvalidException
     */
    public function myPublish(){
        //我发布的头条
        $news                 = new News();
        $where                = [];
        $where['publisher']  = $this->uid;
        $where['pcOrder']    = 1;
        $where["requestType"]='pc';   //请求类型
        $page                 = app()->request()->params('page',1);
        $pagesize             = app()->request()->params('pagesize',12);
        $newsList             = $news->publishList($where,$page,$pagesize);
        $this->responseJSON($newsList);
    }

    /**
     * 发布头条详情
     * @throws \Exception\ParamsInvalidException
     */
     public function NewsDetail(){
         $infoId = app()->request()->params('infoId','');
         $infoId = $infoId ? abs(intval($infoId)) : 0;
         $model = new \Framework\Lib\SimpleModel('news', app('mysqlbxd_app'));
         $listRs['cont'] = $model->findOne('', " n_id={$infoId} ");
         $model_image = new \Framework\Lib\SimpleModel('news_image', app('mysqlbxd_app'));
         $listRs['img'] = $model_image->findOne('ni_img', " n_id={$infoId} ");
         if ($listRs['img']['ni_img']) {
             $listRs['img']['ni_img'] = FileHelper::getFileUrl($listRs['img']['ni_img'], 'news_images');
         }
         $this->responseJSON($listRs);
     }

    //--------------------------------2: 商城-----------------------------------------

    //商城模块接口
    public function mall(){
        // status=3  出售中   isHaveStock=>1  是否有库存     pageSize=>4条信息  g_categoryId=>categoryId  商品分类id
        $firstLoad   =  app()->request()->params('firstLoad','') ;       //是否是初次加载
        $categoryId = app()->request()->params('categoryId','') ;
        $params['page'] = app()->request()->params('page',1);
        $params['pageSize'] = app()->request()->params('pageSize',4) ;
        $result = api_request(['status' => 3, 'isHaveStock' => 1,'page'=>$params['page'],'pageSize' =>$params['pageSize'],'categoryId'=>$categoryId,'goodsOrderColumn'=>'sjtime-desc'], 'mall/item/query');
        $goods_list = [];
        if ($result && $result['count'] > 0) {
            $goods_list = $result['list'];
            $user_list = api_request(['uids' => implode(',', array_column($goods_list, 'g_salesId')), 'needExtend' => 1], 'user/get');
            foreach ($goods_list as $key => $val) {
                $goods_list[$key]['u_avatar'] = $user_list[$val['g_salesId']]['u_avatar'];
                $realname = $user_list[$val['g_salesId']]['u_realname'];
                $goods_list[$key]['u_realname'] = empty($realname) ? $user_list[$val['g_salesId']]['u_nickname'] : $realname;
                if ($val['g_width'] == 0 || $val['g_high'] == 0 ) {
                    $goods_list[$key]['goods_size'] = isset($val['itemAttr'][0]['ga_value'])?$val['itemAttr'][0]['ga_value']:'';
                } else {
                    $goods_list[$key]['goods_size'] = $val['g_width'] . 'x' . $val['g_high'];
                }
                $goods_list[$key]['goods_image'] = empty($val['g_surfaceImg']['gi_img']) ? $val['image'][0]['gi_img'] : $val['g_surfaceImg']['gi_img'];
            }
        }
        $goods_lists['list']  = $goods_list;
        $goods_lists['count'] = $result['count'];
        //首页初次加载全部   或  不是首页初次加载全部  --调取  顶部分类样式
        if($firstLoad==1){
            $goodsCategory = new GoodsCategory();
            $goods_lists['goods_category'] = $goodsCategory->getListByParentId(['parentIds' => 0]);
            array_unshift($goods_lists['goods_category'],['c_id'=>0,'c_name'=>'全部']);
        }
        $this->responseJSON($goods_lists);
        return $goods_list;
    }









    //热门商品 -- 精选推荐(精选商品)
    public function hotGoods(){
        $categoryId = 0;
        $result = api_request(['categoryId' => $categoryId, 'status' => 3, 'isHaveStock' => 1, 'pageSize' => 6], 'mall/item/Handpick/query');
        $goods_list = [];
        if ($result && $result['count'] > 0) {
            $user_list = api_request(['uids' => implode(',', array_column($result['list'], 'g_salesId')), 'needExtend' => 1], 'user/get');
            foreach ($result['list'] as $key => $val) {
                // 商品id
                $goods_list['list'][$key]['g_id']        = $val['g_id'];
                //商户姓名
                $realname = $user_list[$val['g_salesId']]['u_realname'];
                $goods_list['list'][$key]['u_realname'] = empty($realname) ? $user_list[$val['g_salesId']]['u_nickname'] : $realname;
                //商品尺寸
                if ($val['g_width'] == 0 || $val['g_high'] == 0) {
                    $goods_list['list'][$key]['goods_size'] = isset($val['itemAttr'][0]['ga_value']) ? $val['itemAttr'][0]['ga_value'] :$val['g_width'] . 'x' . $val['g_high'];
                } else {
                    $goods_list['list'][$key]['goods_size'] = $val['g_width'] . 'x' . $val['g_high'];
                }
                //商品价格
                $goods_list['list'][$key]['g_price'] = $val['g_price'];
                //商品种类名称
                $goods_list['list'][$key]['categoryName'] = $val['categoryName'];
                $goods_list['list'][$key]['g_name']        = $val['g_name'];
                //商品图片
                $goods_list['list'][$key]['goods_image'] = empty($val['g_surfaceImg']['gi_img']) ? $val['image'][0]['gi_img'] : $val['g_surfaceImg']['gi_img'];
            }
        }
        $this->responseJSON(array_values($goods_list['list']));
    }


  //-------------------------------3:  艺术人物-----------------------------------------
    //热门艺术人物  和   首页 艺术人物访问同一个接口
    //艺术家详情
    public function  artistDetail(){
        $user_list1 = [];
        $artistId = app()->request()->params('artistId', '');
        $from     = app()->request()->params('from', 0);

        //站内艺术家详情
        if($from==0){
            $artist   =  UserExtend::getOneColumn('*','u_id',$artistId);
            if ($artist) {
                $user_list = api_request(['uids' =>$artistId, 'needExtend' => 1], 'user/get');
                $user_list = $user_list[$artist['u_id']];
                $user_list1['u_id'] = $artist['u_id'];
                //艺术家头像
                $user_list1['u_avatar']  = $user_list['u_avatar'];
                //艺术家姓名
                $realname                 = $user_list['u_realname'];
                $user_list1['u_realname'] = empty($realname) ? $user_list['u_nickname'] : $realname;
                //艺术家介绍
                $user_list1['desc']        =  (new Setting())->settingGetValue($artist['u_id'],'introduction');
                //艺术家浏览次数
                $user_list1['ue_browse_artist'] = $artist['ue_browse_artist'];
                //发布时间
                $user_list1['publish_time'] = date('Y-m-d',strtotime($artist['ue_updateDate']));
                //来源
                $user_list1['source']         = config('app.website_name');
                // 浏览次数+1
                app('mysqlbxd_user')->update('user_extend',[
                    'ue_browse_artist'=>$user_list1['ue_browse_artist']+1
                ],['u_id'=>$artist['u_id']]);
            }
        }
        //站外艺术家详情
        if($from==1){
            //获取艺术家详情信息
            $sql     = "select * from  outside_artist WHERE artistId ={$artistId}  ";
            $artist  =  app('mysqlbxd_user')->fetch($sql);
            if($artist){
                $user_list1['u_id']       = $artistId;
                //艺术家头像
                //头像处理
                if ($artist['u_avatar']) {
                    $user_list1['u_avatar'] = FileHelper::getFileUrl($artist['u_avatar'], 'user_avatar');
                }else{
                    $user_list1['u_avatar']='https://cdn.16988.cn/res/html/pc/images/morentouxiang.png';
                }
                //艺术家姓名
                $user_list1['u_realname'] = $artist['u_realname'];
                //艺术家介绍
                $user_list1['desc']        = $artist['desc'];
                //艺术家浏览次数
                $user_list1['ue_browse_artist'] = $artist['ue_browse_artist'];
                //发布时间
                $user_list1['publish_time']   = date('Y-m-d',strtotime($artist['publish_time']));
                //来源
                $user_list1['source']          = $artist['source']?$artist['source']:'';
                //修改浏览次数
                app('mysqlbxd_user')->update('outside_artist',[
                    'ue_browse_artist'=>$user_list1['ue_browse_artist']+1
                ],['artistId'=>$artistId]);
            }
        }
        $this->responseJSON($user_list1);
    }

    //艺术人物列表    艺术人物列表
    public function artistList(){
        // 艺术人物·标签
        $type                 = app()->request()->params('typeName', 0);
        $page                 = app()->request()->params('page',1);
        $pagesize             = app()->request()->params('pagesize',15);
        $last_page            = app()->request()->params('last_page',0);
        $diff_num             = app()->request()->params('diff_num',0);
        // 排序标示
        $order                = app()->request()->params('sort','');
        $condition['remove'] = 0;
       //获取艺术人物信息
       $artists      = Artist::getList($page,$pagesize,$type,$last_page,$diff_num,$order,$condition);
       $artists_list = $artists['list'];
       //判断是否是最后一页
       foreach ($artists_list as $k=>&$v){
           //头像处理
           if ($v['u_avatar']) {
               $v['u_avatar'] = FileHelper::getFileUrl($v['u_avatar'], 'user_avatar');
           }else{
               $v['u_avatar']='https://cdn.16988.cn/res/html/pc/images/morentouxiang.png';
           }
           //查询艺术家真实姓名
           if(isset($v['u_id'])){
               $sql  = "select uce_realName  from user_certification  where u_id={$v['u_id']}";
               $uce_realName = app('mysqlbxd_app')->fetchColumn($sql);
               $v['u_realname'] = $uce_realName?$uce_realName:$v['u_nickname'];
           }
       }
        $this->responseJSON(["artist"=>$artists_list,'last_page'=>$artists['last_page'],'diff_num'=>$artists['diff_num']]);
    }

    /**
     * 热门艺术人物
     * @throws \Exception\ParamsInvalidException
     */
     public function hotArtistList(){
         $hotArtists  = Artist::hotArtists();
         foreach ($hotArtists as &$v){
             if ($v['u_avatar']) {
                 $v['u_avatar'] = FileHelper::getFileUrl($v['u_avatar'], 'user_avatar');
             }else{
                 $v['u_avatar']='https://cdn.16988.cn/res/html/pc/images/morentouxiang.png';
             }
             //艺术家介绍
              $v['desc']        =  (new Setting())->settingGetValue($v['u_id'],'introduction');
         }
         $this->responseJSON($hotArtists);
     }


    //------------------- 4: 展览展会-------------------------------
    // 1:展会详情信息
    public  function  exhibitionDetail(){
         $exhibitionId = app()->request()->params('exhibitionId','');
         if(!$exhibitionId){
             throw new \Exception\ParamsInvalidException("exhibitionId不能为空！");
         }
         $exhibition   = new \Model\News\Exhibition();
         $exhibition    = $exhibition->getOneLine(['e_id'=>$exhibitionId]);
         if($exhibition){
             $exhibition['source_from']  = !$exhibition['source_from']?config('app.website_name'):$exhibition['source_from'];
             $exhibition['publish_time'] = !$exhibition['publish_time']?date('Y-m-d H:i'):$exhibition['publish_time'];
             $exhibition['image'] =  FileHelper::getFileUrl($exhibition['image']);
             //展会开始时间  （没有具体时间点   默认早上5点）
             $exhibition['start_time'] = str_replace("00:00:00","09:00",$exhibition['start_time']);
             //展会结束时间   下午5点
             $exhibition['end_time'] = str_replace("00:00:00","17:00",$exhibition['end_time']);
             // 浏览次数+1
             app('mysqlbxd_app')->update('exhibition',[
                 'browse_times'=>$exhibition['browse_times']+1
             ],['e_id'=>$exhibitionId]);
         }
        $this->responseJSON($exhibition);
    }
    /**
     * 2:
     * 按照访问量  从高到低（无访问量或者访问量相同按照审核时间倒序）
     */
    public function  hotExhibition(){
         $hotExhibition  = \Model\News\Exhibition::hotExhibition();
         if($hotExhibition){
              foreach($hotExhibition as $k=>$v){
                  $hotExhibition[$k]['publish_time']    = date_format_to_display(strtotime($v['publish_time']));
              }
         }
        $this->responseJSON($hotExhibition);
    }

    //------------------- 5:文玩场馆详情-------------------------------
    /**
     * 热门城市   热门分类
     */
    public function  venueCategoryCity()
    {
        $type = app()->request()->params('type', '');
        $data = searchWord::venueHotMessage($type);
        $this->responseJSON($data);
    }
    /**
     *  文玩场馆详情
     */
    public function  venueDetail(){
        $venueId = app()->request()->params('venueId', '');
        if(!$venueId){
            throw new \Exception\ParamsInvalidException("venueId不能为空！");
        }
        $venueDetail   = Venue::venueDetail($venueId);
        $venueDetail['image'] = FileHelper::getFileUrl($venueDetail['image']);
        //浏览次数加1
        app('mysqlbxd_app')->update('venue',[
            'browse_times'=>$venueDetail['browse_times']+1
        ],['venue_id'=>$venueId]);
        $this->responseJSON($venueDetail);
    }
    //------------------- 6:艺术圈子-------------------------------
   //具有独立的艺术圈

    /**
     *圈子列表
     * @throws ModelException
     */
    public function circleLists()
    {
        $param = array(
            't_status' => 0
        );
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (!$page || !$pageSize) {
            throw new ParamsInvalidException('参数错误');
        }

        $t_desc = app()->request()->params('t_desc');
        if ($t_desc) {
            $param['t_desc'] = $t_desc;
            //模糊词收录
            searchWord::keywordsCollect($param['t_desc']);
        }

        $type = app()->request()->params('type');
        if ($type && !in_array($type, ['1', '2', '3', '4'])) { //1自己 2关注 3好友 4所有用户
            throw new ParamsInvalidException('参数错误');
        }

        $uid = app()->request()->params('uid');
        $param['u_id'] = $uid ? $uid : $this->uid;
        /**
        if ($type == 1) {
            $param['u_id'] = $uid ? $uid : $this->uid;
        } else {
            if (in_array($type, ['2', '3'])) {
                if (empty($this->uid)) {
                    throw new ParamsInvalidException('未登录');
                }
                $friendsCtl = new \Controller\User\Friends();
                $userLists = $friendsCtl->getRelateList($type);
                if (isset($userLists['list']) && $userLists['list']) {
                    $param['u_id'] = $userLists['list'];
                } else {
                    $param['u_id'] = 0;
                }
            }
        }
          **/
        $topic = app()->request()->params('topic');
        //latest最新的，morelike点赞多的
        $sort = app()->request()->params('sort');
        if($sort && in_array($sort,['latest','morelike'])){
            $param['sort']=$sort;
        }
        $topicModel=new \Model\Treasure\TreasureTopic();
        if($topic && ($topic=explode(',',$topic))){
            try{
                $topicModel->isTopicNos($topic);
                $param['topic']=$topic;
            }catch (\Exception $e){

            }
        }
        $TreasureModel = new \Model\Treasure\Treasure();
        $rest = $TreasureModel->lists($param, $page, $pageSize);
        $res       =  $rest[0];
        $res_count = 0;
        if (is_array($res) && !empty($res)) {
            $treasureImgModel = new \Model\Treasure\TreasureImage();
            $userLib = new \Lib\User\User();
            foreach ($res as &$value) {
                list ($pic,) = $treasureImgModel->lists(['t_id' => $value['t_id']], 1, 10);
                $value['t_pictures'] = $pic;
                $this->applyCustomizeInfo($value);
                //关联的话题
                $value['topics']=$topicModel->getTreasureRefTopics($value['t_id']);
            }
            $userLib->extendUserInfos2Array($res, 'u_id', array(
                'u_nickname' => 't_nickname',
                'u_avatar' => 't_avatar',
                'u_realname' => 't_realname',
            ));
            $res_count = $rest[1];
        }
        $res_end  = [$res,$res_count];
        $this->responseJSON($res_end);
    }

    /**
     *
     * @param $row
     */
    private function applyCustomizeInfo(&$row)
    {
        if(!$id=$row['t_business_id']){
            $row['t_share_title'] = '';
            $row['t_share_url'] = '';
        } else{
            $customize=app('mysqlbxd_mall_common')->fetch('select `c_id`, `c_title` from custom where c_id ='.$id);
            $row['t_share_title'] = $customize['c_title'];
            $row['t_share_url'] = 'https:' . config('app.CDN.BASE_URL_RES') . '/res/zw2.5/index.html#/detail';
        };
    }



















}

