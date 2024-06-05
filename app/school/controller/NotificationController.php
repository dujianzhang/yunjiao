<?php

namespace app\school\controller;


use cmf\controller\SchoolBaseController;
use think\Db;

class NotificationController extends  SchoolBaseController{




    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->assign('cur','notification');
    }

    public function index(){
        $param = $this->request->param();
        $school_id = session('school.id');
        $Ok_inform_id = array_column(Db::name('class_inform_user')->where(['school_id'=>$school_id])->select()->toArray(),'inform_id');
        $where = [
            ['type','<>',1],
            ['school_show','=',1]
        ];
        $whereOr = [
            ['school_id','like',"%{$school_id}%"],
            ['school_id','=',"[]"],
//            ['school_id','=',null],
            ['push_school_id','=',$school_id]
        ];
        $status = $param['status'] ?? '';
        if($status == 1){
            $where[] = ['id','IN',$Ok_inform_id];
        }elseif($status == 2){
            $where[] = ['id','not IN',$Ok_inform_id];
        }
        $keyword = $param['keyword'] ?? 0;
        if($keyword){
            $where[] = ['title' , 'like' , "%{$keyword}%"];
        }
        $type = $param['type'] ?? 0;
        if($type){
            $where[] = ['type' , '=' , $type];
        }
        $list = Db::name('class_inform')->where($where)->where(function ($query) use ($whereOr){
            $query->whereOr($whereOr);
        })->order('id desc')->paginate(20);

        $list->each(function ($v,$k){
            $v['addtime'] =  date('Y-m-d H:i:s',$v['addtime']);
            return $v;
        });
        $this->assign('list',$list);
        $this->assign('Ok_inform_id',$Ok_inform_id);
        $this->assign('page',$list->render());
        return $this->fetch();
    }



    public function detail(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_inform')->where(['id'=>$id])->find();
        if(!$info){
            $this->error('数据异常');
        }

        $images = json_decode($info['images'],true);
        foreach ($images as $k=>$v){
            $images[$k] = get_upload_path($v);
        }
        $info['images'] = $images;
        $school_id = session('school.id');
        if($info['push_school_id']){
            $info['keys'] = json_decode($info['keys'],true);
            $school_grade_key = Db::name('school_grade')->field('content')->where('school_id',$school_id)->find()['content'] ?? '[]';
            $school_grade_key = json_decode($school_grade_key,true);
            $key = [];
            foreach ($school_grade_key as $k=>$v){
                $key = array_merge($key,$v);
            }
            $content = [];
            foreach ($this->configs as $k=>$v){
                if(in_array($k,$key)){
                    $v['key'] = $k;
                    $content[] = $v;
                }
            }
            $this->assign('content',$content);
        }else{
            $ststus = Db::name('class_inform_user')->where(['inform_id'=>$id,'school_id'=>$school_id])->find();
            $this->assign('ststus',$ststus ? 1 : 0);

        }
        $this->assign('info',$info);
        return $this->fetch();
    }



    public function status(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('网络异常');
        }
        $school_id = session('school.id');
        $exist = Db::name('class_inform_user')->where(['inform_id'=>$id,'school_id'=>$school_id])->find();
        if($exist){
            $this->error('已确认收到 无需重复缺');
        }
        $insert = [
            'addtime'=> time(),
            'school_id' =>$school_id,
            'inform_id' => $id
        ];
        $result = Db::name('class_inform_user')->insert($insert);
        if($result === false){
            $this->error('网络异常');
        }
        $this->success('成功');
    }


    public function add(){
        $school_id = session('school.id');
        $school_grade_key = Db::name('school_grade')->field('content')->where('school_id',$school_id)->find()['content'] ?? '[]';
        $school_grade_key = json_decode($school_grade_key,true);
        $key = [];
        foreach ($school_grade_key as $k=>$v){
            $key = array_merge($key,$v);
        }
        $content = [];
        foreach ($this->configs as $k=>$v){
            if(in_array($k,$key)){
                $v['key'] = $k;
                $content[] = $v;
            }
        }
        $this->assign('content',$content);
        return $this->fetch();
    }


    public function addpost(){
        $param = $this->request->param();
        $title = $param['title'] ?? 0;
        $content = $param['content'] ?? 0;
        $images = $param['images'] ?? 0;
        $key = $param['key'] ?? [];
        $people_type = $param['people_type'] ?? 0;
        if(!$title){
            $this->error('请填写标题');
        }
        if(!$content){
            $this->error('请填写内容');
        }

        if(empty($images)){
            $this->error('请上传通知图片');
        }
        if(!$people_type){
            $this->error('请选择接收人员');
        }
        $school_id = session('school.id');
        $insert = [
            'uid' => 0,
            'course_class_id' => 0,
            'title' => $title,
            'content' => $content,
            'images' => json_encode($images),
            'class_id' => 0,
            'addtime' => time(),
            'type' => 2 ,
            'keys'=>json_encode($key,true),
            'patriarch_show' => 0,
            'teacher_show' => 0 ,
            'school_show' => 1,
            'push_school_id' => $school_id
        ];
        $http_url = getConfigPri()['http_url'].'/Push/eliminate';
        Db::startTrans();
        try{
            $where_ = ['school_id'=>$school_id];
            if($key){
                $where_['key'] = $key;
            }
            if($key){
                $class_id = Db::name('class_grade_system')->alias('A')
                    ->join('class B','A.id = B.pid')
                    ->where(['A.school_id'=>$school_id,'B.key'=>$key])->column('B.id');
            }else{
                $class_id = Db::name('class_grade_system')->alias('A')
                    ->join('class B','A.id = B.pid')
                    ->where(['A.school_id'=>$school_id])->column('B.id');
            }
            foreach ($people_type as $k=>$v){
                if($v == 1){
                    $insert['patriarch_show'] = 1;
                }elseif($v == 2){
                    $insert['teacher_show'] = 1;
                }
            }
            $inform_id = Db::name('class_inform')->insertGetId($insert);
            $where = [
                'class_id' => $class_id
            ];
            Db::name('class_patriarch')->where($where)->setInc('inform_count', 1);
            Db::name('class_teacher')->where($where)->setInc('inform_count', 1);

            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }


        foreach ($people_type as $k=>$v){
            if($v == 1){
                $insert['patriarch_show'] = 1;
                $param_post = [
                    'field'=>'clearNotification',
                    'type' => '1',
                    'patriarch_id' => json_encode(Db::name('class_patriarch')->where($where_)->column('id')),
                    'inform_id' => $inform_id
                ];
                curl_post($http_url,$param_post);
            }elseif($v == 2){
                $insert['teacher_show'] = 1;
                $param_post = [
                    'uid' => json_encode(Db::name('class_teacher')->where($where_)->column('uid')),
                    'field'=>'clearNotification',
                    'type' => '2',
                    'inform_id' => $inform_id
                ];
                curl_post($http_url,$param_post);
            }
        }


        $this->success('成功');
    }

    public function detailInform(){
        $param = $this->request->param();
        $inform_id = $param['id'] ?? 0;
        $school_id = session('school.id');
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
        return $this->fetch();
    }

    public function classdetail(){
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


    public function patriarchdetail(){
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

    public function uploadImg()
    {

        $file = isset($_FILES['file']) ? $_FILES['file'] : '';
        if (!$file) {
            $this->error('请选择图片');
        }
        if ($file['size'] == 0) {
            $this->error('不能上传空文件');
        }
        $res = upload($file, 'image');
        if ($res['code'] != 0) {
            $this->error($res['msg']);
        }
        $url = $res['url'];

        $rs = [
            'url' => get_upload_path($url),
            'path' => $url
        ];
        $this->success('', '', $rs);
    }
}