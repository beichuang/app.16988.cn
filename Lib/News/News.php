<?php
/**
 * 新闻
 * @author Administrator
 *
 */
namespace Lib\News;

use Exception\InternalException;
use Exception\ServiceException;
use Framework\Helper\FileHelper;
use Framework\Lib\Validation;
use Exception\ParamsInvalidException;
use Lib\Common\AppMessagePush;


class News 
{

    /**
     * 新闻列表
     *
     * @throws ModelException
     */
    public function getList($condition,$page=1,$pageSize=10)
    {
        $params = [];
        $params['n_status'] = 0;
        $params['n_form'] = 1;
        if (isset($condition['form']) && $condition['form']!='')
        {
            $params['n_form'] = $condition['form'];
        }
        if (isset($condition['is_index']) && $condition['is_index']!='')
        {
            $params['is_index'] = $condition['is_index'];
        }
        if (isset($condition['n_title']) && $condition['n_title']!='')
        {
            $params['n_title'] = $condition['n_title'];
        }
        if (isset($condition['n_type']) && is_numeric($condition['n_type']) && !empty($condition['n_type']))
        {
            $params['n_type'] = $condition['n_type'];
        }
        if (isset($condition['addTime']) && $condition['addTime']!='')
        {
            $params['addTime'] = $condition['addTime'];
        }
        if (isset($condition['recommend']) && $condition['recommend']!='')
        {
            $params['recommend'] = $condition['recommend'];
        }
        if (isset($condition['page']) && $condition['page']!='')
        {
            $page = $condition['page'];
        }
        if (isset($condition['pageSize']) && $condition['pageSize']!='')
        {
            $pageSize = $condition['pageSize'];
        }
        if (isset($condition['click_rate']) && $condition['click_rate']!='')
        {
            $params['click_rate'] = $condition['click_rate'];
        }
        if (isset($condition['dianzan']) && $condition['dianzan']!='')
        {
            $params['dianzan'] = $condition['dianzan'];
        }
        // 按照pc站排序
        if(isset($condition['pcOrder'])&&!empty($condition['pcOrder'])){
            $params['pcOrder'] = $condition['pcOrder'];
        }

        //是否按照分类展示
        if(isset($condition['is_show_item'])&&!empty($condition['is_show_item'])){
            $params['is_show_item'] = 1;
        }

        //pcHomePage  pc首页排序
        if(isset($condition['pcHomePage'])&&!empty($condition['pcHomePage'])){
            $params['pcHomePage'] = 1;
        }


        $newsLib = new \Model\News\News();

        list($getList['list'],$getList['count']) = $newsLib->getList($params,$page,$pageSize);
        
        return $getList;
    }
    /**
     * 获取资讯图片
     * @param unknown $nid
     */    
    public function newsImg($nid,$limit=6) {
        
        $newsLib = new \Model\News\News();
        
        $imgArr = $newsLib->getImg($nid,$limit);

        foreach ($imgArr as &$row) {
            if ($row['ni_img']) {
                $row['ni_img'] = FileHelper::getFileUrl($row['ni_img'], 'news_images');
            }
        }

        return $imgArr;
    }
    public function parseEmbed($content)
    {
        //找到embed标签或标志
        $embeds=[];
        if(preg_match_all("/\[\[\[(.*?)\]\]\]/is",$content,$m,PREG_SET_ORDER)){
            foreach ($m as $sm){
                $embeds[]=[
                    'origin'=>$sm[0],
                    'src'=>$sm[1]
                ];
            }
        }
        if(preg_match_all("/<embed.*?((>(.*?)<\/embed>)|(\/>))/is",$content,$m,PREG_SET_ORDER)){
            foreach ($m as $sm){
                $v=[];
                if(preg_match("/src=\"([^\s]+)\"/is",$sm[0],$ssm1)){
                    $v['origin']=$sm[0];
                    $v['src']=$ssm1[1];
                }
                $embeds[]=$v;
            }
        }
        //替换embed标签
        if($embeds){
            $content=$this->initAudioPlayer($content,$embeds);
        }
        return $content;

    }
    private function initAudioPlayer($content,$audios)
    {
        //替换audios标签
        if($audios){
            foreach ($audios as $i=>$v){
                $audioTag=<<<AUDIO_TAG2
<audio src="{$v['src']}" width="50%" height="30" controls="controls"></audio> <br>
AUDIO_TAG2;
                $content=str_replace($v['origin'],$audioTag,$content);
            }
        }
        return $content;
    }

