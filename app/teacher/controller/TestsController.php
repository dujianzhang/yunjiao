<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 考试
 */
class TestsController extends TeacherBaseController {
    
	public function index() {
        $cur='tests';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');
        $this->uid=$uid;

        $data = $this->request->param();
        $map=[];
        $where='';

        $map[]=['status','>=',0];
        $map[]=['actionuid', 'like', "%[{$uid}]%"];
        $nowtime=time();

        $status=isset($data['status']) ? $data['status']: '0';
        if($status!=''){
            if($status==0){
                $map[]=['endtime','>',$nowtime];
            }

            if($status==1){
                $map[]=['endtime','<=',$nowtime];
                $where='review!=total';
            }

            if($status==2){
                $map[]=['endtime','<=',$nowtime];
                $where='review=total';
            }
        }

        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $map[]=['title','like','%'.$keyword.'%'];
        }


        $list = Db::name("tests")
            ->where($map)
            ->where($where)
            ->order("id desc")
            ->paginate(20);


        $list->each(function($v,$k){
            $end_time='--';
            if($v['endtime']>0){
                $end_time=date('Y-m-d H:i:s',$v['endtime']);
            }
            $v['end_time']=$end_time;
            return $v;
        });

        $list->appends($data);
        // 获取分页显示
        $page = $list->render();

        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('status', $status);

        return $this->fetch();
    }
}


