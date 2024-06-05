<?php

namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\Db;
use app\student\model\SlideItemModel;
use app\student\model\CourseModel;
use app\student\model\ActivityModel;

class PlanActivitiesController extends StudentBaseController
{
    public function index()
    {
        $SlideItemModel = new SlideItemModel();
        $SlideList = $SlideItemModel->getSlideItemList(7);

        //精选套餐
        $CourseController = new CourseController();
        $packWhere = [];
        if (session('gradeid')) {
            $packWhere[] = ['gradeid', '=', session('gradeid')];
        }
        $packList = $CourseController->getpackList($packWhere, 8);

        //秒杀课程
        $son_grade = 0;
        if (session('gradeid')) {
            $son_grade =  session('gradeid');
        }
        $class_grade = 0;
        $sort = '0,1,2,3,4';
        $SeckillCourse = $CourseController->getSeckillCourseList(compact("class_grade", 'son_grade', "sort"), 8);
        foreach ($SeckillCourse as $key => $value) {
            $SeckillCourse[$key]['link'] = $this->replaceThePath($value);
        }
        //vip课程
        $vipCourse = $CourseController->getVipCourseList(compact("class_grade", 'son_grade', "sort"), 8);
        foreach ($vipCourse as $key2 => $value2) {
            $vipCourse[$key2]['link'] = $this->replaceThePath($value2);
        }

        //拼团课程
        $pinkCourse = $CourseController->getPinkCourseList(compact("class_grade", 'son_grade', "sort"), 8);
        foreach ($pinkCourse as $key3 => $value3) {
            $pinkCourse[$key3]['link'] = $this->replaceThePath($value3);
        }
        $ActivityModel = new ActivityModel();

//        热门活动
        $activelist = $ActivityModel->gethostList(session('gradeid'), 8);
        foreach ($activelist as $key4 => $value4) {
            $activelist[$key4]['startDate'] = explode(' ',$value4['starttime'])[0];
            $activelist[$key4]['endDate'] = explode(' ',$value4['endtime'])[1];
        }

        $this->assign([
            'navid' => 9,
            'silide' => $SlideList,
            'packList' => $packList,
            'freeCourse' => $SeckillCourse,
            'vipCourse' => $vipCourse,
            'pinkCourse' => $pinkCourse,
            'activelist' => $activelist,
        ]);
        return $this->fetch();
    }

    /**
     * 替换课程跳转路径
     * @param $value
     * @return mixed
     */
    public function replaceThePath($value)
    {
        if ($value['sort'] == 0) {//内容
            $jump_url = url('student/detail/substance', ['id' => $value['id']]);
        } else if ($value['sort'] == 1) {//课程
            $jump_url = url('student/detail/class', ['id' => $value['id']]);
        } else if (in_array($value['sort'], [2, 3, 4])) {//直播
            $jump_url = url('student/detail/live', ['id' => $value['id']]);
        }
        return $jump_url;
    }
}