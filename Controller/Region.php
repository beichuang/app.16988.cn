<?php
namespace Controller;

use Lib\Base\BaseController;

class Region extends BaseController
{

    protected $userApi = null;

    public function __construct()
    {
        parent::__construct();
        $this->userApi = get_api_client('User');
    }

    public function get()
    {
        throw new \Exception("无数据！");
        $data = app()->request()->params();
        if (! is_array($data)) {
            throw new \Exception("无数据！");
        }
        $res = array(
            "error_code" => "0",
            "error_msg" => "",
            "total" => count($data),
            "list" => $data
        );
        $this->responseJSON($res);
    }
}
