<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\Db;
use app\student\model\UsersModel;
use function GuzzleHttp\Psr7\build_query;

/**
 * 我的
 * Class MineController
 * @package app\student\controller
 */
class MineController extends StudentBaseController
{
    //我购买的
    public function mybuy()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];


        //已购买的课程
        $url = $this->siteUrl . '/api/?s=Course.GetMyBuy&uid=' . $uid . '&token=' . $token . '&p=1';

        $list = curl_get($url);

        $isMore = 0;
        if (count($list['data']['info']) >= 20) {
            $isMore = 1;
        }
        $this->assign('isMore', $isMore);
        $this->assign('lists', $list['data']['info']);


        $this->assign('mynavid', 13);
        $this->assign('navid', -1);

        return $this->fetch();
    }

    //我的课程里面我的课程
    public function index()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        //我的全部课程
        $url = $this->siteUrl . '/api/?s=Course.GetMyCourse&uid=' . $uid . '&token=' . $token . '&type=0';

        $bothlist = curl_get($url);
        $isMore0 = 0;
        if (count($bothlist['data']['info']) >= 20) {
            $isMore0 = 1;
        }
        $this->assign('isMore0', $isMore0);
        $this->assign('bothlist', $bothlist['data']['info']);

        $this->assign('mynavid', 1);
        $this->assign('navid', -1);

        return $this->fetch();
    }

    //我的课程里面我的直播课程
    public function livelist()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        //购买的直播
        $url = $this->siteUrl . '/api/?s=Course.GetMyBuy&uid=' . $uid . '&token=' . $token . '&p=1&sort=2';

        $bothlist = curl_get($url);
        $isMore0 = 0;
        if (count($bothlist['data']['info']) >= 20) {
            $isMore0 = 1;
        }
        $this->assign('isMore0', $isMore0);
        $this->assign('bothlist', $bothlist['data']['info']);


        $this->assign('mynavid', 2);
        $this->assign('navid', -1);
        return $this->fetch();
    }

    //我的课程里面我的内容课程
    public function contlist()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        //内容
        $url = $this->siteUrl . '/api/?s=Course.GetMyBuy&uid=' . $uid . '&token=' . $token . '&p=1&sort=-1';

        $bothlist = curl_get($url);
        $isMore0 = 0;
        if (count($bothlist['data']['info']) >= 20) {
            $isMore0 = 1;
        }
        $this->assign('isMore0', $isMore0);
        $this->assign('bothlist', $bothlist['data']['info']);


        $this->assign('mynavid', 3);
        $this->assign('navid', -1);
        return $this->fetch();
    }


    //我的课件
    public function myclassware()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $where = [
            'uid' => $userinfo['id'],
            'sort' => 1,
            'status' => 1,
        ];

        $newcourseids = '0';
        $alllist = Db::name('course_users')
            ->field('liveuid,courseid')
            ->where($where)
            ->select()
            ->toArray();


        if ($alllist) {
            $liveuids = [];
            $courseids = [];
            foreach ($alllist as $k => $v) {
                $liveuids[] = $v['liveuid'];
                $courseids[] = $v['courseid'];
            }


            $liveuids = array_unique($liveuids);
            $courseids = array_unique($courseids);

            /* 讲师ID */
            $liveuids_s = implode(',', $liveuids);
            $courseids_s = implode(',', $courseids);

            $where6 = '';
            $where4 = 'type=1 and id in (' . $liveuids_s . ')';
            $uidlist = Db::name('users')->field('id')->where($where4)->select()->toArray();

            if ($uidlist) {
                $uidlist_a = [];
                foreach ($uidlist as $k => $v) {
                    $uidlist_a[] = $v['id'];
                }
                $uidlist_a = array_unique($uidlist_a);
                $uidlist_s = implode(',', $uidlist_a);
                $where6 = 'liveuid in (' . $uidlist_s . ')';
            }

            $where5 = 'sort=1 and id in (' . $courseids_s . ')';


            $courseidlist = Db::name('course')->field('id,uid')->where($where5)->select()->toArray();
            if ($courseidlist) {
                $courseidlist_a = [];
                foreach ($courseidlist as $k => $v) {
                    $courseidlist_a[] = $v['id'];
                }
                $courseidlist_a = array_unique($courseidlist_a);
                $courseidlist_s = implode(',', $courseidlist_a);
                if ($where6) {
                    $where6 .= ' or courseid in (' . $courseidlist_s . ')';
                } else {
                    $where6 = 'courseid in (' . $courseidlist_s . ')';
                }
            }


            if ($where6) {
                $where7 = [];

                foreach ($where as $k => $v) {
                    $where7[] = $k . '=' . $v;
                }

                $where7_s = implode(' and ', $where7);

                $where = $where7_s . ' and ' . $where6;

                $list = Db::name('course_users')->field('courseid')->where($where)->select()->toArray();


                $newcourseids = [];
                foreach ($list as $k => $v) {
                    $newcourseids[] = $v['courseid'];
                }


                $newcourseids = array_unique($newcourseids);
                $newcourseids = implode(',', $newcourseids);
            }
        }


        $newcourseids = $newcourseids;
        //课件列表
        $endwhere = 'courseid in (' . $newcourseids . ')';
        $warelist = Db::name('course_ware')->field('name,url')->where($endwhere)->select()->toArray();

        foreach ($warelist as $k => $v) {
            $type = explode('.', $v['url']);
            $name = end($type);
            $v['type'] = $name;
            $v['url'] = get_upload_path($v['url']);
            $warelist[$k] = $v;
        }

        $this->assign('warelist', $warelist);
        $this->assign('mynavid', 4);
        $this->assign('navid', -1);
        return $this->fetch();
    }


    //我的作业
    public function homework()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $data = $this->request->param();
        $userinfo = session('student');
        $type = $data['type'];
        $mynavid = $data['mynavid'];

        $uid = $userinfo['id'];
        $token = $userinfo['token'];


        $this->assign('type', $type);
        if ($type == 0) { //待完成作业
            $status = 0;
            //作业信息
            $url = $this->siteUrl . '/api/?s=Task.GetWait&uid=' . $uid . '&token=' . $token;
        } else if ($type == 1) { //已完成
            $status = 1;
            //作业信息
            $url = $this->siteUrl . '/api/?s=Task.GetComplete&uid=' . $uid . '&token=' . $token;
        } else { //已超时
            $status = 2;
            //作业信息
            $url = $this->siteUrl . '/api/?s=Task.GetTimeout&uid=' . $uid . '&token=' . $token;
        }

        $work_info = curl_get($url);
        $lists = $work_info['data']['info'];
        foreach ($lists as $k => $v) {
            $v['status'] = $status;
            $lists[$k] = $v;
        }

        $isMore = 0;
        if (count($lists) >= 20) {
            $isMore = 1;
        }
        $this->assign('isMore', $isMore);
        $this->assign('lists', $lists);


        $this->assign('mynavid', $mynavid);
        $this->assign('navid', -1);

        return $this->fetch();
    }

    //作业详情
    public function homeworkinfo()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $data = $this->request->param();
        $userinfo = session('student');
        $id = $data['id'];

        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $select_list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
        $select_color = ['#38DAD0', '#FFAD31', '#FF6825', '#3B85F3', '#38DAA6', '#38DAD0', '#FFAD31', '#FF6825', '#3B85F3', '#38DAA6', '#38DAD0', '#FFAD31', '#FF6825', '#3B85F3'];
        $type_list = ['判断题', '单选题', '定项多选题', '简答题', '填空题', '不定项多选题'];
        $pan_list = ['错', '对'];

        $url = $this->siteUrl . '/api/?s=Task.GetTask&uid=' . $uid . '&token=' . $token . '&taskid=' . $id;
        $work_info = curl_get($url);
        $stateOfScores = true; //默认显示分(有一个简答题 为未批阅状态 就不现实得分)
        if ($work_info['data']['code'] != '0') {
            $this->error($work_info['data']['msg'], url('/student/index/index'));
        }

        //先 生成一下提交作业时答案的格式
        $answerlist = []; //未作答的答案格式
        $answerlist_over = []; //要订正的答案格式
        foreach ($work_info['data']['info'][0]['answer'] as $k => $v) {

            if ($v['type'] == '1' || $v['type'] == '2' || $v['type'] == '5') { //选择题，处理一下答案
                $arr = [];
                for ($i = 0; $i < $v['nums']; $i++) {
                    $arr[] = $i;
                }
                $v['arr'] = $arr;

                if ($work_info['data']['info'][0]['status'] == 1 || $work_info['data']['info'][0]['status'] == 3) {
                    $arr1 = explode(',', $v['rs']);
                    $v['arr1'] = $arr1;

                    $work_info['data']['info'][0]['answer'][$k] = $v;

                    $arr2 = explode(',', $v['rs_user']['rs']);
                    $v['rs_user']['arr1'] = $arr2;
                }


                $work_info['data']['info'][0]['answer'][$k] = $v;
            }

            if ($work_info['data']['info'][0]['status'] == 1 && $v['type'] == 3) {
                $answerlist_over[$k + 1] = ['rs' => $v['rs_user']['rs'], 'img' => $v['rs_user']['img'], 'audio' => $v['rs_user']['audio'], 'audio_time' => $v['rs_user']['audio_time']];
            }
            if ($v['type'] == 3 && $work_info['data']['info'][0]['status'] != 3) { //有简单题 并且 未批阅 不现实成绩
                $stateOfScores = false;
            }
            $answerlist[$k + 1] = ['rs' => '', 'img' => '', 'audio' => '', 'audio_time' => ''];
        }

        $answerlist_over = json_encode($answerlist_over);
        $this->assign('answerlist_over', $answerlist_over);

        $answerlist = json_encode($answerlist);
        $this->assign('answerlist', $answerlist);
        $this->assign('work_info', $work_info['data']['info'][0]);

        $this->assign('pan_list', $pan_list);
        $this->assign('select_list', $select_list);
        $this->assign('select_color', $select_color);
        $this->assign('type_list', $type_list);
        $this->assign('navid', -1);
        $this->assign('stateOfScores', $stateOfScores);

        return $this->fetch();
    }

    /* 语音 */
    public function addAudio()
    {
        $file = $_FILES['file'];
        /* var_dump($file); */
        if (!$file) {
            $this->error('请先录制语音');
        }
        $_FILES['file']['name'] = $_FILES['file']['name'] . '.mp3';

        $res = upload($file, 'audio');

        if ($res['code'] != 0) {
            $this->error($res['msg']);
        }
        $url = get_upload_path($res['url']);
        $this->success("发送成功！", '', $url);
    }


    //错题本
    public function wrongbook()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $data = $this->request->param();
        $userinfo = session('student');
        $type = $data['type'];
        $mynavid = $data['mynavid'];

        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $gradeid = session('gradeid');

        $type_list = ['判断题', '单选题', '定项多选题', '简答题', '填空题', '不定项多选题'];
        $this->assign('type_list', $type_list);
        $this->assign('type_listj', json_encode($type_list));

        $url = $this->siteUrl . '/api/?s=Wrongbook.GetList&uid=' . $uid . '&token=' . $token .'&gradeid=' . $gradeid . '&type=2' . '&status=' . $type;
        $this->assign('type', $type);
        $work_info = curl_get($url);
        $lists = $work_info['data']['info'];

        $isMore = 0;
        if (count($lists) >= 20) {
            $isMore = 1;
        }
        $this->assign('isMore', $isMore);
        $this->assign('lists', $lists);


        $this->assign('mynavid', $mynavid);
        $this->assign('navid', -1);

        return $this->fetch();
    }

    //错题本提交页面
    public function wrongbookadd()
    {

        //判断有没有登录
        $this->checkMyLogin();


        $userinfo = session('student');
        $this->assign('mynavid', '-1');
        $this->assign('navid', -1);

        return $this->fetch();
    }

    //错题详情页面
    public function wrongbookinfo()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $data = $this->request->param();

        $id = $data['id'];
        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];


        $select_list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
        $select_color = ['#38DAD0', '#FFAD31', '#FF6825', '#3B85F3', '#38DAA6', '#38DAD0', '#FFAD31', '#FF6825', '#3B85F3', '#38DAA6', '#38DAD0', '#FFAD31', '#FF6825', '#3B85F3'];
        $type_list = ['判断题', '单选题', '定项多选题', '简答题', '填空题', '不定项多选题'];
        $pan_list = ['错', '对'];

        $this->assign('select_list', $select_list);
        $this->assign('type_list', $type_list);
        $this->assign('pan_list', $pan_list);


        $url = $this->siteUrl . '/api/?s=Wrongbook.GetInfo&uid=' . $uid . '&token=' . $token . '&id=' . $id;
        $work_info = curl_get($url);
        if ($work_info['data']['code'] != '0') {
            $this->error($work_info['data']['msg'], url('/student/index/index'));
        }


        if ($work_info['data']['info'][0]['type'] == 1) {
            if ($work_info['data']['info'][0]['content']['type'] == 1 || $work_info['data']['info'][0]['content']['type'] == 2 || $work_info['data']['info'][0]['content']['type'] == 5) {
                //正确答案
                $rs = $work_info['data']['info'][0]['answer']['rs'];
                foreach ($select_list as $k => $v) {
                    $rs = str_replace($k, $v, $rs);
                }
                $rs = str_replace(',', '、', $rs);
                $work_info['data']['info'][0]['answer']['rs'] = $rs;

                //你的回答
                $rs1 = $work_info['data']['info'][0]['answer']['rs_user']['rs'];
                foreach ($select_list as $k => $v) {
                    $rs1 = str_replace($k, $v, $rs1);
                }
                $rs1 = str_replace(',', '、', $rs1);
                $work_info['data']['info'][0]['answer']['rs_user']['rs'] = $rs1;
            }
        }


        $work_info = $work_info['data']['info'][0];

        $this->assign('work_info', $work_info);

        $this->assign('mynavid', '-1');
        $this->assign('navid', -1);

        return $this->fetch();
    }


    //账号设置
    public function mybase()
    {

        //判断有没有登录
        $this->checkMyLogin();
        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        //用户信息
        $url = $this->siteUrl . '/api/?s=User.GetBaseInfo&uid=' . $uid . '&token=' . $token;

        $baseinfo = curl_get($url);
//        dump($baseinfo);
        $this->assign('baseinfo', $baseinfo['data']['info'][0]);
        $this->assign('mynavid', 5);
        $this->assign('navid', -1);
        return $this->fetch();
    }

    //上传头像
    public function uploadImg()
    {

        $data = $this->request->param();

        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $file = $_FILES['file'];
        if (!$file) {
            $this->error('请选择图片');
        }


        $res = upload();
        if ($res['code'] != 0) {
            $this->error($res['msg']);
        }

        $data = [
            'avatar' => $res['url'],
            'url' => get_upload_path($res['url'])
        ];

        session('student.avatar', get_upload_path($res['url']));
        session('student.avatar_thumb', get_upload_path($res['url']));
        $this->success('操作成功', '', $data);
    }


    //我的课件
    public function message()
    {

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        //系统消息
        $url = $this->siteUrl . '/api/?s=Message.GetList&uid=' . $uid . '&token=' . $token . '&type=0';

        $syslist = curl_get($url);
        $this->assign('syslist', $syslist['data']['info']);


        //课程消息
        $url = $this->siteUrl . '/api/?s=Message.GetList&uid=' . $uid . '&token=' . $token . '&type=1';

        $classlist = curl_get($url);
        $this->assign('classlist', $classlist['data']['info']);


        //讲师消息
        $url = $this->siteUrl . '/api/?s=Message.GetList&uid=' . $uid . '&token=' . $token . '&type=2';

        $teacherlist = curl_get($url);
        $this->assign('teacherlist', $teacherlist['data']['info']);

        $this->assign('mynavid', 6);
        $this->assign('navid', -1);
        return $this->fetch();
    }


    //关注的讲师
    public function follows()
    {
        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');


        $uid = $userinfo['id'];
        $token = $userinfo['token'];


        //关注的讲师
        $url = $this->siteUrl . '/api/?s=User.GetFollow&uid=' . $uid . '&token=' . $token . '&p=1';

        $followslist = curl_get($url);
        $this->assign('followslist', $followslist['data']['info']);


        $isMore = 0;
        if (count($followslist['data']['info']) >= 50) {
            $isMore = 1;
        }

        $this->assign('isMore', $isMore);
        $this->assign('mynavid', -1);
        $this->assign('navid', -1);
        return $this->fetch();
    }


    /**
     * 课堂表扬
     */
    public function praise()
    {
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $this->assign([
            'navid' => -1,
            'mynavid' => 15,
            'praise_url' => $this->siteUrl . '/appapi/Praise/index?uid=' . $uid . '&token=' . $token
        ]);
        return $this->fetch();
    }

    /**
     * 我的积分
     */
    public function integral()
    {
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $integralUrl = $this->siteUrl . '/api/?s=App.Integral.GetList&uid=' . $uid . '&token=' . $token;
        $integralInfo = curl_get($integralUrl);

        $this->assign([
            'integralInfo' => $integralInfo['data']['info'],
            'navid' => -1,
            'mynavid' => 16
        ]);
        return $this->fetch();
    }

    /**
     * 推广中心
     */
    public function promotion()
    {
        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $promotionUrl = $this->siteUrl . '/api/?s=App.Agent.MyAgent&uid=' . $uid . '&token=' . $token;
        $promotioninfo = curl_get($promotionUrl);

        $this->assign([
            'navid' => -1,
            'mynavid' => 17,
            'promotioninfo' => $promotioninfo['data']['info'][0],
            'promotioninfo_json' => json_encode($promotioninfo['data']['info'][0])
        ]);
        return $this->fetch();
    }

    /**
     * 获取推广海报
     */
    public function ajaxGetPopuImage()
    {
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $url = $this->siteUrl . '/api/?s=App.Agent.GetPopuImage&uid=' . $uid . '&token=' . $token;
        return curl_get($url);
    }

    /**
     * 获取分享海报
     */
    public function ajaxGetShareImage()
    {
        $this->checkMyLogin();

        $courseid = input('courseid/d');
        $type = input('type/d');
        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $url = $this->siteUrl . '/api/?s=App.Agent.GetShareImage&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid . '&type=' . ($type + 1);
        return curl_get($url);
    }

    /**
     * 推广员认证 界面
     */
    public function approve()
    {
        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $url = $this->siteUrl . '/api/?s=User.GetBaseInfo&uid=' . $uid . '&token=' . $token;
        $baseinfo = curl_get($url);

        $this->assign([
            'baseinfo' => $baseinfo['data']['info'][0],
            'navid' => -1,
            'mynavid' => 17,
        ]);

        return $this->fetch();
    }

    /**
     * 推广员认证提交 数据提交
     */
    public function AjaxPostApprove()
    {
        $this->checkMyLogin();

        $username = input('username');
        $phone = input('phone');
        $id_number = input('id_number');
        $id_front = input('id_front');
        $id_back = input('id_back');
        $id_portrait = input('id_portrait');
        return $this->submitTheCertification($username, $phone, $id_number, $id_front, $id_back, $id_portrait);
    }

    /**
     * 请求接口提交认证
     */
    protected function submitTheCertification($username, $phone, $id_number, $id_front, $id_back, $id_portrait)
    {
        $s = 'App.Auth.SetAuth';
        $testQueryData = [
            'name' => $username,
            'mobile' => $phone,
            'cer_no' => $id_number,
            'front_view' => $id_front,
            'back_view' => $id_back,
            'handset_view' => $id_portrait,
        ];
        return $this->requestInterface($s, $testQueryData);
    }

    /**
     * 优惠券列表
     *
     * @return void
     */
    public function discountCoupon()
    {
        $this->checkMyLogin();


        $this->assign([
            'mynavid' => 18,
        ]);
        return $this->fetch();
    }

    public function ajaxGetMycoupon()
    {
        $this->checkMyLogin();
        $input = input('type', 1);
        $page = input('page', 1);
        $result = $this->getCouponMyList($input, $page);
        $type = [
            1 => '满减',
            2 => '折扣',
        ];
        $limit_type = [
            0 => '无门槛',
            1 => '满额',
        ];
        $isall = [
            0 => '非全部商品可用',
            1 => '全部商品可用'
        ];
        foreach ($result['data']['info'][0]['list'] as $key => &$value) {
            if ($value['type'] == 1) {//满减
                $value['money_tag'] = '<span class="symbol">¥</span><span class="money">' . $value['limit'] . '</span>';
            } else {//折扣
                $value['money_tag'] = '<span class="money">' . $value['limit'] . '</span><span class="symbol">折</span>';
            }
            $value['limit_type_tag'] = $limit_type[$value['limit_type']];

            if ($value['limit_type'] == 1) {//满额 满多少
                $value['limit_type_tag'] = '满' . $value['limit_val'];
            }
            $value['isall_tag'] = $isall[$value['isall']];
        }

        return ['code' => 0, 'msg' => 'success', 'info' => $result['data']['info']];
    }

    /**
     * 用于获取我的优惠券列表
     *
     * @param integer $type 类型 1未使用 2已过期
     * @param integer $p 页码
     * @return void
     */
    protected function getCouponMyList(int $type = 1, int $p = 1)
    {
        $this->checkMyLogin();

        $s = 'App.Coupon.GetMyList';
        $data = [
            'type' => $type,
            'p' => $p
        ];
        return $this->requestInterface($s, $data);
    }

    /**
     * 我的余额
     * @return mixed|string
     */
    public function balance()
    {
        $this->checkMyLogin();

        $s = 'App.Balance.GetList';
        $list = $this->requestInterface($s);//明细

        $account_s = 'App.Cash.GetAccountList';
        $accountList = $this->requestInterface($account_s);//提现账户

        $type = [
            1 => '支付宝',
            2 => '微信',
            3 => '银行卡'
        ];

        $this->assign([
            'balance_list' => $list['data']['info'] ?? [],
            'accountList' => $accountList['data']['info'] ?? [],
            'mynavid' => 19,
            'type' => $type,
        ]);
        return $this->fetch();
    }

    /**
     * 新增提现账户
     */
    public function ajaxAddAccount()
    {
        $this->checkMyLogin();

        $result = [
            'info' => [],
            'msg' => '',
            'code' => 0
        ];
        $input = input();

        $type = input('type', 0);
        if (!in_array($type, [1, 2, 3])) {
            $result['msg'] = '类型错误';
            return $result;
        }

        $data = [];
        $data['type'] = $type;
        if ($type == 1) {//支付宝
            $data['account'] = $input['alipay']['number'];
            $data['name'] = $input['alipay']['user'];
        } else if ($type == 2) {//微信
            $data['account'] = $input['wechat']['number'];
        } else if ($type == 3) {//银行
            $data['account'] = $input['bank']['number'];
            $data['name'] = $input['bank']['user'];
            $data['account_bank'] = $input['bank']['name'];
        }
        $res = $this->setAccount($data);

        return ['code' => 0, 'msg' => 'success', 'info' => $res['data']['info']];
    }

    /**
     * 余额提现
     */
    public function ajaxSetCash()
    {
        $this->checkMyLogin();

        $accountid = input('account_id', 0);
        $money = input('money', 0);

        $s = 'App.Cash.SetCash';
        $data = [
            'accountid' => $accountid,
            'money' => $money,
        ];
        $res = $this->requestInterface($s, $data);
        if ($res['data']['code'] !== 0) {
            return ['code' => 1, 'msg' => $res['data']['msg'], 'info' => 1];
        }
        return ['code' => 0, 'msg' => $res['data']['msg'], 'info' => $res['data']];

    }

    /**
     * 用户设置 新增提现账户
     * @param $data
     */
    protected function setAccount($data)
    {
        $this->checkMyLogin();

        $s = 'App.Cash.SetAccount';
        return $this->requestInterface($s, $data);
    }

    /**
     * 删除提现账户
     */
    public function ajaxDelAccount()
    {
        $this->checkMyLogin();

        $id = input('id', 0);
        $s = 'App.Cash.DelAccount';
        $data = [
            'id' => $id
        ];
        return $this->requestInterface($s, $data);
    }

    //我的订单
    public function coursesInOrder()
    {
        $this->checkMyLogin();
        //支付方式
        $info = $this->apiAppCartGetPayList();
        $paylist = $info['data']['info'];

        $this->assign([
            'mynavid' => 20,
            'paylist' => $paylist,
        ]);
        return $this->fetch();
    }


    public function getAjaxOrderList()
    {
        $status = input('status', 0);
        $page = input('page', 1);

        $data = [
            'status' => $status,
            'p' => $page,
        ];

        $res = $this->getOrderList($data);
        if ($res['data']['code'] !== 0) {
            return ['code' => 1, 'msg' => $res['data']['msg'], 'info' => 1];
        }
        foreach ($res['data']['info'] as $key => $value) {
            $res['data']['info'][$key] = $this->setOrderTag($value);
        }
        return ['code' => 0, 'msg' => 'success', 'info' => $res['data']['info']];
    }

    /**
     * 设置订单 标签状态
     * @param $item
     */
    protected function setOrderTag($value)
    {

        $showpink_tag = '';//拼团详情显示状态
        $group_tag = '';//拼团标签
        $pinkid = $value['pinkid'];//拼团id
        if (isset($value['showpink']) && $value['showpink'] == 1) {//是
            $sort = $value['goods'][0]['sort'] ?? 'pack';
            $id = $value['goods'][0]['id'];
            if ($sort == 0) {//内容
                $jump_url = $this->siteUrl . '/student/detail/substance/id/' . $id;
            } else if ($sort == 1) {//课程
                $jump_url = $this->siteUrl . '/student/detail/class/id/' . $id;
            } else if (in_array($sort, [2, 3, 4])) {//直播
                $jump_url = $this->siteUrl . '/student/detail/live/id/' . $id;
            } else if ($sort == 'pack') {
                $jump_url = $this->siteUrl . '/student/detail/index/id/' . $id;
            }
            $showpink_tag = '<div class="group getPinkInfo" data-href="' . $jump_url . '" data-pinkid="' . $pinkid . '"><button>拼团详情</button></div>';
            $group_tag = '<div class="group"><span>拼团</span></div>';
        }
        $pinkstatus_tag = '';
        if (isset($value['pinkstatus']) && $value['pinkstatus'] == 1) {
            $pinkstatus_tag = '<div class="group"><span class="name">拼团失败</span></div>';
        }

        $time_tag = '';//支付时间显示
        $pay_tag = '';//订单支付按钮状态
        $cancel_tag = '';//订单取消状态

        $addTime = strtotime($value['add_time']) * 1000;//订单生成时间
        $endTime = $addTime + $value['paytime'] * 1000;//订单支付失效时间

        if ($value['status'] == 0) {//待支付

            $pay_tag = '<div class="pay"><button data-id="' . $value['orderno'] . '">立即支付</button></div>';
            $time_tag = '<div class="time"><span class="name">支付时间:</span> <span class="time" id="time_' . $value['id'] . '">00:00</span></div>';
        } else if ($value['status'] == 1) {//已支付
            $cancel_tag = '<div class="cancel"><span class="name">已支付</span></div>';
        } else if ($value['status'] == -1) {//已取消
            $cancel_tag = '<div class="cancel"><span class="name">已取消</span></div>';
        }

        $goods_tag = $this->setOrderProduct($value['goods']);

        $value['group_tag'] = $group_tag;
        $value['showpink_tag'] = $showpink_tag;
        $value['pinkstatus_tag'] = $pinkstatus_tag;
        $value['time_tag'] = $time_tag;
        $value['pay_tag'] = $pay_tag;
        $value['cancel_tag'] = $cancel_tag;
        $value['goods_tag'] = $goods_tag;
        $value['add_time_d'] = $addTime;
        $value['end_time_d'] = $endTime;

        return $value;
    }

    /**
     * 设置订单商品标签
     * @param $goods
     */
    protected function setOrderProduct($goods)
    {

        $goods_tag = '';
        $teacher_tag = '';

        foreach ($goods as $key2 => $value2) {
            $less = '';
            if ($value2['goodstype'] == 1) {//套餐
                $less = $value2['nums'] . '课程';
                foreach ($value2['teacher'] as $key3 => $value3) {
                    $teacher_tag .= '<img src="' . $value3['avatar'] . '" alt=""><span>' . $value3['user_nickname'] . '</span>';
                }
            } else if ($value2['goodstype'] == 0) {//课程
                $less = $value2['lesson'] == '' ? '尚未添加内容' : $value2['lesson'];
                $teacher_tag = '<img src="' . $value2['avatar'] . '" alt=""><span>' . $value2['user_nickname'] . '</span>';
            }
            $goods_tag .= '<div class="top">
                                <div class="left">
                                    <div class="img" style="background-image: url(' . $value2['thumb'] . ')"></div>
                                </div>
                                <div class="right">
                                    <div class="top">
                                        ' . $value2['name'] . '
                                    </div>
                                    <div class="middle">
                                        <span>' . $less . '</span>
                                    </div>
                                    <div class="bottom">
                                        <div class="left">
                                            ' . $teacher_tag . '
                                        </div>
                                        <div class="right">¥' . $value2['money'] . '</div>
                                    </div>
                                </div>
                            </div>';

        }

        return $goods_tag;
    }

    /**
     * 获取订单
     * @param $data
     */
    protected function getOrderList($data)
    {
        $s = 'App.Orders.GetList';
        return $this->requestInterface($s, $data);
    }

    /**
     * 订单详情
     */
    public function orderInfo()
    {
        $this->checkMyLogin();

        $orderno = input('id', 0);
        $s = 'App.Orders.GetDetail';
        $data = [
            'orderno' => $orderno
        ];
        $orderInfo = $this->requestInterface($s, $data);

        if ($orderInfo['data']['code'] ?? 1 != 0) {
            return $this->error($orderInfo['data']['msg'] ?? '发生错误');
        }
        $info = $orderInfo['data']['info'][0] ?? [];
        $info['goods_tag'] = $this->setOrderProduct($info['goods']);

        $userinfo = session('student');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        //支付方式
        $url3 = $this->siteUrl . '/api/?s=Cart.GetPayList&uid=' . $uid . '&token=' . $token;
        $info2 = curl_get($url3);
        $paylist = $info2['data']['info'];

        $this->assign([
            'mynavid' => 20,
            'info' => $info,
            'paylist' => $paylist
        ]);
        return $this->fetch();
    }

    public function ajaxorderPay()
    {
        $this->checkMyLogin();

        $payid = input('payid', 0);
        $orderno = input('orderno', 0);

        if (!in_array($payid, [1, 5, 2])) {
            return ['code' => 1, 'msg' => '支付参数有误', 'info' => []];
        }
        if (!$orderno) {
            return ['code' => 1, 'msg' => '订单号参数有误', 'info' => []];
        }
        $openid = UsersModel::field('openid')->find(session('student.id'))['openid'];
        if ($payid == 2) {
            $payid = 7;
        }
        $res = $this->orderPay($payid, $orderno, $openid);

        if ($res['data']['code'] != 0) {
            return ['code' => 1, 'msg' => $res['data']['msg'], 'info' => []];
        }

        $result = ['code' => 1, 'msg' => '发生错误', 'info' => []];


        if ($payid == 7) {//微信支付
            $payRes = $this->orderWecahtPay($res);
            if ($payRes['code'] != 0) {
                $result['msg'] = $payRes['msg'];
                return $result;
            }
            return [
                'code' => 0,
                'msg' => '请求支付成功',
                'info' => [
                    'code_url' => $payRes['info']['code_url'] ?? '',
                    'orderno' => $orderno
                ]
            ];
        } else if ($payid == 1) {//支付宝支付
            return ['code' => 0, 'msg' => '支付宝支付成功', 'info' => []];
        } else if ($payid == 5) {//余额支付
            return ['code' => 0, 'msg' => '余额支付成功', 'info' => []];
        }
        return $result;
    }

    /**
     * 订单支付宝支付
     * @param $res 订单支付接口返回的数据
     */
    public function orderAlipay()
    {
        $this->checkMyLogin();

        $orderno = input('orderno', '');

        $openid = UsersModel::field('openid')->find(session('student.id'))['openid'];
        $res = $this->orderPay(1, $orderno, $openid);
        if ($res['data']['code'] != 0) {
            return $this->error($res['data']['msg']);
        }

        echo $this->alipay($res, '购买课程', '购买课程');

    }

    /**
     * 支付宝订单
     * @param $res 支付接口返回的数据
     * @param $subject 订单名称
     * @param $body 订单描述
     * @param $notify_url 服务器异步通知
     */
    protected function alipay($res, $subject, $body, $notify_url = '/appapi/cartpay/notify_pc_ali')
    {
        $configpub = getConfigPub();
        $configpri = getConfigPri();

        require_once CMF_ROOT . 'sdk/alipay/pagepay/service/AlipayTradeService.php';
        require_once CMF_ROOT . 'sdk/alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php';


        $config = [
            //应用ID,您的APPID。
            'app_id' => $configpri['alipc_appid'],

            //商户私钥
            'merchant_private_key' => $configpri['alipc_key'],

            //异步通知地址
            'notify_url' => $configpub['site_url'] . $notify_url,

            //同步跳转
            'return_url' => $configpub['site_url'] . '/student',

            //编码格式
            'charset' => "UTF-8",

            //签名方式
            'sign_type' => "RSA2",

            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => $configpri['alipc_publickey'],
        ];

        //商户订单号，商户网站订单系统中唯一订单号，必填
        $out_trade_no = $res['data']['info'][0]['orderid'];

        //付款金额，必填
        $total_amount = $res['data']['info'][0]['money'];

        //构造参数
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setOutTradeNo($out_trade_no);

        $aop = new \AlipayTradeService($config);

        /**
         * pagePay 电脑网站支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
         */
        $response = $aop->pagePay($payRequestBuilder, $config['return_url'], $config['notify_url']);
        echo $response;

        die();
        //获取后台设置的 配置信息
        /*  $siteconfig=M("siteconfig")->where("id='1'")->find(); */
        //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
        //合作身份者id，以2088开头的16位纯数字
        $alipay_config['partner'] = $configpri['aliapp_partner'];
        //安全检验码，以数字和字母组成的32位字符
        $alipay_config['key'] = $configpri['aliapp_check'];
        //支付宝账号
        $alipay_config['seller_email'] = $configpri['aliapp_seller_id'];
        //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
        //签名方式 不需修改
        $alipay_config['sign_type'] = strtoupper('MD5');
        //字符编码格式 目前支持 gbk 或 utf-8
        $alipay_config['input_charset'] = strtolower('utf-8');
        //ca证书路径地址，用于curl中ssl校验
        //请保证cacert.pem文件在当前文件夹目录中
        $alipay_config['cacert'] = CMF_ROOT . 'sdk/alipay/cacert.pem';
        //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        $alipay_config['transport'] = 'http';
        //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
        require_once CMF_ROOT . "sdk/alipay/lib/alipay_submit.class.php";

        //支付记录
        /**************************请求参数**************************/
        //支付类型
        $payment_type = "1";
        //必填，不能修改
        //服务器异步通知页面路径
//        $notify_url = $configpub['site_url'] . '/appapi/cartpay/notify_pc_ali';
        //需http://格式的完整路径，不能加?id=123这类自定义参数
        //页面跳转同步通知页面路径
        $return_url = $configpub['site_url'] . '/student';
        //需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/
        //商户网站订单系统中唯一订单号，必填
        //付款金额
        $total_fee = $res['data']['info'][0]['money'];
        //商品展示地址
        $show_url = $configpub['site_url'];
        //需以http://开头的完整路径，例如：http://www.xxx.com/myorder.html
        //防钓鱼时间戳
        $anti_phishing_key = "";
        //若要使用请调用类文件submit中的query_timestamp函数
        //客户端的IP地址
        $exter_invoke_ip = "";
        //非局域网的外网IP地址，如：221.0.0.1
        /************************************************************/
        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "create_direct_pay_by_user",
            "partner" => trim($alipay_config['partner']),
            "payment_type" => $payment_type,
            "notify_url" => $notify_url,
            "return_url" => $return_url,
            "seller_email" => trim($alipay_config['seller_email']),
            "out_trade_no" => $res['data']['info'][0]['orderid'],
            "subject" => $subject,
            "total_fee" => $total_fee,
            "body" => $body,
            "show_url" => $show_url,
            "qr_pay_mode" => 2,
            "anti_phishing_key" => $anti_phishing_key,
            "exter_invoke_ip" => $exter_invoke_ip,
            "_input_charset" => trim(strtolower($alipay_config['input_charset']))
        );

        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter, "get", "1");
        return $html_text;
    }

    /**
     * 微信支付
     * @param $res 订单支付接口返回的数据
     * @param $body 订单提示
     * @return array|array[]
     */
    protected function orderWecahtPay($res, $body = '购买课程', $notify_url = '/appapi/cartpay/notify_pc_wx')
    {
        $configpub = getConfigPub();
        $configpri = getConfigPri();
        if ($configpri['pc_wx_appid'] == "" || $configpri['pc_wx_mchid'] == "" || $configpri['pc_wx_key'] == "") {
            $arr = ['code' => 1001, 'msg' => '微信未配置', 'info' => []];
            return $arr;
        }

        $noceStr = md5(rand(100, 1000) . time());//获取随机字符
        $orderid = $res['data']['info'][0]['orderid'];
        $paramarr = array(
            "appid" => $configpri['pc_wx_appid'],
            "body" => $body,
            "mch_id" => $configpri['pc_wx_mchid'],
            "nonce_str" => $noceStr,
            "notify_url" => $configpub['site_url'] . $notify_url,
            "out_trade_no" => $orderid,
            "total_fee" => $res['data']['info'][0]['money'] * 100,
            "trade_type" => "NATIVE"
        );

        $sign = "";
        foreach ($paramarr as $k => $v) {
            $sign .= $k . "=" . $v . "&";
        }

        $sign .= "key=" . $configpri['pc_wx_key'];
        $sign = strtoupper(md5($sign));
        $paramarr['sign'] = $sign;
        $paramXml = "<xml>";
        foreach ($paramarr as $k => $v) {
            $paramXml .= "<" . $k . ">" . $v . "</" . $k . ">";
        }
        $paramXml .= "</xml>";

        $ch = curl_init();
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在
        @curl_setopt($ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/pay/unifiedorder");
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_POST, 1);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $paramXml);
        @$resultXmlStr = curl_exec($ch);
        curl_close($ch);

        $postStr = $resultXmlStr;
        $payres = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (array_key_exists('return_code', $payres) && $payres['return_code'] == 'SUCCESS') {//微信支付请求成功
            return ['code' => 0, 'msg' => '请求微信NATIVE支付成功', 'info' => $payres];
        } else {
            return ['code' => 1, 'msg' => $payres['return_msg'] ?? '微信支付发起失败', 'info' => $payres];
        }
    }

    /**
     * 订单支付
     */
    protected function orderPay($payid, $orderno, $openid)
    {

        $s = 'App.Orders.Pay';

        $data = [
            'payid' => $payid,
            'orderno' => $orderno,
            'openid' => $openid,
        ];
        return $this->requestInterface($s, $data);
    }

    /**
     * 成就
     */
    public function achievement()
    {

        $this->assign([
            'mynavid' => 21,
        ]);

        return $this->fetch();
    }

    /**
     * 请求考试证书
     * @param $page
     */
    public function ajaxGetAchievementList()
    {
        $this->checkMyLogin();

        $page = input('page', 1);
        $s = 'App.Tests.GetCert';
        $data = [
            'p' => $page,
        ];

        $res = $this->requestInterface($s, $data);

        if ($res['data']['code'] != 0) {
            return ['code' => 1, 'msg' => $res['data']['msg'], 'info' => []];
        }

        return ['code' => 0, 'msg' => 'success', 'info' => $res['data']['info']];
    }

    /**
     * 会员中心
     */
    public function memberCenter()
    {
        $s = 'App.Vip.GetVip';
        $vipInfo = $this->requestInterface($s);

        if ($vipInfo['data']['code'] != 0) {
            $this->error($vipInfo['data']['msg']);
        }

        //支付方式
        $info = $this->apiAppCartGetPayList();
        $paylist = $info['data']['info'];


        $this->assign([
            'mynavid' => 22,
            'vipInfo' => $vipInfo['data']['info'][0] ?? [],
            'paylist' => $paylist,
        ]);
        return $this->fetch();
    }

    /**
     * 购买vip
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ajaxBuyVIP()
    {
        $this->checkMyLogin();
        $type = input('type');//套餐
        $payType = input('payType');//支付类型
        $vip = input('vip');//会员类型

        if ($type <= 0) {
            return ['code' => 1, 'msg' => '套餐类型选择错误', 'info' => []];
        }

        if (!in_array($payType, [1, 5, 2])) {
            return ['code' => 1, 'msg' => '支付参数有误', 'info' => []];
        }

        if (!in_array($vip, [1, 2])) {
            return ['code' => 1, 'msg' => '会员类型错误', 'info' => []];
        }
        $openid = UsersModel::field('openid')->find(session('student.id'));

        if ($payType == 2) {
            $payType = 7;
        }

        $data = [
            'payid' => $payType,
            'vipid' => $vip,
            'type' => $type,
            'openid' => $openid['openid'],
        ];

        $res = $this->buyVip($data);
        if ($res['data']['code'] != 0) {
            return ['code' => 1, 'msg' => $res['data']['msg'], 'info' => []];
        }

        if ($payType == 7) {//微信支付
            $payRes = $this->orderWecahtPay($res, '购买会员', '/appapi/vippay/notify_pc_wx');
            if ($payRes['code'] != 0) {
                $result['msg'] = $payRes['msg'];
                return $result;
            }
            return [
                'code' => 0,
                'msg' => '请求支付成功',
                'info' => [
                    'code_url' => $payRes['info']['code_url'] ?? '',
                    'orderno' => $res['data']['info'][0]['orderid'] ?? '',
                ]
            ];
        } else if ($payType == 1) {//支付宝支付
            return ['code' => 0, 'msg' => '支付宝支付成功', 'info' => []];
        } else if ($payType == 5) {//余额支付
            return ['code' => 0, 'msg' => '余额支付成功', 'info' => []];
        }

        return ['code' => 0, 'msg' => $res['data']['msg'], 'info' => $res];
    }


    /**
     * 购买会员
     * @param $data
     */
    protected function buyVip($data)
    {
        $s = 'App.Vip.Buy';
        return $this->requestInterface($s, $data);
    }

    /**
     * 会员 支付宝支付
     * @return array
     */
    public function memberAlipay()
    {
        $this->checkMyLogin();
        $type = input('type');//套餐
        $payType = input('payType');//支付类型
        $vip = input('vip');//会员类型

        if ($type <= 0) {
            return $this->error('套餐类型选择错误');
        }

        if (!in_array($payType, [1, 5, 7])) {
            return $this->error('支付参数有误');
        }

        if (!in_array($vip, [1, 2])) {
            return $this->error('会员类型错误');
        }

        $data = [
            'payid' => $payType,
            'vipid' => $vip,
            'type' => $type,
            'openid' => '',
        ];
        $res = $this->buyVip($data);

        if ($res['data']['code'] != 0) {
            return $this->error($res['data']['msg']);
        }

        echo $this->alipay($res, '购买会员', '购买会员', '/appapi/vippay/notify_pc_ali');

    }

    /**
     * 获取微信订单支付状态 课程订单
     */
    public function wxAjaxGetVipStatus()
    {
        $this->checkMyLogin();
        $orderid = input('orderid');
        if ($orderid == 0) {
            return [
                'code' => 1,
                'msg' => '参数错误',
                'info' => []
            ];
        }
        $where = [];
        $where[] = ['orderno', '=', $orderid];
        $where[] = ['type', '=', 7];
        $orderinfo = Db::name("vip_orders")->field('status')->where($where)->find();

        if ($orderinfo && $orderinfo['status'] == 1) {//已支付
            return [
                'code' => 0,
                'msg' => '已支付',
                'info' => []
            ];
        } else {//未支付
            return [
                'code' => 3,
                'msg' => '未支付',
                'info' => []
            ];
        }
    }

    /**
     * 取消订单
     */
    public function ajaxCancelAnOrder()
    {
        $orderno = input('orderno', 0);
        if ($orderno == 0) {
            return $this->error('订单号有误', NULL);
        }
        $s = 'App.Orders.Cancel';
        $data = [
            'orderno' => $orderno
        ];

        $Info = $this->requestInterface($s, $data);

        if ($Info['data']['code'] != 0) {
            $this->error($Info['data']['msg'], NULL, $Info['data']);
        }

        return $this->success($Info['data']['msg'], NULL, $Info['data']);

    }


    /**
     * 我的活动
     */
    public function activitycenter()
    {
        $this->assign([
            'mynavid' => 24
        ]);
        return $this->fetch();
    }

    /**
     * 我的活动
     */
    public function ajaxGetMyActivity()
    {
        $p = input('p/d');
        $res = $this->apiAppActivityMyList($p);
        return $this->success($res['data']['msg'], '', $res['data']['info']);
    }

    /**
     * 我的活动
     */
    protected function apiAppActivityMyList($p = 1)
    {
        $s = 'App.Activity.MyList';
        $queryData = [
            'p' => $p
        ];

        return $this->requestInterface($s, $queryData);
    }





    /**
     * 教辅资料
     */

    /**
     * 商城 快递信息
     */
    public function logistics()
    {

        $orderno = input('id');
        if (!$orderno) {
            return $this->error('参数错误');
        }
        $list = $this->apiAppShopordersGetExpress($orderno);

        if ($list['data']['code'] != 0) {
            return $this->error('快递单号错误');
        }
        $this->assign([
            'mynavid' => 23,
            'list' => $list['data']['info'],
        ]);

        return $this->fetch();
    }

    /**
     * 教辅资料收货
     */
    public function ajaxAppShopordersReceive()
    {
        $orderno = input('id');
        if (!$orderno) {
            return $this->error('参数错误');
        }
        $res = $this->apiAppShopordersReceive($orderno);

        if ($res['data']['code'] != 0) {
            return $this->error($res['data']['msg']);
        }

        return $this->success($res['data']['msg']);


    }

    /**
     * 教辅资料 收货
     * @param $orderno
     */
    protected function apiAppShopordersReceive($orderno)
    {
        $s = 'App.Shoporders.Receive';
        $queryData = [
            'orderno' => $orderno
        ];

        return $this->requestInterface($s, $queryData);
    }

    /**
     * 快递列表
     * @param $orderno
     */
    protected function apiAppShopordersGetExpress($orderno)
    {
        $s = 'App.Shoporders.GetExpress';
        $queryData = [
            'orderno' => $orderno
        ];

        return $this->requestInterface($s, $queryData);
    }

    /**
     * 商城订单
     */
    public function teachingmall()
    {
        $this->checkMyLogin();

        $numList = $this->apiAppShopordersGetNums();

        $userinfo = session('student');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        //支付方式
        $url3 = $this->siteUrl . '/api/?s=Cart.GetPayList&uid=' . $uid . '&token=' . $token;
        $info = curl_get($url3);
        $paylist = $info['data']['info'];

        $this->assign([
            'mynavid' => 23,
            'paylist' => $paylist,
            'numList' => $numList['data']['info'][0],
        ]);
        return $this->fetch();
    }

    /**
     * 我的订单 教辅资料
     */
    public function ajaxGetGetshopList()
    {
        $this->checkMyLogin();

        $status = input('status/d');
        $p = input('p/d');

        if (is_null($status) || !$p) {
            return $this->error('参数错误');
        }

        return $this->apiAppShopordersGetList($status, $p);
    }

    /**
     * 订单支付
     */
    public function ajaxShopordersPay()
    {
        $this->checkMyLogin();

        $payid = input('orderno');
        $orderno = input('orderno');
        if (!$payid && !$orderno) {
            return $this->error('参数错误');
        }


    }

    /**
     * 订单详情
     * @return mixed|string|void
     */
    public function shoporderinfo()
    {
        $this->checkMyLogin();

        $id = input('id');
        if (!$id) {
            return $this->error('参数错误');
        }

        //支付方式
        $info = $this->apiAppCartGetPayList();
        $paylist = $info['data']['info'];
        $info = $this->apiAppShopordersGetDetail($id);

        $this->assign([
            'mynavid' => 23,
            'info' => $info['data']['info'][0],
            'paylist' => $paylist,
        ]);
        return $this->fetch();
    }

    /**
     * 订单支付
     */
    public function ajaxAppShopordersPay()
    {
        $payid = input('payid/d');
        $orderno = input('orderno');

        if (!$orderno || !in_array($payid, [1, 2, 5])) {
            return $this->error('参数错误');
        }

        //微信支付为了放置订单号重复
        if ($payid == 2) {
            $payid = 7;
        }

        if ($payid == 5) {//余额支付
            $res = $this->apiAppShopordersPay($payid, $orderno);
            return $res;
        } else if ($payid == 1) {//支付宝支付
            return $this->error('参数错误');
        } elseif ($payid == 7) {//微信支付
            $res = $this->apiAppShopordersPay($payid, $orderno);
            if ($res['data']['code'] !== 0) {
                return $res;
            }

            $pay = $this->orderWecahtPay($res);
            if ($pay['code'] !== 0) {
                return [
                    'code' => 1,
                    'data' => [
                        'code' => 1
                    ],
                    'msg' => $pay['msg'],
                ];
            }

            return [
                'code' => 0,
                'data' => [
                    'code' => 0,
                    'code_url' => $pay['info']['code_url'],
                    'orderid' => $res['data']['info'][0]['orderid'],
                ],
                'msg' => $pay['msg'],
            ];
        }


    }

    /**
     * 订单继续阿里支付
     */
    public function ShopPayAli()
    {
        $this->checkMyLogin();

        $orderno = input('orderno');

        if (!$orderno) {
            return $this->error('参数错误');
        }
        $res = $this->apiAppShopordersPay(1, $orderno);

        if ($res['data']['code'] != 0) {
            return $this->error($res['data']['msg']);
        }

        echo $this->alipay($res, '购买课程', '购买课程');
    }


    /**
     * 订单取消
     */
    public function ajaxShoporderCancel()
    {
        $this->checkMyLogin();

        $id = input('orderno');
        if (!$id) {
            return $this->error('参数错误');
        }

        return $this->apiAppShopordersCancel($id);
    }


    /**
     * 教辅商城 物流信息
     */
    public function logisticsInformation()
    {
        return $this->fetch();
    }

    /**
     * 订单取消
     * @param $orderno
     */
    protected function apiAppShopordersCancel($orderno)
    {
        $s = 'App.Shoporders.Cancel';
        $queryData = [
            'orderno' => $orderno,
        ];

        return $this->requestInterface($s, $queryData);
    }

    /**
     * 订单详情
     * @param $orderno
     * @return mixed
     */
    protected function apiAppShopordersGetDetail($orderno)
    {
        $s = 'App.Shoporders.GetDetail';
        $queryData = [
            'orderno' => $orderno,
        ];

        return $this->requestInterface($s, $queryData);
    }


    /**
     * 我的商城订单
     */
    protected function apiAppShopordersGetList($status, $p = 1)
    {
        $s = 'App.Shoporders.GetList';
        $queryData = [
            'source' => 0,
            'status' => $status,
            'p' => $p,
        ];

        return $this->requestInterface($s, $queryData);
    }

    /**
     * 订单数量
     */
    protected function apiAppShopordersGetNums()
    {
        $s = 'App.Shoporders.GetNums';

        return $this->requestInterface($s);
    }

    /**
     * 商城订单支付
     * @param $payid
     * @param $orderno
     * @return mixed
     */
    protected function apiAppShopordersPay($payid, $orderno)
    {
        $s = 'App.Shoporders.Pay';
        $queryData = [
            'source' => 0,
            'payid' => $payid,
            'orderno' => $orderno,
        ];
        return $this->requestInterface($s, $queryData);
    }

}
