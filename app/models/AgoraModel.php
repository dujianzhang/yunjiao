<?php

namespace app\models;

use think\Db;

class AgoraModel
{
    /* 生成token */
    public static function getToken($channel,$uid){
        require_once CMF_ROOT.'sdk/AgoraKey/RtcTokenBuilder.php';

        $configpri=getConfigPri();

        $appID = $configpri['sound_appid'];
        $appCertificate = $configpri['sound_certify'];

        $channelName = $channel;
        $uid = (int)$uid;

        $role = \RtcTokenBuilder::RoleAttendee;
        $expireTimeInSeconds = 3600;
        $currentTimestamp = (new DateTime("now", new DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        $token = \RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, $uid, $role, $privilegeExpiredTs);

        return $token;
    }

    public static function getHeader(){

        $configpri=getConfigPri();
        $agora_api_id=$configpri['agora_api_id'];
        $agora_api_key=$configpri['agora_api_key'];
        $auth=base64_encode($agora_api_id.':'.$agora_api_key);

        $headers = array(
            'Content-type:application/json',
            'Authorization: Basic '.$auth,
        );

        return $headers;
    }

    /* 创建录制 */
    public static function createRecord($channel,$uid){

        $rs=['code'=>1000,'msg'=>'','data'=>[]];

        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        $url="https://api.agora.io/v1/apps/{$sound_appid}/cloud_recording/acquire";
        $data='{
            "cname": "'.$channel.'",
            "uid": "4294967295",
            "clientRequest": {}
        }';


        $headers = self::getHeader();

        $res=self::curl($url,$data,$headers);

        self::log(' create channel:'.$channel);
        self::log(' create uid:'.$uid);
        self::log(' create res:'.json_encode($res));

        $res_a=json_decode($res['res'],true);
        if($res['code']!=200){
            $rs['code']=$res['code'];
            $msg=isset($res_a['reason'])?$res_a['reason']: (isset($res_a['message'])?$res_a['message']:'');
            $rs['msg']=$msg;
            return $rs;
        }

        $rs['code']=0;
        $rs['data']=$res_a;

        return $rs;
    }
    /* 开始录制 */
    public static function startRecord($channel,$uid,$resourceid,$token=null,$mode='mix'){

        $rs=['code'=>1000,'msg'=>'','data'=>[]];

        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        $url="https://api.agora.io/v1/apps/{$sound_appid}/cloud_recording/resourceid/{$resourceid}/mode/{$mode}/start";

        $uids=["{$uid}"];
        if($mode=='mix'){
            $uids[]='999999999';
        }

        $uids_s=json_encode($uids);

        $storage = cmf_get_option('storage');

        if (empty($storage['type'])) {
            $storage['type'] = 'Local';
        }
        $vendor=0;
        $region=-1;

        $type=$storage['type'];
        if($type=='Local'){

            $storage_config=Db::name('plugin')->where(['name'=>'Qiniu'])->value('config');
            if($storage_config){
                $storage_a=json_decode($storage_config,true);
                if( !(!isset($storage_a['accessKey']) || $storage_a['accessKey']=='' || !isset($storage_a['secretKey']) || $storage_a['secretKey']=='' || !isset($storage_a['bucket']) || $storage_a['bucket']=='' || !isset($storage_a['zone']) || $storage_a['zone']=='')){
                    $type='Qiniu';
                }
            }
            if($type=='Local'){
                $storage_config=Db::name('plugin')->where(['name'=>'Oss'])->value('config');
                if($storage_config){
                    $storage_a=json_decode($storage_config,true);
                    if( !(!isset($storage_a['accessKeyId']) || $storage_a['accessKeyId']=='' || !isset($storage_a['accessKeySecret']) || $storage_a['accessKeySecret']==''  || !isset($storage_a['bucket']) || $storage_a['bucket']=='' || !isset($storage_a['regionId']) || $storage_a['regionId']=='')){
                        $type='Oss';
                    }
                }
            }
            if($type=='Local'){
                $storage_config=Db::name('plugin')->where(['name'=>'Cos'])->value('config');
                if($storage_config){
                    $storage_a=json_decode($storage_config,true);
                    if( !(!isset($storage_a['secretId']) || $storage_a['secretId']=='' || !isset($storage_a['secretKey']) || $storage_a['secretKey']==''  || !isset($storage_a['bucket']) || $storage_a['bucket']=='' || !isset($storage_a['region']) || $storage_a['region']=='')){
                        $type='Cos';
                    }
                }
            }
            if($type=='Local'){
                $rs['code']=1005;
                $rs['msg']='请先配置云存储';
                return $rs;
            }
        }
        if($type=='Qiniu'){
            $vendor=0;
            /* 获取七牛存储信息 */
            $storage_config=Db::name('plugin')->where(['name'=>'Qiniu'])->value('config');
            if(!$storage_config){
                $rs['code']=1001;
                $rs['msg']='请先配置七牛云存储';
                return $rs;
            }
            $storage_a=json_decode($storage_config,true);
            if( !isset($storage_a['accessKey']) || $storage_a['accessKey']=='' || !isset($storage_a['secretKey']) || $storage_a['secretKey']=='' || !isset($storage_a['bucket']) || $storage_a['bucket']=='' || !isset($storage_a['zone']) || $storage_a['zone']==''){
                $rs['code']=1002;
                $rs['msg']='请先配置七牛云存储';
                return $rs;
            }

            $accessKey=$storage_a['accessKey'];
            $secretKey=$storage_a['secretKey'];
            $bucket=$storage_a['bucket'];
            $zone=$storage_a['zone'];
            $zone=strtolower($zone);
            $zones=[
                'z0'=>0,
                'z1'=>1,
                'z2'=>2,
                'na0'=>3,
                'as0'=>4
            ];

            $region=$zones[$zone] ?? -1;
        }
        if($type=='Oss'){
            $vendor=2;
            $storage_config=Db::name('plugin')->where(['name'=>'Oss'])->value('config');
            if(!$storage_config){
                $rs['code']=1001;
                $rs['msg']='请先配置阿里云存储';
                return $rs;
            }
            $storage_a=json_decode($storage_config,true);
            if( !isset($storage_a['accessKeyId']) || $storage_a['accessKeyId']=='' || !isset($storage_a['accessKeySecret']) || $storage_a['accessKeySecret']=='' || !isset($storage_a['bucket']) || $storage_a['bucket']=='' || !isset($storage_a['regionId']) || $storage_a['regionId']==''){
                $rs['code']=1002;
                $rs['msg']='请先配置阿里云存储';
                return $rs;
            }

            $accessKey=$storage_a['accessKeyId'];
            $secretKey=$storage_a['accessKeySecret'];
            $bucket=$storage_a['bucket'];
            $zone=$storage_a['regionId'];
            $zone=strtolower($zone);

            $zones=['cn-hangzhou'=>0,'cn-shanghai'=>1,'cn-qingdao'=>2,'cn-beijing'=>3,'cn-zhangjiakou'=>4,'cn-huhehaote'=>5,'cn-shenzhen'=>6,'cn-hongkong'=>7,'us-west-1'=>8,'us-east-1'=>9,'ap-southeast-1'=>10,'ap-southeast-2'=>11,'ap-southeast-3'=>12,'ap-southeast-5'=>13,'ap-northeast-1'=>14,'ap-south-1'=>15,'eu-central-1'=>16,'eu-west-1'=>17,'eu-east-1'=>18,'ap-southeast-6'=>19,'cn-heyuan'=>20,'cn-guangzhou'=>21,'cn-chengdu'=>22];

            $region=$zones[$zone] ?? -1;

        }
        if($type=='Cos'){
            $vendor=3;
            $storage_config=Db::name('plugin')->where(['name'=>'Cos'])->value('config');
            if(!$storage_config){
                $rs['code']=1001;
                $rs['msg']='请先配置腾讯云存储';
                return $rs;
            }
            $storage_a=json_decode($storage_config,true);
            if( !isset($storage_a['secretId']) || $storage_a['secretId']=='' || !isset($storage_a['secretKey']) || $storage_a['secretKey']=='' || !isset($storage_a['bucket']) || $storage_a['bucket']=='' || !isset($storage_a['region']) || $storage_a['region']==''){
                $rs['code']=1002;
                $rs['msg']='请先配置腾讯云存储';
                return $rs;
            }

            $accessKey=$storage_a['secretId'];
            $secretKey=$storage_a['secretKey'];
            $bucket=$storage_a['bucket'];
            $zone=$storage_a['region'];
            $zone=strtolower($zone);

            $zones=['ap-beijing-1'=>0,'ap-beijing'=>1,'ap-shanghai'=>2,'ap-guangzhou'=>3,'ap-chengdu'=>4,'ap-chongqing'=>5,'ap-shenzhen-fsi'=>6,'ap-shanghai-fsi'=>7,'ap-beijing-fsi'=>8,'ap-hongkong'=>9,'ap-singapore'=>10,'ap-mumbai'=>11,'ap-seoul'=>12,'ap-bangkok'=>13,'ap-tokyo'=>14,'na-siliconvalley'=>15,'na-ashburn'=>16,'na-toronto'=>17,'eu-frankfurt'=>18,'eu-moscow'=>19];

            $region=$zones[$zone] ?? -1;

        }

        if($region<0){
            $rs['code']=1003;
            $rs['msg']='该存储区域暂不支持';
            return $rs;
        }

        $data='{
            "cname": "'.$channel.'",
            "uid": "4294967295",
            "clientRequest": {
                "recordingConfig": {
                    "maxIdleTime": 3600,
                    "streamTypes": 2,
                    "channelType": 1,
                    "videoStreamType": 0,
                    "transcodingConfig": {
                        "height": 480, 
                        "width": 640,
                        "bitrate": 500,
                        "fps": 15,
                        "mixedVideoLayout": 1,
                        "backgroundColor": "#000000"
                    },
                    "subscribeVideoUids": '.$uids_s.',
                    "subscribeAudioUids": '.$uids_s.',
                    "subscribeUidGroup": 0
                },
                "recordingFileConfig": {
                    "avFileType": [
                        "hls"
                    ]
                },
                "storageConfig": {
                    "vendor": '.$vendor.',
                    "accessKey": "'.$accessKey.'",
                    "secretKey": "'.$secretKey.'",
                    "bucket": "'.$bucket.'",
                    "region": '.$region.',
                    "fileNamePrefix": [
                        "record"
                    ]
                }
            }
        }';

