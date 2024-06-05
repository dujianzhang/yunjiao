<?php

/* 提现 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class CashController extends AdminBaseController
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

        return $status[$k] ?? '';
    }

    /* 账号类型 */
    protected function getTypes($k=''){
        $type=array(
            '1'=>'支付宝',
            '2'=>'微信',
            '3'=>'银行卡',
        );
        if($k===''){
            return $type;
        }

        return $type[$k] ?? '';
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
            $map[]=['orderno|trade_no','like',"%".$keyword."%"];
        }

        $status_a=$this->getStatus();
        $type_a=$this->getTypes();

        $list = Db::name('cash_record')
            ->where($map)
            ->order("addtime desc")
            ->paginate(20);
        $list->each(function ($v,$k)use($status_a,$type_a){

            $v['userinfo']=getUserInfo($v['uid']);
            $v['status_t']=$status_a[$v['status']]??'';
            $v['type_t']=$type_a[$v['type']]??'';
            $v['add_t']=date('Y-m-d H:i',$v['addtime']);
            $up_t='--';
            if($v['status']!=0){
                $up_t=date('Y-m-d H:i',$v['uptime']);
            }
            $v['up_t']=$up_t;
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
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('cash_record')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $userinfo=getUserInfo($data['uid']);

        $data['type_t']=$this->getTypes($data['type']);

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

            $id=$data['id'] ?? 0;
            $status=$data['status'] ?? 0;

            $nowtime=time();
            $data['uptime']=$nowtime;

            $rs = DB::name('cash_record')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }

            /* 拒绝 */
            if($status==2){
                $info=DB::name('cash_record')->where('id',$id)->find();

                DB::name('users')->where('id',$info['uid'])->inc('coin',$info['money'])->update();
                $record=[
                    'type'=>'1',
                    'action'=>'5',
                    'uid'=>$info['uid'],
                    'actionid'=>$info['id'],
                    'nums'=>'1',
                    'total'=>$info['money'],
                    'addtime'=>$nowtime,
                ];
                DB::name('users_coinrecord')->insert($record);
            }

            $this->success("保存成功！");
        }
    }


    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('cash_record')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        $this->success("删除成功！");
    }

}