<?php

/* 套餐 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class PackageController extends AdminBaseController
{
    /* 学级分类 */
    protected function getGrade(){
        $list = Db::name('course_grade')
            ->order("list_order asc")
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
    
    /* 更具课程ID 获取 课程信息 */
    protected function getCourseids($courseid_s){
        $course=[];
        $courseid_a=[];
        $courseids_a=explode(',',$courseid_s);
        foreach($courseids_a as $k=>$v){
            $courseid_a[]=preg_replace('/\[|\]/','',$v);
        }
        if(!$courseid_a){
            return $course;
        }
        
        //$courseid_s=implode(',',$courseid_a);
        
        $where=[
            ['id','in',$courseid_a],
        ];
        
        $course=Db::name('course')
            ->where($where)
            ->order("list_order asc")
            ->select()
            ->toArray();
            
        return $course;
    }

    public function index()
    {
        $data = $this->request->param();
        $map=[];
        
        $courseid=isset($data['courseid']) ? $data['courseid']: '';
        if($courseid!=''){
            $map[]=['courseids','like','%'.$courseid.'%'];
        }
        
        
        $list = Db::name('course_package')
            ->where($map)
            ->order("list_order asc")
            ->paginate(20);
            
        $list->each(function($v,$k){
            $v['thumb']=get_upload_path($v['thumb']);
            
            $v['courses']=$this->getCourseids($v['courseids']);
            
            return $v;
        });
		
        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        
        $this->assign('grade', $this->getGrade());

        return $this->fetch();
    }


    public function getCourse()
    {
        $data = $this->request->param();
        
        $nowtime=time();
        
        $map=[
            ['isvip','=',0],
            ['sort','=',1],
            ['paytype','=',1],
            ['shelvestime','<',$nowtime],
        ];
        
        $gradeid=isset($data['gradeid']) ? $data['gradeid']: '';
        if($gradeid!=''){
            $map[]=['gradeid','=',$gradeid];
        }

        $list=Db::name('course')
            ->where($map)
            ->order("list_order asc")
            ->select()
            ->toArray();
            
        $this->success('','', $list);
        
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
            $isexist = DB::name('course_package')->where($map)->find();
            if($isexist){
                $this->error('同名套餐已存在');
            }
            
            $courseids_a=isset($_POST['courseids_a']) ? $_POST['courseids_a'] : '';
            unset($data['courseids_a']);
            if(!$courseids_a){
                $this->error('请选择课程');
            }
            $nums=count($courseids_a);
            if($nums>10){
                $this->error('最多选择10个课程');
            }
            $data['nums']=$nums;
            
            $courseids=[];
            foreach($courseids_a as $k=>$v){
                $courseids[]='['.$v.']';
            }
            $data['courseids']=implode(',',$courseids);
            
            $thumb=$data['thumb'];
            if($thumb == ''){
                $this->error('请上传封面图片');
            }
            
            $price=$data['price'];
            if($price == ''){
                $this->error('请填写价格');
            }

            $id = DB::name('course_package')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }
            
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('course_package')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $data['courses']=$this->getCourseids($data['courseids']);
        
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
            $isexist = DB::name('course_package')->where($map)->find();
            if($isexist){
                $this->error('同名套餐已存在');
            }
            
            $thumb=$data['thumb'];
            if($thumb == ''){
                $this->error('请上传封面图片');
            }
			
            $price=$data['price'];
            if($price == ''){
                $this->error('请填写价格');
            }

            $rs = DB::name('course_package')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('course_package');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('course_package')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        /* 删除购物车中的 */
        DB::name('cart')->where(['type'=>1,'typeid'=>$id])->delete();

        $this->success("删除成功！");
    }

}