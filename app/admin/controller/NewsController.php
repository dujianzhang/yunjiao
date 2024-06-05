<?php

/* 新闻资讯 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class NewsController extends AdminBaseController
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

        $data = $this->request->param();
        $map=[];

        $gradeid= $data['gradeid'] ?? '';
        if($gradeid!=''){
            $map[]=['gradeid','=',$gradeid];
        }

        $keyword= $data['keyword'] ?? '';
        if($keyword!=''){
            $map[]=['title','like','%'.$keyword.'%'];
        }

        
        $list = Db::name('news')
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

            $gradeid=$data['gradeid'] ?? '0';
            if($gradeid < 1){
                $this->error('请选择年级');
            }

            $title=$data['title'] ?? '';
            if($title == ''){
                $this->error('请填写标题');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $nowtime=time();

            $data['addtime']=$nowtime;

            $id = DB::name('news')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('news')
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

            $title=$data['title'] ?? '';
            if($title == ''){
                $this->error('请填写标题');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传封面');
            }

            $rs = DB::name('news')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('news');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('news')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }

}