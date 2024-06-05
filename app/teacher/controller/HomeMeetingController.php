<?php

namespace  app\teacher\controller;



use cmf\controller\TeacherBaseController;
use think\Db;

class HomeMeetingController extends  TeacherBaseController{

    public $class_id;

    public function initialize()
    {
        parent::initialize();
        $this->assign('cur','HomeSchool');
    }

    public function checkUser(){
        $userId = session('teacher.id');
        $token= session('teacher.token');
        $key = "token_" . $userId;
        $userinfo = getcaches($key);
        if (!$userinfo) {
            $userinfo = Db::name('users_token')
                ->field('token,expire_time,is_authentication,account_update')
                ->where("user_id = ? $userId")
                ->fetchOne();
            if ($userinfo) {
                setcaches($key, $userinfo);
            }
        }
        if (!$userinfo || $userinfo['token'] != $token || $userinfo['expire_time'] < time()) {
            session("teacher", null);
            $this->error("登录状态已失效", url("teacher/login/index"));
        }
        $this->success();
    }

    public function index(){
        $param = $this->request->param();

        $class_id =$param['class_id'] ?? 0;
        if(!$class_id){
            $this->error('信息错误');
        }
        $where = [];
        $keyword = $param['keyword'] ?? 0;
        if($keyword){
            $where[] =  ['name' , 'like' , "%{$keyword}%"];
        }
        $this->assign('class_id',$class_id);
        $uid = session('teacher.id');
        $where[] = ['uid','=',$uid];
        $list = Db::name('course')->where('class_id',$class_id)->where($where)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $v['starttime']  = date('Y-m-d H:i:s',$v['starttime']);
            $v['thumb']  = get_upload_path($v['thumb']);
            $list[$k] = $v;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }


    public function live() {

        $uid=session('teacher.id');

        $data = $this->request->param();

        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $lessonid=isset($data['lessonid']) ? $data['lessonid']: '0';

        if($courseid<1){
            $this->error('信息错误');
        }

        $nowtime=time();
        $times=0;
        $livemode='0';

        $title='';

        $courseinfo=Db::name("course")->where(['id'=>$courseid])->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid && $courseinfo['tutoruid']!=$uid){
            $this->error('信息错误');
        }
        $gradeinfo=getGradeInfo($courseinfo['gradeid']);
        if($gradeinfo){
            $title.=$gradeinfo['name'].'/';
        }
        $title.=$courseinfo['name'];

        $tutoruid=$courseinfo['tutoruid'];
        $thumb=get_upload_path($courseinfo['thumb']);

        if($lessonid>0){
            $liveinfo=Db::name("course_lesson")->where(['id'=>$lessonid,'courseid'=>$courseid])->find();
            if(!$liveinfo){
                $this->error('信息错误');
            }

            $type=$liveinfo['type']-3;
            $islive=$liveinfo['islive'];

            if($islive==0 && $liveinfo['starttime']>$nowtime){
                $times=$liveinfo['starttime']-$nowtime;
            }
            $liveinfo['uid']=$courseinfo['uid'];

            if($type==4 && $islive==2){
                $this->error('授课已结束');
            }

            $title.='/'.$liveinfo['name'];

        }else{
            $liveinfo=$courseinfo;
            $type=$liveinfo['type'];

            if($liveinfo['starttime']>$nowtime){
                $times=$liveinfo['starttime']-$nowtime;
            }
            $islive=$liveinfo['islive'];

            $livemode=$liveinfo['livemode'];

            if($type==4 && $islive==2){
                $this->error('授课已结束');
            }
        }

        $pptindex=$liveinfo['pptindex'];
        $isshup=$liveinfo['isshup'];
        $chatopen=$liveinfo['chatopen'];

        $uuid=$liveinfo['uuid'];
        $roomtoken=$liveinfo['roomtoken'];

        $liveuid=$liveinfo['uid'];

        /* 用户身份 */
        $user_type='0';
        if($uid==$liveuid){
            $user_type='1';
        }

        if($uid==$tutoruid){
            $user_type='2';
        }

        if($user_type==1){
            $livemode='0';
            $pptindex='0';
        }


