<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/25
 * Time: 10:31
 */

namespace Model\News;
use Lib\Base\BaseModel;

class Exhibition extends BaseModel
{
    protected $table = 'exhibition';
    protected $id = 'e_id';
    /**
     * 展讯列表
     *
     * @throws ModelException
     */
    public function getList($condition, $page, $pageSize)
    {
        $where = [];
        $s = 'select * from  exhibition where 1=1 ';
        $sc = 'select count(e_id) as count from exhibition where 1=1';
        $sql = '';
        //标题
        if (isset($condition['title'])) {
            $sql .= ' AND title LIKE CONCAT("%", :title , "%")';
            $where[':title'] = $condition['title'];
        }
        //主办方
        if (isset($condition['sponsor'])) {
            $sql .= ' and sponsor=:sponsor ';
            $where[':sponsor'] = $condition['sponsor'];
        }
        //城市code 等于
        if (isset($condition['city'])) {
            $sql .= ' AND city LIKE CONCAT("%", :city , "%")';
            $where[':city'] = $condition['city'];
        }

        //城市code不等于
        if(isset($condition['notEqualCity'])){
            $sql .= ' AND city NOT LIKE CONCAT("%", :city , "%")';
            $where[':city'] = $condition['notEqualCity'];
        }
      
        //审核时否通过
        if(isset($condition['apply'])){
            $sql .= ' and apply=:apply  ';
            $where[':apply'] = $condition['apply'];
        }


        //处于进行中 即将开始的  展会
        if(isset($condition['effective'])){
            $date = date('Y-m-d H:i:s',time());
            $sql .= ' and end_time > :end_time';
            $where[':end_time'] = $date;
        }


        //展会日期范围
        if(isset($condition['date'])){
            $sql .= ' and   (start_time >=:start_time and end_time<=:end_time)';
            $where[':start_time'] = $condition['date']['start_time'];
            $where[':end_time'] = $condition['date']['end_time'];
        }

        //被删除的话  不显示
        if(isset($condition['remove'])){
            $sql .= '  and  remove=:remove  ';
            $where[':remove']  = $condition['remove'];
        }


        //展会状态  1:进行中  2:未开始 3:已结束
        if(isset($condition['state'])){
            $now_time = date('Y-m-d H:i');
            $where[':now_time'] = $now_time;
            switch($condition['state']){
                case 1:
                    $sql .= ' and   (start_time <:now_time and end_time>:now_time) ';
                break;
                case 2:
                    $sql .= ' and   (start_time>:now_time) ';
                break;
                case 3:
                    $sql .= ' and   (end_time<:now_time) ';
                break;
                default :
                     unset($where[':now_time']);
                break;
            }
        }

        //按照发布人排序
        if(isset($condition['uid'])&&$condition['uid']){
            $sql .= ' and uid=:uid ';
            $where[':uid'] = $condition['uid'];
        }

        //各种情况下   排序规则

        if(!isset($condition['order'])){
            $condition['order'] = 'default';
        }

        switch ($condition['order']){
            case 'myPublish':             //我发布的展会
                $order = ' order by update_time DESC,apply ASC';
            break;
            case "hotExhibition":       //热门展会
                $order = ' order by browse_times DESC,e_id DESC';
            break;
            default :
                $order = ' order by start_time asc ';
            break;
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
     * 获取指定条件的 展会信息
     * @param $condition
     * @return array
     */
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
     * 热门展讯信息获取
     * @param string $select
     * @return mixed
     */
    public static function  hotExhibition($select='image,title,publish_time'){
        $qsTime  = date('Y-m-d',strtotime('-6 month'));
        $s = "select {$select} from  exhibition where  apply = 1 AND start_time>{$qsTime} ORDER BY  browse_times DESC,publish_time ASC limit 0,6 ";
        $list = app('mysqlbxd_app')->select($s);
        return $list;
    }


    /**
     * 发布展览展会
     * @param $params
     */
    public function publishExhibition($params){
        $result =   $this->insert($params);
        return $result;
    }







}