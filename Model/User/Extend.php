<?php
namespace Model\User;

use Lib\Base\BaseModel;

class Extend extends BaseModel
{
    protected $table = 'user_app_extend';
    protected $id = 'u_id';

    public function getGoodsNum($uid)
    {
        if ( !$uid ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        $userGoods = $this->oneById($uid);
        if ( !$userGoods ) {
            $retChange = $this->overallChange($uid);
            $userGoods = $this->oneById($uid);
        }

        return isset($userGoods['u_goodsNum']) ? $userGoods['u_goodsNum'] : 0;
    }


    /**
     * @param  $uid     用户id
     * @param  $overall 模式: 1搜索商品数量更新 0累加
     * @param  $step    步长,仅对累加模式生效
     */
	public function change($uid, $overall=1, $step=1)
	{
        if ( !$uid ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }

        $userGoods = $this->oneById($uid);

        if ( $overall || 
            !$userGoods || 
            (time()-strtotime($userGoods['u_updateDate'])>3*86400) 
            ) {
            return $this->overallChange($uid);
        }

        return $this->increase($uid);
    }

    private function overallChange($uid)
    {
        $currentTime = date('Y-m-d H:i:s');
        $currentNum = $this->goodsNum($uid);
        
        $sql = "replace into {$this->table}(u_id, u_goodsNum, u_updateDate) 
                    value('{$uid}', '{$currentNum}', '{$currentTime}')";
        $ret = $this->mysql->query($sql);

        return $ret;
    }

    private function increase($uid)
    {
        $currentTime = date('Y-m-d H:i:s');
        $sql = "update {$this->table} set 
                `u_goodsNum`=(u_goodsNum+'{$step}'),
                `u_updateDate`='{$currentTime}' 
                where u_id='{$uid}'";

        $ret = $this->mysql->query($sql);

        return $ret;
    }

    private function goodsNum($uid)
    {
        $params['salesId'] = $uid;
        $params['status'] = 3;
        $params['isHaveStock'] = 1;

        $goodsLib = new \Lib\Mall\Goods();
        $arr = $goodsLib->itemQuery($params);

        return isset($arr['count']) ? $arr['count'] : 0;
    }

    /** 修改用户的作品总量
     * @param $param
     * @return mixed
     */
    public function updateUserGoodsnum($param){

        //$currentTime = date('Y-m-d H:i:s');
        $sql = "update {$this->table} set `u_goodsNum`=".$param['count']." where u_id=".$param['g_salesId'];

        $ret = $this->mysql->query($sql);

        return $ret;
    }
}