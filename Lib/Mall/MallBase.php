<?php
namespace Lib\Mall;

class MallBase
{

    protected $mallApi = null;

    public function __construct()
    {
        $this->mallApi = get_api_client('Mall');
    }

    /**
     * 将请求转发到商城
     */
    protected function passRequest2Mall($params, $uri)
    {
        $this->mallApi->chooseRequest($uri, 1)->setParams($params);
        $this->mallApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
        $this->mallApi->setHeader('X-Forwarded-URL', $this->getRequestUrl());
        $res = $this->mallApi->execRequest();
        if ($res->code != 200) {
            throw new \Exception\ServiceException($res->data, $res->code);
        }
        return $res->data;
    }


    /**
     * 将请求转发到商城
     * 仅供Cli脚本使用
     */
    protected function cliPassRequest2Mall($params, $uri)
    {
        $this->mallApi->chooseRequest($uri, 1)->setParams($params);
        $this->mallApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
        $res = $this->mallApi->execRequest();
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
