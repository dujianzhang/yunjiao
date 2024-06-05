<?php


namespace app\student\model;

use think\Model;

class PortalCategoryModel extends Model
{
    protected $pk = 'id';
    protected $name = 'portal_category';

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
    public function getList($where = [], $limit = 0, $page = 0, $page_num = 0, $order = 'list_order ASC', $field = '')
    {
        if (!$field) {
            $field = 'id,parent_id,name,path,list_order';
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

    public function portalArticle()
    {
        return $this->belongsToMany('PortalArticleModel', '\app\student\model\PortalCategoryArticleModel', 'post_id', 'category_id');
    }
}