<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 考试_学生
 */
class TestsuserController extends TeacherBaseController {
	public function index() {
        $cur='tests';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');
        $this->uid=$uid;

        $data = $this->request->param();
        $map=[];

        $testsid=isset($data['testsid']) ? $data['testsid']: '0';

        if(!$testsid){
            $this->error('信息错误');
        }

        $testsinfo=Db::name("tests")
            ->field('id,title,actionuid,review,total')
            ->where('id',$testsid)
            ->where([['actionuid', 'like', "%[{$uid}]%"]])
            ->find();
        if(!$testsinfo){
            $this->error('信息错误');
        }

        $uids=[];
        if($testsinfo['actionuid']!=''){
            $acs=explode(',',$testsinfo['actionuid']);
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

        $testsinfo['acname']=implode('/',$acname_s);

        $testsinfo['no']=$testsinfo['total'] - $testsinfo['review'];

        $map[]=['testsid','=',$testsid];
        $map[]=['ishandel','=',1];

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


        $list = Db::name("tests_user")
            ->where($map)
            ->order("id desc")
            ->paginate(20);

        $list->each(function($v,$k){

            $v['endtime']=date('Y-m-d H:i:s',$v['endtime']);
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
        $this->assign('testsinfo', $testsinfo);
        $this->assign('testsid', $testsid);

        return $this->fetch();
    }


    public function edit(){
        $cur='tests';
        $this->assign('cur',$cur);

        $uid=session('teacher.id');

        $data = $this->request->param();
        $id=isset($data['id']) ? $data['id']: '0';

        if(!$id){
            $this->error('信息错误');
        }

        $info=Db::name("tests_user")
            ->where('id',$id)
            ->find();
        if(!$info){
            $this->error('信息错误');
        }

        $testsinfo=Db::name("tests")
            ->field('id,title,content')
            ->where('id',$info['testsid'])
            ->where([['actionuid', 'like', "%[{$uid}]%"]])
            ->find();
        if(!$testsinfo){
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

        $content_a=json_decode($testsinfo['content'],true);
        foreach ($content_a as $k=>$v){

            $type=$v['type'];

            $v['type_name']=$type_list[$type];
            $select='';

            if($v['answer']['t_img']!=''){
                $select.='<div class="li_img"><img src="'.get_upload_path($v['answer']['t_img']).'"></div>';
            }
            if($v['answer']['t_audio']!=''){
                $select.='<div class="li_audio"><video src="'.get_upload_path($v['answer']['t_audio']).'" controls></video></div>';
            }
            if($v['answer']['t_video']!=''){
                $select.='<div class="li_video"><video src="'.get_upload_path($v['answer']['t_video']).'" controls></video></div>';
            }
            if($type==1 || $type==2 || $type==5){
                foreach ($v['answer']['ans'] as $k2=>$v2){
                    $select.='<div class="li_item">'.$select_list[$k2].'.'.$v2.'</div>';
                }

            }

            $v['select']=$select;
            $content_a[$k]=$v;
        }

        $testsinfo['content_a']=$content_a;
        $answer_a=[];
        foreach ($content_a as $k=>$v){

            $answer=$v['answer'];
            $answer['type']=$v['type'];
            $answer['score']=$v['score'];
            $answer['score2']=$v['score2'];
            $answer['parsing']=$v['parsing'];
            $n=$k+1;
            $rs_user=$answer_user_a[$n]??[];
            $type=$answer['type'];
            $answer['type_name']=$type_list[$type];
            if($type==0){
                $answer['rs']=$judge_list[$answer['rs']];
                if($rs_user){
                    $rs_user['rs']=$judge_list[$rs_user['rs']];
                }

            }

            if($type==1 || $type==2 || $type==5){
                $rs_a=[];
                $rs=explode(',',$answer['rs']);
                foreach ($rs as $k2=>$v2){
                    if($v2!=''){
                        $rs_a[]=isset($select_list[$v2]) ? $select_list[$v2] :'';
                    }
                }
                $answer['rs']=implode(' ',$rs_a);

                $rs_user_a=[];
                if($rs_user){
                    $rs_user2=explode(',',$rs_user['rs']);
                    foreach ($rs_user2 as $k2=>$v2){
                        if($v2!=''){
                            $rs_user_a[]=isset($select_list[$v2]) ? $select_list[$v2] :'';
                        }
                    }
                    $rs_user['rs']=implode(' ',$rs_user_a);
                }


            }

            if($type==4){
                $rs_user_new=[];
                $rs_score=0;
                foreach ($answer['ans'] as $k2=>$v2){
                    $item=[];
                    $rs_set=$rs_user['rs'][$k2] ?? '';
                    $item['rs']=$rs_set;
                    $item['isok']='0';
                    if(in_array($rs_set,$v2)){
                        $rs_score+=$v['score2'];
                        $item['isok']='1';
                    }

                    $rs_user_new[]=$item;
                }
                if($rs_score==$v['score']){
                    $rs_user['isok']='1';
                }

                $rs_user['rs']=$rs_user_new;
                $rs_user['score']=$rs_score;
            }

            if(isset($answer['img'])){
                $answer['img']=get_upload_path($answer['img']);
            }
            if(isset($rs_user['img'])){
                $rs_user['img']=get_upload_path($rs_user['img']);
            }
            if(isset($rs_user['audio'])){
                $rs_user['audio']=get_upload_path($rs_user['audio']);
            }

            if(isset($rs_user['audio_time'])){
                $rs_user['audio_time_s']=handellength($rs_user['audio_time'],2);
            }

            if(isset($rs_user['re_audio'])){
                $rs_user['re_audio']=get_upload_path($rs_user['re_audio']);
            }

            if(isset($rs_user['re_audio_time'])){
                $rs_user['re_audio_time_s']=handellength($rs_user['re_audio_time'],2);
            }

            $re_audio= isset($rs_user['re_audio']) ? $rs_user['re_audio']:'';
            $review= isset($rs_user['review']) ? $rs_user['review']:'';
            $isreview2='0';
            if($isreview==1){
                if( $re_audio!='' || $review!='' ){
                    $isreview2='1';
                }
            }

            $rs_user['isreview']=$isreview2;

            $answer['rs_user']=$rs_user;
            $css='on';
            if($type!=3){
                if(isset($rs_user['isok']) && $rs_user['isok']==1){
                    $css='ok';
                }else{
                    $css='no';
                }
            }
            $answer['css']=$css;
            $answer_a[]=$answer;
        }
        $testsinfo['answer_a']=$answer_a;

        $this->assign('info',$info);
        $this->assign('testsinfo',$testsinfo);

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

        $info=Db::name("tests_user")
            ->where('id',$id)
            ->find();
        if(!$info){
            $this->error('信息错误');
        }

        if($info['actiontime']>0){
            $this->error('已批阅');
        }

        $testsinfo=Db::name("tests")
            ->field('*')
            ->where('id',$info['testsid'])
            ->where([['actionuid', 'like', "%[{$uid}]%"]])
            ->find();
        if(!$testsinfo){
            $this->error('信息错误');
        }

        $nowtime=time();

        $data_up=[
            'actionuid'=>$uid,
            'actiontime'=>$nowtime,
            'error_nums'=>$testsinfo['nums'],
        ];

        if($info['answer']==''){
            Db::name('tests_user')->where('id',$id)->update($data_up);
            createCert($info['uid'],0,$testsinfo);
            $this->success('批阅成功');
        }

        $answer_user_a=json_decode($info['answer'],true);
        if(!$answer_user_a){
            Db::name('tests_user')->where('id',$id)->update($data_up);
            createCert($info['uid'],0,$testsinfo);
            $this->success('批阅成功');
        }

        $content_a=json_decode($testsinfo['content'],true);
        $review_a=json_decode($review,true);

        $score=0;
        $correct_nums=0;
        foreach ($content_a as $k=>$v){
            $n=$k+1;
            $type=$v['type'];

            $rs_user=isset($answer_user_a[$n]) ? $answer_user_a[$n] :[];
            if(!$rs_user){
                continue;
            }

            $isok=$rs_user['isok'] ?? 0;
            $score2=$rs_user['score'] ?? 0;
            if($type==3){
                $rs=isset($review_a[$n]) ? $review_a[$n] :[];
                if($rs){
                    $rs_user['score']=$rs['score'];
                    $score2=$rs_user['score'];
                    if($rs['score']==$v['score']){
                        $rs_user['isok']='1';
                        $isok=1;
                    }
                    $rs_user['review']=$rs['review'];
                    $rs_user['re_audio']=$rs['re_audio'];
                    $rs_user['re_audio_time']=$rs['re_audio_time'];

                    $answer_user_a[$n]=$rs_user;
                }
            }

            if($isok==1){
                $correct_nums++;
            }

            $score+=$score2;
        }
        $answer=json_encode($answer_user_a);

        $data_up['answer']=$answer;
        $data_up['score']=$score;
        $data_up['correct_nums']=$correct_nums;
        $error_nums=$testsinfo['nums']-$correct_nums;
        $data_up['error_nums']=$error_nums;


        $res=Db::name('tests_user')->where('id',$id)->update($data_up);
        if($res===false){
            $this->error('批阅失败，请重试');
        }

        /* 更新考试信息 */
        upTestsNums($info['testsid']);

        createCert($info['uid'],$score,$testsinfo);

        $this->success('批阅成功');
    }

    /* 语音 */
    public function addAudio(){

        $data      = $this->request->param();

        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }

        $file=$_FILES['file']??'';

        if(!$file){
            $this->error('请先录制语音');
        }
        $_FILES['file']['name']=$_FILES['file']['name'].'.mp3';

        $res=upload($file,'audio');
        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $url=$res['url'];

        $info=[
            'url2'=>$url,
            'url'=>get_upload_path($url),
        ];

        $this->success("",'',$info);
    }

}


