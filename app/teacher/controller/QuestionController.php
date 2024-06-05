<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
/**
 * 题库
 */
class QuestionController extends TeacherBaseController {

    public function getclass(){

	    $list=Db::name('question_class')
            ->field('id,name,pid,nums')
            ->order('list_order asc')
            ->select()
            ->toArray();
        $total=0;
        $list=$this->handellist($list);
        foreach ($list as $k=>$v){
            $nums=0;
            foreach ($v['list'] as $k2=>$v2){
                $nums+=$v2['nums'];
            }

            $v['nums']=$nums;
            $total+=$nums;
            $list[$k]=$v;
        }
        $info=[
            'list'=>$list,
            'total'=>$total,
        ];
        $this->success('','',$info);

    }

    /* 处理课时数组 */
    protected function handellist($list=[],$pid=0){
        $rs=[];
        foreach($list as $k=>$v){
            if($v['pid']==$pid){
                unset($list[$k]);
                $v['list']=$this->handellist($list,$v['id']);
                $rs[]=$v;
            }
        }

        return $rs;
    }

    public function getQuestion(){
        $data      = $this->request->param();
        $classid=isset($data['classid']) ? checkNull($data['classid']): '0';
        $type=isset($data['type']) ? checkNull($data['type']): '';
        $keyword=isset($data['keyword']) ? checkNull($data['keyword']): '';
        $page=isset($data['page']) ? checkNull($data['page']): '';

        $map=[];
        if($classid!=0){
            $map[]=['classid','=',$classid];
        }

        if($type!=''){
            $map[]=['type','=',$type];
        }

        if($keyword!=''){
            $map[]=['title','like','%'.$keyword.'%'];
        }

        if($page<1){
            $page=1;
        }
        $nums=7;
        $start=($page-1) * $nums;

        $type_list=['判断题','单选题','定项多选题','简答题','填空题','不定项多选题'];

        $list=Db::name('question')
            ->field('id,type,title,answer,score,score2')
            ->where($map)
            ->order('id desc')
            ->limit($start,$nums)
            ->select()
            ->toArray();

        foreach ($list as $k=>$v){
            $answer=json_decode($v['answer'],true);
            $img=$answer['img'] ?? '';
            $img_t='';
            if(isset($answer['img'])){
                $img_t=get_upload_path($answer['img']);
            }
            $answer['img']=$img;
            $answer['img_t']=$img_t;

            $t_img=$answer['t_img'] ?? '';
            $t_img_t='';
            if(isset($answer['t_img'])){
                $t_img_t=get_upload_path($answer['t_img']);
            }
            $answer['t_img']=$t_img;
            $answer['t_img_t']=$t_img_t;

            $t_audio=$answer['t_audio'] ?? '';
            $t_audio_t='';
            if(isset($answer['t_audio'])){
                $t_audio_t=get_upload_path($answer['t_audio']);
            }
            $answer['t_audio']=$t_audio;
            $answer['t_audio_t']=$t_audio_t;

            $t_video=$answer['t_video'] ?? '';
            $t_video_t='';
            if(isset($answer['t_video'])){
                $t_video_t=get_upload_path($answer['t_video']);
            }
            $answer['t_video']=$t_video;
            $answer['t_video_t']=$t_video_t;

            $t_video_img=$answer['t_video_img'] ?? '';
            $t_video_img_t='';
            if(isset($answer['t_video_img'])){
                $t_video_img_t=get_upload_path($answer['t_video_img']);
            }
            $answer['t_video_img']=$t_video_img;
            $answer['t_video_img_t']=$t_video_img_t;

            if($v['type']==3){
                foreach ($answer['ans'] as $k2=>$v2){
                    $answer['ans'][$k2]=get_upload_path($v2);
                }
            }

            $v['answer']=$answer;
            $v['type_name']=$type_list[$v['type']];

            $list[$k]=$v;
        }
        $total=Db::name('question')
            ->where($map)
            ->count();
        $info=[
            'list'=>$list,
            'total'=>$total,
            'nums'=>$nums,
        ];

        $this->success('','',$info);
    }
}


