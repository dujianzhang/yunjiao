<?php

/* 活动 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ActivityController extends AdminBaseController
{
    /* 状态 */
    protected function getStatus($k=''){
        $status=[
            '-1'=>'下架',
            '0'=>'待上架',
            '1'=>'上架中',
        ];

        if($k===''){
            return $status;
        }
        return isset($status[$k])? $status[$k] : '' ;
    }
    /* 学级分类 */
    protected function getGrade(){
        $list = Db::name('course_grade')
            ->order("pid asc,list_order asc")
            ->column('*','id');
        $list2=[];
        foreach($list as $k=>$v){
            if($v['pid']!=0){
                $name=$list[$v['pid']]['name'].' - '.$v['name'];
                $v['name']=$name;

                $list2[$k]=$v;
            }
        }
        return $list2;

    }

    public function index()
    {

        $data = $this->request->param();
        $map=[];


        $start_time= $data['start_time'] ?? '';
        $end_time= $data['end_time'] ?? '';

        if($start_time!=""){
            $map[]=['addtime','>=',strtotime($start_time)];
        }

        if($end_time!=""){
            $map[]=['addtime','<=',strtotime($end_time) + 60*60*24];
        }

        $gradeid= $data['gradeid'] ?? '';
        if($gradeid!=''){
            $map[]=['gradeid','=',$gradeid];
        }

        $keyword= $data['keyword'] ?? '';
        if($keyword!=''){
            $map[]=['name','like','%'.$keyword.'%'];
        }

        
        $list = Db::name('activity')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k){
            $v['thumb']=get_upload_path($v['thumb']);

            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        $this->assign('grade', $this->getGrade());
        $this->assign('status', $this->getStatus());

        return $this->fetch();
    }

    /* 更新 */
    function setstatus(){
        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->param('status', 0, 'intval');

        $rs = DB::name('activity')->where(['id'=>$id])->update(['status'=>$status]);
        if(!$rs){
            $this->error("操作失败！");
        }

        $this->success("操作成功！");

    }

    public function add()
    {
        $this->assign('grade', $this->getGrade());
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $gradeid=$data['gradeid'] ?? '0';
            if($gradeid < 1){
                $this->error('请选择年级');
            }

            $name=$data['name'] ?? '';
            if($name == ''){
                $this->error('请填写标题');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? '';
            if($starttime == '' || $endtime==''){
                $this->error('请选择活动时间');
            }
            $nowtime=time();
            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime) ;
            if($starttime >= $endtime){
                $this->error('请选择正确活动时间');
            }

            if($endtime<=$nowtime){
                $this->error('请选择正确活动时间');
            }

            $data['starttime']=$starttime;
            $data['endtime']=$endtime;
            $data['addtime']=$nowtime;

            $id = DB::name('activity')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('activity')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $this->assign('data', $data);
        $this->assign('grade', $this->getGrade());

        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];
            $gradeid=$data['gradeid'] ?? '0';
            if($gradeid < 1){
                $this->error('请选择年级');
            }

            $name=$data['name'] ?? '';
            if($name == ''){
                $this->error('请填写标题');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? '';
            if($starttime == '' || $endtime==''){
                $this->error('请选择活动时间');
            }
            $nowtime=time();
            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime >= $endtime){
                $this->error('请选择正确活动时间');
            }

            if($endtime<=$nowtime){
                $this->error('请选择正确活动时间');
            }

            $data['starttime']=$starttime;
            $data['endtime']=$endtime;

            $rs = DB::name('activity')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('activity');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('activity')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }

}