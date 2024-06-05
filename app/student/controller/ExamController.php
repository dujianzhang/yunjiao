<?php

namespace app\student\controller;


use cmf\controller\StudentBaseController;
use think\Db;
use think\facade\Session;
use think\facade\Request;

/**
 * 在线考试
 * Class examController
 * @package app\student\controller
 */
class examController extends StudentBaseController
{

    protected $testStatus = [//考试状态
        0 => '未开始',
        1 => '开始考试',//待做
        2 => '开始考试',//未付费
        3 => '已做完',
        4 => '继续答题',
        5 => '已过期',
    ];

    protected $canEnterThe = [1, 3, 4];//可以进入考试界面的 考试状态

    protected $noEntry = [0, 2, 5];//不可以进入考试界面的 考试状态

    protected $questionType = [//考试试题类型
        0 => '判断',
        1 => '单选',
        2 => '定项多选',
        3 => '简答',
        4 => '填空',
        5 => '不定项多选'
    ];

    public function initialize()
    {
        parent::initialize();
        //判断有没有登录
        $this->checkMyLogin();
    }

    function index()
    {
//        print_r(session('student'));
        $testClassList = $this->getQuestionClass();

        $userinfo = session('student');
        $testList = $this->getTestListByParam($userinfo['gradeid']);
        $this->assign([
            'navid' => 4,
            'lesslist' => $testList,
            'isMore' => 1,
            'testClassList' => $testClassList,
        ]);
        return $this->fetch();
    }

    /**
     * 试题分类
     */
    protected function getQuestionClass()
    {
        $s = 'App.Tests.GetClass';
        return $this->requestInterface($s);
    }

    public function ajaxGetClassList()
    {
        $catid = input('catid/d');
        $gradeid = session('student.gradeid');
        $page = input('page/d');
        return $this->getTestListByParam($gradeid, $catid, ++$page);
    }

    /**
     * 通过条件请求 考试列表信息
     * @param int $gradeid
     * @param int $catid
     * @param int $page
     */
    protected function getTestListByParam($gradeid = 0, $catid = 0, $page = 1)
    {
        $queryData = [
            'gradeid' => $gradeid,
            'catid' => $catid,
            'p' => $page,
        ];
        $s = 'App.Tests.GetList';
        $testList = $this->requestInterface($s, $queryData);

        return $testList;
    }


