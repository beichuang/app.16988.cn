<?php

/**
 * 更新多规格商品集商品列表显示状态
 */
namespace Cli\Worker;

class GoodsListShowStatus
{
    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }

    public function run()
    {
        while (true) {
            //获取多规格商品集中价格最低的商品
            $data = $this->fetchData();
            if ($data) {
                $showGoodsIds = array_column($data, 'g_id');
                $this->updateListShowStatus($showGoodsIds);
            }

            exitTask('03:00:00', '03:02:00');
            sleep(3600);
        }
    }

    private function fetchData()
    {
        $sql = "SELECT t1.* FROM goods t1 INNER JOIN(
SELECT `gc_id`,min(g_price) AS min_price FROM goods WHERE `gc_id` !=0 AND g_status=3 AND g_stock>0 GROUP BY `gc_id`
) t2 ON t1.`gc_id`=t2.`gc_id` AND t1.`g_price`=t2.min_price
WHERE t1.`gc_id` !=0 AND g_status=3 AND g_stock>0 GROUP BY t1.`gc_id`;";
        $data = $this->db->select($sql);
        return $data;
    }

    private function updateListShowStatus($showGoodsIds)
    {
        if ($showGoodsIds) {
            $updateShowSql = 'UPDATE `goods` SET `g_list_show_status`=1 WHERE FIND_IN_SET(g_id,:g_id) AND `g_list_show_status`=0;';
            $updateNotShowSql = 'UPDATE `goods` SET `g_list_show_status`=0 WHERE `gc_id` !=0 AND NOT FIND_IN_SET(g_id,:g_id) AND `g_list_show_status`=1;';

            $this->db->query($updateShowSql, [':g_id' => implode(',', $showGoodsIds)]);
            $this->db->query($updateNotShowSql, [':g_id' => implode(',', $showGoodsIds)]);
        }
    }
}
