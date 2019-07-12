<?php
namespace Controller\Office;
use Lib\Mall\GoodsCategory;
use Framework\Helper\FileHelper;
use Model\User\Setting;
use Lib\Base\BaseController;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/24
 * Time: 8:34
 */
class HomePage  extends  BaseController
{
    /**
     * 首页广告图收集
     * 26 轮播如   27中部图  28底部图
     * http://{{host}}/common/ad/lists
     */



    /**
     * 获取分类头条信息
     * 注释：
     *1：左侧4个广告位显示头条中排序最大的4个，最大的排在第一个位置
     *2: 每个分类下 显示12条最新的头条信息  倒序显示
     *3: 默认是独家列表信息
     * @param page    //头条分页信息
     *
     */
    public function  hotNews(){
        $params['page'] = app()->request()->params('page', 1);
        $params['pageSize'] = app()->request()->params('pageSize', 12);
        $params['n_type']   =  app()->request()->params('n_type', '');    //默认情况下 是独家信息
          //按照推荐顺序排序
        //1：加载掌玩商城的时候 把 艺术头条分类加载进去  2：把艺术人物加载进去
        $params['homepage'] =  app()->request()->params('homepage','');    //是否加载分类
        //不是首页的  排序   （homepage 是 -1或 空的排序）
        if(!$params['homepage'] || $params['homepage']==-1){
            $params['pcOrder']   = 1;
        }else{
        //是首页的   排序  （homepage  = 1或2）
            $params['pcHomePage'] = 1;
        }
        // 获取首页分类信息  （加载头条分类信息）
        $newsCategory = [];

        //只展示 分类为展示的条目
        $params['is_show_item'] = 1;

        //1是首页第一次加载分类   -1是 艺术头条第一次加载分类使用  （其它）
        if(($params['homepage']&&$params['homepage']==1)||($params['homepage']&&$params['homepage']==-1) ){
            $newsCategory = new \Model\News\NewsCategory();
            $params['parentId'] = 0;
            $params['isShow']   = 1;
            $newsCategory  = $newsCategory->lists($params, 1, 20);
            if($params['homepage']!=1){
                array_unshift($newsCategory[0],['nc_id'=>'','nc_name'=>'全部']);
            }
            //获取头条 一次分类
            $params['n_type']  = isset($newsCategory[0][0]['nc_id'])&&!empty($newsCategory[0][0]['nc_id'])?$newsCategory[0][0]['nc_id']:'';
        }

        $newsLib = new \Lib\News\News();
        $data = $newsLib->getList($params);
        foreach ($data['list'] as &$val) {
            //文章标题
            $val['n_title'] = htmlspecialchars_decode($val['n_title']);
            $time = strtotime($val['n_update_date']);
            $val['displayTime'] = date_format_to_display($time);
            //文章主图
            $val['img'] = $newsLib->newsImg($val['n_id'], 6);
            $val['n_update_date'] = date('Y-m-d', strtotime($val['n_update_date']));
            //发布来源
            $val['n_anthor'] = $val['n_anthor'] ? $val['n_anthor'] : $val['n_from'];
            // 阅读量等于 真实阅读量 + 默认初始阅读量
            $val['n_click_rate'] = $val['n_click_rate'] +  $val['n_default_click_rate'];
            //获取分类名称
            $val['n_type_name'] = app('mysqlbxd_app')->fetchColumn("select nc_name from news_category where  nc_id={$val['n_type']}");
        }
        $data['category_list'] = $newsCategory;
        $this->responseJSON($data);
    }


    /**
     * 艺术家数据
     * 注释：
     *  1：图片  作品数  真实姓名     作家简介    头像
     * @param   // $homePage=0
     */
    public function artist() {
        $params['goodsNum'] = 1;
        $params['lastUploadTime'] = 1;
        $params['likeNum'] = 1;
        $params['type'] = 1;
        $params['page'] = app()->request()->params('page',1);
        $params['pageSize'] = app()->request()->params('pageSize',10) ;
        $params['u_type'] = 1;
        //热门头条
        $params['artistPopular'] = app()->request()->params('artistPopular','') ;
        $result = api_request($params, 'user/query/recommend');
        $artist_list = [];
        if ($result && $result['count'] > 0) {
            $artist_list = $result['list'];
            $user_list = api_request(['uids' => implode(',', array_column($artist_list, 'u_id')), 'needExtend' => 1], 'user/get');
            foreach ($artist_list as $key => $val) {
                $artist_list[$key]['u_avatar'] = $user_list[$val['u_id']]['u_avatar'];
                $realname = $user_list[$val['u_id']]['u_realname'];
                $artist_list[$key]['u_realname'] = empty($realname) ? $user_list[$val['u_id']]['u_nickname'] : $realname;
                $artist_list[$key]['goodsNum'] = $user_list[$val['u_id']]['user_extend']['ue_goodsNum'];
                $artist_list[$key]['desc']      =  (new Setting())->settingGetValue($val['u_id'],'introduction');
            }
        }
        $this->responseJSON($artist_list);
    }

