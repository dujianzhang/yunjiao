<?php

/**
 * 充值规则
 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ChargerulesController extends AdminbaseController {

		
    function index(){
        
        $list = Db::name("charge_rules")
			->order("list_order asc")
			->paginate(20);

        $list->each(function ($v,$k){
            $v['add_time']=date('Y-m-d H:i',$v['addtime']);
            return $v;
        });
        $page = $list->render();

    	$this->assign('list', $list);

    	$this->assign("page", $page);
    	
    	return $this->fetch();
        
    }		
		
	function del(){
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('charge_rules')->where("id={$id}")->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        $this->resetcache();
        $this->success("删除成功！");
	}
    
    //排序
    public function listOrder() { 
		
        $model = DB::name('charge_rules');
        parent::listOrders($model);

        $this->resetcache();
        $this->success("排序更新成功！");
        
    }	

	
    function add(){
		return $this->fetch();
    }	
	
    function addPost(){
		if ($this->request->isPost()) {
            
            $data = $this->request->param();

            $name=$data['name'];
            $money=$data['money'];
            $coin=$data['coin'];
            $coin_ios=$data['coin_ios'];
            $product_id=$data['product_id'];

            if(!$name){
                $this->error("请填写名称");
            }

            if(!$money){
                $this->error("请填写价格");
            }

            if(!is_numeric($money)){
                $this->error("价格必须为数字");
            }

            if($money<=0||$money>99999999){
                $this->error("价格在0.01-99999999之间");
            }

            $data['money']=round($money,2);

            if(!$coin){
                $this->error("请填写安卓增加数");
            }

            if(!is_numeric($coin)){
                $this->error("安卓增加数必须为数字");
            }

            if($coin<1||$coin>99999999){
                $this->error("安卓增加数在1-99999999之间");
            }

            if(floor($coin)!=$coin){
                $this->error("安卓增加数必须为整数");
            }

            if(!$coin_ios){
                $this->error("请填写苹果增加数");
            }

            if(!is_numeric($coin_ios)){
                $this->error("苹果增加数必须为数字");
            }

            if($coin_ios<1||$coin_ios>99999999){
                $this->error("苹果增加数在1-99999999之间");
            }

            if(floor($coin_ios)!=$coin_ios){
                $this->error("苹果增加数必须为整数");
            }

            if($product_id==''){
                $this->error("苹果项目ID不能为空");
            }

            $data['addtime']=time();
            
			$id = DB::name('charge_rules')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }

            $this->resetcache();
            $this->success("添加成功！");
            
		}
	}
    
    function edit(){
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('charge_rules')
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
            
            $data = $this->request->param();

            $name=$data['name'];
            $money=$data['money'];
            $coin=$data['coin'];
            $coin_ios=$data['coin_ios'];
            $product_id=$data['product_id'];

            if(!$name){
                $this->error("请填写名称");
            }

            if(!$money){
                $this->error("请填写价格");
            }

            if(!is_numeric($money)){
                $this->error("价格必须为数字");
            }

            if($money<=0||$money>99999999){
                $this->error("价格在0.01-99999999之间");
            }

            $data['money']=round($money,2);

            if(!$coin){
                $this->error("请填写安卓增加数");
            }

            if(!is_numeric($coin)){
                $this->error("安卓增加数必须为数字");
            }

            if($coin<1||$coin>99999999){
                $this->error("安卓增加数在1-99999999之间");
            }

            if(floor($coin)!=$coin){
                $this->error("安卓增加数必须为整数");
            }

            if(!$coin_ios){
                $this->error("请填写苹果增加数");
            }

            if(!is_numeric($coin_ios)){
                $this->error("苹果增加数必须为数字");
            }

            if($coin_ios<1||$coin_ios>99999999){
                $this->error("苹果增加数在1-99999999之间");
            }

            if(floor($coin_ios)!=$coin_ios){
                $this->error("苹果增加数必须为整数");
            }

            if($product_id==''){
                $this->error("苹果项目ID不能为空");
            }

			$rs = DB::name('charge_rules')->update($data);
            if($rs===false){
                $this->error("修改失败！");
            }

            $this->resetcache();
            $this->success("修改成功！");
		}
	}
    	

    function resetcache(){
        $key='getChargeRules';
        $rules= DB::name("charge_rules")
            ->field('id,coin,coin_ios,money,product_id')
            ->order('list_order asc')
            ->select();
        if($rules){
            setcaches($key,$rules);
        }else{
			delcache($key);
		}
        return 1;
    }
}
