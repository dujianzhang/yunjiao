<?php

/* 表扬统计 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class PraiseController extends HomebaseController{

	public function index() {

        $data = $this->request->param();
        $uid=isset($data['uid']) ? $data['uid']: '';
        $token=isset($data['token']) ? $data['token']: '';
        $uid=(int)checkNull($uid);
        $token=checkNull($token);

        $this->assign('uid', $uid);
        $this->assign('token', $token);

        $checkToken=checkToken($uid,$token);
        if($checkToken==700){
            $reason='您的登陆状态失效，请重新登陆！';
            $this->assign('reason', $reason);
            return $this->fetch(':error');
        }

        Db::name('praise_new')->where('uid',$uid)->update(['nums'=>0]);

        $type=[
            '1'=>'点赞',
            '2'=>'666',
            '3'=>'小红星',
            '4'=>'鼓掌',
        ];

        $list=Db::name('praise')->where('uid',$uid)->order('type asc')->select()->toArray();
        foreach ($list as $k=>$v){
            $v['name']=$type[$v['type']]??'';
            $list[$k]=$v;
        }

        $this->assign('list', $list);

        return $this->fetch();
	}
	

}