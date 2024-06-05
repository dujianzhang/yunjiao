<?php

/* 裂变海报 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class FissionposterController extends AdminBaseController
{

    public function index()
    {
        $data = $this->request->param();
        $map=[];

        $nowtime=time();

        $status=$data['status'] ?? '';
        if($status!=''){

            if($status==1){
                $map[]=['status','=',1];
                $map[]=['starttime','<=',$nowtime];
                $map[]=['endtime','>',$nowtime];
            }
            if($status==2){
                $map[]=['status','<>',0];
                $map[]=['endtime','<=',$nowtime];
            }
            if($status==3){
                $map[]=['status','=',1];
                $map[]=['starttime','>',$nowtime];
            }
        }

        $keyword=$data['keyword'] ?? '';
        if($keyword!=''){
            $ids=[];
            $vlist=Db::name('fissionposter')->field('id,cid,type')->where($map)->select()->toArray();
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

        $list = Db::name('fissionposter')
            ->where($map)
            ->order("id desc")
            ->paginate(20);
        $list->each(function ($v,$k)use($nowtime){
            $status=1;
            if($v['starttime'] > $nowtime){
                $status=3;
            }
            if($v['endtime'] < $nowtime){
                $status=2;
            }
            $v['status']=$status;

            $cname='';
            $cprice='';
            $cthumb='';
            $type_t='精选套餐';
            $type=$v['type'];
            $sort=$v['sort'];

            if($type==1){
                $info=Db::name('course_package')->field('id,name,thumb,price')->where('id',$v['cid'])->find();
                if($info){
                    $cname=$info['name'];
                    $cthumb=get_upload_path($info['thumb']);
                    $cprice='￥'.$info['price'];
                }
            }else{
                $info=Db::name('course')->field('id,name,thumb,paytype,payval,views,sort')->where('id',$v['cid'])->find();
                if($info){
                    $cname=$info['name'];
                    $cthumb=get_upload_path($info['thumb']);
                    $cprice='免费';
                    if($info['paytype']==1){
                        $cprice='￥'.$info['payval'];
                    }

                }

                $type_t='直播课堂';
                if($sort==0){
                    $type_t='精选内容';
                }
                if($sort==1){
                    $type_t='好课推荐';
                }
            }

            $v['cname']=$cname;
            $v['cthumb']=$cthumb;
            $v['cprice']=$cprice;
            $v['type_t']=$type_t;

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

            $where=['type'=>$type,'cid'=>$cid];
            $isexist=DB::name('fissionposter')->where($where)->find();
            if($isexist){
                $this->error('该商品已有裂变海报');
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

            $isexist = DB::name('pink')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置拼团活动');
            }

            $name=$data['name'];
            
            if($name == ''){
                $this->error('请填写活动名称');
            }
            
            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? '';
            if($starttime=='' || $endtime==''){
                $this->error('请设置活动时间');
            }
            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime >= $endtime){
                $this->error('请设置正确活动时间');
            }

            if($endtime<=$nowtime){
                $this->error('请设置正确活动时间');
            }

            $thumb_type=$data['thumb_type'] ?? '0';
            $localimg=$data['localimg'] ?? '';
            $thumb_up=$data['thumb'] ?? '';
            $thumb='';
            if($thumb_type==0){
                $this->error('请设置海报背景');
            }
            if($thumb_type==1){
                if($localimg==''){
                    $this->error('请选择海报背景');
                }
                $thumb=$localimg;
            }
            if($thumb_type==2){
                if($thumb_up==''){
                    $this->error('请上传海报背景');
                }
                $thumb=$thumb_up;
            }

            $rate=$data['rate'] ?? '';
            if($rate==''){
                $this->error('请填写奖励比例');
            }

            if($rate <1 || $rate > 100){
                $this->error('奖励比例范围为1-100');
            }

            $insert=[
                'type'=>$type,
                'sort'=>$sort,
                'cid'=>$cid,
                'name'=>$name,
                'thumb_type'=>$thumb_type,
                'thumb'=>$thumb,
                'rate'=>$rate,
                'starttime'=>$starttime,
                'endtime'=>$endtime,
                'status'=>1,
            ];

            $id = DB::name('fissionposter')->insertGetId($insert);
            if(!$id){
                $this->error("添加失败！");
            }

            $this->resetcache($type,$cid);

            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('fissionposter')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $data['thumb_url']=get_upload_path($data['thumb']);

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
        $data['type']=$type;
        $type_name=['精选套餐','好课推荐','直播课堂','精选内容'];
        $type_t=$type_name[$type] ?? '';
        $this->assign('info', $info);
        $this->assign('type_t', $type_t);

        $thumb='';
        if($data['thumb_type']==2){
            $thumb=$data['thumb'];
        }
        $data['thumb_up']=$thumb;

        $this->assign('data', $data);
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

            $where=[['id','<>',$id],['type','=',$type],['cid','=',$cid]];
            $isexist=DB::name('fissionposter')->where($where)->find();
            if($isexist){
                $this->error('该商品已有裂变海报');
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

            $isexist = DB::name('pink')->where($where)->find();
            if($isexist){
                $this->error('该商品已设置拼团活动');
            }

            $name=$data['name'];

            if($name == ''){
                $this->error('请填写活动名称');
            }

            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? '';
            if($starttime=='' || $endtime==''){
                $this->error('请设置活动时间');
            }
            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime >= $endtime){
                $this->error('请设置正确活动时间');
            }

            if($endtime<=$nowtime){
                $this->error('请设置正确活动时间');
            }

            $thumb_type=$data['thumb_type'] ?? '0';
            $localimg=$data['localimg'] ?? '';
            $thumb_up=$data['thumb'] ?? '';
            $thumb='';
            if($thumb_type==0){
                $this->error('请设置海报背景');
            }
            if($thumb_type==1){
                if($localimg==''){
                    $this->error('请选择海报背景');
                }
                $thumb=$localimg;
            }
            if($thumb_type==2){
                if($thumb_up==''){
                    $this->error('请上传海报背景');
                }
                $thumb=$thumb_up;
            }

            $rate=$data['rate'] ?? '';
            if($rate==''){
                $this->error('请填写奖励比例');
            }
            if($rate <1 || $rate > 100){
                $this->error('奖励比例范围为1-100');
            }

            $insert=[
                'type'=>$type,
                'sort'=>$sort,
                'cid'=>$cid,
                'name'=>$name,
                'thumb_type'=>$thumb_type,
                'thumb'=>$thumb,
                'rate'=>$rate,
                'starttime'=>$starttime,
                'endtime'=>$endtime,
            ];

            $info=DB::name('fissionposter')->field('type,cid')->where('id',$id)->find();

            $rs = DB::name('fissionposter')->where('id',$id)->update($insert);

            if($rs === false){
                $this->error("保存失败！");
            }

            $this->resetcache($type,$cid,$info['type'],$info['cid']);

            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('fissionposter');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $info = DB::name('fissionposter')->where('id',$id)->find();
        $rs = DB::name('fissionposter')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache($info['type'],$info['cid']);
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

    protected function resetcache($type,$cid,$oldtype=0,$oldcid=0){

        $key1='fissionposter_'.$oldtype.'_'.$oldcid;
        delcache($key1);

        $key='fissionposter_'.$type.'_'.$cid;
        $nowtime=time();
        $info=DB::name('fissionposter')
            ->where([['type','=',$type],['cid','=',$cid],['status','=',1],['starttime','<=',$nowtime],['endtime','>',$nowtime]])
            ->find();
        if($info){
            setcaches($key,$info);
        }else{
            delcache($key);
        }
    }
}