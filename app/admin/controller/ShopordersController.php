<?php

/**
 * 商品订单管理
 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ShopordersController extends AdminbaseController {
    
    /* 支付状态 */
    protected function getStatus($k=''){
        $status=array(
            '-1'=>'已超时',
            '0'=>'未支付',
            '1'=>'待发货',
            '2'=>'待收货',
            '3'=>'已完成',
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
            '4'=>'微信H5',
            '7'=>'微信PC',
            '8'=>'微信内H5',
            '5'=>'余额支付',
            '6'=>'其他',
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
		
        $lists = Db::name("shop_orders")
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

    /* 订单商品 */
    function goods(){
        $data = $this->request->param();
        $map=[];
        
        $orderno=isset($data['orderno']) ? $data['orderno']: '';

        $map[]=['orderno','=',$orderno];
		
        $lists = Db::name("shop_orders_good")
            ->where($map)
			->order("id desc")
			->paginate(20);
        
        $lists->each(function($v,$k){
            $info=json_decode($v['info'],true);
            $v['info']=$info ;
            return $v;           
        });
        
        $lists->appends($data);
        $page = $lists->render();

    	$this->assign('lists', $lists);

    	$this->assign("page", $page);
        
    	return $this->fetch();
    }
    
    /* 确认支付 */
    function setPay(){
        $id = $this->request->param('id', 0, 'intval');
        
        $info = DB::name('shop_orders')->where("id={$id}")->find();
        if(!$info){
            $this->error("标记失败！");
        }

        $this->success("标记成功！");
        							  			
    }
    
    /* 标记发货 */
    function send(){
        $id = $this->request->param('id', 0, 'intval');
        
        $express=Db::name('express')->order('list_order asc')->select()->toArray();

        $this->assign("id", $id);
        $this->assign("express", $express);

        return $this->fetch();
    }

    /* 标记发货 */
    function sendPost(){

        if ($this->request->isPost()) {

            $data      = $this->request->param();

            $id=$data['id'] ?? 0;
            $express_no=$data['express_no'] ?? '';
            $expressid=$data['expressid'] ?? 0;

            if($expressid==0){
                $this->error("请选择快递公司");
            }

            if($express_no==''){
                $this->error("请填写快递单号");
            }

            $expressinfo=Db::name('express')->where('id',$expressid)->find();
            if(!$expressinfo){
                $this->error("快递公司不存在");
            }

            $express_name=$expressinfo['name'];
            $express_id=$expressinfo['sign'];

            $up=[
                'express_name'=>$express_name,
                'express_no'=>$express_no,
                'express_id'=>$express_id,
                'sendtime'=>time(),
                'status'=>2,
            ];
            $rs = DB::name('shop_orders')->where(['id'=>$id])->update($up);
            if($rs===false){
                $this->error("发货失败！");
            }

            $this->success("发货成功！");

        }



    }
    
    function del(){
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('shop_orders')->where("id={$id}")->delete();
        if($rs===false){
            $this->error("删除失败！");
        }
                   
        $this->success("删除成功！");
        							  			
    }
    
}
