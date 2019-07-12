<?php

namespace Lib\User;

use Exception\InternalException;
use Exception\ServiceException;
use Framework\Lib\Validation;
use Exception\ParamsInvalidException;

/**
 * 用户基础公共类
 *
 * @author Administrator
 *
 */
class User
{

    private $userApi = null;

    public function __construct($setHeader = true)
    {
        $this->userApi = get_api_client('User');
        if ($setHeader) {
            $this->userApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
            $this->userApi->setHeader('X-Forwarded-URL', $this->getRequestUrl());
        }
    }

    /**
     * Get URL (scheme + host [ + port if non-standard ])
     * @return string
     */
    private function getRequestUrl()
    {
        $url = get_request_url_schema() . '://' . app()->request()->getHost();
        if ((app()->request()->getScheme() === 'https' && app()->request()->getPort() !== 443) || (app()->request()->getScheme() === 'http' && app()->request()->getPort() !== 80)) {
            $url .= sprintf(':%s', app()->request()->getPort());
        }

        return $url;
    }

    /** 查询验证码
     * @param $phone
     * @param int $type
     * @return mixed
     * @throws ServiceException
     */
    public function queryCaptcha($phone, $type = 0)
    {
        $this->userApi->chooseRequest('user/captcha/query', 2)
            ->setParam('phone', $phone)
            ->setParam('type', $type);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\ServiceException($sendRes->data, $sendRes->code);
        }
        return $sendRes->data;
    }

    /**
     * 检查验证码
     *
     * @param string $phone
     * @param string $captcha
     * @throws \Exception\InternalException
     * @return boolean
     */
    public function checkCaptcha($phone, $captcha, $type = 0)
    {
        $this->userApi->chooseRequest('user/captcha/check', 2)
            ->setParam('phone', $phone)
            ->setParam('captcha', $captcha)
            ->setParam('type', $type);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\ServiceException($sendRes->data, $sendRes->code);
        }
        return true;
    }

    /**
     * 发送短信验证码
     *
     * @param string $phone
     * @param number $type
     *            验证码类型:0无，1登录，2找回密码，3注册,4设置支付密码，5忘记支付密码.默认0
     * @throws \Exception\InternalException
     * @return boolean
     */
    public function sendCaptcha($phone, $type = 0)
    {
        $this->userApi->chooseRequest('user/captcha/get', 1)
            ->setParam('phone', $phone)
            ->setParam('type', $type);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\InternalException($sendRes->data, $sendRes->code);
        }
        return true;
    }

    public function sendCaptchaInfo($phone, $type = 0, $param = [])
    {
        $this->userApi->chooseRequest('user/captcha/Info', 1)
            ->setParam('phone', $phone)
            ->setParam('type', $type)
            ->setParam('param', $param);
        $sendRes = $this->userApi->execRequest();

        return $sendRes;
    }

    /** 发送设置支付密码的验证码
     * @param $phone
     * @param int $type
     * @return bool
     * @throws InternalException
     */
    public function sendPayPwdCaptcha($phone, $type)
    {
        if (!in_array($type, [4, 5])) {
            throw new \Exception\InternalException(false);
        }

        $this->userApi->chooseRequest('user/captcha/get/new', 1)
            ->setParam('phone', $phone)
            ->setParam('type', $type);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\InternalException($sendRes->data, $sendRes->code);
        }

        return $sendRes->data;

    }

    /**
     * 修改密码
     */
    public function changeUserPassword($uid, $oldPassword, $password)
    {
        $this->userApi->chooseRequest('user/password/by/oldpassword/update', 2)
            ->setParam('uid', $uid)
            ->setParam('oldPassword', $oldPassword)
            ->setParam('password', $password);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\InternalException($sendRes->data, $sendRes->code);
        }
        return true;
    }

    /**
     * 设置密码
     */
    public function setUserPassword($uid, $password)
    {
        $this->userApi->chooseRequest('user/password/by/admin/update', 2)
            ->setParam('uid', $uid)
            ->setParam('password', $password);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\InternalException($sendRes->data, $sendRes->code);
        }
        return true;
    }

    /**
     * 通过手机验证码找回密码
     *
     * @param string $phone
     * @param string $captcha
     * @param string $password
     * @throws ParamsInvalidException
     * @return unknown
     */
    public function findPasswordByCaptcha($phone, $captcha, $password)
    {
        if (!$phone || !$captcha || !$password) {
            throw new ParamsInvalidException("缺少参数");
        }
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!Validation::checkNumber($captcha, 6)) {
            throw new ParamsInvalidException("验证码格式错误");
        }
        if (!Validation::checkLen($password, 6, 18)) {
            throw new ParamsInvalidException("密码长度6~18位");
        }
        if (!Validation::checkNumAlpha($password)) {
            throw new ParamsInvalidException("密码必须是字母、数字的组合");
        }

        $this->userApi->chooseRequest('user/password/by/phone/update', 2)
            ->setParam('phone', $phone)
            ->setParam('captcha', $captcha)
            ->setParam('password', $password);
        $regRes = $this->userApi->execRequest();
        return $regRes;
    }

    /**
     * 用户登录
     *
     * @param string $phone
     * @param string $captcha
     * @param string $password
     * @param string $channel
     * @throws ParamsInvalidException
     * @return unknown
     */
    public function userLogin($phone, $captcha, $password, $channel)
    {
        if (!$phone) {
            throw new ParamsInvalidException("账号必须");
        }
        if (!$password && !$captcha) {
            throw new ParamsInvalidException("密码或验证码必须");
        }

        $this->userApi->chooseRequest('user/login', 2)->setParam('phone', $phone);
        if ($password) {
            $this->userApi->setParam('password', $password);
        } else if ($captcha) {
            $this->userApi->setParam('captcha', $captcha);
        }elseif($channel) {
            $this->userApi->setParam('channel', $channel);
        }

        $loginRes = $this->userApi->execRequest();

        return $loginRes;
    }

    /**
     * 注册新用户
     *
     * @param string $phone
     * @param string $captcha
     * @param string $password
     * @throws ParamsInvalidException
     * @return array
     */

    public function regNewUser($phone, $captcha, $password = '',$from='')
    {
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }
        if (!$password) {
            //$password = substr($phone, - 6);
        }
        $this->userApi->chooseRequest('user/add', 2)
            ->setParam('phone', $phone)
            ->setParam('password', $password)
            ->setParam('from', $from)
            ->setParam('type', 0);
        $this->userApi->setParam('captcha', $captcha);
        $regRes = $this->userApi->execRequest();
        return $regRes;
    }

    /**
     *
     * @param int $uid
     * @param array $params
     * @throws ParamsInvalidException
     * @return object
     */
    public function updateUserInfo($uid, $params)
    {
        if (!$uid || !$params) {
            throw new ParamsInvalidException("缺少参数");
        }
        if (!is_array($params)) {
            throw new ParamsInvalidException("参数错误");
        }
        $allowParams = array(
            'uid',
            'email',
            'nickname',
            'realname',
            'provinceCode',
            'cityCode',
            'areaCode',
            'gender',
            'birthday',
            'from',
            'channel',
            'type',
            'qq',
            'wechat',
            'store_des'
        );
        foreach ($params as $key => $val) {
            if (!in_array($key, $allowParams)) {
                throw new ParamsInvalidException("{$key}:不支持的参数");
            }
        }
        $params['uid'] = $uid;
        $this->userApi->chooseRequest('user/update', 1)->setParams($params);
        $updRes = $this->userApi->execRequest();
        return $updRes;
    }
    /**
     *
     * @param int $uid
     * @param string $phone
     * @param string $captcha
     * @throws ParamsInvalidException
     * @return object
     */
    public function updateUserPhone($uid, $phone,$captcha)
    {
        if (!$uid || !$phone) {
            throw new ParamsInvalidException("缺少参数");
        }
        $params['uid'] = $uid;
        $params['phone'] = $phone;
        $params['captcha'] = $captcha;
        $this->userApi->chooseRequest('user/phone/update', 2)->setParams($params);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            throw new ServiceException($res->data, $res->code);
        }
        return $res->data;
    }
    /**
     * 获取用户信息*
     * @param array $uids
     * @param number $needExtend
     * @throws ServiceException
     */
    public function getUserInfo($uids, $phone = '', $needExtend = 0)
    {
        $parsedUids = array();
        foreach ($uids as $uid) {
            if ($uid) {
                $parsedUids[] = $uid;
            }
        }
        $this->userApi->chooseRequest('user/get', 1)->setParam('needExtend', $needExtend);
        if (!empty($parsedUids)) {
            $this->userApi->setParam('uids', implode(',', $parsedUids));
        } else if ($phone) {
            $this->userApi->setParam('phone', $phone);
        } else {
            throw new ParamsInvalidException("uids或phone必须");
        }

        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            throw new ServiceException($res->data, $res->code);
        }
        return $res->data;
    }

    public function getUserInfoByPhone($phone,$needExtend=0)
    {
        $uInfos=$this->getUserInfo([],$phone,$needExtend);
        if($uInfos &&  is_array($uInfos)){
            foreach ($uInfos as $uid=>$uInfo){
                return $uInfo;
            }
        }
        return [];
    }

    /**
     * 获取用户IM信息
     */
    public function queryUserIm($uid)
    {
        $parsedUids = array();
        $this->userApi->chooseRequest('user/im', 1);
        $this->userApi->setParam('uid', $uid);
        $res = $this->userApi->execRequest();

        if ($res->code != 200) {
            throw new ServiceException($res->data, $res->code);
        }
        return $res->data;
    }

    /**
     * 模糊查询用户
     */
    public function fuzzySearch($data)
    {
        $parsedUids = array();
        $this->userApi->chooseRequest('user/fuzzy/search', 1);
        $this->userApi->setParams($data);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /**
     * 使用手机号，查询用户id
     * @param unknown $phone
     * @throws ServiceException
     */
    public function queryUserIdByPhone($phone)
    {
        $parsedUids = array();
        $this->userApi->chooseRequest('user/query', 1);
        $this->userApi->setParam('phone', $phone);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }
    /**
     * 使用微信unionid，查询用户
     * @param unknown $unionid
     * @throws ServiceException
     */
    public function queryUserIdByWxUnionId($unionid)
    {
        $this->userApi->chooseRequest('user/query', 1);
        $this->userApi->setParam('wx_unionid', $unionid);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /*
     * 获取推荐艺术家
     */
    public function queryUserIsRecommend($type, $page, $pageSize, $condition = [])
    {
        $this->userApi->chooseRequest('user/query/recommend', 1);
        $this->userApi->setParam('type', $type);
        $this->userApi->setParam('page', $page);
        $this->userApi->setParam('pageSize', $pageSize);
        $this->userApi->setParams($condition);
        $res = $this->userApi->execRequest();

        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }


    /**
     * 更新用户头像
     *
     * @param int $uid
     * @param string $targetFile
     * @param string $filetype
     * @param string $qrText
     * @return unknown
     */
    public function updateAvatar($uid, $targetFile, $filetype, $qrText = '')
    {
        if (!$uid || !$targetFile || !$filetype) {
            throw new ParamsInvalidException("缺少参数");
        }
        if (!is_file($targetFile)) {
            throw new ParamsInvalidException("不是有效的图片");
        }
        $filetype = strtolower($filetype);
        $imgbinary = fread(fopen($targetFile, 'r'), filesize($targetFile));
        $restPostFile = 'data:image/' . $filetype . ';base64,' . base64_encode($imgbinary);
        $this->userApi->chooseRequest('user/avatar/update', 1)
            ->setParam('uid', $uid)
            ->setParam('file', $restPostFile)
            ->setParam('multiSave', 'original,large,middle,small,qrcode')
            ->setParam('qrtext', $qrText);
        $result = $this->userApi->execRequest();
        if ($result->code != 200) {
            throw new ServiceException($result->data);
        }
        return true;
    }

    /**
     * 将用户信息扩展进数组
     *
     * @param array $rows
     * @param array $loadMap
     *            格式 ['fromField'=>'toField']
     * @param string $uidColumnName
     *            一般是u_id
     * @return array
     */
    public function extendUserInfos2Array(&$rows, $uidColumnName, $loadMap)
    {
        $uids = array();
        foreach ($rows as $row) {
            if (!isset($row[$uidColumnName])) {
                break;
            }
            $u_id = $row[$uidColumnName];
            if (!is_numeric($u_id)) {
                continue;
            }
            $uids[] = $u_id;
        }
        $needExtends = 0;
        foreach ($loadMap as $k => $v) {
            if (strpos($k, 'ue_') === 0) {
                $needExtends = 1;
                break;
            }
        }
        if (!empty($uids)) {
            $userInfos = $this->getUserInfo($uids, '', $needExtends);
            if ($userInfos && is_array($userInfos) && !empty($userInfos)) {
                foreach ($rows as &$row) {
                    $u_id = $row[$uidColumnName];
                    if (isset($userInfos[$u_id])) {
                        $userInfo = $this->loadUserInfo2Array($userInfos[$u_id], $loadMap);
                        $row = array_merge($row, $userInfo);
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * 从用户信息加载指定的字段
     *
     * @param array $userInfo
     * @param array $map
     *            ['fromField'=>'toField']
     * @return array
     */
    public function loadUserInfo2Array($userInfo, $map)
    {
        $result = [];
        foreach ($map as $kFrom => $kTo) {
            $result[$kTo] = '';
        }
        if ($userInfo && is_array($userInfo) && !empty($userInfo)) {
            foreach ($map as $kFrom => $kTo) {
                if (strpos($kFrom, 'ue_') === 0) {
                    if (isset($userInfo['user_extend']) && is_array($userInfo['user_extend']) &&
                        !empty($userInfo['user_extend'])) {
                        if (isset($userInfo['user_extend'][$kFrom])) {
                            $result[$kTo] = $userInfo['user_extend'][$kFrom];
                        }
                    }
                } else {
                    if (isset($userInfo[$kFrom])) {
                        $result[$kTo] = $userInfo[$kFrom];
                    }
                }
            }
        }
        return $result;
    }

    public function updateUserIm()
    {
        $this->userApi->chooseRequest('user/update/ali/im', 1);
        $this->userApi->setParam('uid', 111);
        $res = $this->userApi->execRequest();
        return $res;
    }

    /** 获奖列表
     * @param $uid
     * @param $aid
     * @return bool
     */
    public function getAwardList($uid, $aid)
    {
        $this->userApi->chooseRequest('user/award/lists', 1);
        $this->userApi->setParam('uid', $uid);
        $this->userApi->setParam('aid', $aid);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /** 添加/修改 获奖经历
     * @param $params
     * @return bool
     */
    public function awardPost($params)
    {
        $time = $params['time'];
        $des = $params['des'];
        $uid = $params['uid'];

        $this->userApi->chooseRequest('user/award/post', 1);

        if (!isset($params['id']) || empty($params['id'])) {  //新增
            if (!$time || !$des || !$uid) {
                throw new ParamsInvalidException("缺少参数");
            }

            $this->userApi->setParam('time', $time);
            $this->userApi->setParam('des', $des);
            $this->userApi->setParam('uid', $uid);
        } else {
            foreach ($params as $k => $val) {
                if ($val) {
                    $this->userApi->setParam($k, $val);
                }
            }
        }

        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            throw new ParamsInvalidException($res->data);
        }
        //完善获奖记录送积分
        (new \Lib\User\UserIntegral())->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_USER_REWARD_ADD);
        return $res->data;
    }

    /** 通过imId 获取uid
     * @param $imId
     * @return mixed
     * @throws ServiceException
     */
    public function getUidByIm($imId)
    {
        $this->userApi->chooseRequest('user/uid/by/imid', 1);
        $this->userApi->setParam('imId', $imId);
        $res = $this->userApi->execRequest();

        if ($res->code != 200) {
            throw new ServiceException($res->data, $res->code);
        }
        return $res->data;
    }

    /** 设置支付密码
     * @param $phone
     * @param $captcha
     * @param $password
     * @return mixed
     * @throws ParamsInvalidException
     */
    public function setPayPassword($phone, $captcha, $password, $type)
    {
        if (!$phone || !$captcha || !$password) {
            throw new ParamsInvalidException("缺少参数");
        }

        $this->userApi->chooseRequest('user/pay/password/update', 2)
            ->setParam('phone', $phone)
            ->setParam('captcha', $captcha)
            ->setParam('password', $password)
            ->setParam('type', $type);
        $regRes = $this->userApi->execRequest();
        if ($regRes->code != 200) {
            throw new \Exception\InternalException($regRes->data, $regRes->code);
        }
        return $regRes;
    }

    /** 检测支付密码
     * @param $password
     * @param $uid
     * @throws ServiceException
     */
    public function checkPayPwd($password, $uid)
    {
        $this->userApi->chooseRequest('user/pay/password/check', 2)
            ->setParam('uid', $uid)
            ->setParam('password', $password);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\ServiceException($sendRes->data, $sendRes->code);
        }
        return true;
    }

    /** 修改支付密码
     * @param $uid
     * @param $oldPassword
     * @param $password
     * @return mixed
     * @throws InternalException
     */
    public function updatePayPassword($uid, $oldPassword, $password, $type)
    {
        $this->userApi->chooseRequest('user/pay/password/by/old/update', 2)
            ->setParam('uid', $uid)
            ->setParam('oldPassword', $oldPassword)
            ->setParam('password', $password)
            ->setParam('type', $type);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\InternalException($sendRes->data, $sendRes->code);
        }
        return $sendRes;
    }

    /** 检测输入了几次错误支付密码
     * @param $uid
     * @param $type
     */
    public function checkPayPwdErrorCount($uid, $type)
    {
        $this->userApi->chooseRequest('user/check/paypwd/error', 2)
            ->setParam('uid', $uid)
            ->setParam('type', $type);
        $sendRes = $this->userApi->execRequest();
        if ($sendRes->code != 200) {
            throw new \Exception\InternalException($sendRes->data, $sendRes->code);
        }
        return $sendRes->data;
    }

    /** 更新用户最新一次上传作品的时间
     * @param $uid
     * @return bool
     */
    public function lastUploadTime($uid)
    {
        $this->userApi->chooseRequest('user/goods/last/upload', 1);
        $this->userApi->setParam('uid', $uid);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /** 修改用户的商品数
     * @param $params
     * @return bool
     */
    public function updateUserGoodsNum($params)
    {
        $this->userApi->chooseRequest('user/goods/num/update', 1);
        $this->userApi->setParams($params);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /** cli 修改用户的商品数
     * @param $params
     * @return bool
     */
    public function cliUpdateUserGoodsNum($params)
    {
        $this->userApi->chooseRequest('user/goods/num/update', 1);
        $this->userApi->setParams($params);
        $this->userApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /** 获取用户的扩展信息
     * @param $uid
     * @return bool
     */
    public function getUserExtend($uid)
    {
        $this->userApi->chooseRequest('user/extend/query', 1);
        $this->userApi->setParam('uid', $uid);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /** 分销商品到自己的店铺
     * @param $params
     * @return bool
     */
    public function distribution($params)
    {
        $this->userApi->chooseRequest('user/distribution', 1);
        $this->userApi->setParams($params);
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    /** 保险岛注册
     * @param $phone
     * @param $special_user
     * @return mixed
     * @throws ParamsInvalidException
     */

    public function regUser($phone, $special_user, $from = '')
    {
        if (!Validation::checkMobile($phone)) {
            throw new ParamsInvalidException("手机号格式错误");
        }

        $this->userApi->chooseRequest('user/add', 2)
            ->setParam('phone', $phone)
            ->setParam('special_user', $special_user)
            ->setParam('from', $from)
            ->setParam('type', 0);
        $regRes = $this->userApi->execRequest();
        return $regRes;
    }

    /**
     * 获取所有艺术家
     * @param $page
     * @param $pageSize
     * @param array $condition
     * @return bool
     */
    public function getUserArtList($page, $pageSize, $condition = [])
    {
        $this->userApi->chooseRequest('user/art/list', 1);
        $this->userApi->setParam('page', $page);
        $this->userApi->setParam('pageSize', $pageSize);
        $this->userApi->setParams($condition);
        $res = $this->userApi->execRequest();

        if ($res->code != 200) {
            return false;
        }
        return $res->data;
    }

    public function queryUserIsRegisterByPhones($phones)
    {
        $res=[];
        if($phones){
            if($list=app('mysqlbxd_user')->select('select u_phone from `user` where u_phone in(\''.implode('\',\'',$phones).'\')')){
                $res=array_column($list,'u_phone');
            }
        }
        return $res;
    }
}
