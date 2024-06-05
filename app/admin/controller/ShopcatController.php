<?php

/* 教辅商城 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ShopcatController extends AdminBaseController
{

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
        
        $list = Db::name('shop_cat')
            ->order("list_order asc")
            ->paginate(20);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        $this->assign('grade', $this->getGrade());

        return $this->fetch();
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
            
            $name=$data['name'];
            
            if($name == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['name','=',$name];
            $isexist = DB::name('shop_cat')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }


            $id = DB::name('shop_cat')->insertGetId($data);
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
        
        $data=Db::name('shop_cat')
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
            $name=$data['name'];
            
            if($name == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['name','=',$name];
            $map[]=['id','<>',$id];
            $isexist = DB::name('shop_cat')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }


            $rs = DB::name('shop_cat')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('shop_cat');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $isok=DB::name('shop')->where("catid",$id)->find();
        if($isok){
            $this->error("该分类下已有商品，不能删除");
        }
        
        $rs = DB::name('shop_cat')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache(){
        $key='shop_cat';

        $list=DB::name('shop_cat')
                ->order("list_order asc")
                ->select();
        if($list){
            setcaches($key,$list);
        }else{
			delcache($key);
		}
    }
}