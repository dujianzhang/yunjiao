<?php


namespace app\teacher\controller;

use app\models\AgoraModel;
use cmf\controller\TeacherBaseController;
use think\Db;

/**
 * 声网
 */
class AgoraController extends TeacherBaseController {

	public function startcdn() {
        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $cdntype=$data['cndtype'] ?? '0';
        if($stream==''){
            $this->error('信息错误');
        }
        $uid=session('teacher.id');

        $pushurl=getCdnUrl($cdntype,'rtmp',$stream,1);
        if($pushurl==''){
            $this->error('信息错误');
        }

	    $res=AgoraModel::getConverters($stream);
        if($res['code']!=0){
            $this->error($res['msg']);
        }

        if($res['info']){
            $converterId=$res['info'][0]['converterId'] ?? '';
            if($converterId){

                AgoraModel::updateCdn($converterId,$stream,$uid,$uid);

                $this->success('','',compact('converterId'));
            }
        }

	    $res=AgoraModel::createCdn($stream,$uid,$pushurl);

        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $this->success('','',$res['info']);
    }

    public function updatecdn(){

        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $converterId=$data['converterId'] ?? '';
        $type=$data['type'] ?? '0';

        if($stream=='' || $converterId==''){
            $this->error('信息错误');
        }
        $uid=session('teacher.id');

        $maxResolutionUid=$uid;
        /* 共享屏幕 */
        if($type==1){
            $maxResolutionUid=999999999;
        }

        $res=AgoraModel::updateCdn($converterId,$stream,$uid,$maxResolutionUid);
        if($res['code']!=0){
            $this->error($res['msg']);
        }

        $this->success('','',$res['info']);

    }

    public function delcdn(){

        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $converterId=$data['converterId'] ?? '';

        if($stream=='' || $converterId==''){
            $this->error('信息错误');
        }

        $res=AgoraModel::delCdn($converterId,$stream);

        if($res['code']!=0){
            $this->error($res['msg']);
        }
        $this->success('','',$res['info']);

    }

    /* 声网开始录制 */
    public function createRecord(){
        $data      = $this->request->param();
        $uid=session('teacher.id');

        if($uid<1){
            $this->error('您的登陆状态失效，请重新登陆！');
        }
        $id=isset($data['id']) ? checkNull($data['id']): '0';
        $courseid=isset($data['courseid']) ? checkNull($data['courseid']): '0';
        $lessonid=isset($data['lessonid']) ? checkNull($data['lessonid']): '0';

        if(!$id){
            if($courseid<1 ){
                $this->error('信息错误');
            }
        }

        $nowtime=time();
        if($id>0){
            $where=[
                'uid'=>$uid,
                'id'=>$id,
            ];
            $info=Db::name('class_meeting')->where($where)->find();
        }elseif($lessonid>0){
            $where=[
                'uid'=>$uid,
                'courseid'=>$courseid,
                'id'=>$lessonid,
            ];

            $info=Db::name('course_lesson')->where($where)->find();
        }else{
            $where=[
                'uid'=>$uid,
                'id'=>$courseid,
            ];

            $info=Db::name('course')->where($where)->find();
        }

        if(!$info){
            $this->error('无权操作');
        }

        if($info['islive']!=1){
            $this->error('还未开始上课');
        }

        if($info['resourceid']!='' && $info['sid']!=''){
            $this->error('已录制');
        }

       if($id){
           $stream='class_'.$uid.'_'.$info['class_id'].'_'.$info['id'];
       }else{
           $stream=$uid.'_'.$courseid.'_'.$lessonid;
       }

        $rs_create=AgoraModel::createRecord($stream,$uid);
        if($rs_create['code']!=0){
            $this->error($rs_create['msg']);
        }

        $resourceid=$rs_create['data']['resourceId'];

        /* 开始录制 */
        $rs_start=AgoraModel::startRecord($stream,$uid,$resourceid);
        if($rs_start['code']!=0){
            $this->error($rs_start['msg']);
        }

        $sid=$rs_start['data']['sid'];

        $data_up=[
            'resourceid'=>$resourceid,
            'sid'=>$sid,
        ];
        if($id){
            $isok=Db::name('class_meeting')->where($where)->update($data_up);
        }else{
            if($lessonid>0){
                $isok=Db::name('course_lesson')->where($where)->update($data_up);
            }else{
                $isok=Db::name('course')->where($where)->update($data_up);
            }
        }

        if($isok===false){
            $this->error('操作失败，请重试');
        }

        $this->success('操作成功');
    }
}


