<?php

/* 考试管理 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use cmf\lib\Storage;
use think\Db;

class TestsController extends AdminBaseController
{
    protected function getStatus($key=''){
        $status=[
            '-1'=>'已删除',
            '0'=>'待发布',
            '1'=>'进行中',
        ];
        if($key==''){
            return $status;
        }

        return  $status[$key] ?? '';
    }
    protected function getCat(){
        $rs=[];
        $list = Db::name('tests_cat')
            ->field('id,title')
            ->order("list_order asc")
            ->select()->toArray();
        foreach ($list as $k=>$v){
            $rs[$v['id']]=$v;
        }
        return $rs;
    }
    protected  function getGrade(){
        $rs=[];
        $grades=getGrade();

        foreach ($grades as $k=>$v){
            $rs[$v['id']]=$v;
        }

        $list2=[];
        foreach($rs as $k=>$v){
            if($v['pid']!=0){
                $name=$rs[$v['pid']]['name'].' - '.$v['name'];
                $v['name']=$name;

                $list2[$k]=$v;
            }
        }
        return $list2;
    }
    public function index()
    {
        $cats=$this->getCat();
        $grades=$this->getGrade();
        $status=$this->getStatus();

        $data = $this->request->param();
        $map=[];
        $map[]=['status','>=',0];

        $catid= $data['catid'] ?? '';
        if($catid!=''){
            $map[]=['catid','=',$catid];
        }
        $gradeid= $data['gradeid'] ?? '';
        if($gradeid!=''){
            $map[]=['gradeid','=',$gradeid];
        }
        $keyword= $data['keyword'] ?? '';
        if($keyword!=''){
            $map[]=['title','like','%'.$keyword.'%'];
        }

        $list = Db::name('tests')
            ->field('id,title,thumb,gradeid,catid,score,nums,ans_nums,addtime,status')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k)use($cats,$grades,$status){

            $v['thumb']=get_upload_path($v['thumb']);
            $v['gradename']=$grades[$v['gradeid']]['name'] ?? '';
            $v['catname']=$cats[$v['catid']]['title'] ?? '';
            $v['status_t']=$status[$v['status']] ?? '';

            return $v;
        });
        $list->appends($data);
        $page = $list->render();
        $this->assign("page", $page);

        $this->assign('list', $list);
        $this->assign('cats', $cats);
        $this->assign('grades', $grades);

        return $this->fetch();
    }

    protected function getUser($keyword=''){
        $map=[];
        $map[]=['type','=',1];
        if($keyword!=''){
            $map[]=['user_nickname','like',"%{$keyword}%"];
        }

        $list=Db::name('users')->field('id,user_nickname')->where($map)->select()->toArray();

        return $list;
    }

    public function getUserList(){
        $data = $this->request->param();

        $keyword=isset($data['keyword']) ? $data['keyword']: '';

        $list=$this->getUser($keyword);

        $this->success('','',$list);
    }


    protected function getCourses($gradeid,$keyword=''){
        $where=[];
        $where[]=['status','>','0'];
        $where[]=['gradeid','=',$gradeid];
        if($keyword!=''){
            $where[]=['name','like',"%{$keyword}%"];
        }

        $list=Db::name('course')
            ->field('id,name')
            ->where($where)
            ->order('id desc')
            ->select()
            ->toArray();

        return $list;
    }
    public function getCourse(){
        $data      = $this->request->param();
        $gradeid=$data['gradeid'] ?? 0;
        $keyword=$data['keyword'] ?? '';

        $list=$this->getCourses($gradeid,$keyword);

        $this->success('','',$list);
    }

    public function add()
    {
        $cats=$this->getCat();
        $grades=$this->getGrade();

        $this->assign("grades", $grades);
        $this->assign("cats", $cats);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $title=$data['title'] ?? '';
            if($title == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $com=$data['com'] ?? [];
            $com_s=handelSetToStr($com);
            $data['course_com']=$com_s;
            unset($data['com']);

            $recom=$data['recom'] ?? [];
            $recom_s=handelSetToStr($recom);
            $data['course_recom']=$recom_s;
            unset($data['recom']);

            $actionuid=$data['actionuid'] ?? [];
            $actionuid_s=handelSetToStr($actionuid);
            $data['actionuid']=$actionuid_s;

            $time_type=$data['time_type'] ?? 0;
            if($time_type==1){
                $starttime=$data['starttime'] ?? '';
                if($starttime==''){
                    $this->error('请选择开始时间');
                }
                $endtime=$data['endtime'] ?? '';
                if($endtime==''){
                    $this->error('请选择结束时间');
                }
                $starttime=strtotime($starttime);
                $endtime=strtotime($endtime);
                if($starttime>=$endtime){
                    $this->error('结束时间不能早于开始时间');
                }
                $data['starttime']=$starttime;
                $data['endtime']=$endtime;
            }else{
                $data['starttime']=0;
                $data['endtime']=0;
            }

            $length=$data['length'] ?? 0;
            if($length<0){
                $length=0;
            }
            $data['length']=$length;

            $test_nums_type=$data['test_nums_type'] ?? 0;
            $test_limit=$data['test_limit'] ?? 0;
            if($test_nums_type==1){
                $test_nums=$data['test_nums'] ?? 0;
                if($test_nums<1){
                    $this->error('参与考试次数不能小于1');
                }
                if($test_nums==1){
                    $test_limit=0;
                }

            }else{
                $test_nums=0;
                if($test_limit<1){
                    $test_limit=0;
                }
            }

            $data['test_nums']=$test_nums;
            $data['test_limit']=$test_limit;
            $data['content']='[]';
            unset($data['test_nums_type']);
            $data['addtime']=time();

            $id = DB::name('tests')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');

        $data=Db::name('tests')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $cats=$this->getCat();
        $grades=$this->getGrade();

        $this->assign("grades", $grades);
        $this->assign("cats", $cats);

        $test_nums_type=0;
        if($data['test_nums']!=0){
            $test_nums_type=1;
        }
        $data['test_nums_type']=$test_nums_type;

        if($data['time_type']==1){
            $data['starttime']=date('Y-m-d H:i:s',$data['starttime']);
            $data['endtime']=date('Y-m-d H:i:s',$data['endtime']);
        }else{
            $data['starttime']='';
            $data['endtime']='';
        }

        if($data['course_com']!=''){
            $course_com=handelSetToArr($data['course_com']);
            $com=Db::name('course')->field('id,name')->where([['id','in',$course_com]])->select()->toArray();
            $data['course_com']=$com;
        }else{
            $data['course_com']=[];
        }

        if($data['course_recom']!=''){
            $course_recom=handelSetToArr($data['course_recom']);
            $recom=Db::name('course')->field('id,name')->where([['id','in',$course_recom]])->select()->toArray();
            $data['course_recom']=$recom;
        }else{
            $data['course_recom']=[];
        }

        if($data['actionuid']!=''){
            $actionuid=handelSetToArr($data['actionuid']);
            $users=Db::name('users')->field('id,user_nickname')->where([['type','=','1'],['id','in',$actionuid]])->select()->toArray();
            $data['actionuid']=$users;
        }else{
            $data['actionuid']=[];
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $title=$data['title'] ?? '';
            if($title == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $com=$data['com'] ?? [];
            $com_s=handelSetToStr($com);
            $data['course_com']=$com_s;
            unset($data['com']);

            $recom=$data['recom'] ?? [];
            $recom_s=handelSetToStr($recom);
            $data['course_recom']=$recom_s;
            unset($data['recom']);

            $actionuid=$data['actionuid'] ?? [];
            $actionuid_s=handelSetToStr($actionuid);
            $data['actionuid']=$actionuid_s;

            $time_type=$data['time_type'] ?? 0;
            if($time_type==1){
                $starttime=$data['starttime'] ?? '';
                if($starttime==''){
                    $this->error('请选择开始时间');
                }
                $endtime=$data['endtime'] ?? '';
                if($endtime==''){
                    $this->error('请选择结束时间');
                }
                $starttime=strtotime($starttime);
                $endtime=strtotime($endtime);
                if($starttime>=$endtime){
                    $this->error('结束时间不能早于开始时间');
                }
                $data['starttime']=$starttime;
                $data['endtime']=$endtime;
            }else{
                $data['starttime']=0;
                $data['endtime']=0;
            }

            $length=$data['length'] ?? 0;
            if($length<0){
                $length=0;
            }
            $data['length']=$length;

            $test_nums_type=$data['test_nums_type'] ?? 0;
            $test_limit=$data['test_limit'] ?? 0;
            if($test_nums_type==1){
                $test_nums=$data['test_nums'] ?? 0;
                if($test_nums<1){
                    $this->error('参与考试次数不能小于1');
                }
                if($test_nums==1){
                    $test_limit=0;
                }

            }else{
                $test_nums=0;
                if($test_limit<1){
                    $test_limit=0;
                }
            }

            $data['test_nums']=$test_nums;
            $data['test_limit']=$test_limit;
            unset($data['test_nums_type']);

            $rs = DB::name('tests')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }

    public function listOrder()
    {
        $model = DB::name('tests');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('tests')->where('id',$id)->update(['status'=>-1]);
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }

    public function setRelease(){
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('tests')->where('id',$id)->update(['status'=>1,'uptime'=>time()]);
        if(!$rs){
            $this->error("操作失败！");
        }
        $this->success("操作成功！");
    }

    /* 类型 */
    protected function getTypes($k=''){
        $type=[
            '0'=>'判断题',
            '1'=>'单选题',
            '2'=>'定项多选题',
            '5'=>'不定项多选题',
            '4'=>'填空题',
            '3'=>'简答题',
        ];
        if($k===''){
            return $type;
        }
        return isset($type[$k])? $type[$k] : '' ;
    }

    public function topic(){
        $id   = $this->request->param('id', 0, 'intval');

        $data=Db::name('tests')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $this->assign('id', $data['id']);
        $this->assign('status', $data['status']);
        $this->assign('type', $this->getTypes());

        $host_url=self::getUrl();
        $this->assign('host_url', $host_url);

        return $this->fetch();
    }

    public function getUrl(){
        $storage = Storage::instance();
        $filepath= $storage->getImageUrl('', '');

        return rtrim($filepath,'/');
    }

    public function getTopic(){
        $id   = $this->request->param('id', 0, 'intval');

        $data=Db::name('tests')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        $content_a=json_decode($data['content'],true);
        $list=[];
        if($content_a) {
            foreach ($content_a as $k => $v) {
                $rs = $v['answer']['rs'];
                $ans = $v['answer']['ans'];
                if ($v['type'] == 4) {
                    $rs = $ans;
                    $ans = [];
                }
                $list[] = [
                    'type' => $v['type'],
                    'title' => $v['title'],
                    't_img' => $v['answer']['t_img'] ?? '',
                    't_audio' => $v['answer']['t_audio'] ?? '',
                    't_video' => $v['answer']['t_video'] ?? '',
                    't_video_img' => $v['answer']['t_video_img'] ?? '',
                    'img' => $v['answer']['img'] ?? '',
                    'rs' => $rs,
                    'ans' => $ans,
                    'score' => $v['score'],
                    'score2' => $v['score2'],
                    'parsing' => $v['parsing'],
                ];
            }
        }

        $this->success('','',$list);

    }
    public function topicPost(){
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            $id=$data['id'] ?? 0;
            if($id==0){
                $this->error("参数错误！");
            }

            $content=$data['content'] ?? '';
            if($content==''){
                $this->error("参数错误！");
            }
            $content_a=json_decode($content,true);
            if(!$content_a && $content_a!=[]){
                $this->error("参数错误！");
            }

            $info=DB::name('tests')->field('status')->where('id',$id)->find();
            if($info['status']!=0){
                $this->error("当前考试非待发布状态，无法修改题目！");
            }
            $score=0;
            $list=[];
            foreach ($content_a as $k=>$v){
                unset($v['id']);
                $score+=$v['score'];
                $type=$v['type'];
                $nums='1';
                if($type==0){
                    $nums='2';
                }
                if($type==1 || $type==2 ||  $type==5){
                    $nums=(string)count($v['ans']);
                }
                if($type==4){
                    foreach ($v['rs'] as $k2=>$v2){
                        if(!$v2){
                            $this->error("请填写正确答案");
                        }
                        foreach ($v2 as $k3=>$v3){
                            $v3=checkNull($v3);
                            if($v3==''){
                                $this->error("请填写正确答案");
                            }
                            $v2[$k3]=$v3;
                        }
                        $v['rs'][$k2]=$v2;
                    }
                    $nums=(string)count($v['rs']);
                    $v['ans']=$v['rs'];
                    $v['rs']='';
                }

                $ins=[
                    'type'=>$v['type'],
                    'title'=>$v['title'],
                    'answer'=>[
                        'nums'=>$nums,
                        'rs'=>$v['rs'],
                        'ans'=>$v['ans'],
                        'img'=>$v['img'],
                        't_img'=>$v['t_img'] ?? '',
                        't_audio'=>$v['t_audio'] ?? '',
                        't_video'=>$v['t_video'] ?? '',
                        't_video_img'=>$v['t_video_img'] ?? '',
                    ],
                    'parsing'=>$v['parsing'],
                    'score'=>$v['score'],
                    'score2'=>$v['score2'],
                ];
                $list[]=$ins;
            }

            $content_s=json_encode($list);

            $nums=count($list);

            $rs = DB::name('tests')->where('id',$id)->update(['content'=>$content_s,'nums'=>$nums,'score'=>$score]);

            if($rs === false){
                $this->error("保存失败！");
            }

            $this->success("保存成功！");
        }
    }

    /* 证书设置 */
    public function cert(){
        $id   = $this->request->param('id', 0, 'intval');

        $data=Db::name('tests')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $data['cert_url']=get_upload_path($data['cert']);
        $thumb='';
        if($data['cert_type']==2){
            $thumb=$data['cert'];
        }
        $data['cert_thumb']=$thumb;
        $cert_config=json_decode($data['cert_config'],true);

        if(mb_strlen($data['title'])>10){
            $data['title']=mb_substr($data['title'],0,4).'...'.mb_substr($data['title'],-4,4);
        }

        $this->assign('data', $data);
        $this->assign('cert_config', $cert_config);


        return $this->fetch();
    }
    public function certPost(){
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            $id=$data['id'] ?? 0;

            if($id==0){
                $this->error("参数错误！");
            }

            $up=[];
            $cert_switch=$data['cert_switch'] ?? 0;
            $up['cert_switch']=$cert_switch;
            if($cert_switch==0){
                $rs = DB::name('tests')->where('id',$id)->update($up);
                if($rs === false){
                    $this->error("保存失败！");
                }

                $this->success("保存成功！");
            }

            $cert_name=$data['cert_name'] ?? '';
            if($cert_name==''){
                $this->error("请输入证书名称");
            }
            $up['cert_name']=$cert_name;

            $cert='';
            $cert_type=$data['cert_type'] ?? 0;

            if($cert_type==1){
                $localimg=$data['localimg'] ?? '';
                if($localimg==''){
                    $this->error("请选择背景");
                }
                $cert=$localimg;
            }

            if($cert_type==2){
                $thumb=$data['thumb'] ?? '';
                if($thumb==''){
                    $this->error("请上传背景");
                }

                $cert=$thumb;
            }

            $up['cert_type']=$cert_type;
            $up['cert']=$cert;

            $start=$data['start'] ?? [];
            $end=$data['end'] ?? [];
            $des=$data['des'] ?? [];
            $score=$data['score'] ?? 0;

            if(!$start){
                $this->error("请填写的得分设置");
            }

            $cert_config=[];
            $order=[];
            foreach ($start as $k=>$v){
                $s=$start[$k] ?? '';
                $e=$end[$k] ?? '';
                $d=$des[$k] ?? '';
                if($s=='' || $e=='' || $d==''){
                    $this->error("请填写完整的得分设置");
                }
                if($s > $score || $e > $score){
                    $this->error("分数不能大于试卷总分");
                }
                $cert_config[]=[
                    'start'=>$s,
                    'end'=>$e,
                    'des'=>$d,
                ];
                $order[]=$s;
            }
            array_multisort($order,SORT_ASC,$cert_config);

            $com_start=0;
            $com_end=0;
            foreach ($cert_config as $k=>$v){
                if($k==0){
                    $com_start=$v['start'];
                    $com_end=$v['end'];
                    continue;
                }

                if($com_start==$v['start'] && $com_end==$v['end']){
                    $this->error("得分范围存在交集，请更正");
                }

                if($com_end > $v['start']){
                    $this->error("得分范围存在交集，请更正");
                }
            }

            $up['cert_config']=json_encode($cert_config);


            $cert_des=$data['cert_des'] ?? '';
            if($cert_des==''){
                $this->error("请输入课程介绍");
            }
            $up['cert_des']=$cert_des;

            $rs = DB::name('tests')->where('id',$id)->update($up);

            if($rs === false){
                $this->error("保存失败！");
            }

            $this->success("保存成功！");
        }
    }
}
