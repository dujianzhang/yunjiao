<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use app\student\model\CourseGradeModel;
use app\student\model\CourseClassModel;
use app\student\model\CourseModel;
use app\student\model\CoursePackageModel;
use app\student\model\SeckillModel;
use app\student\model\PinkModel;
use think\Model;


/**
 * 课程
 * Class CourseController
 * @package app\student\controller
 */
class CourseController extends StudentBaseController
{
    /**
     * 精选内容
     * @return mixed|string
     */
    public function index()
    {

        $this->theFirstCondition();

        $sort = [0];

        $this->assign([
            'navid' => 6,
            'sort' => json_encode($sort)
        ]);
        return $this->fetch();
    }

    /**
     * 在线课程
     */
    public function onlineCourse()
    {
        $this->theFirstCondition();

        $sort = [1];
        $category = true;//类别条件
        $this->assign([
            'navid' => 1,
            'sort' => json_encode($sort),
            'category' => $category,

        ]);
        return $this->fetch('index');
    }

    /**
     * 直播课程
     */
    public function liveCourse()
    {
        $this->theFirstCondition();

        $sort = [2, 3, 4];

        $this->assign([
            'sort' => json_encode($sort)
        ]);
        return $this->fetch('index');
    }

    /**
     * 直播课程
     */
    public function packlist()
    {
        $this->theFirstCondition();

        $sort = ['pack'];

        $this->assign([
            'sort' => json_encode($sort)
        ]);
        return $this->fetch('index');
    }

    public function ajaxGetCourseList()
    {
        $param = input();
        $sort = input('sort', '');
        $page = (int)$param['page'] ?? 0;
        $param['son_grade'] = session('gradeid');

        if ($sort == 'pack') {//套餐
            $packWhere = [];
            $packWhere[] = ['isvip', '=', 0];
            if ($param['son_grade'] ?? 0 > 0) {
                $packWhere[] = ['gradeid', '=', $param['son_grade']];
            }
            $packList = $this->getpackList($packWhere, 0, $page);
            return $packList;
        }

        $where = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['isvip', '=', 0],
        ];
        if ($param['son_grade'] ?? 0 > 0) {
            $where[] = ['gradeid', '=', (int)$param['son_grade']];
        }

        if ($param['class_grade'] ?? 0 > 0) {
            $where[] = ['classid', '=', (int)$param['class_grade']];
        }
        if ($sort != '') {
            $sortArr = explode(',', trim($sort, ','));
            $where[] = ['sort', 'IN', $sortArr];
        }

