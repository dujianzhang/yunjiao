<?php

/* VIP管理 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class VipController extends AdminBaseController
{

    public function index()
    {
        
        $list = Db::name('vip')
            ->order("list_order asc")
            ->paginate(20);
		
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
            
            $title=$data['title'];


            $id = DB::name('vip')->insertGetId($data);
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
        
        $data=Db::name('vip')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }

        $price=json_decode($data['price'],true);
        if(!$price){
            $price[]=[
                'type'=>1,
                'money'=>0,
            ];
        }
        $data['price']=$price;
        $discount_cid=handelSetToArr($data['discount_cid']);
        $discount_pid=handelSetToArr($data['discount_pid']);
        $free_cid=handelSetToArr($data['free_cid']);
        $free_pid=handelSetToArr($data['free_pid']);

        foreach ($discount_cid as $k=>$v){
            $info=Db::name('course')->field('id,name,sort')->where('id',$v)->find();
            if(!$info){
                unset($discount_cid[$k]);
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
            $discount_cid[$k]=$info;
        }
        $discount_cid=array_values($discount_cid);

        foreach ($discount_pid as $k=>$v){
            $info=Db::name('course_package')->field('id,name')->where('id',$v)->find();
            if(!$info){
                unset($discount_pid[$k]);
                continue;
            }
            $info['type_t']='精选套餐';
            $discount_pid[$k]=$info;
        }
        $discount_pid=array_values($discount_pid);

        foreach ($free_cid as $k=>$v){
            $info=Db::name('course')->field('id,name,sort')->where('id',$v)->find();
            if(!$info){
                unset($free_cid[$k]);
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
            $free_cid[$k]=$info;
        }
        $free_cid=array_values($free_cid);

        foreach ($free_pid as $k=>$v){
            $info=Db::name('course_package')->field('id,name')->where('id',$v)->find();
            if(!$info){
                unset($free_pid[$k]);
                continue;
            }
            $info['type_t']='精选套餐';
            $free_pid[$k]=$info;
        }
        $free_pid=array_values($free_pid);


        $data['discount_cid']=$discount_cid;
        $data['discount_pid']=$discount_pid;
        $data['free_cid']=$free_cid;
        $data['free_pid']=$free_pid;



        $lengths=getVipLength();

        $this->assign('data', $data);
        $this->assign('lengths', $lengths);
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();

            $id=$data['id'];

            $type=$data['type'] ?? [];
            $money=$data['money'] ?? [];

            if(!$type || !$money){
                $this->error("请设置规格");
            }

            $price=[];
            $order=[];
            foreach ($type as $k=>$v){
                if($v==0){
                    $this->error("请选择有效期");
                }
                foreach ($type as $k2=>$v2){
                    if($v==$v2 && $k!=$k2){
                        $this->error("每种规格只能设置一个");
                    }
                }
                $price[$k]['type']=$v;
                $order[$k]=$v;
            }

            foreach ($money as $k=>$v){
                if($v<=0){
                    $this->error("请设置正确的价格");
                }
                $price[$k]['money']=$v;
            }
            array_multisort($order, SORT_ASC, $price);

            $up=[
                'price'=>json_encode($price),
            ];

            if($id==1){
                $discount=$data['discount'] ?? 0;
                $discount=floor(floatval($discount)*10)*0.1;
                if($discount==0 || $discount>10){
                    $this->error("请设置正确的折扣比例");
                }
                $discount_type=$data['discount_type'] ?? -1;
                $discount_cid=$data['discount_cid'] ?? [];
                $discount_pid=$data['discount_pid'] ?? [];
                $free_cid=$data['free_cid'] ?? [];
                $free_pid=$data['free_pid'] ?? [];
                if($discount_type!=1){
                    $discount_cid=[];
                    $discount_pid=[];
                }

                $up['discount']=$discount;
                $up['discount_type']=$discount_type;
                $up['discount_cid']=handelSetToStr($discount_cid);
                $up['discount_pid']=handelSetToStr($discount_pid);
                $up['free_cid']=handelSetToStr($free_cid);
                $up['free_pid']=handelSetToStr($free_pid);
            }


            $rs = DB::name('vip')->where('id',$id)->update($up);

            if($rs === false){
                $this->error("保存失败！");
            }
            $this->resetcache();
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('vip');
        parent::listOrders($model);
        $this->resetcache();
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $isok=DB::name('vip')->where("catid",$id)->find();
        if($isok){
            $this->error("该分类下已有考试，不能删除");
        }
        
        $rs = DB::name('vip')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->resetcache();
        $this->success("删除成功！");
    }


    protected function resetcache(){
        $key='vip_list';

        $list=DB::name('vip')
                ->order("list_order asc")
                ->select();
        if($list){
            setcaches($key,$list);
        }else{
			delcache($key);
		}
    }
}