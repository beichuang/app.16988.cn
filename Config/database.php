<?php
// database config
return array(
    'mysql' => array(
        'bxd_app' => array(
            'host' => '192.168.1.214',
            'port' => '3306',
            'database' => 'jp_app',
            'username' => 'root',
            'password' => '123456',
            'prefix' => ''
        ),
        'bxd_pay_center' => array(
            'host' => '192.168.1.214',
            'port' => '3306',
            'database' => 'jp_api_pay_center',
            'username' => 'root',
            'password' => '123456',
            'prefix' => ''
        ),
        'bxd_mall_user' => array(
            'host' => '192.168.1.214',
            'port' => '3306',
            'database' => 'jp_api_mall_user',
            'username' => 'root',
            'password' => '123456',
            'prefix' => '',
        ),
        'bxd_user' => array(
            'host' => '192.168.1.214',
            'port' => '3306',
            'database' => 'jp_api_user',
            'username' => 'root',
            'password' => '123456',
            'prefix' => '',
        ),
        'bxd_mall_common' => array(
            'host' => '192.168.1.214',
            'port' => '3306',
            'database' => 'jp_api_mall_common',
            'username' => 'root',
            'password' => '123456',
            'prefix' => '',
        ),
        'annual_meeting' => array(
            'host' => '192.168.1.214',
            'port' => '3306',
            'database' => 'am_award',
            'username' => 'root',
            'password' => '123456',
            'prefix' => '',
        ),
    ),
    'redis' => array(
        'host' => '192.168.1.214',
        'port' => '6379',
    ),
// 'memcache' => array(
// 'host' => 'localhost',
// 'port' => 11211,
// ),
);
