<?php


namespace app\teacher\controller;

use cmf\controller\TeacherBaseController;
use think\Db;
use app\models\PanoModel;
/**
 * 拍乐云相关
 */
class PanoController extends TeacherBaseController {

	public function cdnstart() {
        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $cdntype=$data['cndtype'] ?? '0';
        $type=$data['type'] ?? '0';

        if($stream==''){
            $this->error('信息错误');
        }
        $uid=session('teacher.id');

	    $url='https://api.pano.video/streaming/start';
	    $sign=$this->getPanoSign();

	    $Tracking=md5(time());
	    $header=[
	        'Content-Type: application/json',
	        'Authorization: PanoSign '.$sign,
            'Tracking-Id: '.$Tracking
        ];

        $pushurl=getCdnUrl($cdntype,'rtmp',$stream,1);
        if($pushurl==''){
            $this->error('信息错误');
        }

        $list='{
                          "userId": "'.$uid.'",
                          "streamType": 0, 
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 0
                      }';
        if($type==1){
            $list='
                      {
                          "streamType": 1, 
                          "userId": "'.$uid.'",
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 1
                      }';
        }

	    $data='{
          "channelId":"'.$stream.'",
          "streamList": [
            {
              "streamId": '.$uid.',
              "type": 1,
              "flag": 15,
              "layout": {
                  "mode": 3,
                  "scale": 0,
                  "canvas": {
                    "width": 640,
                    "height": 360,
                    "color": "#000000"
                  },
                  "customModeCells":[
                      '.$list.'
                  ]
              },
              "url": "'.$pushurl.'"
            }
          ]
        }';

        //print_r($Tracking);
        //print_r($data);

	    $res=curl_post($url,$data,$header);

        //var_dump($res);
        $this->success();
    }

    public function cdnup(){
        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $type=$data['type'] ?? '0';

        if($stream==''){
            $this->error('信息错误');
        }
        $uid=session('teacher.id');

        $url='https://api.pano.video/streaming/update';
        $sign=$this->getPanoSign();

        $Tracking=md5(time());
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
            'Tracking-Id: '.$Tracking
        ];

        $list='{
                          "userId": "'.$uid.'",
                          "streamType": 0, 
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 0
                      }';
        if($type==1){
            $list='{
                          "userId": "'.$uid.'",
                          "streamType": 0, 
                          "x": 0.45,
                          "y": 0.45,
                          "width": 0.1,
                          "height": 0.1,
                          "zOrder": 0
                      },
                      {
                          "streamType": 1, 
                          "userId": "'.$uid.'",
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 1
                      }';
        }

        $data='{
          "channelId":"'.$stream.'",
          "streamList": [
            {
              "streamId": '.$uid.',
              "layout": {
                  "mode": 3,
                  "scale": 0,
                  "canvas": {
                    "width": 640,
                    "height": 360,
                    "color": "#000000"
                  },
                  "customModeCells":[
                      '.$list.'
                  ]
              }
            }
          ]
        }';

        //print_r($Tracking);
        //print_r($data);

        $res=curl_post($url,$data,$header);

        //var_dump($res);
        $this->success();
    }

    public function cdnstop(){
        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';

        if($stream==''){
            $this->error('信息错误');
        }

        $url='https://api.pano.video/streaming/stop';
        $sign=$this->getPanoSign();
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
        ];

        $data='{
          "channelId":"'.$stream.'"
        }';

        $res=curl_post($url,$data,$header);


        $this->success();
    }

    public function recordstart(){

        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $iswhite=$data['iswhite'] ?? '0';
        $isscreen=$data['isscreen'] ?? '0';
        $linknums=$data['linknums'] ?? '0';
        if($stream==''){
            $this->error('信息错误');
        }
        $uid=session('teacher.id');

        $url='https://api.pano.video/recording/start';
        $sign=$this->getPanoSign();
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
        ];

        $list='{
              "userId": "'.$uid.'",
              "streamType": 0, 
              "x": 0,
              "y": 0,
              "width": 1,
              "height": 1,
              "zOrder": 0
          }';
        if($iswhite==1){
            /* 小班课 */
            $list='';
            if($isscreen==1){
                $list.='{
                          "streamType": 1,
                          "userId": "'.$uid.'",
                          "x": 0,
                          "y": 0,
                          "width": 0.8,
                          "height": 1,
                          "zOrder": 1
                      }';
            }else{

                $list.='{
                          "streamType": 2, 
                          "whiteboardId": "default", 
                          "x": 0,
                          "y": 0,
                          "width": 0.8,
                          "height": 1,
                          "zOrder": 1
                      }';
            }
            $nums=$linknums+1;
            $w=0.2;
            $h=0.2;
            $ws=1;
            if($nums>10){
                $w=0.06;
                $ws=3;
            }else if($nums>5){
                $w=0.1;
                $ws=2;
            }

            for($i=0;$i<$nums;$i++){
                $c=$i;
                $d=0;
                if($ws!=1){
                    $c=floor($i/$ws);
                    $d=floor($i%$ws);
                }
                $x=0.8+$w*$d;
                $y=0+$h*$c;
                if($i==0){
                    $list.=',{
                      "userId": "'.$uid.'",
                      "streamType": 0, 
                      "x": '.$x.',
                      "y": '.$y.',
                      "width": '.$w.',
                      "height": '.$h.',
                      "zOrder": 0
                  }';
                }else{
                    $list.=',{
                      "sequence": '.$i.',
                      "streamType": 0, 
                      "x": '.$x.',
                      "y": '.$y.',
                      "width": '.$w.',
                      "height": '.$h.',
                      "zOrder": 0
                  }';
                }
            }

        }else{
            /* 大班课 */
            if($isscreen==1){
                $list='{
                          "userId": "'.$uid.'",
                          "streamType": 0, 
                          "x": 0.45,
                          "y": 0.45,
                          "width": 0.1,
                          "height": 0.1,
                          "zOrder": 0
                      },{
                          "streamType": 1, 
                          "userId": "'.$uid.'",
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 1
                      }';
            }
        }



        $data='{
          "channelId":"'.$stream.'",
          "streamList": [
            {
              "streamId": '.$uid.',
              "type": 1,
              "flag": 15,
              "layout": {
                  "mode": 3,
                  "scale": 0,
                  "canvas": {
                    "width": 640,
                    "height": 360,
                    "color": "#000000"
                  },
                  "customModeCells":[
                      '.$list.'
                  ]
              },
              "format": 2
            }
          ]
        }';
