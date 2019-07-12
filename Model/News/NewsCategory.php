<?php
/**
 * 新闻
 * @author Administrator
 *
 */
namespace Model\News;

use Lib\Base\BaseModel;

class NewsCategory extends BaseModel
{

    protected $table = 'news_category';

    protected $id = 'nc_id';

    /**
     * 头条列表
     *
     * @throws ModelException
     */
    public function lists($condition, $page, $pageSize)
    {
        $where = [];
        $s = "select * from `{$this->table}` where 1=1 and nc_status =0 ";
        $sc = "select count(nc_id) as count from `{$this->table}` where 1=1 and nc_status =0 ";
        $sql = '';

        if (isset($condition['parentId'])) {
            $sql .= ' and nc_pid=:parentId ';
            $where[':parentId'] = $condition['parentId'];
        }
        if (isset($condition['isShow'])) {
            $sql .= ' and nc_is_show=:isShow ';
            $where[':isShow'] = $condition['isShow'];
        }

        $sql .= ' and nc_is_show=1';

        $order = ' order by nc_sort desc ';

        $s .= $sql . $order;
        $sc .= $sql;
          // 之前需要加入的推荐数据
//        $all['nc_id'] = 0;
//        $all['nc_name'] = "推荐";


        $count = app('mysqlbxd_app')->fetchColumn($sc, $where);
        $list = app('mysqlbxd_app')->selectPage($s,$page,$pageSize, $where);
     //   array_unshift($list, $all);
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
        return $this->one($where, $data);;
    }
    
    /**
     * 获取资讯图片
     */
    public function getImg($nid,$limit)
    {
        if (! $nid) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $sql = "select * from news_image where n_id=:n_id ";
        return $this->mysql->selectPage($sql,1,$limit, array(
            'n_id' => $nid
        ));
        
    }


    /**
     *获取相应字段信息
     * @param $field   //条件字段
     * @param $value   //值
     * @param $select  //需要查询获取的字段
     * @throws \Exception\ParamsInvalidException
     */
    public static function getOneColumn($select,$field='',$value=""){
        if (! $field) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $sql = "select {$select} from news_category where {$field} =:{$field} ";
        $where = [":{$field}"=>$value];
        $data = app('mysqlbxd_app')->fetch($sql, $where);
        return $data;
    }






    
}
