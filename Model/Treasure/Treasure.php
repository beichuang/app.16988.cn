<?php

namespace Model\Treasure;

use Framework\Helper\FileHelper;
use Lib\Base\BaseModel;
use Exception\ParamsInvalidException;

class Treasure extends BaseModel {

    protected $table = 'treasure';
    protected $id = 't_id';

    /**
     * 新增晒宝
     *
     * @param int $u_id
     * @param string $t_title
     * @param string $t_desc
     * @param string $t_pictures
     * @param string $t_type
     * @param string $t_business_id
     * @param int $t_provinceCode
     * @param int $t_cityCode
     * @param int $t_areaCode
     * @param int $t_status
     * @param number $t_likeTimes
     * @param number $t_commentTimes
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($u_id, $t_title, $t_desc, $t_type, $t_business_id, $t_provinceCode, $t_cityCode, $t_areaCode = 0, $t_status = 0, $t_likeTimes = 0, $t_commentTimes = 0) {
        if (!$u_id) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        // if (strlen($t_desc) > 255) {
        //     throw new \Exception\ParamsInvalidException("标题过长");
        // }

        isset($t_category) ? implode(',', $t_category) : "";
        $data = array(
            'u_id' => $u_id,
            't_title' => $t_title ? $t_title : '',
            't_desc' => $t_desc,
            't_provinceCode' => $t_provinceCode,
            't_cityCode' => $t_cityCode,
            't_areaCode' => $t_areaCode > 0 ? $t_areaCode : 0,
            't_status' => $t_status,
            't_createDate' => date('Y-m-d H:i:s'),
            't_updateDate' => date('Y-m-d H:i:s'),
            't_likeTimes' => $t_likeTimes,
            't_commentTimes' => $t_commentTimes,
            't_type' => $t_type,
            't_business_id' => $t_business_id,
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 移除晒宝
     *
     * @param int $u_id
     * @param int $t_id
     * @throws \Exception\ModelException
     * @return Ambigous <number, \Framework\Route, \Framework\Route>
     */
    public function removeTreasure($u_id, $t_id) {
        $row = $this->oneById($t_id);
        if (!$row || $row['u_id'] != $u_id) {
            throw new \Exception\ServiceException("已移除");
        }
        return $this->delete($t_id);
    }

    /**
     * 增加或减少点赞数
     *
     * @param int $t_id
     * @param boolean $like
     * @throws \Exception\ModelException
     * @return number
     */
    public function treasureLikeAdd($t_id, $like = true) {
        $row = $this->oneById($t_id);
        if (!$row) {
            throw new \Exception\ServiceException("宝贝已移除");
        }
        $likeTimes = $row['t_likeTimes'];
        if ($like) {
            $likeTimes ++;
        } else {
            $likeTimes --;
            if ($likeTimes < 0) {
                throw new \Exception\ServiceException("已取消点赞");
            }
        }
        return $this->update($t_id, array(
                    't_likeTimes' => $likeTimes
        ));
    }

    /**
     * 根据用户id、获取一条数据
     *
     * @param int $u_id
     *
     * @return multitype:
     */
    public function selectOne($t_id) {
        $row = $this->oneById($t_id);
        if (!$row) {
            throw new \Exception\ServiceException("信息已移除");
        }
        return $row;
    }

    /**
     * 增加或减少评论数
     *
     * @param int $t_id
     * @param boolean $add
     * @throws \Exception\ModelException
     * @return number
     */
    public function treasureCommentAdd($t_id, $add = true) {
        $row = $this->oneById($t_id);
        if (!$row) {
            throw new \Exception\ServiceException("艺术圈内容已移除");
        }
        $times = $row['t_commentTimes'];
        if ($add) {
            $times ++;
        } else {
            $times --;
            if ($times < 0) {
                $times = 0;
            }
        }
        return $this->update($t_id, array(
                    't_commentTimes' => $times
        ));
    }

    /**
     * 查询列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    /*
     * public function selectsAll($page,$pagesize) { $sql = "SELECT * FROM `{$this->table}` ORDER BY tre.t_id DESC LIMIT $page, $pagesize" $rest = $this->mysql->query($sql); return $rest; }
     */

