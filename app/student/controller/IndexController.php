<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\Db;
use app\student\model\CourseModel;
use app\student\model\CourseCom;
use app\student\model\CourseClassModel;
use app\student\model\TestsModel;
use app\student\model\UsersModel;
use app\student\model\PortalCategoryModel;
use app\student\model\SlideItemModel;
use app\student\model\SeckillModel;
use app\student\model\NewsModel;
use think\facade\Cache;

/**
 * 首页
 */
class IndexController extends StudentBaseController
{


    //选择学级
    public function SetGrade()
    {
        $data = $this->request->param();


        $rs = array('code' => 0, 'msg' => '设置成功', 'info' => array());

        $id = isset($data['id']) ? $data['id'] : '';
        $id = checkNull($id);
        //$ip = get_client_ip();
        session('gradeid',$id);
        $userId = session('student.id');

        if ($userId) {
            $data = array(
                'gradeid' => $id
            );
            $result = Db::name('users')->where(['id' => $userId])->update($data);

            $gradeinfo = Db::name('course_grade')->where(['id' => $id])->find();

            if ($gradeinfo) {
                $gradename = $gradeinfo['name'];
            } else {
                $gradename = '';
            }

            $userinfo = session('student');
            $userinfo['gradename'] = $gradename;
            $userinfo['gradeid'] = $id;
            session('student', $userinfo);
            $rs['msg'] = '设置成功';

        }


        echo json_encode($rs);
        exit;
    }

    //首页
    public function index()
    {
        $data = $this->request->param();


        $isBackLog = 0;
        if (isset($data['isBackLog'])) {
            $isBackLog = $data['isBackLog'];
        }

        $SlideItemModel = new SlideItemModel();
        $SlideList = $SlideItemModel->getSlideItemList(5);

        $CourseModel = new CourseModel();
        //直播课
        $whereSort3 = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['sort', 'IN', [2, 3, 4]],
            ['tx_trans', '=', 1],
            ['isvip', '=', 0],//非会员专享
        ];

        if (session('gradeid')) {
            $whereSort3[] = ['gradeid', '=', session('gradeid')];
        }
        //直播课
        $course3list = $CourseModel
            ->getCourseList($whereSort3, 8, 0, 0, 'sort ASC')
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'];
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);

            })
            ->toArray();

        //内容
        $whereSort0 = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['sort', '=', 0],
            ['tx_trans', '=', 1],
            ['isvip', '=', 0],//非会员专享
        ];

        if (session('gradeid')) {
            $whereSort0[] = ['gradeid', '=', session('gradeid')];
        }
        //内容
        $course0list = $CourseModel
            ->getCourseList($whereSort0, 8, 0, 0, 'sort ASC')
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'];
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);

            })
            ->toArray();

        //课程
        $whereSort1 = [
            ['sort', '=', 1],
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['tx_trans', '=', 1],
            ['isvip', '=', 0],//非会员专享

        ];
        if (session('gradeid')) {
            $whereSort1[] = ['gradeid', '=', session('gradeid')];
        }
        //课程
        $course1list = $CourseModel
            ->getCourseList($whereSort1, 6, 0, 0, 'sort ASC')
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'];
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);
            })
            ->toArray();

        //免费课程
        $whereSort1[] = ['paytype', '=', 0];
        $courseFreelist =  $CourseModel
            ->getCourseList($whereSort1, 8, 0, 0, 'sort ASC')
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'];
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);
            })
            ->toArray();

        //考试
        $TestsModel = new TestsModel();
        $testWhere = [
            ['status', '=', 1],
        ];
        if (session('gradeid')) {
            $testWhere[] = ['gradeid', '=', session('gradeid')];
        }
        $testList = $TestsModel->getList($testWhere, 8)->select();

        //名师团队
        $UsersModel = new UsersModel();
        $teacWhere = [
            ['user_status', '<>', 0],
            ['type', '=', 1],
        ];
        $teacherList = $UsersModel
            ->getList($teacWhere, 4)
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['colour'] = $item->identity_info['colour'] ?? '';
                $item['identity_name'] = $item->identity_info['name'] ?? '';

                $number = $CourseModel->getCountByteacher($item['id']);
                $item['course_number']  = $number['course_number'];
                $item['student_number']  = $number['student_number'];

            })->toArray();
        //关联年级下新闻咨询
        $newsModel = new NewsModel();
        $newsList = $newsModel->getHomeList(session('student.gradeid') ?? 0);
        //科目分类
        $CourseClassModel = new CourseClassModel();
        $courseClassList = $CourseClassModel->getSelect();
        $courseByCourseClass = $CourseModel->getSelectByCourseClass($courseClassList);

        $CourseCom = new CourseCom();
        $Wonderful = $CourseCom->getWonderful();
        foreach ($Wonderful as $com_key => &$com_value){
            $com_value['id'] = $com_value['courseid'];
            $com_value['link'] = $this->replaceThePath($com_value);
        }
        $Wonderful = array_chunk($Wonderful, 4);

        if (count($Wonderful) == 0) {
            $Wonderful[0] = [];
            $Wonderful[1] = [];
        }
        if (count($Wonderful) == 1) {
            $Wonderful[1] = [];
        }

