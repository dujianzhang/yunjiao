<?php


namespace app\student\controller;


use app\student\model\CourseClassModel;
use app\student\model\CourseModel;
use cmf\controller\StudentBaseController;
use think\Db;
use function PhalApi\DI;

class MeetingController extends StudentBaseController{

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->assign('navid',20);
    }


    public function index(){
        $sort = [''];

        $this->assign([
            'sort' => json_encode($sort),
            'is_vip' => 0,
            'is_seckill' => 0,
            'is_pink' => 0
        ]);

        return $this->fetch();
    }



    public function ajaxGetMeetingList()
    {
        $meeting= Db::name('course')->where('sort',5)->order('id desc')->select()->toArray();
        $teacher= array_column(Db::name('class_teacher')->select()->toArray(),null,'id');
        foreach ($meeting as $k=>$v){
            $v['starttime'] = date('Y-m-d H:i:s',$v['starttime']);
            $v['thumb'] = get_upload_path($v['thumb']);
            $teacher_name = $teacher[$v['teacher_id']]['name'] . '老师';
            $v['teacher_name'] = $teacher_name;
            $meeting[$k] = $v;
        }
        return $meeting;
    }


    public function live()
    {
        $data = $this->request->param();
        $id = $data['id'] ?? 0;
        if(!$id){
            $this->error('网络异常');
        }
        $meeting= Db::name('course')->where('id',$id)->find();

        $this->assign('info',$meeting);
        return $this->fetch();
    }



    public function liveing()
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
            'class_id'=>$courseinfo['class_id']
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



        return $this->fetch();
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

    public function isUserShutup()
    {


        $data = $this->request->param();
        $uid = session('student.id');
        $id = $data['id'];


        $where = [
            'uid' => $uid,
            'meeting_id' => $id,
        ];

        $isshut = Db::name('class_meeting_shutup')->where($where)->find();

        if ($isshut) {
            $this->success('你已被禁言', '', 1);
        }


        $this->success('', '', 0);

    }

    /* 获取用户列表数量 */
    protected function getUserNums($stream){

        $nums=zCard('user_'.$stream);
        if(!$nums){
            $nums=0;
        }

        return $nums;
    }



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
        $class_id = $data['class_id'];

        $where = [
          'class_id' => $class_id,
          'uid' => 5
        ];
        if($user_type == 0){
            $name = getName(1,Db::name('class_patriarch')->where($where)->find()['id']);
        }else{
            $name = getName(2,Db::name('class_teacher')->where($where)->find()['id']);
        }
        $info = getUserInfo($uid);
        $info['user_nickname'] = $name;
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
}