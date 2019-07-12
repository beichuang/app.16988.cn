<?php

return array(
    //头像地址前缀
    'imgDomainPath' => '//s29.xtest.tech/attach/common/user/avatar',
    'sysDomain' => 'api-sys.16988.cn',
    'avatar'=>array(
        'ftp_root_path'=>'common/user/avatar',
        //可选jpg,png,gif,目标头像的格式
        'target_image_type'=>'jpg',
        'size'=>array(
            'large'=>array(
                'width'=>100,
                'height'=>100,
                'name_suffix'=>'_l',
                'quality'=>100,
            ),
            'middle'=>array(
                'width'=>70,
                'height'=>70,
                'name_suffix'=>'_m',
                'quality'=>100,
            ),
            'small'=>array(
                'width'=>50,
                'height'=>50,
                'name_suffix'=>'_s',
                'quality'=>100,
            ),
        ),
        'qrcode'=>array(
            //Levels of error correction.   可选0,1,2,3
            'errorCorrectionLevel'=>2,
            //1~10
            'size'=>4,
            'margin'=>1,
            'name_suffix'=>'_qr',
            'quality'=>100,
        ),
    ),
    'uploadTempFolder' => dirname(__DIR__).'/Data/Temp/',
    'aliyun_oss' => [
        'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
        'bucket' => 'zhangwan-picture-dev'
    ],
    'smsText' =>[
        'url' => 'http://114.55.141.65/msg/HttpBatchSendSM',
        'account' => 'xjds88',
        'password' => '',
    ]
);
