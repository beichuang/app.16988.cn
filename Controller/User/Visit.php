<?php
/**
 * 粉丝来访
 * @author Administrator
 *
 */

namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Lib\Common\SessionKeys;
use Exception\ModelException;

class Visit extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->visitModel = new \Model\User\Visit();
    }

    /**
     * 粉丝来访问
     */
    public function come()
    {
        $target = app()->request()->params('target');
        if ($target == $this->uid) {
            $this->responseJSON(1);
        }
        if (!$target) {
            throw new ParamsInvalidException("访问对象必须");
        }
        $type = app()->request()->params('type', 1);
        if (1 == $type) {
            $userLib = new \Lib\User\User();
            $userInfos = $userLib->getUserInfo([$target]);
            if (!$userInfos) {
                throw new ParamsInvalidException("访问对象不存在");
            }
        }

        $retVisit = $this->visitModel->add($this->uid, $target, $type);
        if (!$retVisit) {
            throw new ParamsInvalidException("访问失败");
        }
        $this->responseJSON(1);
    }

    /**
     * 访问列表
     */
    public function showLists()
    {
        $user = new \Controller\User\Common();
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 20);
        $uid = app()->request()->params('uid');
        if (!$uid) {
            throw new ParamsInvalidException("用户uid必须");
        }

        $retList = $this->visitModel->lists($uid, $page, $pageSize);
        $data = [];
        foreach ($retList as $key => &$value) {
            if ($value['u_id'] !== $uid) {
                $userInfos = $user->fillDetail($value['u_id']);
                $value['userInfo'] = $userInfos;
                $data[] = $value;
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 商品浏览/访问历史
     */
    public function goodsHisLists()
    {
        $page = app()->request()->params('page', 1);
        $page=intval($page);
        if($page<1){
            $page=1;
        }
        $pageSize = 5;
        $offset=($page-1)*$pageSize;
        $uid=$this->uid;
        $lists=app('mysqlbxd_app')->select("select target as g_id, max(vtime) as vtime from visit_log where u_id='{$uid}' and type=2 group by target order by vtime desc limit {$offset},{$pageSize} ");
        $data=[];
        if($lists){
            $g_ids=array_column($lists,'g_id');
            $g_ids_filter_list=app('mysqlbxd_mall_user')->select("select g_id from goods where g_id in(".implode(',', $g_ids).")");
            if($g_ids_filter_list){
                $goodsLib = new \Lib\Mall\Goods();
                $res = $goodsLib->itemQuery([
                    'id' => implode(',', array_column($g_ids_filter_list,'g_id')),
                ]);
                if ($res['count']) {
                    $goods=array_column($res['list'],null,'g_id');
                    foreach ($lists as $row){
                        if(isset($goods[$row['g_id']])){
                            $goodsInfo=$goods[$row['g_id']];
                            $row['goodsInfo']=[
                                'g_id'=>$goodsInfo['g_id'],
                                'g_name'=>$goodsInfo['g_name'],
                                'g_price'=>$goodsInfo['isSecKill']?$goodsInfo['g_activityPrice']:$goodsInfo['g_price'],
                                'categoryName2'=>$goodsInfo['categoryName2'],
                                'g_surfaceImg'=>$goodsInfo['g_surfaceImg'],
                            ];
                            $data[]=$row;
                        }
                    }
                }
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 增加浏览/访问商品的历史
     */
    public function goodsHisAdd()
    {
        $gid = app()->request()->params('gid', 0);
        $uid=$this->uid;
        $date=date('Y-m-d H:i:s');
        $data=[
            'g_id'=>$gid,
            'time'=>$date,
            'is_success'=>0,
        ];
        if($uid){
            $gid=intval($gid);
            $count=app('mysqlbxd_mall_user')->fetchColumn("select count(*) c from goods where g_id='{$gid}'");
            if($gid>0 && $count>0){
                app('mysqlbxd_app')->insert('visit_log',[
                    'type'=>2,
                    'u_id'=>$uid,
                    'target'=>$gid,
                    'vtime'=>$date
                ]);
                $data['is_success']=1;
            }
        }
        $this->responseJSON($data);
    }
}