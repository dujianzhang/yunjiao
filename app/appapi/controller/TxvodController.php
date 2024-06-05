<?php

/* 腾讯云点播 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class TxvodController extends HomebaseController{

    function transback(){

        $data = $this->request->param();
        $path=CMF_DATA.'log/txvod/';
        if (!is_dir($path)) {
            @mkdir($path);
        }
        file_put_contents($path.'transback_'.date('Ymd').'.log',date('Ymd H:i:s').'data:'.json_encode($data).PHP_EOL,FILE_APPEND);
        //$datajs='{"EventType":"ProcedureStateChanged","FileUploadEvent":null,"ProcedureStateChangeEvent":{"TaskId":"1305819496-procedurev2-f4c4e73a9b8ff4cba3ee9df5c4608270tt0","Status":"FINISH","ErrCode":0,"Message":"SUCCESS","FileId":"3701925920477195402","FileName":"1","FileUrl":"http:\/\/1305819496.vod2.myqcloud.com\/17421db0vodcq1305819496\/f0e32f023701925920477195402\/CFJFijgLOlIA.mp4","MetaData":{"AudioDuration":98.66159057617188,"AudioStreamSet":[{"Bitrate":128247,"Codec":"aac","SamplingRate":44100}],"Bitrate":1158311,"Container":"mov,mp4,m4a,3gp,3g2,mj2","Duration":98.66159057617188,"Height":640,"Rotate":0,"Size":14214980,"VideoDuration":97.80000305175781,"VideoStreamSet":[{"Bitrate":1030064,"Codec":"h264","Fps":15,"Height":640,"Width":360}],"Width":360},"AiAnalysisResultSet":[],"AiRecognitionResultSet":[],"AiContentReviewResultSet":[],"MediaProcessResultSet":[{"Type":"ImageSprites","TranscodeTask":null,"AnimatedGraphicTask":null,"SnapshotByTimeOffsetTask":null,"SampleSnapshotTask":null,"ImageSpriteTask":{"Status":"SUCCESS","ErrCode":0,"ErrCodeExt":"","Message":"SUCCESS","Progress":0,"BeginProcessTime":"0000-00-00T00:00:00Z","FinishTime":"0000-00-00T00:00:00Z","Input":{"Definition":10},"Output":{"TotalCount":10,"ImageUrlSet":["http:\/\/1305819496.vod2.myqcloud.com\/ee2c0b58vodtranscq1305819496\/f0e32f023701925920477195402\/imageSprite\/imageSprite_10_0.jpg"],"WebVttUrl":"http:\/\/1305819496.vod2.myqcloud.com\/ee2c0b58vodtranscq1305819496\/f0e32f023701925920477195402\/imageSprite\/imageSprite_10.vtt","Definition":10,"Height":80,"Width":142}},"CoverBySnapshotTask":null,"AdaptiveDynamicStreamingTask":null},{"Type":"CoverBySnapshot","TranscodeTask":null,"AnimatedGraphicTask":null,"SnapshotByTimeOffsetTask":null,"SampleSnapshotTask":null,"ImageSpriteTask":null,"CoverBySnapshotTask":{"Status":"SUCCESS","ErrCode":0,"ErrCodeExt":"","Message":"SUCCESS","Progress":0,"BeginProcessTime":"0000-00-00T00:00:00Z","FinishTime":"0000-00-00T00:00:00Z","Input":{"Definition":10,"PositionType":"Time","PositionValue":0,"WatermarkSet":[]},"Output":{"CoverUrl":"http:\/\/1305819496.vod2.myqcloud.com\/ee2c0b58vodtranscq1305819496\/f0e32f023701925920477195402\/coverBySnapshot\/coverBySnapshot_10_0.jpg"}},"AdaptiveDynamicStreamingTask":null},{"Type":"AdaptiveDynamicStreaming","TranscodeTask":null,"AnimatedGraphicTask":null,"SnapshotByTimeOffsetTask":null,"SampleSnapshotTask":null,"ImageSpriteTask":null,"CoverBySnapshotTask":null,"AdaptiveDynamicStreamingTask":{"Status":"SUCCESS","ErrCode":0,"ErrCodeExt":"","Message":"SUCCESS","Progress":0,"BeginProcessTime":"0000-00-00T00:00:00Z","FinishTime":"0000-00-00T00:00:00Z","Input":{"Definition":10,"WatermarkSet":[]},"Output":{"Definition":10,"Package":"HLS","DrmType":"","Url":"http:\/\/1305819496.vod2.myqcloud.com\/ee2c0b58vodtranscq1305819496\/f0e32f023701925920477195402\/adp.10.m3u8"}}}],"SessionContext":"","SessionId":"","TasksPriority":0,"TasksNotifyMode":"Finish"},"FileDeleteEvent":null,"PullCompleteEvent":null,"EditMediaCompleteEvent":null,"ComposeMediaCompleteEvent":null,"WechatPublishCompleteEvent":null,"TranscodeCompleteEvent":null,"ConcatCompleteEvent":null,"ClipCompleteEvent":null,"CreateImageSpriteCompleteEvent":null,"SnapshotByTimeOffsetCompleteEvent":null,"WechatMiniProgramPublishEvent":null}';
        //$data=json_decode($datajs,true);
        $EventType=$data['EventType'] ?? '';
        if($EventType=='ProcedureStateChanged'){
            $ProcedureStateChangeEvent=$data['ProcedureStateChangeEvent'] ?? [];
            if(!$ProcedureStateChangeEvent){
                echo 'OK1';
                exit;
            }
            $ErrCode=$ProcedureStateChangeEvent['ErrCode'] ?? '-1';
            if($ErrCode!=0){
                echo 'OK2';
                exit;
            }

            $FileId=$ProcedureStateChangeEvent['FileId'] ?? '';
            if($FileId==''){
                echo 'OK3';
                exit;
            }

            $up=['tx_trans'=>1];
            Db::name('course')->where(['tx_fileid'=>$FileId])->update($up);

            Db::name('course_lesson')->where(['tx_fileid'=>$FileId])->update($up);

            $info=Db::name('course_lesson')->field('courseid')->where(['tx_fileid'=>$FileId])->find();
            if($info){
                $courseid=$info['courseid'];
                $nums=DB::name('course_lesson')->where([['courseid','=',$courseid],['tx_trans','=',1]])->count();
                DB::name('course')->where('id',$courseid)->setField('lessons',$nums);
            }


        }

        echo 'OK';

    }

}