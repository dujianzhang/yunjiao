<?php

/* 弹窗广告管理 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class PopimgController extends AdminBaseController
{
    protected function getStatus($key=''){
        $status=[
            '1'=>'未开始',
            '2'=>'进行中',
            '3'=>'已结束',
            '4'=>'已失效',
        ];
        if($key==''){
            return $status;
        }

        return  $status[$key] ?? '';
    }

    protected function upStatus(){
        $nowtime=time();
        $where=[
            ['status','=',1],
            ['endtime','<=',$nowtime],
        ];
        Db::name('popads')->where($where)->update(['status'=>2]);

        $this->resetcache();
    }

    public function index()
    {

        $data = $this->request->param();
        $map=[];

        $this->upStatus();

        $nowtime=time();

        $status=isset($data['status']) ? $data['status']: '';
        if($status!=''){
            if($status==1){
                $map[]=['status','=',1];
                $map[]=['starttime','>',$nowtime];
            }
            if($status==2){
                $map[]=['status','=',1];
                $map[]=['starttime','<=',$nowtime];
                $map[]=['endtime','>',$nowtime];
            }
            if($status==3){
                $map[]=['status','=',2];
            }
            if($status==4){
                $map[]=['status','=',3];
            }

        }

        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['name','like','%'.$keyword.'%'];
        }

        $statuss=$this->getStatus();

        $list = Db::name('popads')
            ->field('*')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k)use($statuss,$nowtime){
            $status=$v['status'];
            if($status==3){
                $status=4;
            }
            if($status==2){
                $status=3;
            }
            if($status==1){
                $status=2;
                if($v['starttime'] > $nowtime){
                    $status=1;
                }
            }
            $v['status_t']=$statuss[$status] ?? '';
            $v['status']=$status;
            $v['start_time']=date('Y-m-d H:i:s',$v['starttime']);
            $v['end_time']=date('Y-m-d H:i:s',$v['endtime']);

            $rate=0;
            if($v['nums_show']>0){
                $rate=floor($v['nums_open'] / $v['nums_show'] * 100);
            }
            $v['rate']=$rate;

            return $v;
        });
        $list->appends($data);
        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        $this->assign('statuss', $statuss);

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

            $nowtime=time();

            $name=$data['name'] ?? '';
            if($name == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传广告样式');
            }

            $type=$data['type'] ?? '0';
            $sort=$data['sort'] ?? '0';
            $typeval=$data['typeval'] ?? '';
            if($type == 0){
                $this->error('请选择跳转路径');
            }
            if($typeval==''){
                $this->error('请设置跳转路径');
            }

            
            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? '';
            if($starttime=='' || $endtime==''){
                $this->error('请设置投放时间');
            }
            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime>=$endtime){
                $this->error('请设置有效投放时间');
            }

            if($nowtime >= $endtime){
                $this->error('请设置有效投放时间');
            }

            $isexist=Db::name('popads')->where([['status','=',1],['starttime','<',$endtime],['endtime','>',$starttime]])->find();
            if($isexist){
                $this->error('投放时间与其他广告有重叠');
            }

            $fixed_type=$data['fixed_type'] ?? 0;
            $fixed_cid=$data['fixed_cid'] ?? [];
            $fixed_pid=$data['fixed_pid'] ?? [];
            $fixed_start=$data['fixed_start'] ?? '';
            $fixed_end=$data['fixed_end'] ?? '';
            $fixed_cids='';
            $fixed_pids='';
            if($fixed_type>0){
                if($fixed_type==3){
                    if(!$fixed_cid && !$fixed_pid){
                        $this->error('请选择指定商品');
                    }
                    $fixed_cids=handelSetToStr($fixed_cid);
                    $fixed_pids=handelSetToStr($fixed_pid);
                }

                if($fixed_start=='' || $fixed_end==''){
                    $this->error('请设置投放人群时间');
                }
                $fixed_start=strtotime($fixed_start);
                $fixed_end=strtotime($fixed_end);
                if($fixed_start>=$fixed_end){
                    $this->error('请设置有效投放人群时间');
                }

            }else{
                $fixed_start=0;
                $fixed_end=0;
            }


            $rate_type=$data['rate_type'] ?? 0;
            $rate_length=$data['rate_length'] ?? '';
            if($rate_type==0){
                $this->error('请选择出现间隔方式');
            }
            if($rate_type==2){
                if($rate_length==''){
                    $this->error('请设置出现频次间隔时间');
                }
                $rate_length=(int)$rate_length;
                if($rate_length<1){
                    $this->error('请设置有效的出现频次间隔时间');
                }
            }else{
                $rate_length=0;
            }

            $insert=[
                'name'=>$name,
                'thumb'=>$thumb,
                'type'=>$type,
                'sort'=>$sort,
                'typeval'=>$typeval,
                'starttime'=>$starttime,
                'endtime'=>$endtime,
                'status'=>1,
                'fixed_type'=>$fixed_type,
                'fixed_cids'=>$fixed_cids,
                'fixed_pids'=>$fixed_pids,
                'fixed_start'=>$fixed_start,
                'fixed_end'=>$fixed_end,
                'rate_type'=>$rate_type,
                'rate_length'=>$rate_length,
                'addtime'=>$nowtime,
            ];

            $id = DB::name('popads')->insertGetId($insert);
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
        
        $data=Db::name('popads')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $data['thumb_url']=get_upload_path($data['thumb']);
        $data['start_time']=date('Y-m-d H:i:s',$data['starttime']);
        $data['end_time']=date('Y-m-d H:i:s',$data['endtime']);

        $type=$data['type'];
        $sort=$data['sort'];
        $typeval=$data['typeval'];
        $type_show='';
        if($type>0){
            switch($type){
                case 1:
                    $type_show='APP外链接';
                    break;
                case 2:
                    $type_show='APP内链接';
                    break;
                case 3:
                    $name='已删除';
                    if($sort==-1){
                        $info=Db::name('course_package')->field('id,name,price')->where('id',$typeval)->find();
                        if($info){
                            $name=$info['name'].'('.$info['price'].'元)';
                        }
                    }else{
                        $info=Db::name('course')->field('id,name,paytype,payval,views,sort')->where('id',$typeval)->find();
                        if($info){
                            $payval='免费';
                            if($info['paytype']==1){
                                $payval=$info['payval'].'元';
                            }
                            $name=$info['name'].'('.$payval.')';
                        }
                    }
                    $typeval=$name;
                    switch($sort){
                        case -1:
                            $type_show='精选套餐';
                            break;
                        case 0:
                            $type_show='精选内容';
                            break;
                        case 1:
                            $type_show='好课推荐';
                            break;
                        case 2:
                            $type_show='直播课堂';
                            break;
                        default:
                            $type_show='';
                    }
                    break;
                default:
                    $type_show='';
            }
        }
        if($type_show!=''){
            $type_show=$type_show.' | '.$typeval;
        }
        $data['type_show']=$type_show;


        $fixed_type=$data['fixed_type'];
        $fixed_cid=[];
        $fixed_pid=[];
        $fixed_start='';
        $fixed_end='';
        if($fixed_type>0){
            $fixed_start=date('Y-m-d H:i:s',$data['fixed_start']);
            $fixed_end=date('Y-m-d H:i:s',$data['fixed_end']);
        }
        if($fixed_type==3){
            $fixed_cid=handelSetToArr($data['fixed_cids']);
            $fixed_pid=handelSetToArr($data['fixed_pids']);
            foreach ($fixed_cid as $k=>$v){
                $info=Db::name('course')->field('id,name,sort,type,paytype,payval')->where('id',$v)->find();
                if(!$info){
                    unset($fixed_cid[$k]);
                    continue;
                }
                $type_t='直播课堂';
                if($info['sort']==0){
                    $type_t='精选内容';
                }
                if($info['sort']==1){
                    $type_t='好课推荐';
                }

                $info['type_t']=$type_t;
                $fixed_cid[$k]=$info;
            }

            $fixed_cid=array_values($fixed_cid);

            foreach ($fixed_pid as $k=>$v){
                $info=Db::name('course_package')->field('id,name,price')->where('id',$v)->find();
                if(!$info){
                    unset($fixed_pid[$k]);
                    continue;
                }
                $info['type_t']='精选套餐';
                $info['price'].='元';
                $fixed_pid[$k]=$info;
            }
            $fixed_pid=array_values($fixed_pid);

        }
        $this->assign('fixed_cid', $fixed_cid);
        $this->assign('fixed_pid', $fixed_pid);

        $data['fixed_start_t']=$fixed_start;
        $data['fixed_end_t']=$fixed_end;

        if($data['rate_type']==1){
            $data['rate_length']='';
        }
        $nowtime=time();
        $status=$data['status'];
        if($status==3){
            $status=4;
        }
        if($status==2){
            $status=3;
        }
        if($status==1){
            $status=2;
            if($data['starttime'] > $nowtime){
                $status=1;
            }
        }
        $data['status']=$status;
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $nowtime=time();

            $id=$data['id'] ?? 0;
            $name=$data['name'] ?? '';
            if($name == ''){
                $this->error('请填写名称');
            }

            $thumb=$data['thumb'] ?? '';
            if($thumb == ''){
                $this->error('请上传广告样式');
            }

            $type=$data['type'] ?? '0';
            $sort=$data['sort'] ?? '0';
            $typeval=$data['typeval'] ?? '';
            if($type == 0){
                $this->error('请选择跳转路径');
            }
            if($typeval==''){
                $this->error('请设置跳转路径');
            }


            $starttime=$data['starttime'] ?? '';
            $endtime=$data['endtime'] ?? '';
            if($starttime=='' || $endtime==''){
                $this->error('请设置投放时间');
            }
            $starttime=strtotime($starttime);
            $endtime=strtotime($endtime);
            if($starttime>=$endtime){
                $this->error('请设置有效投放时间');
            }

            /*if($nowtime<= $endtime){
                $this->error('请设置有效投放时间');
            }*/

            $isexist=Db::name('popads')->where([['status','=',1],['id','<>',$id],['starttime','<',$endtime],['endtime','>',$starttime]])->find();
            if($isexist){
                $this->error('投放时间与其他广告有重叠');
            }

            $fixed_type=$data['fixed_type'] ?? 0;
            $fixed_cid=$data['fixed_cid'] ?? [];
            $fixed_pid=$data['fixed_pid'] ?? [];
            $fixed_start=$data['fixed_start'] ?? '';
            $fixed_end=$data['fixed_end'] ?? '';
            $fixed_cids='';
            $fixed_pids='';
            if($fixed_type>0){
                if($fixed_type==3){
                    if(!$fixed_cid && !$fixed_pid){
                        $this->error('请选择指定商品');
                    }
                    $fixed_cids=handelSetToStr($fixed_cid);
                    $fixed_pids=handelSetToStr($fixed_pid);
                }

                if($fixed_start=='' || $fixed_end==''){
                    $this->error('请设置投放人群时间');
                }
                $fixed_start=strtotime($fixed_start);
                $fixed_end=strtotime($fixed_end);
                if($fixed_start>=$fixed_end){
                    $this->error('请设置有效投放人群时间');
                }

            }else{
                $fixed_start=0;
                $fixed_end=0;
            }


            $rate_type=$data['rate_type'] ?? 0;
            $rate_length=$data['rate_length'] ?? '';
            if($rate_type==0){
                $this->error('请选择出现间隔方式');
            }
            if($rate_type==2){
                if($rate_length==''){
                    $this->error('请设置出现频次间隔时间');
                }
                $rate_length=(int)$rate_length;
                if($rate_length<1){
                    $this->error('请设置有效的出现频次间隔时间');
                }
            }else{
                $rate_length=0;
            }

            $insert=[
                'name'=>$name,
                'thumb'=>$thumb,
                'type'=>$type,
                'sort'=>$sort,
                'typeval'=>$typeval,
                'starttime'=>$starttime,
                'endtime'=>$endtime,
                'fixed_type'=>$fixed_type,
                'fixed_cids'=>$fixed_cids,
                'fixed_pids'=>$fixed_pids,
                'fixed_start'=>$fixed_start,
                'fixed_end'=>$fixed_end,
                'rate_type'=>$rate_type,
                'rate_length'=>$rate_length,
                'addtime'=>$nowtime,
            ];
            if($endtime>$nowtime){
                $insert['status']=1;
            }

            $rs = DB::name('popads')->where('id',$id)->update($insert);
            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('popads');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('popads')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }

    public function setStatus(){
        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->param('status', 0, 'intval');

        if($status<0){
            $this->error("信息错误");
        }

        $rs = DB::name('popads')->where('id',$id)->update(['status'=>$status,'uptime'=>time()]);
        if(!$rs){
            $this->error("操作失败！");
        }
        $this->resetcache();
        $this->success("操作成功！");
    }

    protected function resetcache(){
        $key='getPopads';
        $nowtime = time();

        $list=DB::name('popads')
            ->field('*')
            ->where([['status','=',1],['starttime','<=',$nowtime],['endtime','>',$nowtime]])
            ->find();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
    }

    public function getList(){
        $data = $this->request->param();

        $type=$data['type'] ?? '0';
        $keyword=$data['keyword'] ?? '';
        $map=[];
        $nowtime=time();
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