<?php

/* 处理拼团 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class HandelpinkController extends HomebaseController{

    /* 处理拼团超时 自动取消 每分钟一次*/
    public function handelPink(){

        $nowtime=time();
        $where=[];
        $where[]=['status','=',1];
        $where[]=['ishead','=',1];
        $where[]=['nums_no','<>',0];
        $where[]=['endtime','<=',$nowtime];

        $list=Db::name("pink_users")->where($where)->select()->toArray();
        foreach ($list as $k=>$v){
            $where2=['ordertype'=>1,'pinkid'=>$v['id'],'status'=>1];
            ordersBack($where2,1);
        }

        echo 'OK';
        exit;
    }
	

}