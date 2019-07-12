<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/10
 * Time: 17:59
 */

namespace Controller\Office;
use framework\Helper\File;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Model\Common\searchWord;
use Model\User\Artist;
use Lib\Common\Region;

class Web  extends BaseController
{
    /**
     * 友情链接接口
     */
    public function link(){
        $sql  = 'select  link_id,link_code,link_name,link from  pc_link  where remove=0 ORDER BY  create_time DESC  ';
        $link = app('mysqlbxd_app')->select($sql);
        $this->responseJSON(['link'=>$link]);
    }


    //头部搜索
    public function  headSearch(){
          $page     = app()->request()->params('page', 1);
          $pageSize = app()->request()->params('pageSize', 5);
          $search   = app()->request()->params('search', "");
          $search   = trim($search);
          $type     = app()->request()->params('type',"");
          $last_page  = app()->request()->params('last_page',0);
          $diff_num   = app()->request()->params('diff_num',0);
          if(!trim($search)){
              throw new \Exception\ParamsInvalidException("搜索项目不能为空！");
          }
          searchWord::keywordsCollect($search);
          switch ($type){
              // 艺术头条
              case  1 :
                  $data[1]       = $this->hotNews($search,$page,$pageSize);
              break;
              // 艺术人物
              case 2 :
                  $data[2]       = $this->artistList($search,$page,$pageSize,$last_page,$diff_num);
              break;
              // 商品搜索
              case 3:
                  $data[3]       = $this->query($search,$page,$pageSize);
              break;
              //  展览展会
              case 4:
                  $data[4]       = $this->exhibitionList($search,$page,$pageSize);
              break;
              //  文化场馆
              case 5:
                  $data[5]       = $this->venues($search,$page,$pageSize);
              break;
              //  艺术圈子
              case 6:
                  $data[6]       = $this->article($search,$page,$pageSize);
              break;
              // 全部搜索
              case 0:
                  //艺术头条
                  $data[1] = $this->hotNews($search,$page,$pageSize);
                  //艺术人物
                  $data[2] = $this->artistList($search,$page,$pageSize,$last_page,$diff_num);
                  //商品搜索
                  $data[3] = $this->query($search,$page,$pageSize);
                  //展览展会
                  $data[4] =  $this->exhibitionList($search,$page,$pageSize);
                  //文化场馆
                  $data[5] =  $this->venues($search,$page,$pageSize);
                  //艺术圈子
                  $data[6] =  $this->article($search,$page,$pageSize);
              break;
              default:
                  throw new \Exception\ParamsInvalidException("搜索项不合法！");
              break;
          }
         $this->responseJSON($data);
    }

    /**
     * 艺术圈子
     * @param $search       //搜索项目
     * @param $page         //当前页
     * @param $pageSize     //页码大小
     * @return mixed
     */
    private function  article($search,$page,$pageSize){
         $data  =  ArticleCircle::circle_list_search($search,$page,$pageSize,'search');
         $data['list'] = $data['circle_list'];
         unset($data['circle_list']);
         return $data;
    }