        $headers = self::getHeader();

        $res=self::curl($url,$data,$headers);

        self::log(' stop channel:'.$channel);
        self::log(' stop uid:'.$uid);
        self::log(' stop res:'.json_encode($res));

        $res_a=json_decode($res['res'],true);

        if($res['code']!=200){
            $rs['code']=$res['code'];
            $msg=isset($res_a['reason'])?$res_a['reason']: (isset($res_a['message'])?$res_a['message']:'');
            $rs['msg']=$msg;
            return $rs;
        }

        $rs['code']=0;
        $rs['data']=$res_a;

        return $rs;
    }
    /* 结束录制 */
    public static function stopRecord($channel,$uid,$resourceid,$sid,$token=null,$mode='mix'){
        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        $url="https://api.agora.io/v1/apps/{$sound_appid}/cloud_recording/resourceid/{$resourceid}/sid/{$sid}/mode/{$mode}/stop";

        $data='{"cname": "'.$channel.'","uid": "4294967295","clientRequest": {}}';

        $headers = self::getHeader();

        $res=self::curl($url,$data,$headers);

        self::log(' stop channel:'.$channel);
        self::log(' stop uid:'.$uid);
        self::log(' stop res:'.json_encode($res));
        $res_a=json_decode($res['res'],true);

        if($res['code']!=200){
            $rs['code']=$res['code'];
            $msg=isset($res_a['reason'])?$res_a['reason']: (isset($res_a['message'])?$res_a['message']:'');
            $rs['msg']=$msg;
            return $rs;
        }

        $rs['code']=0;
        $rs['data']=$res_a;

        return $rs;

    }

    /* 旁路推流 */
    public static function getConverters($channel){
        $rs=['code'=>1000,'msg'=>'','info'=>[]];

        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        $url="https://api.agora.io/v1/projects/{$sound_appid}/channels/{$channel}/rtmp-converters";

        $headers = self::getHeader();
        $res=self::curl($url,'',$headers,'GET');
        self::log(' getConverters channel:'.$channel);
        self::log(' getConverters res:'.json_encode($res));

        $res_a=json_decode($res['res'],true);
        if(!$res_a){
            $rs['msg']='信息错误，请重试';
            return $rs;
        }
        if(isset($res_a['reason'])){
            $rs['msg']=$res_a['reason'];
            return $rs;
        }

        if(isset($res_a['message'])){
            $rs['msg']=$res_a['message'];
            return $rs;
        }

        if(!isset($res_a['success'])){
            $rs['msg']='信息错误，请重试';
            return $rs;
        }

        $rs['code']=0;
        $rs['info']=$res_a['data']['members'] ?? [];
        return $rs;
    }

    public static function createCdn($channel,$uid,$push){
        $rs=['code'=>1000,'msg'=>'','info'=>[]];

        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        /*
            region   cn：中国大陆  ap：除中国大陆以外的亚洲区域  na：北美  eu：欧洲
        */
        /* 创建 */
        $region='cn';
        $url="https://api.agora.io/{$region}/v1/projects/{$sound_appid}/rtmp-converters";

        $data='{
            "converter":{
                "name":"'.$channel.'",
                "rtmpUrl":"'.$push.'",
                "transcodeOptions":{
                    "rtcChannel":"'.$channel.'",
                    "audioOptions":{},
                    "videoOptions":{
                        "canvas":{
                            "width":1280,
                            "height":720
                        },
                        "layoutType":0,
                        "layout":[
                            {
                                "rtcStreamUid": '.$uid.',
                                "region": {
                                    "xPos": 0,
                                    "yPos": 0,
                                    "zIndex": 1,
                                    "width": 1280,
                                    "height": 720
                                },
                                "fillMode": "fit"
                            }
                        ],
                        "bitrate":2260
                    }
                }
            }
        }';

        $headers = self::getHeader();
        $res=self::curl($url,$data,$headers);
        self::log(' createCdn channel:'.$channel);
        self::log(' createCdn uid:'.$uid);
        self::log(' createCdn push:'.$push);
        self::log(' createCdn res:'.json_encode($res));

        $res_a=json_decode($res['res'],true);
        if(!$res_a){
            $rs['msg']='信息错误，请重试';
            return $rs;
        }

        if(isset($res_a['reason'])){
            $rs['msg']=$res_a['reason'];
            return $rs;
        }

        if(isset($res_a['message'])){
            $rs['msg']=$res_a['message'];
            return $rs;
        }

        $converterId=$res_a['converter']['id'] ?? '';
        if($converterId==''){
            $rs['msg']='信息错误，请重试';
            return $rs;
        }

        $rs['code']=0;
        $rs['info']=compact('converterId');

        return $rs;

    }

    public static function updateCdn($converterId,$channel,$uid,$maxResolutionUid){
        $rs=['code'=>1000,'msg'=>'','info'=>[]];

        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        /*
            region   cn：中国大陆  ap：除中国大陆以外的亚洲区域  na：北美  eu：欧洲
        */
        $region='cn';
        $sequence=time();

        $url="https://api.agora.io/{$region}/v1/projects/{$sound_appid}/rtmp-converters/{$converterId}?sequence={$sequence}";
        $width=1280;
        $height=720;
        $data='{
            "converter":{
                "transcodeOptions":{
                    "videoOptions":{
                        "layout":[';
        if($uid!=$maxResolutionUid){
            $w_d= floor($width * 0.8);
            $h_d= floor($height * 0.8);
            $w_s= floor($width * 0.2);
            $h_s= floor($height * 0.2);
                            $data.='{
                                "rtcStreamUid": '.$uid.',
                                "region": {
                                    "xPos": '.$w_d.',
                                    "yPos": 0,
                                    "zIndex": 2,
                                    "width": '.$w_s.',
                                    "height": '.$h_s.'
                                },
                                "fillMode": "fit"
                            },
                            {
                                "rtcStreamUid": '.$maxResolutionUid.',
                                "region": {
                                    "xPos": 0,
                                    "yPos": 0,
                                    "zIndex": 1,
                                    "width": '.$w_d.',
                                    "height": '.$height.'
                                },
                                "fillMode": "fit"
                            }';
        }else{
                            $data.='{
                                "rtcStreamUid": '.$uid.',
                                "region": {
                                    "xPos": 0,
                                    "yPos": 0,
                                    "zIndex": 1,
                                    "width": '.$width.',
                                    "height": '.$height.'
                                },
                                "fillMode": "fit"
                            }';
        }

        $data.='
                        ]
                    }
                }
            },
            "fields": "transcodeOptions.videoOptions.layout"
            
        }';
        $headers = self::getHeader();

        $res=self::curl($url,$data,$headers,'PATCH');
        self::log(' updateCdn channel:'.$channel);
        self::log(' updateCdn maxResolutionUid:'.$maxResolutionUid);
        self::log(' updateCdn res:'.json_encode($res));

        $res_a=json_decode($res['res'],true);
        if(!$res_a){
            $rs['msg']='信息错误，请重试';
            return $rs;
        }

        if(isset($res_a['reason'])){
            $rs['msg']=$res_a['reason'];
            return $rs;
        }
        $rs['code']=0;
        return $rs;
    }

    public static function delCdn($converterId,$channel){
        $rs=['code'=>1000,'msg'=>'','info'=>[]];

        $configpri=getConfigPri();
        $sound_appid=$configpri['sound_appid'];

        /*
            region   cn：中国大陆  ap：除中国大陆以外的亚洲区域  na：北美  eu：欧洲
        */
        $region='cn';
        $url="https://api.agora.io/{$region}/v1/projects/{$sound_appid}/rtmp-converters/{$converterId}";

        $headers = self::getHeader();
        $res=self::curl($url,'',$headers,'DELETE');
        self::log(' delCdn converterId:'.$converterId);
        self::log(' delCdn channel:'.$channel);
        self::log(' delCdn res:'.json_encode($res));

        $res_a=json_decode($res['res'],true);
        if(!$res_a){
            $rs['msg']='信息错误，请重试';
            return $rs;
        }

        if(isset($res_a['reason'])){
            $rs['msg']=$res_a['reason'];
            return $rs;
        }

        $rs['code']=0;
        return $rs;

    }

    /* 打印log */
    public static function log($msg,$name='agoraRecord'){
        $path=CMF_ROOT.'data/log/agora';
        if (!is_dir($path)) {
            @mkdir($path);
        }

        file_put_contents($path.'/'.$name.'_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').' '.$msg."\r\n",FILE_APPEND);
    }


    public static function curl($url,$curlPost='',$headers=[],$method='POST'){

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if($method=='POST'){
            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_POST, true);
        }

        if($curlPost){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        }

        if($headers){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 跳過證書檢查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);  // 從證書中檢查SSL加密算法是否存在

        $return_str = curl_exec($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ['code'=>$httpCode,'res'=>$return_str];
    }
}
