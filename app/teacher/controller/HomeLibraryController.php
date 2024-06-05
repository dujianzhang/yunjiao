<?php

namespace  app\teacher\controller;



use cmf\controller\TeacherBaseController;
use think\Db;

class HomeLibraryController extends  TeacherBaseController{

    public $class_id;

    public function initialize()
    {
        parent::initialize();
        $this->assign('cur','HomeSchool');
        $action = $this->request->action();
        if(!in_array($action,['upload'])){
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
            $where['course_class_id'] = ['course_class_id','=',$course_class_id];
        }
        if($teacher_id){
            $where[] = ['uid','=',$teacher_id];
        }
        if($keyword){
            $where[] =  ['file_name|text_title' , 'like' , "%{$keyword}%"];
        }
        $teacher = Db::name('class_teacher')->where('class_id',$this->class_id)->select()->toArray();
        $this->assign("teacher", $teacher);
        $teacher = array_column($teacher,null,'uid');
        $list = Db::name('library')->where('class_id',$this->class_id)->where($where)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $v['teacher_name'] = $teacher[$v['uid']]['name'];
            $v['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
            if($v['type'] == 1){
                $v['suffix'] = substr($v['file_name'],strrpos($v['file_name'], '.')+1);
                $v['file_url'] = get_upload_path($v['file_url']);
            }
            $list[$k] = $v;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function add(){
        $library_class = Db::name('library_class')->order('list_order desc')->select();
        $this->assign('library_class',$library_class);
        return $this->fetch();
    }

    public function addpost(){
        $param = $this->request->param();
        $class_id = $param['class_id'];
        $library_class_id = $param['library_class_id'] ?? 0;
        if(!$library_class_id){
            $this->error('请选择文库分类');
        }

        $type = $param['type'] ?? 0;
        if(!$type){
            $this->error('请选择类型');
        }
        $file_name= $param['file_name'] ?? 0;
        $file_url =  $param['file_url'] ?? 0;
        $text_content = $param['text_content'] ?? 0;
        $text_title =  $param['text_title'] ?? 0;
        if($type == 1){
            if(!$file_name || !$file_url){
                $this->error('上传文件');
            }
        }else{
            if(!$text_title){
                $this->error('请填写名称');
            }
            if(!$text_content){
                $this->error('请填写文本内容');
            }
        }
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'file_name' => $file_name,
            'file_url' => $file_url,
            'text_content' => $text_content,
            'text_title' => $text_title,
            'type'  => $type,
            'library_class_id' => $library_class_id
        ];
        $url = $this->siteUrl.'/api/?s=School.Library.Add';
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $this->success('成功');
    }



    public function del(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常',url('index'));
        }
        $result = Db::name('library')->where('id',$id)->delete();
        if($result === false){
            $this->error('系统错误',url('index'));
        }
        $this->success('成功');
    }


    public function upload()
    {

        $file = isset($_FILES['file']) ? $_FILES['file'] : '';
        if (!$file) {
            $this->error('请选择文件');
        }
        if ($file['size'] == 0) {
            $this->error('不能上传空文件');
        }

        $res = upload($file, 'file');
        if ($res['code'] != 0) {
            $this->error($res['msg']);
        }
        $url = $res['url'];

        $rs = [
            'url' => get_upload_path($url),
            'path' => $url,
            'name' => $file['name']
        ];
        $this->success('', '', $rs);
    }
}