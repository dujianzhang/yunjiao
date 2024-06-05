<?php

/* 题库分类 */
namespace app\admin\controller;

use app\models\GradeModel;
use app\models\QuestionclassModel;
use cmf\controller\AdminBaseController;
use think\Db;
use tree\Tree;

class QuestionclassController extends AdminBaseController
{

    /* 一级 */
    protected function getClass(){
        $list = Db::name('question_class')
            ->where(['pid'=>0])   
            ->order("list_order asc")   
            ->column('*','id');

        $geade_name=GradeModel::getIdName();

        foreach ($list as $k=>$v){

            $geadename=$geade_name[$v['gradeid']] ?? '';
            $v['name']=$geadename.'/'.$v['name'];

            $list[$k]=$v;
        }
        return $list;
    }
    
    public function index()
    {
        
        $result = Db::name("question_class")->order("list_order asc")->select()->toArray();
        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─', '&nbsp;&nbsp;&nbsp;└─'];
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';

        $geade_name=GradeModel::getIdName();

        foreach ($result as $key => $value) {
            $result[$key]['parent_id_node'] = ($value['pid']) ? ' class="child-of-node-' . $value['pid'] . '"' : '';
            $result[$key]['parent_id'] = $value['pid'];
            $result[$key]['gradename'] = $geade_name[$value['gradeid']] ?? '';
            $result[$key]['style']          = empty($value['pid']) ? '' : 'display:none;';
            $url = "javascript:admin.openIframeLayer('".url('questionclass/edit', ["id" => $value['id']])."','分类编辑',{btn: ['保存','关闭'],area:['640px','50%'],end:function(){}});";
            $result[$key]['str_manage']     = '<a class="layui-bo layui-bo-small layui-bo-checked" href="' . $url . '">' . lang('EDIT') . '</a>  
                                               <a class="layui-bo layui-bo-small layui-bo-close js-ajax-delete" href="' . url("questionclass/del", ["id" => $value['id']]) . '">' . lang('DELETE') . '</a> ';
        }
        $tree->init($result);
        $str      = "<tr id='node-\$id' \$parent_id_node style='\$style'>
                        <td style='padding-left:20px;'><input name='list_orders[\$id]' type='text' size='3' value='\$list_order' class='input input-order'></td>
                        <td>\$id</td>
                        <td>\$gradename</td>
                        <td>\$spacer\$name</td>
                        <td>\$str_manage</td>
                    </tr>";
        $list = $tree->getTree(0, $str);
        
        
        $this->assign('list', $list);
        

        return $this->fetch();
    }


    public function add()
    {
        $this->assign('classs', $this->getClass());


        $this->assign('grade',GradeModel::getListTwo());
        
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            
            $name=$data['name'];
            $pid=$data['pid'];
            $gradeid=$data['gradeid'];

            if($name == ''){
                $this->error('请填写名称');
            }

            if($pid>0){
                $pinfo=db('question_class')->where(['id'=>$pid])->find();
                if(!$pinfo){
                    $this->error('上级不存在，请刷新重选');
                }
                $gradeid=$pinfo['gradeid'];
            }
            
            $map[]=['gradeid','=',$gradeid];
            $map[]=['name','=',$name];
            $isexist = DB::name('question_class')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }
            $data['gradeid']=$gradeid;

            $id = DB::name('question_class')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            QuestionclassModel::resetcache();
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('question_class')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $this->assign('data', $data);
        
        $this->assign('classs', $this->getClass());
        
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];
            $name=$data['name'];
            $gradeid=$data['gradeid'];
            
            if($name == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['gradeid','=',$gradeid];
            $map[]=['name','=',$name];
            $map[]=['id','<>',$id];
            $isexist = DB::name('question_class')->where($map)->find();
            if($isexist){
                $this->error('同名分类已存在');
            }
			

            $rs = DB::name('question_class')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            QuestionclassModel::resetcache();
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('question_class');
        parent::listOrders($model);
        QuestionclassModel::resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $isok=DB::name('question')->where("classid",$id)->find();
        if($isok){
            $this->error("该分类下已有题目，不能删除");
        }
        
        $ifhas = DB::name('question_class')->where('pid',$id)->find();
        if($ifhas){
            $this->error("该分类下已有分类，不能删除");
        }
        
        $rs = DB::name('question_class')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        QuestionclassModel::resetcache();
        $this->success("删除成功！");
    }

    public function getAllClass(){

        $list=Db::name('question_class')
            ->field('id,name,pid,nums')
            ->order('list_order asc')
            ->select()
            ->toArray();
        $total=0;
        $list=$this->handellist($list);
        foreach ($list as $k=>$v){
            $nums=0;
            foreach ($v['list'] as $k2=>$v2){
                $nums+=$v2['nums'];
            }

            $v['nums']=$nums;
            $total+=$nums;
            $list[$k]=$v;
        }
        $info=[
            'list'=>$list,
            'total'=>$total,
        ];
        $this->success('','',$info);

    }

    /* 处理课时数组 */
    protected function handellist($list=[],$pid=0){
        $rs=[];
        foreach($list as $k=>$v){
            if($v['pid']==$pid){
                unset($list[$k]);
                $v['list']=$this->handellist($list,$v['id']);
                $rs[]=$v;
            }
        }

        return $rs;
    }
}