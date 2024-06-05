<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 作业_学生
 */
class TaskuserController extends TeacherBaseController {
	public function index() {
        $cur='task';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');
        $this->uid=$uid;

        $data = $this->request->param();
        $map=[];

        $taskid=isset($data['taskid']) ? $data['taskid']: '0';

        if(!$taskid){
            $this->error('信息错误');
        }

        $taskinfo=Db::name("task")
            ->field('id,uid,name,courseid,actionuid,review,total')
            ->where('id',$taskid)
            ->where(function ($query) use($uid) {
                $query->whereor('uid','=', $uid)
                    ->whereor('actionuid', 'like', "%[{$uid}]%");
            })
            ->find();
        if(!$taskinfo){
            $this->error('信息错误');
        }
        $coursename='课程已删除';
        $courseinfo=Db::name("course")
            ->field('id,name')
            ->where('id',$taskinfo['courseid'])
            ->find();
        if($courseinfo){
            $coursename=$courseinfo['name'];
        }
        $taskinfo['coursename']=$coursename;

        $uids=[];
        $uids[]=(int)$taskinfo['uid'];
        if($taskinfo['actionuid']!=''){
            $acs=explode(',',$taskinfo['actionuid']);
            foreach ($acs as $k=>$v){
                $v=str_replace('[','',$v);
                $v=str_replace(']','',$v);
                $uids[]=(int)$v;
            }
        }

        $uids=array_unique(array_filter($uids));
        $acname_s=[];
        foreach ($uids as $k=>$v){
            $userinfo=getUserInfo($v);
            $acname_s[]=$userinfo['user_nickname'];
        }

        $taskinfo['acname']=implode('/',$acname_s);

        $taskinfo['no']=$taskinfo['total'] - $taskinfo['review'];

        $map[]=['taskid','=',$taskid];

        $status=isset($data['status']) ? $data['status']: '0';
        if($status!=''){
            if($status==1){
                $map[]=['actiontime','=',0];
            }

            if($status==2){
                $map[]=['actiontime','<>',0];
            }
        }

        $keyword=isset($data['keyword']) ? $data['keyword']: '';
        if($keyword!=''){
            $uid_a=[];
            $uids=Db::name("users")
                ->field('id')
                ->where('user_nickname','like','%'.$keyword.'%')
                ->select()->toArray();

            if($uids){
                $uid_a=array_column($uids,'id');
            }
            $map[]=['uid','in',$uid_a];
        }


        $list = Db::name("task_user")
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function($v,$k){

            $v['add_time']=date('Y-m-d H:i:s',$v['addtime']);
            $action_time='--';
            if($v['actiontime']>0){
                $action_time=date('Y-m-d H:i:s',$v['actiontime']);
            }
            $v['action_time']=$action_time;

            $v['userinfo']=getUserInfo($v['uid']);
            $actioninfo=[
                'user_nickname'=>'--'
            ];

            if($v['actionuid']>0){
                $actioninfo=getUserInfo($v['actionuid']);
            }
            $v['actioninfo']=$actioninfo;

            return $v;
        });

        $list->appends($data);
        // 获取分页显示
        $page = $list->render();

        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('taskinfo', $taskinfo);
        $this->assign('taskid', $taskid);

