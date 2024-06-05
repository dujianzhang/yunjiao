<?php


namespace app\teacher\controller;

use app\models\AgoraModel;
use app\models\PanoModel;
use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 直播
 */
class LiveingController extends TeacherBaseController {

	public function index() {

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


        $teacherinfo=getUserInfo($liveuid);

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
     /* 开始直播 */
    public function startlive(){
        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        if($courseid<1){
            $this->error('信息错误');
        }

        if($lessonid>0){
            $isexist=Db::name('course_lesson')->where(['uid'=>$uid,'courseid'=>$courseid,'id'=>$lessonid])->find();
        }else{
            $isexist=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->find();
        }

        if(!$isexist){
            $this->error('无权操作');
        }

        if($isexist['islive']==2){
            $this->error('已上完课了');
        }

        /* if($isexist['islive']==1){
            $this->error('已经上课了');
        } */

        $data_live=[
            'islive'=>1,
            'starttime'=>time(),
        ];

        if($lessonid>0){
            $res=Db::name('course_lesson')->where(['uid'=>$uid,'courseid'=>$courseid,'id'=>$lessonid])->update($data_live);
        }else{
            $res=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->update($data_live);
        }
        if($res===false){
            $this->error('操作失败，请重试');
        }

        $this->success('操作成功');
    }
    /* 结束直播 */
    public function endlive(){
        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        if($courseid<1){
            $this->error('信息错误');
        }

        if($lessonid>0){
            $isexist=Db::name('course_lesson')->where(['uid'=>$uid,'courseid'=>$courseid,'id'=>$lessonid])->find();
        }else{
            $isexist=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->find();
        }

        if(!$isexist){
            $this->error('无权操作');
        }

        $nowtime=time();

        if($isexist['islive']!=1 && $nowtime<$isexist['starttime']){
            $this->error('当前未在直播中');
        }

        $data_live=[
            'islive'=>2,
            'endtime'=>$nowtime,
        ];

        /* 结束录制 */
        $stream=$uid.'_'.$courseid.'_'.$lessonid;
        if($isexist['resourceid'] && $isexist['sid']){
            $rs_stop=AgoraModel::stopRecord($stream,$uid,$isexist['resourceid'],$isexist['sid']);
            if($rs_stop['code']==0){
                // $url=$rs_stop['data']['serverResponse']['fileList'][0]['filename'];
            }

            $url="record/{$isexist['sid']}_{$stream}.m3u8";

            $data_live['url']=$url;
        }

        if($lessonid>0){
            $res=Db::name('course_lesson')->where(['uid'=>$uid,'courseid'=>$courseid,'id'=>$lessonid])->update($data_live);
        }else{
            $res=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->update($data_live);
        }
        if($res===false){
            $this->error('操作失败，请重试');
        }
        //发布作业
        releaseTask($uid,$courseid,$lessonid);

        if($isexist['rtc_type']==2){
            PanoModel::recordend($stream);
            PanoModel::closechannel($stream);
        }


        $this->success('操作成功');
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

        $user_type=isset($data['user_type']) ? $data['user_type']: '0';

        $info=getUserInfo($uid);
        $info['token']=$token;

        $info['usertype']=$user_type;
        $info['user_type']=$user_type;
        $info['sign']='0';


		setcaches($token,$info);

        $data=[
            'uid'=>$uid,
            'token'=>$token,
        ];

        $this->success('','',$data);
    }

    public function setChat(){
        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $liveuid=isset($data['liveuid']) ? checkNull($data['liveuid']): '0';
        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';
        $content=isset($data['content']) ? checkNull($data['content']): '';
        $url=isset($data['url']) ? checkNull($data['url']): '';
        $length=isset($data['length']) ? checkNull($data['length']): '0';
        $toid=isset($data['toid']) ? checkNull($data['toid']): '0';
        $status=isset($data['status']) ? checkNull($data['status']): '0';
        $user_type=isset($data['user_type']) ? checkNull($data['user_type']): '0';

        if($liveuid<0 || $courseid<0 ){
            $this->error('信息错误');
        }

        if($type==0 && $content==''){
            $this->error('请输入内容');
        }

        if($type==1 && ($url=='' || $length<1) ){
            $this->error('请录制语音');
        }

        if($status==2 && $toid<1){
            $this->error('请选择回复内容');
        }

        if($content!=strip_tags($content)){
            $this->error('内容包含非法字符');
        }

        $data_insert=[
            'uid'=>$uid,
            'liveuid'=>$liveuid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'type'=>$type,
            'content'=>$content,
            'url'=>$url,
            'length'=>$length,
            'toid'=>$toid,
            'status'=>$status,
            'user_type'=>$user_type,
            'addtime'=>time(),
        ];

        $id = DB::name('live_chat')->insertGetId($data_insert);
        if(!$id){
            $this->error("添加失败！");
        }

        $info=[
            'chatid'=>$id,
            'type'=>$type,
            'content'=>$content,
            'url'=>get_upload_path($url),
            'length'=>$length,
            'status'=>$status,
            'user_type'=>$user_type,
            'toid'=>$toid,
        ];

        $this->success("添加成功！",'',$info);
    }

    public function getChat(){
        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';
        $lastid=isset($data['lastid']) ? checkNull($data['lastid']): '0';

        $where=[
            ['courseid','=',$courseid],
            ['lessonid','=',$lessonid],
        ];

        if($type==1){
            $where[]=['uid','=',$uid];
        }
        if($type==2){
            $where[]=['status','<>',0];
        }

        if($lastid>0){
            $where[]=['id','<',$lastid];
        }
        $list=Db::name('live_chat')->where($where)->order("id desc")->limit(20)->select()->toArray();
        foreach($list as $k=>$v){
            $userinfo=getUserInfo($v['uid']);
            $v['user_nickname']=$userinfo['user_nickname'];
            $v['avatar']=$userinfo['avatar'];
            if($v['type']==1){
                $v['url']=get_upload_path($v['url']);
            }

            $v['add_time']=date('Y-m-d H:i:s',$v['addtime']);


            $toinfo=[];
            if($v['toid']>0){
                $toinfo=Db::name('live_chat')->where(['id'=>$v['toid']])->find();
                if($toinfo){
                    $touserinfo=getUserInfo($toinfo['uid']);
                    $toinfo['user_nickname']=$touserinfo['user_nickname'];
                    $toinfo['avatar']=$touserinfo['avatar'];
                    if($toinfo['type']==1){
                        $toinfo['url']=get_upload_path($toinfo['url']);
                    }
                }
            }

            $v['toinfo']=$toinfo;

            $list[$k]=$v;
        }

        $list=array_reverse($list);

        $this->success('','',$list);
    }

    /* 语音 */
	public function addAudio(){

		$data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $audio_time=isset($data['audio_time']) ? checkNull($data['audio_time']): '0';

        $file=$_FILES['file'];
        /* var_dump($file); */
        if(!$file){
            $this->error('请先录制语音');
        }
        $_FILES['file']['name']=$_FILES['file']['name'].'.mp3';

        $res=upload($file,'audio');
        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $url=$res['url'];

        $length=floor($audio_time*100)*0.01;

        $data_insert=[
            'uid'=>$uid,
            'liveuid'=>$uid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'type'=>1,
            'content'=>'',
            'url'=>$url,
            'length'=>$length,
            'status'=>0,
            'addtime'=>time(),
        ];

        $id = DB::name('live_chat')->insertGetId($data_insert);
        if(!$id){
            $this->error("添加失败！");
        }

        $info=[
            'chatid'=>$id,
            'type'=>'1',
            'content'=>'',
            'url'=>get_upload_path($url),
            'length'=>$length,
            'status'=>0,
        ];

        $this->success("发送成功！",'',$info);
	}

    /* 添加ppt图片 */
	public function addPPT(){
        $rs=['code'=>0,'data'=>[],'msg'=>'','url'=>''];
		$data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            // $this->error('您的登陆状态失效，请重新登陆！');
            $rs['msg']='您的登陆状态失效，请重新登陆！';
            echo json_encode($rs);
            exit;
        }

        $file=$_FILES['file'];
        if(!$file){
            $rs['msg']='请选择图片';
            echo json_encode($rs);
            exit;
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

		if($courseid<1 ){
			// $this->error('信息错误');
            $rs['msg']='信息错误';
            echo json_encode($rs);
            exit;
		}

        if($lessonid>0){
            $isexist=Db::name('course_lesson')->where(['id'=>$lessonid,'uid'=>$uid,'courseid'=>$courseid])->find();
            if(!$isexist){
                // $this->error('无权操作');
                $rs['msg']='无权操作';
                echo json_encode($rs);
                exit;
            }
        }else{
            $isexist=Db::name('course')->where(['id'=>$courseid,'uid'=>$uid])->find();
            if(!$isexist){
                // $this->error('无权操作');
                $rs['msg']='无权操作';
                echo json_encode($rs);
                exit;
            }
        }

        $res=upload();
        if($res['code']!=0){
            $rs['msg']=$res['msg'];
            echo json_encode($rs);
            exit;
        }
        $thumb=$res['url'];
		$insert=[
            'uid'=>$uid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'thumb'=>$thumb,
            'addtime'=>time(),
        ];

		$id=Db::name('course_ppt')->insertGetId($insert);
		if(!$id){
            // $this->error('添加失败，请重试');
            $rs['msg']='添加失败，请重试';
            echo json_encode($rs);
            exit;
        }

        $res=[
            'id'=>$id,
            'thumb'=>get_upload_path($thumb),
        ];

		// $this->success('操作成功','',$res);

        $rs['code']=1;
        $rs['data']=$res;
        echo json_encode($rs);
        exit;
	}

    /* 删除ppt图片 */
	public function delPPT(){
		$data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $pptid=isset($data['pptid']) ? checkNull($data['pptid']): '0';

		if($pptid<1 ){
			$this->error('信息错误');
		}

		$where=[
            'uid'=>$uid,
            'id'=>$pptid,
        ];

		$isok=Db::name('course_ppt')->where($where)->delete();
		if($isok===false){
            $this->error('操作失败，请重试');
        }

		$this->success('操作成功');
	}

    /* 获取课件 */
	public function getWare(){
		$data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

		if($courseid<1 ){
			$this->error('信息错误');
		}

		$cinfo=db('course')->where(['id'=>$courseid])->field('uid,tutoruid')->find();
        if($cinfo['uid']!=$uid && $cinfo['tutoruid']!=$uid){
            $this->error('信息错误');
        }
		$where=[
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
        ];

		$list=Db::name('course_ware')->where($where)->order("id desc")->select()->toArray();

		foreach($list as $k=>$v){
			$v['url']=get_upload_path($v['url']);

            $list[$k]=$v;
		}

		$this->success('','',$list);
	}

    /* 添加课件 */
	public function addWare(){

		$data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }
        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        if($courseid<1 ){
            $this->error('信息错误');
        }

        $cinfo=db('course')->where(['id'=>$courseid])->field('uid,tutoruid')->find();
        if($cinfo['uid']!=$uid && $cinfo['tutoruid']!=$uid){
            $this->error('无权操作');
        }

        $file=isset($_FILES['file'])?$_FILES['file']:'';
        if(!$file){
            $this->error('请选择课件');
        }

        if($file['size']==0){
            $this->error('不能上传空文件');
        }

        $res=upload($file,'file');
        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $name=$file['name'];
        $url=$res['url'];
        $size=handelSize($file['size']);
		$insert=[
            'uid'=>$uid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'name'=>$name,
            'url'=>$url,
            'size'=>$size,
            'addtime'=>time(),
        ];

		$id=Db::name('course_ware')->insertGetId($insert);
		if(!$id){
            $this->error('添加失败，请重试');
        }

        $res=[
            'id'=>$id,
            'name'=>$name,
            'size'=>$size,
            'url'=>get_upload_path($url),
        ];

		$this->success('操作成功','',$res);

	}

