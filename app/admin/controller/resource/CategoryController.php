<?php

namespace app\admin\controller\resource;


use cmf\controller\AdminBaseController;
use think\Db;
use tree\Tree;
/**
 * 资源分类
 */
class CategoryController extends AdminBaseController
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

        $result = Db::name("resource_category")->order("list_order asc")->select()->toArray();
        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─', '&nbsp;&nbsp;&nbsp;└─ '];
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';


        foreach ($result as $key => $value) {

            $result[$key]['parent_id_node'] = ($value['pid']) ? ' class="child-of-node-' . $value['pid'] . '"' : '';
            $result[$key]['parent_id'] = $value['pid'];
            $result[$key]['style']          = empty($value['pid']) ? '' : 'display:none;';
            $url = "javascript:admin.openIframeLayer('".url('resource.category/edit', ["id" => $value['id']])."','编辑',{btn: ['保存','关闭'],area:['640px','50%'],end:function(){location.reload();}});";
            $result[$key]['str_manage']     = '<a class="layui-bo layui-bo-small layui-bo-checked" href="' . $url . '">' . lang('EDIT') . '</a>  
                                               <a class="layui-bo layui-bo-small layui-bo-close js-ajax-delete" href="' . url("resource.category/del", ["id" => $value['id']]) . '">' . lang('DELETE') . '</a> ';
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
        $list = $this->getaddList();
        $this->assign([
            'list'=>$list
        ]);

        return $this->fetch();
    }


    public function getAddTree($parentInfo, $target = [], $start = 0)
    {

        $info = Db::name("resource_category")
            ->where('pid', $parentInfo['id'])
            ->select();
        if ($start == 0) {
            array_push($target, $parentInfo);
        }

        foreach ($info as $key => &$value) {
            array_push($target, $value);
            if($value['level'] < 2){
                $target = $this->getTree($value, $target, 1);
            }

        }
        return $target;
    }
    public function getaddList()
    {
        $info = Db::name("resource_category")
            ->where('pid', 0)
            ->select()->toArray();

        $list = [];
        foreach ($info as $key => $value){
            $son = $this->getAddTree($value);
            $list = array_merge($list,$son);
        }

        return $list;
    }
    public function getTree($parentInfo, $target = [], $start = 0)
    {

        $info = Db::name("resource_category")
            ->where('pid', $parentInfo['id'])
            ->select();
        if ($start == 0) {
            array_push($target, $parentInfo);
        }

        static $n = 1;
        foreach ($info as $key => &$value) {
            array_push($target, $value);
            $n++;
            $target = $this->getTree($value, $target, 1);
            $n--;
        }

        return $target;
    }



    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $name=$data['name'];

            if($name == ''){
                $this->error('请填写名称');
            }

            $pid=$data['pid'];
            if($pid == 0){
                $level = 1;
            }else{
                $parentInfo = DB::name('resource_category')->where(['id'=>$pid])->find();
                if(!$parentInfo){
                    $level = 1;
                    $pid = 0;
                }else{
                    $level = $parentInfo['level']+1;
                    $type = $parentInfo['type'];
                }
            }
            if(!isset($type)){
                $this->error("添加失败！");
            }
            $data['pid'] = $pid;
            $data['level'] = $level;
            $data['type'] = $type;

            $id = DB::name('resource_category')->insertGetId($data);
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

        $data=Db::name('resource_category')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $list = $this->getaddList();
        $this->assign([
            'list'=>$list
        ]);

        $this->assign('data', $data);

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
            $isexist = DB::name('resource_category')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }

            $rs = DB::name('resource_category')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }

    public function listOrder()
    {
        $model = DB::name('resource_category');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $info = DB::name('resource_category')->where('id',$id)->find();
        if(!$info){
            $this->error("当前分类已不存在");
        }

        if($info['pid'] == 0){
            $this->error("当前分类无法删除");
        }

        $ifhas = DB::name('resource_category')->where('pid',$id)->find();
        if($ifhas){
            $this->error("该分类下已有分类，不能删除");
        }

        if(in_array($id,[1,2,3,4,5,6])){
            $this->error("默认分类，不能删除");
        }
        $rs = DB::name('resource_category')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache(){
        $key='resource_category';

        $list=DB::name('resource_category')
            ->order("list_order asc")
            ->select();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
    }
}