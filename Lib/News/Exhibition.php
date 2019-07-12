<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/25
 * Time: 10:28
 */

namespace Lib\News;


class Exhibition
{
    /**
     * 展览展会列表
     */
    public function getList($condition,$page=1,$pageSize=10)
    {
        $params = [];
        //标题
        if (isset($condition['title']) && $condition['title']!='')
        {
            $params['title'] = $condition['title'];
        }
        //主办方
        if (isset($condition['sponsor']) && $condition['sponsor']!='')
        {
            $params['sponsor'] = $condition['sponsor'];
        }
        //城市code
        if (isset($condition['city']) && $condition['city']!='')
        {
            $params['city'] = $condition['city'];
        }

        //不是已知城市
        if(isset($condition['notEqualCity'])&& $condition['notEqualCity']!=''){
            $params['notEqualCity'] = $condition['notEqualCity'];
        }

        //展会开始时间
        if (isset($condition['start_time']) &&!empty($condition['start_time']))
        {
            $params['start_time'] = $condition['start_time'];
        }

        // 展会结束时间
        if (isset($condition['end_time']) && !empty($condition['end_time']))
        {
            $params['end_time'] = $condition['end_time'];
        }

        //是否是有效的展会
        if(isset($condition['effective'])&& !empty($condition['effective'])){
            $params['effective'] = $condition['effective'];
        }

        //删除数据的过滤
        if(isset($condition['remove'])){
            $params['remove'] = $condition['remove'];
        }

        //默认是审核通过的展会
        $params['apply']       =   1;
        $newsLib = new \Model\News\Exhibition();
        list($getList['list'],$getList['count']) = $newsLib->getList($params,$page,$pageSize);

        return $getList;
    }

}