<?php

/* 快递公司管理 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ExpressController extends AdminBaseController
{

    public function index()
    {
        
        $list = Db::name('express')
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
            
            $name=$data['name'] ?? '';
            $sign=$data['sign'] ?? '';

            if($name == ''){
                $this->error('请填写快递公司名称');
            }

            if($sign == ''){
                $this->error('请填写快递公司标识');
            }
            
            $map[]=['name','=',$name];
            $isexist = DB::name('express')->where($map)->find();
            if($isexist){
                $this->error('快递公司已存在');
            }

            $map2[]=['sign','=',$sign];
            $isexist = DB::name('express')->where($map2)->find();
            if($isexist){
                $this->error('快递公司标识已存在');
            }
			

            $id = DB::name('express')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }

            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('express')
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
            $name=$data['name'] ?? '';
            $sign=$data['sign'] ?? '';

            if($name == ''){
                $this->error('请填写快递公司名称');
            }

            if($sign == ''){
                $this->error('请填写快递标识');
            }

            $map[]=['name','=',$name];
            $map[]=['id','<>',$id];
            $isexist = DB::name('express')->where($map)->find();
            if($isexist){
                $this->error('快递公司已存在');
            }

            $map2[]=['sign','=',$sign];
            $map2[]=['id','<>',$id];
            $isexist = DB::name('express')->where($map2)->find();
            if($isexist){
                $this->error('快递公司标识已存在');
            }
			

            $rs = DB::name('express')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('express');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        
        $rs = DB::name('express')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }


}