        return $this->fetch();
    }


    public function edit(){
        $cur='task';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');

        $data = $this->request->param();
        $id=isset($data['id']) ? $data['id']: '0';

        if(!$id){
            $this->error('信息错误');
        }

        $info=Db::name("task_user")
            ->where('id',$id)
            ->find();
        if(!$info){
            $this->error('信息错误');
        }

        $taskinfo=Db::name("task")
            ->field('id,uid,type,name,content,answer')
            ->where('id',$info['taskid'])
            ->where(function ($query) use($uid) {
                $query->whereor('uid','=', $uid)
                    ->whereor('actionuid', 'like', "%[{$uid}]%");
            })
            ->find();
        if(!$taskinfo){
            $this->error('信息错误');
        }

        $answer_user_a=json_decode($info['answer'],true);

        $isreview=0;
        if($info['actiontime']>0){
            $isreview=1;
        }
        $info['isreview']=$isreview;
        if($isreview==0){
            $info['score']='--';
        }

        $info['userinfo']=getUserInfo($info['uid']);

        $select_list=['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];
        $judge_list=['错','对'];
        $type_list=['判断题','单选题','定项多选题','简答题','填空题','不定项多选题'];

        $content_a=json_decode($taskinfo['content'],true);
        foreach ($content_a as $k=>&$v){
            if($taskinfo['type']==1){
                $type=$v['type'];

                $v['type_name']=$type_list[$type];
                $select='';
                if(isset($v['t_img']) && $v['t_img']!=''){
                    $select.='<div class="li_img"><img src="'.get_upload_path($v['t_img']).'"></div>';
                }
                if(isset($v['t_audio']) && $v['t_audio']!=''){
                    $select.='<div class="li_audio"><video src="'.get_upload_path($v['t_audio']).'" controls></video></div>';
                }
                if(isset($v['t_video']) && $v['t_video']!=''){
                    $select.='<div class="li_video"><video src="'.get_upload_path($v['t_video']).'" controls></video></div>';
                }
                if($type==1 || $type==2 || $type==5){
                    foreach ($v['ans'] as $k2=>$v2){
                        $select.='<div class="li_item">'.$select_list[$k2].'.'.$v2.'</div>';
                    }

                }

                $v['select']=$select;
            }else{
                $v=get_upload_path($v);
            }

        }

        $taskinfo['content_a']=$content_a;
        $answer_a=json_decode($taskinfo['answer'],true);
        foreach ($answer_a as $k=>&$v){
            $n=$k+1;
            $rs_user=$answer_user_a[$n];
            $type=$v['type'];
            $v['type_name']=$type_list[$type];
            if($type==0){
                $v['rs']=$judge_list[$v['rs']];
                $rs_user['rs']=$judge_list[$rs_user['rs']];
            }

            if($type==1 || $type==2 || $type==5){
                $rs_a=[];
                $rs=explode(',',$v['rs']);
                foreach ($rs as $k2=>$v2){
                    if($v2!=''){
                        $rs_a[]=isset($select_list[$v2]) ? $select_list[$v2] :'';
                    }
                }
                $v['rs']=implode(' ',$rs_a);

                $rs_user_a=[];
                $rs_user2=explode(',',$rs_user['rs']);
                foreach ($rs_user2 as $k2=>$v2){
                    if($v2!=''){
                        $rs_user_a[]=isset($select_list[$v2]) ? $select_list[$v2] :'';
                    }
                }
                $rs_user['rs']=implode(' ',$rs_user_a);

            }

            if($type==4){

            }

            if(isset($v['img'])){
                $v['img']=get_upload_path($v['img']);
            }
            if(isset($rs_user['img'])){
                $rs_user['img']=get_upload_path($rs_user['img']);
            }
            if(isset($rs_user['audio'])){
                $rs_user['audio']=get_upload_path($rs_user['audio']);
            }

            $v['rs_user']=$rs_user;
            $css='on';
            if($type!=3){
                if($rs_user['isok']==1){
                    $css='ok';
                }else{
                    $css='no';
                }
            }
            $v['css']=$css;
        }
        $taskinfo['answer_a']=$answer_a;

        $this->assign('info',$info);
        $this->assign('taskinfo',$taskinfo);

        return $this->fetch();
    }

    public function editPost(){

        $uid=session('teacher.id');

        $data = $this->request->param();

        $id=isset($data['id']) ? $data['id']: '0';
        $review=isset($data['review']) ? $data['review']: '';

        if(!$id){
            $this->error('信息错误');
        }

        $info=Db::name("task_user")
            ->where('id',$id)
            ->find();
        if(!$info){
            $this->error('信息错误');
        }

        if($info['actiontime']>0){
            $this->error('已批阅');
        }

        $taskinfo=Db::name("task")
            ->field('id,type,answer,content')
            ->where('id',$info['taskid'])
            ->where(function ($query) use($uid) {
                $query->whereor('uid','=', $uid)
                    ->whereor('actionuid', 'like', "%[{$uid}]%");
            })
            ->find();
        if(!$taskinfo){
            $this->error('信息错误');
        }

        $nowtime=time();

        $answer_a=json_decode($taskinfo['answer'],true);
        $answer_user_a=json_decode($info['answer'],true);

        $review_a=json_decode($review,true);

        $score=0;
        foreach ($answer_a as $k=>$v){
            $n=$k+1;
            $type=$v['type'];

            $rs_user=isset($answer_user_a[$n]) ? $answer_user_a[$n] :'';
            $score2=$rs_user['score'];
            if($type==3){
                $rs=isset($review_a[$n]) ? $review_a[$n] :'';
                if($rs){
                    $answer_user_a[$n]['score']=$rs;
                    $score2=$rs;
                    if($score2==$v['score']){
                        $answer_user_a[$n]['isok']='1';
                    }
                }
            }

            $score+=$score2;
        }
        $answer=json_encode($answer_user_a);
        $data_up=[
            'actionuid'=>$uid,
            'actiontime'=>$nowtime,
            'answer'=>$answer,
            'score'=>$score,
        ];

        $res=Db::name('task_user')->where('id',$id)->update($data_up);
        if($res===false){
            $this->error('批阅失败，请重试');
        }

        Db::name('task')->where('id',$info['taskid'])->inc('review',1)->update();

        /* 错题本 */
        if($taskinfo['type']==1){
            $content=json_decode($taskinfo['content'],true);
            foreach ($answer_user_a as $k=>$v){
                $n=$k-1;
                if($v['isok']==0 && isset($content[$n])){
                    $ans=$answer_a[$n];
                    if($ans['type']==3){
                        $content_i=$content[$n];

                        unset($v['isok']);
                        unset($v['score']);
                        $answer_i=[
                            'type'=>$ans['type'],
                            'rs'=>$ans['rs'],
                            'img'=>$ans['img'],
                            'rs_user'=>$v
                        ];
                        $insert=[
                            'uid'=>$info['uid'],
                            'type'=>1,
                            'content'=>json_encode($content_i),
                            'answer'=>json_encode($answer_i),
                            'des'=>'',
                            'addtime'=>time(),
                        ];
                        Db::name('wrongbook')->insert($insert);
                    }
                }
            }
        }

        $this->success('批阅成功');
    }
}