//        var_dump($list);
//        var_dump($data);
        $res=curl_post($url,$data,$header);

//        var_dump($res);
        $this->success();
    }
    public function recordup(){

        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';
        $iswhite=$data['iswhite'] ?? '0';
        $isscreen=$data['isscreen'] ?? '0';
        $linknums=$data['linknums'] ?? '0';
        if($stream==''){
            $this->error('信息错误');
        }
        $uid=session('teacher.id');

        $url='https://api.pano.video/recording/update';
        $sign=$this->getPanoSign();
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
        ];

        $list='{
                          "userId": "'.$uid.'",
                          "streamType": 0, 
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 0
                      }';
        if($iswhite==1){
            /* 小班课 */
            $list='';
            if($isscreen==1){
                $list.='{
                          "streamType": 1,
                          "userId": "'.$uid.'",
                          "x": 0,
                          "y": 0,
                          "width": 0.8,
                          "height": 1,
                          "zOrder": 1
                      }';
            }else{

                $list.='{
                          "streamType": 2, 
                          "whiteboardId": "default", 
                          "x": 0,
                          "y": 0,
                          "width": 0.8,
                          "height": 1,
                          "zOrder": 1
                      }';
            }
            $nums=$linknums+1;
            $w=0.2;
            $h=0.2;
            $ws=1;
            if($nums>10){
                $w=0.06;
                $ws=3;
            }else if($nums>5){
                $w=0.1;
                $ws=2;
            }

            for($i=0;$i<$nums;$i++){
                $c=$i;
                $d=0;
                if($ws!=1){
                    $c=floor($i/$ws);
                    $d=floor($i%$ws);
                }
                $x=0.8+$w*$d;
                $y=0+$h*$c;
                if($i==0){
                    $list.=',{
                      "userId": "'.$uid.'",
                      "streamType": 0, 
                      "x": '.$x.',
                      "y": '.$y.',
                      "width": '.$w.',
                      "height": '.$h.',
                      "zOrder": 0
                  }';
                }else{
                    $list.=',{
                      "sequence": '.$i.',
                      "streamType": 0, 
                      "x": '.$x.',
                      "y": '.$y.',
                      "width": '.$w.',
                      "height": '.$h.',
                      "zOrder": 0
                  }';
                }
            }

        }else{
            /* 大班课 */
            if($isscreen==1){
                $list='{
                          "userId": "'.$uid.'",
                          "streamType": 0, 
                          "x": 0.45,
                          "y": 0.45,
                          "width": 0.1,
                          "height": 0.1,
                          "zOrder": 0 
                      },{
                          "streamType": 1, 
                          "userId": "'.$uid.'",
                          "x": 0,
                          "y": 0,
                          "width": 1,
                          "height": 1,
                          "zOrder": 1
                      }';
            }
        }



        $data='{
          "channelId":"'.$stream.'",
          "streamList": [
            {
              "streamId": '.$uid.',
              "layout": {
                  "mode": 3,
                  "scale": 0,
                  "canvas": {
                    "width": 640,
                    "height": 360,
                    "color": "#000000"
                  },
                  "customModeCells":[
                      '.$list.'
                  ]
              }
            }
          ]
        }';
//        var_dump($list);
        $res=curl_post($url,$data,$header);

//        var_dump($res);
        $this->success();
    }
    public function recordend(){

        $data      = $this->request->param();

        $stream=$data['stream'] ?? '';

        if($stream==''){
            $this->error('信息错误');
        }

        $url='https://api.pano.video/recording/stop';
        $sign=$this->getPanoSign();
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
        ];

        $data='{
          "channelId":"'.$stream.'"
        }';

        $res=curl_post($url,$data,$header);


        $this->success();
    }

    public function getrecordend(){

        $channelKey='225454148273832448';
        $PanoModel=new PanoModel();
        $res= $PanoModel->getrecordend($channelKey);

        var_dump($res);
        //return [];
    }

    public function getchannelKey(){
        $stream='490_459_507';
        $PanoModel=new PanoModel();
        $res= $PanoModel->getchannel($stream);

        var_dump($res);
        //return [];
    }

    public function closechannel(){
        $stream='490_459_508';
        $PanoModel=new PanoModel();
        $res= $PanoModel->closechannel($stream);

        var_dump($res);
        //return [];
    }
    protected function getPanoSign(){
	    $PanoModel=new PanoModel();
        return $PanoModel->getPanoSign();
    }
}


