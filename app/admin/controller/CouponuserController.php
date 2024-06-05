<?php

/* 优惠券-用户 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class CouponuserController extends AdminBaseController
{

    public function index()
    {
        $data = $this->request->param();

        $couponid=$data['couponid'] ?? '0';

        $map=[];
        $map[]=['couponid','=',$couponid];

        $info=Db::name('coupon')->where('id',$couponid)->find();

        if($info['isfixed']==1){
            $info['fixed_start_t']=date('Y-m-d H:i',$info['fixed_start']);
            $info['fixed_end_t']=date('Y-m-d H:i',$info['fixed_end']);
        }

        $where2=[
            ['status','=',2],
            ['couponid','=',$couponid],
        ];
        $order_total=Db::name('orders')->field('count(*) as nums,sum(money_total) as total,sum(discount_money) as discount')->where($where2)->find();
        $total=0;
        $discount=0;
        $nums=0;
        if($order_total['nums']){
            $total=$order_total['total'];
            $discount=$order_total['discount'];
            $nums=$order_total['nums'];
        }
        $fei='0';
        $kedan='0';
        if($nums>0){
            $fei=floor($discount/$total*10000)*0.01;
            $kedan=floor($total/$nums*100)*0.01;
        }

        $user_nums=Db::name('coupon_user')->where('couponid',$couponid)->group('uid')->count();
        $user_use_nums=Db::name('coupon_user')->where([['couponid','=',$couponid],['usetime','<>',0]])->group('uid')->count();

        $rate=[
            'total'=>$total,
            'discount'=>$discount,
            'nums'=>$nums,
            'fei'=>$fei,
            'kedan'=>$kedan,
            'user_nums'=>$user_nums,
            'user_use_nums'=>$user_use_nums,
        ];

        $nowtime=time();

        $status=$data['status'] ?? '';
        if($status!=''){
            if($status==1){
                $map[]=['usetime','=',0];
                $map[]=['endtime','>',$nowtime];
            }
            if($status==2){
                $map[]=['usetime','<>',0];
            }
            if($status==3){
                $map[]=['usetime','=',0];
                $map[]=['endtime','<',$nowtime];
            }

        }


        $list = Db::name('coupon_user')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k)use($nowtime){
            $userinfo=getUserInfo($v['uid']);
            $v['userinfo']=$userinfo;

            $v['add_time']=date('Y-m-d H:i',$v['addtime']);

            $status='1';
            $status_t='已领取';
            if($v['endtime']<=$nowtime){
                $status_t='已作废';
                $status='3';
            }
            $use_time='--';
            if($v['usetime']!=0){
                $status='2';
                $status_t='已使用';
                $use_time=date('Y-m-d H:i',$v['usetime']);
            }

            $v['use_time']=$use_time;
            $v['status']=$status;
            $v['status_t']=$status_t;

            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        $this->assign('info', $info);
        $this->assign('rate', $rate);
        $this->assign('couponid', $couponid);

        return $this->fetch();
    }


    public function setStatus()
    {
        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->param('status', 0, 'intval');
        $nowtime=time();
        $where=[
            ['id','=',$id],
        ];

        $up['endtime']=$nowtime;


        $rs = DB::name('coupon_user')->where($where)->update($up);
        if($rs===false){
            $this->error("操作失败！");
        }

        $this->success("操作成功！");
    }

}