    public function parseVideo($content)
    {
        //找到video标签或标志
        $videos=[];
        if(preg_match_all("/\[\[\[(.*?)\]\]\]/is",$content,$m,PREG_SET_ORDER)){
            foreach ($m as $sm){
                $videos[]=[
                    'origin'=>$sm[0],
                    'src'=>$sm[1],
                    'poster'=>"https://zhangwan-video.oss-cn-hangzhou.aliyuncs.com/img/video_poster.jpg",
                ];
            }
        }
        if(preg_match_all("/<video.*?((>(.*?)<\/video>)|(\/>))/is",$content,$m,PREG_SET_ORDER)){
            foreach ($m as $sm){
                $v=[];
                if(preg_match("/src=\"([^\s]+)\"/is",$sm[0],$ssm1)){
                    $v['origin']=$sm[0];
                    $v['src']=$ssm1[1];
                    if(preg_match("/poster=\".*?([^\s]+).*?\"/is",$sm[0],$ssm2)){
                        $v['poster']=$ssm2[1];
                    }
                }
                $videos[]=$v;
            }
        }
        //替换video标签
        if($videos){
            $content=$this->initVideoPlayer($content,$videos);
        }
        return $content;
    }

    private function initVideoPlayer($content,$videos)
    {
        //替换video标签
        if($videos){
//            $html='<link rel="stylesheet" href="//g.alicdn.com/de/prismplayer/2.7.2/skins/default/aliplayer-min.css" />';
//            $html.='<script type="text/javascript" charset="utf-8" src="//g.alicdn.com/de/prismplayer/2.7.2/aliplayer-min.js"></script>';
//            $content=$html.$content;

            foreach ($videos as $i=>$v){
//                $videoTag=<<<videoTag
//                <div class="prism-player" id="player-con{$i}"></div>
//                <script>
//                var player{$i} = new Aliplayer({
//                  "id": "player-con{$i}",
//                  "source": "{$v['src']}",
//                  "width": "100%",
//                  "height": "220px",
//                  "autoplay": false,
//                  "isLive": false,
//                  "cover": "{$v['poster']}",
//                  "rePlay": false,
//                  "playsinline": true,
//                  "preload": false,
//                  "controlBarVisibility": "hover",
//                  "useH5Prism": true,
//                  "skinLayout": [
//                    {
//                      "name": "bigPlayButton",
//                      "align": "blabs",
//                      "x": 30,
//                      "y": 80
//                    },
//                    {
//                      "name": "H5Loading",
//                      "align": "cc"
//                    },
//                    {
//                      "name": "errorDisplay",
//                      "align": "tlabs",
//                      "x": 0,
//                      "y": 0
//                    },
//                    {
//                      "name": "infoDisplay"
//                    },
//                    {
//                      "name": "tooltip",
//                      "align": "blabs",
//                      "x": 0,
//                      "y": 56
//                    },
//                    {
//                      "name": "thumbnail"
//                    },
//                    {
//                      "name": "controlBar",
//                      "align": "blabs",
//                      "x": 0,
//                      "y": 0,
//                      "children": [
//                        {
//                          "name": "progress",
//                          "align": "blabs",
//                          "x": 0,
//                          "y": 44
//                        },
//                        {
//                          "name": "playButton",
//                          "align": "tl",
//                          "x": 15,
//                          "y": 12
//                        },
//                        {
//                          "name": "timeDisplay",
//                          "align": "tl",
//                          "x": 10,
//                          "y": 7
//                        },
//                        {
//                          "name": "fullScreenButton",
//                          "align": "tr",
//                          "x": 10,
//                          "y": 12
//                        },
//                        {
//                          "name": "volume",
//                          "align": "tr",
//                          "x": 5,
//                          "y": 10
//                        }
//                      ]
//                    }
//                  ]
//                }, function (player) {
//                    console.log("播放器创建了。");
//                  }
//                );
//                </script>
//videoTag;
                $videoTag=<<<VIDEO_TAG2
<video src="{$v['src']}" poster="{$v['poster']}" width="100%" height="220" controls="controls"></video> <br>
VIDEO_TAG2;
                $content=str_replace($v['origin'],$videoTag,$content);
            }
        }
        return $content;
    }
}
