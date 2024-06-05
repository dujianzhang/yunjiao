<?php

/**
 * 充值记录
 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ChargeController extends AdminbaseController {
    protected function getStatus($k=''){
        $status=array(
            '0'=>'未支付',
            '1'=>'已完成',
        );
        if($k===''){
            return $status;
        }
        
        return isset($status[$k]) ? $status[$k]: '';
    }
                                                             
    protected function getTypes($k=''){
        $type=array(
            '1'=>'支付宝',
            '2'=>'微信APP',
            '3'=>'微信小程序',
            '4'=>'微信H5',
            '7'=>'微信PC',
            '8'=>'微信内H5',
            '6'=>'其他',
            '9'=>'苹果支付',
        );
        if($k===''){
            return $type;
        }
        
        return isset($type[$k]) ? $type[$k]: '';
    }
    
    protected function getAmbient($k=''){
        $ambient=array(
            "9"=>array(
                '0'=>'沙盒',
                '1'=>'生产',
            )
        );
        
        if($k===''){
            return $ambient;
        }
        
        return isset($ambient[$k]) ? $ambient[$k]: '';
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
        
        $uid=isset($data['uid']) ? $data['uid']: '';
        if($uid!=''){
            $map[]=['uid','=',$uid];
        }
        
        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['orderno|trade_no','like','%'.$keyword.'%'];
        }
        
        
        $list = Db::name("charge_user")
            ->where($map)
			->order("id desc")
			->paginate(20);

        $list->each(function($v,$k){
			$v['userinfo']=getUserInfo($v['uid']);
            return $v;           
        });

        $list->appends($data);
        $page = $list->render();

    	$this->assign('list', $list);

    	$this->assign("page", $page);
        
        $this->assign('status', $this->getStatus());
        $this->assign('type', $this->getTypes());
        $this->assign('ambient', $this->getAmbient());
    	
        $moneysum = Db::name("charge_user")
            ->where($map)
			->sum('money');
        if(!$moneysum){
            $moneysum=0;
        }

    	$this->assign('moneysum', $moneysum);
        
    	return $this->fetch();
    }
    
    function setPay(){
        $id = $this->request->param('id', 0, 'intval');
        if($id){
            $result=Db::name("charge_user")->where(["id"=>$id,"status"=>0])->find();				
            if($result){
                /* 更新会员余额 */
                $coin=$result['coin'];
                Db::name("users")->where("id='{$result['touid']}'")->setInc("coin",$coin);
                /* 更新 订单状态 */
                Db::name("charge_user")->where("id='{$result['id']}'")->update(array("status"=>1));

                /* 余额变动记录 */
                $add=[
                    'type'=>1,
                    'action'=>7,
                    'uid'=>$result['touid'],
                    'actionid'=>$result['id'],
                    'nums'=>1,
                    'total'=>$coin,
                    'addtime'=>time(),
                ];

                Db::name('users_coinrecord')->insert($add);

                $this->success('操作成功');
             }else{
                $this->error('数据传入失败！');
             }			
        }else{				
            $this->error('数据传入失败！');
        }								          
    }
    
    
    function del(){
        $id = $this->request->param('id', 0, 'intval');
        
        $rs = DB::name('charge_user')->where("id={$id}")->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        $this->success("删除成功！");
        							  			
    }

}
