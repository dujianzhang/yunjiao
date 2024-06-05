<?php

namespace  app\teacher\controller;



use cmf\controller\TeacherBaseController;
use think\Db;
use think\Exception;

class HomeScoreController extends  TeacherBaseController{

    public $class_id;

    public function initialize()
    {
        parent::initialize();
        $this->assign('cur','HomeSchool');
        $class_id = $this->request->param('class_id',0,'intval');
        $this->class_id = $class_id;
        $this->assign('class_id',$class_id);

    }


    public function index(){
        $param = $this->request->param();
        $teacher_id = $param['teacher_id'] ?? 0;
        $keyword = $param['keyword'] ?? 0;
        $where = [];
        if($teacher_id){
            $where[] = ['A.uid','=',$teacher_id];
        }
        if($keyword){
            $where[] =  ['A.title' , 'like' , "%{$keyword}%"];
        }
        $teacher = Db::name('class_teacher')->where('class_id',$this->class_id)->select()->toArray();
        $this->assign("teacher", $teacher);
        $teacher = array_column($teacher,null,'uid');
        $list = Db::name('class_score_info')
            ->field('A.*')
            ->alias('A')->join('class B','A.class_id = B.id')
            ->where($where)->where('A.class_id',$this->class_id)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $v['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
            $v['teacher_name'] = $teacher[$v['uid']]['name'];
            $list[$k] = $v;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function add(){
        $key = 'getcourseclass';
        $course_class = getcaches($key);
        if(!$course_class){
            $course_class =  DB::name('course_class')
                ->order("list_order asc")
                ->select();
            setcaches($key,$course_class);
        }
        $this->assign('course_class',$course_class);
        $student = DB::name('student')->where(['class_id'=>$this->class_id])->order("id desc")->select();
        $this->assign('student',$student);
        return $this->fetch();
    }

    public function addpost(){
        $param = $this->request->param();
        $param['content'] = json_encode($param['content']);
        $param['course_class_id'] = json_encode($param['course_class_id']);
        $userinfo = session('teacher');
        $param = array_merge($param,['uid' => $userinfo['id'],'token' => $userinfo['token'],]);
        $url = $this->siteUrl.'/api/?s=School.Score.Add';
        $data = json_decode(curl_post($url,$param)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $this->success('成功');
    }


    public function detail(){
        $param = $this->request->param();
        $score_id = $param['id'] ?? 0;
        $class_id = $param['class_id'] ?? 0;
        if(!$score_id || !$class_id){
            $this->error('网络异常');
        }
        $url = $this->siteUrl.'/api/?s=School.Score.DetailAll';
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'score_id' => $score_id,
        ];
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $info = $data['info'];
        $subjects = $info[0]['only'] ? array_column($info[0]['only'],'name') : [];
        $this->assign('subjects',$subjects);
        $this->assign('list',$info);
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


    public function import(){
        $key = 'getcourseclass';
        $course_class = getcaches($key);
        if(!$course_class){
            $course_class =  DB::name('course_class')
                ->order("list_order asc")
                ->select();
            setcaches($key,$course_class);
        }
        $this->assign('course_class',$course_class);
        return $this->fetch();
    }


    public function importPost(){
        $data      = $this->request->param();
        $class_id = $data['class_id'] ??0;
        $title = $data['title'] ?? 0;
        if(!$class_id)$this->error('参数异常');
        if(!$title)$this->error('请填写成绩标题');
        $file = isset($_FILES['file']) ? $_FILES['file'] : '';
        if (!$file) {
            $this->error('请选择上传文件');
        }

        if ($file['size'] == 0) {
            $this->error('不能上传空文件');
        }


        $res = upload_tp_local( 'file');
        if ($res['code'] != 0) {
            $this->error($res['msg']);
        }

        $fileinfo=$res['data'];

        $url=$fileinfo['filepath'];

        $res = excel_import($url,1);

        if (!$res[1]){
            $this->error('无数据');
        }

        $filename = CMF_ROOT.'public/upload/'.$url;
        @unlink($filename);

        try{

            $student = DB::name('student')->where(['class_id'=>$this->class_id])->order("id desc")->select()->toArray();
            if(!$student){
                throw new Exception('班级暂无学生');
            }
            $school_id = $student[0]['school_id'];

            $url = $this->siteUrl.'/api/?s=School.ClassGrade.GetCourseClass';
            $School_CourseClass = json_decode(curl_post($url,['school_id'=>$school_id])['res'],true)['data'];
            if($School_CourseClass['code']){
                throw new Exception($data['msg']);
            }

            $School_CourseClass = array_column($School_CourseClass['info'],null,'name');

            $course_class = $res[0];  //获取上传科目
            unset($res[0]);
            unset($course_class[count($course_class)-1]); //去除排名标题
            unset($course_class[0]);  //去除学生姓名标题
            $course_class_id = [];
            foreach ($course_class as $k=>$v){
                if(isset($School_CourseClass[$v])){
                    array_push($course_class_id,$School_CourseClass[$v]['id']);
                }else{
                    throw new Exception('科目有误');
                }
            }

            $student = Db::name('student')->where(['class_id'=>$class_id])->select()->toArray();
            $student = array_column($student,null,'name');
            $content = [];
            foreach ($res as $k=>$v){
                $all_ranking = $v[count($v)-1];
                $all_score = 0;
                $student_name = $v[0];
                if(!$student_name){
                    throw new Exception('请填写学生姓名');
                }

                if(isset($student[$v[0]])){
                    $student_id = $student[$v[0]]['id'];
                }else{

                    throw new Exception($student_name.':不在本班级内');
                }

                if(isset($content[$student_id])){
                    throw new Exception($student_name.':成绩重复填写');
                }

                unset($v[count($v)-1]);
                unset($v[0]);
                $only = [];
                $v = array_values($v);
                foreach ($v as $index=>$item){
                    $item = explode(',',$item);
                    if(!$item){
                        throw new Exception($student_name.':格式有误，请使用英文逗号分割 成绩与排名');
                    }
                    $score = mb_substr($item[0],3,mb_strlen($item[0])-1) ?? 0;
                    $score = sprintf("%.2f", $score);

                    $ranking = mb_substr($item[1],3,mb_strlen($item[0])-1) ?? 0;

                    $all_score += $score;


                    $only[$course_class_id[$index]] = compact('score','ranking');
                }

                $all = [
                    'score' => $all_score,
                    'ranking' => $all_ranking
                ];
                $content[$student_id] = compact('all','only');

            }
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }


        $content = json_encode($content);
        $course_class_id = json_encode($course_class_id);
        $param = [
            'title' => $title,
            'class_id' => $class_id,
            'content' => $content,
            'course_class_id' => $course_class_id
        ];
        $userinfo = session('teacher');
        $param = array_merge($param,['uid' => $userinfo['id'],'token' => $userinfo['token'],]);
        $url = $this->siteUrl.'/api/?s=School.Score.Add';
        $data = json_decode(curl_post($url,$param)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $this->success('成功');



        return $content;

    }

}