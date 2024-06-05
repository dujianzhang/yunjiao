<?php

/* 套餐 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class PackageController extends HomebaseController{
    
    function content(){       
		$data = $this->request->param();
        $uid=isset($data['uid']) ? $data['uid']: '';
        $token=isset($data['token']) ? $data['token']: '';
        $packageid=isset($data['packageid']) ? $data['packageid']: '';
        $uid=(int)checkNull($uid);
        $packageid=(int)checkNull($packageid);
        $token=checkNull($token);
        
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        
        /*$checkToken=checkToken($uid,$token);
		if($checkToken==700){
			$reason='您的登陆状态失效，请重新登陆！';
			$this->assign('reason', $reason);
			return $this->fetch(':error');
		}*/
        
        if($packageid<1){
            $reason='信息错误';
			$this->assign('reason', $reason);
			return $this->fetch(':error');
        }
		

		$courseinfo=Db::name('course_package')->field('name,content')->where(["id"=>$packageid])->find();
        
		if(!$courseinfo){
            $reason='课程不存在';
			$this->assign('reason', $reason);
			return $this->fetch(':error');
        }

		$this->assign("title",$courseinfo['name']);
		$this->assign("body",$courseinfo['content']);

		return $this->fetch();
	    
	}


}