<?php

namespace app\models;

use think\Model;

class PanoModel extends Model
{

    public static function getPanoSign(){

        $configpri=getConfigPri();

        $appId =  $configpri['pano_appid'];
        $appSecret = $configpri['pano_secret'];

        $timestamp = time();
        // https://www.php.net/manual/zh/function.hash-hmac.php
        // raw_output 设置为 true 输出原始二进制数据
        $signature = base64_encode(hash_hmac("sha256", $appId . $timestamp, $appSecret, true));
        $panoSign = $appId . "." . $timestamp . "." . $signature;
        return $panoSign;
    }

    public static function getchannel($stream){

        $url='https://api.pano.video/channel/data';
        $sign=self::getPanoSign();
        $Tracking=md5(time());
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
            'Tracking-Id: '.$Tracking
        ];

        $data='{
          "channelId":"'.$stream.'"
        }';

        $res=curl_post($url,$data,$header);

        return $res;
    }

    public static function closechannel($stream){

        $url='https://api.pano.video/channel/close';
        $sign=self::getPanoSign();
        $Tracking=md5(time());
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
            'Tracking-Id: '.$Tracking
        ];

        $data='{
          "channelId":"'.$stream.'"
        }';

        $res=curl_post($url,$data,$header);

        return $res;
    }

    public static function getrecordend($channelKey){

        $url='https://api.pano.video/recording/url';
        $sign=self::getPanoSign();
        $Tracking=md5(time());
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
            'Tracking-Id: '.$Tracking
        ];

        $data='{
          "channelKey":"'.$channelKey.'",
          "duration": 86400 
        }';

        $res=curl_post($url,$data,$header);

        if($res['code']!=200){
            return [];
        }
        $urls=[];
        $list=json_decode($res['res'],true);

        foreach ($list as $k=>$v){
            $url= $v['url'] ?? '';
            if($url==''){
                continue;
            }
            $urls[]=$url;
        }
        $urls=array_reverse($urls);
        return $urls;
    }

    public static function recordend($stream){

        $url='https://api.pano.video/recording/stop';
        $sign=self::getPanoSign();
        $header=[
            'Content-Type: application/json',
            'Authorization: PanoSign '.$sign,
        ];

        $data='{
          "channelId":"'.$stream.'"
        }';

        $res=curl_post($url,$data,$header);

        $path=CMF_DATA.'log/pano/';
        if (!is_dir($path)) {
            @mkdir($path);
        }
        file_put_contents($path.'pano_stop_'.date('Ymd').'.log',date('Ymd H:i:s').'stream:'.$stream.PHP_EOL,FILE_APPEND);
        file_put_contents($path.'pano_stop_'.date('Ymd').'.log',date('Ymd H:i:s').'res:'.json_encode($res).PHP_EOL,FILE_APPEND);
        return 1;
    }
}