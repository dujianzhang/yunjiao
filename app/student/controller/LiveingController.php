<?php


namespace app\Student\controller;

use cmf\controller\StudentBaseController;
use think\Db;

/**
 * 直播
 */
class LiveingController extends StudentBaseController
{

    public function index()
    {

        $uid = session('student.id');

        $data = $this->request->param();

        $courseid = isset($data['courseid']) ? $data['courseid'] : '0';
        $lessonid = isset($data['lessonid']) ? $data['lessonid'] : '0';

        //检测直播
        if ($courseid < 1) {
            $this->error('信息错误');
        }

        $this->checkEnterLive($courseid, $lessonid);


        $nowtime = time();
        $times = 0;

        $courseinfo = Db::name("course")->where(['id' => $courseid])->find();
        if (!$courseinfo) {
            $this->error('信息错误');
        }

        $tutoruid = $courseinfo['tutoruid'];
        $thumb = get_upload_path($courseinfo['thumb']);

        if ($lessonid > 0) {
            $liveinfo = Db::name("course_lesson")->where(['id' => $lessonid, 'courseid' => $courseid])->find();
            if (!$liveinfo) {
                $this->error('信息错误');
            }

            $type = $liveinfo['type'] - 3;
            $islive = $liveinfo['islive'];

            if ($islive == 0 && $liveinfo['starttime'] > $nowtime) {
                $times = $liveinfo['starttime'] - $nowtime;
            }

            if ($type == 4 && $islive == 2) {
                $this->error('授课已结束');
            }

        } else {
            $liveinfo = $courseinfo;

            if (!$liveinfo) {
                $this->error('信息错误');
            }

            if ($liveinfo['sort'] < 2) {
                $this->error('当前非直播课程');
            }

            $islive = $liveinfo['islive'];
            $type = $liveinfo['type'];

            if ($liveinfo['starttime'] > $nowtime) {
                $times = $liveinfo['starttime'] - $nowtime;
            }
        }

        $pptindex = $liveinfo['pptindex'];
        $isshup = $liveinfo['isshup'];
        $chatopen = $liveinfo['chatopen'];
        $livemode = $liveinfo['livemode'];


        $liveuid = $liveinfo['uid'];
        if ($liveuid['uid'] != $courseinfo['uid']) {//课时主讲老师和课时主讲老师不同时 以课程主讲老师为准
            $liveuid = $courseinfo['uid'];
        }
        /* 用户身份 */
        $user_type = '0';

        $teacherinfo = getUserInfo($liveuid);

        $configpri = getConfigPri();
        $stream = $liveuid . '_' . $courseid . '_' . $lessonid;

        $name = $liveinfo['name'];
        $pull = get_upload_path($liveinfo['url']);

        /* 用户数量 */
        $nums = $this->getUserNums($stream);

        $ppts = [];
        if ($type == 1 || $type == 5) {
            $ppts = Db::name("course_ppt")->where(['courseid' => $courseid, 'lessonid' => $lessonid])->order('id asc')->select()->toArray();
            foreach ($ppts as $k => $v) {
                $v['thumb'] = get_upload_path($v['thumb']);
                $ppts[$k] = $v;
            }
        }

        $uuid = $liveinfo['uuid'];
        $roomtoken = $liveinfo['roomtoken'];
        /* 音视频 */
        $rtc_type = $liveinfo['rtc_type'];
        $rtc_token = '';
        $pano_appid = $configpri['pano_appid'];
        if ($rtc_type == 2) {
            $rtc_token = pano_token($stream, $uid);
        }

        $netless_appid = $configpri['netless_appid'];
        $whitetype = $liveinfo['whitetype'];


        /* CDN */
        $cdntype = $liveinfo['cdntype'];
        if ($cdntype > 0) {
            $pull = getCdnUrl($cdntype, 'http', $stream, 0);
        }

        $info = [
            'id' => $liveinfo['id'],
            'liveuid' => $liveuid,
            'chatserver' => $configpri['chatserver'],
            'sound_appid' => $configpri['sound_appid'],
            'whitetype' => $whitetype,
            'netless_appid' => $netless_appid,
            'rtc_type' => $rtc_type,
            'rtc_token' => $rtc_token,
            'cdntype' => $cdntype,
            'pano_appid' => $pano_appid,
            'pull' => $pull,
            'stream' => $stream,
            'livetype' => $type,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'name' => $name,
            'thumb' => $thumb,
            'nums' => $nums,
            'ppts' => $ppts,
            'pptsj' => json_encode($ppts),
            'islive' => $islive,
            'times' => $times,
            'uuid' => $uuid,
            'roomtoken' => $roomtoken,
            'isshup' => $isshup,
            'user_type' => $user_type,
            'livemode' => $livemode,
            'pptindex' => $pptindex,
            'shutup_room' => $liveinfo['isshup'],
            'chatopen' => $chatopen,
            'tx_appid' => $configpri['tx_trans_appid'],
            'tx_fileid' => $liveinfo['tx_fileid'],
        ];

        $this->setLesson($uid, $courseid, $lessonid);

        $this->assign('info', $info);
        $this->assign('infoj', json_encode($info));

        $this->assign('teacherinfoj', json_encode($teacherinfo));


        $select_list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
        $pan_list = ['错', '对'];
        $type_list = ['判断题', '单选题', '定项多选题', '简答题', '填空题', '不定项多选题'];


        $this->assign('type_listj', json_encode($type_list));
        $this->assign('select_listj', json_encode($select_list));
        $this->assign('pan_listj', json_encode($pan_list));

        if ($type == 4) {
            return $this->fetch('white');
        }

        return $this->fetch();
    }

