<?php
namespace app\school\controller;

use cmf\controller\SchoolBaseController;
use think\Db;

class ConfigController extends SchoolBaseController{



    public function initialize(){
        parent::initialize();
        $this->assign('cur','config');


    }

   public function index(){
        return $this->fetch();

   }

   public  function editpost(){
        $param = $this->request->param();
        $research_id = $param['research_id'] ?? [];
        $school_id = session('school.id');
        $update = [
            'research_id' => json_encode($research_id),
        ];
        $result = Db::name('school_config_research')->where('school_id',$school_id)->update($update);
        if($result === false){
            $this->error('失败');
        }
        $this->success('成功');
   }

    public function research(){
        $school_id = session('school.id');
        $school_config =Db::name('school_config_research')->where('school_id',$school_id)->find();
        $research_id = json_decode($school_config['research_id'],true) ??[];
        $research = Db::name('research')->order('id desc')->select();
        $this->assign('research',$research);
        $this->assign('research_id',$research_id);
        return $this->fetch();

    }

   public function course_class_index(){
       $school_id = session('school.id');
       $data = Db::name('school_config_course_class')->order('id desc')->where(['school_id'=>$school_id])->paginate(20);
       $course_class = Db::name('course_class')->order('id desc')->select();
       $book_versions = Db::name('book_versions')->order('id desc')->select();
       $course_class = array_column($course_class->toArray(),null,'id');
       $book_versions = array_column($book_versions->toArray(),null,'id');
        $data->each(function ($v,$k) use($course_class , $book_versions){
            $v['course_class_name'] = $course_class[$v['course_class_id']]['name'] ?? '不存在';
            $v['book_versions_name'] = $book_versions[$v['book_versions_id']]['name'] ?? '不存在';
            return $v;
        });
       $page = $data->render();
       $this->assign('list',$data);
       $this->assign('page',$page);
       return $this->fetch();
   }

    public function course_class_add(){
        $course_class = Db::name('course_class')->order('id desc')->select();
        $this->assign('course_class',$course_class);

        $book_versions = Db::name('book_versions')->order('id desc')->select();
        $this->assign('book_versions',$book_versions);
        return $this->fetch();
    }

    public function course_class_edit(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $school_id = session('school.id');
        $info = Db::name('school_config_course_class')->where(['school_id'=>$school_id,'id'=>$id])->find();
        if(!$info){
            $this->error('数据不存在');
        }
        $course_class = Db::name('course_class')->order('id desc')->select();
        $this->assign('course_class',$course_class);

        $book_versions = Db::name('book_versions')->order('id desc')->select();
        $this->assign('book_versions',$book_versions);
        $this->assign('info',$info);
        return $this->fetch();
    }

    public function course_class_add_post(){
        $param = $this->request->param();
        $course_class_id = $param['course_class_id'] ?? 0;
        $book_versions_id = $param['book_versions_id'] ?? 0;
        if(!$course_class_id){
            $this->error('请选择学科');
        }
        if(!$book_versions_id){
            $this->error('请选择教材版本');
        }
        $school_id = session('school.id');
        $info = Db::name('school_config_course_class')->where(['school_id'=>$school_id,'course_class_id'=>$course_class_id])->find();
        if($info){
            $this->error('学科已存在');
        }
        $insert = ['school_id'=>$school_id,'course_class_id'=>$course_class_id,'book_versions_id'=>$book_versions_id];
        $result = Db::name('school_config_course_class')->insert($insert);
        if($result === false){
            $this->error('添加失败');
        }
        $this->success('添加成功');
    }


    public function course_class_edit_post(){
        $param = $this->request->param();
        $id = $param['id'] ?? 0;
        if(!$id){
            $this->error('参数异常');
        }
        $book_versions_id = $param['book_versions_id'] ?? 0;

        if(!$book_versions_id){
            $this->error('请选择教材版本');
        }
        $school_id = session('school.id');

        $update= ['book_versions_id'=>$book_versions_id];
        $result = Db::name('school_config_course_class')->where(['id'=>$id,'school_id'=>$school_id])->update($update);
        if($result === false){
            $this->error('失败');
        }
        $this->success('成功');
    }

    public function course_class_add_del(){
        $id= $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('网络异常');
        }
        $school_id = session('school.id');
        $info =  Db::name('school_config_course_class')->where(['school_id'=>$school_id,'id'=>$id])->find();
        $exist  = Db::name('class_teacher')->where(['school_id'=>$school_id,'course_class_id'=>$info['course_class_id']])->find();
        if($exist){
            $this->error('已有使用该学科的老师，无法删除');
        }
        $result = Db::name('school_config_course_class')->where(['school_id'=>$school_id,'id'=>$id])->delete();
        if($result === false){
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }



    public function school_grade(){
        $school_id = session('school.id');
        $info = Db::name('school')->where('id',$school_id)->find();
        $content = Db::name('school_grade')->field('content')->where('school_id',$school_id)->find()['content'] ?? '[]';
        $content = json_decode($content,true);
        $type = json_decode($info['type']);
        $this->assign('type',$type);
        $this->assign('content',$content);
        $this->assign('config',$this->config);
        return $this->fetch();
    }

    public function set_school_grade(){
        $config = $this->request->param('config');
        $school_id = session('school.id');
        $info = Db::name('school')->where('id',$school_id)->find();
        $school_key = array_column(Db::name('class_grade_system')->where('school_id',$school_id)->select()->toArray(),'key');
        $type = json_decode($info['type']);
        $data = [];
        $set_key = [];
        foreach ($type as $k){
            $value = $config[$k] ?? 0;
            if($value){
                $data[$k] = $value;
                sort($value);
                $int = 0;
                foreach ($value as $key){
                    $set_key[] = $key;
                    if($int == 0){
                        $int = $key;
                    }else{
                        if($int != ($key-1)){
                            $this->error('每个阶段的年级只能选择连续的年级');
                        }
                        $int = $key;
                    }
                }
            }
        }

        foreach ($school_key as $k=>$v){
            if(!in_array($v,$set_key)){
                $this->error($this->configs[$v]['name'].'已经被使用，无法去除');
            }
        }
        $data = json_encode($data,true);
        $result = Db::name('school_grade')->where('school_id',$school_id)->update(['content'=>$data]);
        if($result === false){
            $this->error('错误');
        }
        $this->success('成功');
    }
}