        $CourseModel = new CourseModel();
        $course1list = $this->getCourseList($where, 8, $page);
        foreach ($course1list as $key => &$value) {
            $value['auto_tag'] = $CourseModel->autoTag($value);
        }
        return $course1list;
    }

    public function ajaxGetSonGrade()
    {
        $parent_grade = input('parent_grade', 0);

        if ($parent_grade <= 0) {
            return [];
        }
        $gradeInfo = getGrade();
        if ($gradeInfo) {
            $sonGrade = [];
            foreach ($gradeInfo as $key => $value) {
                if($value['pid'] == $parent_grade){
                    array_push($sonGrade,$value);
                }
            }
        } else {
            $CourseGradeModel = new CourseGradeModel();
            $sonGrade = $CourseGradeModel->getSonGrade($parent_grade);
        }

        return $sonGrade;

    }


    /**
     * 会员专享
     */
    public function forMembersOnly()
    {
        $sonGrade = $this->theFirstCondition();
        $sort = [1];

        $this->assign([
            'sonGrade' => $sonGrade,
            'sort' => json_encode($sort),
            'is_vip' => 1,
            'is_seckill' => 0,
            'is_pink' => 0
        ]);

        return $this->fetch('members_to_kill');
    }

    /**
     * 秒杀专区
     */
    public function forSeckillOnly()
    {
        $sonGrade = $this->theFirstCondition();
        $sort = [1];

        $this->assign([
            'sonGrade' => $sonGrade,
            'sort' => json_encode($sort),
            'is_vip' => 0,
            'is_seckill' => 1,
            'is_pink' => 0
        ]);

        return $this->fetch('members_to_kill');
    }

    /**
     * 拼团专区
     */
    public function forPinkOnly()
    {
        $sonGrade = $this->theFirstCondition();
        $sort = [1];

        $this->assign([
            'sonGrade' => $sonGrade,
            'sort' => json_encode($sort),
            'is_vip' => 0,
            'is_seckill' => 0,
            'is_pink' => 1
        ]);

        return $this->fetch('members_to_kill');
    }


    /**
     * 获取会员课程 列表
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     */
    public function getVipCourseList($where, $limit = 1, $page = 1, $page_num = 8, $order = 'id DESC')
    {
        $whereArr = [
            ['course_model.status', '=', 1],
            ['course_model.isvip', '=', 1],
            ['course_model.shelvestime', '<=', time()],
        ];

        if ($where['son_grade'] > 0) {
            $whereArr[] = ['course_model.gradeid', '=', (int)$where['son_grade']];
        }
        if ($where['class_grade'] > 0) {
            $whereArr[] = ['course_model.classid', '=', (int)$where['class_grade']];
        }
        $sortArr = explode(',', trim($where['sort'], ','));
        $whereArr[] = ['course_model.sort', 'IN', $sortArr];

        $CourseModel = new CourseModel();
        $course1list = $CourseModel
            ->getCourseList($whereArr, $limit, $page, $page_num, $order)
            ->withJoin([
                'users' => [
                    'id', 'avatar_thumb', 'user_nickname'
                ],
                'vipCourse' => [
                    'id',
                    'cid',
                    'type'
                ]
            ])
            ->where("vipCourse.type", 0)
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {

                $item['avatar_thumb'] = '<img src="' . $item->users['avatar_thumb'] . '">';
                $item['user_nickname'] = $item->users['user_nickname'] ?? '';

                $CourseModel->setCourseStatus($item);
                $item['auto_tag'] = $CourseModel->autoTag($item);

            })
            ->toArray();

        return $course1list;
    }

    /**
     * 好课推荐
     */
    public function goodCourse(){
        $sonGrade = $this->theFirstCondition();
        $sort = [''];

        $this->assign([
            'sonGrade' => $sonGrade,
            'sort' => json_encode($sort),
            'is_vip' => 0,
            'is_seckill' => 0,
            'is_pink' => 0
        ]);

        return $this->fetch();
    }

    /**
     * 获取营销 类课程列表
     * @return array|void
     */
    public function ajaxGetMarketingCourseList()
    {
        $son_grade = input('son_grade');
        $son_grade = session('gradeid');
        $son_grade = 0;

        $class_grade = input('class_grade');
        $sort = input('sort');
        $is_vip = input('is_vip', 0);
        $is_seckill = input('is_seckill', 0);
        $is_pink = input('is_pink', 0);
        $page = input('page', 1);

        if ($sort == '') {
            return [];
        }

        if ($sort == 'pack') {//套餐
            if ($is_vip == 1) {//VIP套餐
                return $this->getVipPackList(compact('son_grade'), 8, $page);
            }

            if ($is_seckill == 1) {//秒杀
                return $this->getSeckillPackList(compact("class_grade", 'son_grade', "sort"), 8, $page);
            }

            if ($is_pink == 1) {//拼团
                return $this->getPinkPackList(compact("class_grade", 'son_grade', "sort"), 8, $page);
            }


        } else {//课程
            if ($is_vip == 1) {//vip
                return $this->getVipCourseList(compact("class_grade", 'son_grade', "sort"), 8, $page);
            }

            if ($is_seckill == 1) {//秒杀
                return $this->getSeckillCourseList(compact("class_grade", 'son_grade', "sort"), 8, $page);
            }

            if ($is_pink == 1) {//拼团
                return $this->getPinkCourseList(compact("class_grade", 'son_grade', "sort"), 8, $page);
            }
        }

        return [];
    }

    /**
     * 获取 拼团 套餐
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     */
    protected function getPinkPackList($where, $limit = 0, $page = 1, $page_num = 8, $order = 'packInfo.id DESC')
    {
        $useWhere = [];

        if ($where['son_grade'] > 0) {
            $useWhere[] = ['packInfo.gradeid', '=', (int)$where['son_grade']];
        }
        $useWhere[] = ['pink_model.status', '=', 1];
        $useWhere[] = ['pink_model.type', '=', 1];
        $useWhere[] = ['pink_model.endtime', '>=', time()];
        $useWhere[] = ['pink_model.starttime', '<=', time()];

        $CoursePackageModel = new CoursePackageModel();
        $CourseModel = new CourseModel();
        $PinkModel = new PinkModel();
        $list = $PinkModel
            ->getList($useWhere, $limit, $page, $page_num, $order)
            ->withJoin([
                'packInfo' => ['id', 'name', 'thumb', 'courseids', 'nums', 'price']
            ])->group('pink_model.cid')
            ->select()
            ->each(function ($item, $key) use ($CoursePackageModel, $CourseModel) {
                $courseId = handelSetToArr($item->pack_info['courseids']);
                $item->pack_info['teacher'] = $CourseModel->getTeacherInfo($courseId);
                $item['auto_tag'] = $item->pack_info['nums'];
                $item['sort'] = 'pack';
//
                $item->pack_info['price'] = $item->pack_info['price'];//秒杀价
//
                $tapStatus = $CoursePackageModel->setTapStatus($item->pack_info);
                $item['vip_tag'] = '';
                $item['avatar_thumb'] = $tapStatus['teacher_avatar_tap'];
                $item['user_nickname'] = $tapStatus['user_nickname_tap'];
                $item['marketing_tag'] = $tapStatus['marketing_tag'];
                $item['thumb'] = $item->pack_info['thumb'];
                $item['name'] = $item->pack_info['name'];
                $item['id'] = $item->pack_info['id'];
                $item['ismaterialTag'] = $CoursePackageModel->getIsmaterial($item['id']);
            });
        return $list;
    }

    /**
     * 拼团课程
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     */
    public function getPinkCourseList($where, $limit = 0, $page = 1, $page_num = 8, $order = 'courseInfo.id DESC')
    {
        $useWhere = [];

        if ($where['son_grade'] > 0) {
            $useWhere[] = ['courseInfo.gradeid', '=', (int)$where['son_grade']];
        }

        if ($where['class_grade'] > 0) {
            $useWhere[] = ['courseInfo.classid', '=', (int)$where['class_grade']];
        }

        $sortArr = explode(',', trim($where['sort'], ','));
        $useWhere[] = ['courseInfo.sort', 'IN', $sortArr];

        $useWhere[] = ['pink_model.endtime', '>=', time()];
        $useWhere[] = ['pink_model.starttime', '<=', time()];
        $useWhere[] = ['pink_model.type', '=', 0];
        $useWhere[] = ['pink_model.status', '=', 1];

        $CourseModel = new CourseModel();
        $PinkModel = new PinkModel();
        $list = $PinkModel
            ->getList($useWhere, $limit, $page, $page_num, $order)
            ->withJoin([
                'courseInfo' => ['id', 'name', 'thumb', 'lessons', 'paytype', 'starttime','ismaterial', 'endtime', 'sort', 'type', 'payval', 'uid','islive']
            ])->group('pink_model.cid')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $CourseModel->setCourseStatus($item->course_info);
                $item['auto_tag'] = $CourseModel->autotag($item->course_info);
                $item['id'] = $item->course_info['id'];
                $item['name'] = $item->course_info['name'];
                $item['lessons'] = $item->course_info['lessons'];
                $item['marketing_tag'] = $item->course_info['marketing_tag'];
                $item['thumb'] = $item->course_info['thumb'];
                $item['vip_tag'] = '';
                $item['avatar_thumb'] = '<img src="' . $item->course_info->users['avatar_thumb'] . '">';
                $item['user_nickname'] = $item->course_info->users['user_nickname'];
                $item['ismaterialTag'] = $item->course_info['ismaterialTag'];

                unset($item->course_info);
            })->toArray();
        return $list;
    }

    /**
     * 秒杀套餐
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     */
    protected function getSeckillPackList($where, $limit = 0, $page = 1, $page_num = 8, $order = 'packInfo.id DESC')
    {
        $useWhere = [];

        if ($where['son_grade'] > 0) {
            $useWhere[] = ['packInfo.gradeid', '=', (int)$where['son_grade']];
        }
        $useWhere[] = ['seckill_model.status', '=', 1];
        $useWhere[] = ['seckill_model.type', '=', 1];
        $useWhere[] = ['seckill_model.endtime', '>=', time()];
        $useWhere[] = ['seckill_model.starttime', '<=', time()];

        $CoursePackageModel = new CoursePackageModel();
        $CourseModel = new CourseModel();
        $SeckillModel = new SeckillModel();
        $list = $SeckillModel
            ->getList($useWhere, $limit, $page, $page_num, $order)
            ->withJoin([
                'packInfo' => ['id', 'name', 'thumb', 'courseids', 'nums']
            ])->group('seckill_model.cid')
            ->select()
            ->each(function ($item, $key) use ($CoursePackageModel, $CourseModel) {
                $courseId = handelSetToArr($item->pack_info['courseids']);
                $item->pack_info['teacher'] = $CourseModel->getTeacherInfo($courseId);
                $item['auto_tag'] = $item->pack_info['nums'];
                $item['sort'] = 'pack';
//
                $item->pack_info['price'] = $item['money'];//秒杀价
//
                $tapStatus = $CoursePackageModel->setTapStatus($item->pack_info);
                $item['vip_tag'] = '';
                $item['avatar_thumb'] = $tapStatus['teacher_avatar_tap'];
                $item['user_nickname'] = $tapStatus['user_nickname_tap'];
                $item['marketing_tag'] = $tapStatus['marketing_tag'];
                $item['thumb'] = $item->pack_info['thumb'];
                $item['name'] = $item->pack_info['name'];
                $item['id'] = $item->pack_info['id'];
                $item['ismaterialTag'] = $CoursePackageModel->getIsmaterial($item['id']);

            });

        return $list;
    }

    /**
     * 获取 VIP 套餐
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     */
    protected function getVipPackList($where, $limit = 0, $page = 1, $page_num = 8, $order = 'id DESC')
    {
        $packWhere = [];
        if ($where['son_grade'] > 0) {
            $packWhere[] = ['gradeid', '=', $where['son_grade']];
            $packWhere[] = ['isvip', '=', 1];
        }
        return $this->getpackList($packWhere, $limit, $page, $page_num, $order);
    }

    /**
     * 获取秒杀课程
     * @param $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     */
    public function getSeckillCourseList($where, $limit = 1, $page = 1, $page_num = 8, $order = 'courseInfo.id DESC')
    {
        $useWhere = [];

        if ($where['son_grade'] > 0) {
            $useWhere[] = ['courseInfo.gradeid', '=', (int)$where['son_grade']];
        }

        if ($where['class_grade'] > 0) {
            $useWhere[] = ['courseInfo.classid', '=', (int)$where['class_grade']];
        }

        $sortArr = explode(',', trim($where['sort'], ','));
        $useWhere[] = ['courseInfo.sort', 'IN', $sortArr];
        $useWhere[] = ['seckill_model.starttime', '<=', time()];
        $useWhere[] = ['seckill_model.endtime', '>=', time()];
        $useWhere[] = ['seckill_model.type', '=', 0];
        $useWhere[] = ['seckill_model.status', '=', 1];
        $CourseModel = new CourseModel();
        $SeckillModel = new SeckillModel();
        $list = $SeckillModel
            ->getList($useWhere, $limit, $page, $page_num, $order)
            ->withJoin([
                'courseInfo' => ['id', 'name', 'thumb', 'lessons', 'paytype', 'ismaterial','starttime', 'endtime', 'sort', 'type', 'payval', 'uid','islive']
            ])->group('seckill_model.cid')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $CourseModel->setCourseStatus($item->course_info);
                $item['auto_tag'] = $CourseModel->autotag($item->course_info);
                $item['id'] = $item->course_info['id'];
                $item['name'] = $item->course_info['name'];
                $item['lessons'] = $item->course_info['lessons'];
                $item['marketing_tag'] = $item->course_info['marketing_tag'];
                $item['thumb'] = $item->course_info['thumb'];
                $item['vip_tag'] = '';
                $item['avatar_thumb'] = '<img src="' . $item->course_info->users['avatar_thumb'] . '">';
                $item['user_nickname'] = $item->course_info->users['user_nickname'];
                $item['ismaterialTag'] = $item->course_info['ismaterialTag'];

                unset($item->course_info);

            })->toArray();
