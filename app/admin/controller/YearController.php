<?php

/* 课程分类 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class YearController extends AdminBaseController
{

    public function index()
    {

        $list = Db::name('year')
            ->order("name desc")
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
            $data = $this->request->param();

            $name = $data['name'];

            if ($name == '') {
                $this->error('请填写名称');
            }

            $map[] = ['name', '=', $name];
            $isexist = DB::name('year')->where($map)->find();
            if ($isexist) {
                $this->error('同名分类已存在');
            }



            $id = DB::name('year')->insertGetId($data);
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
        $data = Db::name('year')
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
            $isexist = DB::name('year')->where($map)->find();
            if ($isexist) {
                $this->error('同名月份已存在');
            }



            $rs = DB::name('year')->update($data);

            if ($rs === false) {
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }

    public function listOrder()
    {
        $model = DB::name('year');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');



        $isok = DB::name('class_grade_system')->where("year_id", $id)->find();
        if ($isok) {
            $this->error("年份已有学校使用");
        }

        $rs = DB::name('year')->where('id', $id)->delete();
        if (!$rs) {
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache()
    {
        $key = 'getyear';

        $list = DB::name('year')
            ->order("list_order asc")
            ->select();
        if ($list) {
            setcaches($key, $list);
        } else {
            delcache($key);
        }
    }


}