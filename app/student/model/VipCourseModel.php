<?php


namespace app\student\model;

use think\Model;

/**
 * vip课程/套餐
 * Class VipcourseModel
 * @package app\student\model
 */
class VipCourseModel extends Model
{
    protected $pk = 'id';
    protected $name = 'vip_course';

    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }

    public function course()
    {
        return $this->belongsTo('CourseModel', 'cid', 'id');
    }

}