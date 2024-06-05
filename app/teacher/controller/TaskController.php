<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 作业
 */
class TaskController extends TeacherBaseController {
    
	public function index() {
        $cur='task';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');
        $this->uid=$uid;

        $data = $this->request->param();
        $map=[];
        $where='';

        $map[]=['status','>=',0];
        $nowtime=time();

        $status=isset($data['status']) ? $data['status']: '0';
        if($status!=''){
            if($status==0){
                $map[]=['endtime','>',$nowtime];
            }

            if($status==1){
                $map[]=['endtime','<=',$nowtime];
                $where='review!=total';
            }

            if($status==2){
                $map[]=['endtime','<=',$nowtime];
                $where='review=total';
            }
        }

        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['name','like','%'.$keyword.'%'];
        }


        $list = Db::name("task")
            ->where($map)
            ->where($where)
            ->where(function ($query) use($uid) {
                $query->whereor('uid','=', $uid)
                    ->whereor('actionuid', 'like', "%[{$uid}]%");
            })
            ->order("id desc")
            ->paginate(20);


        $list->each(function($v,$k){

            $v['end_time']=date('Y-m-d H:i:s',$v['endtime']);

            $courseinfo=Db::name("course")->field('name')->where('id',$v['courseid'])->find();
            $coursename='';
            if($courseinfo){
                $coursename=$courseinfo['name'];
            }
            $v['coursename']=$coursename;

            return $v;
        });

        $list->appends($data);
        // 获取分页显示
        $page = $list->render();

        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('status', $status);

