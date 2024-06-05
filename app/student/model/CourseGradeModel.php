<?php


namespace app\student\model;

use think\Db;
use think\Model;

/**
 * 年级分类
 * Class CourseGradeModel
 * @package app\student\model
 */
class CourseGradeModel extends Model
{
    protected $pk = 'id';
    protected $name = 'course_grade';

    public function getParentGrade()
    {
        $where[] = ['pid', '=', 0];
        $result = $this
            ->field('id,name,pid')
            ->where($where)
            ->order('list_order asc')
            ->select()
            ->toArray();
        return $result;
    }

    public function getSonGrade($pid)
    {
        $where = [['pid', '=', $pid]];
        $result = $this
            ->field('id,name,pid')
            ->where($where)
            ->order('list_order asc')
            ->select()
            ->toArray();
        return $result;
    }
}