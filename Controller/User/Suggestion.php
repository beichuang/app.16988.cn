<?php
/**
 * 投诉建议
 * @author Administrator
 *
 */
namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Lib\Common\SessionKeys;
use Exception\ModelException;

class Suggestion extends BaseController
{

    private $suggestionModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->suggestionModel = new \Model\User\Suggestion();
    }

    /**
     * 新增投诉建议
     * 
     * @throws ModelException
     */
    public function add()
    {
        $content = app()->request()->params('content');
        $lastSuggestion = $this->suggestionModel->getLastSuggestionByUid($this->uid);
        if ($lastSuggestion) {
            $privTime = strtotime($lastSuggestion['usug_time']);
            if (time() - $privTime < 86400 && $lastSuggestion['usug_content'] == $content) {
                throw new ServiceException("已提交");
            }
        }
        $id = $this->suggestionModel->add($this->uid, $content);
        if (! $id) {
            throw new ModelException("保存投诉建议失败");
        }
        (new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_SUGGEST_ADD);
        $this->responseJSON(array(
            'suggestion_id' => $id
        ));
    }

    /**
     * 意投诉建议列表
     */
    public function lists()
    {
        $page=app()->request()->params('page',1);
        $pageSize=app()->request()->params('pageSize',10);
        list($list,$count)=$this->suggestionModel->lists([
            'u_id'=>$this->uid,
        ],$page,$pageSize);
        if($list){
            foreach ($list as &$row){
                $replyList=$this->suggestionModel->lists([
                    'usug_pid'=>$row['usug_id'],
                ],1,99,'usug_id');
                list($row['replyList'],)=$replyList?$replyList:[];
            }
        }else{
             $list=[];
        }
        $this->responseJSON([
            'list'=>$list,
            'total'=>$count,
            'page'=>$page,
            'pageSize'=>$pageSize,
        ]);
    }
}
