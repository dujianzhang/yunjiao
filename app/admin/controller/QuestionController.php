<?php

/* 题目管理 */
namespace app\admin\controller;

use app\models\QuestionclassModel;
use cmf\controller\AdminBaseController;
use think\Db;

class QuestionController extends AdminBaseController
{
    protected function handelClass($data,$pid=0){
        $list=[];
        foreach ($data as $k=>$v){
            if($v['pid']==$pid){
                unset($data[$k]);
                $rs=$this->handelClass($data,$v['id']);
                $v['list']=$rs;
                $list[]=$v;
            }
        }
        return $list;
    }
    protected function getClass(){
        $list = Db::name('question_class')
            ->order("pid asc,list_order asc")
            ->select()->toArray();

        $list=$this->handelClass($list,0);
        $list2=[];
        foreach($list as $k=>$v){
            foreach ($v['list'] as $k2=>$v2){
                $name=$v['name'].' - '.$v2['name'];
                $v2['name']=$name;
                $list2[$v2['id']]=$v2;
            }
        }
        return $list2;
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
    public function index()
    {
        $data = $this->request->param();
        $map=[];


        $classid=isset($data['classid']) ? $data['classid']: '';
        if($classid!=''){
            $map[]=['classid','=',$classid];
        }


        $typeid=isset($data['typeid']) ? $data['typeid']: '';
        if($typeid!=''){
            $map[]=['type','=',$typeid];
        }

        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['title','like','%'.$keyword.'%'];
        }

        $list = Db::name('question')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k){
            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);

        $this->assign('list', $list);

        $class=$this->getClass();

        $this->assign('class', $class);

        $type=$this->getTypes();
        $this->assign('type', $type);

