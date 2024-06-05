<?php

namespace app\admin\controller;


use cmf\controller\AdminBaseController;
use think\Db;

class StudentController extends  AdminBaseController{


    public function index()

    {
        $school_id = $this->request->param('school_id',0,'intval');
        $year_id =  $this->request->param('year_id',0,'intval');
        $key = $this->request->param('key',0,'intval');
        $where  = [];
        if($school_id){
            $where['school_id'] = $school_id;
        }
        if($year_id){
            $where['year_id'] = $year_id;
        }
        if($key){
            $where['key'] = $key;
        }
        $class_id = [];
        $list = Db::name('student')->order('list_order desc')->where($where)->paginate(20);
        foreach ($list as $k=>$v){
            $class_id[] = $v['class_id'];
        }
        $class_info =array_column( Db::name('class')->where(['id'=>$class_id])->select()->toArray(),null,'id');
        $school = Db::name('school')->order('list_order desc')->select();
        $year = Db::name('year')->order('name desc')->select();
        $school_info =array_column( $school->toArray(),null,'id');
        $year_info =array_column( $year->toArray(),null,'id');
        $list->each(function ($v,$k)use ($school_info,$year_info,$class_info){
            $v['grade_name'] = $this->changeKey($v['key'],$v['year_id'],$v['class_id'],$v['school_id']);
            $v['old_grade_name'] = $this->configs[$v['key']]['name'];
                $v['school_name'] = $school_info[$v['school_id']]['name'];
            $v['year'] = $year_info[$v['year_id']]['name'];
            $v['class_name'] = $class_info[$v['class_id']]['class'] ?? '班级不存在';
                return $v;
        });
        $this->assign('year',$year);
        $this->assign('school',$school);
        $this->assign('list',$list);
        $this->assign('configs',$this->configs);
        $this->assign('page',$list->render());
        return $this->fetch();
    }

    public function detail(){
        $param = $this->request->param();
        $class_id = $param['class_id'] ?? 0;
        $id  = $param['id'] ?? 0;
        if(!$class_id || !$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_patriarch')->where(['student_id'=>$id,'class_id'=>$class_id])->select();
        if(!$info){
            $this->error('信息有误');
        }
        $student = Db::name('student')->where(['id'=>$id])->find();
        $relation = Db::name('relation')
            ->order("list_order asc")->select()->column('name','id');
        foreach ($info as $k=>$v){
            $v['name'] = $student['name'] . $relation[$v['relation_id']];
            $info[$k] = $v;
        }
        $this->assign('info',$info);
        return $this->fetch();
    }




}