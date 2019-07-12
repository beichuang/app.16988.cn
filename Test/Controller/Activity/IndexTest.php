<?php

// 活动管理

 class IndexTest extends \Test\BaseTestCase
{
    /**
     * 首页
     */
    public function testjiazhuang20180801()
    {
//        $openid='fewfds';
//        $secret=config('app.weChat.appSecret');
//        $this->curl->setCookie('openid',$openid);
//        $this->curl->setCookie('openid_check',md5($openid.$secret));

        $res=$this->curlPost('activity/index/jiazhuang20180801');
        $this->assertEquals(302,$this->curl->httpStatusCode);
    }
    /**
     * 首页
     */
    public function testmainPage()
    {
        $res=$this->curlPost('activity/index/mainPage');
        $this->assertEquals(302,$this->curl->httpStatusCode);
//        $openId = WxHelper::getOpenId();
//        if (!empty($openId)) {
//            //增加活动访问量
//            $this->updateTotalView();
//            app()->redirect(self::MAIN_PAGE);
//        }
    }

    /**
     * 参赛者详情页
     */
    public function testdetailPage()
    {
        $params['id']=1;
        $res=$this->curlPost('activity/index/detailPage',$params);
        $this->assertEquals(302,$this->curl->httpStatusCode);
    }

    /**
     * 报名页面
     */
    public function testregisterPage()
    {
        $res=$this->curlPost('activity/index/registerPage');
        $this->assertEquals(302,$this->curl->httpStatusCode);
    }

    /**
     * 获取首页统计数据
     */
    public function testgetAllTotal()
    {
        $res=$this->curlPost('activity/index/getAllTotal');
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 获取最新榜单
     */
    public function testgetNewList()
    {
        $params['page']=1;
        $params['pageSize']=10;
        $res=$this->curlPost('activity/index/getNewList',$params);
        $this->assertEquals(0,$res->error_code);
        $this->assertGreaterThanOrEqual(0,$res->data->count);
    }

    /**
     * 获取排行榜
     */
    public function testgetRankingList()
    {
        $params['page']=1;
        $params['pageSize']=10;
        $res=$this->curlPost('activity/index/getRankingList',$params);
        $this->assertEquals(0,$res->error_code);
        $this->assertGreaterThanOrEqual(0,$res->data->count);
    }

    /**
     * 获取参赛者详情信息
     */
    public function testgetContestantDetail()
    {
        $params['id']=1;
        $res=$this->curlPost('activity/index/getContestantDetail',$params);
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 投票
     */
    public function testvote()
    {
        $params['id']=1;
        $res=$this->curlPost('activity/index/vote',$params);
        $this->assertEquals(0,$res->error_code);
        return;
    }

    /**
     * @Summary :添加用户
     * @Author yyb update at 2018/6/4 9:37
     */
    public function testaddContestant()
    {
        $params['ac_name']='test';
        $params['ac_phone']='test';
        $params['ac_sex']='test';
        $params['ac_age']='test';
        $params['ac_category']='test';
        $params['ac_img']=json_encode(['1','2']);
        $params['ac_openid']='12345678980';

        $res=$this->withWxClient()->curlPost('activity/index/addContestant',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals('',$res->error_msg);
        $this->assertEquals(0,$res->error_code);
        return;
    }

    /**
     * 线下比赛签到页面初始化
     */
    public function testsignOnInit180701()
    {
        $res=$this->curlPost('activity/index/signOnInit180701');
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals('',$res->error_msg);
        $this->assertEquals(0,$res->error_code);
    }
    /**
     * 线下比赛签到页面
     */
    public function testsignOn180701()
    {
        $params['user_ids']='1,2,34,5,6,7,8';
        $res=$this->curlPost('activity/index/signOn180701',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals('success',$res->error_msg);
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 未排号的选手
     */
    public function testusersNoSort180701()
    {
        $res=$this->curlPost('activity/index/usersNoSort180701');
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }
    /**
     * 给选手排号
     */
    public function testsortUsers180701()
    {
        $params['user_ids']='[1,2,3,4,5]';
        $params['category']='1';
        $res=$this->curlPost('activity/index/sortUsers180701',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }


    public function testsave618Join()
    {

        $params['type']='1';
        $res=$this->curlPost('activity/index/save618Join',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        $this->assertGreaterThanOrEqual(0,$res->data->userCount);
    }

    /**
     * 展会专题页
     */
    public function testexhiBition()
    {
        $params['type']='1';
        $res=$this->curlPost('activity/index/exhiBition',$params);
        $this->assertEquals(302,$this->curl->httpStatusCode);
        $this->assertEquals('https://app.16988.cn/html/apph5/exhiBition.html#/index?id=1',$this->curl->responseHeaders['location']);
    }


    /**
     * 家装专题商品列表
     * @throws ParamsInvalidException
     */
    public function testjiazhuangGoods20180801()
    {
        $params['scene']='1';
        $params['category']='1';
        $res=$this->curlPost('activity/index/jiazhuangGoods20180801',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        return;
    }

}
