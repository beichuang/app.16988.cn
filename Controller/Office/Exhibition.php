<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/27
 * Time: 19:53
 */
namespace Controller\Office;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
class Exhibition  extends BaseController
{
     /**
      * 1:展会展览列表 （展览展会模块）
      */
     public function  exhibitionList(){
          $where             = [];
          //展会开始日期结束日期
          $exhibitionStart= app()->request()->params('start','');
          $exhibitionEnd  = app()->request()->params('end','');
          $title          = app()->request()->params('title','');
          $page           = app()->request()->params('page',1);
          $pagesize       = app()->request()->params('pagesize',12);
          if(trim($title)){
               $where['title'] = $title;
          }
          if(($exhibitionStart&&!$exhibitionEnd)||(!$exhibitionStart&&$exhibitionEnd)){
               throw new \Exception\ParamsInvalidException("请选择开始时间和结束时间！");
          }
          //----获取所需要的请求城市
          $city              = app()->request()->params('city','');
          if(!$city){
               $city         = get_ip_address_info_ali('')['city'];
          }
          $belongCity        = str_replace('市','',$city);
          $belongCity        = str_replace('县','',$belongCity);
          if($belongCity){
               $where['city'] = $belongCity;
          }
          // 展会日期
          if($exhibitionStart&&$exhibitionEnd){
               $where['date']  = ['start_time'=>$exhibitionStart,'end_time'=>$exhibitionEnd];
          }
          //展会状态    1:进行中  2:未开始 3:已结束
          $exhibitionState  = app()->request()->params('state','');
          if($exhibitionState){
               $where['state'] = $exhibitionState;
          }
          //审核已经通过
          $where['apply']  =1;
          //没有被删除
          $where['remove'] = 0;
          //根据省市区 列表帅选
          $exhibition        = new \Model\News\Exhibition();
          $exhibitionList    = $exhibition->getList($where,$page,$pagesize);
          //oss图片处理
          if(isset($exhibitionList[0])&&$exhibitionList[0]){
               $exhibitionList[0]  = array_map(function($v1){

                    //获取展会状态
                    if($v1['start_time']<date('Y-m-d H:i:s')&&($v1['end_time']>date('Y-m-d H:i:s'))){
                         $v1['exhibition_status'] = '进行中';
                    }elseif($v1['end_time']>date('Y-m-d H:i:s')){
                         $v1['exhibition_status'] = '即将开始';
                    }else{
                         $v1['exhibition_status'] = '已结束';
                    }
                    //获取分类名称
                    $v1['image']  = FileHelper::getFileUrl($v1['image']) ;
                    return $v1;
               },$exhibitionList[0]);
          }
          $this->responseJSON(['list'=>isset($exhibitionList[0])?$exhibitionList[0]:[],'count'=>isset($exhibitionList[1])?$exhibitionList[1]:0,'address'=>['city'=>$belongCity]]);
     }

     /**
      * 1:展会展览列表 （展览展会模块）
      */
     public function  hotExhibition(){
          //展会开始日期结束日期
          $page           = app()->request()->params('page',1);
          $pagesize       = app()->request()->params('pagesize',6);
          $order          =  app()->request()->params('order','hotExhibition');
          //展览展会排序
          $where['order']  = $order;
          //审核已经通过
          $where['apply']  =1;
          //没有被删除
          $where['remove'] = 0;
          //根据省市区 列表帅选
          $exhibition        = new \Model\News\Exhibition();
          $exhibitionList    = $exhibition->getList($where,$page,$pagesize);
          //oss图片处理
          if(isset($exhibitionList[0])&&$exhibitionList[0]){
               $exhibitionList[0]  = array_map(function($v1){

                    //获取展会状态
                    if($v1['start_time']<date('Y-m-d H:i:s')&&($v1['end_time']>date('Y-m-d H:i:s'))){
                         $v1['exhibition_status'] = '进行中';
                    }elseif($v1['end_time']>date('Y-m-d H:i:s')){
                         $v1['exhibition_status'] = '即将开始';
                    }else{
                         $v1['exhibition_status'] = '已结束';
                    }
                    //获取分类名称
                    $v1['image']  = FileHelper::getFileUrl($v1['image']) ;
                    return $v1;
               },$exhibitionList[0]);
          }
          $this->responseJSON(['list'=>isset($exhibitionList[0])?$exhibitionList[0]:[],'count'=>isset($exhibitionList[1])?$exhibitionList[1]:0,'address'=>['city'=>$belongCity]]);
     }














