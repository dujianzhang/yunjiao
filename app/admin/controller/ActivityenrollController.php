<?php

/* 活动报名记录 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class ActivityenrollController extends AdminBaseController
{

    public function index()
    {
        
        $data = $this->request->param();
        $map=[];
        
        $map[]=['status','=',1];

        $aid=isset($data['aid']) ? $data['aid']: '0';
        $map[]=['aid','=',$aid];


        $start_time=isset($data['start_time']) ? $data['start_time']: '';
        $end_time=isset($data['end_time']) ? $data['end_time']: '';
        
        if($start_time!=""){
           $map[]=['addtime','>=',strtotime($start_time)];
        }

        if($end_time!=""){
           $map[]=['addtime','<=',strtotime($end_time) + 60*60*24];
        }
        
        $uid=isset($data['uid']) ? $data['uid']: '';
        if($uid!=''){
			$map[]=['uid','=',$uid];
        }

        $list = Db::name('activity_enroll')
            ->where($map)
            ->order("id desc")
            ->paginate(20);
        
		$list->each(function($v,$k){
            $v['userinfo']=getUserInfo($v['uid']);
            $v['add_time']=date('Y-m-d H:i:s',$v['addtime']);
            return $v;
        });
		
        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        return $this->fetch();


    }

}