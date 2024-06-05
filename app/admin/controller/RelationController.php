<?php

namespace app\admin\controller;


use cmf\controller\AdminBaseController;
use think\Db;

class RelationController extends  AdminBaseController{

    public function index()
    {

        $list = Db::name('relation')
            ->order("list_order asc")
            ->paginate(20);


        $page = $list->render();
        $this->assign("page", $page);
        // $this->assign('versions', array_column($this->book_versions()->toArray(),null,'id'));
        $this->assign('list', $list);

        return $this->fetch();
    }


    public function add()
    {
        // $this->assign('versions' , $this->book_versions());
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $name = $data['name'];

            if ($name == '') {
                $this->error('请填写名称');
            }

            $map[] = ['name', '=', $name];
            $isexist = DB::name('relation')->where($map)->find();
            if ($isexist) {
                $this->error('同名已存在');
            }

            $id = DB::name('relation')->insertGetId($data);
            if (!$id) {
                $this->error("添加失败！");
            }
            $this->resetcache();
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        $data = Db::name('relation')
            ->where("id={$id}")
            ->find();
        if (!$data) {
            $this->error("信息错误");
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $id = $data['id'];
            $name = $data['name'];

            if ($name == '') {
                $this->error('请填写名称');
            }

            $map[] = ['name', '=', $name];
            $map[] = ['id', '<>', $id];
            $isexist = DB::name('relation')->where($map)->find();
            if ($isexist) {
                $this->error('同名分类已存在');
            }



            $rs = DB::name('relation')->update($data);

            if ($rs === false) {
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }

    public function listOrder()
    {
        $model = DB::name('course_class');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $exist = DB::name('class_patriarch')->where('relation_id', $id)->find();
        if($exist){
            $this->error('已有家长使用此关系，无法删除');
        }
        $rs = DB::name('relation')->where('id', $id)->delete();
        if (!$rs) {
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache()
    {
        $key = 'getrelation';

        $list = DB::name('relation')
            ->order("list_order asc")
            ->select();
        if ($list) {
            setcaches($key, $list);
        } else {
            delcache($key);
        }
    }

}