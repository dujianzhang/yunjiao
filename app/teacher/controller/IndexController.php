<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 首页
 */
class IndexController extends TeacherBaseController {
    
	public function index() {
        $cur='index';
        $this->assign('cur',$cur);
        
        $uid=session('teacher.id');
        
        $course_nums=Db::name("course")->where(['uid|tutoruid'=>$uid,'sort'=>1])->count();
        $live_nums=Db::name("course")->where([['uid|tutoruid','=',$uid],['sort','>=',2]])->count();
        
        $this->assign('course_nums',$course_nums);
        $this->assign('live_nums',$live_nums);
        
        $list=Db::name('portal_category_post c')
                ->leftJoin('portal_post p','c.post_id=p.id')
                ->field('p.id,p.post_title')
                ->where([['c.status','=',1],['c.category_id','=',1],['p.post_status','=',1]])
                ->order('id desc')
                ->limit(0,10)
                ->select();
                
        $this->assign('list',$list);
        
        return $this->fetch();
    }
}