    //回放
    public function liveback()
    {

        $uid = session('student.id');

        $data = $this->request->param();

        $courseid = isset($data['courseid']) ? $data['courseid'] : '0';
        $lessonid = isset($data['lessonid']) ? $data['lessonid'] : '0';

        $this->checkEnterLive($courseid, $lessonid);

        //检测直播
        if ($courseid < 1) {
            $this->error('信息错误');
        }
        $courseinfo = Db::name("course")->where(['id' => $courseid])->find();

        if ($lessonid > 0) {
            $liveinfo = Db::name("course_lesson")->where(['id' => $lessonid, 'courseid' => $courseid])->find();
        } else {
            $liveinfo = $courseinfo;
        }

        if (!$liveinfo) {
            $this->error('信息错误');
        }

        $thumb = get_upload_path($courseinfo['thumb']);


        $url = get_upload_path($liveinfo['url']);

        $uuid = $liveinfo['uuid'];
        $roomtoken = $liveinfo['roomtoken'];
        $configpri = getConfigPri();
        $info = [
            'name' => $liveinfo['name'],
            'uuid' => $uuid,
            'roomtoken' => $roomtoken,
            'url' => $url,
            'netless_appid' => $configpri['netless_appid'],
            'thumb' => $thumb,
            'whitetype' => $liveinfo['whitetype'],
            'rtc_type' => $liveinfo['rtc_type'],
        ];
        $panoPlayback=[];
        if($info['rtc_type'] == 2){
            $panoPlayback = $this->apiAppLiveCheckLive($uid,$courseid,$lessonid);
        }
        $info['pano_playback'] = $panoPlayback['data']['info'][0] ?? ['recordlist'=>[]];
        $this->setLesson($uid, $courseid, $lessonid);

        $this->assign('info', $info);
        $this->assign('infoj', json_encode($info));

        return $this->fetch();
    }
    /**
     * 请求拍了云回放视频列表
     * @param $liveuid
     * @param $courseid
     * @param $lessonid
     */
    protected function apiAppLiveCheckLive($liveuid, $courseid, $lessonid)
    {
        $s = 'App.Live.CheckLive';
        $queryData = [
            'liveuid' => $liveuid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
        ];

        return $this->requestInterface($s, $queryData);
    }
    /* 用户进入 写缓存
		50本房间主播 60超管 40管理员 30观众 10为游客(判断当前用户身份)
    */
    public function setNodeInfo()
    {

        /* 当前用户信息 */
        $uid = session('student.id');
        $token = session('student.token');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $data = $this->request->param();

        $user_type = isset($data['user_type']) ? $data['user_type'] : '0';

        $info = getUserInfo($uid);
        $info['token'] = $token;

        $info['usertype'] = $user_type;
        $info['user_type'] = $user_type;
        $info['sign'] = '0';


        setcaches($token, $info);

        $data = [
            'uid' => $uid,
            'token' => $token,
        ];

        $this->success('', '', $data);
    }

