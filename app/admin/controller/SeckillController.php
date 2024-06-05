<?php

/* 秒杀 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class SeckillController extends AdminBaseController
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
            $vlist=Db::name('seckill')->field('id,cid,type')->where($map)->select()->toArray();
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

        $list = Db::name('seckill')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k)use($nowtime){
            $name='已删除';
            $price='';
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
                }
            }else{
                $cinfo=Db::name('course')->field('id,name,paytype,payval')->where('id',$v['cid'])->find();
                if($cinfo){
                    $name=$cinfo['name'];
                    $price='免费';
                    if($cinfo['paytype']==1){
                        $price=$cinfo['payval'];
                    }
                }
            }
            $v['name']=$name;
            $v['price']=$price;
            $v['type_t']=$type_t;
            $v['money_total']=$v['money']*$v['nums_ok'];
            if($v['status']==1 && $v['endtime'] <= $nowtime){
                $v['status']=2;
            }

            if($v['status']==1 && $v['starttime'] > $nowtime){
                $v['status']=3;
            }
            if($v['nums_total']==0){
                $v['nums_total']='不限量';
            }

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

            $cids=$data['cid'] ?? 0;
            $type=$data['type'] ?? 0;

            if(!$cids){
                $this->error('请选择商品');
            }

            if($type==0){
                $type=1;
            }else{
                $type=0;
            }
            $nowtime=time();

            $v_a=explode('_',$cids);
            $cid=$v_a[0] ?? 0;
            $sort=$v_a[1] ?? 0;
            if($cid==0){
                $this->error('请选择正确内容');
            }

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


            $money=$data['money'] ?? 0;
            if($money<=0){
                $this->error('请设置正确的价格');
            }

            $nums=$data['nums'] ?? 0;
            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? 0;
            if($starttime=='' || $endtime==''){
                $this->error('请选择活动时间');
            }

            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime>=$endtime){
                $this->error('请选择正确的活动时间');
            }


            $insert=[
                'type'=>$type,
                'cid'=>$cid,
                'sort'=>$sort,
                'money'=>$money,
                'nums'=>$nums,
                'nums_total'=>$nums,
                'starttime'=>$starttime,
                'endtime'=>$endtime,
            ];

            $id = DB::name('seckill')->insert($insert);

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

        $data=Db::name('seckill')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        $type=$data['type'];
        if($type==1){
            $info=Db::name('course_package')->field('id,name,price')->where('id',$data['cid'])->find();
            $info['price']=$info['price'].'元';
        }else{
            $info=Db::name('course')->field('id,name,paytype,payval,views,sort')->where('id',$data['cid'])->find();
            $info['price']=$info['payval'].'元';
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


        $this->assign('type', $type);
        $this->assign('data', $data);
        $this->assign('info', $info);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];

            $nowtime=time();
            $info=Db::name('seckill')->where('id',$id)->find();

            $where=[
                ['type','=',$info['type']],
                ['cid','=',$info['cid']],
                ['sort','=',$info['sort']],
                ['endtime','>',$nowtime]
            ];

            $isexist = DB::name('fissionposter')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置裂变海报');
            }

            $isexist = DB::name('pink')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置拼团活动');
            }

            $money=$data['money'] ?? 0;
            if($money<=0){
                $this->error('请设置正确的价格');
            }

            $nums=$data['nums'] ?? 0;
            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? 0;
            if($starttime=='' || $endtime==''){
                $this->error('请选择活动时间');
            }

            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime>=$endtime){
                $this->error('请选择正确的活动时间');
            }

            $insert=[
                'money'=>$money,
                'nums'=>$nums,
                'nums_total'=>$nums,
                'starttime'=>$starttime,
                'endtime'=>$endtime,
            ];

            $rs = DB::name('seckill')->where('id',$id)->update($insert);

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

        $rs = DB::name('seckill')->where($where)->update($up);
        if($rs===false){
            $this->error("操作失败！");
        }
        $this->resetcache();
        $this->success("操作成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('seckill')->where('id',$id)->delete();
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
            $map[]=['sort','=',1];
            $where[]=['sort','=',1];
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

    protected function resetcache(){
        $key='seckill';
        $nowtime=time();
        $list=DB::name('seckill')
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