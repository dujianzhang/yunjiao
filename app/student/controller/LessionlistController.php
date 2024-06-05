<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\Db;

/**
 * 选课中心
 */
class LessionlistController extends StudentBaseController
{

    //首页
    public function index()
    {

        //查找学段
        $gradeInfo = getGrade();
        $xdlist = [];
        foreach ($gradeInfo as $key => $value) {
            if($value['pid'] == 0){
                array_push($xdlist,$value);
            }
        }
        $xdid = 0; //第一个学段的id
        if ($xdlist) {
            $xdid = $xdlist[0]['id'];
        }

        //查找第一个学段的年级
        $njlist = array();
        $gradeid = 0;
        if ($xdid != 0) {
            foreach ($gradeInfo as $key => $value) {
                if($value['pid'] == $xdid){
                    array_push($njlist,$value);
                }
            }
            $gradeid = $njlist[0]['id'];

        }

        //科目
        $kmlist = Db::name('course_class')->field('id,name')->order('list_order asc')->select();
        $this->assign('kmlist', $kmlist);

        $sort = [1];
        $classid = NULL;
        $keywords = input('keywords') ?? NULL;

        $listAll = $this->getCourseList($sort, $gradeid, $classid, $keywords);
        $list = $listAll['data'];


        $this->assign([
            'lesslist' => $list,
            'navid' => 1,
            'PGradeList' => $xdlist,
            'gradeList' => $njlist,
            'classList' => $kmlist,

            'PGrade' => $xdid,
            'gradeid' => $gradeid,
            'keywords' => $keywords,
        ]);

        return $this->fetch();
    }

    //选择学段
    public function GetGrade()
    {
        $data = $this->request->param();

        $xdid = $data['xdid']; //学段id
        $kmid = $data['kmid']; //科目id
        $lbid = $data['lbid']; //类别id

        $info = array();
        $gradeinfo = Db::name('course_grade')->field('id,name')->where(['pid' => $xdid])->order('list_order asc')->select()->toArray();

        $info['njlist'] = $gradeinfo;
        $gradeid = 0;
        if ($gradeinfo) {
            $gradeid = $gradeinfo[0]['id'];
        }

        if ($lbid == 2) { //套餐
            $list = array();

            $nowtime = time();

            $list = Db::name('course_package')
                ->field('id,name,thumb,price,courseids,nums,des')
                ->where('gradeid =' . $gradeid)
                ->order('list_order asc,id desc')
                ->select()
                ->toArray();

            foreach ($list as $k => $v) {

                $courseid_a = $this->handelCourseids($v['courseids']);
                $isT = false;
                foreach ($courseid_a as $ks => $vs) {

                    $where = 'id = ' . $vs;
                    if ($kmid != 0) {
                        $where .= ' and classid =' . $kmid;
                    }

                    $lkd = Db::name('course')
                        ->field('id')
                        ->where('tx_trans',1)
                        ->where($where)
                        ->find();
                    if ($lkd) {
                        $isT = true;
                    }
                }

                if ($isT == false) {
                    unset($list[$k]);
                    continue;
                }

                $v['sort'] = -1;
                $v['thumb'] = get_upload_path($v['thumb']);
                $ismaterial = '0';
                $teacher = [];
                $courses = $this->getCourseids($v['courseids']);
                foreach ($courses as $k1 => $v1) {
                    $ishas = 0;
                    foreach ($teacher as $k2 => $v2) {
                        if ($v2['id'] == $v1['uid']) {
                            $ishas = 1;
                            break;
                        }
                    }
                    if ($ishas == 0) {
                        $t_a = [
                            'id' => $v1['uid'],
                            'user_nickname' => $v1['user_nickname'],
                            'avatar' => $v1['avatar'],
                        ];

                        $teacher[] = $t_a;
                    }

                    if ($v1['ismaterial'] == 1) {
                        $ismaterial = '1';
                    }

                }

                $v['teacher'] = $teacher;
                $v['ismaterial'] = $ismaterial;

                unset($v['courseids']);

                $list[$k] = $v;
            }

            $list = array_values($list);

            $list = array_slice($list, 0, 20);

        } else {  //除了套餐的其他

            $nowtime = time();

            $where = '';
            switch ($lbid) {
                case 4:
                    $where .= 'sort = 0';
                    break;
                case 1:
                    $where .= 'sort = 1';
                    break;
                case 3:
                    $where .= 'sort >= 2';
                    break;
            }


            $where .= ' and gradeid=' . $gradeid . ' and status>=1 and shelvestime<' . $nowtime;
            $list = Db::name('course')
                ->field('id,uid,sort,type,name,thumb,paytype,payval,status,starttime,lessons,islive,ismaterial,views,des')
                ->where($where)
                ->where('tx_trans',1)
                ->order('list_order asc,id desc')
                ->limit(0, 20)
                ->select();

            foreach ($list as $k => $v) {
                $v = handelInfo($v);

                $userinfo = getUserInfo($v['uid']);
                $v['user_nickname'] = $userinfo['user_nickname'];
                $v['avatar'] = $userinfo['avatar'];

                $list[$k] = $v;
            }
        }

        $info['lesslist'] = $list;
        $info['gradeid'] = $gradeid;

        $this->success('', '', $info);
    }

