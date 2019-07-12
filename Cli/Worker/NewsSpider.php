<?php
/* Do NOT delete this comment */
/* 不要删除这段注释 */
/**
 *
 * 新闻爬虫
 */
namespace Cli\Worker;

use phpspider\core\phpspider;
use phpspider\core\requests;
use phpspider\core\selector;

//register_shutdown_function(function(){
//    $error=error_get_last();
//    if ($error && is_array($error)){
//        var_dump($error);
//        exit(1);
//    }
//});

class NewsSpider
{
    private $db_config=[
        'host'=>'192.168.1.71',
        'port'=>'3306',
        'user'=>'root',
        'pass'=>'root',
        'name'=>'jp_app',
    ];
    private $export_config=[
        'type'=>'db',
        'table'=>'news',
    ];
    private $log_file='phpspider.log';
    /**
     * @var phpspider
     */
    private $spider =null;
    public function __construct()
    {
        $BASE_PATH=dirname(dirname(__DIR__));
        require_once $BASE_PATH.'/Framework/Bootstrap.php';
        require_once $BASE_PATH.'/Framework/Helper/Fun.php';
        require_once $BASE_PATH."/vendor/autoload.php";
        $log_dir=$BASE_PATH.'/Data/Logs/'.date('Ymd');
        if(!is_dir($log_dir)){
            mkdir($log_dir);
            chmod($log_dir,0777);
        }
        $this->log_file=$log_dir.'/phpspider.log';
        if(app()->getMode()=='product'){
            $this->db_config=[
                'host'=>'rm-bp1s5q18ykysow250.mysql.rds.aliyuncs.com',
                'port'=>'3306',
                'user'=>'app',
                'pass'=>'j0H4tEliODORTTzjmK3Y',
                'name'=>'app',
            ];
        }
    }