    /**
     * 试题详情
     * @return mixed|string
     */
    public function detail()
    {
        $id = input('id/d');
        $queryData = [
            'testsid' => $id,
        ];
        $s = 'App.Tests.GetTests';
        $info = $this->requestInterface($s, $queryData);
        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }
        if (!isset($info['data']['info'][0])) {
            $this->error('数据请求失败');
        }
        $buton_status = in_array($info['data']['info'][0]['status'], $this->canEnterThe) ? 'yes' : 'no';
        if ($info['data']['info'][0]['status'] == 2) {
            $buton_status = 'yes';
        }
        $this->assign([
            'info' => $info['data']['info'],
            'navid' => 4,
            'buton_text' => $this->testStatus[$info['data']['info'][0]['status']],
            'buton_status' => $buton_status,
            'status' => $info['data']['info'][0]['status'],
        ]);
        return $this->fetch();
    }

    /**
     * 考试页面 (考试中)
     */
    public function theExamWillBegin()
    {
        $id = input('id/d');
        $testCheck = $this->ajaxCheckTest($id);
        if ($testCheck['code'] == 1) {//无法进入考试
            $this->error($testCheck['msg']);
        }
        $list = $this->getTopicList($id, $testCheck['type'] ?? NULL);
        $info = $list['data']['info'][0]['list'] ?? [];

        foreach ($info as $key => $value) {
            $info[$key]['rs_user']['rs_temp'] = $value['rs_user']['rs'] ?? '';//临时答案存放
        }

        $list['data']['info'][0]['list'] = $info;
        $this->assign([
            'navid' => 4,
            'topic_list' => json_encode($list, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->fetch();
    }

    /**
     *  用于获取试题题目
     * @param $testsid 试题id
     * @param $type 试题类型
     */
    public function getTopicList($testsid, $type)
    {
        $queryData = [
            'testsid' => $testsid,
            'type' => $type,
        ];
        $s = 'App.Tests.GetTopic';

        return $this->requestInterface($s, $queryData);
    }


    /**
     *  检测能否考试接口
     * @param $type
     * @param $testsid
     */
    protected function getTestCheck($type, $testsid)
    {
        $queryData = [
            'testsid' => $testsid,
            'type' => $type,
        ];
        $s = 'App.Tests.Check';

        return $this->requestInterface($s, $queryData);
    }

    /**
     *  检测是否能进入考试
     * @param $id 考试id
     * @return array
     */
    public function ajaxCheckTest($id)
    {
        $testQueryData = [
            'testsid' => $id,
        ];
        $testInfoS = 'App.Tests.GetTests';
        $data = $this->requestInterface($testInfoS, $testQueryData);
        $info = $data['data']['info'];

        if ($data['data']['code'] != 0) {
            return [
                'code' => 1,
                'msg' => '接口请求失败:' . $data['data']['msg'],
                'status' => $info[0]['status'],
            ];
        }

        if (in_array($info[0]['status'], $this->noEntry)) {//不可进入考试
            return [
                'code' => 1,
                'msg' => '此试题' . $this->testStatus[$info[0]['status']],
                'status' => $info[0]['status'],
            ];
        }

        if (in_array($info[0]['status'], $this->canEnterThe)) {//可进入考试界面
            if ($info[0]['status'] == 4) {//继续做
                $type = 1;
            } else {// 新做/重做
                $type = 0;
            }

            $testCheckInfo = $this->getTestCheck($type, $id);
            if ($testCheckInfo['data']['code'] == 0) {//可以去做题
                return [
                    'code' => 0,
                    'msg' => '',
                    'status' => $info[0]['status'],
                    'type' => $type,
                ];
            } else {//不可以去做题
                return [
                    'code' => 1,
                    'msg' => '此试题' . $testCheckInfo['data']['msg'],
                    'status' => $info[0]['status'],
                ];
            }
        }


    }

    /**
     * 答案隐藏查看详情
     */
    public function testDetail()
    {
        $testId = input('id/d');
        $infoData = $this->getScore($testId);
        $info = $infoData['data']['info'][0];
        if ($infoData['data']['code'] != 0) {
            $this->error($info['data']['msg'], url('student/exam/index'));
        }

        $list = $this->getTopicList($testId, 3);

        $this->assign([
            'navid' => 4,
            'topic_list' => json_encode($list, JSON_UNESCAPED_UNICODE),
        ]);
        return $this->fetch();
    }

    /**
     * ajax请求提交试题答案
     */
    public function ajaxSubmitAnswersToQuestions()
    {
        $testsid = input('testsid/d');
        $result = input('result');
        return $this->submitAnswersToQuestions($testsid, $result);
    }

    /**
     * 请求接口 提交试题答案
     * @param $testsid 试题ID
     * @param $result 答案json串 {"qid":"题号","rs":"1","img":""}
     */
    protected function submitAnswersToQuestions($testsid, $result)
    {
        $testQueryData = [
            'testsid' => $testsid,
            'result' => json_encode($result),
        ];
        $testInfoS = 'App.Tests.SetAnswer';
        $info = $this->requestInterface($testInfoS, $testQueryData);
        return $info;
    }

    /**
     * 请求接口添加错题本 用于题库的题目添加/删除错题本
     * @param $type 类型 1在线测试2考试3收藏
     * @param $testsid 试题ID, 0在线刷题
     * @param $qid 题目ID/题目编号
     * @param $ans 在线测试/收藏 题目答案json {"rs":"1"}
     * @param $status 状态 1添加2删除
     * @return mixed
     */
    protected function addIncorrectText($type, $testsid, $qid, $ans, $status)
    {
        $testQueryData = [
            'type' => $type,
            'testsid' => $testsid,
            'qid' => $qid,
            'ans' => $ans,
            'status' => $status,
        ];
        $testInfoS = 'App.Wrongbook.SetWrong';
        $info = $this->requestInterface($testInfoS, $testQueryData);
        return $info;
    }

    /**
     * 请求接口交卷
     * @param $content $testsid
     */
    protected function handIn($testsid)
    {
        $testQueryData = [
            'testsid' => $testsid,
        ];
        $testInfoS = 'App.Tests.EndTests';
        $info = $this->requestInterface($testInfoS, $testQueryData);
        return $info;
    }

    /**
     * 交卷
     * @param $content $testsid
     */
    public function ajaxHandIn()
    {
        $testsid = input('testsid/d');
        $info = $this->handIn($testsid);
        return $info;
    }

    /**
     * 请求接口添加错题本
     * @param $content content
     * @param $answer answer
     * @param $des des
     */
    public function ajaxAddIncorrectText()
    {
        $type = input('type/d');
        $testsid = input('testsid/d');
        $qid = input('qid/d');
        $ans = input('ans');
        $status = input('status/d');
        $ansNew = ['rs' => $ans];

        $info = $this->addIncorrectText($type, $testsid, $qid, json_encode($ansNew), $status);
        return $info;
    }

    /**
     * 交卷后页面
     */
    public function showHandIn()
    {
        $testId = input('id/d');
        $data = $this->getScore($testId);
        if ($data['data']['code'] != 0) {
            $this->error($data['data']['msg'], url('student/exam/index'));
        }
        $info = $data['data']['info'];
        $isviewArr = [
            0 => '待批阅',
            1 => '已批阅',
        ];

        if ($info[0]['isview'] == 0) {//待批阅
            $isviewText = $isviewArr[$info[0]['isview']];//得分显示待批阅
            $showans_status = 'dis';
        } else {//已批阅
            $showans_status = '';
            //if ($info[0]['showrs'] == 1) {//是否显示成绩 0否1是
                $isviewText = $info[0]['score'];
            //} else {
                //$isviewText = '已隐藏';
            //}
        }


        $radar = $data['data']['info'][0]['radar'];
        $questionType = [];
        $rankEnd = [];

        foreach ($radar as $key => $value) {
            $tmpArr = [
                'name' => $value['name'],
                'max' => (int)$value['total']
            ];
            array_push($questionType,$tmpArr);
            array_push($rankEnd,(int)$value['score']);
        }


        $this->assign([
            'navid' => 4,
            'info' => $info,
            'isview_text' => $isviewText,
            'isview' => $info[0]['isview'],
            'showans_status' => $showans_status,
            'showans' => $info[0]['showans'],//答案隐藏
            'showq' => $info[0]['showq'],//是否隐藏题目
            'radar' => $radar,
            'questionType' => json_encode($questionType),
            'rankEnd' => json_encode($rankEnd),
        ]);
        return $this->fetch();
    }


    /**
     *
     * 查看成绩
     * @param $testsid
     */
    protected function getScore($testsid)
    {
        $testQueryData = [
            'testsid' => $testsid,
        ];
        $testInfoS = 'App.Tests.GetScore';
        $info = $this->requestInterface($testInfoS, $testQueryData);
        return $info;
    }

    /**
     * 考试全部解析
     */
    public function allAnalytical()
    {
        $testId = input('id/d');
//        $testId = 16;
        $infoData = $this->getScore($testId);
        $info = $infoData['data']['info'][0];
        if ($infoData['data']['code'] != 0) {
            $this->error($info['data']['msg'], url('student/exam/index'));
        }
        if ($info['isview'] == 0) {//待批阅
            $info['showans'] = 0;//不显示答案解析
        }
        if ($info['showans'] == 0) {
            $this->error('此试题不允许查看题目解析', url('student/exam/index'));
        }


        $list = $this->getTopicList($testId, 3);


        $this->assign([
            'navid' => 4,
            'topic_list' => json_encode($list, JSON_UNESCAPED_UNICODE),
        ]);
        return $this->fetch('sanalytical');
    }

    /**
     * 考试错误解析
     */
    public function errorAnalytical()
    {
        $testId = input('id/d');
//        $testId = 16;
        $infoData = $this->getScore($testId);
        $info = $infoData['data']['info'][0];

        if ($infoData['data']['code'] != 0) {
            $this->error($info['data']['msg'], url('student/exam/index'));
        }
        if ($info['isview'] == 0) {//待批阅
            $info['showans'] = 0;//不显示答案解析
        }
        if ($info['showans'] == 0) {
            $this->error('此试题不允许查看题目解析', url('student/exam/index'));
        }


        $list = $this->getTopicList($testId, 2);


        $this->assign([
            'navid' => 4,
            'topic_list' => json_encode($list, JSON_UNESCAPED_UNICODE),

        ]);
        return $this->fetch('sanalytical');
    }


    /**
     * 在线刷题
     */
    public function brushQuestionOnline()
    {
        $class = input('class') ?? [];
        if ($class) {
            $gradeId = array_keys($class)[0] ?? 0;
        }
        $list = $this->getTheOnlineTestQuestions(array_values($class)[0] ?? [], $gradeId ?? 0);
        $info = $list['data']['info'] ?? [];
        $tmp = ['rs' => ''];

        foreach ($info as $key => $value) {
            $info[$key]['rs_user'] = $tmp;
            $info[$key]['qid'] = $value['id'];
            $info[$key]['isans'] = '0';
        }
        $list['data']['info'] = $info;

        $this->assign([
            'navid' => 4,
            'topic_list' => json_encode($list, JSON_UNESCAPED_UNICODE),
            'hint' => '暂无题目,先去试试其他试题吧',
            'wrong_topic_this_type' => 1,//在线刷题存入错题本
        ]);
        return $this->fetch();
    }

    /**
     * 获取题目分类
     */
    protected function getClassList()
    {
        $S = 'App.Topic.GetClassList';
        $list = $this->requestInterface($S);
        return $list;
    }

    /**
     * 获取 在线测试 试题分类
     */
    public function ajaxGetClassLists()
    {
        return $this->getClassList();
    }

    /**
     * 获取在线试题列表
     * @param array $classids 分类ID,多个ID用,拼接
     * @param array $gradeid 班级ID
     * @return mixed
     */
    protected function getTheOnlineTestQuestions($classids, $gradeid = 0)
    {
        $S = 'App.Topic.GetList';
        $testQueryData = [
            'classids' => implode(',', $classids),
            'gradeid' => $gradeid,
        ];
        $list = $this->requestInterface($S, $testQueryData);
        return $list;
    }

    /**
     * 试题收藏操作接口
     * @param $testid 试题ID，在线测试传0
     * @param $qid 题目ID，试题中传 题号1开始
     * @param $type 类型 1添加2取消
     */
    protected function setFav($testid, $qid, $type)
    {
        $S = 'App.Topic.SetFav';
        $testQueryData = [
            'testid' => $testid,
            'qid' => $qid,
            'type' => $type,
        ];
        $list = $this->requestInterface($S, $testQueryData);
        return $list;
    }

    /**
     * 在线刷题试题收藏
     */
    public function ajaxSetFav()
    {
        $testid = 0;
        $qid = input('qid/d');
        $type = input('type/d');
        if ($qid <= 0) {
            return [
                "ret" => 200,
                "data" => [
                    "code" => 1,
                    "msg" => "收藏失败",
                    "info" => []
                ],
                "msg" => ""
            ];
        }
        return $this->setFav($testid, $qid, $type);
    }

    /**
     * 我的考试
     */
    public function myTest()
    {
        $questionClass = $this->getQuestionClass();
        $baseinfo = $this->getBaseInfo();

        $catid = 0;
        $page = 1;

        $questionList = $this->getMyTests($catid, $page);

        $this->assign([
            'navid' => -1,
            'mynavid' => 14,
            'baseinfo' => $baseinfo['data']['info'][0],
            'questionClass' => $questionClass['data']['info'],
            'questionList' => $questionList['data']['info'],
        ]);
        return $this->fetch();
    }

    /**
     * 我的考试信息
     * @param $catid
     * @param $page
     * @return mixed
     */
    protected function getMyTests($catid, $page)
    {
        $s = 'App.Tests.GetMyTests';
        $testQueryData = [
            'catid' => $catid,
            'p' => $page,
        ];
        return $this->requestInterface($s, $testQueryData);
    }

    /**
     * 我的考试 获取更多
     */
    public function ajaxMyTestGetMore()
    {
        $page = input('page/d');
        $catid = input('catid/d');
        return $this->getMyTests($catid, $page);
    }

    /**
     * 获取用户信息
     */
    protected function getBaseInfo()
    {
        $s = 'User.GetBaseInfo';
        return $this->requestInterface($s);
    }
}