     /**
      *我发布的展览信息   （个人中心）
      */
     public function  publishedExhibition(){
          $page     = app()->request()->params('page', 1);
          $pagesize = app()->request()->params('pageSize', 12);
          //根据省市区 列表帅选
          $where['order']   = 'myPublish';
          $where['uid']     = $this->uid;
          $where['remove']  = 0;
          $exhibition        = new \Model\News\Exhibition();
          $exhibitionList    = $exhibition->getList($where,$page,$pagesize);
          if(isset($exhibitionList[0])&&$exhibitionList[0]){
               $exhibitionList[0]  = array_map(function($v1){
                    //图片处理
                    $v1['image'] = FileHelper::getFileUrl($v1['image']);
                    return $v1;
               },$exhibitionList[0]);
          }
          $this->responseJSON($exhibitionList);
     }

     /**
      *发布展览展会   (个人中心模块)
      */
     public function  publishExhibition(){
          $params = app()->request()->params();
          //判断是否是修改操作
          if(isset($params['e_id'])&&$params['e_id']){
               $sql   = "select  apply from exhibition where e_id={$params['e_id']} and apply!=1";
               $apply = app('mysqlbxd_app')->fetchColumn($sql);
               if(is_null($apply)){
                    throw new \Exception\ParamsInvalidException("需要修改的展会不存在!");
               }
          }
          //参数验证
          if($params){
               //展会标题
               if(!isset($params['title'])|| !$params['title']){
                    throw new \Exception\ParamsInvalidException("标题不能为空！");
               }
               //展会 开始时间  结束时间
               if((!isset($params['start_time'])|| !$params['start_time'])||(!isset($params['start_time'])|| !$params['start_time'])){
                    throw new \Exception\ParamsInvalidException("展会时间有误！");
               }
               //展会 地址有误         详细地址：position不做限定
               if(!(isset($params['province'])&&$params['province']&&isset($params['city'])&&$params['city'])){
                    throw new \Exception\ParamsInvalidException("展会地址有误！");
               }
               //主办方
               if(!isset($params['sponsor'])|| !$params['sponsor']){
                    throw new \Exception\ParamsInvalidException("展会主办方有误！");
               }
               //主图
               if(!isset($params['image'])|| !$params['image']){
                    throw new \Exception\ParamsInvalidException("展会主图不能为空！");
               }
               //内容
               if(!isset($params['content'])|| !$params['content']){
                    throw new \Exception\ParamsInvalidException("展会内容不能为空！");
               }
               $params['content'] = $this->saveThirdNewsImages2Oss($params['content']);
          }else{
               throw new \Exception\ParamsInvalidException("参数有误！");
          }
          $params['uid']    = $this->uid;
          //发布展会   让展会处于待审核状态
          $params['apply']  = 2;
          //修改展会信息
          if($params['e_id']){
                $update_exhibition = app('mysqlbxd_app')->update("exhibition",$params,['e_id'=>$params['e_id']]);
          }else{
               $exhibition        = new \Model\News\Exhibition();
               $exhibition->publishExhibition($params);
          }
          $this->responseJSON('操作成功');
     }


     /**
      * 发布内容修正
      * @param $newsHtml
      * @return mixed
      */
     private function saveThirdNewsImages2Oss($newsHtml)
     {
          return (new \Framework\Model\NewsContent())->saveThirdNewsImages2Oss($newsHtml);
     }


}