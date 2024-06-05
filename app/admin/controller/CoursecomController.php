<?php

/* 评论记录 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class CoursecomController extends AdminBaseController
{

    public function index()
    {
        
        $data = $this->request->param();
        $map=[];

        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $map[]=['courseid','=',$courseid];
        
        
        $courseinfo=Db::name("course")
                ->where(['id'=>$courseid])
                ->find();
        if($courseinfo){
            $courseinfo['thumb']=get_upload_path($courseinfo['thumb']);
            $type_a='免费';
            if($courseinfo['paytype']==1){
                $type_a='￥'.$courseinfo['payval'];
            }
            if($courseinfo['paytype']==2){
                $type_a='密码';
            }
            $courseinfo['type_a']=$type_a;
        }
        $this->assign('courseinfo', $courseinfo);
        

        
        $star=$data['star'] ?? 0;
        if($star!=''){
			$map[]=['star','=',$star];
        }

        $keyword= $data['keyword'] ?? '';
        if($keyword!=''){
            $uids=[];
            $uid_list=Db::name('course_com')->where($map)->group('uid')->column('uid');
            if($uid_list){
                $uids=Db::name('users')->where('type',1)->whereOr([['id','=',$keyword],['user_nickname','like',"%{$keyword}%"]])->column('id');
            }
			$map[]=['uid','in',$uids];
        }
        
        $nums=Db::name('course_com')->where($map)->count();

        $list = Db::name('course_com')
            ->where($map)
            ->order("id desc")
            ->paginate(20);
        
		$list->each(function($v,$k){
            $v['userinfo']=getUserInfo($v['uid']);
            $v['addtime']=date('Y-m-d H:i:s',$v['addtime']);
            return $v;
        });
		
        $page = $list->render();
        $this->assign("page", $page);
            
        $this->assign('list', $list);
        $this->assign('nums', $nums);

        if($courseinfo['sort']==1){
            return $this->fetch('index2');
        }else{
            return $this->fetch();
        }

    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $rs = DB::name('course_com')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }
        $this->success("删除成功！");
    }

    /**
     * 设置精彩评价
     */
    public function wonderful(){
        $id = input('id/d');
        $rs = DB::name('course_com')->where('id',$id)->find();
        if(!$rs){
            $this->error("评价不存在！");
        }
        $update = DB::name('course_com')->where('id',$id)->update([
            'wonderful' => (int)!$rs['wonderful']
        ]);
        $this->success("修改成功！");
    }

}