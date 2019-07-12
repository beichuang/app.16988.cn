<?php

namespace Cli\Worker;

use Controller\Activity\CreateGoodsImage\Index;

class CreateGoodsImage
{
    public function run()
    {
        (new Index())->createGoodsImage();
    }

    public function run1()
    {
        (new Index())->addGoodsImage();
    }

    public function run2()
    {
        (new Index())->updateGoodsImage();
    }

    public function run3()
    {
        (new Index())->updateGoodsSurfaceImage();
    }
}