        $teacherinfo=Db::name('class_teacher')->where(['class_id'=>$courseinfo['class_id'],'uid'=>$uid])->find();

        $configpri=getConfigPri();
        $stream=$liveuid.'_'.$courseid.'_'.$lessonid;

        $name=$liveinfo['name'];
        $pull=get_upload_path($liveinfo['url']);

        /* 用户数量 */
        $nums=$this->getUserNums($stream);

        $ppts=[];
        if($type==1 || $type==5){
            $ppts=Db::name("course_ppt")->where(['courseid'=>$courseid,'lessonid'=>$lessonid])->order('id asc')->select()->toArray();
            foreach($ppts as $k=>$v){
                $v['thumb']=get_upload_path($v['thumb']);
                $ppts[$k]=$v;
            }
        }

        $start_length=0;
        if($islive==1){
            $start_length=$nowtime-$liveinfo['starttime'];
        }

        /* 音视频 */
        $rtc_type=$liveinfo['rtc_type'];
        $rtc_token='';
        $pano_appid=$configpri['pano_appid'];
        if($rtc_type==2){
            $rtc_token=pano_token($stream,$uid);
        }

        /* 白板类别 */
        $netless_appid=$configpri['netless_appid'];
        $whitetype=$liveinfo['whitetype'];

        /* CDN */
        $push='';
        $cdntype=$liveinfo['cdntype'];
        if($cdntype > 0){
            $push=getCdnUrl($cdntype,'rtmp',$stream,1);
            $pull=getCdnUrl($cdntype,'http',$stream,0);
        }

        $info=[
            'id'=>$liveinfo['id'],
            'liveuid'=>$liveuid,
            'chatserver'=>$configpri['chatserver'],
            'sound_appid'=>$configpri['sound_appid'],
            'netless_appid'=>$netless_appid,
            'rtc_type'=>$rtc_type,
            'rtc_token'=>$rtc_token,
            'pano_appid'=>$pano_appid,
            'whitetype'=>$whitetype,
            'cdntype'=>$cdntype,
            'push'=>$push,
            'pull'=>$pull,
            'stream'=>$stream,
            'livetype'=>$type,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'title'=>$title,
            'name'=>$name,
            'thumb'=>$thumb,
            'nums'=>$nums,
            'ppts'=>$ppts,
            'pptsj'=>json_encode($ppts),
            'islive'=>$islive,
            'times'=>$times,
            'start_length'=>$start_length,
            'uuid'=>$uuid,
            'roomtoken'=>$roomtoken,
            'isshup'=>$isshup,
            'chatopen'=>$chatopen,
            'user_type'=>$user_type,
            'livemode'=>$livemode,
            'pptindex'=>$pptindex,
            "class_id"=>$courseinfo['class_id'],
            'tx_appid'=>$configpri['tx_trans_appid'],
            'tx_fileid'=>$liveinfo['tx_fileid'],

        ];



        $this->assign('info',$info);
        $this->assign('infoj',json_encode($info));

        $this->assign('teacherinfoj',json_encode($teacherinfo));

        if($type==4){
            return $this->fetch('white');
        }