//        好课推荐左侧广告
        $SlideItemModel = new SlideItemModel();
        $CourseSlideList = $SlideItemModel->getSlideItemList(6);

        $this->assign([
            'navid' => 0,
            'silide' => $SlideList,
            'isBackLog' => $isBackLog,

            'course3list' => $course3list,
            'course1list' => $course1list,
            'course0list' => $course0list,
            'courseFreelist' => $courseFreelist,

            'testList' => $testList,
            'teacherList' => $teacherList,
            'newsList' => $newsList,
            'courseClassList' => $courseClassList,
            'courseByCourseClass' => $courseByCourseClass,

            'wonderful' => $Wonderful,
            'courseSlideList' => $CourseSlideList[0] ?? [],
        ]);
        return $this->fetch('indexv2');
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

    /**
     * 获取年级信息
     * @param array $where
     */
    protected function getGrade($where = [])
    {
        $result = Db::name('course_grade')
            ->field('id,name,pid')
            ->where($where)
            ->order('list_order asc')
            ->select()
            ->toArray();

        return $result;
    }

    /**
     * 获取年级信息
     * @param array $where
     */
    protected function getClass($where = [])
    {
        $result = Db::name('course_grade')
            ->field('id,name,pid')
            ->where($where)
            ->order('list_order asc')
            ->select()
            ->toArray();

        return $result;
    }

    public function ajaxGetGrade()
    {
        $pgradeId = input('pgrade_id/d');

        $gradeInfo = getGrade();
        $sonGrade = [];
        foreach ($gradeInfo as $key => $value) {
            if ($value['pid'] == $pgradeId) {
                array_push($sonGrade, $value);
            }
        }
        return $sonGrade;

    }

    /**
     * 获取套餐列表
     * @param array $where
     */
    protected function getPackageList($where = [], $limit = 8)
    {
        $result = Db::name('course_package')
            ->field('id,name,thumb,price,courseids,nums')
            ->where($where)
            ->limit($limit)
            ->select()
            ->toArray();
        foreach ($result as $key => $value) {
            $result[$key]['thumb'] = $value['thumb'];
            $result[$key]['ismaterial'] = $this->checkPackageHasTeaching($value['courseids']);
            $result[$key]['teacher'] = $this->getPackageUserInfo($value['courseids']);
        }
        return $result;
    }

    /**
     * 检查课程是否有教材
     * @param $courseID
     */
    protected function checkHasTeaching($courseID)
    {
        $res = Db::name('course')
            ->field('ismaterial')
            ->where('id', $courseID)
            ->find();
        return $res && $res['ismaterial'] == 1 ? true : false;
    }

    /**
     * 检查套餐中的 课程是否有教材
     * @param $courseids
     */
    protected function checkPackageHasTeaching($courseids)
    {
        $arr = explode(',', $courseids);
        $res = false;
        foreach ($arr as $key => $value) {
            $resu = $this->checkHasTeaching(trim(trim($value, '['), ']'));
            if ($resu === true) {
                $res = true;
                break;
            }
        }
        return $res;

    }

    /**
     * 获取用户信息
     * @param $id 用户 id
     */
    protected function getUserInfo($id)
    {
        $info = Db::name('users')
            ->field('id,user_nickname,avatar')
            ->where('id', $id)
            ->find();
        return $info;
    }

    /**
     * 获取套餐 课程中主讲老师头像
     * @param $courseids
     */
    protected function getPackageUserInfo($courseids)
    {
        $arr = explode(',', $courseids);
        $res = [];
        foreach ($arr as $key => $value) {
            $resu = $this->getUserInfo(trim(trim($value, '['), ']'));
            array_push($res, get_upload_path($resu['avatar']));
        }
        return $res;
    }

    //精选套餐
    public function ajaxGetPackageList()
    {
        $grade_id = input('grade_id/d');
        return $this->getPackageList([['gradeid', '=', $grade_id]]);
    }

    /**
     * 获取课程列表
     * @param array $where
     * @param int $limit
     */
    protected function getCourseList($where = [], $limit = 8)
    {
        $list = Db::name('course')
            ->field('id,uid,name,thumb,gradeid,payval,paytype,ismaterial,lessons,islive,type')
            ->where($where)
            ->where([
                ['status', '=', 1],
            ])
            ->limit($limit)
            ->order('list_order ASC,id DESC')
            ->select()
            ->toArray();
        foreach ($list as $key => $value) {
            $userInfo = $this->getUserInfo($value['uid']);
            $list[$key]['avatar'] = get_upload_path($userInfo['avatar']);
            $list[$key]['thumb'] = get_upload_path($value['thumb']);
            $list[$key]['user_nickname'] = $userInfo['user_nickname'];

        }
        return $list;
    }

    /**
     * 好课推荐
     */
    public function ajaxGetCourseList()
    {
        $pgardeId = input('p_grade_id/d');
        $kind_id = input('kind_id/d');
        $kind = [
            0 => [0, 1, 2, 3],//全部类型
            1 => [1],//课程
            2 => -1,//套餐
            3 => [2, 3],//直播
            4 => [0],//内容
        ];
        $sort = $kind[$kind_id] ?? -1;
        if ($sort === -1) {
            return [];
        }

        $gradeList = array_column($this->getGrade([['pid', '=', $pgardeId]]), 'id');


        if ($gradeList) {
            $where[] = ['gradeid', 'IN', $gradeList];
        } else {
            $where[] = ['gradeid', '=', -1];
        }
        $where[] = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['isvip', '=', 0],
//            ['paytype', '=', 1],
            ['tx_trans', '=', 1],
        ];

        $where[] = [
            ['sort', 'IN', $sort],
        ];
        $CourseModel = new CourseModel();
        $course1list = $CourseModel
            ->getCourseList($where, 8, 0, 0, 'id DESC')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'] ?? '';
                $item['user_nickname'] = $item->users['user_nickname'] ?? '';

                $CourseModel->setCourseStatus($item);

            });

        return $course1list;
    }

}