    /**
     * 中国文化艺术网-资讯
     */
    public function runOrgccZixun()
    {
        $configs = array(
            'name' => '中国文化艺术网-资讯',
            'domains' => array(
                'www.orgcc.com',
            ),
            'scan_urls' => array(
                "http://www.orgcc.com/news/list10.html",
                "http://www.orgcc.com/news/list2.html",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.orgcc\.com\/news\/\d+\.html",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.orgcc\.com\/news\/list10\.html$",
                "http:\/\/www\.orgcc\.com\/news\/list2\.html$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='ntitle']",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='artibody']",
                    'required' => true,
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $this->spider=$spider;
        $spider->on_list_page=function ($page,$content,$spider){
            $list = selector::select($content, "//div[contains(@class,'fitem3')]/div[@class='feeditem']");
            foreach ($list as $row){
                if(strpos(selector::select($row, "//div[@class='feedinfo']/span"),date('小时前'))!==false){
                    $url=selector::select($row, "//h2/a/@href");
                    $status=$spider->add_url($url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=trim($data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    /**
     * 收藏界-社会新闻
     */
    public function runCangworldNews()
    {
        $today=date('Y-m-d');
        $configs = array(
            'name' => '收藏界-社会新闻',
            'domains' => array(
                'www.cangworld.com',
            ),
            'scan_urls' => array(
                "http://www.cangworld.com/sczx/shxw/",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.cangworld\.com\/sczx\/shxw\/{$today}\/\d+\.html",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.cangworld\.com\/sczx\/shxw\/?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@id='content']/h1[position()=1]",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='content']/div[@class='news']",
                    'required' => true,
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $this->spider=$spider;
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=trim($data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }


    /**
     * 收藏界-焦点资讯
     */
    public function runCangworldZhanxun()
    {
        $today=date('Ymd');
        $configs = array(
            'name' => '收藏界-焦点资讯',
            'domains' => array(
                'www.cangworld.com',
            ),
            'scan_urls' => array(
                "http://www.cangworld.com/sczx/",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.cangworld\.com\/sczx\/cjdt/{$today}\/\d+\.html$",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.cangworld\.com\/sczx\/?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@id='content']/h1[position()=1]",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='content']/p[@class='MsoNormal']",
                    'required' => true,
                    'repeated'=>true,
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $this->spider=$spider;
        $spider->on_list_page=function ($page,$content,$spider){
            $list = selector::select($content, "//div[@class='col_c']/ul[@class='blist' and position()=1]/li/a/@href");
            foreach ($list as $href){
                $status=$spider->add_url($href,[
                    'url_type' => 'content_page'
                ]);
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=trim($data['n_title']);
            $content='';
            foreach ($data['n_content'] as $c){
                $content.="<p>{$c}</p>";
            }
            $data['n_content']=$content;
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    /**
     * 人民网书画频道拍卖
     */
    public function runPeopleArtPaimai()
    {
        $year=date('Y');
        $md=date('md');
        $configs = array(
            'name' => '人民网拍卖',
            'domains' => array(
                'art.people.com.cn',
            ),
            'scan_urls' => array(
                "http://art.people.com.cn/GB/206244/356130/index.html",
            ),
            'content_url_regexes' => array(
                "http:\/\/art\.people\.com\.cn\/n1\/{$year}\/{$md}\/c226026-\d+\.html",
            ),
            'list_url_regexes' => array(
                "http:\/\/art\.people\.com\.cn\/GB\/206244\/356130\/index\.html$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[contains(@class,'text_title')]/h1",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='rwb_zw']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='2';
            $data['n_type']='2';
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }
    /**
     * 人民网书画频道资讯
     */
    public function runPeopleArtZixun()
    {
        $year=date('Y');
        $md=date('md');
        $configs = array(
            'name' => '人民网资讯',
            'domains' => array(
                'art.people.com.cn',
            ),
            'scan_urls' => array(
                "http://art.people.com.cn/GB/226026/index.html",
            ),
            'content_url_regexes' => array(
                "http:\/\/art\.people\.com\.cn\/n1\/{$year}\/{$md}\/c226026-\d+\.html",
            ),
            'list_url_regexes' => array(
                "http:\/\/art\.people\.com\.cn\/GB\/226026\/index\.html$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[contains(@class,'text_title')]/h1",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='rwb_zw']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }


    /**
     * 中国艺术品网-展讯
     */
    public function runCnartsZhanxun()
    {
        $configs = array(
            'name' => '中国艺术品网-展讯',
            'domains' => array(
                'www.cnarts.net',
            ),
            'scan_urls' => array(
                "http://www.cnarts.net/cweb/news/news_list.asp?kind=%D5%B9%C0%C0",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.cnarts\.net\/cweb\/news\/read\.asp\?kind=%D5%B9%C0%C0&id=\d+",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.cnarts\.net\/cweb\/news\/news_list\.asp\?kind=%D5%B9%C0%C0$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='read_news_content_title']/h2",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@class='read_news_content1']",
                    'required' => true,
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
            'input_encoding'=>'gb2312',
        );
        $spider = new \phpspider\core\phpspider($configs);
        $this->spider=$spider;
        $spider->on_list_page=function ($page,$content,$spider){
            $host=$spider->get_config('domains')[0];
            $list = selector::select($content, "//div[@class='list_in_er']/ul/li");
            foreach ($list as $row){
                if(strpos(selector::select($row, "//span"),date('n月j日'))!==false){
                    $url=selector::select($row, "//a/@href");
                    $status=$spider->add_url('http://'.$host.'/cweb/news/'.$url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='3';
            $data['n_type']='3';
            $data['n_title']=trim($data['n_title']);
            $data['n_content']=is_array($data['n_content'])?implode(' ',$data['n_content']):$data['n_content'];
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    /**
     * 中国艺术品网-拍卖
     */
    public function runCnartsPaimai()
    {
        $configs = array(
            'name' => '中国艺术品网-拍卖',
            'domains' => array(
                'www.cnarts.net',
            ),
            'scan_urls' => array(
                "http://www.cnarts.net/cweb/news/news_list.asp?kind=%C5%C4%C2%F4",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.cnarts\.net\/cweb\/news\/read\.asp\?kind=%C5%C4%C2%F4&id=\d+",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.cnarts\.net\/cweb\/news\/news_list\.asp\?kind=%C5%C4%C2%F4$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='read_news_content_title']/h2",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@class='read_news_content_title_aa'] | //div[@class='read_news_content1']",
                    'required' => true,
                    'repeated'=>true,
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
            'input_encoding'=>'gb2312',
        );
        $spider = new \phpspider\core\phpspider($configs);
        $this->spider=$spider;
        $spider->on_list_page=function ($page,$content,$spider){
            $host=$spider->get_config('domains')[0];
            $list = selector::select($content, "//div[@class='list_in_er']/ul/li");
            foreach ($list as $row){
                if(strpos(selector::select($row, "//span"),date('n月j日'))!==false){
                    $url=selector::select($row, "//a/@href");
                    $status=$spider->add_url('http://'.$host.'/cweb/news/'.$url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='2';
            $data['n_type']='2';
            $data['n_title']=trim($data['n_title']);
            $data['n_content']=implode(' ',$data['n_content']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }


    /**
     * 博宝资讯
     */
    public function runNewsArtxun()
    {
        $configs = array(
            'name' => '博宝资讯',
            'domains' => array(
                'news.artxun.com',
            ),
            'scan_urls' => array(
                "http://news.artxun.com/",
            ),
            'content_url_regexes' => array(
                "http:\/\/news\.artxun\.com\/\d+\.shtml",
            ),
            'list_url_regexes' => array(
                "http:\/\/news\.artxun\.com\/?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='info_page_cont']/h1[@class='title']",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@class='info_page_cont']/div[@class='bio']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_list_page=function ($page,$content,$spider){
            $host=$spider->get_config('domains')[0];
            $list = selector::select($content, "//div[@class='indexblock']/dl/dd");
            foreach ($list as $row){
                if(strpos(selector::select($row, "//div[@class='news_c_function']/div[1]/span[2]"),date('Y-m-d'))!==false){
                    $url=selector::select($row, "//a[@class='title']/@href");
                    $status=$spider->add_url('http://'.$host.'/'.$url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            if(date('Ymd',strtotime(trim(selector::select($page['raw'],"//div[@class='time']/span[@class='show_time']"))))!=date('Ymd'))return false;
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=str_replace('【博宝·资讯】','',trim($data['n_title']));
            $data['n_content']=selector::remove($data['n_content'],"/p/img[contains(@src,'@648w_1e_1c')]");
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }


    /**
     * 华夏收藏网-热门资讯
     */
    public function runNewsCangHotNews()
    {
        $configs = array(
            'name' => '华夏收藏网-热门资讯',
            'domains' => array(
                'news.cang.com',
            ),
            'scan_urls' => array(
                "http://news.cang.com/",
            ),
            'content_url_regexes' => array(
                "http:\/\/news\.cang\.com\/infos\/\d+/\d+\.html",
            ),
            'list_url_regexes' => array(
                "http:\/\/news\.cang\.com\/?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//dl[@id='box_content']/dt/h1",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//dd[@id='main_content']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_list_page=function ($page,$content,$spider){
            $list = selector::select($content, "//div[contains(@class,'main')]/div[contains(@class,'warp_box')][2]/div[3]/ul/li/a/@href");
            if(!is_array($list))return false;
            foreach ($list as $url){
                $status=$spider->add_url($url,[
                    'url_type' => 'content_page'
                ]);
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=trim($data['n_title']);
            if(date('Ymd',strtotime(trim(selector::select($page['raw'],"//dl[@id='box_content']/dt/h2/span[1]"))))!=date('Ymd'))return false;
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    /**
     * 华夏收藏网-拍卖
     */
    public function runNewsCangPaimai()
    {
        $configs = array(
            'name' => '华夏收藏网-拍卖',
            'domains' => array(
                'news.cang.com',
            ),
            'scan_urls' => array(
                "http://news.cang.com/info/list-7.html",
            ),
            'content_url_regexes' => array(
                "http:\/\/news\.cang\.com\/infos\/\d+/\d+\.html",
            ),
            'list_url_regexes' => array(
                "http:\/\/news\.cang\.com\/info\/list-7\.html",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//dl[@id='box_content']/dt/h1",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//dd[@id='main_content']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_list_page=function ($page,$content,$spider){
            $host=$spider->get_config('domains')[0];
            $list = selector::select($content, "//div[@class='newslist']/ul/li");
            foreach ($list as $row){
                if(strpos(selector::select($row, "//span"),date('m月d日'))!==false){
                    $url=selector::select($row, "//a/@href");
                    $status=$spider->add_url($url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='2';
            $data['n_type']='2';
            $data['n_title']=trim($data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }


    /**
     * 中国大美书画网
     */
    public function runChinafpo()
    {
        $configs = array(
            'name' => '中国大美书画网',
            'domains' => array(
                'www.chinafpo.com',
            ),
            'scan_urls' => array(
                "http://www.chinafpo.com/",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.chinafpo\.com\/View\/ContentViewPage\.aspx\?aID=\d+",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.chinafpo\.com\/",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//span[@id='Label_Title']",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='div_Content']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_list_page=function ($page,$content,$spider){
            $host=$spider->get_config('domains')[0];
            $url1 = selector::select($content, "//div[@id='div_TopShishidongtai']/h4/a/@href");
            $status=$spider->add_url('http://'.$host.$url1,[
                'url_type' => 'content_page'
            ]);

            $list2 = selector::select($content, "//div[@id='div_DongtaiList']/ul/li");
            foreach ($list2 as $row){
                if(selector::select($row, "//a/em")!='[征稿启事]'){
                    $url=selector::select($row, "//a/@href");
                    $status=$spider->add_url('http://'.$host.$url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=trim($data['n_title']);
            if(date('Y-m-d')!=selector::select($page['raw'], "//span[@id='Label_Date']")){
                return false;
            }
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    /**
     * 中华古玩网-资讯
     */
    public function runGucnZixun()
    {
        $configs = array(
            'name' => '中华古玩网-资讯',
            'domains' => array(
                'www.gucn.com',
            ),
            'scan_urls' => array(
                "http://www.gucn.com/Info_ArticleList_More.asp?Id=3",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.gucn\.com\/Info_ArticleList_Show\.asp\?Id=\d+",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.gucn\.com\/Info_ArticleList_More\.asp\?Id=3$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@id='detail_left_a1']/p[@class='detail_a1_title']",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@id='article']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
            'max_depth'=>0,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_list_page=function ($page,$content,$spider){
            $list = selector::select($content, "//div[@class='marketfocus_more_list']/ul/li");
            $today=date('Y-m-d');
            foreach ($list as $row){
                if(selector::select($row, "//span")==$today){
                    $url=selector::select($row, "//a/@href");
                    $status=$spider->add_url('http://www.gucn.com'.$url,[
                        'url_type' => 'content_page'
                    ]);
                }
            }
            return false;
        };
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=trim($data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    /**
     * 盛世收藏-拍卖
     */
    public function runSsscPaimai()
    {
        $monthDay=date('Ymd');
        $configs = array(
            'name' => '盛世收藏-拍卖',
            'domains' => array(
                'www.sssc.cn',
            ),
            'scan_urls' => array(
                "http://www.sssc.cn/b/news/auction/auction-news/archive/",
            ),
            'content_url_regexes' => array(
                "http:\/\/www\.sssc\.cn\/a\/{$monthDay}\/\d+\.shtml",
            ),
            'list_url_regexes' => array(
                "http:\/\/www\.sssc\.cn\/b\/news\/auction\/auction-news\/archive\/?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='text_cont_lef']/div[contains(@class,'text_cont_l_tit')]",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[@class='text_cont_l_wenmain']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_scan_page=function (){return false;};
        $spider->on_content_page=function (){return false;};

        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='2';
            $data['n_type']='2';
            $data['n_title']=trim($data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }
    /**
     * 雅昌-资讯
     */
    public function runArtronZixun()
    {
        $monthDay=date('Ymd');
        $configs = array(
            'name' => '雅昌-资讯',
            'domains' => array(
                'news.artron.net',
            ),
            'scan_urls' => array(
                "https://news.artron.net/",
            ),
            'content_url_regexes' => array(
                "https:\/\/news\.artron\.net\/{$monthDay}\/n\d+(_\d+)?\.html",
            ),
            'list_url_regexes' => array(
                "https:\/\/auction\.artron\.net\/morenews\/list(728|734|731|729|732|730)(/p[23])?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='caption']/h1[@class='title']",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[contains(@class,'newsContentDetail')]",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            $data['n_title']=preg_replace('/【.+】/','',$data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }
    /**
     * 雅昌-拍卖
     */
    public function runArtronPaimai()
    {
        $monthDay=date('Ymd');
        $configs = array(
            'name' => '雅昌-拍卖',
            'domains' => array(
                'auction.artron.net',
            ),
            'scan_urls' => array(
                "https://auction.artron.net/morenews/list16",
            ),
            'content_url_regexes' => array(
                "https:\/\/auction\.artron\.net\/{$monthDay}\/n\d+(_\d+)?\.html",
            ),
            'list_url_regexes' => array(
                "https:\/\/auction\.artron\.net\/morenews\/list16(\/)?$",
            ),
            'fields' => array(
                [
                    'name' => "n_title",
                    'selector' => "//div[@class='caption']/h1[@class='title']",
                    'required' => true
                ],[
                    'name' => "n_content",
                    'selector' => "//div[contains(@class,'newsContentDetail')]",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='2';
            $data['n_type']='2';
            $data['n_title']=preg_replace('/【.+】/','',$data['n_title']);
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }
    /**
     * 新浪收藏-拍卖
     */
    public function runSinaCollectPaimai()
    {
        $monthDay=date('Y-m-d');
        $configs = array(
            'name' => '新浪收藏-拍卖',
            'domains' => array(
                'collection.sina.com.cn',
                'roll.collection.sina.com.cn',
            ),
            'scan_urls' => array(
                "http://collection.sina.com.cn/auction/",
            ),
            'content_url_regexes' => array(
                "http:\/\/collection\.sina\.com\.cn\/auction\/(pcdt|pmgg|hqgc|ppsx|zjgd)\/{$monthDay}\/doc-\w+\.shtml",
                "http:\/\/collection\.sina\.com\.cn\/{$monthDay}\/doc-\w+\.shtml"
            ),
            'list_url_regexes' => array(
                "http:\/\/roll\.collection\.sina\.com\.cn\/collection_1\/pmzx\/(pcdt|pmgg|xqgc|ppsx|zjgd1)\/index\.shtml",
            ),


            'fields' => array(
                [
                    // 抽取内容页的文章标题
                    'name' => "n_title",
                    'selector' => "//h1[@id='main_title']",
                    'required' => true
                ],[
                    // 抽取内容页的文章内容
                    'name' => "n_content",
                    'selector' => "//div[@id='artibody' and @class='content']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='2';
            $data['n_type']='2';
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }
    /**
     * 新浪收藏-资讯
     */
    public function runSinaCollectZixun()
    {
        $monthDay=date('Y-m-d');
        $configs = array(
            'name' => '新浪收藏-资讯',
            'domains' => array(
                'collection.sina.com.cn',
                'roll.collection.sina.com.cn',
            ),
            'scan_urls' => array(
                //
                "http://collection.sina.com.cn/",
                //红木
                "http://collection.sina.com.cn/hmjj/",
                //雕塑
                "http://collection.sina.com.cn/ds/",
                //书画
                "http://collection.sina.com.cn/zgsh/",
                //紫砂
                "http://collection.sina.com.cn/zisha/",
            ),
         'content_url_regexes' => array(
                //红木
                "http://collection.sina.com.cn/jjhm/(yjzx|hmbk|hmrw)/{$monthDay}/doc-\w+.shtml",
                //雕塑
                "http://collection.sina.com.cn/ds/(dsj|ggys|jgtj|zxzl|dsds)/{$monthDay}/doc-\w+.shtml",
                //书画
                "http://collection.sina.com.cn/zgsh/(mjdp|jgxx|shgd)/{$monthDay}/doc-\w+.shtml",
                //紫砂
                "http://collection.sina.com.cn/zisha/(yjzx|zsbk|zsrw)/{$monthDay}/doc-\w+.shtml",
                //其他
                "http://collection.sina.com.cn/(yxys|cqty|zwyp|qbtd|tqfx|yhds|wwzx|lfx|cqyw|cpsc|zlxx|hwdt|yjjj|jczs2|cjrw1)/{$monthDay}/doc-\w+.shtml",
            ),
            'list_url_regexes' => array(
                //红木
                "http:\/\/roll\.collection\.sina\.com\.cn\/collection_1\/jjhm\/(yjzx1|hmbk|hmrw)\/index\.shtml$",
                //雕塑
                "http:\/\/interface.sina.cn\/collection\/pc_ds_list_index.d.html?cid=(dsj|ggys|jgtj|zxzl|dsds)$",
                //书画
                "http:\/\/interface.sina.cn\/collection\/pc_shpd_yjzx_list_index.d.html?cid=(mjdp|jgxx|shgd)$",
                //紫砂
                "http:\/\/roll.collection.sina.com.cn\/collection_1\/zspd1\/(yjzx1|zsbk|zsrw)\/index\.shtml$",
                //影像、瓷器、邮票、钱币、铜器、油画、文玩、评论、人物、藏趣、鉴藏、藏品、展览、海外动态 、业界聚焦
                "http:\/\/roll.collection.sina.com.cn\/collection\/(yxys|cqty|zwyp|qbtd|tqfx|yhds|wwzx|lfx|cqyw|cpsc|zlxx|hwdt|yjjj|jczs2|cjrw1)\/index\.shtml$",
            ),
            'fields' => array(
                [
                    // 抽取内容页的文章标题
                    'name' => "n_title",
                    'selector' => "//h1[@id='main_title']",
                    'required' => true
                ],[
                    // 抽取内容页的文章内容
                    'name' => "n_content",
                    'selector' => "//div[@id='artibody' and @class='content']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            return $this->news_spider_on_extract_page($page,$data);
        };
        $spider->start();
    }

    public function run99ys()
    {
        $year=date('Y');
        $monthDay=date('md');
        $configs = array(
            'name' => '99艺术网',
            'domains' => array(
                'news.99ys.com'
            ),
            'scan_urls' => array(
                'http://news.99ys.com/'
            ),
            'content_url_regexes' => array(
                "http:\/\/news\.99ys\.com\/news\/{$year}\/{$monthDay}\/\d+_\d+_\d+\.shtml",
            ),
            'list_url_regexes' => array(
                "http:\/\/news\.99ys\.com\/index\.php\?m=content&c=index&a=lists&catid=\d+$",
            ),
            'fields' => array(
                [
                    // 抽取内容页的文章标题
                    'name' => "n_title",
                    'selector' => "//div[@class='article_title']",
                    'required' => true
                ],[
                    // 抽取内容页的文章内容
                    'name' => "n_content",
                    'selector' => "//div[@class='article']",
                    'required' => true
                ]
            ),
            'log_show'=>false,
            'log_file'=>$this->log_file,
            'export'=>$this->export_config,
            'user_agent'=>\phpspider\core\phpspider::AGENT_PC,
            'db_config'=>$this->db_config,
        );
        $spider = new \phpspider\core\phpspider($configs);
        $spider->on_extract_page = function ($page,$data){
            $data['nc_id']='1';
            $data['n_type']='1';
            return $this->news_spider_on_extract_page($page,$data);
        };
//        $spider->on_handle_img= function ($fieldname,$img){return $this->news_spider_on_handle_img($fieldname,$img);};
        $spider->start();
//        $url = "http://news.99ys.com/news/2018/0803/9_212884_1.shtml";
//        $url = "http://news.99ys.com/news/2018/0806/20_212901_1.shtml";
//        $html = requests::get($url);
//var_dump($html);exit;
// 抽取文章标题
//        $selector = "//div[contains(@class,'article_title')]";
//        $title = selector::select($html, $selector);
//echo $title;exit;
// 抽取文章内容
//        $selector = "//div[@class='article']";
//        $content = selector::select($html, $selector);
//        var_dump($content);exit;
// 抽取文章图片
//        $selector = "//div[@class='article']//img";
//        $content = selector::select($html, $selector);
//        var_dump($content);exit;
    }

    /**
     * @param $page
     * @param $data
     * @return bool
     */
    private function news_spider_on_extract_page($page,$data)
    {
        $dataDefault=[
            'n_from'=>'',
            'nc_id'=>0,
            'n_picurl'=>'',
            'n_type'=>0,
            'n_update_date'=>date('Y-m-d H:i:s'),
            'n_create_date'=>date('Y-m-d H:i:s'),
            'n_status'=>-1,
        ];
        $data=array_merge($dataDefault,$data);
        //是否存在
        $row=\phpspider\core\db::get_one("select n_id from news where n_title='{$data['n_title']}'");
        if($row){
            return false;
        }
        $data['n_content']=$this->parseContent($data['n_content']);
        $pageUrl=$page['url'];
        list($content,$images)=$this->processContentImages($data['n_content'],$pageUrl);
        $data['n_content']=$content;
        //头条默认初始点击量（1到500之间）
        $data['n_default_click_rate'] = rand(1, 500);
        
        if($n_id=\phpspider\core\db::insert('news',$data)){
//            if($images){
//                $i=0;
//                $news_images=array_map(function($img)use($i,$n_id){return [
//                    'n_id'=>$n_id,
//                    'ni_img'=>$img['path'],
//                    'ni_sort'=>++$i,
//                    'ni_width'=>$img['width'],
//                    'ni_height'=>$img['height'],
//                ];},$images);
//                \phpspider\core\db::insert_batch('news_image',$news_images);
//            }
        }
        return false;
    }
    private function parseContent($content)
    {
        $m_AllowAttr = array('src');
        $m_AllowTag = array('a', 'img', 'br', 'strong', 'b', 'code', 'pre', 'p', 'div', 'em', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'ul', 'ol', 'tr', 'th', 'td', 'hr', 'li', 'u');

        $xss = new \Framework\Lib\XssHtml($content,'utf-8',$m_AllowTag,$m_AllowAttr);
        $html = $xss->getHtml();
        return $html;
    }

    /**
     * @param $content
     * @return array
     */
    private function processContentImages($content,$fromPageUrl)
    {
        $selector = "//img";
        $images = selector::select($content, $selector);
        $images_new=[];
        if($images){
            if(is_array($images)){
                foreach ($images as $img){
                    if($new_url_info=$this->saveImage($img,$fromPageUrl)){
                        $content=str_replace($img,$new_url_info['url'],$content);
                        $images_new[]=$new_url_info;
                    }
                }
            }else if (is_string($images)){
                if($new_url_info=$this->saveImage($images,$fromPageUrl)){
                    $content=str_replace($images,$new_url_info['url'],$content);
                    $images_new[]=$new_url_info;
                }
            }
        }
//        \phpspider\core\log::info(''.json_encode([$images,$images_new]));
        return [$content,$images_new];
    }
    private function saveImage($imageUrl,$fromPageUrl)
    {
        if(preg_match('@^https?:\/\/[^/?#]+@i',$imageUrl)){
            $imageUrl=$imageUrl;
        }else if(preg_match('@^\/.+@',$imageUrl)){
            if(preg_match("@^https?:\/\/[^/?#]+@i",$fromPageUrl,$m)){
                $imageUrl=$m[0].$imageUrl;
            }else{
                return false;
            }
        }else{
            $imageUrl=preg_match('@\/$@',$fromPageUrl)?$fromPageUrl.$imageUrl:$fromPageUrl.'/'.$imageUrl;
        }
        $fileName=pathinfo($imageUrl,PATHINFO_BASENAME);
        \phpspider\core\requests::get($imageUrl);
        $data=\phpspider\core\requests::$content;
        if($data){
            $jpeg_quality = 90;
            $img_r = imagecreatefromstring($data);
            list($w,$h,,)=getimagesizefromstring($data);
            if(preg_match("/(arton|artimg\.net)/",$imageUrl)){
                $this->imagemask($img_r, $w-160, $h-60, $w, $h, 20);
            }
            $tmpFile=__DIR__.'/'.time().\Framework\Helper\Str::random().'.jpg';
            imagejpeg($img_r,$tmpFile,$jpeg_quality);
            if($new_url_info= \Framework\Helper\FileHelper::saveImageContent2Oss(file_get_contents($tmpFile),$tmpFile,'news_images')){
                unlink($tmpFile);
                return $new_url_info;
            }else{
                unlink($tmpFile);
            }
        }
        return false;
    }

//    public function test()
//    {
//        $jpeg_quality = 90;
//        $src = '../1186401751000.jpg';   //原始的图片
//        $data=file_get_contents('https://img1.artron.net/auction_manager/201808/bb2bdec41ffdf89585caee6c08569ebd1534492560.jpg');
//        $img_r = imagecreatefromstring($data);
//        list($w,$h,,)=getimagesizefromstring($data);
//        $this->imagemask($img_r, $w-160, $h-60, $w, $h, 20);
//        imagejpeg($img_r,time().'.jpg',$jpeg_quality);
//    }
    private function imagemask(&$im, $x1, $y1, $x2, $y2, $deep)
    {
        for($x = $x1; $x < $x2; $x += $deep){
            for ($y = $y1; $y < $y2; $y += $deep){
                $color = ImageColorAt ($im, $x + round($deep / 2), $y + round($deep / 2));
                imagefilledrectangle ($im, $x, $y, $x + $deep, $y + $deep, $color);
            }
        }
    }

}
//$row=\phpspider\core\db::get_one("select n_id from news_spider where n_title=''");
//var_dump($row);exit;

//new NewsSpider();
//$curl=new \Curl\Curl();
//$content=$curl->get('http://image.99ys.com/2018/0803/20180803105916681.jpg');
//$si=getimagesizefromstring($content);
//var_dump($si);
$spider=new NewsSpider();
$categories=$argv;
if(count($categories)>1){
    array_shift($categories);
    while($categories && $cat=array_shift($categories)){
        if(method_exists($spider,$cat)){
            $spider->$cat();
        }
    }
}else{
    $spider->runOrgccZixun();
    $spider->runCangworldNews();
    $spider->runCangworldZhanxun();
    $spider->runPeopleArtPaimai();
    $spider->runPeopleArtZixun();
    $spider->runCnartsPaimai();
    $spider->runCnartsZhanxun();
    $spider->runNewsArtxun();
    $spider->runNewsCangHotNews();
    $spider->runNewsCangPaimai();
    $spider->runChinafpo();
    $spider->runGucnZixun();
    $spider->runSsscPaimai();
    $spider->run99ys();
    $spider->runSinaCollectZixun();
    $spider->runSinaCollectPaimai();
    $spider->runArtronPaimai();
    $spider->runArtronZixun();
}
