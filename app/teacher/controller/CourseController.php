<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 我的课程
 */
class CourseController extends TeacherBaseController {
    
    /* 形式 */
    protected function getMode($k=''){
        $mode=[
            '0'=>'自由',
            '1'=>'解锁',
        ];
        if($k===''){
            return $mode;
        }
        return isset($mode[$k])? $mode[$k] : '' ;
    }
    
    /* 状态 */
    protected function getStatus($k=''){
        $status=[
            '0'=>'未上架',
            '1'=>'已上架',
        ];
        
        if($k===''){
            return $status;
        }
        return isset($status[$k])? $status[$k] : '' ;
    }
    
    /* 科目分类 */
    protected function getClass(){
        $list = Db::name('course_class')
            ->order("list_order asc")
            ->column('*','id');
        return $list;
    }
    
    /* 学级分类 */
    protected function getGrade(){
        $list = Db::name('course_grade')
            ->order("list_order asc")
            ->column('*','id');
        $list2=[];
        foreach($list as $k=>$v){
            if($v['pid']!=0){
                $name=$list[$v['pid']]['name'].' - '.$v['name'];
                $v['name']=$name;
                
                $list2[$k]=$v;
            }
        }
        return $list2;
    }
    
	public function index() {
        $cur='course';
        $this->assign('cur',$cur);
        
        $uid=session('teacher.id');
        $this->uid=$uid;
        
        $data = $this->request->param();
        $map=[];
        
        $map[]=['sort','=',1];
        $map[]=['uid|tutoruid','=',$uid];
        
        $nowtime=time();
        
        $status=isset($data['status']) ? $data['status']: '';
        if($status!=''){
            if($status==1){
                $map[]=['status','>=',1];
                $map[]=['shelvestime','<',$nowtime];
            }
        }
        
        $mode=isset($data['mode']) ? $data['mode']: '';
        if($mode!=''){
            $map[]=['mode','=',$mode];
        }
        
        
        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['name','like','%'.$keyword.'%'];
        }
        
        if($status!='' && $status==0){
            $list = Db::name("course")
                ->where($map)
                ->where(function ($query) {
                    $query->whereor('status','<', '1')
                          ->whereor('shelvestime', '>', time());
                })
                ->order("id desc")
                ->paginate(20);
        }else{
            $list = Db::name("course")
                ->where($map)
                ->order("id desc")
                ->paginate(20);
        }
        
                
        $list->each(function($v,$k){
            
            $v['thumb']=get_upload_path($v['thumb']);
            $v['live_time']=date('Y-m-d H:i:s',$v['starttime']);
            
            $paytype=$v['paytype'];
            $pay_val='免费';
            
            if($paytype==1){
                $pay_val='￥ '.$v['payval'];
            }
            
            if($paytype==2){
                $pay_val='密码';
            }
            $v['pay_val']=$pay_val;
            
            $v['mode_s']=$this->getMode($v['mode']);
            $status=$v['status'];
            
            if($status<1){
                $status=0;
            }
            if($status==2){
                if($v['shelvestime']>time()){
                    $status=0;
                }else{
                    $status=1;
                }
            }
            $v['status_s']=$this->getStatus($status);
            
            $user_type='';
            if($v['uid']==$this->uid){
                $user_type='主讲老师';
            }
            
            if($v['tutoruid']==$this->uid){
                $user_type='辅导老师';
            }
            $v['user_type']=$user_type;
            
            return $v;           
        });
        
        $list->appends($data);
        // 获取分页显示
        $page = $list->render();
        
        $this->assign('list', $list);
        $this->assign('page', $page);
        
        // $this->assign('page', $page);
        
        $this->assign('mode',$this->getMode());
        $this->assign('status',$this->getStatus());
        
        $this->assign('classs', $this->getClass());
        $this->assign('grade', $this->getGrade());
        
        return $this->fetch();
    }
    
    /* 内容形式 */
    protected function getTypes($k=''){
        $type=[
            '1'=>'图文自学',
            '2'=>'视频自学',
            '3'=>'音频自学',
            '4'=>'PPT讲解',
            '5'=>'视频讲解',
            '6'=>'音频讲解',
            '8'=>'普通直播',
            '7'=>'白板互动',
        ];
        if($k===''){
            return $type;
        }
        return isset($type[$k])? $type[$k] : '' ;
    }
    
    public function lesson()
    {
        $cur='course';
        $this->assign('cur',$cur);
        
        $data = $this->request->param();
        $map=[];
        
        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $map[]=['courseid','=',$courseid];
        
        $courseinfo=Db::name("course")
                ->where(['id'=>$courseid])
                ->find();
        if($courseinfo){
            $courseinfo['thumb']=get_upload_path($courseinfo['thumb']);
            
            $paytype=$courseinfo['paytype'];
            $pay_val='免费';
            
            if($paytype==1){
                $pay_val='￥ '.$courseinfo['payval'];
            }
            
            if($paytype==2){
                $pay_val='密码';
            }
            $courseinfo['pay_val']=$pay_val;
            
            $courseinfo['mode_s']=$this->getMode($courseinfo['mode']);
            
        }
        $this->assign('courseinfo', $courseinfo);
        
        
        $list = Db::name("course_lesson")->where($map)->order("list_order asc")->select()->toArray();
        foreach($list as $k=>$v){
            $v['starttime']=date('Y-m-d H:i:s',$v['starttime']);
            $list[$k]=$v;
        }

        $this->assign('list', $list);
        
        
        $this->assign('nums', count($list));
        $this->assign('types', $this->getTypes());
        // 渲染模板输出
        return $this->fetch();
    }

}


