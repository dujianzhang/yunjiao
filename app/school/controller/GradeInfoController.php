<?php
namespace app\school\controller;

use cmf\controller\SchoolBaseController;
use think\Db;

class GradeInfoController extends SchoolBaseController{

  public function initialize()
  {
    parent::initialize();
    $this->assign('cur', 'index');
  }


  public function index(){
    $id = $this->request->param('id',0,'intval');
    if(!$id){
      $this->error('参数异常');
    }
    $school_id = session('school.id');
    $data = Db::name('class')->where(['school_id'=>$school_id, 'pid'=>$id])->paginate(10);
    $page = $data->render();
    $this->assign('page',$page);
    $this->assign('list',$data);
    return $this->fetch();
  }

  public function del(){
    $id = $this->request->param('id',0,'intval');
    if(!$id){
      $this->error('参数异常');
    }
    $school_id = session('school.id');
    $class_info = Db::name('class')->where(['id' => $id, 'school_id'=> $school_id])->find();
    $pid = $class_info['pid'] ?? 0;
    if(!$pid){
      $this->error('数据异常');
    }
    $class_grade_system_info = Db::name('class_grade_system')->where(['id' => $pid, 'school_id' => $school_id])->find();
    $count = $class_grade_system_info['count'] ?? 0;
    if($count){
      $update = [];
      $update['count'] = $count - 1;
        Db::name('class_grade_system')->where('id', $class_grade_system_info['id'])->update($update);
    }
    $result = Db::name('class')->where(['id'=>$id])->delete();
    if ($result === false) {
      $this->error('网络异常');
    }

      Db::name('class_chat')->where('class_id',$id)->delete();
      Db::name('class_chat_log')->where('class_id',$id)->delete();
      Db::name('class_home_work')->where('class_id',$id)->delete();
      Db::name('class_home_work_user')->where('class_id',$id)->delete();
      Db::name('class_inform')->where('class_id',$id)->delete();
      Db::name('class_inform_user')->where('class_id',$id)->delete();
      Db::name('class_log')->where('class_id',$id)->delete();
      Db::name('class_patriarch')->where('class_id',$id)->delete();
      Db::name('class_score')->where('class_id',$id)->delete();
      Db::name('class_score_info')->where('class_id',$id)->delete();
      Db::name('class_survey')->where('class_id',$id)->delete();
      Db::name('class_survey_user')->where('class_id',$id)->delete();
      Db::name('class_teacher')->where('class_id',$id)->delete();
      Db::name('student')->where('class_id',$id)->delete();
      $key = 'teacher_class'.$id;
      delcache($key);
      $key = 'patriarch_class'.$class_id;
      \App\delcache($key);
    $this->success('成功');
  }

  public function status(){
    $param = $this->request->param();
    $status = $param['status'] ?? 0;
    if(!in_array($status,[0,1])){
      $this->error('状态有误');
    }
    $id = $param['id'] ?? 0;
    if(!$id){
      $this->error('参数异常');
    }
    $result = Db::name('class')->where(['id'=>$id])->update(['status' => $status]);
    if($result === false){
      $this->error('网络异常');
    }
    $this->success('成功');
  }


  public function groupStatus(){
      $param = $this->request->param();
      $status = $param['status'] ?? 0;

      if(!in_array($status,[0,1])){
          $this->error('状态有误');
      }
      $id = $param['id'] ?? 0;
      if(!$id){
          $this->error('参数异常');
      }
      $result = Db::name('class')->where(['id'=>$id])->update(['is_group_open' => $status]);
      if($result === false){
          $this->error('网络异常');
      }
      $this->success('成功');
  }
}