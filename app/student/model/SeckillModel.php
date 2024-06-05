<?php


namespace app\student\model;

use think\Model;

/**
 * 秒杀课程/套餐
 * Class SeckillModel
 * @package app\student\model
 */
class SeckillModel extends Model
{
    protected $pk = 'id';
    protected $name = 'seckill';


    public function courseInfo()
    {
        return $this->belongsTo('CourseModel', 'cid', 'id');
    }

    public function packInfo()
    {
        return $this->belongsTo('CoursePackageModel', 'cid', 'id');
    }

    /**
     * @param array $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     * @param string $field
     * @return array|\PDOStatement|string|\think\Collection|\think\model\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList($where = [], $limit = 0, $page = 0, $page_num = 0, $order = '', $field = '')
    {
        if (!$field) {
            $field = 'type,sort,cid,money';
        }

        $query = $this
            ->field($field);

        if ($where) {
            $query = $query->where($where);
        }
        if ($limit) {
            $query = $query->limit($limit);
        }
        if ($order) {
            $query = $query->order($order);
        }
        if ($page && $page_num) {
            $query = $query->page($page, $page_num);
        }

        return $query;
    }
}