<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/28
 * Time: 16:56
 */

namespace Model\Common;
use Lib\Base\BaseModel;
class searchWord extends BaseModel
{
     protected $table = 'hot_search_words';
     protected $id = 'id';

     /**
      * 关键词搜索 收录
      * @param string $keyword
      * @return bool
      */
     public static function keywordsCollect($keyword=''){
          $time = time();
          if(!trim($keyword)){
            return  false;
          }
          $sql   = "SELECT id FROM hot_search_words WHERE keywords=:keywords";
          $where = ['keywords'=>$keyword];
          $id = app('mysqlbxd_app')->fetchColumn($sql,$where);
          if($id){
               app('mysqlbxd_app')->query("update hot_search_words set num=num+1,update_time={$time} where id={$id}");
          }else{
               app('mysqlbxd_app')->query("INSERT INTO hot_search_words (keywords,create_time,update_time)  VALUES ('{$keyword}',{$time},{$time})");
          }
          return  true;
     }

     /**
      * 搜索热门城市   （文玩场馆）
      * @param $searchType   //搜索类型
      * @return bool
      */
     public static function venueHotMessage($searchType=2){
           $hotMessage = [];
           //热门分类
           if($searchType==1 || $searchType==3 ){
                $sql       = "select venue_category_name from venue_category_name where is_show = 1    ORDER BY sort DESC,create_time DESC";
                $categorys =  app('mysqlbxd_app')->select($sql);
                $categorys = array_column($categorys,'venue_category_name');
                $hotMessage['categorys'] = $categorys;
           }
           //热门城市
           if($searchType==2 || $searchType==3){
               $sql      = "select city from venue where  (status=1 and remove=0 and  `city` IS NOT NULL  and city!='' )  GROUP BY city  ORDER BY  count(venue_id) DESC  limit 0,10 ";
               $cityS    = app('mysqlbxd_app')->select($sql);
               $cityS    = array_column($cityS,'city');
               $hotMessage['citys'] = $cityS;
           }
         return  $hotMessage;
     }

    /**
     * 搜索场馆数量最多的热门城市
     * @return bool
     */
    public static function  venueHotCity(){


    }








}