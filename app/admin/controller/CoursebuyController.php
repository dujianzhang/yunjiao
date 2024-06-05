<?php

/* 课程购买记录 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class CoursebuyController extends AdminBaseController
{

    public function index()
    {
        
        $data = $this->request->param();
        $map=[];
        
        $map[]=['status','=',1];
        
        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $map[]=['courseid','=',$courseid];
        
        
        $courseinfo=Db::name("course")
                ->where(['id'=>$courseid])
                ->find();
        if($courseinfo){
            $courseinfo['thumb']=get_upload_path($courseinfo['thumb']);
            $type_a='免费';
            if($courseinfo['paytype']==1){
                $type_a='￥'.$courseinfo['payval'];
            }
            if($courseinfo['paytype']==2){
                $type_a='密码';
            }
            $courseinfo['type_a']=$type_a;
        }
        $this->assign('courseinfo', $courseinfo);
        
        
        $start_time=isset($data['start_time']) ? $data['start_time']: '';
        $end_time=isset($data['end_time']) ? $data['end_time']: '';
        
        if($start_time!=""){
           $map[]=['paytime','>=',strtotime($start_time)];
        }

        if($end_time!=""){
           $map[]=['paytime','<=',strtotime($end_time) + 60*60*24];
        }
        
        $uid=isset($data['uid']) ? $data['uid']: '';
        if($uid!=''){
			$map[]=['uid','=',$uid];
        }
        
        $nums=Db::name('course_users')->where($map)->count();
        $total=Db::name('course_users')->where($map)->sum('money');
        if(!$total){
            $total=0;
        }
        
        $list = Db::name('course_users')
            ->where($map)
            ->order("id desc")
            ->paginate(20);
        
		$list->each(function($v,$k){
            $v['userinfo']=getUserInfo($v['uid']);
            $v['paytime']=date('Y-m-d H:i:s',$v['paytime']);
            return $v;
        });
		
        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        $this->assign('nums', $nums);
        $this->assign('total', $total);

        if($courseinfo['sort']==1){
            return $this->fetch('index2');
        }else{
            return $this->fetch();
        }

    }

}