    /**
     * @param $search            //搜索项
     * @param $page              //第几页
     * @param $pageSize          //页码大小
     * @return mixed
     */
    private function  hotNews($search,$page,$pageSize){
        $params['page']      = $page;
        $params['pageSize']  = $pageSize;
        $params["n_title"]   = $search;
        $params['pcOrder']   = 1;
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
        }
        return $data;
    }


    /**
     * 搜索项目
     * @param $search      //搜说项目
     * @param $page
     * @param $pagesize
     * @param $last_page
     * @param $diff_num
     * @return array
     */
    private function artistList($search,$page,$pagesize,$last_page,$diff_num){

        //获取艺术人物信息
        $condition             = ["u_realname"=>$search];
        $artists               = Artist::getList($page,$pagesize,"",$last_page,$diff_num,"",$condition);
        //判断是否是最后一页
        foreach ($artists['list'] as $k=>&$v){
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
        return  $artists;
}


//------------------------商品搜索----------------------------------
    /**
     * @param string $search
     * @param string $page
     * @param string $pageSize
     */
    private function query($search='',$page='',$pageSize='')
    {
        //参数传递
        $params['keyword']  =  trim($search);
        $params['tags']     =  1;
        $params['page']     =  $page;
        $params['pageSize'] = $pageSize;
        $resMall = $this->getLists($params);
        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($resMall['list'], 'g_salesId', array(
                'u_realname' => 'u_realname',
                'u_nickname' => 'u_nickname',
                'u_avatar' => 'u_avatar',
            )
        );
        $goodsBoxModel = new \Model\Mall\GoodsBox();
        foreach ($resMall['list'] as &$value) {
            if ($value['g_goodsBox'] == -1) {
                $value['goodsBoxName'] = '默认作品集';
            } else {
                $boxInfo = $goodsBoxModel->oneById($value['g_goodsBox']);
                $value['goodsBoxName'] = isset($boxInfo['gb_name']) ? $boxInfo['gb_name'] : '';
            }

            if($this->clientType==self::CLIENT_TYPE_ANDROID && $value['isSecKill']){
                $tmpActivityPrice=$value['g_activityPrice'];
                $value['g_activityPrice']=$value['g_price'];
                $value['g_price']=$tmpActivityPrice;
            }
            //返回商品省份
            $value['g_provinceName'] = empty($value['g_provinceCode']) ? '' : Region::getRegionNameByCode($value['g_provinceCode']);
            //格式化点赞量
            $value['likeCount'] = $this->formatLikeCount($value['likeCount']);
        }
        return  $resMall;
    }
    /**
     * 获取商品列表
     * @param  $params
     */
    public function getLists($params)
    {
        $params['status'] = app()->request()->params('status', 3);
        $params['isHaveStock'] = app()->request()->params('isHaveStock', 1);
        if (isset($params['recentDay'])) {
            $params['onShowDate'] = date('Y-m-d H:i:s', time() - $params['recentDay'] * 86400);
        }
        $goodsLib =  new \Lib\Mall\Goods();
        return $goodsLib->itemQuery($params);
    }
    /**
     * 格式化点赞量
     * @param int $likeCount 点赞量
     * @return float|int|string
     */
    private function formatLikeCount($likeCount)
    {
        if (empty($likeCount) || $likeCount < 0) {
            $result = 0;
        } elseif ($likeCount < 100) {
            $result = $likeCount;
        } elseif ($likeCount < 1000) {
            $result = $likeCount / 1000;
            $result = round($result, 1) . 'k';
        } else {
            $result = $likeCount / 10000;
            $result = round($result, 1) . 'w';
        }

        return $result;
    }
//------------------------end--------------------------------------------------------------------

    /**
     * 展览展会列表
     * @param $title
     * @param $page
     * @param $pagesize
     * @return array
     */
    private function  exhibitionList($title,$page,$pagesize){
        $where              = [];
        $where['title']    = $title;
        //审核已经通过
        $where['apply']    =1;
        $exhibition         = new \Model\News\Exhibition();
        $exhibitionList     = $exhibition->getList($where,$page,$pagesize);
        //oss图片处理
        if(isset($exhibitionList[0])&&$exhibitionList[0]){
            $exhibitionList[0]  = array_map(function($v1){
                $image = json_decode($v1['image'],true);
                isset($image['gi_img'])?$image['gi_img'] = FileHelper::getFileUrl($image['gi_img']):null;
                $v1['image']  = $image ;
                return $v1;
            },$exhibitionList[0]);
        }
        $data['list']   = isset($exhibitionList[0])?$exhibitionList[0]:[];
        $data['count']  = isset($exhibitionList[1])?$exhibitionList[1]:0;
        return $data;
    }


    /**
     * @param $title
     * @param $page
     * @param $pageSize
     * @return mixed
     */
    private function venues($title,$page,$pageSize){
        $params['page']             =  $page;
        $params['pageSize']         =  $pageSize ;
        $params['title']             = $title ;
        $params['pcListOrder']       = 1;
        //获取场馆列表数据
        $newsLib = new \Model\News\Venue();
        list($getList['list'],$getList['count']) = $newsLib->getList($params,$params['page'],$params['pageSize']);
        //修正图片信息
        $getList['list']      = array_map(function($v){
            $v['image']       = json_decode($v['image'],true);
            if($v['image']&&is_array($v['image'])){
                $v['image']['gi_img'] = $v['image']['gi_img']?FileHelper::getFileUrl($v['image']['gi_img']):'';
            }
            return $v;
        },$getList['list']);
        return  $getList;
    }

//--------------------------其它辅助接口----------------

 






}