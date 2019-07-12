<?php
namespace Lib\User;

class UserBase
{
    protected $userApi = null;

    public function __construct()
    {
        $this->userApi = get_api_client('User');
    }

    /**
     * 将请求转发到用户
     */
    protected function passRequest2User($params, $uri)
    {
        $this->userApi->chooseRequest($uri, 1)->setParams($params);
        $this->userApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
        $this->userApi->setHeader('X-Forwarded-URL', $this->getRequestUrl());
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            throw new \Exception\ServiceException($res->data, $res->code);
        }
        return $res->data;
    }


    /**
     * 将请求转发到用户
     * 仅供Cli脚本使用
     */
    protected function cliPassRequest2User($params, $uri)
    {
        $this->userApi->chooseRequest($uri, 1)->setParams($params);
        $this->userApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
        $res = $this->userApi->execRequest();
        if ($res->code != 200) {
            throw new \Exception\ServiceException($res->data, $res->code);
        }
        return $res->data;
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
}
