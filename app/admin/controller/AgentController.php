<?php

/* 推广 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class AgentController extends AdminBaseController
{


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

        $uid=isset($data['uid']) ? $data['uid']: '';
        if($uid!=''){
            $map[]=['uid','=',$uid];
        }

        $pid=isset($data['pid']) ? $data['pid']: '';
        if($pid!=''){
            $map[]=['pid','=',$pid];
        }

        $list = Db::name('agent')
            ->where($map)
            ->order("addtime desc")
            ->paginate(20);
        $list->each(function ($v,$k){

            $v['userinfo']=getUserInfo($v['uid']);

            $v['pidinfo']=getUserInfo($v['pid']);

            $v['add_t']=date('Y-m-d H:i',$v['addtime']);
            return $v;
        });

        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        return $this->fetch();
    }


    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('agent')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        $this->success("删除成功！");
    }

}