    /* 删除课件 */
	public function delWare(){
		$data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $id=isset($data['id']) ? checkNull($data['id']): '0';

		if($id<1 ){
			$this->error('信息错误');
		}

        $cinfo=db('course')->where(['id'=>$courseid])->field('uid,tutoruid')->find();
        if($cinfo['uid']!=$uid && $cinfo['tutoruid']!=$uid){
            $this->error('无权操作');
        }

		$where=[
            'id'=>$id,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
        ];

		$isok=Db::name('course_ware')->where($where)->delete();
		if($isok===false){
            $this->error('操作失败，请重试');
        }

		$this->success('操作成功');
	}

    /* 获取用户列表数量 */
	protected function getUserNums($stream){

        $nums=zCard('user_'.$stream);
        if(!$nums){
            $nums=0;
        }

		return $nums;
	}

    /* 获取用户列表数量 */
	public function getUserListNum(){
		$data      = $this->request->param();

        $stream=isset($data['stream']) ? checkNull($data['stream']): '';

		if( $stream==''){
			$this->error('信息错误');
		}

        $nums=zCard('user_'.$stream);
        if(!$nums){
            $nums=0;
        }

        $rs=[
            'nums'=>$nums,
        ];

		$this->success('操作成功','',$rs);
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

        foreach($uidlist as $k=>$v){
            if(in_array($k,$uids_have)){
                continue;
            }
            $userinfo=getUserInfo($k);
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

        $uids=Db::name('course_users')->where(['courseid'=>$courseid,'status'=>1])->group('uid')->column('uid');
        $total=count($uids);
        $list_nums=count($list);
        if($list_nums<$pnum){
            $uids_p=array_slice($uids,$start,$pnum);
            foreach ($uids_p as $k=>$v){
                if(in_array($v,$uids_have)){
                    continue;
                }
                $userinfo=getUserInfo($v);
                if(!$userinfo){
                    continue;
                }
                $type='-1';  //离线
                $iswrite='0';
                $userinfo['type']=$type;
                $userinfo['iswrite']=$iswrite;
                $userinfo['isshut']='0';
                $list[]=$userinfo;
            }
        }
        $rs=[
            'list'=>$list,
            'nums'=>$nums,
            'total'=>$total,
        ];

		$this->success('操作成功','',$rs);
	}

    /* 获取举手用户 */
    public function getHandLists(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $stream=isset($data['stream']) ? checkNull($data['stream']): '';

        if($courseid<1 || $stream==''){
            $this->error('信息错误');
        }

        $list=[];

        /* 申请 */
        $apply_list=zRange('linkmic_apply_'.$stream,0,-1,true);
        foreach($apply_list as $k=>$v){
            $userinfo=getUserInfo($k);
            $list[]=$userinfo;
        }

        $nums=zCard('linkmic_apply_'.$stream);
        if(!$nums){
            $nums=0;
        }

        $rs=[
            'list'=>$list,
            'nums'=>$nums,
        ];

        $this->success('操作成功','',$rs);
    }

    /* 上下麦 */
    public function setLinkmic(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $stream=isset($data['stream']) ? checkNull($data['stream']): '';
        $touid=isset($data['touid']) ? checkNull($data['touid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';
        $status=isset($data['status']) ? checkNull($data['status']): '0';

        if($courseid<0 || $touid<1){
            $this->error('信息错误');
        }

        $where1=['id'=>$courseid];
        $courseinfo=Db::name('course')->where($where1)->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid && $courseinfo['tutoruid']!=$uid){
            $this->error('无权操作');
        }

        if($status){
            $status=1;
        }else{
            $status=0;
        }
        $key='linkmic_apply_'.$stream;
        if($status==1){
            if($type==1){
                /* 上麦 */
                $isapply=zScore($key,$touid);
                if(!$isapply){
                    $this->error('对方未举手');
                }
            }
        }

        if($status>0){
            /* 上麦后修改列表顺序 */
            $key2='user_'.$stream;
            zAdd($key2,'1',$touid);
        }

        zRem($key,$touid);

        $this->success('操作成功');

    }

    /* 个人禁言 */
    public function setShutup(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $touid=isset($data['touid']) ? checkNull($data['touid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';

        if($courseid<1 || $touid<1){
            $this->error('信息错误');
        }

        $where1=['id'=>$courseid];
        $courseinfo=Db::name('course')->where($where1)->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid && $courseinfo['tutoruid']!=$uid){
            $this->error('无权操作');
        }

        if($type==1){
            $where=[
                'uid'=>$touid,
                'courseid'=>$courseid,
                'lessonid'=>$lessonid,
            ];
            $isshut=$this->isShutup($where);
            if($isshut){
                 $this->error('对方已被禁言');
            }

            $insert=[
                'uid'=>$touid,
                'courseid'=>$courseid,
                'lessonid'=>$lessonid,
                'operateuid'=>$uid,
                'addtime'=>time(),
            ];

            $isok=Db::name('live_shutup')->insert($insert);

            if(!$isok){
                $this->error('操作失败，请重试');
            }

        }else{
            $where=[
                'uid'=>$touid,
                'courseid'=>$courseid,
                'lessonid'=>$lessonid,
            ];
            $isok=Db::name('live_shutup')->where($where)->delete();
        }

        $this->success('操作成功');

    }

    /* 踢人 */
    public function kick(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $touid=isset($data['touid']) ? checkNull($data['touid']): '0';

        if($courseid<1 || $touid<1){
            $this->error('信息错误');
        }
        $where1=['id'=>$courseid];
        $courseinfo=Db::name('course')->where($where1)->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid && $courseinfo['tutoruid']!=$uid){
            $this->error('无权操作');
        }

        $where=[
            'uid'=>$touid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
        ];
        $iskick=Db::name('live_kick')->where($where)->find();
        if($iskick){
             $this->error('对方已被踢出');
        }


        $insert=[
            'uid'=>$touid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'operateuid'=>$uid,
            'addtime'=>time(),
        ];

        $isok=Db::name('live_kick')->insert($insert);

        if(!$isok){
            $this->error('操作失败，请重试');
        }

        $this->success('操作成功');


    }

    /* 房间禁言 */
    public function roomShutup(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';


        if($courseid<1){
            $this->error('信息错误');
        }

        $courseinfo=Db::name('course')->where(['id'=>$courseid])->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid && $courseinfo['tutoruid']!=$uid){
            $this->error('信息错误');
        }

        $update=[
            'isshup'=>$type
        ];

        if($lessonid>0){
            $isok=Db::name('course_lesson')->where(['courseid'=>$courseid,'id'=>$lessonid])->update($update);
        }else{
            $isok=Db::name('course')->where(['id'=>$courseid])->update($update);
        }

        $this->success('操作成功');

    }

    /* 房间开放交流区 */
    public function roomChat(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';


        if($courseid<1){
            $this->error('信息错误');
        }

        $courseinfo=Db::name('course')->where(['id'=>$courseid])->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid && $courseinfo['tutoruid']!=$uid){
            $this->error('信息错误');
        }

        $update=[
            'chatopen'=>$type
        ];

        if($lessonid>0){
            $isok=Db::name('course_lesson')->where(['courseid'=>$courseid,'id'=>$lessonid])->update($update);
        }else{
            $isok=Db::name('course')->where(['id'=>$courseid])->update($update);
        }

        $this->success('操作成功');

    }

