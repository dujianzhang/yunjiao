<?php


namespace app\student\controller;

use app\student\model\CourseModel;
use cmf\controller\StudentBaseController;
use think\Db;

/**
 * 名师堂
 */
class TeachersController extends StudentBaseController
{

    //首页
    public function index()
    {

        $userinfo = session('student');

        //查找科目
        $kmlist = getSignoryList();

        $classid = 0;
        if ($kmlist) {
            $classid = $kmlist[0]['id'];
        }

        $this->assign('classid', 0);
        $this->assign('kmlist', $kmlist);

        //查看对应的老师
        $where = 'signoryid = ' . $classid;
        $where = '';
        $techerslist = $this->GetTeachers(1, $where);

        $this->assign('techerslist', $techerslist);

        $isMore = 0;
        if (count($techerslist) >= 20) {
            $isMore = 1;
        }

        $this->assign('isMore', $isMore);
        $this->assign('navid', 2);
        return $this->fetch();
    }


    //切换科目老师
    public function chooseTeachers()
    {
        $data = $this->request->param();

        if (isset($data['p'])) {
            $p = $data['p'];
        } else {
            $p = 1;
        }
        $classid = $data['id'];

        $where = '';
        if($classid > 0){
            $where = 'signoryid = ' . $classid;
        }
        $techerslist = $this->GetTeachers($p, $where);

        $this->success('', '', $techerslist);
    }


    //获取老师列表
    protected function GetTeachers($p, $where)
    {

        if ($p < 1) {
            $p = 1;
        }

        $nums = 20;

        $start = ($p - 1) * $nums;

        $list = Db::name('users')
            ->field('id,user_nickname,avatar,avatar_thumb,sex,signature,birthday,type,signoryid,identity,school,experience,feature')
            ->where('type=1 and user_status!=0 ')
            ->where($where)
            ->order('courses desc,list_order asc')
            ->limit($start, $nums)
            ->select()
            ->toArray();

        $CourseModel = new CourseModel();

        $userinfo = session('student');
        foreach ($list as $k => $v) {
            $v = handleUser($v);

            if ($userinfo) {
                $v['isAttent'] = isAttent($userinfo['id'], $v['id']);
            } else {
                $v['isAttent'] = 0;
            }
            $number = $CourseModel->getCountByteacher($v['id']);
            $v['course_number']  = $number['course_number'];
            $v['student_number']  = $number['student_number'];

            $list[$k] = $v;
        }


        return $list;
    }


    //老师详情页面
    public function detail()
    {
        $data = $this->request->param();


        //判断有没有登录
        $this->checkMyLogin();


        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $touid = $data['touid'];

        //老师信息
        $url = $this->siteUrl . '/api/?s=Teacher.GetHome&uid=' . $uid . '&token=' . $token . '&touid=' . $touid;

        $info = curl_get($url);
        $info = $info['data']['info'][0];

        $this->assign('info', $info);
        //老师课程
        $url = $this->siteUrl . '/api/?s=Course.GetTeacherCourse&uid=' . $uid . '&token=' . $token . '&touid=' . $touid;

        $lesslist = curl_get($url);

        $lesslist = $lesslist['data']['info'];

        $this->assign('lesslist', $lesslist);

        $isMore = 0;
        if (count($lesslist) >= 10) {
            $isMore = 1;
        }

        $this->assign('isMore', $isMore);
        $this->assign('touid', $touid);
        $this->assign('navid', -1);
        return $this->fetch();
    }

    public function teachers()
    {
        $this->assign([
            'navid' => 5,
        ]);
        return $this->fetch();
    }

}


