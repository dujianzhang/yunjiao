<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 账户
 */
class AccountController extends TeacherBaseController {
    
	public function index() {
        
        $cur='account';
        $this->assign('cur',$cur);
        
        return $this->fetch();
    }
    
    public function uploadImg(){
        
		$data      = $this->request->param();
        
        $uid=session('teacher.id');
        
        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }
        
        $file=$_FILES['file'];
        if(!$file){
            $this->error('请选择图片');
        }
        
		
        $res=upload();
        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $thumb=$res['url'];
        
        $data=[
            'avatar'=>$thumb.'?imageView2/2/w/600/h/600',
            'avatar_thumb'=>$thumb.'?imageView2/2/w/150/h/150',
        ];
        
        $isok=Db::name('users')->where(['id'=>$uid])->update($data);
        if($isok===false){
            $this->error('更换失败，请重试');
        }
        
        $userinfo=DB::name('users')
            ->where(['id'=>$uid])
            ->find();
            
        session('teacher.avatar',$data['avatar']);
        session('teacher.avatar_thumb',$data['avatar_thumb']);
		
		$this->success('操作成功');
	}
    
    
    public function upname(){
        
		$data      = $this->request->param();
        
        $uid=session('teacher.id');
        
        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }
        
        $name=isset($data['name']) ? checkNull($data['name']): '0';
        
        if($name==''){
            $this->error('请输入昵称');
            
        }
        
        $count=mb_strlen($name);
        if($count>10){
            $this->error('昵称最多10个字');
        }
        
        $isexist=Db::name('users')->where([['id','<>',$uid],['user_nickname','=',$name]])->find();

        if($isexist){
            $this->error('昵称已存在');
        }
        
        
        
        $data=[
            'user_nickname'=>$name,
        ];
        
        $isok=Db::name('users')->where(['id'=>$uid])->update($data);
        if($isok===false){
            $this->error('更换失败，请重试');
        }
        
        $userinfo=DB::name('users')
            ->where(['id'=>$uid])
            ->find();
        
        session('teacher.user_nickname',$name);
		
		$this->success('操作成功');
	}
}