//        print_r($list);
        return $list;
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
    protected function getCourseList($where, $limit = 1, $page = 1, $page_num = 8, $order = 'id DESC,list_order ASC')
    {
        $CourseModel = new CourseModel();

        $course1list = $CourseModel
            ->getCourseList($where, $limit, $page, $page_num, $order)
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = '<img src="' . $item->users['avatar_thumb'] . '">';
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);
            })
            ->toArray();

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
    public function getpackList($where, $limit = 0, $page = 1, $page_num = 8, $order = 'list_order ASC,id DESC')
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
                $item['ismaterialTag'] =   $CoursePackageModel->getIsmaterial($item['id']);

            })->toArray();
        return $result;
    }

    protected function theFirstCondition()
    {
        $gradeList = getGrade();

        $pGrade = [];
        foreach ($gradeList as $key => $value) {
            if ($value['pid'] == 0) {
                array_push($pGrade, $value);
            }
        }

        $sonGrade = [];
        foreach ($gradeList as $key => $value) {
            if ($value['pid'] == $pGrade[0]['id'] ?? '') {
                array_push($sonGrade, $value);
            }
        }

        $this->assign([
            'pgrade' => $pGrade,
            'sonGrade' => $sonGrade,
            'classList' => CourseClassModel::select()->toArray(),
        ]);
        return $sonGrade;
    }

}
