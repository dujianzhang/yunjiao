<?php

/* 会员专享 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class VipcourseController extends AdminBaseController
{

    public function index()
    {
        $data = $this->request->param();
        $map=[];

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

        $keyword=$data['keyword'] ?? '';
        if($keyword!=''){
            $ids=[];
            $vlist=Db::name('vip_course')->field('id,cid,type')->where($map)->select()->toArray();
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

                $plist=Db::name('course_package')->field('id')->where([['id','in',$cids],['id|name','like','%'.$keyword.'%']])->select()->toArray();
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


        $list = Db::name('vip_course')
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function ($v,$k){
            $name='已删除';
            $price='';
            $nums='--';
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
                $cinfo=Db::name('course')->field('id,name,paytype,payval,views')->where('id',$v['cid'])->find();
                if($cinfo){
                    $name=$cinfo['name'];
                    $price='免费';
                    if($cinfo['paytype']==1){
                        $price=$cinfo['payval'];
                    }
                    $nums=$cinfo['views'];
                }
            }
            $v['name']=$name;
            $v['price']=$price;
            $v['nums']=$nums;
            $v['type_t']=$type_t;

            return $v;
        });
        $list->appends($data);

        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);

        $nums=Db::name('vip_course')
            ->where($map)
            ->count();
        $this->assign('nums', $nums);

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

            $cids=$data['cids'] ?? [];
            $type=$data['type'] ?? 0;

            if(!$cids){
                $this->error('请选择相关内容');
            }

            if($type==0){
                $type=1;
            }else{
                $type=0;
            }
            $nowtime=time();

            $insertall=[];
            foreach ($cids as $k=>$v){
                if($v==''){
                    continue;
                }
                $v_a=explode('_',$v);
                $cid=$v_a[0] ?? 0;
                $sort=$v_a[1] ?? 0;
                if($cid==0){
                    $this->error('请选择正确内容');
                    break;
                }

                $insert=[
                    'type'=>$type,
                    'cid'=>$cid,
                    'sort'=>$sort,
                ];
                $isexist = DB::name('vip_course')->where($insert)->find();
                if($isexist){
                    continue;
                }
                $insert['addtime']=$nowtime;

                $insertall[]=$insert;
            }

            if(!$insertall){
                $this->error('请选择未添加过的内容');
            }


            $id = DB::name('vip_course')->insertAll($insertall);
            if(!$id){
                $this->error("添加失败！");
            }
            foreach ($insertall as $k=>$v){
                if($v['type']==1){
                    DB::name('course_package')->where('id',$v['cid'])->update(['isvip'=>1]);
                }else{
                    DB::name('course')->where('id',$v['cid'])->update(['isvip'=>1]);
                }
            }
            $this->resetcache();
            $this->success("添加成功！");
        }
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $info = DB::name('vip_course')->where('id',$id)->find();
        $rs = DB::name('vip_course')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        if($info['type']==1){
            DB::name('course_package')->where('id',$info['cid'])->update(['isvip'=>0]);
        }else{
            DB::name('course')->where('id',$info['cid'])->update(['isvip'=>0]);
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    public function getList(){
        $data = $this->request->param();

        $type=$data['type'] ?? '0';
        $keyword=$data['keyword'] ?? '';
        $map=[];
        $map[]=['isvip','=',0];
        if($type==1){
            $map[]=['sort','=',1];
        }
        if($type==2){
            $map[]=['sort','>=',2];
        }
        if($type==3){
            $map[]=['sort','=',0];
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

    protected function resetcache(){
        $key='vip_course';

        $list=DB::name('vip_course')
                ->order("id desc")
                ->select();
        if($list){
            setcaches($key,$list);
        }else{
			delcache($key);
		}
    }
}