    /**
     * 查询搜索列表
     *
     * @param array $params
     * @param int $page
     * @param int $pagesize
     * @return array $List
     */
    public function lists($params, $page, $pagesize) {
        $whereArr[] = 'tre.t_is_advertise = :t_is_advertise';
        $bindData[':t_is_advertise'] = 0;
        if (isset($params['t_id'])) {
            $tids=[];
            if(is_array($params['t_id'])){
                $tids=$params['t_id'];
            }else if(is_numeric($params['t_id'])){
                $tids[]=$params['t_id'];
            }else if(is_string($params['t_id']) && preg_match("/^(\d+,?)+$/",$params['t_id'])){
                $tids=explode(',',$params['t_id']);
            }
            if($tids){
                $whereArr[] = 'tre.u_id in ('.implode(',',$tids).')';
            }
        }
        if (isset($params['u_id'])) {
            if (is_array($params['u_id'])) {
                $tempdata = implode(',', $params['u_id']);
                $whereArr[] = " tre.u_id in($tempdata) ";
            } else {
                $whereArr[] = 'tre.u_id = :u_id';
                $bindData[':u_id'] = $params['u_id'];
            }
        }
        if (isset($params['t_provinceCode']) && $params['t_provinceCode'] != '') {
            $whereArr[] = 'tre.t_provinceCode = :t_provinceCode';
            $bindData[':t_provinceCode'] = $params['t_provinceCode'];
        }
        if (isset($params['t_desc']) && $params['t_desc'] != '') {
            $whereArr[] = "tre.t_desc like concat('%',:t_desc,'%')";
            $bindData[':t_desc'] = $params['t_desc'];
        }
        if (isset($params['keyword']) && $params['keyword'] != '') {
            $whereArr[] = "(tre.t_desc like concat('%',:keyword,'%') or tre.t_title like concat('%',:keyword,'%'))";
            $bindData[':keyword'] = $params['keyword'];
        }

        if (isset($params['t_cityCode']) && $params['t_cityCode'] != '') {
            $whereArr[] = 'tre.t_cityCode = :t_cityCode';
            $bindData[':t_cityCode'] = $params['t_cityCode'];
        }
        if (isset($params['t_areaCode']) && $params['t_areaCode'] != '') {
            $whereArr[] = 'tre.t_areaCode = :t_areaCode';
            $bindData[':t_areaCode'] = $params['t_areaCode'];
        }
        if (isset($params['t_status']) && $params['t_status'] !== '') {
            $whereArr[] = 'tre.t_status = :t_status';
            $bindData[':t_status'] = $params['t_status'];
        }
        if (isset($params['t_type']) && $params['t_type'] !== '') {
            $whereArr[] = 'tre.t_type = :t_type';
            $bindData[':t_type'] = $params['t_type'];
        }
        if (isset($params['t_category']) && $params['t_category'] != '') {
            $whereArr[] = 'tre.t_category = :t_category';
            $bindData[':t_category'] = $params['t_category'];
        }

        if (isset($params['createDateStart']) && $params['createDateStart'] != '') {
            $whereArr[] = '`tre`.t_createDate >= :createDateStart';
            $bindData[':createDateStart'] = $params['createDateStart'];
        }
        if (isset($params['createDateEnd']) && $params['createDateEnd'] != '') {
            $whereArr[] = '`tre`.t_createDate <= :createDateEnd';
            $bindData[':createDateEnd'] = $params['createDateEnd'];
        }

        if (isset($params['updateDateStart']) && $params['updateDateStart'] != '') {
            $whereArr[] = '`tre`.t_updateDate >= :updateDateStart';
            $bindData[':updateDateStart'] = $params['updateDateStart'];
        }
        if (isset($params['updateDateEnd']) && $params['updateDateEnd'] != '') {
            $whereArr[] = '`tre`.t_updateDate <= :updateDateEnd';
            $bindData[':updateDateEnd'] = $params['updateDateEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);


        $sql = "SELECT tre.* FROM `{$this->table}` tre ";
        if (isset($params['topic']) && $params['topic'] && is_array($params['topic'])) {
            $tempdata = implode("','", $params['topic']);
            $sql.= " join treasure_topic_ref ttr on tre.t_id=ttr.t_id and ttr.tt_no in('".$tempdata."') ";
        }
        $sql .=" $where ";
        if (isset($params['sort']) && $params['sort'] === 'latest') {
            $sql.=" ORDER BY tre.t_id DESC ";
        }else if ( isset($params['sort']) && $params['sort'] === 'morelike') {
            $sql.=" ORDER BY tre.t_likeTimes DESC ";
        }else{
            $sql.=" ORDER BY tre.t_id DESC ";
        }
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(0) FROM `{$this->table}` tre $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [$rows, $count];
    }

    /**
     * 获取圈子广告
     */
    public function getTreasureAdvertise() {
        $sql = "SELECT * FROM `{$this->table}` WHERE t_is_advertise = 1 AND t_status = 0 ORDER BY t_id DESC LIMIT 1";
        $rows = app('mysqlbxd_app')->fetch($sql, []);
        return $rows;
    }

    /**
     * 将逗号分隔的图片处理成适合url访问的图片链接
     *
     * @param string $picsStr
     * @return array
     */
    private function parsePicsForUrlVisit($picsStr) {
        $picsProcessed = [];
        if ($picsStr) {
            $pics = explode(',', $picsStr);
            foreach ($pics as $pic) {
                if ($pic) {
                    $picsProcessed[] = FileHelper::getFileUrl($pic, 'treasure');
                }
            }
        }
        return $picsProcessed;
    }

    /**
     * 获取活跃的圈友
     */
    public function getActiveLists($params, $page, $pageSize) {
        $whereArr = $bindData = [];
        if (isset($params['t_status']) && $params['t_status'] !== '') {
            $whereArr[] = 'tre.t_status = :t_status';
            $bindData[':t_status'] = $params['t_status'];
        }

        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT tre.* FROM `{$this->table}` tre
                $where GROUP BY tre.u_id ORDER BY tre.t_id DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pageSize, $bindData);

        $countSql = "SELECT u_id FROM `{$this->table}` tre $where GROUP BY u_id";
        $count = app('mysqlbxd_app')->select($countSql, $bindData);
        return [
            $rows,
            count($count)
        ];
    }

}
