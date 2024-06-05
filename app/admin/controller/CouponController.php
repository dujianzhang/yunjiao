<?php

/* 优惠券 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class CouponController extends AdminBaseController
{

    protected function getTypes($k=''){
        $status=[
            '1'=>'满减券',
            '2'=>'折扣券',
        ];

        if($k===''){
            return $status;
        }
        return isset($status[$k])? $status[$k] : '' ;
    }
    public function upStatus(){
        $nowtime=time();
        $where=[
            ['status','=',1],
            ['use_end','<>',0],
            ['use_end','<=',$nowtime],
        ];
        Db::name('coupon')->where($where)->update(['status'=>2]);
    }
    public function index()
    {
        $this->upStatus();
        $data = $this->request->param();
        $map=[];

        $nowtime=time();

        $status=$data['status'] ?? '';
        if($status!=''){
            if($status==0){
                $map[]=['status','=',0];
            }
            if($status==1){
                $map[]=['status','=',1];
                $map[]=['use_start',['=',0],['<=',$nowtime],'or'];
                $map[]=['use_end',['=',0],['>',$nowtime],'or'];
            }
            if($status==2){
                $map[]=['status','=',2];
            }
        }

        $keyword=$data['keyword'] ?? '';
        if($keyword!=''){
            $map[]=['name','like',"%{$keyword}%"];
        }

        $types=$this->getTypes();

        $list = Db::name('coupon')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k)use($types,$nowtime){

            $limit_val=$v['limit_val'];
            if($limit_val==(int)$limit_val){
                $limit_val=(int)$limit_val;
            }
            $v['limit_val']=$limit_val;

            $limit=$v['limit_money'];
            if($v['type']==2){
                $limit=$v['limit_rate'];
            }
            if($limit==(int)$limit){
                $limit=(int)$limit;
            }
            $v['limit']=(string)$limit;

            $v['type_t']=$types[$v['type']] ?? '';

            $v['nums_ok']=$v['nums_total'] - $v['nums'];

            if($v['use_type']==1){
                if($v['status']==1 && $v['use_end'] <= $nowtime){
                    $v['status']=2;
                }
            }


            $text='';
            if($v['limit_type']==1){
                $text='满'.$v['limit_val'];
                if($v['type']==1){
                    $text.='减'.$v['limit'];
                }
                if($v['type']==2){
                    $text.='享'.$v['limit'].'折';
                }
            }else{
                if($v['type']==1){
                    $text=$v['limit'].'元优惠券';
                }
                if($v['type']==2){
                    $text.=$v['limit'].'折优惠券';
                }
            }

            $v['text']=$text;

            $use_tips='领取后'.$v['use_lenth'].'天内可用';
            if($v['use_type']==1){
                $use_tips='使用时间：'.date('Y-m-d H:i',$v['use_start']).'~'.date('Y-m-d H:i',$v['use_end']);
            }
            $v['use_tips']=$use_tips;
            $where2=[
                ['couponid','=',$v['id']],
                ['usetime','<>',0],
            ];
            $nums_use=Db::name('coupon_user')->where($where2)->count();
            $v['nums_use']=$nums_use;

            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);

        $this->assign('list', $list);

        return $this->fetch();
    }


    public function add()
    {
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $name=$data['name'] ?? '';
            if($name==''){
                $this->error('请填写优惠券名称');
            }

            $type=$data['type'] ?? 0;
            if($type==0){
                $this->error('请选择优惠券类型');
            }

            $limit_type=$data['limit_type'] ?? 0;
            $limit_val=$data['limit_val'] ?? 0;
            if($limit_type==1){
                if($limit_val<=0){
                    $this->error('请填写正确的满足金额');
                }

            }else{
                $limit_val=0;
            }

            $limit_money=$data['limit_money'] ?? 0;
            $limit_rate=$data['limit_rate'] ?? 0;

            if($type==1){
                if($limit_money<=0){
                    $this->error('请填写正确的满减金额');
                }
            }else{
                $limit_money=0;
            }
            if($type==2){
                if($limit_rate<=0){
                    $this->error('请填写正确的折扣');
                }
            }else{
                $limit_rate=0;
            }

            $nums=$data['nums'] ?? 0;
            if($nums<=0){
                $this->error('请填写正确的发行量');
            }

            $nowtime=time();

            $use_type=$data['use_type'] ?? 0;
            $use_start=$data['use_start'] ?? '';
            $use_end=$data['use_end'] ?? '';
            $use_lenth=$data['use_lenth'] ?? 0;
            if($use_type==1){
                if($use_start=='' || $use_end==''){
                    $this->error('请选择使用时间');
                }
                $use_start=strtotime($use_start);
                $use_end=strtotime($use_end) + 60*60*24;
                if($use_start<$nowtime){
                    $this->error('使用时间不能早于当前时间');
                }
                if($use_start>=$use_end){
                    $this->error('请选择正确的使用时间');
                }
            }else{
                $use_start=0;
                $use_end=0;
            }

            if($use_type==2){
                if($use_lenth<=0){
                    $this->error('请填写使用天数');
                }
            }else{
                $use_lenth=0;
            }


            $insert=[
                'name'=>$name,
                'type'=>$type,
                'limit_type'=>$limit_type,
                'limit_val'=>$limit_val,
                'limit_rate'=>$limit_rate,
                'limit_money'=>$limit_money,
                'nums'=>$nums,
                'nums_total'=>$nums,
                'isall'=>1,
                'cids'=>'',
                'pids'=>'',
                'use_type'=>$use_type,
                'use_start'=>$use_start,
                'use_end'=>$use_end,
                'use_lenth'=>$use_lenth,
                'addtime'=>$nowtime,
                'fixed_cids'=>'',
                'fixed_pids'=>'',
            ];

            $id = DB::name('coupon')->insert($insert);

            if(!$id){
                $this->error("添加失败！");
            }
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');

        $data=Db::name('coupon')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        if($data['type']==2){
            $data['limit_money']='';
        }else{
            $data['limit_rate']='';
        }

        if($data['limit_type']==0){
            $data['limit_val']='';
        }


        if($data['use_type']==1){
            $data['use_start']=date('Y-m-d H:i:s',$data['use_start']);
            $data['use_end']=date('Y-m-d H:i:s',$data['use_end']);
            $data['use_lenth']='';
        }else{
            $data['use_start']='';
            $data['use_end']='';
        }

        $this->assign('data', $data);

        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'] ?? 0;
            $name=$data['name'] ?? '';
            if($name==''){
                $this->error('请填写优惠券名称');
            }

            $type=$data['type'] ?? 0;
            if($type==0){
                $this->error('请选择优惠券类型');
            }

            $limit_type=$data['limit_type'] ?? 0;
            $limit_val=$data['limit_val'] ?? 0;
            if($limit_type==1){
                if($limit_val<=0){
                    $this->error('请填写正确的满足金额');
                }
            }else{
                $limit_val=0;
            }

            $limit_money=$data['limit_money'] ?? 0;
            $limit_rate=$data['limit_rate'] ?? 0;
            if($type==1){
                if($limit_money<=0){
                    $this->error('请填写正确的满减金额');
                }
            }else{
                $limit_money=0;
            }
            if($type==2){
                if($limit_rate<=0){
                    $this->error('请填写正确的折扣');
                }
            }else{
                $limit_rate=0;
            }

            $nums=$data['nums'] ?? 0;
            if($nums<=0){
                $this->error('请填写正确的发行量');
            }

            $nowtime=time();

            $use_type=$data['use_type'] ?? 0;
            $use_start=$data['use_start'] ?? '';
            $use_end=$data['use_end'] ?? '';
            $use_lenth=$data['use_lenth'] ?? 0;
            if($use_type==1){
                if($use_start=='' || $use_end==''){
                    $this->error('请选择使用时间');
                }
                $use_start=strtotime($use_start);
                $use_end=strtotime($use_end);
                if($use_start<$nowtime){
                    $this->error('使用时间不能早于当前时间');
                }
                if($use_start>=$use_end){
                    $this->error('请选择正确的使用时间');
                }
            }else{
                $use_start=0;
                $use_end=0;
            }

            if($use_type==2){
                if($use_lenth<=0){
                    $this->error('请填写使用天数');
                }
            }else{
                $use_lenth=0;
            }


            $insert=[
                'name'=>$name,
                'type'=>$type,
                'limit_type'=>$limit_type,
                'limit_val'=>$limit_val,
                'limit_rate'=>$limit_rate,
                'limit_money'=>$limit_money,
                'nums'=>$nums,
                'nums_total'=>$nums,
                'isall'=>1,
                'cids'=>'',
                'pids'=>'',
                'use_type'=>$use_type,
                'use_start'=>$use_start,
                'use_end'=>$use_end,
                'use_lenth'=>$use_lenth,
            ];

            $rs = DB::name('coupon')->where('id',$id)->update($insert);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->success("保存成功！");
        }
    }

    public function setStatus()
    {
        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->param('status', 0, 'intval');
        $nowtime=time();
        $where=[
            ['id','=',$id],
        ];
        $up=[
            'status'=>$status,
        ];
        if($status==1){
            $up['releasetime']=$nowtime;
        }
        if($status==2){
            $up['endtime']=$nowtime;
        }

        $rs = DB::name('coupon')->where($where)->update($up);
        if($rs===false){
            $this->error("操作失败！");
        }
        $this->success("操作成功！");
    }

    public function setFixed()
    {

        $data      = $this->request->param();
        $id=$data['id'] ?? 0;
        $fixed_type=$data['fixed_type'] ?? 0;
        $fixed_start=$data['fixed_start'] ?? '';
        $fixed_end=$data['fixed_end'] ?? '';
        $cids=$data['cids'] ?? [];
        $pids=$data['pids'] ?? [];


        if($id==0){
            $this->error("信息错误！");
        }
        if($fixed_type==3){
            if(!$cids && !$pids){
                $this->error("请选择指定商品");
            }
        }

        if($fixed_start=='' || $fixed_end==''){
            $this->error("请选择时间段");
        }
        $fixed_start=strtotime($fixed_start);
        $fixed_end=strtotime($fixed_end) + 60*60*24;
        if($fixed_start>=$fixed_end){
            $this->error("请选择正确的时间段");
        }

        $nowtime=time();
        $up=[
            'isfixed'=>1,
            'fixed_type'=>$fixed_type,
            'fixed_start'=>$fixed_start,
            'fixed_end'=>$fixed_end,
            'fixed_cids'=>handelSetToStr($cids),
            'fixed_pids'=>handelSetToStr($pids),
            'status'=>1,
            'releasetime'=>$nowtime,
        ];
        $where=['id'=>$id];
        $rs = DB::name('coupon')->where($where)->update($up);
        if($rs===false){
            $this->error("操作失败！");
        }

        /* 处理信息 */
        if($fixed_start >= $nowtime){
            $this->success("操作成功！");
        }
        $insert_all=[];
        $info=DB::name('coupon')->where($where)->find();
        if(!$info){
            $this->success("操作成功！");
        }

        if($info['isfixed']!=1){
            $this->success("操作成功！");
        }

        $endtime=$info['use_end'];
        if($info['use_type']==2){
            $endtime=$nowtime + $info['use_lenth'] * 60*60*24;
        }

        $where3=[
            ['id','=',$info['id']],
            ['nums','>=',1],
        ];
        if($fixed_type==1){
            $where2=[
                ['create_time','>=',$fixed_start],
                ['create_time','<',$fixed_end],
            ];
            $list=Db::name('users')->field('id')->where($where2)->order('id asc')->select()->toArray();
            foreach ($list as $k=>$v){

                $isok=DB::name('coupon')->where($where3)->dec('nums',1)->update();
                if(!$isok){
                    break;
                }

                $insert=[
                    'uid'=>$v['id'],
                    'couponid'=>$info['id'],
                    'endtime'=>$endtime,
                    'addtime'=>$nowtime,
                    'name'=>$info['name'],
                    'type'=>$info['type'],
                    'limit_type'=>$info['limit_type'],
                    'limit_val'=>$info['limit_val'],
                    'limit_rate'=>$info['limit_rate'],
                    'limit_money'=>$info['limit_money'],
                    'isall'=>$info['isall'],
                    'cids'=>$info['cids'],
                    'pids'=>$info['pids'],
                    'isnotice'=>1,
                ];

                $insert_all[]=$insert;
            }
        }
        if($fixed_type==2){
            $where2=[
                ['status','=',2],
                ['money','>',0],
                ['paytime','>=',$fixed_start],
                ['paytime','<',$fixed_end],
            ];
            $list=Db::name('orders')->field('uid')->where($where2)->group('uid')->order('paytime asc')->select()->toArray();
            foreach ($list as $k=>$v){

                $isok=DB::name('coupon')->where($where3)->dec('nums',1)->update();
                if(!$isok){
                    break;
                }

                $insert=[
                    'uid'=>$v['uid'],
                    'couponid'=>$info['id'],
                    'endtime'=>$endtime,
                    'addtime'=>$nowtime,
                    'name'=>$info['name'],
                    'type'=>$info['type'],
                    'limit_type'=>$info['limit_type'],
                    'limit_val'=>$info['limit_val'],
                    'limit_rate'=>$info['limit_rate'],
                    'limit_money'=>$info['limit_money'],
                    'isall'=>$info['isall'],
                    'cids'=>$info['cids'],
                    'pids'=>$info['pids'],
                    'isnotice'=>1,
                ];

                $insert_all[]=$insert;
            }
        }
        if($fixed_type==3){
            $cids=handelSetToArr($info['cids']);
            if($cids){
                $where2=[
                    ['money','>',0],
                    ['paytime','>=',$fixed_start],
                    ['paytime','<',$fixed_end],
                    ['courseid','in',$cids],
                ];
                $list=Db::name('course_users')->field('uid')->where($where2)->group('uid')->order('paytime asc')->select()->toArray();
                foreach ($list as $k=>$v){

                    $isok=DB::name('coupon')->where($where3)->dec('nums',1)->update();
                    if(!$isok){
                        break;
                    }

                    $insert=[
                        'uid'=>$v['uid'],
                        'couponid'=>$info['id'],
                        'endtime'=>$endtime,
                        'addtime'=>$nowtime,
                        'name'=>$info['name'],
                        'type'=>$info['type'],
                        'limit_type'=>$info['limit_type'],
                        'limit_val'=>$info['limit_val'],
                        'limit_rate'=>$info['limit_rate'],
                        'limit_money'=>$info['limit_money'],
                        'isall'=>$info['isall'],
                        'cids'=>$info['cids'],
                        'pids'=>$info['pids'],
                        'isnotice'=>1,
                    ];

                    $insert_all[]=$insert;
                }
            }

            $pids=handelSetToArr($info['pids']);
            if($pids){
                $where2=[
                    ['paytime','>=',$fixed_start],
                    ['paytime','<',$fixed_end],
                    ['packageid','in',$pids],
                ];
                $list=Db::name('course_package_users')->field('uid')->where($where2)->group('uid')->order('paytime asc')->select()->toArray();
                foreach ($list as $k=>$v){
                    $ishas=0;
                    foreach ($insert_all as $k2=>$v2){
                        if($v2['uid']!=$v['uid']){
                            continue;
                        }
                        $ishas=1;
                        break;
                    }
                    if($ishas){
                        continue;
                    }

                    $isok=DB::name('coupon')->where($where3)->dec('nums',1)->update();
                    if(!$isok){
                        break;
                    }

                    $insert=[
                        'uid'=>$v['uid'],
                        'couponid'=>$info['id'],
                        'endtime'=>$endtime,
                        'addtime'=>$nowtime,
                        'name'=>$info['name'],
                        'type'=>$info['type'],
                        'limit_type'=>$info['limit_type'],
                        'limit_val'=>$info['limit_val'],
                        'limit_rate'=>$info['limit_rate'],
                        'limit_money'=>$info['limit_money'],
                        'isall'=>$info['isall'],
                        'cids'=>$info['cids'],
                        'pids'=>$info['pids'],
                        'isnotice'=>1,
                    ];

                    $insert_all[]=$insert;
                }
            }
        }

        if($insert_all){
            Db::name('coupon_user')->insertAll($insert_all);
        }

        $this->success("操作成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('coupon')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }


    public function getList(){
        $data = $this->request->param();

        $type=$data['type'] ?? '0';
        $keyword=$data['keyword'] ?? '';
        $map=[];

        if($type==1){
            $map[]=['sort','=',1];
            $map[]=['paytype','=',1];
            $map[]=['payval','>',0];
        }
        if($type==2){
            $map[]=['sort','>=',2];
            $map[]=['paytype','=',1];
            $map[]=['payval','>',0];
        }
        if($type==3){
            $map[]=['sort','=',0];
            $map[]=['paytype','=',1];
            $map[]=['payval','>',0];
        }
        $nowtime=time();

        if($keyword!=''){
            $map[]=['id|name','like','%'.$keyword.'%'];
        }
        if($type==0){
            $list=Db::name('course_package')->field('id,name,price')->where($map)->order('id desc')->select()->toArray();
            foreach ($list as $k=>$v){
                $v['price']=$v['price'].'元';
                $v['type_t']='精选套餐';
                $v['type_id']=$v['id'].'_0';
                $list[$k]=$v;
            }
        }else{
            $map[]=['status','>=',1];
            $map[]=['shelvestime','<=',$nowtime];

            $list=Db::name('course')->field('id,name,paytype,payval,views,sort')->where($map)->order('id desc')->select()->toArray();
            foreach ($list as $k=>$v){
                $price='免费';
                $type_t='直播课堂';
                if($v['paytype']==1){
                    $price=$v['payval'].'元';
                }
                if($v['sort']==0){
                    $type_t='精选内容';
                }
                if($v['sort']==1){
                    $type_t='好课推荐';
                }
                $v['price']=$price;
                $v['type_t']=$type_t;
                $v['type_id']=$v['id'].'_'.$v['sort'];

                $list[$k]=$v;
            }
        }

        $this->success('','',$list);
    }

}