        return $this->fetch();
    }

    public function del(){

        $uid=session('teacher.id');

        $data = $this->request->param();

        $id=isset($data['id']) ? $data['id']: '0';
        if($id<1){
            $this->error('信息错误');
        }

        $res=Db::name('task')->where('id',$id)
            ->where(function ($query) use($uid) {
                $query->whereor('uid','=', $uid)
                    ->whereor('actionuid', 'like', "%[{$uid}]%");
            })
            ->update(['status'=>-1]);

        $this->success('操作成功');
    }

    /* 科目分类 */
    protected function getClass(){
        $list = Db::name('course_class')
            ->order("list_order asc")
            ->column('*','id');
        return $list;
    }

    /* 课程 */
    protected function getCourse($uid,$classid){
        $nowtime=time();
        $list = Db::name('course')
            ->where([['uid','=',$uid],['classid','=',$classid]])
            ->where(function ($query) use($nowtime) {
                $query->whereor('sort', '=', '1')
                    ->whereor(function ($query2) use($nowtime) {
                        $query2->where('sort', '>=', '2')
                            ->where('endtime', '>', $nowtime);
                    });
            })
            ->order("list_order asc")
            ->column('id,sort,name','id');
        foreach ($list as $k=>$v){

            if($v['sort']==1){
                $lesson=Db::name('course_lesson')
                    ->where([['courseid','=',$v['id']],['type','>=','4'],['endtime','>',$nowtime]])
                    ->order("list_order asc")
                    ->column('id,name','id');
                if(!$lesson){
                    unset($list[$k]);
                    continue;
                }
                $v['lessons']=$lesson;

                $list[$k]=$v;
            }

        }

        return $list;
    }

    public function uploadImg(){

        $file=isset($_FILES['file'])?$_FILES['file']:'';
        if(!$file){
            $this->error('请选择图片');
        }
        if($file['size']==0){
            $this->error('不能上传空文件');
        }

        $res=upload($file,'image');
        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $url=$res['url'];

        $rs=[
            'url'=>get_upload_path($url),
        ];
        $this->success('','',$rs);

    }

    public function getTeachers(){

        $uid=session('teacher.id');
        $list=Db::name('users')
            ->field('id,user_nickname')
            ->where([['id','<>',$uid],['type','=','1'],['user_status','<>','0']])
            ->select();

        $this->success('','',$list);
    }

    public function add(){
        $cur='task';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');

        $class=$this->getClass();
        //$courses=$this->getCourse($uid);

        $this->assign('class',$class);
        //$this->assign('courses',$courses);
        //$this->assign('coursesj',json_encode($courses));

        return $this->fetch();
    }

    public function addPost(){

        $uid=session('teacher.id');

        $data = $this->request->param();

        $name=isset($data['name']) ? $data['name']: '';
        $type_t=isset($data['type']) ? $data['type']: '0';
        $classid=isset($data['classid']) ? $data['classid']: '0';
        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $lessonid=isset($data['lessonid']) ? $data['lessonid']: '0';
        $actionuid=isset($data['actionuid']) ? $data['actionuid']: '';
        $endtime=isset($data['endtime']) ? $data['endtime']: '0';
        $content=isset($data['content']) ? $data['content']: '';
        $answer=isset($data['answer']) ? $data['answer']: '';

        $nowtime=time();

        if($name==''){
            $this->error('请填写作业标题');
        }
        if($classid==0){
            $this->error('请选择科目');
        }

        $classinfo=Db::name('course_class')->field('id')->where('id',$classid)->find();
        if(!$classinfo){
            $this->error('科目信息错误');
        }

        if($courseid==0){
            $this->error('请选择课程');
        }

        $courseinfo=Db::name('course')->field('id,uid,sort,endtime')->where([['id','=',$courseid],['uid','=',$uid],['sort','<>',0]])->find();
        if(!$courseinfo){
            $this->error('课程信息错误');
        }

        if($courseinfo['sort']!=1 && $courseinfo['endtime']<=$nowtime){
            $this->error('课程信息错误');
        }

        if($courseinfo['sort']==1){
            if($lessonid==0){
                $this->error('请选择课时');
            }
            $lessoninfo=Db::name('course_lesson')->field('id,type,endtime')->where([['id','=',$lessonid],['courseid','=',$courseid],['type','>=','4'],['endtime','>=',$nowtime]])->find();
            if(!$lessoninfo){
                $this->error('课时信息错误');
            }

        }
        $actionuid_s='';
        if($actionuid=='[]'){
            $actionuid='';
        }
        if($actionuid!=''){
            $actionuid_a=json_decode($actionuid,true);
            if(!$actionuid_a){
                $this->error('批阅老师信息错误');
            }
            $actionuid_a2=[];
            foreach ($actionuid_a as $k=>$v){
                $v=(int)$v;
                $actionuid_a2[]='['.$v.']';
            }
            $actionuid_a2=array_filter($actionuid_a2);
            $actionuid_s=implode(',',$actionuid_a2);
        }

        if($endtime==0){
            $this->error('请填写截止时间');
        }
        $endtime=strtotime($endtime);

        if($courseinfo['sort']!=1 && $endtime<=$courseinfo['endtime']){
            $this->error('截止时间不能早于下课时间');
        }

        if($courseinfo['sort']==1 && $endtime<=$lessoninfo['endtime']){
            $this->error('截止时间不能早于下课时间');
        }

        if($content==''){
            $this->error('请先上传作业');
        }

        $content_a=json_decode($content,true);
        if(!$content_a){
            $this->error('作业信息错误');
        }

        if($answer==''){
            $this->error('请先添加答题卡');
        }
        $answer_a=json_decode($answer,true);
        if(!$answer_a){
            $this->error('答题卡信息错误');
        }
        foreach ($answer_a as $k=>$v){
            if(!isset($v['type']) || !isset($v['nums']) || !isset($v['rs']) || !isset($v['img']) || !isset($v['score'])){
                $this->error('答题卡信息错误');
            }

            $type=$v['type'];
            if($type==3){
                if($v['rs']=='' && $v['img']==''){
                    $this->error('所有习题请设置正确答案');
                }
            }else if($type==4){
                if(!$v['rs']){
                    $this->error('所有习题请设置正确答案');
                }
                for($i=0;$i<$v['nums'];$i++){
                    if(!isset($v['rs'][$i])){
                        $this->error('所有习题请设置正确答案');
                    }
                    $isok=0;
                    foreach ($v['rs'][$i] as $k2=>$v2){
                        if($v2!=''){
                            $isok=1;
                            break;
                        }
                    }
                    if(!$isok){
                        $this->error('所有习题请设置正确答案');
                    }
                }
            }else{
                if($v['rs']==''){
                    $this->error('所有习题请设置正确答案');
                }
            }
        }

        $data_insert=[
            'uid'=>$uid,
            'type'=>$type_t,
            'name'=>$name,
            'classid'=>$classid,
            'courseid'=>$courseid,
            'lessonid'=>$lessonid,
            'actionuid'=>$actionuid_s,
            'endtime'=>$endtime,
            'content'=>$content,
            'answer'=>$answer,
            'addtime'=>time(),
        ];

        $res=Db::name('task')->insert($data_insert);
        if(!$res){
            $this->error('布置作业失败，请重试');
        }

        $this->success('布置作业成功');
    }

    public function getCourseApi(){
        $data = $this->request->param();
        $classid=$data['classid'];

        $uid=session('teacher.id');

        $list=self::getCourse($uid,$classid);


        $this->success('','',$list);
    }

}


