<?php

namespace  app\admin\controller;



use cmf\controller\AdminBaseController;
use think\Db;
use think\Exception;

class InformController extends  AdminBaseController{



    public function index(){
        $param = $this->request->param();
        $keyword = $param['keyword'] ?? 0;
        $where = [];
        if($keyword){
            $where[] = ['title','like',"%{$keyword}%"];
        }
        $list = Db::name('class_inform')->where('type',3)->where($where)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $value = [
                'id' => $v['id'],
                'title' => $v['title'],
                'addtime' => date('Y-m-d H:i:s',$v['addtime']),
                'detail_people'=>'',
            ];
            if($v['school_show']){
                $value['detail_people'].= '学校后台 <br/>';
            }
            if($v['teacher_show']){
                $value['detail_people'].= '老师 <br/>';
            }
            if($v['patriarch_show']){
                $value['detail_people'].= '家长 <br/>';
            }
            $list[$k] = $value;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function add(){
        $school_info = Db::name('school')->select();
        $this->assign('school_info',$school_info);
        return $this->fetch();
    }

    public function addpost(){
        $param = $this->request->param();
        $title = $param['title'] ?? 0;
        $content = $param['content'] ?? 0;
        $images = $param['images'] ?? 0;
        $school_id = $param['school_id'] ?? [];
        $people_type = $param['people_type'] ?? 0;
        if(!$title){
            $this->error('请填写标题');
        }
        if(!$content){
            $this->error('请填写内容');
        }

        if(empty($param['images'])){
            $this->error('请上传通知图片');
        }
        if(!$people_type){
            $this->error('请选择接收人员');
        }

        $insert = [
            'uid' => 0,
            'course_class_id' => 0,
            'title' => $title,
            'content' => $content,
            'images' => json_encode($images),
            'class_id' => 0,
            'addtime' => time(),
            'type' => 3 ,
            'school_id'=>json_encode($school_id,true),
            'patriarch_show' => 0,
            'teacher_show' => 0 ,
            'school_show' => 0
        ];
        $http_url = getConfigPri()['http_url'].'/Push/eliminate';
        Db::startTrans();
        try{
            $where = [];
            if($school_id){
                $where['school_id'] = $school_id;
            }

            foreach ($people_type as $k=>$v){
                if($v == 1){
                    $insert['patriarch_show'] = 1;

                }elseif($v == 2){
                    $insert['teacher_show'] = 1;
                }else{
                    $insert['school_show'] = 1;
                }
            }
            $inform_id = Db::name('class_inform')->insertGetId($insert);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error('错误');
        }

        foreach ($people_type as $k=>$v){
            if($v == 1){
                Db::name('class_patriarch')->where($where)->setInc('inform_count', 1);
                $param_post = [
                    'field'=>'clearNotification',
                    'type' => '1',
                    'patriarch_id' => json_encode(Db::name('class_patriarch')->where($where)->column('id')),
                    'inform_id' => $inform_id
                ];
                curl_post($http_url,$param_post);

            }elseif($v == 2){
                Db::name('class_teacher')->where($where)->setInc('inform_count', 1);
                $param_post = [
                    'uid' => json_encode(Db::name('class_teacher')->where($where)->column('uid')),
                    'field'=>'clearNotification',
                    'type' => '2',
                    'inform_id' => $inform_id
                ];
                curl_post($http_url,$param_post);
            }else{
                $insert['school_show'] = 1;
            }
        }


        $this->success('成功');
    }


    public function content(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_inform')->where('type',3)->where(['id'=>$id])->find();
        if(!$info){
            $this->error('数据异常');
        }
        $images = json_decode($info['images'],true);
        foreach ($images as $k=>$v){
            $images[$k] = get_upload_path($v);
        }
        $info['images'] = $images;
        $info['school_id'] = json_decode($info['school_id'],true);
        $school_info = Db::name('school')->select();
        $this->assign('school_info',$school_info);
        $this->assign('info',$info);
        return $this->fetch();
    }


    public function detailList(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_inform')->where(['id'=>$id])->find();
        if(!$info){
            $this->error('数据异常');
        }
        $school_ids = json_decode($info['school_id'],true);
        $where = [];
        if($school_ids){
            $where['id'] = $school_ids;
        }
        $list = Db::name('school')->where($where)->paginate(20);
        $list->each(function ($v,$k)use ($id,$info){
           $v['status'] = Db::name('class_inform_user')->where(['school_id'=>$v['id'],'inform_id'=>$id])->find() ? 1 : 0;
           $v['school_show'] = $info['school_show'];
           return $v;
        });
        $this->assign('list',$list);
        $this->assign('info',$info);
        $this->assign('page',$list->render());
        return $this->fetch();
    }


    public function teacherDetail(){
        $param = $this->request->param();
        $inform_id = $param['inform_id'] ?? 0;
        $school_id = $param['school_id'] ?? 0;
        if(!$inform_id || !$school_id){
            $this->error('参数异常');
        }
        $list = Db::name('class_teacher')->where(['school_id'=>$school_id])->group('uid')->paginate(20);
        $list->each(function ($v,$k)use($inform_id){
            $v['status'] = Db::name('class_inform_user')->where(['teacher_uid'=>$v['uid'],'inform_id'=>$inform_id])->find() ? 1 : 0;
            return $v;
        });
        $this->assign('list',$list);
        $this->assign('page',$list->render());
        $this->assign('inform_id',$inform_id);
        return $this->fetch();
    }

    public function gradeDetail(){
        $param = $this->request->param();
        $inform_id = $param['inform_id'] ?? 0;
        $school_id = $param['school_id'] ?? 0;
        if(!$inform_id || !$school_id){
            $this->error('参数异常');
        }
        $data = Db::name('class_grade_system')->where('school_id', $school_id)->order('id desc')->paginate(20);
        $year =  array_column(Db::name('year')->select()->toArray(),null,'id');
        $data->each(function($v, $k)use($year) {
            $name = $this->changeKey($v['key'],$v['year_id'],$v['id'],$v['school_id']);
            $v['name'] = $name;
            $v['year'] = $year[$v['year_id']]['name'];
            return $v;
        });
        $page = $data->render();
        $this->assign('page',$page);
        $this->assign('list', $data);
        $this->assign('inform_id',$inform_id);
        return $this->fetch();
    }


    public function classDetail(){
        $param = $this->request->param();
        $inform_id = $param['inform_id'] ?? 0;
        $id = $param['id'] ?? 0;
        if(!$inform_id || !$id){
            $this->error('参数异常');
        }
        $data = Db::name('class')->where(['pid'=>$id])->paginate(10);
        $page = $data->render();
        $this->assign('page',$page);
        $this->assign('list',$data);
        $this->assign('inform_id',$inform_id);
        return $this->fetch();
    }


    public function patriarchDetail(){
        $param = $this->request->param();
        $class_id = $param['class_id'] ?? 0;
        $inform_id = $param['inform_id'] ?? 0;
        if(!$class_id || !$inform_id){
            $this->error('参数异常');
        }
        $patriach_info = Db::name('class_patriarch')->where(['class_id'=>$class_id])->order('id desc')->select();
        $complete_info = Db::name('class_inform_user')->where(['inform_id'=>$inform_id,'class_id'=>$class_id])->select();
        $student_id = array_column($complete_info->toArray(),'student_id');
        $student_info = Db::name('student')->where(['class_id'=>$class_id])->select();
        $student_info = array_column($student_info->toArray(),null,'id');
        $read = $unread = [];
        $relation_info = Db::name('relation')->order("list_order asc")->select()->toArray();
        $relation_info = array_column($relation_info,null,'id');
        $unread_student_id = [];
        foreach ($patriach_info as $k=>$v){
            if(in_array($v['student_id'],$student_id)){
                $read[] = [
                    'name' => $student_info[$v['student_id']]['name'] ,
                    'uid' => $v['uid'],
                    'relation' => $relation_info[$v['relation_id']]['name']
                ];
            }else{
                $unread_student_id[] = $v['student_id'];
            }
        }
        foreach ($unread_student_id as $k=>$v){
            $unread [] = [
                'name' => $student_info[$v]['name']
            ];
        }
        $this->assign('list',compact('read','unread'));
        $this->assign('inform_id',$inform_id);
        return  $this->fetch();
    }


    public function changeKey($old_key,$year_id,$id,$school_id){

        $class_grade_system = Db::name('class_grade_system')->where('id',$id)->find();
        $ini_key = $class_grade_system['ini_key'];

        $old_year = Db::name('year')->where(['id'=>$year_id])->find()['name'];
        $old_time = Db::name('class_grade')->where(['key'=>$ini_key])->find()['change_time'];

        $new_year = date('Y');
        $new_time = date('m-d');

        $year = $new_year-$old_year;
        $ini_key = $ini_key + $year;
        if(($old_time > $new_time) && $year){
            $ini_key -= 1;
        }


        if($old_key != $ini_key){
            $content = Db::name('school_grade')->field('content')->where('school_id',$school_id)->find()['content'] ?? [];
            $content = json_decode($content,true);
            $data = [];
            foreach ($content as $k=>$v){
                $value = $v;
                $info = $this->config[$k];
                foreach ($info as $keys => $item){
                    if(in_array($item['key'],$value)){
                        $data[] = $item;
                    }
                }
            }
            $data = array_column($data,null,'key');

            $name = $data[$ini_key]['name'] ?? 0;
            if(!$name){
                for($i=$old_key;$i<13;$i++){
                    if(isset($data[$i])){
                        $name =  $data[$i]['name'];
                        $ini_key = $i;
                        continue;
                    }
                }
            };
            if(!$name){
                $name = $this->configs[$old_key]['name'];
            }
            $class_id = Db::name('class')->where('pid',$id)->select()->column('id');
            Db::name('class_patriarch')->where(['class_id'=>$class_id])->update(['key'=>$ini_key]);
            Db::name('student')->where(['class_id'=>$class_id])->update(['key'=>$ini_key]);
            Db::name('class_teacher')->where(['class_id'=>$class_id])->update(['key'=>$ini_key]);
            Db::name('class')->where('pid',$id)->update(['key'=>$ini_key]);
            Db::name('class_grade_system')->where('id',$id)->update(['key'=>$ini_key]);

        }else{
            $name =  $this->configs[$old_key]['name'];
        }
        return $name;

    }
}