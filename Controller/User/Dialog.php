<?php
/**
 * 会话、对话
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

class Dialog extends BaseController
{
    /**
     * 会话列表
     *
     * @throws ModelException
     */
    public function lists()
    {
        $u_id = $this->uid;
        $page = app('request')->params('page', '1');
        $pagesize = app('request')->params('pagesize', '10');
        $dialogUserModel = new \Model\Message\DialogUser();
        list ($dialogLists, ) = $dialogUserModel->lists([
            'u_id' => $u_id
        ], $page, $pagesize);
        $rows = [];
        if ($dialogLists && is_array($dialogLists) && ! empty($dialogLists)) {
            $msgdcModel = new \Model\Message\DialogContent();
            foreach ($dialogLists as $dialogRow) {
                $msgd_id = $dialogRow['msgd_id'];
                list ($msgdcListTmp, ) = $msgdcModel->lists([
                    'msgd_id' => $msgd_id
                ], 1, 1);
                if ($msgdcListTmp && is_array($msgdcListTmp)) {
                    $row=$msgdcListTmp[0];
                    $row['displayTime']=date_format_to_display(strtotime($row['msgdc_time']));
                    $row['displayUid']=$row['msgdc_receiveUserId'];
                    if($u_id ==$row['msgdc_receiveUserId']){
                        $row['displayUid']=$row['u_id'];
                    }
                    $rows[] = $row;
                }
            }
            if(!empty($rows))
            {
                $userLib=new \Lib\User\User();
                $userLib->extendUserInfos2Array($rows,'displayUid', array(
                    'u_nickname'=>'displayNickname',
                    'u_avatar'=>'displayAvatar',
                ));
            }
        }
        
        $this->responseJSON($rows);
    }
}
