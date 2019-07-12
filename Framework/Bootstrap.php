<?php

require __DIR__.'/Core.php';

\Framework\Core::registerAutoloader();

//不生成全局app变量，phpunit测试时会报错
\Framework\Core::getSelf()
    ->config(config('app'))
     ->initContainer()
    ->initMiddleware();

//向容器中加入ftp类
\Framework\Core::getSelf()->container->singleton('ftp', function ($c) {
    $ftp = null;
    if (isset($c['settings']['ftp.host'])) {
        $ftp = new \FtpClient\FtpClient();
        $ftp->connect($c['settings']['ftp.host'], $c['settings']['ftp.ssl'], $c['settings']['ftp.port']);
        $ftp->login($c['settings']['ftp.username'], $c['settings']['ftp.password']);
    }
    return $ftp;
});
/**
 * 初始化配置Intervention\Image
 */
/*
Intervention\Image\ImageManagerStatic::configure(array('driver' => 'imagick'));
*/