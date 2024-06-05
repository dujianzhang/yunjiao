<?php


namespace app\student\model;
use think\Model;

/**
 * 拼团/课程套餐
 * Class PinkModel
 * @package app\student\model
 */
class PinkModel extends Model
{
    protected $pk = 'id';
    protected $name = 'pink';

    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }

    public function courseInfo()
    {
        return $this->belongsTo('CourseModel', 'cid', 'id');
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
            $field = 'type,sort,cid,price';
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

    public function packInfo()
    {
        return $this->belongsTo('CoursePackageModel', 'cid', 'id');
    }
}