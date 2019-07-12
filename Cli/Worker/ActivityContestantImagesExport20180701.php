<?php

/**
 * 导出参赛选手作品，图片
 */
namespace Cli\Worker;

use Framework\Helper\FileHelper;

class ActivityContestantImagesExport20180701
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_app');
    }
/*    public function exportData()
    {
        $sql="select a.`aci_id`, a.`ac_id`, a.`aci_sort`, a.`aci_img`, a.`aci_imageType`,b.ac_name,b.ac_organization_name,b.ac_category from `activity_contestant_image` a
join activity_contestant b on a.ac_id=b.ac_id and b.ac_auditStatus=2
 where a.aci_imageType = 2 
 order by a.aci_sort , a.aci_sort  ";
        $datas=$this->db->select($sql);
        $csvFile=__DIR__.'/export_'.time().'.csv';
        foreach ($datas as $data){

        }
    }*/

    public function run()
    {
        $offset=isset($_GET['offset'])?$_GET['offset']:0;
        $limit=isset($_GET['limit'])?$_GET['limit']:20;

        do{
            $count=0;
            $list=$this->fetchData($offset,$limit);
            if(is_array($list)){
                $count=count($list);
                foreach ($list as $img){
                    $this->saveImage($img);
                }
            }
            msg("offset:{$offset}   limit:{$limit}  count:{$count}");

            $offset=$offset+$count;
        }
        while ($count>0);
    }

    private function saveImage($image)
    {
        $user=$this->getUserInfo($image['ac_id']);
        $url=$image['aci_img'];
        if($url && $user){
            $catName=$user['ac_category']==1?'毛笔':'硬笔';
            $ext=pathinfo($url,PATHINFO_EXTENSION);
            $save_name="{$image['ac_id']}-{$user['ac_name']}-{$user['ac_organization_name']}-{$catName}-{$image['aci_sort']}.{$ext}";
            $save_path= __DIR__ . '/images_download/' .$user['ac_category'];
            $save_file=$save_path.'/'.$save_name;
            if(!is_dir($save_path)){
                mkdir($save_path,0777,true);
            }
            $originFile=FileHelper::getFileUrl($url);
            if($originFile){
                msg($originFile.'  saveAS:  '. $save_file);
                file_put_contents($save_file,file_get_contents($originFile));
            }
        }
    }

    private function getUserInfo($uid)
    {
        $user=$this->db->fetch("select * from activity_contestant where ac_id=:ac_id and ac_auditStatus=:ac_auditStatus",[
            'ac_id'=>$uid,
            'ac_auditStatus'=>1
        ]);
        return $user;
    }

    private function fetchData($offset=0,$limit=20)
    {
        $sql = "select * from `activity_contestant_image` order by ac_id , aci_sort,aci_id  limit $offset,$limit";
        $data = $this->db->select($sql);
        return $data;
    }





}
