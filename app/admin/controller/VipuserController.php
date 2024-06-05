<?php

/* VIP用户 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class VipuserController extends AdminBaseController
{

    public function index()
    {
        $data = $this->request->param();
        $map=[];

        $vipid=$data['vipid'] ?? 1;

        $map[]=['vipid','=',$vipid];

        $start_time=$data['start_time'] ?? '';
        $end_time=$data['end_time'] ?? '';

        if($start_time!=""){
            $map[]=['starttime','>=',strtotime($start_time)];
        }

        if($end_time!=""){
            $map[]=['starttime','<=',strtotime($end_time) + 60*60*24];
        }

        $start_time2=$data['start_time2'] ?? '';
        $end_time2=$data['end_time2'] ?? '';

        if($start_time2!=""){
            $map[]=['endtime','>=',strtotime($start_time2)];
        }

        if($end_time2!=""){
            $map[]=['endtime','<=',strtotime($end_time2) + 60*60*24];
        }

        $keyword=$data['keyword'] ?? '';
        if($keyword!=''){
            $uids=[];
            $uidall=Db::name('vip_user')->field('uid')->where($map)->select()->toArray();
            if($uidall){
                $uids_sel=array_column($uidall,'uid');

                $userlist=Db::name('users')->field('id')->where([['id','in',$uids_sel],['id|user_login|user_nickname|mobile','like','%'.$keyword.'%']])->select()->toArray();
                if($userlist){
                    $uids=array_column($userlist,'id');
                }
            }

            $map[]=['uid','in',$uids];
        }


        $list = Db::name('vip_user')
            ->where($map)
            ->order("starttime desc")
            ->paginate(20);

        $list->each(function ($v,$k){
            $v['userinfo']=getUserInfo($v['uid']);
            $v['start_time']=date('Y-m-d',$v['starttime']);
            $v['end_time']=date('Y-m-d',$v['endtime']);
            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('vipid', $vipid);
        $this->assign('list', $list);

        $nums=Db::name('vip_user')
            ->where($map)
            ->count();
        $this->assign('nums', $nums);

        return $this->fetch('index');
    }

    public function super()
    {
        return $this->index();
    }

    public function getUserList(){
        $data = $this->request->param();

        $keyword=$data['keyword'] ?? '';

        $map=[];
        //$map[]=['type','=',0];
        if($keyword!=''){
            $map[]=['user_nickname','like','%'.$keyword.'%'];
        }

        $list=Db::name('users')->field('id,user_nickname')->where($map)->order('id desc')->select()->toArray();

        $this->success('','',$list);
    }

    public function add()
    {
        $data = $this->request->param();

        $vipid=$data['vipid'] ?? 1;
        $this->assign('vipid', $vipid);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            
            $uid=$data['uid'] ?? 0;
            $vipid=$data['vipid'] ?? 1;

            if($uid == 0){
                $this->error('请选择会员');
            }
            $endtime=$data['endtime'] ?? 0;
            if($endtime==0){
                $this->error('请选择会员到期日');
            }
            $nowtime=time();

            $endtime=strtotime($endtime);
            $up=[
                'uid'=>$uid,
                'vipid'=>$vipid,
                'starttime'=>$nowtime,
                'endtime'=>$endtime,
            ];


            $isexist=DB::name('vip_user')->where(['uid'=>$uid,'vipid'=>$vipid])->find();
            if($isexist){
                if($isexist['endtime']>$nowtime){
                    $this->error('该用户已开通会员，无法添加');
                }
                $id = DB::name('vip_user')->where(['uid'=>$uid,'vipid'=>$vipid])->update($up);
            }else{
                $id = DB::name('vip_user')->insertGetId($up);
            }

            if(!$id){
                $this->error("添加失败！");
            }
            $this->resetcache($uid);
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('vip_user')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        $userinfo=getUserInfo($data['uid']);
        
        $this->assign('userinfo', $userinfo);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];
            $uid=$data['uid'];

            $endtime=$data['endtime'] ?? 0;
            if($endtime==0){
                $this->error('请选择会员到期日');
            }

            $data['endtime']=strtotime($endtime);

            $rs = DB::name('vip_user')->update($data);
            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache($uid);
            $this->success("保存成功！");
        }
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $info=DB::name('vip_user')->where("id",$id)->find();
        if(!$info){
            $this->error("信息错误");
        }
        $up=[
            'endtime'=>time()
        ];
        $rs = DB::name('vip_user')->where('id',$id)->update($up);
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache($info['uid']);
        $this->success("删除成功！");
    }


    protected function resetcache($uid){
        $key='vip_'.$uid;
        delcache($key);
    }
}