    public function setChat()
    {
        $data = $this->request->param();

        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $liveuid = isset($data['liveuid']) ? checkNull($data['liveuid']) : '0';
        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';
        $type = isset($data['type']) ? checkNull($data['type']) : '0';
        $content = isset($data['content']) ? checkNull($data['content']) : '';
        $url = isset($data['url']) ? checkNull($data['url']) : '';
        $length = isset($data['length']) ? checkNull($data['length']) : '0';
        $toid = isset($data['toid']) ? checkNull($data['toid']) : '0';
        $status = isset($data['status']) ? checkNull($data['status']) : '0';
        $user_type = isset($data['user_type']) ? checkNull($data['user_type']) : '0';

        if ($liveuid < 0 || $courseid < 0) {
            $this->error('信息错误');
        }

        if ($type == 0 && $content == '') {
            $this->error('请输入内容');
        }

        if ($type == 1 && ($url == '' || $length < 1)) {
            $this->error('请录制语音');
        }

        if ($status == 2 && $toid < 1) {
            $this->error('请选择回复内容');
        }

        if ($content != strip_tags($content)) {
            $this->error('内容包含非法字符');
        }

        $data_insert = [
            'uid' => $uid,
            'liveuid' => $liveuid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'type' => $type,
            'content' => $content,
            'url' => $url,
            'length' => $length,
            'toid' => $toid,
            'status' => $status,
            'user_type' => $user_type,
            'addtime' => time(),
        ];

        $id = DB::name('live_chat')->insertGetId($data_insert);
        if (!$id) {
            $this->error("添加失败！");
        }

        $info = [
            'chatid' => $id,
            'type' => $type,
            'content' => $content,
            'url' => get_upload_path($url),
            'length' => $length,
            'status' => $status,
            'user_type' => $user_type,
            'toid' => $toid,
        ];

        $this->success("添加成功！", '', $info);
    }

    public function getChat()
    {
        $data = $this->request->param();

        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';
        $type = isset($data['type']) ? checkNull($data['type']) : '0';
        $lastid = isset($data['lastid']) ? checkNull($data['lastid']) : '0';
        $liveuid = isset($data['liveuid']) ? checkNull($data['liveuid']) : '0';

        $where = [
            ['courseid', '=', $courseid],
            ['lessonid', '=', $lessonid],
        ];

        if ($type == 1) {
            $where[] = ['uid', '=', $liveuid];
        }
        if ($type == 2) {
            $where[] = ['status', '<>', 0];
        }

        if ($lastid > 0) {
            $where[] = ['id', '<', $lastid];
        }
        $list = Db::name('live_chat')->where($where)->order("id desc")->limit(20)->select()->toArray();
        foreach ($list as $k => $v) {
            $userinfo = getUserInfo($v['uid']);
            $v['user_nickname'] = $userinfo['user_nickname'];
            $v['avatar'] = $userinfo['avatar'];
            if ($v['type'] == 1) {
                $v['url'] = get_upload_path($v['url']);
            }

            $v['add_time'] = date('Y-m-d H:i:s', $v['addtime']);


            $toinfo = [];
            if ($v['toid'] > 0) {
                $toinfo = Db::name('live_chat')->where(['id' => $v['toid']])->find();
                if ($toinfo) {
                    $touserinfo = getUserInfo($toinfo['uid']);
                    $toinfo['user_nickname'] = $touserinfo['user_nickname'];
                    $toinfo['avatar'] = $touserinfo['avatar'];
                    if ($toinfo['type'] == 1) {
                        $toinfo['url'] = get_upload_path($toinfo['url']);
                    }
                }
            }

            $v['toinfo'] = $toinfo;

            $list[$k] = $v;
        }

        $list = array_reverse($list);

        $this->success('', '', $list);
    }

    /* 语音 */
    public function addAudio()
    {
        $rs = ['code' => 0, 'data' => [], 'msg' => '', 'url' => ''];
        $data = $this->request->param();

        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';
        $audio_time = isset($data['audio_time']) ? checkNull($data['audio_time']) : '0';

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
        $url = $res['url'];

        $length = floor($audio_time * 100) * 0.01;

        $data_insert = [
            'uid' => $uid,
            'liveuid' => $uid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'type' => 1,
            'content' => '',
            'url' => $url,
            'length' => $length,
            'status' => 0,
            'addtime' => time(),
        ];

        $id = DB::name('live_chat')->insertGetId($data_insert);
        if (!$id) {
            $this->error("添加失败！");
        }

        $info = [
            'chatid' => $id,
            'type' => '1',
            'content' => '',
            'url' => get_upload_path($url),
            'length' => $length,
            'status' => 0,
        ];

        $this->success("发送成功！", '', $info);
    }

