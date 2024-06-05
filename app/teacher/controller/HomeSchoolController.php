<?php

namespace app\teacher\controller;


use cmf\controller\TeacherBaseController;
use think\Db;

class HomeSchoolController extends  TeacherBaseController {

    public function initialize()
    {
        parent::initialize();
        $this->assign('cur','HomeSchool');
    }

    public function index(){
        $uid = session('teacher.id');
        $list = Db::name('class_teacher')->where('uid',$uid)->paginate(20);
        $key = 'getcourseclass';
        $course_class = getcaches($key);
        if(!$course_class){
            $course_class = DB::name('course_class')
                ->order("list_order asc")
                ->select();
        }
        $school_id = [];
        $class_id = [];

        foreach ($list as $k=>$v){
            $school_id[] = $v['school_id'];
            $class_id[] = $v['class_id'];
        }
        $course_class_info = array_column($course_class,null,'id');
        $school = Db::name('school')->order('list_order desc')->select();
        $school_info =array_column( $school->toArray(),null,'id');
        $class_info =array_column( Db::name('class')->where(['id'=>$class_id])->select()->toArray(),null,'id');
        $year = Db::name('year')->order('name desc')->select();
        $year_info =array_column( $year->toArray(),null,'id');
        foreach ($list as $k=>$v){
            $v['school_name'] = $school_info[$v['school_id']]['name'];
            $v['class_name'] = $class_info[$v['class_id']]['class'];
            $year_id = $class_info[$v['class_id']]['year_id'];
            $v['grade_name'] = $this->changeKey($v['key'],$year_id,$v['class_id'],$v['school_id']);
            $v['course_class_name'] = $course_class_info[$v['course_class_id']]['name'];
            $v['year'] = $year_info[$year_id]['name'];
            $list[$k] = $v;
        }

        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }






    public function regulate(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_teacher')->where('id',$id)->find();
        $uid = session('teacher.id');
        if($uid != $info['uid']){
            $this->error('参数异常');
        }
        if(!$info['is_director']){
            $this->error('您不班主任，无权操作');
        }

        $status = Db::name('class')->where('id',$info['class_id'])->find()['status'];

        $teacher = Db::name('class_teacher')->where('class_id',$info['class_id'])->select();

        $this->assign('teacher',$teacher);
        $this->assign('class_id',$info['class_id']);
        $this->assign('status',$status);
        return $this->fetch();
    }

    public function setregulate(){
        $param = $this->request->param();
        $status = $param['status'] ?? 0;
        $teacher_id = $param['teacher_id'] ?? 0;
        $class_id = $param['class_id'] ?? 0;
        if(!$class_id){
            $this->error('参数异常');
        }
        Db::name('class')->where('id',$class_id)->update(['status'=>$status]);
        if($teacher_id){
            Db::name('class_teacher')->where('class_id',$class_id)->update(['is_director'=>0]);
            Db::name('class_teacher')->where('id',$teacher_id)->update(['is_director'=>1]);
        }
        $this->success('成功',url('index'));
    }

    public function delClass(){
        $class_id  =$this->request->param('class_id');
        $exist =  Db::name('class')->where('id',$class_id)->delete();
        if(!$exist){
            $this->error('异常');
        }
        Db::name('class_chat')->where('class_id',$class_id)->delete();
        Db::name('class_chat_log')->where('class_id',$class_id)->delete();
        Db::name('class_home_work')->where('class_id',$class_id)->delete();
        Db::name('class_home_work_user')->where('class_id',$class_id)->delete();
        Db::name('class_inform')->where('class_id',$class_id)->delete();
        Db::name('class_inform_user')->where('class_id',$class_id)->delete();
        Db::name('class_log')->where('class_id',$class_id)->delete();
        Db::name('class_patriarch')->where('class_id',$class_id)->delete();
        Db::name('class_score')->where('class_id',$class_id)->delete();
        Db::name('class_score_info')->where('class_id',$class_id)->delete();
        Db::name('class_survey')->where('class_id',$class_id)->delete();
        Db::name('class_survey_user')->where('class_id',$class_id)->delete();
        Db::name('class_teacher')->where('class_id',$class_id)->delete();
        Db::name('student')->where('class_id',$class_id)->delete();
        $key = 'teacher_class'.$class_id;
        delcache($key);
        $key = 'patriarch_class'.$class_id;
        \App\delcache($key);
        $this->success('解散成功',url('index'));
    }

    public function class_log(){
        $class_id = $this->request->param('class_id',0,'intval');
        if(!$class_id){
            $this->error('网络异常',url('index'));
        }
        $userinfo = session('teacher');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $url = $this->siteUrl.'/api/?s=School.ClassGrade.ClassLog';
        $field = [
            'uid' => $uid,
            'token' => $token,
            'class_id'=>$class_id
        ];
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $info = $data['info'];
        $this->assign('info',$info);
        $this->assign('class_id',$class_id);
        return $this->fetch();
    }

    public function patriarchStatus(){
        $param = $this->request->param();
        $userinfo = session('teacher');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $url = $this->siteUrl.'/api/?s=School.ClassGrade.PatriarchStatus';
        $param['uid'] = $uid;
        $param['token'] = $token;
        $data = json_decode(curl_post($url,$param)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $this->success('成功');
    }


    public function teacher(){
        $param = $this->request->param();
        $class_id = $param['class_id'] ?? 0;
        if(!$class_id){
            $this->error('网络异常',url('index'));
        }
        $userinfo = session('teacher');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $param['uid'] = $uid;
        $param['token'] = $token;
        $url = $this->siteUrl.'/api/?s=School.ClassGrade.People';
        $data = json_decode(curl_post($url,$param)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $list = $data['info']['teacher'];
        $this->assign('list',$list);
        return $this->fetch();
    }


    public function patriarch(){
        $param = $this->request->param();
        $class_id = $param['class_id'] ?? 0;
        if(!$class_id){
            $this->error('网络异常',url('index'));
        }
        $userinfo = session('teacher');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $exist= Db::name('class_teacher')->where(['class_id'=>$class_id,'uid'=>$uid])->find();
        if(!$exist)$this->error('网络异常',url('index'));
        $param['teacher_id'] = $exist['id'];
        $param['uid'] = $uid;
        $param['token'] = $token;
        $param['type'] = 2;
        $url = $this->siteUrl.'/api/?s=School.ClassGrade.Contacts';
        $data = json_decode(curl_post($url,$param)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $list = $data['info']['patriarch'];
        $this->assign('list',$list);
        return $this->fetch();
    }

    public function student(){
        $param = $this->request->param();
        $class_id = $param['class_id'] ?? 0;
        if(!$class_id){
            $this->error('网络异常',url('index'));
        }
        $userinfo = session('teacher');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $param['uid'] = $uid;
        $param['token'] = $token;
        $param['type'] = 2;
        $url = $this->siteUrl.'/api/?s=School.Student.List';
        $data = json_decode(curl_post($url,$param)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $list = $data['info'];
        $this->assign('list',$list);
        return $this->fetch();
    }
}