<?php

/* 教辅商城 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ShopController extends AdminBaseController
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

    public function getCat(){

        $list=Db::name('shop_cat')->order('list_order asc')->column('*','id');

        return $list;
    }

    public function getCat2(){

        $gradeid   = $this->request->param('gradeid', 0, 'intval');

        $list=Db::name('shop_cat')->where('gradeid',$gradeid)->order('list_order asc')->select()->toArray();

        $this->success("",'',$list);
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

        $catid= $data['catid'] ?? '';
        if($catid!=''){
            $map[]=['catid','=',$catid];
        }

        $keyword= $data['keyword'] ?? '';
        if($keyword!=''){
            $map[]=['name','like','%'.$keyword.'%'];
        }

        
        $list = Db::name('shop')
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
        $this->assign('cat', $this->getCat());
        $this->assign('status', $this->getStatus());

        return $this->fetch();
    }

    /* 更新 */
    function setstatus(){
        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->param('status', 0, 'intval');

        $rs = DB::name('shop')->where(['id'=>$id])->update(['status'=>$status]);
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

            $catid=$data['catid'] ?? '0';
            if($catid < 0){
                $this->error('请选择分类');
            }

            $name=$data['name'] ?? '';
            if($name == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $price=$data['price'] ?? '';
            if($price == ''){
                $this->error('请填写价格');
            }

            if($price<=0){
                $this->error('请填写正确价格');
            }

            $thumbs=$data['photo_urls'] ?? [];

            $data['thumbs']=json_encode($thumbs);
            unset($data['photo_urls']);

            $data['addtime']=time();

            $id = DB::name('shop')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('shop')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $data['thumbs']=json_decode($data['thumbs'],true);
        
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

            $catid=$data['catid'] ?? '0';
            if($catid < 0){
                $this->error('请选择分类');
            }

            $name=$data['name'] ?? '';
            if($name == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $price=$data['price'] ?? '';
            if($price == ''){
                $this->error('请填写价格');
            }

            if($price<=0){
                $this->error('请填写正确价格');
            }

            $thumbs=$data['photo_urls'] ?? [];

            $data['thumbs']=json_encode($thumbs);
            unset($data['photo_urls']);

            $rs = DB::name('shop')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('shop');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('shop')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }

}