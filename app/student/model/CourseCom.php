<?php

namespace app\student\model;

use mysql_xdevapi\Exception;
use think\Model;

class CourseCom extends Model
{
    protected $pk = 'id';
    protected $name = 'course_com';

    public function getWonderful($limit = 8)
    {
        $list = $this
            ->with(['userInfo', 'courseInfo'])
            ->field('id,uid,courseid,lessons,content,sort')
            ->order('sort ASC')
            ->where([
                ['wonderful', '=', 1]
            ])
            ->limit($limit)
            ->select()
            ->toArray();
        return $list;
    }

    public function userInfo()
    {
        return $this->belongsTo(UsersModel::class, 'uid', 'id')->bind('user_nickname,avatar');
    }

    public function courseInfo()
    {
        return $this->belongsTo(CourseModel::class, 'courseid', 'id')->bind(['course_name' => 'name']);
    }
}