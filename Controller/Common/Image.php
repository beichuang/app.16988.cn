<?php
namespace Controller\Common;

use Exception\ParamsInvalidException;
use Exception\ServiceException;
use Lib\Base\BaseController;

class Image extends BaseController
{

    /**
     * resize && crop
     * @throws ServiceException
     */
    public function process()
    {
        $uri=app()->request()->params('uri');
        if(!preg_match("@(.+[^!])!(.+)@",$uri,$m)){
            throw new ServiceException("参数错误");
        }
        $fileUri=$m[1];
        $cmd=$m[2];
        switch ($cmd){
            case 'c50':
                $this->crop($fileUri,50,50,true);
                break;
            case 'rw300':
                $this->resize($fileUri,300,null,false);
                break;
            case 'rw600':
                $this->resize($fileUri,600,null,false);
                break;
            case 'c200-150':
                $this->crop($fileUri,200,150,false);
                break;
            case 'c400-250':
                $this->crop($fileUri,400,250,false);
                break;
            case 'c600-400':
                $this->crop($fileUri,600,400,false);
                break;
            case 'r600':
                $this->resize($fileUri,600,600,false);
                break;
            case 'c200':
                $this->crop($fileUri,200,200,false);
                break;
            case 'c100':
                $this->crop($fileUri,100,100,false);
                break;
            case 'rw800':
                $this->resize($fileUri,800,null,false);
                break;
            case 'c400':
                $this->crop($fileUri,400,400,false);
                break;
        }
    }
    private function resize($fileUri,$maxW,$maxH,$enlarge=0)
    {
        $enlarge=$enlarge?true:false;
        $image = new \Gumlet\ImageResize($this->getFilePath($fileUri));
        if($maxW && $maxH){
            $image->resizeToBestFit($maxW,$maxH,$enlarge);
        }else if($maxW){
            $image->resizeToWidth($maxW,$enlarge);
        }else if($maxH){
            $image->resizeToHeight($maxH,$enlarge);
        }
        $image->output();
        exit();
    }
    private function crop($fileUri,$w,$h,$enlarge=0)
    {
        $enlarge=$enlarge?true:false;
        $image = new \Gumlet\ImageResize($this->getFilePath($fileUri));
        if($w && $h){
            $image->crop($w,$h,$enlarge);
        }
        $image->output();
        exit();
    }
    private function getFilePath($fileUri)
    {
        $ext=pathinfo($fileUri,PATHINFO_EXTENSION);
        if(!in_array(strtolower($ext),['jpg','jpeg','png'])){
            throw new ServiceException('文件格式只能是\'jpg\',\'jpeg\',\'png\'');
        }
        $file='';
        $baseDir=dirname(app()->baseDir);
        if(strpos($fileUri,'/res/')===0){
            $file= "{$baseDir}/res.16988.cn".substr($fileUri,4);
        }elseif (strpos($fileUri,'/attach/')===0){
            $file= "{$baseDir}/attach.16988.cn".substr($fileUri,7);
        }
        if(!$file || !file_exists($file)){
            app()->status(404);
            throw new ServiceException("file no found");
        }
        return $file;
    }
}