    //选择年级等操作
    public function ChooseNj()
    {
        $data = $this->request->param();

        $njid = $data['njid']; //学级id
        $kmid = $data['kmid']; //科目id
        $lbid = $data['lbid']; //类别id

        $info = array();
        $gradeid = $njid;


        if ($lbid == 2) { //套餐
            $list = array();

            $nowtime = time();

            $list = Db::name('course_package')
                ->field('id,name,thumb,price,courseids,nums,des')
                ->where('gradeid =' . $gradeid)
                ->order('list_order asc,id desc')
                ->select()
                ->toArray();

            foreach ($list as $k => $v) {

                $courseid_a = $this->handelCourseids($v['courseids']);
                $isT = false;
                foreach ($courseid_a as $ks => $vs) {

                    $where = 'id = ' . $vs;
                    if ($kmid != 0) {
                        $where .= ' and classid =' . $kmid;
                    }

                    $lkd = Db::name('course')
                        ->field('id')
                        ->where($where)
                        ->find();
                    if ($lkd) {
                        $isT = true;
                    }
                }

                if ($isT === false) {
                    unset($list[$k]);
                    continue;
                }

                $v['sort'] = -1;
                $v['thumb'] = get_upload_path($v['thumb']);
                $ismaterial = '0';
                $teacher = [];
                $courses = $this->getCourseids($v['courseids']);
                foreach ($courses as $k1 => $v1) {
                    $ishas = 0;
                    foreach ($teacher as $k2 => $v2) {
                        if ($v2['id'] == $v1['uid']) {
                            $ishas = 1;
                            break;
                        }
                    }
                    if ($ishas == 0) {
                        $t_a = [
                            'id' => $v1['uid'],
                            'user_nickname' => $v1['user_nickname'],
                            'avatar' => $v1['avatar'],
                        ];

                        $teacher[] = $t_a;
                    }

                    if ($v1['ismaterial'] == 1) {
                        $ismaterial = '1';
                    }

                }

                $v['teacher'] = $teacher;
                $v['ismaterial'] = $ismaterial;

                unset($v['courseids']);

                $list[$k] = $v;
            }

            $list = array_values($list);

            $list = array_slice($list, 0, 20);

        } else {  //除了套餐的其他

            $nowtime = time();

            $where = '';
            switch ($lbid) {
                case 4:
                    $where .= 'sort = 0';//内容
                    break;
                case 1:
                    $where .= 'sort = 1';//课程
                    break;
                case 3:
                    $where .= 'sort >= 2';//直播
                    break;
            }


            if ($kmid != 0) {
                $where .= ' and classid =' . $kmid;
            }
            $where .= ' and gradeid=' . $gradeid . ' and status>=1 and shelvestime<' . $nowtime;
            $list = Db::name('course')
                ->field('id,uid,sort,type,name,thumb,paytype,payval,status,starttime,lessons,islive,ismaterial,views,des')
                ->where($where)
                ->order('list_order asc,id desc')
                ->limit(0, 20)
                ->select();

            foreach ($list as $k => $v) {
                $v = handelInfo($v);

                $userinfo = getUserInfo($v['uid']);
                $v['user_nickname'] = $userinfo['user_nickname'];
                $v['avatar'] = $userinfo['avatar'];

                $list[$k] = $v;
            }
        }

        $info['lesslist'] = $list;


        $this->success('', '', $info);
    }

