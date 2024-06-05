<?php

/* 考试分类 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class TestscatController extends AdminBaseController
{

    public function index()
    {
        
        $list = Db::name('tests_cat')
            ->order("list_order asc")
            ->paginate(20);
		
        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        return $this->fetch();
    }


    public function add()
    {
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            
            $title=$data['title'];
            
            if($title == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['title','=',$title];
            $isexist = DB::name('tests_cat')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }

            $id = DB::name('tests_cat')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            $this->resetcache();
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('tests_cat')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];
            $title=$data['title'];
            
            if($title == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['title','=',$title];
            $map[]=['id','<>',$id];
            $isexist = DB::name('tests_cat')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }

            $rs = DB::name('tests_cat')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('tests_cat');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $isok=DB::name('tests')->where("catid",$id)->where('status','<>',-1)->find();
        if($isok){
            $this->error("该分类下已有考试，不能删除");
        }
        
        $rs = DB::name('tests_cat')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache(){
        $key='gettestscat';

        $list=DB::name('tests_cat')
                ->order("list_order asc")
                ->select();
        if($list){
            setcaches($key,$list);
        }else{
			delcache($key);
		}
    }
}