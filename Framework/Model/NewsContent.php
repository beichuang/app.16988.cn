<?php

// 新闻内容处理
namespace Framework\Model;


class NewsContent
{


    public function saveThirdNewsImages2Oss($newsHtml)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$newsHtml);
        $xpath = new \DOMXpath($dom);
        $selector="//img";
        $elements = $xpath->query($selector);
        if ($elements === false)
        {
            throw new ServiceException("the selector in the xpath(\"{$selector}\") syntax errors");
        }
        $result = array();
        if (!is_null($elements))
        {
            foreach ($elements as $element)
            {
                $nodeName = $element->nodeName;
                $nodeType = $element->nodeType;     // 1.Element 2.Attribute 3.Text
                // 如果是img标签，直接取src值
                if ($nodeType == 1 && in_array($nodeName, array('img')))
                {
                    $content = $element->getAttribute('src');
                }
                // 如果是标签属性，直接取节点值
                elseif ($nodeType == 2 || $nodeType == 3 || $nodeType == 4)
                {
                    $content = $element->nodeValue;
                }
                else
                {
                    $content = preg_replace(array("#^<{$nodeName}.*>#isU","#</{$nodeName}>$#isU"), array('', ''));
                }
                $result[] = $content;
            }
        }
        if($result){
            $needUpload=[];
            foreach ($result as $url){
                if(preg_match('/aliyun_oss\/.+/',$url) || isset($needUpload[$url])){
                    continue;
                }else{
                    $needUpload[$url]=1;
                }
            }
            $needUpload2=array_keys($needUpload);
            foreach ($needUpload2 as $url){
                $fileName=pathinfo($url,PATHINFO_BASENAME);
                $data=file_get_contents($url);
                if($data){
                    $jpeg_quality = 90;
                    $img_r = imagecreatefromstring($data);
                    list($w,$h,,)=getimagesizefromstring($data);
                    if(preg_match("/(arton|artimg\.net)/",$url)){
                        $this->imagemask($img_r, $w-160, $h-60, $w, $h, 20);
                    }
                    $tmpFile=__DIR__.'/'.time().\Framework\Helper\Str::random().'.jpg';
                    imagejpeg($img_r,$tmpFile,$jpeg_quality);
                    if($new_url_info= \Framework\Helper\FileHelper::saveImageContent2Oss(file_get_contents($tmpFile),$tmpFile,'news_images')){
                        $newsHtml=str_replace($url,$new_url_info['url'],$newsHtml);
                    }
                    unlink($tmpFile);
                }
            }
        }
        return $newsHtml;
    }
    private function imagemask(&$im, $x1, $y1, $x2, $y2, $deep)
    {
        for($x = $x1; $x < $x2; $x += $deep){
            for ($y = $y1; $y < $y2; $y += $deep){
                $color = ImageColorAt ($im, $x + round($deep / 2), $y + round($deep / 2));
                imagefilledrectangle ($im, $x, $y, $x + $deep, $y + $deep, $color);
            }
        }
    }
}