    //翻页操作
    public function getNextList()
    {
        $data = $this->request->param();

        $njid = $data['njid']; //学级id
        $kmid = $data['kmid']; //科目id
        $lbid = $data['lbid']; //类别id

        $p = $data['p']; //类别id

        $nums = 8;
        $start = ($p - 1) * $nums;


        $info = array();
        $gradeid = $njid;


        if ($lbid == 2) { //套餐
            $list = array();

            $nowtime = time();

            $list = Db::name('course_package')
                ->field('id,name,thumb,price,courseids,nums,des')
                ->where('gradeid =' . $gradeid)
                ->order('list_order asc,id desc')
                ->select()
                ->toArray();

            foreach ($list as $k => $v) {

                $courseid_a = $this->handelCourseids($v['courseids']);
                $isT = false;
                foreach ($courseid_a as $ks => $vs) {

                    $where = 'id = ' . $vs;
                    if ($kmid != 0) {
                        $where .= ' and classid =' . $kmid;
                    }

                    $lkd = Db::name('course')
                        ->field('id')
                        ->where($where)
                        ->find();
                    if ($lkd) {
                        $isT = true;
                    }
                }

                if ($isT === false) {
                    unset($list[$k]);
                    continue;
                }

                $v['sort'] = -1;
                $v['thumb'] = get_upload_path($v['thumb']);
                $ismaterial = '0';
                $teacher = [];
                $courses = $this->getCourseids($v['courseids']);
                foreach ($courses as $k1 => $v1) {
                    $ishas = 0;
                    foreach ($teacher as $k2 => $v2) {
                        if ($v2['id'] == $v1['uid']) {
                            $ishas = 1;
                            break;
                        }
                    }
                    if ($ishas == 0) {
                        $t_a = [
                            'id' => $v1['uid'],
                            'user_nickname' => $v1['user_nickname'],
                            'avatar' => $v1['avatar'],
                        ];

                        $teacher[] = $t_a;
                    }

                    if ($v1['ismaterial'] == 1) {
                        $ismaterial = '1';
                    }

                }

                $v['teacher'] = $teacher;
                $v['ismaterial'] = $ismaterial;

                unset($v['courseids']);

                $list[$k] = $v;
            }

            $list = array_values($list);

            $list = array_slice($list, $start, $nums);

        } else {  //除了套餐的其他

            $nowtime = time();

            $where = '';
            switch ($lbid) {
                case 4:
                    $where .= 'sort = 0';
                    break;
                case 1:
                    $where .= 'sort = 1';
                    break;
                case 3:
                    $where .= 'sort >= 2';
                    break;
            }


            if ($kmid != 0) {
                $where .= ' and classid =' . $kmid;
            }
            $where .= ' and gradeid=' . $gradeid . ' and status>=1 and shelvestime<' . $nowtime;
            $list = Db::name('course')
                ->field('id,uid,sort,type,name,thumb,paytype,payval,status,starttime,lessons,islive,ismaterial,views,des')
                ->where($where)
                ->order('list_order asc,id desc')
                ->limit($start, $nums)
                ->select();

            $count = Db::name('course')
                ->field('id')
                ->where($where)
                ->count();

            foreach ($list as $k => $v) {
                $v = handelInfo($v);

                $userinfo = getUserInfo($v['uid']);
                $v['user_nickname'] = $userinfo['user_nickname'];
                $v['avatar'] = $userinfo['avatar'];

                $list[$k] = $v;
            }
        }

        $info['lesslist'] = $list;
        $info['pages'] = ceil($count / $nums);
        $info['count'] = $count;

        $this->success('', '', $info);
    }


