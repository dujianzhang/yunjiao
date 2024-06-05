<?php

/* 拼团 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class PinkController extends AdminBaseController
{

    public function index()
    {
        $data = $this->request->param();
        $map=[];

        $nowtime=time();

        $type=$data['type'] ?? '';
        if($type!=''){
            if($type==0){
                $map[]=['type','=',1];
            }else{
                $map[]=['type','=',0];
            }
            if($type==1){
                $map[]=['sort','=',1];
            }
            if($type==2){
                $map[]=['sort','>=',2];
            }
            if($type==3){
                $map[]=['sort','=',0];
            }
        }

        $status=$data['status'] ?? '';
        if($status!=''){
            if($status==0){
                $map[]=['status','=',0];
            }
            if($status==1){
                $map[]=['status','=',1];
                $map[]=['starttime','<=',$nowtime];
                $map[]=['endtime','>',$nowtime];
            }
            if($status==2){
                $map[]=['status','<>',0];
                $map[]=['endtime','<',$nowtime];
            }
            if($status==3){
                $map[]=['status','=',1];
                $map[]=['starttime','>',$nowtime];
            }
        }

        $keyword=$data['keyword'] ?? '';
        if($keyword!=''){
            $ids=[];
            $vlist=Db::name('pink')->field('id,cid,type')->where($map)->select()->toArray();
            if($vlist){
                $cids=[];
                $pids=[];
                foreach ($vlist as $k=>$v){
                    if($v['type']==1){
                        $pids[]=$v['cid'];
                    }else{
                        $cids[]=$v['cid'];
                    }
                }

                $clist=Db::name('course')->field('id')->where([['id','in',$cids],['id|name','like','%'.$keyword.'%']])->select()->toArray();
                foreach ($clist as $k=>$v){
                    foreach ($vlist as $k2=>$v2){
                        if($v2['type']==1){
                            continue;
                        }
                        if($v2['cid']!=$v['id']){
                            continue;
                        }
                        $ids[]=$v2['id'];
                        break;
                    }
                }

                $plist=Db::name('course_package')->field('id')->where([['id','in',$pids],['id|name','like','%'.$keyword.'%']])->select()->toArray();
                foreach ($plist as $k=>$v){
                    foreach ($vlist as $k2=>$v2){
                        if($v2['type']==0){
                            continue;
                        }
                        if($v2['cid']!=$v['id']){
                            continue;
                        }
                        $ids[]=$v2['id'];
                        break;
                    }
                }
            }

            $map[]=['id','in',$ids];
        }

        $list = Db::name('pink')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k)use($nowtime){
            $name='已删除';
            $price='';
            $isdel='1';
            $type_t='精选套餐';
            $type=$v['type'];
            $sort=$v['sort'];
            if($type==0){
                $type_t='直播课堂';
                if($sort==0){
                    $type_t='精选内容';
                }
                if($sort==1){
                    $type_t='好课推荐';
                }
            }

            if($v['type']==1){
                $pinfo=Db::name('course_package')->field('id,name,price')->where('id',$v['cid'])->find();
                if($pinfo){
                    $name=$pinfo['name'];
                    $price=$pinfo['price'];
                    $isdel='0';
                }
            }else{
                $cinfo=Db::name('course')->field('id,name,paytype,payval')->where('id',$v['cid'])->find();
                if($cinfo){
                    $name=$cinfo['name'];
                    $price='免费';
                    if($cinfo['paytype']==1){
                        $price=$cinfo['payval'];
                    }
                    $isdel='0';
                }
            }
            $v['name']=$name;
            $v['price']=$price;
            $v['type_t']=$type_t;
            $v['isdel']=$isdel;

            if($v['status']==1 && $v['endtime'] <= $nowtime){
                $v['status']=2;
            }

            if($v['status']==1 && $v['starttime'] > $nowtime){
                $v['status']=3;
            }

            $where2=[
                ['ishead','=',1],
                ['ctype','=',$type],
                ['cid','=',$v['cid']],
                ['status','>=',1],
            ];
            $total=Db::name('pink_users')->where($where2)->count();
            $v['total']=$total;
            $where3=[
                ['ishead','=',1],
                ['ctype','=',$type],
                ['cid','=',$v['cid']],
                ['status','=',2],
            ];
            $ok=Db::name('pink_users')->where($where3)->count();
            $v['ok']=$ok;

            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        return $this->fetch();
    }

    public function getLength(){
        $timed=[];
        $timeh=[];
        for($i=0;$i<=15;$i++){
            $d=$i;
            if($d<10){
                $d='0'.$d;
            }
            $timed[]=$d;
        }

        for($i=0;$i<24;$i++){
            $d=$i;
            if($d<10){
                $d='0'.$d;
            }
            $timeh[]=$d;
        }

        $this->assign('timed', $timed);
        $this->assign('timeh', $timeh);

    }
    public function add()
    {
        $this->getLength();
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $cid=$data['cid'] ?? 0;
            $type=$data['type'] ?? 0;
            $sort=$data['sort'] ?? 0;

            if(!$cid){
                $this->error('请选择商品');
            }

            if($type==0){
                $type=1;
            }else{
                $type=0;
            }
            $nowtime=time();

            $where=[
                ['type','=',$type],
                ['cid','=',$cid],
                ['sort','=',$sort],
                ['endtime','>',$nowtime]
            ];
            $isexist = DB::name('seckill')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置秒杀活动');
            }

            $isexist = DB::name('fissionposter')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置裂变海报');
            }

            $isexist = DB::name('pink')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置拼团活动');
            }

            $ptype=$data['ptype'] ?? 0;
            $price=$data['price'] ?? [];
            $cmoney=$data['cmoney'] ?? 0;
            $prices=[];
            $prices_order=[];

            if($ptype<1 || $ptype>2){
                $this->error('请选择拼团类型');
            }

            if($ptype==1){
                $num=$data['num'] ?? 0;
                if($num<2 || $num>100){
                    $this->error('请设置合理的人数');
                }
                if(!$price){
                    $this->error('请设置拼团价');
                }
                $money=$price[0] ?? 0;
                if($money<=0 || $money>$cmoney){
                    $this->error('请设置正确的拼团价');
                }
                $prices[]=[
                    'nums'=>$num,
                    'price'=>$money,
                ];
                $prices_order[]=$num;
            }
            if($ptype==2){
                $nums=$data['nums'] ?? [];
                if(!$nums){
                    $this->error('请设置人数');
                }

                if(!$price){
                    $this->error('请设置拼团价');
                }

                foreach ($nums as $k=>$v){
                    if($v<2 || $v>100){
                        $this->error('请设置合理的人数');
                    }
                    $money=$price[$k] ?? 0;
                    if($money<=0 || $money>$cmoney){
                        $this->error('请设置正确的拼团价');
                    }
                    $prices[]=[
                        'nums'=>$v,
                        'price'=>$money,
                    ];
                    $prices_order[]=$v;
                }
            }

            array_multisort($prices_order,SORT_ASC,$prices);

            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? 0;
            if($starttime=='' || $endtime==''){
                $this->error('请选择活动时间');
            }

            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);

            if($starttime<$nowtime){
                $this->error('活动时间不能早于当前时间');
            }
            if($starttime>=$endtime){
                $this->error('请选择正确的活动时间');
            }

            $time_d=$data['time_d'] ?? 0;
            $time_h=$data['time_h'] ?? 0;

            $length=$time_d*60*60*24 + $time_h*60*60;
            if($length<=0){
                $this->error('请选择正确的拼团有效期');
            }

            $insert=[
                'type'=>$type,
                'sort'=>$sort,
                'cid'=>$cid,
                'ptype'=>$ptype,
                'price'=>json_encode($prices),
                'starttime'=>$starttime,
                'endtime'=>$endtime,
                'length'=>$length,
                'addtime'=>$nowtime,
                'status'=>0,
            ];

            $id = DB::name('pink')->insert($insert);

            if(!$id){
                $this->error("添加失败！");
            }
            $this->resetcache();
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        $issee   = $this->request->param('issee', 0, 'intval');

        $data=Db::name('pink')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        $type=$data['type'];
        if($type==1){
            $info=Db::name('course_package')->field('id,name,thumb,price')->where('id',$data['cid'])->find();
            $info['thumb']=get_upload_path($info['thumb']);
        }else{
            $info=Db::name('course')->field('id,name,thumb,paytype,payval,views,sort')->where('id',$data['cid'])->find();
            $info['price']=$info['payval'];
            $info['thumb']=get_upload_path($info['thumb']);
        }

        if($type==0){
            $type=2;
            if($data['sort']==0){
                $type=3;
            }
            if($data['sort']==1){
                $type=1;
            }
        }else{
            $type=0;
        }
        $type_name=['精选套餐','好课推荐','直播课堂','精选内容'];
        $type_t=$type_name[$type] ?? '';

        $nums_name=['第一阶梯','第二阶梯','第三阶梯'];
        $prices=json_decode($data['price'],true);
        foreach ($prices as $k=>$v){
            $v['name']=$nums_name[$k] ?? '';
            $prices[$k]=$v;
        }

        $this->getLength();

        $length=$data['length'];

        $h=$length/(60*60);

        $time_h=$h%24;
        $time_d=floor($h/24);
        if($time_h<10){
            $time_h='0'.$time_h;
        }
        if($time_d<10){
            $time_d='0'.$time_d;
        }

        $this->assign('time_d', $time_d);
        $this->assign('time_h', $time_h);
        $this->assign('type', $type);
        $this->assign('type_t', $type_t);
        $this->assign('data', $data);
        $this->assign('prices', $prices);
        $this->assign('info', $info);
        $this->assign('issee', $issee);

        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];
            $cid=$data['cid'] ?? 0;
            $type=$data['type'] ?? 0;
            $sort=$data['sort'] ?? 0;

            if(!$cid){
                $this->error('请选择商品');
            }

            if($type==0){
                $type=1;
            }else{
                $type=0;
            }
            $nowtime=time();

            $where=[
                ['type','=',$type],
                ['cid','=',$cid],
                ['sort','=',$sort],
                ['endtime','>',$nowtime]
            ];
            $isexist = DB::name('seckill')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置秒杀活动');
            }

            $isexist = DB::name('fissionposter')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置裂变海报');
            }

            $where[]=['id','<>',$id];
            $isexist = DB::name('pink')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置拼团活动');
            }

            $ptype=$data['ptype'] ?? 0;
            $price=$data['price'] ?? [];
            $cmoney=$data['cmoney'] ?? 0;
            $prices=[];
            $prices_order=[];

            if($ptype<1 || $ptype>2){
                $this->error('请选择拼团类型');
            }

            if($ptype==1){
                $num=$data['num'] ?? 0;
                if($num<2 || $num>100){
                    $this->error('请设置合理的人数');
                }
                if(!$price){
                    $this->error('请设置拼团价');
                }
                $money=$price[0] ?? 0;
                if($money<=0 || $money>$cmoney){
                    $this->error('请设置正确的拼团价');
                }
                $prices[]=[
                    'nums'=>$num,
                    'price'=>$money,
                ];
                $prices_order[]=$num;
            }
            if($ptype==2){
                $nums=$data['nums'] ?? [];
                if(!$nums){
                    $this->error('请设置人数');
                }
                if(!$price){
                    $this->error('请设置拼团价');
                }

                foreach ($nums as $k=>$v){
                    if($v<2 || $v>100){
                        $this->error('请设置合理的人数');
                    }
                    $money=$price[$k] ?? 0;
                    if($money<=0 || $money>$cmoney){
                        $this->error('请设置正确的拼团价');
                    }
                    $prices[]=[
                        'nums'=>$v,
                        'price'=>$money,
                    ];
                    $prices_order[]=$v;
                }
            }

            array_multisort($prices_order,SORT_ASC,$prices);

            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? 0;
            if($starttime=='' || $endtime==''){
                $this->error('请选择活动时间');
            }

            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);

            if($starttime<$nowtime){
                $this->error('活动时间不能早于当前时间');
            }
            if($starttime>=$endtime){
                $this->error('请选择正确的活动时间');
            }

            $time_d=$data['time_d'] ?? 0;
            $time_h=$data['time_h'] ?? 0;

            $length=$time_d*60*60*24 + $time_h*60*60;
            if($length<=0){
                $this->error('请选择正确的拼团有效期');
            }

            $insert=[
                'type'=>$type,
                'sort'=>$sort,
                'cid'=>$cid,
                'ptype'=>$ptype,
                'price'=>json_encode($prices),
                'starttime'=>$starttime,
                'endtime'=>$endtime,
                'length'=>$length,
            ];

            $rs = DB::name('pink')->where('id',$id)->update($insert);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
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
            $where[]=['status','=',0];
        }
        if($status==2){
            $where[]=['status','<>',2];
            $where[]=['endtime','>',$nowtime];

            $up['endtime']=$nowtime;
        }

        $rs = DB::name('pink')->where($where)->update($up);
        if($rs===false){
            $this->error("操作失败！");
        }
        $this->resetcache();

        if($status!=2){
            $this->success("操作成功！");
        }

        /* 处理拼团中的订单 */
        $info=DB::name('pink')->where('id',$id)->find();

        $where=[];
        $where[]=['ishead','=',1];
        $where[]=['ctype','=',$info['type']];
        $where[]=['cid','=',$info['cid']];
        $where[]=['nums_no','<>',0];
        $where[]=['status','in',[0,1]];

        $list=Db::name("pink_users")->where($where)->select()->toArray();
        foreach ($list as $k=>$v){
            $where2=[
                ['ordertype','=',1],
                ['pinkid','=',$v['id']],
                ['status','in',[0,1]],
            ];
            ordersBack($where2);
        }

        $this->success("操作成功！");
    }
    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('pink')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    public function getList(){
        $data = $this->request->param();

        $type=$data['type'] ?? '0';
        $keyword=$data['keyword'] ?? '';
        $map=[];

        $nowtime=time();
        $type_c=0;
        if($type==0){
            $type_c=1;
        }
        $where=[
            ['type','=',$type_c],
            ['endtime','>',$nowtime],
        ];

        if($type==1){
            $where[]=['sort','=',1];
            $map[]=['sort','=',1];
            $map[]=['paytype','=',1];
            $map[]=['payval','>',0];
        }
        if($type==2){
            $where[]=['sort','>=',2];
            $map[]=['sort','>=',2];
            $map[]=['paytype','=',1];
            $map[]=['payval','>',0];
        }
        if($type==3){
            $where[]=['sort','=',0];
            $map[]=['sort','=',0];
            $map[]=['paytype','=',1];
            $map[]=['payval','>',0];
        }

        if($keyword!=''){
            $map[]=['id|name','like','%'.$keyword.'%'];
        }

        /* 去除参与其他活动的商品 */
        $pinkids=Db::name('pink')->where($where)->column('cid');
        $seckillids=Db::name('seckill')->where($where)->column('cid');
        $fissionids=Db::name('fissionposter')->where($where)->column('cid');
        $ids=array_merge($pinkids,$seckillids,$fissionids);
        if($ids){
            $ids=array_unique($ids);
            $map[]=['id','not in',$ids];
        }

        if($type==0){
            $list=Db::name('course_package')->field('id,name,thumb,price')->where($map)->order('id desc')->select()->toArray();
            foreach ($list as $k=>$v){
                $v['sort']=0;
                $v['money']=$v['price'];
                $v['price']=$v['price'].'元';
                $v['type_t']='精选套餐';
                $v['type_id']=$v['id'].'_0';
                $v['thumb']=get_upload_path($v['thumb']);

                $list[$k]=$v;
            }
        }else{
            $map[]=['status','>=',1];
            $map[]=['shelvestime','<=',$nowtime];

            $list=Db::name('course')->field('id,name,thumb,paytype,payval,views,sort')->where($map)->order('id desc')->select()->toArray();
            foreach ($list as $k=>$v){
                $price='免费';
                $money='0';
                $type_t='直播课堂';
                if($v['paytype']==1){
                    $money=$v['payval'];
                    $price=$v['payval'].'元';
                }
                if($v['sort']==0){
                    $type_t='精选内容';
                }
                if($v['sort']==1){
                    $type_t='好课推荐';
                }
                $v['money']=$money;
                $v['price']=$price;
                $v['type_t']=$type_t;
                $v['type_id']=$v['id'].'_'.$v['sort'];
                $v['thumb']=get_upload_path($v['thumb']);

                $list[$k]=$v;
            }
        }

        $this->success('','',$list);
    }

    protected function resetcache(){
        $key='pink';
        $nowtime=time();
        $list=DB::name('pink')
                ->where([['status','=',1],['endtime','>',$nowtime]])
                ->order("id desc")
                ->select()
                ->toArray();
        if($list){
            setcaches($key,$list);
        }else{
			delcache($key);
		}
    }
}