    protected function isShutup($where){
        $isshut=Db::name('live_shutup')->where($where)->find();
        if($isshut){
             return 1;
        }
        return 0;
    }

    /* 白板上传 */
	public function whiteUpload(){
        $rs=['code'=>0,'data'=>[],'msg'=>'','url'=>''];
		$data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $type=isset($data['type']) ? checkNull($data['type']): '0';

        $file=$_FILES['file'];
        if(!$file){
            $this->error('请选择文件');
        }
        $types=[
            '1'=>'image',
            '2'=>'audio',
            '3'=>'video',
            '4'=>'file',
            '5'=>'file',
        ];

		$upload_type=$types[$type] ?? 'image';
        $res=upload($file,$upload_type);
        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $url=$res['url'];

        $res=[
            'url'=>$url,
            'url_all'=>get_upload_path($url),
        ];

		$this->success('操作成功','',$res);
	}

    /* 连麦检测 */
    public function getLinkInfo(){

        $data      = $this->request->param();

        $uid=isset($data['id']) ? checkNull($data['id']): '0';

        $userinfo=getUserInfo($uid);

        $this->success('操作成功','',$userinfo);
    }

    /* 更新课程模式 */
    public function upMode(){

        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $livemode=isset($data['livemode']) ? checkNull($data['livemode']): '0';

        if($courseid<1 ){
			$this->error('信息错误');
		}

        $isexist=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->find();
        if(!$isexist){
            $this->error('无权操作');
        }
        $update=['livemode'=>$livemode];
        if($lessonid>0){
            $isok=Db::name('course_lesson')->where(['uid'=>$uid,'id'=>$lessonid,'courseid'=>$courseid])->update($update);
        }else{
            $isok=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->update($update);
        }


        $this->success('操作成功');
    }

