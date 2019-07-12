<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/25
 * Time: 17:12
 */

namespace Model\News;
use Lib\Base\BaseModel;

class   Venue   extends   BaseModel
{
    protected $table = 'venue';
    protected $id = 'venue_id';

    public function  getList($condition, $page=1, $pageSize=10){
        $where = [];
        $s = 'select venue_id,title,content,image,province,city,`position`,concat(province,city,`position`) as address,source_from from  venue where 1=1 ';
        $sc = 'select count(*) as count from venue where 1=1';
        $sql = '';

        //标题
         if(isset($condition['title'])&&!empty($condition['title'])){
             $sql .= " and title like concat('%',:title,'%') ";
             $where[':title'] = $condition['title'];
         }

        //地址
        if(isset($condition['address'])&&!empty($condition['address'])&&$condition['address']!="全部"){
//            $sql .= " and address like concat('%',:address,'%') ";
//            $where[':address'] = $condition['address'];
            //按照城市搜索
            $sql .= " and city=:city ";
            $where[':city'] = $condition['address'];
        }

        //场馆分类
        if(isset($condition['catergory_name'])&&!empty($condition['catergory_name'])){
            $sql .= " and catergory_name =:catergory_name ";
            $where[':catergory_name']    = $condition['catergory_name'];
        }
        $sql .= " and status=1 ";
        //被删除的场馆不显示
        if(isset($condition['remove'])&&in_array($condition['remove'],[0,1])){
            $sql .= " and  remove=:remove ";
            $where[':remove'] = $condition['remove'];
        }
        // 热门场馆列表            1:先按照点击量  2:按照发布时间
        if(isset($condition['pcListOrder'])&&$condition['pcListOrder']==2){
            $order  = ' order by  browse_times desc,publish_time desc,group_sort desc,sort desc';
        }elseif(isset($condition['pcListOrder'])&&$condition['pcListOrder']==1){
          // 热门场馆列表 :   按照发布时间降序排序
            $order  = ' order by  publish_time desc,group_sort desc,sort desc';
        }else{
            //  按照指定排序显示
            $order  = ' order by group_sort desc,sort desc ';
        }
        $s .= $sql . $order;
        $sc .= $sql;
        $count = app('mysqlbxd_app')->fetchColumn($sc, $where);
        $list = app('mysqlbxd_app')->selectPage($s,$page,$pageSize, $where);
        return [
            $list,$count
        ];
    }
    /**
     * 文玩场馆详情
     * @param $venueId     //场馆id
     */
     public static  function  venueDetail($venueId=''){
         $s    = 'select venue_id,title,content,image,province,city,`position`,concat(province,city,`position`) as address,browse_times,publish_time,catergory_name,source_from from  venue where venue_id=:venue_id';
         $data = app('mysqlbxd_app')->fetch($s,["venue_id"=>$venueId]);
         return $data;
     }






}