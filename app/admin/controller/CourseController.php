<?php

/* 课程 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class CourseController extends AdminBaseController
{
    /* 状态 */
    protected function getStatus($k=''){
        $status=[
            '-2'=>'管理员下架',
            '-1'=>'暂时下架',
            '0'=>'审核中',
            '1'=>'上架中',
            '2'=>'定时上架',
        ];
        
        if($k===''){
            return $status;
        }
        return isset($status[$k])? $status[$k] : '' ;
    }
    
    /* 类别 */
    protected function getSort($k=''){
        $sort=[
            '0'=>'自学模式',
            '1'=>'课程',
            '2'=>'直播',
        ];
        if($k===''){
            return $sort;
        }
        return isset($sort[$k])? $sort[$k] : '' ;
    }
    
    /* 内容形式 */
    protected function getTypes($k=''){
        $type=[
            '1'=>'图文自学',
            '2'=>'视频自学',
            '3'=>'音频自学',
        ];
        if($k===''){
            return $type;
        }
        return isset($type[$k])? $type[$k] : '' ;
    }

    /* 获取方式 */
    protected function getPayTypes($k=''){
        $paytype=[
            '0'=>'免费',
            '1'=>'收费',
            '2'=>'密码',
        ];
        if($k===''){
            return $paytype;
        }
        return isset($paytype[$k])? $paytype[$k] : '' ;
    }
    
    /* 试学 */
    protected function getTrialTypes($k=''){
        $trialtype=[
            '0'=>'否',
            /* '1'=>'链接', */
            '2'=>'进度',
        ];
        if($k===''){
            return $trialtype;
        }
        return isset($trialtype[$k])? $trialtype[$k] : '' ;
    }
    
    /* 课程模式 */
    protected function getModes($k=''){
        $mode=[
            '0'=>'自由',
            '1'=>'解锁',
        ];
        if($k===''){
            return $mode;
        }
        return isset($mode[$k])? $mode[$k] : '' ;
    }
    
    /* 科目分类 */
    protected function getClass(){
        $list = Db::name('course_class')
            ->order("list_order asc")
            ->column('*','id');
        return $list;
    }
    
    /* 学级分类 */
    protected function getGrade(){
        $list = Db::name('course_grade')
            ->order("pid asc,list_order asc")
            ->column('*','id');
        $list2=[];
        foreach($list as $k=>$v){
            if($v['pid']!=0){
                $name=$list[$v['pid']]['name'].' - '.$v['name'];
                $v['name']=$name;
                
                $list2[$k]=$v;
            }
        }
        return $list2;
    }

    /* 教材版本 */
    protected function getBookVersions(){
        $key = 'getbookversions';
        $data = getcaches($key);
        if(!$data){
            $data = DB::name('book_versions')
                ->order("list_order asc")
                ->select();
            if ($data) {
                setcaches($key, $data);
            } else {
                delcache($key);
            }
        }
        return $data;
    }


    /* 教材版本 */
    protected function getClassify(){
        $key = 'getcourseclassify';
        $data = getcaches($key);
        if(!$data){
            $data = DB::name('course_classify')
                ->order("list_order asc")
                ->select();
            if ($data) {
                setcaches($key, $data);
            } else {
                delcache($key);
            }
        }
        return $data;
    }

    
    public function index()
    {
        $data = $this->request->param();
        $map=[];
        
        $sort=isset($data['sort']) ? $data['sort']: '1';
        $map[]=['sort','=',$sort];
        
        
        $start_time=isset($data['start_time']) ? $data['start_time']: '';
        $end_time=isset($data['end_time']) ? $data['end_time']: '';
        
        if($start_time!=""){
           $map[]=['addtime','>=',strtotime($start_time)];
        }

        if($end_time!=""){
           $map[]=['addtime','<=',strtotime($end_time) + 60*60*24];
        }
        
        
        $status=isset($data['status']) ? $data['status']: '';
        if($status!=''){
            $map[]=['status','=',$status];
        }
        
        $gradeid=isset($data['gradeid']) ? $data['gradeid']: '';
        if($gradeid!=''){
            $map[]=['gradeid','=',$gradeid];
        }
        
        $classid=isset($data['classid']) ? $data['classid']: '';
        if($classid!=''){
            $map[]=['classid','=',$classid];
        }
        
        $paytype=isset($data['paytype']) ? $data['paytype']: '';
        if($paytype!=''){
            $map[]=['paytype','=',$paytype];
        }
        
        
        $type=isset($data['type']) ? $data['type']: '';
        if($type!=''){
            $map[]=['type','=',$type];
        }
        
        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['name','like','%'.$keyword.'%'];
        }
        
        $uid=isset($data['uid']) ? $data['uid']: '';
        if($uid!=''){
			$map[]=['uid','=',$uid];
        }

        $book_versions_id = isset($data['book_versions_id']) ? $data['book_versions_id']: '';

        if($book_versions_id!=''){
            $map[]=['book_versions_id','=',$book_versions_id];
        }


        $course_classify_id =  isset($data['course_classify_id']) ? $data['course_classify_id']: '';
        if($course_classify_id!=''){
            $map[]=['course_classify_id','=',$course_classify_id];
        }
        $list = Db::name("course")
                ->where($map)
                ->order("id desc")
                ->paginate(20);
        
        $list->each(function($v,$k){
            
            $v['userinfo']=getUserInfo($v['uid']);
            $v['thumb']=get_upload_path($v['thumb']);
            $nowtime=time();
            if($v['status']>0){
                if($v['shelvestime']<$nowtime){
                    $v['status']=1;
                }
            }
            
            return $v;           
        });

        $bookVersions = $this->getBookVersions();
        $list->appends($data);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);

        $this->assign('sort', $sort);
        $this->assign('types', $this->getTypes());
        $this->assign('bookVersions', array_column($bookVersions,null,'id'));
        $this->assign('classify', array_column($this->getClassify(),null,'id'));
        $this->assign('status', $this->getStatus());
        $this->assign('classs', $this->getClass());
        $this->assign('grade', $this->getGrade());
        $this->assign('paytypes', $this->getPayTypes());
        $this->assign('trialtypes', $this->getTrialTypes());
        // 渲染模板输出
        return $this->fetch('index');
    }
    function contents(){
        return $this->index();
	} 
    function live(){
        return $this->index();
	}
    
    function live_video(){
        return $this->index();
	}

    function white(){
        return $this->index();
    }
    
    /* 更新 */
    function setstatus(){
        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->param('status', 0, 'intval');
        
        $nowtime=time();
        $data=['status'=>$status];
        if($status==1){
            $info = DB::name('course')->where(['id'=>$id])->find();
            if($info){
                if($info['shelvestime']>$nowtime){
                    $data['shelvestime']=$nowtime;
                }
            }
        }
        
        $rs = DB::name('course')->where(['id'=>$id])->update(['status'=>$status]);
        if(!$rs){
            $this->error("操作失败！");
        }
        
        
        $this->success("操作成功！");
        							  			
    }
    
    protected function getUser($uid=0){

        $map=[];
        if($uid!=0){
            $map[]=['id','=',$uid];
        }else{
            $uid = array_column(Db::name('class_teacher')->select()->toArray(),'uid');
            $map[]=['id','in',$uid];
        }
        
        $list=Db::name('users')->field('id,user_nickname')->where($map)->select()->toArray();
        
        return $list;
    }
    
    public function getUserList(){
        $data = $this->request->param();
        
        $uid=isset($data['uid']) ? $data['uid']: '0';
        
        $list=$this->getUser($uid);
        
        $this->success('','',$list);
    }
    
    public function add()
    {
        $data = $this->request->param();
        
        $sort=isset($data['sort']) ? $data['sort']: '1';
        
        $this->assign('sort', $sort);

        $this->assign('types', $this->getTypes());

        
        $this->assign('classs', $this->getClass());
        $this->assign('grade', $this->getGrade());
        $this->assign('paytypes', $this->getPayTypes());
        $this->assign('trialtypes', $this->getTrialTypes());
        $this->assign('modes', $this->getModes());
        $this->assign('bookVersions', $this->getBookVersions());
        $this->assign('classify', $this->getClassify());
        $txsign='';
        $configpri=getConfigPri();
        $trans_switch=$configpri['trans_switch'];
        if($trans_switch==1){
            $txsign=getTxVodSign();
        }

        $this->assign('trans_switch', $trans_switch);
        $this->assign('txsign', $txsign);
        
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            
            $sort=$data['sort'];
            $uid=isset($data['uid'])?$data['uid']:'';
            if($uid || in_array($sort,[3,4])){
                if(!$uid){
                    $this->error('请选择主讲老师');
                }
                $isexist=DB::name('users')->field('type')->where('id','=',$uid)->find();
                if(!$isexist){
                    $this->error('该主讲老师不存在');
                }
                $isexist_teacher = Db::name('class_teacher')->where(['uid'=>$uid])->find();
                if(!$isexist_teacher){
                    $this->error('该ID还不是老师');
                }

                if($sort>=1){
                    $tutoruid=isset($data['tutoruid'])?$data['tutoruid']:'0';
                    if($tutoruid>0){
                        if($tutoruid==$uid){
                            $this->error('主讲老师和辅导老师不能是同一个人');
                        }

                        $isexist=DB::name('users')->field('type')->where('id','=',$tutoruid)->find();
                        if(!$isexist){
                            $this->error('该辅导老师不存在');
                        }
                        $isexist_teacher = Db::name('class_teacher')->where(['uid'=>$tutoruid])->find();
                        if(!$isexist_teacher){
                            $this->error('该ID还不是老师');
                        }
                    }else{
                        $data['tutoruid']=0;
                    }
                }
            }

            $semester = $data['semester'] ?? 0;
            if(!$semester){
                $this->error('请选择学期');
            }

            if(!in_array($sort,[3,4])){
                $book_versions_id = $data['book_versions_id'] ?? 0;
                if(!$book_versions_id){
                    $this->error('请选择教材版本');
                }
                $classid=$data['classid'] ?? 0;
                if($classid<1){
                    $this->error('请选择科目分类');
                }
            }

            $course_classify_id = $data['course_classify_id']?? 0;
            if(!$course_classify_id){
                $this->error('请选择课程分类');
            }
            $name=$data['name'];
            if($name == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'];
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $paytype=$data['paytype'];
            $payval=$data['payval'];
            if($paytype==0){
                unset($data['payval']);
                unset($data['trialtype']);
            }
            if($paytype==1){
                if($payval==''){
                    $this->error('请输入价格');
                }
                if($payval<0.01){
                    $this->error('请输入正确的价格');
                }
            }
            
            if($paytype==2){
                if($payval==''){
                    $this->error('请输入密码');
                }
                
            }
            
            if($sort==3){
                $data['type']=5;
            }if($sort==4){
                $data['type']=4;
            }else if($sort!=1){
                $trialtype=isset($data['trialtype'])?$data['trialtype']:0;
                /* 内容类 */
                $type=$data['type'];
                if($type == 1){
                    /* 图文 */
                    if($sort==0 && $trialtype==2){
                        $trialval_1=$data['trialval_1'];
                        if($trialval_1 == ''){
                            $this->error('请填写进度比例');
                        }
                        if($trialval_1<=0 || $trialval_1>=100){
                            $this->error('请填写正确的比例');
                        }
                        $data['trialval']=$trialval_1;
                        
                    }
                }
                
                if($type==2){
                    /* 视频 */
                    $type_video=$data['type_video'];
                    if($type_video == ''){
                        $this->error('请上传视频');
                    }
                    $data['url']=$type_video;
                    
                    if($sort==0 && $trialtype==2){
                        $trialval_2=$data['trialval_2'];
                        if($trialval_2 == ''){
                            $this->error('请填写进度时长');
                        }
                        if($trialval_2<=0 ){
                            $this->error('请填写正确的时长');
                        }
                        $data['trialval']=$trialval_2;
                        
                    }
                    
                }
                
                if($type==3){
                    /* 音频 */
                    $type_audio=$data['type_audio'];
                    if($type_audio == ''){
                        $this->error('请上传音频');
                    }
                    $data['url']=$type_audio;
                    
                    if($sort==0 && $trialtype==2){
                        $trialval_2=$data['trialval_2'];
                        if($trialval_2 == ''){
                            $this->error('请填写进度时长');
                        }
                        if($trialval_2<=0 ){
                            $this->error('请填写正确的时长');
                        }
                        $data['trialval']=$trialval_2;
                        
                    }
                    
                }
                
            }
            $configpri=getConfigPri();
            $nowtime=time();
            $status=$data['status'];
            $shelvestime=$data['shelvestime'];
            unset($data['shelvestime']);
            if($status == 2){
                if($shelvestime==''){
                    $this->error('请填写上架时间');
                }
                
                $data['shelvestime']=strtotime($shelvestime);
            }else{
                $data['shelvestime']=$nowtime;
            }
            
            if($sort==2 || $sort==3 || $sort==4){
                $starttime=$data['starttime'];
                if($starttime==''){
                    $this->error('请填写上课时间');
                }
                $data['starttime']=strtotime($starttime);
                
                $endtime=$data['endtime'];
                if($endtime==''){
                    $this->error('请填写下课时间');
                }
                $data['endtime']=strtotime($endtime);
                
                if($data['starttime']>=$data['endtime']){
                    $this->error('下课时间不能早于上课时间');
                }
            }
            
            
            $info=isset($data['info'])?$data['info']:'';
            if($info == ''){
                $this->error('请编辑介绍');
            }
            
            if($sort==0){
                $content=isset($data['content'])?$data['content']:'';
                if($content == ''){
                    $this->error('请编辑内容');
                }
            }

            if($sort==4){
                /* 白板 */

                $white_type=$configpri['whiteboard_type'];
                $data['whitetype']=$white_type;
                if($white_type==1){
                    $res=createNetlessRoom();
                    if(!$res){
                        $this->error('创建白板失败');
                    }

                    if($res['code']!=200){
                        $this->error($res['msg']['reason']);
                    }

                    $data['uuid']=$res['msg']['room']['uuid'];
                    $data['roomtoken']=$res['msg']['roomToken'];
                }

            }

            /* 去除无用字段 */
            unset($data['type_video']);
            unset($data['type_audio']);
            unset($data['trialval_1']);
            unset($data['trialval_2']);

            $data['addtime']=$nowtime;
            $data['rtc_type']=$configpri['rtc_type'];

            $data['tx_trans']=1;

            $tx_fileid=$data['tx_fileid'] ?? '';
            if($tx_fileid!=''){
                $data['tx_trans']=0;
            }

            $id = DB::name('course')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }

            if( $tx_fileid !='' ){
                tx_sendTrans($tx_fileid);
            }
            
            $this->success("添加成功！",url("course/index",['sort'=>$sort]) );
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('course')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $data['userinfo']=getUserInfo($data['uid']);
        if($data['tutoruid']){
            $data['tutorinfo']=getUserInfo($data['tutoruid']);
        }
        
        $this->assign('data', $data);
        $this->assign('bookVersions', $this->getBookVersions());
        $sort=$data['sort'];
        
        $this->assign('sort', $sort);
            $this->assign('types', $this->getTypes());

        $this->assign('classify', $this->getClassify());
        $this->assign('classs', $this->getClass());
        $this->assign('grade', $this->getGrade());
        $this->assign('paytypes', $this->getPayTypes());
        $this->assign('trialtypes', $this->getTrialTypes());
        $this->assign('modes', $this->getModes());

        $txsign=getTxVodSign();
        $this->assign('txsign', $txsign);
        
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $sort=$data['sort'];
            $courseid=isset($data['id'])?$data['id']:'0';
            if($courseid==0){
                $this->error('课程信息错误');
            }
            $nowtime=time();
            $uid=isset($data['uid'])?$data['uid']:'';
            if($sort==1){
                /* 判断是否上课中 */
                $isexists=Db::name('course_lesson')->where([['courseid','=',$courseid],['starttime','<=',$nowtime],['endtime','>',$nowtime]])->find();
                if($isexists){
                    $this->error('上课期间不能修改信息');
                }
                $isexists = Db::name('course_lesson')->where([['courseid','=',$courseid],['starttime','<>',0]])->find();
                if($isexists){
                    $this->error('存在直播课时，老师必须选择');
                }
            }

            if($uid ||  in_array($sort,[3,4])){
                if(!$uid){
                    $this->error('请选择主讲老师');
                }
                $isexist=DB::name('users')->field('type')->where('id','=',$uid)->find();
                if(!$isexist){
                    $this->error('该主讲老师不存在');
                }
                $isexist_teacher = Db::name('class_teacher')->where(['uid'=>$uid])->find();
                if(!$isexist_teacher){
                    $this->error('该ID还不是老师');
                }

                if($sort>=1){
                    $tutoruid=isset($data['tutoruid'])?$data['tutoruid']:'';
                    if($tutoruid>0){
                        if($tutoruid==$uid){
                            $this->error('主讲老师和辅导老师不能是同一个人');
                        }

                        $isexist=DB::name('users')->field('type')->where('id','=',$tutoruid)->find();
                        if(!$isexist){
                            $this->error('该辅导老师不存在');
                        }
                        $isexist_teacher = Db::name('class_teacher')->where(['uid'=>$tutoruid])->find();
                        if(!$isexist_teacher){
                            $this->error('该ID还不是老师');
                        }
                    }else{
                        $data['tutoruid']=0;
                    }
                }
            }else{
                $data['uid'] = 0;
            }

            $semester = $data['semester'] ?? 0;
            if(!$semester){
                $this->error('请选择学期');
            }

            if(!in_array($sort,[3,4])){
                $book_versions_id = $data['book_versions_id'] ?? 0;
                if(!$book_versions_id){
                    $this->error('请选择教材版本');
                }

                $classid=$data['classid'] ?? 0;
                if($classid<1){
                    $this->error('请选择科目分类');
                }
            }

            $course_classify_id = $data['course_classify_id']?? 0;
            if(!$course_classify_id){
                $this->error('请选择课程分类');
            }
            $name=$data['name'];
            if($name == ''){
                $this->error('请填写名称');
            }
            
            /* $map[]=['name','=',$name];
            $isexist = DB::name('course')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            } */
            
            $thumb=$data['thumb'];
            if($thumb == ''){
                $this->error('请上传封面');
            }
            
            $paytype=$data['paytype'];
            $payval=$data['payval'];
            if($paytype==0){
                unset($data['payval']);
                unset($data['trialtype']);
            }
            if($paytype==1){
                if($payval==''){
                    $this->error('请输入价格');
                }
                if($payval<0.01){
                    $this->error('请输入正确的价格');
                }
                
            }
            
            if($paytype==2){
                if($payval==''){
                    $this->error('请输入密码');
                }
                
            }
            
            if($sort==3){
                $data['type']=5;
            }elseif($sort==4){
                $data['type']=4;
            }else if($sort!=1){
                $trialtype=isset($data['trialtype'])?$data['trialtype']:0;
                /* 内容类 */
                $type=$data['type'];
                if($type == 1){
                    /* 图文 */
                    if($sort==0 && $trialtype==2){
                        $trialval_1=$data['trialval_1'];
                        if($trialval_1 == ''){
                            $this->error('请填写进度比例');
                        }
                        if($trialval_1<=0 || $trialval_1>=100){
                            $this->error('请填写正确的比例');
                        }
                        $data['trialval']=$trialval_1;
                        
                    }
                }
                
                if($type==2){
                    /* 视频 */
                    $type_video=$data['type_video'];
                    if($type_video == ''){
                        $this->error('请上传视频');
                    }
                    $data['url']=$type_video;
                    
                    if($sort==0 && $trialtype==2){
                        $trialval_2=$data['trialval_2'];
                        if($trialval_2 == ''){
                            $this->error('请填写进度时长');
                        }
                        if($trialval_2<=0 ){
                            $this->error('请填写正确的时长');
                        }
                        $data['trialval']=$trialval_2;
                        
                    }
                    
                }
                
                if($type==3){
                    /* 音频 */
                    $type_audio=$data['type_audio'];
                    if($type_audio == ''){
                        $this->error('请上传音频');
                    }
                    $data['url']=$type_audio;
                    
                    if($sort==0 && $trialtype==2){
                        $trialval_2=$data['trialval_2'];
                        if($trialval_2 == ''){
                            $this->error('请填写进度时长');
                        }
                        if($trialval_2<=0 ){
                            $this->error('请填写正确的时长');
                        }
                        $data['trialval']=$trialval_2;
                        
                    }
                    
                }
                
            }
            
            $nowtime=time();
            $status=$data['status'];
            $shelvestime=$data['shelvestime'];
            unset($data['shelvestime']);
            if($status == 2){
                if($shelvestime==''){
                    $this->error('请填写上架时间');
                }
                
                $data['shelvestime']=strtotime($shelvestime);
            }
            
            if($sort==2 || $sort==3 || $sort==4){
                $starttime=$data['starttime'];
                if($starttime==''){
                    $this->error('请填写上课时间');
                }
                $data['starttime']=strtotime($starttime);
                
                $endtime=$data['endtime'];
                if($endtime==''){
                    $this->error('请填写下课时间');
                }
                $data['endtime']=strtotime($endtime);
                
                if($data['starttime']>=$data['endtime']){
                    $this->error('下课时间不能早于上课时间');
                }
            }
            
            
            $info=isset($data['info'])?$data['info']:'';
            if($info == ''){
                $this->error('请编辑介绍');
            }
            
            if($sort==0){
                $content=isset($data['content'])?$data['content']:'';
                if($content == ''){
                    $this->error('请编辑内容');
                }
            }
            
            /* 去除无用字段 */
            unset($data['type_video']);
            unset($data['type_audio']);
            unset($data['trialval_1']);
            unset($data['trialval_2']);
            unset($data['cdntype']);

            $data['uptime']=$nowtime;

            $istrans=0;

            $tx_fileid=$data['tx_fileid'] ?? '';
            if($tx_fileid!=''){
                $oldinfo=Db::name('course')->field('tx_fileid')->where('id',$data['id'])->find();
                if($oldinfo['tx_fileid']!=$tx_fileid){
                    $data['tx_trans']=0;
                    $istrans=1;
                }
            }

            $rs = DB::name('course')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }

            if($istrans==1){
                tx_sendTrans($tx_fileid);
            }
            
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('course');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        /* 套餐中是否含有 */
        $isexist=Db::name('course_package')->where([['courseids','like',"%[{$id}]%"]])->find();
        if($isexist){
            $this->error("已有套餐中包含该课程，请先从套餐中删除");
        }

        $rs = DB::name('course')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        /* 删除 课时 */
        DB::name('course_lesson')->where(['courseid'=>$id])->delete();

        /* 删除购物车中的 */
        DB::name('cart')->where(['type'=>0,'typeid'=>$id])->delete();
        /* 删除 裂变海报 */
        DB::name('fissionposter')->where(['cid'=>$id])->delete();

        $this->success("删除成功！");
    }


    public function refer(){
        $param = $this->request->param();
        $status = $param['status'] ? 1 : 0;
        $id = $param['id'] ?? 0;
        if(!$id)$this->error('参数异常');
        $result = Db::name('course')->where('id',$id)->update(['is_refer'=>$status]);
        if($result === false)$this->error('网络异常');
        $this->success('成功');
    }
}