<?php
namespace Model\Mall;

use Lib\Base\BaseModel;

class Refund extends BaseModel
{

    protected $table = 'refund';

    protected $id = 'r_id';

    /**
     * 新增退货
     *
     */
    public function add($params)
    {
        if ( ! $params['o_sn'] ||
             !$params['u_id'] ||
             !$params['r_status'] ||
             !$params['g_saleid']) {
            throw new \Exception\ParamsInvalidException("缺少必要的参数");
        }
        $refund = $this->getRefund($params['o_sn']);

        if (is_array($refund) && $refund['r_id']) {
            throw new \Exception\ParamsInvalidException("该订单已提交过申请");
        }

        $data = array(
            'r_reason' => $params['r_reason'],
            'r_content' => $params['r_content'],
            'r_linkman' => $params['r_linkman'],
            'r_tel' => $params['r_tel'],
            'o_sn' => $params['o_sn'],
            'u_id' => $params['u_id'],
            'g_saleid' => $params['g_saleid'],
            'r_status' => $params['r_status'],
            'r_ctime' => date('Y-m-d H:i:s'),
            'r_utime' => date('Y-m-d H:i:s'),
            'r_images' => $params['r_images'],

        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 获取申请记录根据订单sn
     *
     */
    public function getRefund($o_sn, $u_id='')
    {
        $where = "o_sn=:o_sn";
        $data['o_sn'] = $o_sn;
        if ($u_id) {
            $where .= " and (u_id=:u_id or g_saleid=:g_saleid) ";
            $data['u_id'] = $u_id;
            $data['g_saleid'] = $u_id;
        }
        $refund = $this->one($where, $data);
        return $refund;
    }


    /**
     * 更新退货状态
     *
     */
    public function updateStatus($o_sn, $status, $g_saleid = '', $u_id = '', $recvParams = [])
    {
        if (! $status || ! $o_sn) {
            throw new \Exception\ParamsInvalidException("缺少参数");
        }
        $refund = $this->getRefund($o_sn);

        if (!is_array($refund) && !$refund['r_id']) {
            return true;
            throw new \Exception\ParamsInvalidException("该订单不可操作");
        }

        if (!empty($g_saleid)) {
            if ($g_saleid != $refund['g_saleid']) {
                throw new \Exception\ParamsInvalidException("用户无权限操作");
            }
        }

        if (!empty($u_id)) {
            if ($u_id != $refund['u_id']) {
                throw new \Exception\ParamsInvalidException("用户无权限操作");
            }
        }
        $data = array(
            'r_status' => $status,
            'r_utime' => date("Y-m-d H:i:s"),
        );
        if (!empty($recvParams)) {
           $data = array_merge($recvParams, $data);
        }
        return $this->update($refund['r_id'], $data);
    }


}