    /**
     * 首页   掌玩商城
     * 注释：
     *  1：取数规则：默认选中【全部】tab，优先显示用户4个浏览记录，按照浏览时间倒序，
     * 不足，用优选商品按照现有优选顺序补充到4个。其他tab按照倒序取平台最新发布的商品，样式和【全部】tab保持一致
     * 2：展示内容：商品缩略图、商品标题（超过UI设计稿特定字数长度自动截断）、作者名称、规格信息、商品价格
     *
     */
    public function  mall(){
        // status=3  出售中   isHaveStock=>1  是否有库存     pageSize=>4条信息  g_categoryId=>categoryId  商品分类id
        $categoryId = app()->request()->params('categoryId','') ;   //如果是空   代表是全部  不是空  代表商品分类信息
        $homePage   =  app()->request()->params('homepage','') ;     //是否是首页加载信息
        $params['page'] = app()->request()->params('page',1);
        $params['pageSize'] = app()->request()->params('pageSize',4) ;
        if(!$categoryId){
            //点击全部  获取 优选商品
            $result = api_request(['status'=>3,'searchType'=>2,'isExchangeIntegral'=>0,'sType'=>1,'goodsOrderColumn'=>'yx-desc','page'=>$params['page'],'pageSize' =>$params['pageSize']], 'mall/item/query');
        }else{
            //点击其它获取 上架商品
            $result = api_request(['status' => 3, 'isHaveStock' => 1,'page'=>$params['page'],'pageSize' =>$params['pageSize'],'categoryId'=>$categoryId,'goodsOrderColumn'=>'sjtime-desc'], 'mall/item/query');
        }
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
        if($homePage==1){
            $goodsCategory = new GoodsCategory();
            $goods_lists['goods_category'] = $goodsCategory->getListByParentId(['parentIds' => 0]);
            array_unshift($goods_lists['goods_category'],['c_id'=>0,'c_name'=>'全部']);
        }
        $this->responseJSON($goods_lists);
        return $goods_list;
    }



    /**
     * 首页   展览展会
     * 取数规则：刚进入根据ip定位城市，选中当前城市，默认显示全部分类，按照场馆审核时间倒序每页加载6条数据，触底加载更多。
     * 显示内容：图片，名称，简介，详情按钮
     */
     public function exhibition(){
         // 进行中  和  即将开始的展会
         $params['effective']  =1 ;
         //获取所在省,市
         $city         = isset(get_ip_address_info_ali()['city'])?get_ip_address_info_ali()['city']:'';
         $city         = str_replace("市","",$city);
         $city         = str_replace('县','',$city);
      //----------------------获取所在城市进行中的展会 -----------------------
         $datalist2  = ['list'=>[]];
         //根据所在省 获取  所有市code
         $params['city'] = $city;
         //开始  或  即将开始的展会
         $params['effective'] = 1;
         //没有删除的展会
         $params['remove']    = 0;
         //获取8条 所在城市 的展会信息
         $newsLib = new \Lib\News\Exhibition();
         $datalist1 = $newsLib->getList($params,1,8);
      //-----------------------升序获取即将开始的几场展会
         if($datalist1['count']<8){
             $params['notEqualCity'] = $city;
             $params['city'] ='';
             $datalist2 = $newsLib->getList($params,1,8-$datalist1['count']);
         }
         $datalist3['list'] =  array_merge($datalist1['list'],$datalist2['list']);
         if(count($datalist3)>0){
             foreach($datalist3['list'] as $k=>$v){
                 $datalist3['list'][$k]['image'] = FileHelper::getFileUrl($v['image']);
                 //获取展会状态
                 if($v['start_time']<date('Y-m-d H:i:s')&&($v['end_time']>date('Y-m-d H:i:s'))){
                     $datalist3['list'][$k]['exhibition_status'] = '进行中';
                 }elseif($v['end_time']>date('Y-m-d H:i:s')){
                     $datalist3['list'][$k]['exhibition_status'] = '即将开始';
                 }else{
                     $datalist3['list'][$k]['exhibition_status'] = '已结束';
                 }
             }
         }
         $this->responseJSON($datalist3);
     }

    /**
     *文化场馆信息返回
     */
    public function venues(){
        //分页设置
        $params['page'] = app()->request()->params('page',1);
        $params['pageSize'] = app()->request()->params('pageSize',4) ;
        $params['homepage'] = app()->request()->params('homepage','') ;
        $params['city'] = app()->request()->params('city','') ;
        //按照分类搜索
        $params['catergory_name'] = app()->request()->params('category_name','') ;
        $params['catergory_name'] = $params['catergory_name']=='全部'?"":$params['catergory_name'];
        //没有请求城市   自动定位城市
        $city = "";
        if(!$params['city']){
            $city               = get_ip_address_info_ali('')['city'];;
        }
        $city                   = str_replace("市","",$city);
        $city                   = str_replace("县","",$city);
        //1：文玩场馆列表项
        if($params['homepage']==1){
            //城市定位
            $params['city']? $params['address'] = $params['city']:$params['address'] = $city;
            //pcListOrder  排序
            $params['pcListOrder']   = 1;
        }elseif($params['homepage']==2){
            //2：右侧热门场馆列表
            $params['pcListOrder']   = 2;
        }
        //删除场馆不显示
        $params['remove'] = 0;
        //3  首页列表
        $newsLib = new \Model\News\Venue();
        list($getList['list'],$getList['count']) = $newsLib->getList($params,$params['page'],$params['pageSize']);
        //返回所属城市
        $getList['address'] = ['city'=>$city];
        //修正图片信息
        $getList['list'] = array_map(function($v){
            $v['image'] = FileHelper::getFileUrl($v['image']);
            return $v;
        },$getList['list']);
        $this->responseJSON($getList);
    }
}