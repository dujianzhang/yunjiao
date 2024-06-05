<?php


namespace app\student\model;

use think\Model;

class ShopCatModel extends Model
{
    protected $pk = 'id';
    protected $name = 'shop_cat';

    /**
     * 获取分类列表
     * @param $gradeid
     */
    public function getList($gradeid = 0)
    {
        $where = [];

        if ($gradeid > 0) {
            $where[] = ['gradeid', '=', $gradeid];
        }

        $list = $this
            ->where($where)
            ->select()->toArray();

        return $list;
    }
}
