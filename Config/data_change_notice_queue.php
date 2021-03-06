<?php
return array (
    'redis_queue_prefix'=>'jupai_api_mall_queue_Notice_Key_',
    'mainQueueFlag'=>'default_main',
    'stopMainQueue'=>0,
    'interface'=>array(
        'order'=>array(
            //主队列分发操作频率
            'frequency'=>200000,
            'workrRestart'=>array('03:00:00','03:01:00'),	
            'appMessagePushQueue'=>'appMessagePushQueueOrder',
        ),
        'goods'=>array(
            //主队列分发操作频率
            'frequency'=>200000,
            'workrRestart'=>array('03:00:00','03:01:00'),	
            'appMessagePushQueue'=>'appMessagePushQueueGoods',
        ),
        'user'=>array(
            //主队列分发操作频率
            'frequency'=>200000,
            'workrRestart'=>array('03:00:00','03:01:00'),   
            'redis_queue_prefix'=>'jupai_api_user_queue_User_Data_Change_Notice_Key_',
            'mainQueueFlag'=>'default_main',
            'stopMainQueue'=>1,
            'noticeApps'=>array(
            ),
        ),
        'goodsDiscuss'=>array(
            //主队列分发操作频率
            'frequency'=>200000,
            'workrRestart'=>array('03:00:00','03:01:00'),   
            'appMessagePushQueue'=>'appMessagePushQueueGoodsDiscuss',
        ),
        'treasureDiscuss'=>array(
            //主队列分发操作频率
            'frequency'=>200000,
            'workrRestart'=>array('03:00:00','03:01:00'),   
            'appMessagePushQueue'=>'appMessagePushQueueTreasureDiscuss',
        ),
        'custom'=>array(
            //主队列分发操作频率
            'frequency'=>200000,
            'workrRestart'=>array('03:00:00','03:01:00'),
            'appMessagePushQueue'=>'appMessagePushQueueCustom',
        ),
    ),
    );