    /* 更新课程PPT页码 */
    public function upPPTindex(){

        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $activeIndex=isset($data['activeIndex']) ? checkNull($data['activeIndex']): '0';

        if($courseid<1 ){
			$this->error('信息错误');
		}

        $isexist=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->find();
        if(!$isexist){
            $this->error('无权操作');
        }

        if($lessonid>0){
            $update=['pptindex'=>$activeIndex];
            $isok=Db::name('course_lesson')->where(['courseid'=>$courseid,'id'=>$lessonid])->update($update);
        }else{

            $update=['pptindex'=>$activeIndex];
            $isok=Db::name('course')->where(['uid'=>$uid,'id'=>$courseid])->update($update);
        }


        $this->success('操作成功');
    }

    /* 声网开始录制 */
    public function createRecord(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

		if($courseid<1 ){
			$this->error('信息错误');
		}

        $nowtime=time();

		if($lessonid>0){
            $where=[
                'uid'=>$uid,
                'courseid'=>$courseid,
                'id'=>$lessonid,
            ];

            $info=Db::name('course_lesson')->where($where)->find();
        }else{
            $where=[
                'uid'=>$uid,
                'id'=>$courseid,
            ];

            $info=Db::name('course')->where($where)->find();
        }

		if(!$info){
            $this->error('无权操作');
        }

        if($info['islive']!=1){
            $this->error('还未开始上课');
        }

        if($info['resourceid']!='' && $info['sid']!=''){
            $this->error('已录制');
        }

        $stream=$uid.'_'.$courseid.'_'.$lessonid;

        $rs_create=AgoraModel::createRecord($stream,$uid);

        if($rs_create['code']!=0){
            $this->error($rs_create['msg']);
        }

        $resourceid=$rs_create['data']['resourceId'];

        /* 开始录制 */
        $rs_start=AgoraModel::startRecord($stream,$uid,$resourceid);
        if($rs_start['code']!=0){
            $this->error($rs_start['msg']);
        }

        $sid=$rs_start['data']['sid'];

        $data_up=[
            'resourceid'=>$resourceid,
            'sid'=>$sid,
        ];
        if($lessonid>0){
            $isok=Db::name('course_lesson')->where($where)->update($data_up);
        }else{
            $isok=Db::name('course')->where($where)->update($data_up);
        }

		if($isok===false){
            $this->error('操作失败，请重试');
        }

		$this->success('操作成功');
    }