    /* 获取套题 */
    public function getExam()
    {
        $data = $this->request->param();

        $uid = session('student.id');
        $token = session('student.token');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';
        $liveuid = isset($data['liveuid']) ? checkNull($data['liveuid']) : '0';
        if ($courseid < 1 || $lessonid < 1) {
            $this->error('信息错误');
        }


        $url = $this->siteUrl . '/api/?s=Exam.GetExam&uid=' . $uid . '&token=' . $token . '&liveuid=' . $liveuid . '&courseid=' . $courseid . '&lessonid=' . $lessonid;
        $list = curl_get($url);
        $this->success('', '', $list['data']);
    }

    /**提交答案获取sign */
    public function getExamSign()
    {
        $data = $this->request->param();

        $uid = $data['uid'];
        $token = $data['token'];
        $liveuid = $data['liveuid'];
        $courseid = $data['courseid'];
        $lessonid = $data['lessonid'];
        $examid = $data['examid'];
        $answer = $data['answer'];

        $checkdata = array(
            'uid' => $uid,
            'token' => $token,
            'liveuid' => $liveuid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'examid' => $examid,
            'answer' => $answer,
        );


        $str = '';
        ksort($checkdata);
        foreach ($checkdata as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str .= '400d069a791d51ada8af3e6c2979bcd7';
        $newsign = md5($str);

        $this->success('', '', $newsign);

    }

    /**抢题获取sign */
    public function getRobSign()
    {
        $data = $this->request->param();

        $uid = $data['uid'];
        $token = $data['token'];
        $liveuid = $data['liveuid'];
        $courseid = $data['courseid'];
        $lessonid = $data['lessonid'];
        $examid = $data['examid'];

        $checkdata = array(
            'uid' => $uid,
            'token' => $token,
            'liveuid' => $liveuid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'examid' => $examid
        );


        $str = '';
        ksort($checkdata);
        foreach ($checkdata as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str .= '400d069a791d51ada8af3e6c2979bcd7';
        $newsign = md5($str);

        $this->success('', '', $newsign);

    }

    /* 获取题目 */
    public function getQuestion()
    {
        $data = $this->request->param();

        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';
        $examid = isset($data['examid']) ? checkNull($data['examid']) : '0';
        $touid = isset($data['touid']) ? checkNull($data['touid']) : '0';

        if ($courseid < 1 || $lessonid < 1 || $examid < 1) {
            $this->error('信息错误');
        }

        $rs = [
            'choice' => [],
            'judge' => [],
            'write' => [],
        ];

        $where = [
            'uid' => $uid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'id' => $examid,
            'type' => 0,
        ];

        $info = Db::name('course_exam')->where($where)->find();
        if (!$info) {
            $this->success('', '', $rs);
        }

        $answers = [];
        if ($touid > 0) {
            $where3 = [
                'uid' => $touid,
                'liveuid' => $uid,
                'courseid' => $courseid,
                'lessonid' => $lessonid,
                'examid' => $examid,
            ];

            $isanswer = Db::name('course_exam_a')->where($where3)->find();
            if ($isanswer) {
                $answers = json_decode($isanswer['answer'], true);
            }
        }
        $where2 = [
            'uid' => $uid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'pid' => $examid,
        ];
        $list = Db::name('course_exam')->where($where2)->order("id asc")->select()->toArray();

        foreach ($list as $k => $v) {
            $ans = [];
            $type = $v['type'];
            if ($type == 1) {
                $ans = json_decode($v['answer'], true);
                foreach ($ans as $k1 => $v1) {
                    $v1['ischoice'] = '0';
                    if (isset($answers[$v['id']]) && $answers[$v['id']] == $k1) {
                        $v1['ischoice'] = '1';
                    }
                    $ans[$k1] = $v1;
                }
            }

            if ($type == 2) {
                $data = [
                    'isok' => $v['answer'],
                    'ischoice' => '2',
                ];
                if (isset($answers[$v['id']])) {
                    $data['ischoice'] = (string)$answers[$v['id']];
                }
                $ans[] = $data;
            }

            if ($type == 3) {
                $data = [
                    'isok' => '',
                    'ischoice' => '',
                ];
                if (isset($answers[$v['id']])) {
                    $data['ischoice'] = (string)$answers[$v['id']];
                }
                $ans[] = $data;
            }

            $v['answer'] = $ans;

            unset($v['addtime']);
            unset($v['status']);
            unset($v['endtime']);
            unset($v['choicenums']);
            unset($v['judgenums']);
            unset($v['writenums']);

            if ($type == 1) {
                $rs['choice'][] = $v;
            }

            if ($type == 2) {
                $rs['judge'][] = $v;
            }

            if ($type == 3) {
                $rs['write'][] = $v;
            }
        }

        $this->success('', '', $rs);
    }


    /* 获取课件 */
    public function getWare()
    {
        $data = $this->request->param();

        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';

        if ($courseid < 1) {
            $this->error('信息错误');
        }

        $where = [
            'courseid' => $courseid,
            'lessonid' => $lessonid,
        ];

        $list = Db::name('course_ware')->where($where)->order("id desc")->select()->toArray();

        foreach ($list as $k => $v) {
            $v['url'] = get_upload_path($v['url']);

            $list[$k] = $v;
        }

        $this->success('', '', $list);
    }

    /* 获取用户列表数量 */
    protected function getUserNums($stream)
    {

        $nums = zCard('user_' . $stream);
        if (!$nums) {
            $nums = 0;
        }

        return $nums;
    }

    /* 获取用户列表数量 */
    public function getUserListNum()
    {
        $data = $this->request->param();

        $stream = isset($data['stream']) ? checkNull($data['stream']) : '';

        if ($stream == '') {
            $this->error('信息错误');
        }

        $nums = zCard('user_' . $stream);
        if (!$nums) {
            $nums = 0;
        }

        $rs = [
            'nums' => $nums,
        ];

        $this->success('操作成功', '', $rs);
    }

    /* 获取用户列表 */
    public function getUserLists()
    {
        $data = $this->request->param();
        $uid = session('student.id');

        if ($uid < 1) {
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid = isset($data['courseid']) ? checkNull($data['courseid']) : '0';
        $lessonid = isset($data['lessonid']) ? checkNull($data['lessonid']) : '0';
        $stream = isset($data['stream']) ? checkNull($data['stream']) : '';


        if ($courseid < 1 || $lessonid < 0 || $stream == '') {
            $this->error('信息错误');
        }

        $list = [];
        $uids_have = [];

        /* 申请 */
        $apply_list = zRange('linkmic_apply_' . $stream, 0, -1, true);
        foreach ($apply_list as $k => $v) {
            $userinfo = getUserInfo($k);
            $type = '2';
            $iswrite = '0';

            $userinfo['type'] = $type;
            $userinfo['iswrite'] = $iswrite;


            $where = [
                'uid' => $k,
                'courseid' => $courseid,
                'lessonid' => $lessonid,
            ];
            $isshut = $this->isShutup($where);
            $userinfo['isshut'] = $isshut;

            $list[] = $userinfo;
            $uids_have[] = $k;
        }

        /* 用户列表 */
        $uidlist = zRevRange('user_' . $stream, 0, -1, true);

        foreach ($uidlist as $k => $v) {
            if (in_array($k, $uids_have)) {
                continue;
            }
            $userinfo = getUserInfo($k);
            $type = '0';

            if ($v > 0) {
                $type = '1';
            }

            $iswrite = '0';
            if ($type == 1) {
                if (hGet('write_' . $stream, $k)) {
                    $iswrite = '1';
                }
            }

            $userinfo['type'] = $type;
            $userinfo['iswrite'] = $iswrite;

            $where2 = [
                'uid' => $k,
                'courseid' => $courseid,
                'lessonid' => $lessonid,
            ];
            $isshut = $this->isShutup($where2);
            $userinfo['isshut'] = $isshut;

            $list[] = $userinfo;
        }

        $nums = zCard('user_' . $stream);
        if (!$nums) {
            $nums = 0;
        }

        $rs = [
            'list' => $list,
            'nums' => $nums,
        ];

        $this->success('操作成功', '', $rs);
    }

    protected function isShutup($where)
    {
        $isshut = Db::name('live_shutup')->where($where)->find();

        if ($isshut) {
            if ($isshut['type'] == 1) {
                return 1;
            } else {
                if ($isshut['endtime'] > time()) {
                    return 1;
                }
            }
        }


        return 0;
    }

    /* 连麦检测 */
    public function getLinkInfo()
    {

        $data = $this->request->param();

        $uid = isset($data['id']) ? checkNull($data['id']) : '0';

        $userinfo = getUserInfo($uid);

        $this->success('操作成功', '', $userinfo);
    }

    /* 更新进度 */
    function setLesson($uid, $courseid, $lessonid = 0)
    {
        $nowtime = time();
        $isview = Db::name('course_views')->where(['uid' => $uid, 'courseid' => $courseid, 'lessonid' => $lessonid])->find();
        if ($isview) {
            Db::name('course_views')->where(["id" => $isview['id']])->update(['addtime' => $nowtime]);
            return !1;
        }

        $course = Db::name('course')->field('sort,type,paytype,lessons,uid')->where(["id" => $courseid])->find();
        if (!$course) {
            return !1;
        }

        $sort = $course['sort'];

        $data = [
            'uid' => $uid,
            'sort' => $sort,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'addtime' => $nowtime
        ];
        Db::name('course_views')->insert($data);

        $nums = Db::name('course_views')->where(['uid' => $uid, 'courseid' => $courseid])->count();
        if ($nums < 2) {
            /* 同一课程下的课时 记一次课程学习数 */
            Db::name('course')->where(["id" => $courseid])->setInc('views', 1);
        }


        $isexist = Db::name('course_users')->where(['uid' => $uid, 'courseid' => $courseid])->find();
        if (!$isexist) {
            /*  */
            $status = 0;
            $paytype = $course['paytype'];
            if ($paytype == 0) {
                $status = 1;
            }
            $data2 = [
                'uid' => $uid,
                'sort' => $course['sort'],
                'paytype' => $paytype,
                'courseid' => $courseid,
                'liveuid' => $course['uid'],
                'status' => $status,
                'addtime' => $nowtime,
                'paytime' => $nowtime,
            ];
            Db::name('course_users')->insert($data2);

            $isexist = Db::name('course_users')->where(['uid' => $uid, 'courseid' => $courseid])->find();
        }

        if ($lessonid > 0) {
            Db::name('course_users')->where(['id' => $isexist['id']])->setInc('lessons', 1);

            $lessons = Db::name('course_users')->field('lessons')->where(['id' => $isexist['id']])->find();
            if ($lessons['lessons'] >= $course['lessons']) {
                /* 看完 */
                Db::name('course_users')->where(['id' => $isexist['id']])->update(['step' => 2]);
            } else {
                Db::name('course_users')->where(['id' => $isexist['id']])->update(['step' => 1]);
            }
        } else {
            Db::name('course_users')->where(['id' => $isexist['id']])->update(['step' => 2]);
        }
    }

    /**判断有没有被禁言*/
    public function isUserShutup()
    {


        $data = $this->request->param();
        $uid = session('student.id');
        $courseid = $data['courseid'];
        $lessonid = $data['lessonid'];


        $where = [
            'uid' => $uid,
            'courseid' => $courseid,
            'lessonid' => $lessonid
        ];

        $isshut = Db::name('live_shutup')->where($where)->find();

        if ($isshut) {
            $this->success('你已被禁言', '', 1);
        }


        $this->success('', '', 0);

    }


    /**生成签名*/
    public function getSign()
    {


        $data = $this->request->param();
        $uid = session('student.id');
        $token = session('student.token');


        $courseid = $data['courseid'];
        $lessonid = $data['lessonid'];
        $liveuid = $data['liveuid'];
        $result = $data['result'];
        $checkdata = array(
            'uid' => $uid,
            'token' => $token,
            'liveuid' => $liveuid,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'result' => $result,
        );

        $key = '400d069a791d51ada8af3e6c2979bcd7';
        $str = '';
        ksort($data);
        foreach ($data as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str .= $key;
        $newsign = md5($str);

        $this->success('', '', $newsign);

    }


}


