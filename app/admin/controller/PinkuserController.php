<?php

/* 拼团-用户 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class PinkuserController extends AdminBaseController
{

    public function index()
    {
        $data = $this->request->param();

        $nowtime=time();

        $pid=$data['pid'] ?? '0';

        $map=[];
        $map[]=['ishead','=',1];
        $map[]=['pinkpid','=',$pid];
        $map[]=['status','>=',1];

        $info=Db::name('pink')->where('id',$pid)->find();

        $info['start_time']=date('Y-m-d H:i',$info['starttime']);
        $info['end_time']=date('Y-m-d H:i',$info['endtime']);

        $name='已删除';
        $thumb='';
        if($info['type']==1){
            $pinfo=Db::name('course_package')->field('id,name,thumb')->where('id',$info['cid'])->find();
            if($pinfo){
                $name=$pinfo['name'];
                $thumb=get_upload_path($pinfo['thumb']);
            }
        }else{
            $cinfo=Db::name('course')->field('id,name,thumb')->where('id',$info['cid'])->find();
            if($cinfo){
                $name=$cinfo['name'];
                $thumb=get_upload_path($cinfo['thumb']);
            }
        }

        $info['c_name']=$name;
        $info['c_thumb']=$thumb;
        $status_t='进行中';
        $s='1';
        if($info['starttime'] > $nowtime){
            $status_t='未开始';
            $s='0';
        }
        if($info['endtime'] <= $nowtime){
            $status_t='已结束';
            $s='2';
        }
        $info['status_t']=$status_t;
        $info['status']=$s;

        $where2=[
            ['status','=',2],
            ['pinkpid','=',$pid],
        ];
        $order_total=Db::name('orders')->field('count(*) as nums,sum(money) as total')->where($where2)->find();
        $total=0;
        $nums=0;
        if($order_total['nums']){
            $total=$order_total['total'];
            $nums=$order_total['nums'];
        }

        $pay=Db::name('pink_users')->where($where2)->group('uid')->count();

        $rate=[
            'user'=>$info['nums_user'],
            'view'=>$info['nums_view'],
            'pay'=>$pay,
            'nums'=>$nums,
            'total'=>$total,
        ];



        $status=$data['status'] ?? '';
        if($status!=''){
            $map[]=['status','=',$status];
        }

        $keyword=$data['keyword'] ?? '';
        if($keyword!=''){
            $uids=[];
            $puids=Db::name('pink_users')->where($map)->group('uid')->column('uid');
            if($puids){
                $where3=[
                    ['id','in',$puids],
                    ['user_nickname','like',"%{$keyword}%"],
                ];
                $uids=Db::name('users')->where($where3)->column('id');
            }
            $map[]=['uid','in',$uids];
        }

        $list = Db::name('pink_users')
            ->where($map)
            ->order("id desc")
            ->paginate(20);
        $list->each(function ($v,$k)use($nowtime){
            $userinfo=getUserInfo($v['uid']);
            $v['userinfo']=$userinfo;

            $v['add_time']=date('Y-m-d H:i',$v['addtime']);
            $v['end_time']=date('Y-m-d H:i',$v['endtime']);

            $status_t='待拼团';
            if($v['status']==2){
                $status_t='拼团成功';
            }
            if($v['status']==3){
                $status_t='拼团失败';
            }

            $v['status_t']=$status_t;

            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        $this->assign('info', $info);
        $this->assign('rate', $rate);
        $this->assign('pid', $pid);

        return $this->fetch();
    }


    public function index2()
    {
        $data = $this->request->param();

        $pinkid=$data['pinkid'] ?? '0';

        $map=[];
        $map[]=['pinkid','=',$pinkid];
        $map[]=['status','>=',1];

        $list = Db::name('pink_users')
            ->where($map)
            ->order("id asc")
            ->select();

        $list->each(function ($v,$k){
            $userinfo=getUserInfo($v['uid']);
            $v['userinfo']=$userinfo;
            $v['add_time']=date('Y-m-d H:i',$v['addtime']);

            return $v;
        });


        $this->assign('list', $list);

        return $this->fetch();
    }


}