    /* 练习-保存题目 */
    public function setPracticeList(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $list=isset($data['list']) ? checkNull($data['list']): '';
        $content=isset($data['content']) ? checkNull($data['content']): '';


        if($list==''){
            $this->error('题目信息错误');
        }

        $list_a=json_decode($list,true);
        if(!$list_a){
            $this->error('题目信息格式错误');
        }
        $content_a=json_decode($content,true);

        $key1='p_'.$uid.'_'.$courseid."_".$lessonid;
        $key2='p_r_'.$uid.'_'.$courseid."_".$lessonid;
        $key3='p_rs_'.$uid.'_'.$courseid."_".$lessonid;
        $key4='q_'.$uid.'_'.$courseid."_".$lessonid;
        delcache($key1);
        delcache($key2);
        delcache($key3);
        delcache($key4);

        setcaches($key1,$list_a);
        setcaches($key4,$content_a);
        $ls_right=[];
        foreach ($list_a as $k=>$v){
            $ls_right[]=0;
        }

        hMSet($key2,$ls_right);

        $this->success('操作成功');
    }
    /* 练习-得分统计 */
    public function getPracticeCount(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $scoretotal=isset($data['scoretotal']) ? checkNull($data['scoretotal']): '0';

        $info=[
            'c1'=>0,
            'c2'=>0,
            'c3'=>0,
            'c4'=>0,
            'c5'=>0,
        ];

        $c1=$scoretotal*0.9;
        $c2=$scoretotal*0.8;
        $c3=$scoretotal*0.7;
        $c4=$scoretotal*0.6;


        $key='p_rs_'.$uid.'_'.$courseid."_".$lessonid;
        $list=zRevRange($key,0,-1,true);

        foreach ($list as $k=>$v){
            if($v>=$c1){
                $info['c1']++;
            }else if($v>=$c2){
                $info['c2']++;
            }else if($v>=$c3){
                $info['c3']++;
            }else if($v>=$c4){
                $info['c4']++;
            }else{
                $info['c5']++;
            }
        }

        $this->success('操作成功','',$info);
    }

