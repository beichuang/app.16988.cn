<?php

/**
 * 刷新分类下在售作品数量
 */
namespace Cli\Worker;

class RefreshGoodsCategoryNum
{

    public function run()
    {
        $statData=app('mysqlbxd_mall_user')->select("select g_categoryId,is_own_shop,count(*) c from `goods` where g_type=1 and g_stock>0 and g_status=3 and g_categoryId!='' and g_categoryId!=0 group by g_categoryId,is_own_shop ");
        $data=[];
        foreach ($statData as $row){
            if(!isset($data[$row['g_categoryId']])){
                $data[$row['g_categoryId']]=[
                    'total'=>0,
                    'own_shop'=>0,
                ];
            }
            $data[$row['g_categoryId']]['total']+=$row['c'];
            if($row['is_own_shop']=='1'){
                $data[$row['g_categoryId']]['own_shop']+=$row['c'];
            }
        }
        app('mysqlbxd_mall_common')->query("update category set goods_onsale_count=0,goods_own_onsale_count=0");

        $this->updateCountData($data);
        var_dump($data);
        $pData=app('mysqlbxd_mall_common')->select("select c_parentId,sum(goods_onsale_count) total, sum(goods_own_onsale_count) own_shop  from category where c_isEnd=1 group by c_parentId");
        $pData=array_column($pData,null,'c_parentId');
        $this->updateCountData($pData);
        var_dump($pData);
    }
    private function updateCountData($data)
    {
        foreach ($data as $c_id=>$row){
            if($c_id){
                $sql="update category set goods_onsale_count={$row['total']},goods_own_onsale_count={$row['own_shop']} where c_id={$c_id}";
                app('mysqlbxd_mall_common')->query($sql);
            }
        }
//        app('mysqlbxd_mall_common')->query(substr($sql,1));
    }
}
