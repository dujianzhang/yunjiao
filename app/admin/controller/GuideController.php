<?php

/**
 * 引导页
 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class GuideController extends AdminbaseController {

    function set(){
        $config=cmf_get_option('guide');
		$this->assign('config',$config );
    	
    	return $this->fetch();
    }
    
    function setPost(){
        if ($this->request->isPost()) {
            
            $config = $this->request->param('options/a');
            $type=$config['type'];
            $time=$config['time'];

            if($type==0){
                if(!$time||$time<1){
                    $this->error("图片展示时长错误");
                }

                if(floor($time)!=$time){
                    $this->error("图片展示时长错误");
                }
            }
            $rs = cmf_set_option('guide', $config,true);
            if($rs===false){
                $this->error("保存失败！");
            }

            setcaches('guide_config',$config);

            $this->success("保存成功！");
            
		}
    }

    public function getOpenType($k=''){
        $sort = [
            '1' => 'APP外',
            '2' => 'APP内',
        ];
        if ($k === '') {
            return $sort;
        }
        return isset($sort[$k]) ? $sort[$k] : '';
    }

    function index(){

        $config=DB::name("option")->where("option_name='guide'")->value("option_value");
        
        $config = json_decode($config,true);
        
        $type=$config['type'];
        
        $map['type']=$type;
        
        
        $lists = Db::name("guide")
            ->where($map)
			->order("list_order asc, id desc")
			->paginate(20);

        $lists->each(function($v,$k){
			$v['thumb']=get_upload_path($v['thumb']);
            return $v;           
        });
        
        $page = $lists->render();

    	$this->assign('list', $lists);

    	$this->assign("page", $page);
        
        $this->assign('type', $type);
        $this->assign('opentype', $this->getOpenType());

    	return $this->fetch();
    }
	
    function del(){
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('guide')->where("id={$id}")->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        $this->success("删除成功！");
        
    }
    //排序
    public function listOrder() { 
		
        $model = DB::name('guide');
        parent::listOrders($model);

        $this->success("排序更新成功！");
        
    }
    
    function add(){
        $config=DB::name("option")->where("option_name='guide'")->value("option_value");
        
        $config = json_decode($config,true);
        
        $type=$config['type'];
        

            $map['type']=$type;
            
            $count=DB::name("guide")->where($map)->count();
            if($count>=1){
                $this->error("同类型引导页只能存在一个");
            }

        $this->assign('type', $type);
        
		return $this->fetch();
	}
    function addPost(){
		if ($this->request->isPost()) {
            
            $data = $this->request->param();

            $type=$data['type'];

            $count=DB::name("guide")->where(['type'=>$type])->count();
            if($count>=1){
                $this->error("同类型引导页只能存在一个");
            }
            
            $thumb=$data['thumb'];

            if(!$thumb){
                $this->error("请上传引导页图片/视频");
            }
            
            
            $data['href']=html_entity_decode($data['href']);
            $data['addtime']=time();
            $data['uptime']=time();
            
			$id = DB::name('guide')->insertGetId($data);
            if(!$id){

                $this->error("添加失败！");
            }
            $this->resetGuide($type);
            $this->success("添加成功！",url('guide/index'));
            
		}
	}
    
	function edit(){
        
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('guide')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $this->assign('data', $data);
        return $this->fetch();
	}
	
	function editPost(){
		if ($this->request->isPost()) {
            
            $data      = $this->request->param();
            
            $thumb=$data['thumb'];

            if(!$thumb){
                $this->error("请上传引导页图片/视频");
            }
            
            $data['href']=html_entity_decode($data['href']);
            $data['uptime']=time();
            
			$rs = DB::name('guide')->update($data);
            if($rs===false){
                $this->error("修改失败！");
            }
            $type=$data['type'];
            $this->resetGuide($type);
            
            $this->success("修改成功！");
		}
	}

	public function resetGuide($type){
        $key='guide_'.$type;
        $where=['type'=>$type];
        $list=db('guide')->where($where)->order('list_order asc')->select()->toArray();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
    }
}
