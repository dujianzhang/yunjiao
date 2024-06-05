<?php

/* 拍乐云回调 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class PanoController extends HomebaseController{

    function back(){

        $data = $this->request->param();
        $path=CMF_DATA.'log/pano/';
        if (!is_dir($path)) {
            @mkdir($path);
        }
        file_put_contents($path.'pano_back_'.date('Ymd').'.log',date('Ymd H:i:s').'data:'.json_encode($data).PHP_EOL,FILE_APPEND);

        $eventType=$data['eventType'] ?? '';
        if($eventType=='101'){
            $eventData=$data['eventData'] ?? [];

            $channelKey=$eventData['channelKey'] ?? '';
            $channelId=$eventData['channelId'] ?? '';
            if($channelKey!='' && $channelId!=''){
                $stream_s=explode('_',$channelId);
                $uid=$stream_s[0] ?? 0;
                $courseid=$stream_s[1] ?? 0;
                $lessonid=$stream_s[2] ?? 0;
                if($lessonid>0){
                    Db::name('course_lesson')->where(['id'=>$lessonid,'courseid'=>$courseid])->update(['url'=>$channelKey]);
                }else{
                    Db::name('course')->where(['id'=>$courseid])->update(['url'=>$channelKey]);
                }
            }
        }
        echo 'OK';

    }

}