    /* 练习-各题对错统计 */
    public function getPracticePro(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        $key='p_r_'.$uid.'_'.$courseid."_".$lessonid;
        $list=hGetAll($key);

        $key2='p_rs_'.$uid.'_'.$courseid."_".$lessonid;
        $nums=zCard($key2);

        $info=[
            'list'=>$list,
            'nums'=>$nums,
        ];

        $this->success('操作成功','',$info);
    }

    /* 练习-平均分 */
    public function getPracticeAve(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        $score=0;
        $key='p_rs_'.$uid.'_'.$courseid."_".$lessonid;
        $list=zRevRange($key,0,-1,true);
        foreach ($list as $k=>$v){
            $score+=$v;
        }
        $ave=0;
        $nums=zCard($key);
        if($nums){
            $ave=bcdiv($score,$nums,2);
        }

        $info=[
            'ave'=>$ave
        ];

        $this->success('操作成功','',$info);
    }
    /* 练习-用户列表 */
    public function getPracticeUser(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        $userlist=[];
        $rank=1;
        $n=1;
        $score=0;
        $key='p_rs_'.$uid.'_'.$courseid."_".$lessonid;
        $list=zRevRange($key,0,-1,true);
        foreach ($list as $k=>$v){
            $userinfo=getUserInfo($k);
            if($n==1){
                $score=$v;
            }
            if($score!=$v){
                $rank++;
            }

            $userinfo['score']=(string)$v;
            $userinfo['rank']=(string)$rank;

            $userlist[]=$userinfo;

            $score=$v;
            $n++;
        }

        $this->success('操作成功','',$userlist);
    }

