<?php

/* 处理订单 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class HandelordersController extends HomebaseController{

    /* 处理订单超时 自动取消 每秒一次*/
    public function handelOrders(){

        $nowtime=time();
        $addtime=$nowtime-30*60;
        $where=[];
        $where[]=['status','=',0];
        $where[]=['addtime','<=',$addtime];

        /* 课程订单 */
        ordersBack($where);

        /* 商品订单 */
        Db::name('shop_orders')->where($where)->update(['status'=>-1]);

        echo 'OK';
        exit;
    }
	

}