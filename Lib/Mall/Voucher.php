<?php

namespace Lib\Mall;

class Voucher extends MallBase {

    /**
     * 代金券领取
     */
    public function receive($params) {
        return $this->passRequest2Mall($params, 'mall/voucher/receive');
    }

    /**
     * 代金券列表
     */
    public function lists($params) {
        return $this->passRequest2Mall($params, 'mall/voucher/query');
    }

    /**
     * 代金券模板列表
     */
    public function templateLists($params) {
        return $this->passRequest2Mall($params, 'mall/voucher/template/query');
    }

    /** 修改过期的代金券
     * @param $params
     * @return mixed
     */
    public function cliClose($params){
        return $this->cliPassRequest2Mall($params, 'mall/voucher/post');
    }

    public function getMinVoucherPrice($gid,$priceNow){
        $minPrice=$priceNow;
        if($priceNow>0){
            if($avalibleVtList=$this->getVoucherTemplatePricesByGid($gid,$priceNow,'regRegard')){
                $minPrice=$priceNow-$avalibleVtList[0]['v_t_price'];
                wlog([
                    '$gid'=>$gid,
                    '$priceNow'=>$priceNow,
                    '$avalibleVtList'=>$avalibleVtList,
                ],'getMinVoucherPrice');
            }
        }
        return $minPrice<0?0:$minPrice;
    }
    private function getVoucherTemplatePricesByGid($gid,$price,$prefix='')
    {
        $time=time();
        $sql="select v_t_id,v_t_price,v_t_limit,v_t_limit_ids from `voucher_template` 
                     where (`v_t_end_date`=0 or `v_t_end_date`>{$time}) 
                     and v_t_limit<={$price} 
                     and `v_t_state`=1 
                     and v_t_limit_ids is not null 
                     and v_t_prefix ='{$prefix}'
                     order by v_t_price desc ";
        $vtList=app('mysqlbxd_mall_user')->select($sql);
        $avalibleVtList=[];
        if($vtList){
            foreach ($vtList as $row){
                $gids=",{$row['v_t_limit_ids']},";
                if(strpos($gids,",{$gid},")!==false){
                    $avalibleVtList[]=[
                        'v_t_id'=>$row['v_t_id'],
                        'v_t_price'=>$row['v_t_price'],
                        'v_t_limit'=>$row['v_t_limit'],
                    ];
                }
            }
        }
        return $avalibleVtList;
    }
}
