<?php


namespace app\student\model;

use think\Model;

/**
 * 科目
 * Class CourseClassModel
 * @package app\student\model
 */
class CourseClassModel extends Model
{
    protected $pk = 'id';
    protected $name = 'course_class';


    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }

    public function getSelect($where = []){
        $result = $this
            ->field('id,name')
            ->where($where)
            ->select()
            ->toArray();

        return $result;
    }
}