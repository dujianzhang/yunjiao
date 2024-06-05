<?php

namespace  app\teacher\controller;



use cmf\controller\TeacherBaseController;
use think\Db;

class HomeworkController extends  TeacherBaseController{

    public $class_id;


    public function initialize()
    {
        parent::initialize();
        $this->assign('cur','HomeSchool');
        $action = $this->request->action();
        if(!in_array($action,['uploadimg'])){
            $class_id = $this->request->param('class_id',0,'intval');
            if(!$class_id){
                $this->error($action);
            }
            $this->class_id = $class_id;
            $this->assign('class_id',$class_id);
        }

    }


    public function index(){
        $param = $this->request->param();
        $course_class_id = $param['course_class_id'] ?? 0;
        $teacher_id = $param['teacher_id'] ?? 0;
        $keyword = $param['keyword'] ?? 0;
        $where = [];
        if($course_class_id){
            $where[] = ['course_class_id' ,'=',$course_class_id];
        }
        if($teacher_id){
            $where[] = ['uid','=',$teacher_id];
        }
        if($keyword){
            $where[] =  ['title' , 'like' , "%{$keyword}%"];
        }
        $key = 'getcourseclass';
        $course_class = getcaches($key);
        if(!$course_class){
            $course_class = DB::name('course_class')
                ->order("list_order asc")
                ->select();
        }
        $teacher = Db::name('class_teacher')->where('class_id',$this->class_id)->select()->toArray();
        $this->assign("teacher", $teacher);
        $teacher = array_column($teacher,null,'uid');
        $this->assign("course_class", $course_class);
        $course_class_info = array_column($course_class,null,'id');
        $list = Db::name('class_home_work')->where('class_id',$this->class_id)->where($where)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $value = [
                'id' => $v['id'],
                'title' => $v['title'],
                'addtime' => date('Y-m-d H:i:s',$v['addtime']),
                'course_class_name' => $course_class_info[$v['course_class_id']]['name'],
                'teacher_name' => $teacher[$v['uid']]['name']
            ];
            $list[$k] = $value;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function add(){
        return $this->fetch();
    }

    public function addpost(){
        $param = $this->request->param();
        $class_id = $param['class_id'];
        $title = $param['title'] ?? 0;
        $content = $param['content'] ?? 0;
        $images = $param['images'] ?? 0;
        if(!$title){
            $this->error('请填写标题');
        }
        if(!$content){
            $this->error('请填写内容');
        }
        if(count($images) < 1 || !$images){
            $this->error('请上传作业图片');
        }
        $images = json_encode($images);
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'title' => $title,
            'content' => $content,
            'images' => $images
        ];
        $url = $this->siteUrl.'/api/?s=School.Homework.Push';
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $this->success('成功');
        return $this->fetch();
    }


    public function detail(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('网络异常',url('index',['class_id'=>$this->class_id]));
        }
        $info = Db::name('class_home_work')->where('id',$id)->find();
        if(!$info){
            $this->error('信息不存在',url('index',['class_id'=>$this->class_id]));
        }
        $images = json_decode($info['images'],true);
        foreach ($images as $k=>$v){
            $images[$k] = get_upload_path($v);
        }
        $info['images'] = $images;
        $this->assign('info',$info);
        return $this->fetch();
    }

    public function info(){
        $param = $this->request->param();
        $id = $param['id'] ?? 0;
        $class_id = $param['class_id'] ?? 0;
        if(!$id){
            $this->error('网络异常',url('index',['class_id'=>$this->class_id]));
        }
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'id' => $id
        ];
        $url = $this->siteUrl.'/api/?s=School.Homework.Detail';
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $info = $data['info'];
        $this->assign('id',$id);
        $this->assign('list',$info);
        return $this->fetch();
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