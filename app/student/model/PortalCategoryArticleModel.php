<?php


namespace app\student\model;
use think\model\Pivot;

class PortalCategoryArticleModel extends Pivot
{
    protected $pk = 'id';
    protected $name = 'portal_category_post';

    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }


}