<?php

namespace app\student\model;

use think\Model;

class ActivityModel extends Model
{
    protected $pk = 'id';
    protected $name = 'activity';

    /**
     * 获取分类列表
     * @param $gradeid
     */
    public function getList($gradeid = 0, $p = 1, $num = 8)
    {
        $where = [];

        if ($gradeid > 0) {
            $where[] = ['gradeid', '=', $gradeid];
        }

        $where[] = ['endtime', '>=', time()];
        $where[] = ['status', '=', 1];

        $list = $this
            ->where($where)
            ->page($p, $num)
            ->select()
            ->toArray();

        foreach ($list as $key => &$value) {
            $value['thumb'] = get_upload_path($value['thumb']);
            $value['starttime'] = date('Y-m-d H:i:s', $value['starttime']);
            $value['endtime'] = date('Y-m-d H:i:s', $value['endtime']);
        }
        return $list;
    }

    /**
     * 获取活动详情
     */
    public function getDetail($id, $gradeid = 0)
    {
        $where = [];
        if ($gradeid > 0) {
            $where[] = ['gradeid', '=', $gradeid];
        }

        $where[] = ['endtime', '>=', time()];
        $where[] = ['status', '=', 1];

        $info = $this
            ->where('id', $id)
            ->where($where)
            ->find();
        if(!$info){
            return $info;
        }
        $info['thumb'] = get_upload_path($info['thumb']);
        $info['starttime'] = date('Y-m-d H:i:s', $info['starttime']);
        $info['endtime'] = date('Y-m-d H:i:s', $info['endtime']);

        return $info;
    }

    /**
     * 热门活动
     * @param int $gradeid
     * @param int $limit
     */
    public function gethostList($gradeid, $limit = 6)
    {
        $where = [];

        if ($gradeid > 0) {
            $where[] = ['gradeid', '=', $gradeid];
        }

        $where[] = ['endtime', '>=', time()];
        $where[] = ['status', '=', 1];

        $list = $this
            ->where($where)
            ->order('nums DESC')
            ->limit($limit)
            ->select()->toArray();

        foreach ($list as $key => &$value) {
            $value['thumb'] = get_upload_path($value['thumb']);
            $value['starttime'] = date('Y-m-d H:i:s', $value['starttime']);
            $value['endtime'] = date('Y-m-d H:i:s', $value['endtime']);
        }
        return $list;
    }
}
