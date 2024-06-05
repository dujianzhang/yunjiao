<?php

/**
 * 订单管理
 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class OrdersController extends AdminbaseController {
    
    /* 支付状态 */
    protected function getStatus($k=''){
        $status=array(
            '-1'=>'已超时',
            '0'=>'未支付',
            '1'=>'已支付',
            '2'=>'已完成',
        );
        if($k===''){
            return $status;
        }
        
        return isset($status[$k]) ? $status[$k]: '';
    }
    
    /* 支付方式 */
    protected function getTypes($k=''){
        $type=array(
            '1'=>'支付宝',
            '2'=>'微信APP',
            '3'=>'微信小程序',
            '4'=>'微信外H5',
            '7'=>'微信PC',
            '5'=>'余额支付',
            '6'=>'其他',
            '8'=>'微信内H5',
        );
        if($k===''){
            return $type;
        }
        
        return isset($type[$k]) ? $type[$k]: '';
    }
    
    function index(){
        $data = $this->request->param();
        $map=[];
        
        $start_time=isset($data['start_time']) ? $data['start_time']: '';
        $end_time=isset($data['end_time']) ? $data['end_time']: '';
        
        if($start_time!=""){
           $map[]=['addtime','>=',strtotime($start_time)];
        }

        if($end_time!=""){
           $map[]=['addtime','<=',strtotime($end_time) + 60*60*24];
        }
        
        $status=isset($data['status']) ? $data['status']: '';
        if($status!=''){
            $map[]=['status','=',$status];
        }
        
        $type=isset($data['type']) ? $data['type']: '';
        if($type!=''){
            $map[]=['type','=',$type];
        }
        
        $uid=isset($data['uid']) ? $data['uid']: '';
        if($uid!=''){
            $map[]=['uid','=',$uid];
        }
        
        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['orderno|trade_no','=',$keyword];
        }
		
        $lists = Db::name("orders")
            ->where($map)
			->order("id desc")
			->paginate(20);
        
        $lists->each(function($v,$k){
			$v['userinfo']=getUserInfo($v['uid']);
            return $v;           
        });
        
        $lists->appends($data);
        $page = $lists->render();

    	$this->assign('lists', $lists);

    	$this->assign("page", $page);
        
        $this->assign('status', $this->getStatus());
        $this->assign('type', $this->getTypes());
        
    	return $this->fetch();
    }
    
    /* 支付方式 */
    protected function getGoodsTypes($k=''){
        $type=array(
            '1'=>'套餐',
            '0'=>'课程',
        );
        if($k===''){
            return $type;
        }
        
        return isset($type[$k]) ? $type[$k]: '';
    }
    
    /* 订单商品 */
    function goods(){
        $data = $this->request->param();
        $map=[];
        
        $orderno=isset($data['orderno']) ? $data['orderno']: '';

        $map[]=['orderno','=',$orderno];
		
        $lists = Db::name("orders_good")
            ->where($map)
			->order("id desc")
			->paginate(20);
        
        $lists->each(function($v,$k){
            $info=json_decode($v['info'],true);
            $v['name']=$info['name'] ?? '';
            return $v;           
        });
        
        $lists->appends($data);
        $page = $lists->render();

    	$this->assign('lists', $lists);

    	$this->assign("page", $page);
        
        $this->assign('type', $this->getGoodsTypes());
        
    	return $this->fetch();
    }
    
    /* 确认支付 */
    function setPay(){
        $id = $this->request->param('id', 0, 'intval');
        
        $info = DB::name('orders')->where("id={$id}")->find();
        if(!$info){
            $this->error("标记失败！");
        }
        
                    
        $this->success("标记成功！");
        							  			
    }
    
    /* 标记发货 */
    function setSend(){
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('orders')->where(['id'=>$id,'issend'=>0])->update(['issend'=>1,'sendtime'=>time()]);
        if($rs===false){
            $this->error("标记失败！");
        }
        
                    
        $this->success("标记成功！");
        							  			
    }
    
    function del(){
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('orders')->where("id={$id}")->delete();
        if($rs===false){
            $this->error("删除失败！");
        }
                   
        $this->success("删除成功！");
        							  			
    }
    
}
