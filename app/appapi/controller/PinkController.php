<?php

/* 拼团 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class PinkController extends HomebaseController{

	public function index() {

        $data      = $this->request->param();
        $configpri=getConfigPri();
        $pinkid=$data['pinkid'] ?? '';

        $this->assign('uid', '');
        $this->assign('token', '');

        if(!$pinkid){
            $reason='信息错误';
            $this->assign('reason', $reason);
            return $this->fetch(':error');
        }


        $where=['id'=>$pinkid];
        $info=Db::name('pink_users')->where($where)->find();
        if(!$info){
            $reason='拼团信息错误';
            $this->assign('reason', $reason);
            return $this->fetch(':error');
        }

        $nowtime=time();

        $length='0';
        if($info['endtime']>$nowtime){
            $length=$info['endtime'] - $nowtime;
        }
        $info['length']=(string)$length;

        $info['nums']=$info['nums_total']-1;

        $name='';
        $price='0';
        $thumb='0';
        $type='套餐';

        if($info['ctype']==1){
            /* 套餐 */
            $where2=['id'=>$info['cid']];
            $pinfo=Db::name('course_package')->where($where2)->find();
            if($pinfo){
                $name=$pinfo['name'];
                $price=$pinfo['price'];
                $thumb=get_upload_path($pinfo['thumb']);
            }
        }else{
            $where2=[
                ['id','=',$info['cid']],
                ['status','>=',1],
            ];
            $pinfo=Db::name('course')->where($where2)->find();
            if($pinfo){
                $name=$pinfo['name'];
                $price=$pinfo['payval'];
                $thumb=get_upload_path($pinfo['thumb']);
                $type='直播';
                if($pinfo['sort']==0){
                    $type='内容';
                }
                if($pinfo['sort']==1){
                    $type='课程';
                }
            }
        }

        $info['name']=$name;
        $info['price']=$price;
        $info['thumb']=$thumb;
        $info['type']=$type;

        $status='1';
        if($info['endtime']>$nowtime){
            $status='2';
        }

        if($info['nums_no']==0){
            $status='3';
        }

        /* 检测拼团活动 */
        $where4=[
            ['type','=',$info['ctype']],
            ['cid','=',$info['cid']],
        ];
        $isexist=Db::name('pink')->where($where4)->find();
        if(!$isexist || $isexist['status']==2 || $isexist['endtime']<=$nowtime){
            $status='1';
        }

        if($status==1 || $status==3){
            $info['length']='0';
        }

        $ispink='0';
        if($ispink==0){
            if($status==1){
                $status='6';
            }
            if($status==2){
                $status='4';
            }
            if($status==3){
                $status='5';
            }
        }

        $info['status']=$status;

        $users=[];
        if($status!=0){
            $userinfo=getUserInfo($info['uid']);
            $users[]=[
                'user_nickname'=>$userinfo['user_nickname'],
                'avatar'=>$userinfo['avatar'],
            ];

            $where3=[
                ['pinkid','=',$info['id']],
                ['ishead','=',0],
                ['status','>',0],
            ];
            $list=Db::name('pink_users')->field('uid')->where($where3)->order('id asc')->select()->toArray();
            foreach ($list as $k=>$v){
                $userinfo=getUserInfo($v['uid']);
                $users[]=[
                    'user_nickname'=>$userinfo['user_nickname'],
                    'avatar'=>$userinfo['avatar'],
                ];
            }
        }

        $ismore='';
        if(count($users)>4){
            $ismore='more';
        }

        $info['userlist']=$users;

        $this->assign('ismore', $ismore);
        $this->assign('info', $info);

        $kouling='pinkid='.$pinkid;
        $kouling_t=string_encryption($kouling);

        $this->assign('kouling_t', $kouling_t);

        return $this->fetch('index');
	}

}