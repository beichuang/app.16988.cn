<?php
namespace Lib\User;

use Exception\ServiceException;
use Exception\InternalException;
use SnsAuth\OAuth2\OAuth;
use SnsAuth\helper\Http;

/**
 * 用户第三方登录
 *
 * @author Administrator
 *        
 */
class ThirdLoginOAuth
{

    private $userApi = null;

    private $allChannels = [
        'QQ',
        'Weixin'
    ];

    public function __construct()
    {
        $this->userApi = get_api_client('User');
        $this->userApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
    }

    /**
     * 绑定第三方用户
     *
     * @param int $uid            
     * @param array $sns_info
     *            array(
     *            'openid' => 'ssfgergre',
     *            'channel' => 'qq',
     *            'nick' => 'rewgdsate',
     *            'gender' => 'rewgdsfafd',
     *            'avatar' =>'https://fsaewgr/fdsaafd/fdsa/fwe.jpg'
     *            );
     * @throws ServiceException
     * @return Ambigous <multitype:, unknown>
     */
    public function bindThirdUser($uid, $sns_info)
    {
        $thirdPartyId = $this->getThirdpartyId($sns_info['channel']);
        $thirdPartyAccountId = $sns_info['openid'];
        $this->userApi->chooseRequest('user/thirdparty/bind', 1)
            ->setParam('uid', $uid)
            ->setParam('thirdpartyId', $thirdPartyId)
            ->setParam('thirdpartyAccountId', $thirdPartyAccountId);
        if(isset($sns_info['wxunionid']) && $sns_info['wxunionid']){
            $this->userApi->setParam('wxunionid', $sns_info['wxunionid']);
        }
        $thirdInfoRes = $this->userApi->execRequest();
        if ($thirdInfoRes->code == 200) {
            $data = $thirdInfoRes->data;
            $userInfo = $data['userInfo'];
            $uid = $userInfo['u_id'];
            return $this->saveThirdSnsInfo($uid, $sns_info, $userInfo);
        } else {
            throw new ServiceException($thirdInfoRes->data, $thirdInfoRes->code);
        }
    }

    /**
     * 第三方登录,回调,处理用户登录
     *
     * @param unknown $channel            
     */
    // public function thirdCallback($channel)
    // {
    // $OAuth = $this->getOAuthObj($channel);
    // $OAuth->getAccessToken();
    // $sns_info = $OAuth->userinfo();
    // return $this->saveThirdSnsInfoIfNotExists($sns_info);
    // }
    
    /**
     * 获取本系统的第三方唯一id
     *
     * @param unknown $channel            
     * @return string
     */
    public function getThirdpartyId($channel)
    {
        return strtoupper($channel) . '_10000';
    }

    /**
     * 查询第三方信息
     *
     * @param unknown $sns_info
     *            array(
     *            'openid' => ,
     *            'channel' => 'qq',
     *            'nick' => ,
     *            'gender' => ,
     *            'avatar' =>
     *            );
     */
    public function queryThirdSnsInfo($sns_info)
    {
        $thirdPartyId = $this->getThirdpartyId($sns_info['channel']);
        $thirdPartyAccountId = $sns_info['openid'];
        $this->userApi->chooseRequest('user/thirdparty/info', 1)
            ->setParam('thirdpartyId', $thirdPartyId)
            ->setParam('thirdpartyAccountId', $thirdPartyAccountId);
        if(isset($sns_info['wxunionid']) && $sns_info['wxunionid']){
            $this->userApi->setParam('wxunionid', $sns_info['wxunionid']);
        }
        $thirdInfoRes = $this->userApi->execRequest();
        $userInfo = [];
        if ($thirdInfoRes->code == 200) {
            $data = $thirdInfoRes->data;
            $userInfo = $data['userInfo'];
        } else {
            throw new ServiceException($thirdInfoRes->data, $thirdInfoRes->code);
        }
        return $userInfo;
    }

    /**
     * 保存第三方账号信息
     *
     * @param unknown $sns_info
     *            array(
     *            'openid' => $this->openid(),
     *            'channel' => 'qq',
     *            'nick' => ,
     *            'gender' => ,
     *            'avatar' =>
     *            );
     */
    private function saveThirdSnsInfo($uid, $sns_info, $userInfo)
    {
        $userLib = new User();
        // 保存用户信息
        if(empty($userInfo['u_nickname_realvalue'])) {
            $userLib->updateUserInfo($uid, [
                    'nickname' => $sns_info['nick'],
                    'gender' => $this->getGender($sns_info['gender']),
                ]);
        }
        // 保存用户头像
        if(empty($userInfo['u_avatar_source'])) {
            $targetFile = $sns_info['avatar'];
            if ($targetFile) {
                $tmpFile = app()->baseDir . '/Data/Temp/';
                try {
                    $tmpFile = $this->saveImage($targetFile, $tmpFile);
                    $filetype = strtolower(get_image_type($tmpFile));
                    if (!$filetype) {
                        throw new ServiceException("无效的头像");
                    }
                    $userLib->updateAvatar($uid, $tmpFile, $filetype);
                    unlink($tmpFile);
                } catch (\Exception $e) {
                    if ($tmpFile && is_file($tmpFile)) {
                        unlink($tmpFile);
                    }
                }
            }
        }
        // 查询修改后的用户信息，用于登录处理
        return $this->queryThirdSnsInfo($sns_info);
    }

    /**
     * 获取第三方认证处理实例
     *
     * @param unknown $channel            
     * @throws ParamsInvalidException
     * @throws ServiceException
     * @return unknown
     */
    public function getOAuthObj($channel)
    {
        $this->checkChannel($channel);
        $config = load_row_configs_trim_prefix('Thirdparty.UserLogin.' . $channel);
        if (! $config || ! is_array($config) || empty($config)) {
            throw new ServiceException('配置加载失败');
        }
        $serverHost = app()->request->getUrl();
        $config['callback']['default'] = $serverHost . $config['callback']['default'];
        $config['callback']['mobile'] = $serverHost . $config['callback']['mobile'];
        
        $OAuth = OAuth::getInstance($config, $channel);
        $OAuth->setDisplay('mobile');
        return $OAuth;
    }

    /**
     * 检查第三方类型
     *
     * @param unknown $channel            
     * @throws ParamsInvalidException
     */
    private function checkChannel($channel)
    {
        if (! in_array($channel, $this->allChannels)) {
            throw new ParamsInvalidException("不支持的第三方类型");
        }
    }

    /**
     * 性别转换
     *
     * @param unknown $gender            
     * @return Ambigous <NULL, string>
     */
    private function getGender($gender)
    {
        $return = null;
        switch ($gender) {
            case 'm':
                $return = 1;
                break;
            case 'f':
                $return = 2;
                break;
            default:
                $return = 0;
        }
        return $return;
    }

    private function saveImage($url, $path, $filename = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $img = curl_exec($ch);
        if ($img !== false) {
            $file_info = curl_getinfo($ch);
            curl_close($ch);
        } else {
            $errorCode = curl_errno($ch);
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new ServiceException("获取头像出错，$errorMsg", $errorCode);
        }
        $content_type = explode('/', $file_info['content_type']);
        if (strtolower($content_type[0]) != 'image') {
            throw new ServiceException('下载地址文件不是图片');
        }
        
        $file_path = '/' . trim($path, '/') . '/';
        if (is_null($filename)) {
            $filename = md5($url);
        }
        $file_path .= $filename . '.' . end($content_type);
        if (file_put_contents($file_path, $img)) {
            return $file_path;
        }
        return false;
    }
}
