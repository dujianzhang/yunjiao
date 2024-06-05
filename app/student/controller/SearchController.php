<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use app\student\model\CourseGradeModel;
use app\student\model\CourseClassModel;
use app\student\model\CourseModel;
use app\student\model\CoursePackageModel;
use app\student\model\SeckillModel;


/**
 * 课程
 * Class CourseController
 * @package app\student\controller
 */
class SearchController extends StudentBaseController
{
    /**
     * 搜索页面
     * @return mixed|string
     */
    public function index()
    {

        $keywords = input('keywords'); //搜索内容

        $this->assign([
            'navid' => -1,
            'keywords'=>$keywords

        ]);
        return $this->fetch();
    }


    public function ajaxGetCourseList()
    {
        $param = input();
        $sort = input('sort', '');
        $keywords = input('keywords', '');
        $page = (int)$param['page'] ?? 0;

        if ($sort == 'pack') {//套餐
            $packWhere = [];
            $packWhere[] = ['name', 'like', "%{$keywords}%"];
            $packList = $this->getpackList($packWhere, 0, $page);
            return $packList;
        }

        $where = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
        ];

        $where[] =['name', 'like', "%{$keywords}%"];


        $CourseModel = new CourseModel();

        $course1list = $this->getCourseList($where, 8, $page);
        foreach ($course1list as $key => &$value) {
            $value['auto_tag'] = $CourseModel->autoTag($value);
        }
        return $course1list;
    }

    /**
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getpackList($where, $limit = 0, $page = 1, $page_num = 8, $order = 'list_order ASC')
    {
        $CourseModel = new CourseModel();

        $CoursePackageModel = new CoursePackageModel();
        $result = $CoursePackageModel
            ->getList($where, $limit, $page, $page_num, $order)
            ->select()
            ->each(function ($item, $key) use ($CourseModel, $CoursePackageModel) {
                $courseId = handelSetToArr($item['courseids']);
                $item['teacher'] = $CourseModel->getTeacherInfo($courseId);
                $item['auto_tag'] = $item['nums'];
                $item['sort'] = 'pack';

                $tapStatus = $CoursePackageModel->setTapStatus($item);
                $item['vip_tag'] = $tapStatus['vip_tag'];
                $item['avatar_thumb'] = $tapStatus['teacher_avatar_tap'];
                $item['user_nickname'] = $tapStatus['user_nickname_tap'];
                $item['marketing_tag'] = $tapStatus['marketing_tag'];

            })->toArray();
        return $result;
    }

    /**
     * 获取课程
     * @param $where
     * @param $limit
     * @param $page
     * @param $page_num
     * @param string $order
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getCourseList($where, $limit = 1, $page = 1, $page_num = 8, $order = 'list_order ASC')
    {
        $CourseModel = new CourseModel();

        $course1list = $CourseModel
            ->getCourseList($where, $limit, $page, $page_num, $order)
            ->with('seckill,users,vipCourse,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = '<img src="' . $item->users['avatar_thumb'] . '">';
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);
            })
            ->toArray();

        return $course1list;
    }




}