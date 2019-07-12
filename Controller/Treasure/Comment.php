<?php
/**
 * 评论
 * @author Administrator
 *
 */
namespace Controller\Treasure;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Lib\Common\SessionKeys;
use framework\Lib\CommonFun;
use Exception\ModelException;
use framework\Helper\file;
use Lib\Common\QueueManager;

class Comment extends BaseController
{

    private $TreasureCommentModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->TreasureCommentModel = new \Model\Treasure\TreasureComment();
    }

    /**
     * 新增评论
     *
     * @throws ModelException
     */
    public function add() {
        $u_id = $this->uid;
        $this->checkUserStatus($u_id);
        $t_id = app('request')->params('t_id');
        $tc_title = app('request')->params('tc_title');
        $tc_content = app('request')->params('tc_content');
        $tc_pid = app('request')->params('tc_pid', 0);
        if (!$tc_content) {
            throw new ParamsInvalidException("内容不能是空");
        }
        $check_content = filter_words(cutstr_html($tc_content));
        if ($check_content) {
            throw new ParamsInvalidException("内容包含敏感词");
        }

        $this->handleData($tc_title, 'str');
        $this->handleData($tc_content, 'str');
        $this->TreasureCommentModel->beginTransaction();
        try{
            $rest = $this->TreasureCommentModel->add($u_id, $t_id, $tc_title, $tc_content, $tc_pid );
            if ($rest) {
                $treasureModel = new \Model\Treasure\Treasure();
                $treasureModel->treasureCommentAdd($t_id);
            } 
            $this->TreasureCommentModel->commit();
            //赠送积分
            (new \Lib\User\UserIntegral())->addIntegral($u_id,\Lib\User\UserIntegral::ACTIVITY_TREASURE_COMMENT_ADD);
        }catch (Exception $e) {
            $this->TreasureCommentModel->rollback();
            throw new ModelException("评论失败");
        } 
        $res = $this->TreasureCommentModel->oneById($rest);
        $arr[0] = $res; 
        if(is_array($arr))
        {
            $userLib=new \Lib\User\User();
            $userLib->extendUserInfos2Array($arr,'u_id', array(
                'u_nickname'=>'tc_nickname',
                'u_realname'=>'t_realname',
                'u_avatar'=>'tc_avatar',
            ));
        }

        if (!$this->isSelf($t_id, $u_id)) {
            $queueAppid = config('app.queue_common_params.appid');
            QueueManager::queue($arr[0]['tc_id'], 8, $arr[0], $queueAppid);
        }

        $this->responseJSON($arr[0]);
    }

    /**
     * 删除评论
     *
     * @throws ModelException
     */
    public function delete()
    {
        $u_id = $this->uid;
        $t_id = app('request')->params('t_id');
        if (! $u_id || ! $t_id) {
            throw new ParamsInvalidException('参数错误');
        }
        $rest = $this->TreasureCommentModel->treasureCommentRemove($u_id, $t_id);
        if ($rest) {
            $treasureModel = new \Model\Treasure\Treasure();
            $treasureModel->treasureCommentAdd($t_id, 0);
        } else {
            throw new ModelException("删除评论失败");
        }
        $this->responseJSON(true);
    }

    /**
     * 获取评论列表
     *
     * @throws ModelException
     */
    public function lists()
    {
        $u_id = $this->uid;
        $t_id = app()->request()->params('t_id');
        $page = app()->request()->params('page');
        $pageSize = app()->request()->params('pageSize');
        if (! $t_id) {
            throw new ParamsInvalidException('参数错误');
        }
        $param = array(
            't_id' => $t_id
        );
        //$param['u_id'] = $u_id;
        $rest = $this->TreasureCommentModel->lists($param, $page, $pageSize);
        $commentUser = array();
        foreach ($rest[0] as &$v) {
            $time = strtotime($v['tc_time']);
            $v['displayTime'] = date_format_to_display($time);
            $commentUser[ $v['tc_id'] ] = $v['u_id'];
        }
        foreach ($rest[0] as &$vu) {
            $vu['p_u_id'] = $vu['tc_pid'] && isset($commentUser[$vu['tc_pid']]) ? $commentUser[ $v['tc_id'] ] : '';
        }
        $userLib=new \Lib\User\User();
        $userLib->extendUserInfos2Array($rest[0],'u_id', array(
            'u_nickname'=>'tc_nickname',
            'u_avatar'=>'tc_avatar',
            'u_realname'=>'tc_realname',
            ));
        $userLib->extendUserInfos2Array($rest[0],'p_u_id', array(
            'u_nickname'=>'p_u_nickname',
            'u_realname'=>'p_u_realname',
            ));
        $this->responseJSON($rest[0]);
    }

    /**
     * 对数据进行初级过滤
     *
     * @param string $data
     *            要处理的数据
     * @param string $filter
     *            过滤的方式
     * @return mix
     */
    private function handleData($data = '', $filter = '')
    {
        switch ($filter) {
            case 'int':
                return abs(intval($data));
                break;
            
            case 'str':
                return trim(htmlspecialchars(strip_tags($data)));
                break;
            
            case 'float':
                return floatval($data);
                break;
            
            case 'arr':
                return (array) $data;
                break;
        }
        
        return '';
    }

    /**
     * 是否是自身发布的圈子
     * @param $t_id
     * @param $u_id
     * @return bool
     */
    private function isSelf($t_id,$u_id)
    {
        $treasureModel = new \Model\Treasure\Treasure();
        $treasureData = $treasureModel->oneById($t_id);
        return !empty($treasureData) && $treasureData['u_id'] == $u_id;
    }
}