    /* 更具课程ID 获取 课程信息 */
    protected function getCourseids($courseid_s)
    {
        $course = [];

        $courseid_a = $this->handelCourseids($courseid_s);

        if (!$courseid_a) {
            return $course;
        }

        $courseid_s = implode(',', $courseid_a);

        $where = "id in ($courseid_s)";


        $nowtime = time();

        $list = Db::name('course')
            ->field('id,uid,sort,type,name,thumb,paytype,payval,status,starttime,lessons,islive,ismaterial,views,des')
            ->where($where)
            ->order('list_order asc,id desc')
            ->select();

        foreach ($list as $k => $v) {
            $v = handelInfo($v);

            $userinfo = getUserInfo($v['uid']);
            $v['user_nickname'] = $userinfo['user_nickname'];
            $v['avatar'] = $userinfo['avatar'];

            $list[$k] = $v;
        }

        $course = $list;
        return $course;
    }


    /* 处理套餐课程ID */
    protected function handelCourseids($courseid_s)
    {
        $courseid_a = [];
        $courseids_a = explode(',', $courseid_s);
        foreach ($courseids_a as $k => $v) {
            $courseid_a[] = preg_replace('/\[|\]/', '', $v);
        }

        return $courseid_a;
    }


    /**
     * 获取课程列表信息
     * @param $sort 课程形式 0内容1课程2语音直播3视频直播
     * @param $gradeid 年级ID
     * @param $keyWord 关键词 (课程/讲师)
     * @param $classid 科目分类
     * @param $page 分页页数
     * @param $pageNum 每页数量
     * @param $additionWhere 其他条件
     */
    protected function getCourseList($sort = [], $gradeid = NULL, $classid = NULL, $keyWord = NULL, $page = 1, $pageNum = 8, $additionWhere = [])
    {
        $where = [];
        if ($sort) {
            $where[] = ['sort', 'IN', $sort];
        }
        if ($gradeid) {
            $where[] = ['gradeid', '=', (int)$gradeid];
        }
        if ($classid) {
            $where[] = ['classid', '=', (int)$classid];
        }
        $where[] = ['status', '=', 1];
        $where[] = ['shelvestime', '<=', time()];
        $where[] = ['tx_trans', '=', 1];

        $userIdArr = [];
        if ($keyWord != '') {
            $userWhere = [];
            $userWhere[] = ['user_nickname', 'like', "%{$keyWord}%"];

            $userInfo = Db::name('users')->field('id')->where($userWhere)->select()->toArray();
            $userIdArr = array_column($userInfo, 'id');
        }

        $list = Db::name('course')
            ->field('id,type,uid,starttime,payval,islive,thumb,name,lessons,ismaterial,paytype,sort')
            ->where(function ($query) use ($keyWord, $userIdArr) {
                if ($keyWord != '') {
                    $query->whereOr('name', 'LIKE', "%{$keyWord}%");
                }
                if ($userIdArr) {
                    $query->whereOr('uid', 'IN', $userIdArr);
                }
            })
            ->where($additionWhere)
            ->where($where)
            ->page($page, $pageNum)
//            ->fetchSql(true)
            ->order('list_order ASC,id DESC')
            ->select()
            ->toArray();

        foreach ($list as $k => $v) {
            $v = handelInfo($v);
            $userinfo = getUserInfo($v['uid']);
            $v['user_nickname'] = $userinfo['user_nickname'];
            $v['avatar'] = $userinfo['avatar'];

            $list[$k] = $v;
        }

        $count = Db::table('cmf_course')
            ->field('id')
            ->alias('course')
            ->where(function ($query) use ($keyWord, $userIdArr) {
                if ($keyWord != '') {
                    $query->whereOr('name', 'LIKE', "%{$keyWord}%");
                }
                if ($userIdArr) {
                    $query->whereOr('uid', 'IN', $userIdArr);
                }
            })
            ->where($additionWhere)
            ->where($where)
            ->count();

        $res = [
            'data' => $list,
            'pages' => ceil($count / $pageNum),
            'count' => $count,
        ];
        return $res;
    }

