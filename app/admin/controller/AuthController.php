<?php

/* 认证 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class AuthController extends AdminBaseController
{
    /* 状态 */
    protected function getStatus($k=''){
        $status=array(
            '0'=>'待审核',
            '1'=>'已通过',
            '2'=>'已拒绝',
        );
        if($k===''){
            return $status;
        }

        return isset($status[$k]) ? $status[$k]: '';
    }

    public function index()
    {
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
            $map[]=['name|mobile|cer_no','=',$keyword];
        }

        $status_a=$this->getStatus();

        $list = Db::name('users_auth')
            ->where($map)
            ->order("addtime desc")
            ->paginate(20);
        $list->each(function ($v,$k)use($status_a){

            $v['mobile']=m_s($v['mobile']);
            $v['cer_no']=m_s($v['cer_no']);

            $v['userinfo']=getUserInfo($v['uid']);
            $v['status_t']=$status_a[$v['status']]??'';
            $v['add_t']=date('Y-m-d H:i',$v['addtime']);
            return $v;
        });

        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        $this->assign('status', $status_a);

        return $this->fetch();
    }

    public function edit()
    {
        $uid   = $this->request->param('uid', 0, 'intval');
        
        $data=Db::name('users_auth')
            ->where("uid={$uid}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $userinfo=getUserInfo($data['uid']);

        $data['mobile']=m_s($data['mobile']);
        $data['cer_no']=m_s($data['cer_no']);

        $this->assign('data', $data);
        $this->assign('userinfo', $userinfo);
        $status_a=$this->getStatus();
        $this->assign('status', $status_a);

        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $status=$data['status'];
            $uid=$data['uid'];

            if($status=='0'){
                //$this->success("修改成功！");
            }

            $data['uptime']=time();
            $rs = DB::name('users_auth')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }


            $isagent=0;
            $istips=0;
            if($status==1){
                $isagent=1;
                $istips=1;
            }
            $agent=Db::name('agent_code')->where('uid',$uid)->find();
            if($agent['isagent']==1 && $agent['istips']==0){
                $istips=0;
            }

            Db::name('agent_code')->where('uid',$uid)->update(['isagent'=>$isagent,'istips'=>$istips]);

            $this->success("保存成功！");
        }
    }


    public function del()
    {
        $uid = $this->request->param('uid', 0, 'intval');

        $rs = DB::name('users_auth')->where('uid',$uid)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        Db::name('agent_code')->where('uid',$uid)->update(['isagent'=>0,'istips'=>0]);
        $this->success("删除成功！");
    }

}