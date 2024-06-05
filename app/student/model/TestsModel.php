<?php


namespace app\student\model;

use think\Model;

/**
 * 考试
 * Class TestsModel
 * @package app\student\model
 */
class TestsModel extends Model
{
    protected $pk = 'id';
    protected $name = 'tests';

    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
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
    public function getList($where = [], $limit = 0, $page = 0, $page_num = 0, $order = 'id DESC', $field = '')
    {
        if (!$field) {
            $field = 'id,title,thumb,nums,ans_nums';
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

    public function getThumbAttr($value)
    {
        return get_upload_path($value);
    }
}