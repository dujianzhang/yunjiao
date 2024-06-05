<?php

namespace  app\admin\controller;


use cmf\controller\AdminBaseController;
use think\Db;

class TeacherController extends  AdminBaseController{


    public function index()
    {
        $school_id = $this->request->param('school_id',0,'intval');
        $key = $this->request->param('key',0,'intval');
        $where  = [];
        if($school_id){
            $where['school_id'] = $school_id;
        }
        if($key){
            $where['key'] = $key;
        }
        $list = Db::name('class_teacher')
            ->where($where)
            ->order("list_order asc")
            ->paginate(20);
        $school_id = [];
        $class_id = [];
        
        foreach ($list as $k=>$v){
            $school_id[] = $v['school_id'];
            $class_id[] = $v['class_id'];
        }
        $school = Db::name('school')->order('list_order desc')->select();
        $school_info =array_column( $school->toArray(),null,'id');
        $class_info =array_column( Db::name('class')->where(['id'=>$class_id])->select()->toArray(),null,'id');
        $course_class_info =array_column( Db::name('course_class')->select()->toArray(),null,'id');
        foreach ($list as $k=>$v){
            $v['school_name'] = $school_info[$v['school_id']]['name'];
            $v['class_name'] = $class_info[$v['class_id']]['class'];
            $year_id = $class_info[$v['class_id']]['year_id'];
            $v['grade_name'] = $this->changeKey($v['key'],$year_id,$v['class_id'],$v['school_id']);
            $v['course_class_name'] = $course_class_info[$v['course_class_id']]['name'];
            $list[$k] = $v;
        }
        $page = $list->render();
        $this->assign('configs',$this->configs);
        $this->assign('school',$school);
        $this->assign("page", $page);
        $this->assign('list', $list);

        return $this->fetch();
    }


}