    /**
     * 获取套餐列表
     * @param null $gradeid
     * @param null $classId
     * @param int $page
     * @param int $pageNum
     * @param array $additionWhere
     */
    protected function getPackAgeList($gradeid = NULL, $classId = NULL, $page = 1, $pageNum = 8, $additionWhere = [])
    {
        $where = [];
        if ($gradeid) {
            $where[] = ['gradeid', '=', (int)$gradeid];
        }
        $list = Db::table('cmf_course_package')
            ->field('id,name,thumb,price,courseids,nums,des')
            ->where($where)
            ->where($additionWhere)
            ->page($page, $pageNum)
            ->select();

        foreach ($list as $k => $v) {

            $v['sort'] = -1;
            $v['thumb'] = get_upload_path($v['thumb']);
            $ismaterial = '0';
            $teacher = [];
            $courses = $this->getCourseids($v['courseids']);
            foreach ($courses as $k1 => $v1) {
                $ishas = 0;
                foreach ($teacher as $k2 => $v2) {
                    if ($v2['id'] == $v1['uid']) {
                        $ishas = 1;
                        break;
                    }
                }
                if ($ishas == 0) {
                    $t_a = [
                        'id' => $v1['uid'],
                        'user_nickname' => $v1['user_nickname'],
                        'avatar' => $v1['avatar'],
                    ];

                    $teacher[] = $t_a;
                }

                if ($v1['ismaterial'] == 1) {
                    $ismaterial = '1';
                }

            }

            $v['teacher'] = $teacher;
            $v['ismaterial'] = $ismaterial;

            unset($v['courseids']);

            $list[$k] = $v;
        }
        $count = Db::table('cmf_course_package')
            ->field('id')
            ->where($where)
            ->where($additionWhere)
            ->page($page, $pageNum)
            ->count();

        $res = [
            'data' => $list,
            'pages' => ceil($count / $pageNum),
            'count' => $count,
        ];
        return $res;
    }

    /**
     * 课程/套餐获取下一页
     */
    function ajaxGetNextList2()
    {
        $param = input();
        $param['gradeid'] = session('gradeiddd') ?? 0;

        if ($param['sortStatus'] == 2) {//套餐

            $additionWhere = [];
            if ($param['keywords']) {
                $additionWhere[] = ['name', 'LIKE', '%' . $param['keywords'] . "%"];
            }
            $resPack = $this->getPackAgeList((int)$param['gradeid'], (int)$param['classid'], (int)$param['page'], 8, $additionWhere);
            $newRes = [
                'data' => [
                    'lesslist' => $resPack['data'],
                    'pages' => $resPack['pages'],
                    'count' => $resPack['count']
                ]
            ];
            return $newRes;
        }

        $kind = [
            1 => [1],//课程
            3 => [2, 3, 4],//直播
            4 => [0],//内容
        ];
        $sortStatus = $kind[(int)$param['sortStatus']] ?? -1;
        if ($sortStatus == -1) {
            return [];
        }
        $res = $this->getCourseList($sortStatus, (int)$param['gradeid'], (int)$param['classid'] <= 0 ? NULL : (int)$param['classid'], $param['keywords'], (int)$param['page']);
        $newRes = [
            'data' => [
                'lesslist' => $res['data'],
                'pages' => $res['pages'],
                'count' => $res['count']
            ]
        ];
        return $newRes;
    }
}


