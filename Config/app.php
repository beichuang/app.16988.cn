<?php

return array(
    'private_key' => 'h6eeded797aa2a8736ea90c09d8793c5',
    'mode' => 'development', //决定了默认日志级别， development:日志级别为\Framework\Log::DEBUG，product:日志级别为\Framework\Log::WARN
    'debug' => 1, //决定了debugbar是否显示，异常会不会被打印出来
    'now' => 0,// 0表示当前时间，你可以指定时间，如：'2016-02-04 12:00:00'
    'log' => array(
        'enabled' => 1,
    ),
    'fileCache' => array(
        'dir' => dirname(__DIR__) . '/Data/Cache/Runtime',
        'lifetime' => 8640000,
    ),
    'messagePush' => array(
        'appName' => 'jupai',
        'timeFormat' => 'Y年m月d',
    ),
    'limit' => array(
        'app' => array(
            //app最低版本
            'minVersionNum' => 0,
        ),
    ),
    'CDN' => array(
        'BASE_URL_RES' => "//cdn.16988.cn/res",
    ),
    'baseDomain' => 'jupai.xtest.tech',
    'request_url_schema_x_forwarded_proto_default' => 'https',
    'cookies' => array(
        'encrypt' => 0,
        'lifetime' => '20 minutes',
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => false,
        'secret_key' => 'CHANGE_ME',
    ),
    'session' => array(
        'session_id_name' => 'JPSESSID',
        'name_prefix' => 'jupai_app_',
        // 有效期(秒),200天
        'expire' => 17280000,
    ),
    'ftp' => array(
        'host' => '192.168.1.72',
        'ssl' => false,
        'port' => '21',
        'username' => 'attach',
        'password' => 'Ta5OQyAqJtMeFUcikrpo',
        'path' => array(
            //圈子图片
            'treasure' => array(
                'ftpPath' => 'common/user/avatar/jupai/image/treasure',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/image/treasure',
            ),
            'user_background' => array(
                'ftpPath' => 'common/user/avatar/jupai/user/background',
                'imgDomainPath' => '//192.168.1.214/attach/common/user/avatar/jupai/user/background',
            ),
            //用户认证图片
            'user_certification' => array(
                'ftpPath' => 'common/user/avatar/jupai/user/certification',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/user/certification',
            ),
            //商品图片
            'mall_goods_attr_images' => array(
                'ftpPath' => 'common/user/avatar/jupai/mall/goods/attrs/images',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/mall/goods/attrs/images',
            ),
            //广告图片
            'mall_ad_images' => array(
                'ftpPath' => 'common/user/avatar/jupai/mall/ad/images',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/mall/ad/images',
            ),
            'user_message_picture' => array(
                'ftpPath' => 'common/user/avatar/jupai/user/message/picture',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/user/message/picture',
            ),
            'mall_refund_images' => array(
                'ftpPath' => 'common/user/avatar/jupai/mall/refund/images',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/mall/refund/images',
            ),
            //头条图片
            'news_images' => array(
                'ftpPath' => 'common/user/avatar/jupai/news/images',
                'imgDomainPath' => '//192.168.1.214/attach/common/user/avatar/jupai/news/images',
            ),
            //拍品图片
            'mall_auction_images' => array(
                'ftpPath' => 'common/user/avatar/jupai/mall/auction/images',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar/jupai/mall/auction/images',
            ),
            //用户头像
            'user_avatar' => array(
                'ftpPath' => 'common/user/avatar/jupai/common/user/avatar',
                'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar',
            )
        ),
    ),
    'interface_common' => array(
        'common',
        'activity/other/activity_art_send_cms',//艺术大赛临时发短信
        'activity/other/everyDayVoteNum', //投票统计
        'user/common/third',
        'activity/other/award_receive', //1500奖品领取
        'user/common/login',
        'user/common/activityLogin',           //针对互动的登录
        'user/common/register',
        'user/common/getCaptcha',
        'user/common/sendCaptchaInfo',
        'user/common/sendCaptcha',          //公共发送验证码接口
        'user/common/findPasswordByPhone',
        'user/common/checkPhoneRegister', //检测手机号是否注册
        'user/common/userSign', //提交签约
        'user/common/uploadSignImages', //签约图片上传
        'user/common/wxMpReg', //微信公众号网页，微信用户绑定手机
        'user/common/getUserLicence', //获取机构营业执照
        'user/common/getSignUser', //获取签约用户
        'mall/activity/activity/lists', //商城活动列表
        'mall/activity/activity/details', //商城活动详情
        'mall/goods/item/query',
        'user/setting/getDefault',
        'mall/goods/item/secKillGoodsList',//秒杀列表
        'mall/goods/item/detail',
        'mall/goods/comment/lists',
        'mall/goods/category/search',
        'mall/goods/gratuity/lists',
        'news/news/newsInfo',
        'user/common/getCommonInfo',
        'mall/goods/item/getGoodsList',
        'mall/goods/item/getSeckillGoodsList', //秒杀商品列表
        'mall/goods/item/getSeckillTime', //秒杀时间
        'mall/goods/item/boxGoods',
        'mall/goods/item/getCanReceiveVoucher', //获取商品可领取优惠券
        'treasure/treasure/lists',
        'user/common/allSearchUser',
        'home/home/homeAll',            // 首页
        'home/search/all',            // 搜索全部
        'found/nearby/recommendArtist', // 艺术家推荐
        'found/nearby/recommendBodies', // 推荐机构
        'news/news/query',              // 头条
        'found/nearby/newArtist',       // 最新艺术家
        'user/visit/showLists',         // 粉丝来访列表
        'mall/goods/item/share_info',   // 商品分享
        'news/news/share_info',         // 资讯分享
        'home/home/recommendGoods',     // 精选
        'mall/goods/item/goodsCredential',  // 证书
        'mall/goods/item/getNewGoodsList',  // 新作
        'special',  //专题
        'home/home/handpickGoods',  //首页精选
        'news/category/lists',  //头条分类
        'mall/goods/category/lists',    //商品分类
        'mall/goods/category/getList',    //获取商品一级分类
        'mall/goods/category/getByParentId',    //获取分类下的子分类及子分类的商品
        'common/ad/lists',   //首页热点图区域
        'home/home/getGoodLists',    //热点区域的频道页


        'user/common/integralLists',   //用户积分记录
        'user/invite/invite',   //生成的邀请链接
//        'user/invite/lists',    //邀请记录
        'user/invite/add',      //邀请链接
        'treasure/treasure/activeTreasure',   //活跃圈友
        'treasure/topic/lists',
        'treasure/topic/recommendLists',
        'treasure/topic/detail',
        'news/news/hotNews',     //热门头条
        'user/award/lists',      //获奖经历列表
        'mall/goods/item/goodsDes',     //商品描述模板
        'mall/goods/category/treasureCategory',    //寻宝页面的分类列表
        'mall/goods/item/recommendGoods', //推荐的商品
        'mall/goods/item/ownShopGoods', //专区商品
        'common/ad/dialog',    //首页弹框广告
        'mall/goods/item/seeAgainQuery',  //看了又看
        'user/distribution/lists',   //我的分销店铺列表
        'news/news/classroom',   //经纪人课堂
//        'user/common/myInfo',    //个人信息
        'mall/user/address/lists',   //收货地址列表
        'mall/user/address/post', //新增收货地址
        'mall/user/address/getCode',   //获取省/市/区的类表
        'wx/wx',   //微信
        'user/common/checkPhone',   //检测手机号是否存在
        'user/common/thirdRegister',  //保险岛，注册
        'user/voucher/getAwardVoucher',  //分享代金券领取
        'mall/auction/item/getList',  //拍品列表
        'mall/auction/item/getHotList',  //首页热门拍品列表
        'mall/auction/item/similarRecommend',  //推荐4个类似商品
        'mall/auction/item/detail',  //拍品详情
        'mall/auction/bidrecord/getList',  //拍品出价记录
        'mall/auction/item/share', //拍品分享
        'mall/goods/item/cutGoodsList', //砍价
        'mall/goods/item/joinCutGoods', //砍价
        'mall/goods/item/joinHelpCutGoods', //砍价
        'mall/goods/item/getListsOpenidCut', //砍价
        'mall/goods/item/openIdAddress', //砍价用户填写的地址
        'mall/goods/item/getWxRecommendGoods', //微信底部推荐
        'mall/goods/item/getOwnUserGoods', //微信名家专区
        'mall/voucher/voucher/getShopVoucherList', //获取店铺优惠券列表
        'mall/voucher/voucher/getGoodsVoucherList', //获取商品优惠券列表
        'activity/index/getAllTotal', //获取活动统计数据
        'activity/index/getNewList', //获取最新参赛者列表
        'activity/index/getRankingList', //获取排行榜
        'activity/index/getContestantDetail', //获取参赛者详情
        'activity/index/addContestant', //新增参赛者
        'activity/index/mainPage', //活动主页
        'activity/index/detailPage', //活动详情页
        'activity/index/registerPage', //活动报名页
        'activity/index/vote', //投票
        'activity/index/save618Join', //618传统文化守护者
        'activity/index/exhiBition', //展会专题页
        'activity/index/signOnInit180701', //2018.7.1线下少儿书画比赛，签到页面初始化
        'activity/index/signOn180701', //2018.7.1线下少儿书画比赛，签到
        'activity/index/sortUsers180701', //2018.7.1线下少儿书画比赛，排选手编号
        'activity/index/usersNoSort180701', //2018.7.1线下少儿书画比赛，未排编号的选手
        'activity/index/jiazhuangGoods20180801',
        'activity/index/jiazhuang20180801',
        'activity/index/teachersDayGoods20180907',
        'activity/index/teachersDay20180907',
        'activity/index/nationalDayGoods201810',
        'activity/index/nationalDayWx201810',
        'activity/index/createBoardingPassImage', //生成机票接口
        'activity/index/createBoardingPassPage',  //生成机票页面
        'activity/index/interestingPosterPage',  //趣味海报页面
        'activity/index/interestingPosterImage', //生成趣味海报接口
        'activity/index/interestingCouplet', //生成趣味对联接口
        'activity/AnnualMeeting',//2018年会
        'activity/index/calligraphyPage', //书法打卡活动-主页面
        'activity/index/getCalligraphyList', //书法打卡活动-获取书法打卡情况
        'activity/index/getCalligraphyPreview', //书法打卡活动-预览
        'activity/index/addCalligraphy', //书法打卡活动-打卡保存
        'activity/index/getCalligraphyDetail', //书法打卡活动-打卡单次详情
        'activity/index/updateCalligraphyStatus', //书法打卡活动-更改打卡状态
        'wx/index/getOpenId', //微信获取openid
        'wx/index/getIsSubscribe', //是否关注公众号
        'wx/index/index', //微信获取openid
        'wx/MiniProgram/Culture/Home',//掌玩文化小程序首页
        'user/common/WxMinilogin',
        'user/common/WxMiniReg',
        'wx/miniprogram/distribution/home/getList',
        'wx/miniprogram/distribution/home/getUserInfo',
        'wx/miniprogram/distribution/goods/getShopGoodsList',
        'wx/miniprogram/distribution/goods/detail',
        'wx/miniprogram/index/getGoodsShareImage',
        'wx/miniprogram/index/getUserShopShareImage',
        'wx/miniProgram/distribution/home/getSpecialDistributionGoods',
        'user/common/getImgCaptcha',
        'mall/custom/custom/getCategory', //定制类别
        'mall/custom/custom/getList', //定制库列表
        'mall/custom/custom/getNewestList', //最新定制动态
        'mall/custom/custom/detail', //定制详情
        'mall/custom/custom/getCustomGoods', //定制投稿记录
        'mall/goods/category/getOwnShopCategorys',//自营商品分类
        'mall/goods/category/h5shop',
        'mall/goods/category/h5shopByCgid',
        'mall/goods/item/listsWxH5',
        'found/market/lists',//附近的市场
        'thirdparty/pay/notify',
        'user/artisn/lists',//匠心记
        'user/artisn/detail',
        'mall/order/pay/init',//支付

        'wx/miniProgram/index/getUserDInviteShareImage',   //图片分享
        'wx/miniprogram/distribution/goods/wxyszz_detail', //微信艺术转转小程序 商品详情
        /***-----火客之歌------**/
        'activity/song/chooseTeam',                           // 火客之歌总决赛 微信选票
        'activity/song/songConfig',                           //火客之歌  活动结束时间配置
        'activity/song/initActivity',                          //火客之歌 数据导入
        'activity/song/initMessage',                          //火客之歌 数据导入
        'user/common/generateCode',                            //随机验证码
        'user/common/isHaveJoin',                             // 是否生成0元活动
        /**------书法活动----------**/
        'activity/other/isHaveJoin',                          // 火客之歌总决赛 微信选票
        'activity/other/homePage',                            // 首页配置
        'activity/other/worksList',                           //首页 作品展示
        'activity/other/worksDetail',                         //作品详情
        'activity/other/cacheClear',                          //清除缓存
        'activity/other/detailPage',                          //分享活动页
        'activity/other/awardWork',                           //获奖页面接口
        'activity/other/voteNumberTen',                      //投票数量前10名
        'activity/other/matchTimeSet',                        //比赛时间设置
        'activity/other/mainPage',                           //比赛时间设置
        'activity/other/activityConfig',                     //活动信息配置（缓存）
        'activity/other/browseNum',                           //浏览量控制
        /***-----pc官网接口------**/
        //艺术头条
        'office/homePage/hotNews',                         //首页头条接口     头条模块
        'office/auxiliary/hotNewsDetail',                 //艺术头条详情
        'office/auxiliary/hotHeadlines',                  //热门头条
        //艺术人物
        'office/homePage/artist',                        //艺术头条
        'office/auxiliary/artistDetail',                 //艺术人物详情
        'office/auxiliary/artistList',                    //艺术家列表
        'office/auxiliary/hotArtistList',                 // 热门 艺术家人物
        //商城接口
        'office/homePage/mall',                            //艺术头条
        'office/auxiliary/hotGoods',                       //热门商品
        'office/auxiliary/mall',                           //商城模块加载
        //展览展会
        'office/homePage/exhibition',                       //展览展会
        'office/auxiliary/exhibitionDetail',                //展会详细信息
        'office/auxiliary/hotExhibition',                   //热门展会
        'office/exhibition/exhibitionList',                // 展览展会列表
        'office/exhibition/hotExhibition',                 //热门展览展会
        //文化场馆信息
        'office/homePage/venues',                          //文化场馆信息
        'office/auxiliary/venueCategoryCity',              //文化场馆信息
        'office/auxiliary/venueDetail',                     //文玩场馆详情
        //艺术圈子
        'office/articleCircle/index',                     //艺术圈子列表
        'office/articleCircle/activeFriend',             //活跃圈友
        //个人中心
       // 'office/exhibition/publishedExhibition',         //我发布的展会
       // 'office/exhibition/publishExhibition',            //发布展览展会
      //  'office/auxiliary/publishNews',                    //发布展览展会
        'office/auxiliary/newsCategory',                  //头条分类
       // 'office/auxiliary/myPublish',                     //头条分类(我的发布)
       // 'office/auxiliary/newsDetail',                    //发布头条详情
         'office/auxiliary/circleLists',                    //发布头条详情
         'office/web/link',                                 //link地址
         'office/web/headSearch'                          //头部搜索
    ),
    'accessSignCheck' => array(
        'secret' => 'FJHAFKDLR56EWF1SDA374FD83',
        'interface_common' => array(
            'html',
        ),
    ),
    'http_param_name' => array(
        // JSONP回调函数“参数名”
        //如？jsonp_callback=callback_func_15612，
        //客户端会得到"callback_func_15612({jsonObj});"
        'jsonp_callback' => 'callback',
        'appid' => 'appid',
    ),
    'queue_common_params' => ['appid' => 33333],

    //微信掌玩文化公众号
    'weChat' => [
        'appid' => "wx8fe6fefedfbd3f2e", //测试：wx3fdb7fe349973671， 正式：wx379a778de198a0fb
        // 微信申请成功之后邮件中的商户id
        'mch_id' => "1500193762",
        // 在微信商户平台上自己设定的api密钥 32位
        'api_key' => "6VsjpoY5DtuqIscVDm3vwd0Be6NnVpH3",
        // 通知地址
        'notify_url' => "https://app.16988.cn/thirdparty/pay/notify/wechat-jsapi",
        // 过期时间，秒
        'time_expire' => 864000,
        // 交易类型
        'trade_type' => 'JSAPI',
        'appSecret' => '32a6b6c6e5c2b6dd6816a21702692a0a',  //测试：bfe455eb8a184d532ed8e413249501cf， 正式：6e9ee634ed0a210e93b78052545bd0d3
        'cutSuccess'=> 'C_vCz2bOkmGnpWqr6S3hrm8kp7zYNnNL_32CXGVDC9o',
        'token'=>'fkjdafeKJs332HFdfD765df4s',
        'encodingaeskey'=>'8gixQ4a1b17ZJ9YkQIk5nNLT4GxgZSI4FMCz0zTx3Mt'
    ],
    'kefu_imId' => '6273328146',   //客服的imid
    'aliyun_oss' => [
        'accessKeyId' => 'LTAIRZf1zZhnbPqq',
        'accessKeySecret' => 'ClexgsYywqyRhhXUv3WEGFt9Td9uTt',
        'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
        'bucket' => 'zhangwan-picture-dev'
    ],
    //阿里云身份证实名认证AppCode
    //网站身份
    'website_name'=>'掌玩艺术网',
    'aliyun_idcard_appcode' =>'a23c992156be4b5084298391fc3addb9'

);