        return $this->fetch();
    }


    public function add()
    {
        $class=$this->getClass();

        $this->assign('class', $class);

        $type=$this->getTypes();
        $this->assign('type', $type);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {

            $insert=self::checkdata();

            $id = DB::name('question')->insertGetId($insert);
            if(!$id){
                $this->error("添加失败！");
            }
            $this->upClass();
            $this->success("添加成功！");
        }
    }

    public function checkdata(){
        $data      = $this->request->param();

        $id=$data['id'] ?? 0;
        $classid=$data['classid'];
        $type=$data['type'];
        $title=checkNull($data['title']);

        $score=$data['score'];
        $answer=[
            'nums'=>'2',
            'rs'=>'',
            'ans'=>[],
            'img'=>'',
            't_img'=>'',
            't_audio'=>'',
            't_video'=>'',
        ];

        if($title==''){
            $this->error('请填写题目文字');
        }

        $t_img= $data['t_img'] ?? '';
        $t_audio= $data['t_audio'] ?? '';
        $t_video= $data['t_video'] ?? '';
        $t_video_img= $data['t_video_img'] ?? '';
        if($t_video!=''){
            if($t_video_img==''){
                $this->error('请上传视频封面');
            }
        }else{
            $t_video_img='';
        }

        $answer['t_img']=$t_img;
        $answer['t_audio']=$t_audio;
        $answer['t_video_img']=$t_video_img;
        $answer['t_video']=$t_video;

        if($type==0){
            if(!isset($data['ans_0'])){
                $this->error('请选择正确答案');
            }
            $answer['rs']=$data['ans_0'];

            if($score<=0){
                $this->error('请填写正确分数');
            }
            $score2=0;
        }
        if($type==1 || $type==2 || $type==5){
            if(!isset($data['item_select'])){
                $this->error('请填写所有选项');
            }
            $item_select=$data['item_select'];
            foreach ($item_select as $k=>$v){
                $v=checkNull($v);
                if($v==''){
                    $this->error('请填写所有选项');
                }
                $item_select[$k]=$v;
            }
            $answer['nums']=(string)count($item_select);
            $answer['ans']=$item_select;
        }

        if($type==1){
            if(!isset($data['ans_1'])){
                $this->error('请选择正确答案');
            }
            $answer['rs']=$data['ans_1'];

            if($score<=0){
                $this->error('请填写正确分数');
            }
            $score2=0;
        }

        if($type==2){
            if(!isset($data['ans_2'])){
                $this->error('请选择正确答案');
            }
            $answer['rs']=implode(',',$data['ans_2']);

            if($score<=0){
                $this->error('请填写正确分数');
            }
            $score2=0;
        }

        if($type==3){
            if(!isset($data['ans_3']) && !isset($data['img_a'])){
                $this->error('请填写正确答案');
            }
            $rs=checkNull($data['ans_3']);
            $img_a=checkNull($data['img_a']);
            if($rs=='' && $img_a==''){
                $this->error('请填写正确答案');
            }
            $answer['rs']=$rs;
            $answer['img']=$img_a;
            if($score<=0){
                $this->error('请填写正确分数');
            }
            $score2=0;

        }

        if($type==4){
            if(!isset($data['ans_4'])){
                $this->error('请填写正确答案');
            }
            $ans_4=$data['ans_4'];
            $ans=[];
            foreach ($ans_4 as $k=>$v){

                $v=checkNull($v);
                if($v==''){
                    $this->error('请填写正确答案');
                }

                $v_a=explode("\r\n",$v);
                foreach ($v_a as $k2=>$v2){
                    $v2=checkNull($v2);
                    if($v2==''){
                        unset($v_a[$k2]);
                    }
                }
                if(!$v_a){
                    $this->error('请填写正确答案');
                }
                $ans[]=array_values($v_a);
            }
            $nums=(string)count($ans);
            $answer['nums']=$nums;
            $answer['ans']=$ans;

            $score2=$data['score2'];
            if($score2<=0){
                $this->error('请填写正确的每空分数');
            }
            $score=$score2 * $nums;
        }

        if($type==5){
            if(!isset($data['ans_5'])){
                $this->error('请选择正确答案');
            }
            $answer['rs']=implode(',',$data['ans_5']);

            $score=$data['score'];
            if($score<=0){
                $this->error('请填写正确分数');
            }

            $score3=$data['score3'];
            if($score3<=0){
                $this->error('请填写正确漏选分数');
            }
            $score2=$score3;
        }

        $parsing=$data['parsing'] ?? '';

        $cinfo=QuestionclassModel::getInfo($classid);
        $path=[];
        $pid=$cinfo['pid'];
        if($pid>0){
            $path[]=$pid;
        }

        $path[]=$classid;

        $path_s=handelSetToStr($path);

        $nowtime=time();
        $insert=[
            'classid'=>$classid,
            'type'=>$type,
            'title'=>$title,
            'answer'=>json_encode($answer),
            'score'=>$score,
            'score2'=>$score2,
            'parsing'=>$parsing,
            'classid_path'=>$path_s,
        ];
        if($id){
            $insert['id']=$id;
            $insert['uptime']=$nowtime;
        }else{
            $insert['addtime']=$nowtime;
        }

        return $insert;
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');

        $data=Db::name('question')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $answer=json_decode($data['answer'],true);
        $type=$data['type'];
        if($type==4){
            $rs=$answer['ans'];
            foreach ($rs as $k=>&$v){
                $v=implode("\r\n",$v);
            }
            $answer['ans']=$rs;
        }

        if($type==3){
            $rs=$answer['ans'];
            $img='';
            foreach ($rs as $k=>&$v){
                $img=$v;
            }
            $answer['ans']=$img;
        }

        $this->assign('data', $data);
        $this->assign('answer', $answer);

        $select_list=['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];
        $this->assign('select_list', $select_list);

        $class=$this->getClass();

        $this->assign('class', $class);

        $type=$this->getTypes();
        $this->assign('type', $type);

        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {

            $insert=self::checkdata();

            $rs = DB::name('question')->update($insert);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->upClass();
            $this->success("保存成功！");
        }
    }

    protected function upClass(){
        $list=Db::name('question')
            ->field('classid,count(*) as nums')
            ->group('classid')
            ->select()
            ->toArray();
        foreach ($list as $k=>$v){
            Db::name('question_class')->where('id',$v['classid'])->update(['nums'=>$v['nums']]);
        }

        $classids=[];
        if($list){
            $classids=array_column($list,'classid');
        }
        Db::name('question_class')->where('id','not in', $classids)->update(['nums' => 0]);

        $list2=Db::name('question')
            ->field('classid,count(*) as nums')
            ->where([ ['type','<>',3] ])
            ->group('classid')
            ->select()
            ->toArray();
        foreach ($list2 as $k=>$v){
            Db::name('question_class')->where('id',$v['classid'])->update(['nums_obj'=>$v['nums']]);
        }

        $classids2=[];
        if($list2){
            $classids2=array_column($list2,'classid');
        }
        Db::name('question_class')->where('id','not in', $classids2)->update(['nums_obj'=>0]);

        QuestionclassModel::resetcache();

    }

    public function listOrder()
    {
        $model = DB::name('question');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('question')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->upClass();
        $this->success("删除成功！");
    }

    public function getQuestion(){
        $data      = $this->request->param();
        $classid=isset($data['classid']) ? checkNull($data['classid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '';
        $keyword=isset($data['keyword']) ? checkNull($data['keyword']): '';
        $page=isset($data['page']) ? checkNull($data['page']): '';

        $map=[];
        if($classid!=0){
            $map[]=['classid','=',$classid];
        }

        if($type!=''){
            $map[]=['type','=',$type];
        }

        if($keyword!=''){
            $map[]=['title','like','%'.$keyword.'%'];
        }

        if($page<1){
            $page=1;
        }
        $nums=7;
        $start=($page-1) * $nums;

        $type_list=['判断题','单选题','定项多选题','简答题','填空题','不定项多选题'];

        $list=Db::name('question')
            ->field('id,type,title,answer,score,score2,parsing')
            ->where($map)
            ->order('id desc')
            ->limit($start,$nums)
            ->select()
            ->toArray();

        foreach ($list as $k=>$v){
            $answer=json_decode($v['answer'],true);
            if(isset($answer['img'])){
                $answer['img']=get_upload_path($answer['img']);
            }
            if($v['type']==3){
                foreach ($answer['ans'] as $k2=>$v2){
                    $answer['ans'][$k2]=get_upload_path($v2);
                }
            }

            $v['answer']=$answer;
            $v['type_name']=$type_list[$v['type']];

            $list[$k]=$v;
        }
        $total=Db::name('question')
            ->where($map)
            ->count();
        $info=[
            'list'=>$list,
            'total'=>$total,
            'nums'=>$nums,
        ];

        $this->success('','',$info);
    }

    public function import()
    {

        $class=$this->getClass();
        $this->assign('class', $class);

        return $this->fetch();
    }

    public function importPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $classid=$data['classid'];

            $file = isset($_FILES['file']) ? $_FILES['file'] : '';
            if (!$file) {
                $this->error('请选择上传文件');
            }

            if ($file['size'] == 0) {
                $this->error('不能上传空文件');
            }

            //$res = upload($file, 'file');
            $res = upload_tp_local( 'file');
            if ($res['code'] != 0) {
                $this->error($res['msg']);
            }

            $fileinfo=$res['data'];

            $url=$fileinfo['filepath'];

            $res = excel_import($url);

            if (!$res){
                $this->error('无数据');
            }

            $filename = CMF_ROOT.'public/upload/'.$url;
            @unlink($filename);
            //var_dump($res);
            //exit;
            $types2=[];
            $types=$this->getTypes();
            foreach ($types as $k=>$v){
                $types2[$v]=$k;
            }
            $nowtime=time();
            $ans_s=[
                "A"=>'0',
                "B"=>'1',
                "C"=>'2',
                "D"=>'3',
                "E"=>'4',
                "F"=>'5',
                "G"=>'6',
                "H"=>'7',
                "I"=>'8',
                "J"=>'9',
                "K"=>'10',
                "L"=>'11',
                "M"=>'12',
                "N"=>'13',
            ];

            $insertall=[];
            foreach ($res as $k=>$v){

                $type=$types2[$v[0]] ?? '';
                $type=checkNull($type);
                if($type===''){
                    continue;
                }
                $title=$v[1] ?? '';
                $title=checkNull($title);
                if($title==''){
                    continue;
                }

                $score=$v[8] ?? 0;
                $score2=0;

                $answer=[
                    'nums'=>'2',
                    'rs'=>'',
                    'ans'=>[],
                    'img'=>'',
                    't_img'=>'',
                    't_audio'=>'',
                    't_video'=>'',
                    't_video_img'=>'',
                ];

                $t_img= $v[2] ?? '';
                $t_img=checkNull($t_img);
                $t_audio= $v[3] ?? '';
                $t_audio=checkNull($t_audio);
                $t_video_img= $v[4] ?? '';
                $t_video_img=checkNull($t_video_img);
                $t_video= $v[5] ?? '';
                $t_video=checkNull($t_video);

                if($t_video=='' || $t_video_img==''){
                    $t_video_img='';
                    $t_video='';
                }

                $answer['t_img']=$t_img;
                $answer['t_audio']=$t_audio;
                $answer['t_video']=$t_video;
                $answer['t_video_img']=$t_video_img;
                $rs=$v[7] ?? '';
                $rs=checkNull($rs);

                if($type!=3 && $rs==''){
                    continue;
                }


                if($type==0){
                    if($rs==''){
                        continue;
                    }
                    if($rs=='对'){
                        $rs='1';
                    }else{
                        $rs='0';
                    }
                    $answer['rs']=$rs;

                    if($score<=0){
                        continue;
                    }
                }
                if($type==1 || $type==2 || $type==5){
                    $item_select=$v[6] ?? '';
                    $item_select=checkNull($item_select);
                    $item_select=explode("\n",$item_select);
                    foreach ($item_select as $k2=>$v2){
                        $v2=checkNull($v2);
                        if($v2==''){
                            unset($item_select[$k2]);
                        }
                    }
                    $item_select=array_values($item_select);
                    $answer['nums']=(string)count($item_select);
                    $answer['ans']=$item_select;
                }

                if($type==1){

                    $rs2=$ans_s[$rs] ?? '';
                    if($rs2===''){
                        continue;
                    }
                    $answer['rs']=$rs2;

                    if($score<=0){
                        continue;
                    }
                }

                if($type==2 || $type==5){
                    $rs_a=preg_split('/、|,|，/',$rs);
                    foreach ($rs_a as $k2=>$v2){
                        $rs2=$ans_s[$v2] ?? '';
                        if($rs2===''){
                            unset($rs_a[$k2]);
                        }
                        $rs_a[$k2]=$rs2;
                    }
                    $rs_a=array_values($rs_a);
                    $answer['rs']=implode(',',$rs_a);

                    if($score<=0){
                        continue;
                    }
                    $score2=0;
                    if($type==5){
                        $score2=$v[9] ?? 0;
                    }
                }

                if($type==3){

                    $img_a=$v[11] ?? '';
                    $img_a=checkNull($img_a);
                    if($rs=='' && $img_a==''){
                        continue;
                    }
                    $answer['rs']=$rs;
                    $answer['img']=$img_a;
                    if($score<=0){
                        continue;
                    }
                }

                if($type==4){

                    $ans_4=explode("\n",$rs);
                    $ans=[];
                    foreach ($ans_4 as $k2=>$v2){
                        $v2=checkNull($v2);
                        if($v2==''){
                            continue;
                        }

                        $v_a=preg_split('/、|,|，/',$v2);
                        foreach ($v_a as $k3=>$v3){
                            $v3=checkNull($v3);
                            if($v3==''){
                                unset($v_a[$k3]);
                            }
                        }
                        $ans[]=array_values($v_a);
                    }
                    $nums=(string)count($ans);
                    $answer['nums']=$nums;
                    $answer['ans']=$ans;

                    if($score<=0){
                        continue;
                    }
                    $score=$score * $nums;
                }


                $parsing=$v[10] ?? '';


                $cinfo=QuestionclassModel::getInfo($classid);
                $path=[];
                $pid=$cinfo['pid'];
                if($pid>0){
                    $path[]=$pid;
                }

                $path[]=$classid;

                $path_s=handelSetToStr($path);

                $insert=[
                    'classid'=>$classid,
                    'type'=>$type,
                    'title'=>$title,
                    'answer'=>json_encode($answer),
                    'score'=>$score,
                    'score2'=>$score2,
                    'parsing'=>$parsing,
                    'uptime'=>$nowtime,
                    'classid_path'=>$path_s,
                ];

                $insertall[]=$insert;
            }

            if(!$insertall){
                $this->error("数据错误！");
            }

            $rs = DB::name('question')->insertAll($insertall);

            if($rs === false){
                $this->error("导入失败！");
            }
            $this->upClass();
            $this->success("导入成功！");
        }
    }
}
