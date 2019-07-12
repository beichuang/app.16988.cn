<?php

//阿里百川IM信息

namespace AliIm;

class AliIm {

    public static function getAvatarRootPath() {
        $path = '//192.168.1.214/attach/common/user/avatar'; //config("app.imgDomainPath");
        return rtrim($path, "/");
    }

    /**
     * 注册阿里百川信息
     */
    public function addUserInfo($uid, $pwd, $nick = '', $icon = '') {
        include "TopSdk.php";
        date_default_timezone_set('Asia/Shanghai');

        $c = new \TopClient;
        $c->appkey = '24496704';
        $c->secretKey = '955569aa494178b54ef468ea9a15f15d';

        $req = new \OpenimUsersAddRequest;
        $userinfos = new \Userinfos;

        $avatarRootPath = self::getAvatarRootPath();
        $urlSchema = get_request_url_schema();

        $userinfos->nick = $nick; //昵称
        $userinfos->icon_url = $urlSchema . ":" . $avatarRootPath . "/" . $icon; //头像
        $userinfos->userid = $uid; //uid
        $userinfos->password = $pwd;

        $req->setUserinfos(json_encode($userinfos));
        $resp = $c->execute($req);
        //$resp = simplexml_load_string($resp);
        $resp = json_decode(json_encode($resp), true);
        if (isset($resp['uid_succ'])) {
            return $resp;
        } else {
            return $resp;
        }
    }

    /**
     * 修改阿里百川信息
     * @param unknown $uid
     * @param unknown $pwd
     * @param string $nick
     * @param string $icon
     */
    public function updateUserInfo($uid, $pwd = '', $nick = '', $icon = '') {

        include "TopSdk.php";
        date_default_timezone_set('Asia/Shanghai');

        $c = new \TopClient;
        $c->appkey = '24496704';
        $c->secretKey = '955569aa494178b54ef468ea9a15f15d';

        $req = new \OpenimUsersUpdateRequest;
        $userinfos = new \Userinfos;

        $avatarRootPath = self::getAvatarRootPath();
        $urlSchema = get_request_url_schema();

        if ($nick) {
            $userinfos->nick = $nick; //昵称
        }

        if ($icon) {
            $userinfos->icon_url = $urlSchema . ":" . $avatarRootPath . "/" . $icon; //头像
        }

        if ($pwd) {
            $userinfos->password = $pwd;
        }

        $userinfos->userid = $uid; //uid
        //var_dump($userinfos);exit;
        $req->setUserinfos(json_encode($userinfos));
        $resp = $c->execute($req);
        //$resp = simplexml_load_string($resp);
        $resp = json_decode(json_encode($resp), true);
        //var_dump($resp);exit;
//        file_put_contents('/data/tmp/a.txt', json_encode($resp));
        if (isset($resp['uid_succ'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 删除用户
     */
    public function delUser($uid) {
        include "TopSdk.php";
        date_default_timezone_set('Asia/Shanghai');

        $c = new \TopClient;
        $c->appkey = '24496704';
        $c->secretKey = '955569aa494178b54ef468ea9a15f15d';

        $req = new \OpenimUsersDeleteRequest;

        $req->setUserids($uid);
        $resp = $c->execute($req);
        return true;
    }

    /**
     * 批量获取阿里百川信息
     */
    public function getImUserInfo($uid) {
        include "TopSdk.php";
        date_default_timezone_set('Asia/Shanghai');

        $c = new \TopClient;
        $c->appkey = '24496704';
        $c->secretKey = '955569aa494178b54ef468ea9a15f15d';

        $req = new \OpenimUsersGetRequest;

        $req->setUserids($uid);
        $resp = $c->execute($req);
        //$resp = simplexml_load_string($resp);
        $resp = json_decode(json_encode($resp), true);

        return $resp;
    }

    /**
     * 标准消息发送
     * @param type $from_user 消息发送者
     * @param type $to_users 消息接受者 ["user1","user2"]
     * @param type $context 发送的消息内容。0(文本消息):填消息内容字符串。1(图片):base64编码的jpg或gif文件。2(语音):base64编码的amr文件。8(地理位置):经纬度，格式如 111,222
     * @param type $msg_type 消息类型。0:文本消息。1:图片消息，只支持jpg、gif。2:语音消息，只支持amr。8:地理位置信息。
     */
    public function sendImMsgPush($from_user, $to_users, $context, $msg_type = 0) {
        include "TopSdk.php";
        date_default_timezone_set('Asia/Shanghai');

        $c = new \TopClient;
        $c->appkey = '24496704';
        $c->secretKey = '955569aa494178b54ef468ea9a15f15d';

        $req = new \OpenimImmsgPushRequest();
        $immsg = new \ImMsg;
        $immsg->from_user = '6273328146';
        $immsg->to_users = $to_users;
        $immsg->summary = $context;
        $immsg->data = 'push payload';
        $immsg->msg_type = $msg_type;
        $immsg->context = $context;

        $req->setImmsg(json_encode($immsg));
        $resp = $c->execute($req);
        return $resp;
    }

}
