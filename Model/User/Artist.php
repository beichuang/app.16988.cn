<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/13
 * Time: 15:32
 */

namespace Model\User;
use Lib\Base\BaseModel;

class Artist  extends BaseModel
{
    /**
     * pc站相关逻辑
     * @var string
     */
    protected $table = 'user';
    protected $id = 'u_id';
    //艺术家分类
    /**
     * @param int $page
     * @param int $pageSize
     * @param string $type
     * @param int $last_page
     * @param int $diff_num
     * @param $order
     * @param $condition;  搜索项目
     * @return array
     */
    public static  function getList($page=1, $pageSize=8,$type='',$last_page=0,$diff_num=0,$order='',$condition=[])
    {
        //站内艺术家获取
        $where = [];
        //站外，站内   艺术家  (一起分页)
        if(!$last_page){
            $s  = "select u.u_id, u.u_realname,u.u_nickname,u.u_avatar,(0) as `from`,ue_celebrityTitle from user u LEFT JOIN user_extend ue ON ue.u_id=u.u_id   where u.u_type=1 and ue.ue_status=1 " ;
            $sc = "select count(*) as count from user u LEFT JOIN user_extend ue ON ue.u_id=u.u_id  where 1=1 and  u.u_type=1  and ue_status=1 ";
            //艺术家分类名称
            if($type){
                $s .= " and ue.ue_celebrityTitle  like '%{$type}%' ";
                $sc.= " and ue.ue_celebrityTitle  like '%{$type}%'";
            }
            //艺术家搜索
            if(isset($condition["u_realname"])&&($condition["u_realname"])){
                $s .= " and u.u_realname  like  '%{$condition["u_realname"]}%' ";
                $sc.= " and u.u_realname  like  '%{$condition["u_realname"]}%' ";
            }
            
            $sql = '';
            $order = ' ORDER BY ue_isRecommendSort DESC ';
            $s .= $sql . $order;
            $sc .= $sql;
            $count      = app('mysqlbxd_user')->fetchColumn($sc, $where);   //总条数\
            $total_page = ceil(($count/$pageSize));
            if($page>=$total_page){
                $last_page   = 1;
            }else{
                $last_page  = 0;
            }
            $list       = app('mysqlbxd_user')->selectPage($s,$page,$pageSize, $where);
            //如果是最后一页   数据填充
            if($last_page){
                $diff_num  = $pageSize - count($list);
            }
            //如果是最后一页   数据    补充
            if($diff_num){
                $sql2  = " select artistId,u_avatar,u_realname,u_avatar,(1) as `from`,ue_celebrityTitle from outside_artist  where status=1  ";
                if($type){
                    $sql2 .= " and ue_celebrityTitle  like  '%{$type}%' ";
                }

                //艺术家搜索
                if(isset($condition["u_realname"])&&($condition["u_realname"])){
                    $sql2 .= " and u_realname  like  '%{$condition["u_realname"]}%'  ";
                }

                //艺术家删除情况控制
                if(isset($condition["remove"])&&is_numeric($condition["remove"])){
                    $sql2 .= " and remove={$condition['remove']}  ";
                }

                $sql2  .= " limit 0,{$diff_num}  ";
                $list1 = app('mysqlbxd_user')->select($sql2);
                $list  = array_merge($list,$list1);
            }
            //判断是否是最后一页
            return [
               'list' => $list,
               'last_page'=>$last_page,
               'diff_num' =>$diff_num
            ];
        }

        //站外艺术家  (模块单独分页)
        if($last_page){
              $start = ($page-1) * $pageSize+$diff_num;
              $sql3  = " select artistId,u_avatar,u_realname ,(1) as `from`,ue_celebrityTitle from outside_artist where status=1  ";
              if($type){
                  $sql3 .= " and ue_celebrityTitle  like  '%{$type}%'  ";
              }

             //艺术家搜索
             if(isset($condition["u_realname"])&&($condition["u_realname"])){
                $sql3 .= " and u_realname like '%{$condition["u_realname"]}%'  ";
             }

            //艺术家删除情况控制
            if(isset($condition["remove"])&&is_numeric($condition["remove"])){
                $sql3 .= " and remove={$condition['remove']}  ";
            }

             if($order==6){
                $sql3.= ' order by  `sort` desc ';
              }
              $sql3  .= " limit {$start},{$pageSize}  ";
              $list = app('mysqlbxd_user')->select($sql3);
              return [  'list' => $list,'last_page'=>1,'diff_num'=>$diff_num];
        }
    }

    /**
     * 热门艺术家列表
     * @var string
     */
    public  static function hotArtists(){
        $s          = "select u.u_id, u.u_realname,u.u_nickname,u.u_avatar,(0) as `from`,ue_celebrityTitle,ue.ue_celebrityTitle from user u LEFT JOIN user_extend ue ON ue.u_id=u.u_id   where u.u_type=1 and ue.ue_status=1  ORDER BY ue_browse_artist DESC limit 0,6" ;
        $list       = app('mysqlbxd_user')->select($s);
        return $list;
    }













}