<?php
/**
 * 用户个人设置
 * @author Administrator
 *
 */
namespace Controller\User;

use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Setting extends BaseController
{

    private $settingModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = new \Model\User\Setting();
        $this->goodsCategoryLib = new \Lib\Mall\GoodsCategory();
        
    }

    /**
     * 获取个人设置
     *
     * @throws ModelException
     */
    public function get()
    {
        $data = [];
        $key = app()->request()->params('key', '');
        $keys = app()->request()->params('keys', '');
        $uid = $this->uid;
        if($keys){
            $keys=explode(',',$keys);
            if($keys && is_array($keys)){
                foreach ($keys as $k){
                    $v = $this->settingModel->settingGet($uid, $k);
                    $data[$v['uset_key']]= $v['uset_value'];
                }
            }
        }else if ($key) {
            $value = $this->settingModel->settingGet($uid, $key);
            $data[$value['uset_key']]= $value['uset_value'];
        } else {
            $rows = $this->settingModel->getAll($uid);
            if ($rows) {
                foreach ($rows as $row) {
                    $data[$row['uset_key']] = $row['uset_value'];
                }
            }
        }
        $this->responseJSON($data);
    }

    /**
     * 获取喜好分类列表
     */
    public function getHobbyList()
    {
        $params = ['id'=>0];//app()->request()->params();
        //$resMall = $this->goodsCategoryLib->lists($params);
        //print_r($params);exit;
        $resMall = $this->goodsCategoryLib->getHobbyCategoryList($params);
        //print_r($resMall);
        $this->responseJSON($resMall);
    }

    /**
     * 默认设置
     */
    public function getDefault()
    {
        $key = app()->request()->params('key', '');
        $uid = $this->uid;
        $value = null;
        switch ($key) {
            case 'hobby2':
            case 'speciality':
                $value = $this->settingModel->getCategoryByPid();
                break;
        }
        $this->responseJSON($value);
    }

    /**
     * 设置个人信息
     *
     * @throws ModelException
     */
    public function set()
    {
        $dataStr = app()->request()->params('data');
        if (! $dataStr) {
            throw new ParamsInvalidException("缺少参数");
        }
        $data = json_decode($dataStr, true);
        if (json_last_error() || ! $data) {
            throw new ParamsInvalidException("参数错误");
        }
        $this->settingModel->settingSets($this->uid, $data);
        //完善个人信息送积分
        if(isset($data[\Model\User\Setting::KEY_USER_INTRODUCTION]) && $data[\Model\User\Setting::KEY_USER_INTRODUCTION]){
            (new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_USER_INTRODUCTION_ADD);
        }
        $this->responseJSON(true);
    }

    /**
     * 上传背景图片, 艺术圈 主页
     */
    public function updateBackground()
    {
        $imageType = app()->request()->params('type');
        if ( empty($imageType) || !in_array($imageType, ['treasur_background', 'host_background']) ) {
            throw new ParamsInvalidException("类型错误".'-'.$imageType); 
        }       

        $types = [
            'image/jpeg' => "jpg",
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];      
        $size = 40 * 1024 * 1024; 
        $ftpConfigKey = 'user_background';
        $uid = $this->uid;
        $filesData = FileHelper::uploadFiles($ftpConfigKey, $size, $types);
        $filePreviewUrl = '';
        $data = [];
        if (!empty($filesData['result'])) {
            $filePath = empty($filesData['data'][0]['filePath']) ? '' : $filesData['data'][0]['filePath'];
            $filePreviewUrl = empty($filesData['data'][0]['previewUrl']) ? '' : $filesData['data'][0]['previewUrl'];
            $data = [$imageType => $filePath];
        }

        if (empty($data)) {
            throw new ParamsInvalidException("参数错误"); 
        }       
        $this->settingModel->settingSets($this->uid, $data); 

        $this->responseJSON($filePreviewUrl);
    }

}
