<?php
/**
 * 新闻
 * @author Administrator
 *
 */
namespace Model\News;

use Lib\Base\BaseModel;

class News extends BaseModel
{

    protected $table = 'news';

    protected $id = 'n_id';

    /**
     * 新闻列表
     *
     * @throws ModelException
     */
    public function getList($condition, $page, $pageSize)
    {
        $where = [];
        $s = 'select n_id,news.nc_id,n_title,n_type,n_subtitle,n_from,n_is_index,n_anthor,n_click_rate,n_default_click_rate,n_update_date,n_picurl,nc_is_show from news  LEFT JOIN  news_category  ON  n_type=news_category.nc_id  where 1=1 and n_status =0 ';
        $sc = 'select count(n_id) as count from news LEFT JOIN  news_category  ON  n_type=news_category.nc_id where 1=1 and n_status =0 ';
        $sql = '';
        if (isset($condition['id'])) {
            $sql .= ' and n_id=:id ';
            $where[':id'] = $condition['id'];
        }
        if (isset($condition['n_status'])) {
            $sql .= ' and n_status=:n_status ';
            $where[':n_status'] = $condition['n_status'];
        }
      
        if (isset($condition['nc_id'])) {
           
            $sql .= ' and nc_id = :nc_id';
            $where[':nc_id'] = $condition['nc_id'];
        }
        
        if (isset($condition['is_index'])) {
            $sql .= ' and n_is_index=:is_index ';
            $where[':is_index'] = $condition['is_index'];
        }
        
        if (isset($condition['n_from'])) {
            $sql .= ' and n_from=:n_from ';
            $where[':n_from'] = $condition['n_from'];
        }

        if (isset($condition['n_type'])) {
            $sql .= ' and n_type=:n_type ';
            $where[':n_type'] = $condition['n_type'];
        }

        if ( isset($condition['n_title']) ) {
            $sql .= " and n_title like concat('%',:n_title,'%') ";
            $where[':n_title'] = $condition['n_title'];
        }

        if (isset($condition['n_form'])) {
            $sql .= ' and n_form=:n_form ';
            $where[':n_form'] = $condition['n_form'];
        }

        if(isset($condition['is_show_item'])&&$condition['is_show_item']){
            $sql .= ' and news_category.nc_is_show=1  ';
        }




        $order = '';
        if (isset($condition['recommend']) && !empty($condition['recommend'])) {
            $order .= ' , n_is_index desc, n_isIndexSort desc,n_update_date desc   ';
        }
        if (isset($condition['click_rate'])) {
            if ($condition['click_rate'] == 1) {
                $order .= ' , n_click_rate desc ';
            }
            if ($condition['click_rate'] == 2) {
                $order .= ' , n_click_rate asc ';
            }
        }

        if (isset($condition['dianzan'])) {
            if ($condition['dianzan'] == 1) {
                $order .= ' , n_dianzan desc ';
            }
            if ($condition['dianzan'] == 2) {
                $order .= ' , n_dianzan asc ';
            }
        }

        if (isset($condition['addTime'])) {
            if ($condition['addTime'] == 1) {
                $order .= ' , n_update_date desc ';
            }
            if ($condition['addTime'] == 2) {
                $order .= ' , n_update_date asc ';
            }
        }

        //pc首页排序   功能
        if(isset($condition['pcHomePage'])&&$condition['pcHomePage']){
            $order .= ' , n_isIndexSort desc,n_update_date desc   ';
        }




        if ($order) {
            $order = ' order by '.substr(ltrim($order), 1);
        }elseif (isset($condition['pcOrder'])&&$condition['pcOrder']){    //pc官网排序
            $order = ' order by n_update_date desc,n_status asc';
        } else {
            $order = ' order by n_create_date desc ';
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
     * pcPublish 发布
     */
    public function  publishList($condition, $page, $pageSize){
        $where = [];
        $s = 'select n_id,n_title,n_subtitle,n_anthor,n_status,n_create_date,nc_name as n_type  from news n  LEFT JOIN  news_category  nc on  n.n_type=nc.nc_id    where 1=1  ';
        $sc = 'select count(n_id) as count from news where 1=1 ';
        $sql = '';
        //针对pc官网的查询
        if(!isset($condition['requestType'])){
            $s  = $s. '   and n_status  = 0 ';
            $sc = $sc.'   and n_status  = 0 ';
        }

        if ($condition['pcOrder']){    //pc官网排序
            $order = ' order by n_update_date desc,n_status asc';
        } else {
            $order = ' order by n_create_date desc ';
        }

        if (isset($condition['publisher'])) {
            $sql .= ' and publisher=:publisher ';
            $where[':publisher'] = $condition['publisher'];
        }

        $s .= $sql . $order;
        $sc .= $sql;

        //按照发布人
        $count = app('mysqlbxd_app')->fetchColumn($sc, $where);
        $list = app('mysqlbxd_app')->selectPage($s,$page,$pageSize, $where);
        return [
            $list,$count
        ];
    }

    
    public function getOneLine($condition) {
        $where = $data = array();
        foreach ($condition as $key => $value) {
            $where[] = " $key = :$key ";
            $data[$key] = $value;
        }
        $where = implode(' and ', $where);
        return $this->one($where, $data);
    }
    
    /**
     * 获取资讯图片
     */
    public function getImg($nid,$limit)
    {
        if (!$nid) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $sql = "select * from news_image where n_id=:n_id";
        return $this->mysql->selectPage($sql,1,$limit, array(
            'n_id' => $nid
        ));
        
    }

    /**
     * @param string $newsId
     * @param  $newsId     //头条id
     * @throws \Exception\ParamsInvalidException
     */
    public static function newsDetail($newsId=''){
        $where = [];
        if(!$newsId){
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $s  = 'select n_id,nc_id,n_title,n_type,n_subtitle,n_from,n_is_index,n_anthor,n_click_rate,n_default_click_rate,n_update_date,n_picurl,n_content from news where 1=1 and n_status =0 and n_id =:n_id';
        $newsId?$where[':n_id']=$newsId:null;
        $list = app('mysqlbxd_app')->fetch($s,$where);
        return $list;
    }






    
}