        return $this->fetch();
    }

    /* 获取用户列表数量 */
    protected function getUserNums($stream){

        $nums=zCard('user_'.$stream);
        if(!$nums){
            $nums=0;
        }

        return $nums;
    }



    /* 用户进入 写缓存
    50本房间主播 60超管 40管理员 30观众 10为游客(判断当前用户身份)
*/
    public function setNodeInfo() {

        /* 当前用户信息 */
        $uid=session('teacher.id');
        $token=session('teacher.token');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $data = $this->request->param();
        $class_id = $data['class_id'] ?? 0;

        $info = Db::name("class_teacher")->where(['uid'=>$uid,'class_id'=>$class_id])->find();
        if(!$info){
            $this->error('信息错误');
        }
        $info['name'] = $info['name'] . '老师';
        $info['token']=$token;

        $info['usertype']=2;
        $info['user_type']=2;
        $info['sign']='0';


        setcaches($token,$info);

        $data=[
            'uid'=>$uid,
            'token'=>$token,
        ];

        $this->success('','',$data);
    }



    /* 获取用户列表 */
    public function getUserLists(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $stream=isset($data['stream']) ? checkNull($data['stream']): '';
        $p=isset($data['p']) ? checkNull($data['p']): '1';

        if($courseid<1 || $stream==''){
            $this->error('信息错误');
        }

        $class_id = Db::name('course')->where(['id'=>$courseid])->find()['class_id'];
        $list=[];
        $uids_have=[];


        /* 申请 */
        $apply_list=zRange('linkmic_apply_'.$stream,0,-1,true);
        foreach($apply_list as $k=>$v){
            $userinfo=getUserInfo($k);
            $type='2';
            $iswrite='0';

            $userinfo['type']=$type;
            $userinfo['iswrite']=$iswrite;


            $where=[
                'uid'=>$k,
                'courseid'=>$courseid,
                'lessonid'=>$lessonid,
            ];
            $isshut=$this->isShutup($where);
            $userinfo['isshut']=$isshut;

            $list[]=$userinfo;
            $uids_have[]=$k;
        }

        if($p<1){
            $p=1;
        }
        $pnum=50;
        $start=$pnum*($p-1);

        /* 用户列表 */
        $uidlist=zRevRange('user_'.$stream,$start,$pnum,true);
        $patriarch = Db::name('class_patriarch')->where(['class_id'=>$class_id])->limit($start,$pnum)->select();
        $patriarch_uid = array_column($patriarch->toArray(),null,'uid');
        $student_id = [];
        foreach($uidlist as $k=>$v){
            if(in_array($k,$uids_have)){
                continue;
            }
            $userinfo=getUserInfo($k);
            $userinfo['user_nickname'] = getName(1,$patriarch_uid[$k]['id']);
            $student_id[] = $patriarch_uid[$k]['student_id'];
            $type='0';

            if($v>0){
                $type='1';
            }

            $iswrite='0';
            if($type==1){
                if(hGet('write_'.$stream,$k)){
                    $iswrite='1';
                }
            }

            $userinfo['type']=$type;
            $userinfo['iswrite']=$iswrite;

            $where2=[
                'uid'=>$k,
                'courseid'=>$courseid,
                'lessonid'=>$lessonid,
            ];
            $isshut=$this->isShutup($where2);
            $userinfo['isshut']=$isshut;

            $list[]=$userinfo;
            $uids_have[]=$k;
        }
        $nums=zCard('user_'.$stream);
        if(!$nums){
            $nums=0;
        }

        $student = Db::name('student')->where('class_id',$class_id)->limit($start,$pnum)->select();
        foreach ($student as $k=>$v){
            if(!in_array($v['id'],$student_id)){
                $userinfo['avatar'] = get_upload_path("/student.jpg");
                $userinfo['user_nickname'] = $v['name'];
                $type='-1';  //离线
                $iswrite='0';
                $userinfo['type']=$type;
                $userinfo['iswrite']=$iswrite;
                $userinfo['isshut']='0';
                $list[]=$userinfo;
            }

        }

//        $student = array_column(Db::name('student')->where('class_id',$class_id)->select()->toArray(),null,'id');
//        $patriarch = array_column($patriarch->toArray(),null,'uid');
//        foreach ($patriarch as $k=>$v){
//            if(in_array($k,$uids_have)){
//                continue;
//            }
//            $userinfo=getUserInfo($k);
//            if(!$userinfo){
//                continue;
//            }
//            $userinfo['user_nickname'] = $student[$v['student_id']]['name'];
//            $type='-1';  //离线
//            $iswrite='0';
//            $userinfo['type']=$type;
//            $userinfo['iswrite']=$iswrite;
//            $userinfo['isshut']='0';
//            $list[]=$userinfo;
//        }




//        $total=count($uids);
//        $list_nums=count($list);




        $rs=[
            'list'=>$list,
            'nums'=>'',
            'total'=>'',
        ];

        $this->success('操作成功','',$rs);
    }

    protected function isShutup($where){
        $isshut=Db::name('live_shutup')->where($where)->find();
        if($isshut){
            return 1;
        }
        return 0;
    }

}