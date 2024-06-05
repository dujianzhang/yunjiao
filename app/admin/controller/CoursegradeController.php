<?php

/* 学级分类 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use tree\Tree;

class CoursegradeController extends AdminBaseController
{

    /* 一级 */
    protected function getGrade0(){
        $list = Db::name('course_grade')
            ->where(['pid'=>0])
            ->order("list_order asc")
            ->column('*','id');
        return $list;
    }

    protected function getClass(){
        $list = Db::name('course_class')
            ->order("list_order asc")
            ->column('*','id');
        return $list;
    }
    
    public function index()
    {
        
        $result = Db::name("course_grade")->order("list_order asc")->select()->toArray();
        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─', '&nbsp;&nbsp;&nbsp;└─ '];
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
        
        
        foreach ($result as $key => $value) {
            $result[$key]['parent_id_node'] = ($value['pid']) ? ' class="child-of-node-' . $value['pid'] . '"' : '';
            $result[$key]['parent_id'] = $value['pid'];
            $result[$key]['style']          = empty($value['pid']) ? '' : 'display:none;';
            $url = "javascript:admin.openIframeLayer('".url('coursegrade/edit', ["id" => $value['id']])."','编辑',{btn: ['保存','关闭'],area:['640px','50%'],end:function(){}});";
            $result[$key]['str_manage']     = '<a class="layui-bo layui-bo-small layui-bo-checked" href="' . $url . '">' . lang('EDIT') . '</a>  
                                               <a class="layui-bo layui-bo-small layui-bo-close js-ajax-delete" href="' . url("coursegrade/del", ["id" => $value['id']]) . '">' . lang('DELETE') . '</a> ';
        }
        $tree->init($result);
        $str      = "<tr id='node-\$id' \$parent_id_node style='\$style'>
                        <td style='padding-left:20px;'><input name='list_orders[\$id]' type='text' size='3' value='\$list_order' class='input input-order'></td>
                        <td>\$id</td>
                        <td>\$spacer\$name</td>
                        <td>\$str_manage</td>
                    </tr>";
        $list = $tree->getTree(0, $str);
        
        
        $this->assign('list', $list);
        

        return $this->fetch();
    }


    public function add()
    {
        $this->assign('grade0', $this->getGrade0());
        $this->assign('classs', $this->getClass());
        
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
            $isexist = DB::name('course_grade')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }

            $pid=$data['pid'];
            $classids_s='';
            if($pid!=0){
                $classids=$data['classids'] ?? [];
                $classids_s=handelSetToStr($classids);
            }

            $data['classids']=$classids_s;

            $id = DB::name('course_grade')->insertGetId($data);
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
        
        $data=Db::name('course_grade')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $this->assign('data', $data);

        $classids=handelSetToArr($data['classids']);

        $this->assign('classids', $classids);

        $this->assign('grade0', $this->getGrade0());
        $this->assign('classs', $this->getClass());
        
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
            $isexist = DB::name('course_grade')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }

            $pid=$data['pid'];
            $classids_s='';
            if($pid!=0){
                $classids=$data['classids'] ?? [];
                $classids_s=handelSetToStr($classids);
            }

            $data['classids']=$classids_s;
			

            $rs = DB::name('course_grade')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('course_grade');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $isok=DB::name('course')->where("gradeid",$id)->find();
        if($isok){
            $this->error("该分类下已有课程，不能删除");
        }
        
        $ifhas = DB::name('course_grade')->where('pid',$id)->find();
        if($ifhas){
            $this->error("该分类下已有分类，不能删除");
        }
        
        $rs = DB::name('course_grade')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache(){
        $key='getcoursegrade';

        $list=DB::name('course_grade')
                ->order("list_order asc")
                ->select();
        if($list){
            setcaches($key,$list);
        }else{
			delcache($key);
		}
    }
}