    /* 练习-添加草稿 */
    public function setPracticeDraft(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $title=isset($data['title']) ? checkNull($data['title']): '';
        $time=isset($data['time']) ? checkNull($data['time']): '';
        $answer=isset($data['answer']) ? checkNull($data['answer']): '';
        $content=isset($data['content']) ? checkNull($data['content']): '';
        $nums=isset($data['nums']) ? checkNull($data['nums']): '0';
        $score=isset($data['score']) ? checkNull($data['score']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';

        if($answer=='' || $nums<1 || $score<=0){
            $this->error('信息错误');
        }

        if($title==''){
            $this->error('请填写简介');
        }

        if($time<=0){
            $this->error('请设置答题时间');
        }

        $list_a=json_decode($answer,true);
        if(!$list_a){
            $this->error('题目信息格式错误');
        }

        if($type!=0){
            $type=1;
        }

        if($type==1){
            if($content==''){
                $this->error('信息错误');
            }

            $list_a=json_decode($content,true);
            if(!$list_a){
                $this->error('题目信息格式错误');
            }
        }

        $insert=[
            'type'=>$type,
            'uid'=>$uid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'title'=>$title,
            'time'=>$time,
            'answer'=>$answer,
            'content'=>$content,
            'nums'=>$nums,
            'score'=>$score,
            'addtime'=>time(),
        ];
        $rs=Db::name('practice_draft')->insert($insert);
        if(!$rs){
            $this->error('保存失败，请重试');
        }

        $this->success('保存成功');
    }
    /* 练习-草稿箱 */
    public function getPracticeDraft(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        $list=Db::name('practice_draft')
                ->field('id,type,title,content,answer,nums,score,time')
                ->where([['uid','=',$uid],['courseid','=',$courseid],['lessonid','=',$lessonid]])
                ->order('id desc')
                ->select()
                ->toArray();
        foreach ($list as $k=>$v){
            $v['answer']=json_decode($v['answer'],true);
            $v['content']=json_decode($v['content'],true);
            $list[$k]=$v;
        }

        $this->success('','',$list);
    }

    /* 练习-删除草稿 */
    public function delPracticeDraft(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $id=isset($data['id']) ? checkNull($data['id']): '';

        if($id<=0){
            $this->error('信息错误');
        }

        $rs=Db::name('practice_draft')->where([['id','=',$id],['uid','=',$uid],['courseid','=',$courseid],['lessonid','=',$lessonid]])->delete();
        if($rs===false){
            $this->error('删除失败，请重试');
        }

        $this->success('删除成功');
    }
    /* 抢答上麦 */
    public function setLinkmicByrob(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';
        $stream=isset($data['stream']) ? checkNull($data['stream']): '';
        $touid=isset($data['touid']) ? checkNull($data['touid']): '0';

        if($courseid<0 || $touid<1){
            $this->error('信息错误');
        }

        $where1=['id'=>$courseid];
        $courseinfo=Db::name('course')->where($where1)->find();
        if(!$courseinfo){
            $this->error('信息错误');
        }

        if($courseinfo['uid']!=$uid ){
            $this->error('无权操作');
        }

        /* 上麦后修改列表顺序 */
        $key2='user_'.$stream;
        zAdd($key2,'1',$touid);

        $this->success('操作成功');

    }

    /* 清除抢答列表 */
    public function clearRobUser(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        $key2='rob_u_'.$uid.'_'.$courseid."_".$lessonid;
        delcache($key2);

        $this->success('操作成功');

    }

    /* 表扬记录 */
    public function setPraise(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $touid=isset($data['touid']) ? checkNull($data['touid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '0';
        if($touid<1 || $type<1 || $type>4){
            $this->error('信息错误');
        }

        $res=Db::name('praise')->where(['uid'=>$touid,'type'=>$type])->inc('nums','1')->update();
        if(!$res){
            Db::name('praise')->insert(['uid'=>$touid,'type'=>$type,'nums'=>1]);
        }

        $res2=Db::name('praise_new')->where(['uid'=>$touid])->inc('nums','1')->update();
        if(!$res2){
            Db::name('praise_new')->insert(['uid'=>$touid,'nums'=>1]);
        }

        $this->success('操作成功');
    }
}


