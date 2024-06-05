<?php

use think\Db;
use cmf\lib\Storage;
use cmf\lib\Upload;
// 应用公共文件
//error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once dirname(__FILE__).'/redis.php';

/* 去除NULL 判断空处理 主要针对字符串类型*/
function checkNull($checkstr){
    $checkstr=trim($checkstr);
    //$checkstr=urldecode($checkstr);

    if( strstr($checkstr,'null') || (!$checkstr && $checkstr!=0 ) ){
        $str='';
    }else{
        $str=$checkstr;
    }
    return $str;
}

/* 校验签名 */
function getSign($data){

    $key='400d069a791d51ada8af3e6c2979bcd7';
    $str='';
    ksort($data);
    foreach($data as $k=>$v){
        $str.=$k.'='.$v.'&';
    }
    $str.=$key;
    $newsign=md5($str);

    return $newsign;
}

/* 校验签名 */
function checkSign($data,$sign){
    if($sign==''){
        return 0;
    }
    $key='';
    $str='';
    ksort($data);
    foreach($data as $k=>$v){
        $str.=$k.'='.$v.'&';
    }
    $str.=$key;
    $newsign=md5($str);

    if($sign==$newsign){
        return 1;
    }
    return 0;
}

/* 校验邮箱 */
function checkEmail($email){
    $preg='/^(\w-*\.*)+@(\w-?)+(\.\w{2,})+$/';
    $isok=preg_match($preg,$email);
    if($isok){
        return 1;
    }
    return 0;
}

/* 校验密码 */
function checkPass($pass){
    /* 必须包含字母、数字 */
    $preg='/^(?=.*[A-Za-z])(?=.*[0-9])[a-zA-Z0-9~!@&%#_]{6,20}$/';
    $isok=preg_match($preg,$pass);
    if($isok){
        return 1;
    }
    return 0;
}

/* 检测用户是否存在 */
function checkUser($where){
    if($where==''){
        return 0;
    }

    $isexist=Db::name('users')->where($where)->find();
    if($isexist){
        return 1;
    }

    return 0;
}

/* 检验手机号 */
function checkMobile($mobile){
    $ismobile = preg_match("/^1[3|4|5|6|7|8|9]\d{9}$/",$mobile);
    if($ismobile){
        return 1;
    }

    return 0;

}

/* 随机数 */
function random($length = 6 , $numeric = 1) {
    PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
    if($numeric) {
        $hash = sprintf('%0'.$length.'d', mt_rand(0, pow(10, $length) - 1));
    } else {
        $hash = '';
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789abcdefghjkmnpqrstuvwxyz_';
        $max = strlen($chars) - 1;
        for($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
    }
    return $hash;
}

/* 发送验证码 */
function sendCode($account,$code){
    $rs = array('code' => 1001, 'msg' => '发送失败');
    $config = getConfigPri();

    if(!$config['sendcode_switch']){
        $rs['code']=667;
        $rs['msg']='123456';
        return $rs;
    }

    // $res=sendCodeByCCP($account,$code);

    $res=sendCodeByTx($account,$code);

    //$res=sendEmailCode($account,$code);

    return $res;
}

/* 容联云短信验证码 */
function sendCodeByCCP($mobile,$code){
    $rs = array('code' => 0, 'msg' => '', 'info' => array());

    $config = getConfigPri();

    require_once CMF_ROOT.'sdk/ronglianyun/CCPRestSDK.php';

    //主帐号
    $accountSid= $config['ccp_sid'];
    //主帐号Token
    $accountToken= $config['ccp_token'];
    //应用Id
    $appId=$config['ccp_appid'];
    //请求地址，格式如下，不需要写https://
    $serverIP='app.cloopen.com';
    //请求端口
    $serverPort='8883';
    //REST版本号
    $softVersion='2013-12-26';

    $tempId=$config['ccp_tempid'];

    file_put_contents(CMF_ROOT.'data/sendCode_ccp_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').' 提交参数信息 post_data: accountSid:'.$accountSid.";accountToken:{$accountToken};appId:{$appId};tempId:{$tempId}\r\n",FILE_APPEND);

    $rest = new \REST($serverIP,$serverPort,$softVersion);
    $rest->setAccount($accountSid,$accountToken);
    $rest->setAppId($appId);

    $datas=[];
    $datas[]=$code;

    $result = $rest->sendTemplateSMS($mobile,$datas,$tempId);
    file_put_contents(CMF_ROOT.'data/sendCode_ccp_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').' 提交参数信息 result:'.json_encode($result)."\r\n",FILE_APPEND);

     if($result == NULL ) {
        $rs['code']=1002;
        $rs['msg']="发送失败";
        return $rs;
     }
     if($result->statusCode!=0) {
        //echo "error code :" . $result->statusCode . "<br>";
        //echo "error msg :" . $result->statusMsg . "<br>";
        //TODO 添加错误处理逻辑
        $rs['code']=1002;
        //$rs['msg']=$gets['SubmitResult']['msg'];
        $rs['msg']="发送失败";
        return $rs;
     }

    return $rs;
}

function getSiteUrl(){
    $configpub=getConfigPub();

    return $configpub['site_url'] ?? '';
}
/**
 * 转化数据库保存图片的文件路径，为可以访问的url
 * @param string $file  文件路径，数据存储的文件相对路径
 * @param string $style 图片样式,支持各大云存储
 * @return string 图片链接
 */
function get_upload_path($file, $style = 'watermark')
{
    if (empty($file)) {
        return '';
    }

    if (strpos($file, "http") === 0) {
        $filepath= $file;
    } else if (strpos($file, "/") === 0) {
        $filepath= getSiteUrl() . $file;
    } else {

        $storage = Storage::instance();
        $filepath= $storage->getImageUrl($file, $style);
    }
    $filepath=urldecode($filepath);
    return html_entity_decode($filepath);
}

/* 公共配置 */
function getConfigPub() {
    $key='getConfigPub';
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }
    $config=getcaches($key);
    if(!$config){
        $config= Db::name('option')
                ->field('option_value')
                ->where(['option_name'=>'site_info'])
                ->find();
        $config=json_decode($config['option_value'],true);
        setcaches($key,$config);
    }
    $GLOBALS[$key]=$config;
    return 	$config;
}

/* 私密配置 */
function getConfigPri() {
    $key='getConfigPri';
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }
    $config=getcaches($key);
    if(!$config){
        $config= Db::name('option')
                ->field('option_value')
                ->where(['option_name'=>'configpri'])
                ->find();
        $config=json_decode($config['option_value'],true);
        setcaches($key,$config);
    }

    if(isset($config['login_type'])){
        if(is_array($config['login_type'])){

        }else if($config['login_type']){
            $config['login_type']=preg_split('/,|，/',$config['login_type']);
        }else{
            $config['login_type']=array();
        }
    }

    if(isset($config['share_type'])){
        if(is_array($config['share_type'])){

        }else if($config['share_type']){
            $config['share_type']=preg_split('/,|，/',$config['share_type']);
        }else{
            $config['share_type']=array();
        }
    }

    $GLOBALS[$key]=$config;

    return 	$config;
}

/**
 * 获取网络文件地址大小
 * @param $url
 * @return mixed|string
 */
function remote_filesize($url)
{
    ob_start();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1);

    $ok = curl_exec($ch);
    curl_close($ch);
    $head = ob_get_contents();
    ob_end_clean();

    $regex = '/Content-Length:\s([0-9].+?)\s/';
    $count = preg_match($regex, $head, $matches);

    return isset($matches[1]) ? $matches[1] : "unknown";
}


/* 判断token */
function checkToken($uid,$token) {
    if($uid<1 || $token==''){
        return 700;
    }
    $key="token_".$uid;
    $userinfo=getcaches($key);
    if(!$userinfo){
        $userinfo= Db::name('users_token')
                    ->field('token,expire_time')
                    ->where(['user_id'=>$uid])
                    ->find();
        if($userinfo){
            setcaches($key,$userinfo);
        }
    }

    if(!$userinfo || $userinfo['token']!=$token || $userinfo['expire_time']<time()){
        return 700;
    }

    return 	0;

}

/* 用户基本信息 */
function getUserInfo($uid,$type=0) {

    $key="userinfo_".$uid;
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }

    $info=getcaches($key);
    if(!$info){
        $info=Db::name('users')
                ->field('id,user_nickname,avatar,avatar_thumb,sex,signature,birthday,type,signoryid,identity')
                ->where(['id'=>$uid])
                ->find();
        if($info){

        }else if($type==1){
            return 	$info;

        }else{
            $info['id']=$uid;
            $info['user_nickname']=lang('用户不存在');
            $info['avatar']='/default.png';
            $info['avatar_thumb']='/default_thumb.png';
            $info['sex']='0';
            $info['signature']='';
            $info['birthday']='0';
        }
        if($info){
            setcaches($key,$info);
        }
    }

    if($info){
        $info=handleUser($info);
    }

    $GLOBALS[$key]=$info;

    return 	$info;
}

/* 处理用户信息 */
function handleUser($info){

    $info['avatar']=get_upload_path($info['avatar']);
    $info['avatar_thumb']=get_upload_path($info['avatar_thumb']);

    if(isset($info['signoryid'])){
        $info['signory']=getSignory($info['signoryid']);
    }

    if(isset($info['identity'])){
        $info['identitys']=getIdentity($info['identity']);
    }
    if(isset($info['identity'])){
        $info['identityinfos']=getIdentity($info['identity']);
    }
    //unset($info['birthday']);

    return $info;
}


/* 年龄计算 */
function getAge($time=0){
    if($time<=0){
        return '';
    }
    $nowtime=time();
    $y_n=date('Y',$nowtime);
    $y_b=date('Y',$time);

    $age=$y_n - $y_b;

    return (string)$age;
}


/* 统计粉丝数 */
function getFansNum($uid){
    $nums =Db::name('users_attention')
            ->where(['touid'=>$uid])
            ->count();
    return (string)$nums;
}
/* 统计关注数 */
function getFollowNum($uid){
    $nums =Db::name('users_attention')
            ->where(['uid'=>$uid])
            ->count();
    return (string)$nums;
}

/* 是否关注 */
function isAttent($uid,$liveuid){
    if($uid<1 || $liveuid<1 || $uid==$liveuid){
        return '0';
    }

    $isok =Db::name('users_attention')
            ->field('*')
            ->where(['uid'=>$uid,'touid'=>$liveuid])
            ->find();

    if($isok){
        return '1';
    }
    return '0';
}

/* 获取用户最新余额*/
function getUserCoin($uid){
    $info =Db::name('users')
            ->field('coin')
            ->where(['id'=>$uid])
            ->find();
    return $info;
}

/* 余额更新
    type  0减 1加
*/
function upCoin($uid,$coin,$type){

    if($uid<1 || $coin<=0){
        return !1;
    }

    $db=Db::name('users')
        ->where('id',$uid);
    if($type==0){
        $db->where('coin','>=',$coin);
        $coin=0-$coin;
    }

    $res=$db->inc('coin',$coin)->update();
    return $res;
}

/* 消费记录 */
function setCoinRecord($insert){
    $rs=0;
    if($insert){
        $rs=Db::name('users_coinrecord')->insert($insert);
    }

    return $rs;
}

/* 字符串加密 */
function string_encryption($code){
    $str = '1ecxXyLRB.COdrAi:q09Z62ash-QGn8VFNIlb=fM/D74WjS_EUzYuw?HmTPvkJ3otK5gp&*+%';
    $strl=strlen($str);

    $len = strlen($code);

    $newCode = '';
    for($i=0;$i<$len;$i++){
        for($j=0;$j<$strl;$j++){
            if($str[$j]==$code[$i]){
                if(($j+1)==$strl){
                    $newCode.=$str[0];
                }else{
                    $newCode.=$str[$j+1];
                }
            }
        }
    }
    return $newCode;
}

/* 字符串解密 */
function string_decrypt($code){
    $str = '1ecxXyLRB.COdrAi:q09Z62ash-QGn8VFNIlb=fM/D74WjS_EUzYuw?HmTPvkJ3otK5gp&*+%';
    $strl=strlen($str);

    $len = strlen($code);

    $newCode = '';
    for($i=0;$i<$len;$i++){
        for($j=0;$j<$strl;$j++){
            if($str[$j]==$code[$i]){
                if($j-1<0){
                    $newCode.=$str[$strl-1];
                }else{
                    $newCode.=$str[$j-1];
                }
            }
        }
    }
    return $newCode;
}

function m_s($a){
    $url=$_SERVER['HTTP_HOST'];
    if($url=='edu.sdwanyue.com' ){
        $l=strlen($a);
        $sl=$l-6;
        $s='';
        for($i=0;$i<$sl;$i++){
            $s.='*';
        }
        $rs=substr_replace($a,$s,3,$sl);
        return $rs;
    }
    return $a;
}

/* 时长处理 */
function handellength($cha,$type=0){
    $iz=floor($cha/60);
    $hz=floor($iz/60);
    $dz=floor($hz/24);
    /* 秒 */
    $s=$cha%60;
    /* 分 */
    $i=floor($iz%60);
    /* 时 */
    $h=floor($hz/24);
    /* 天 */

    if($type==1){
        if($s<10){
            $s='0'.$s;
        }
        if($iz<10){
            $iz='0'.$iz;
        }
        return lang('{:i}:{:s}',['i'=>$iz,'s'=>$s]);
    }


    if($cha<60){
        return lang('{:s}秒',['s'=>$s]);
    }else if($iz<60){
        return lang('{:i}分钟{:s}秒',['i'=>$iz,'s'=>$s]);
    }else if($hz<24){
        return lang('{:h}小时{:i}分钟',['h'=>$hz,'i'=>$i]);
    }else{
        return lang('{:d}天{:h}小时{:i}分钟',['d'=>$dz,'h'=>$h,'i'=>$i]);
    }
}

/* 毫秒时间戳 */
function getMillisecond(){
    list($msec, $sec) = explode(' ', microtime());
    $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectimes = substr($msectime,0,13);
}

/* 身份标识列表 */
function getIdentityList(){
    $key='getidentity';
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }
    $list=getcaches($key);
    if(!$list){
        $list= Db::name("identity")->order("list_order asc")->select();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
    }
    $GLOBALS[$key]=$list;
    return $list;
}
function getIdentity($identity){
    $info=[];

    if($identity==''){
        return $info;
    }

    $identitys=explode(',',$identity);

    $list=getIdentityList();

    foreach($list as $k=>$v){
        if(in_array($v['id'],$identitys)){
            unset($v['list_order']);
            $info[]=$v;
        }
    }

    return $info;
}

/* 专业领域列表 */
function getSignoryList(){
    $key='getsignory';
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }
    $list=getcaches($key);
    if(!$list){
        $list= Db::name("signory")->order("list_order asc")->select();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
    }
    $GLOBALS[$key]=$list;
    return $list;
}
function getSignory($signoryid){
    $info=[];

    if($signoryid<1){
        return $info;
    }
    $list=getSignoryList();

    foreach($list as $k=>$v){
        if($v['id']==$signoryid){
            unset($v['list_order']);
            $info=$v;
            break;
        }
    }

    return $info;
}

/* 发送系统消息 */
function sendMessage($type,$touids,$content){

    $touid='';
    if($touids){
        $touid_a=[];
        foreach($touids as $k=>$v){
            $touid_a[]='['.$v.']';
        }

        $touid=implode(',',$touid_a);
    }

    $data=[
        'type'=>$type,
        'touid'=>$touid,
        'content'=>$content,
        'addtime'=>time(),
    ];

    if($type==0){
        $data['adminid']=session('ADMIN_ID');
        $data['name']=session('name');
        $data['ip']=ip2long(get_client_ip(0, true));
    }
    $isok=Db::name('message')->insert($data);
    if($isok){
        $where=[];
        $where[]=['pushid','<>',''];
        if($touids){
            $where[]=['uid','in',$touids];
        }

        $uids=Db::name('users_pushid')->field('pushid')->where($where)->select()->toArray();
        $pushids=array_column($uids,'pushid');
        $pushids=array_filter($pushids);


        $rs=JPush($content,$pushids);
        return $rs;
    }

    return 0;
}

/* 极光推送 */
function JPush($title,$pushids,$data=[]){

    $configpri=getConfigPri();
    /* 极光推送 */
    $app_key = $configpri['jpush_key'];
    $master_secret = $configpri['jpush_secret'];
    $sandbox = $configpri['jpush_sandbox'];
    if(!$sandbox){
        return 1;
    }

    if($app_key=='' || $master_secret==''){
        return 1001;
    }
    require_once CMF_ROOT.'sdk/jpush/autoload.php';

    $client = new \JPush\Client($app_key, $master_secret);

    $apns_production=false;
    if($sandbox==2){
        $apns_production=true;
    }

    $nums=count(array_unique($pushids));

    for($i=0;$i<$nums;){
        $alias=array_slice($pushids,$i,900);
        $i+=900;
        try{
            $result = $client->push()
                    ->setPlatform('all')
                    ->addRegistrationId($alias)
                    ->setNotificationAlert($title)
                    ->iosNotification($title, array(
                        'sound' => 'sound.caf',
                        'category' => 'jiguang',
                        'extras' => $data,
                    ))
                    ->androidNotification($title, array(
                        'extras' => $data,
                    ))
                    ->options(array(
                        'sendno' => 100,
                        'time_to_live' => 0,
                        'apns_production' =>  $apns_production,
                    ))
                    ->send();
        } catch (Exception $e) {
            file_put_contents( CMF_ROOT.'data/log/jpush_'.date('y-m-d').'.txt',date('y-m-d h:i:s').'提交参数信息 设备名:'.json_encode($alias)."\r\n",FILE_APPEND);
            file_put_contents( CMF_ROOT.'data/log/jpush_'.date('y-m-d').'.txt',date('y-m-d h:i:s').'提交参数信息:'.$e."\r\n",FILE_APPEND);
        }
    }

    return 1;
}

/* 生成白板房间token */
function createNetlessRoom(){
    $configpri=getConfigPri();
    $netless_sdktoken=$configpri['netless_sdktoken'];
    $url='https://cloudcapiv4.herewhite.com/room?token='.$netless_sdktoken;
    $data=[
        'name'=>'',
        'limit'=>0,
        //'mode'=>'persistent',//普通房间无回放
        'mode'=>'historied',//可回放房间
    ];

    $headers = array(
        'Content-Type:application/json'
    );

    $res=curl_post($url,json_encode($data),$headers);

    // file_put_contents( CMF_ROOT.'data/log/netless_'.date('y-m-d').'.txt',date('y-m-d h:i:s').' res:'.json_encode($res)."\r\n",FILE_APPEND);

    $res=json_decode($res['res'],true);
    return $res;
}


/* curl POST 请求 */
function curl_post($url,$curlPost='',$headers=''){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_POST, true);
    if($curlPost){
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
    }
    if($headers){
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 跳过证书检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);  // 从证书中检查SSL加密算法是否存在

    $return_str = curl_exec($curl);
    $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['code'=>$httpCode,'res'=>$return_str];
}

/* 单文件上传 */
function upload($file=[],$type='image'){
    return upload_tp($type);
}

/* tp框架上传 */
function upload_tp($type='image'){

    $rs=['code'=>1000,'url'=>'','msg'=>'上传失败'];
    $uploader = new Upload();
    $uploader->setFileType($type);
    $result = $uploader->upload();


    if ($result === false) {
        $rs['msg']=$uploader->getError();
        return $rs;
    }

    /* $result=[
        'filepath'    => $arrInfo["file_path"],
        "name"        => $arrInfo["filename"],
        'id'          => $strId,
        'preview_url' => cmf_get_root() . '/upload/' . $arrInfo["file_path"],
        'url'         => cmf_get_root() . '/upload/' . $arrInfo["file_path"],
    ]; */

    $rs['code']=0;
    $rs['url']=$result['filepath'];
    return $rs;
}

/* 处理文件大小 */
function handelSize($size){
    $rs='0';
    if($size > 1024*1024){
        $size=floor( $size /(1024*1024) *10 )*0.1;
        $rs=$size.'MB';
        return $rs;
    }

    if($size > 1024){
        $size=floor( $size /(1024) *10 )*0.1;
        $rs=$size.'KB';
        return $rs;
    }

    $rs=$size.'B';
    return $rs;
}

/* 获取教师ID */
function get_current_teacher_id(){
    $sessionTeacherId = session('teacher.id');
    if (empty($sessionTeacherId)) {
        return 0;
    }

    return $sessionTeacherId;
}


/* 腾讯云短信验证码 */
function sendCodeByTx($mobile,$code){

    $rs = array('code' => 0, 'msg' => '', 'info' => array());

    $config = getConfigPri();

    $tx_sms_sdkappid= $config['tx_sms_sdkappid'];
    $tx_sms_sign= $config['tx_sms_sign'];
    $tx_sms_tempid= $config['tx_sms_tempid'];

    $mobile='+86'.$mobile;
    $phonenums=[$mobile];

    $params=[$code];

    $host = "sms.tencentcloudapi.com";
    $service = "sms";
    $version = "2019-07-11";
    $action = "SendSms";
    $region = "ap-shanghai";

    $httpRequestMethod = "POST";
    $param=[
        'SmsSdkAppid'=>$tx_sms_sdkappid,
        'Sign'=>$tx_sms_sign,
        'ExtendCode'=>'0',
        'PhoneNumberSet'=>$phonenums,
        'TemplateID'=>$tx_sms_tempid,
        'TemplateParamSet'=>$params,
    ];

    $response=tencentApi($host,$httpRequestMethod,$service,$version,$action,$region,$param);

    //file_put_contents(CMF_ROOT.'data/log/sendCode_tx_api_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').' 提交参数信息 res:'.json_encode($response)."\r\n",FILE_APPEND);

    // 输出json格式的字符串回包
    //{"SendStatusSet":[{"SerialNo":"2019:6180501101329406318","PhoneNumber":"+8613053838131","Fee":1,"SessionContext":"","Code":"Ok","Message":"send success"}],"RequestId":"69a550c3-74e9-4be7-b5bb-5856b7c36daa"}
    $res_a=json_decode($response['res'],true);
    if(!$res_a){
        $rs['code']=1002;
        //$rs['msg']=$gets['SubmitResult']['msg'];
        $rs['msg']="发送失败";
        return $rs;
    }

    $nums=0;
    $nums_e=0;
    $res=$res_a['Response'];

    if(isset($res['Code'])){
        $rs['code']=1002;
        $rs['msg']=$res['Code']['Message'];
        return $rs;
    }

    foreach($res['SendStatusSet'] as $k=>$v){
        if($v['Code']!='Ok'){
            $nums_e++;
        }
        $nums++;
    }

    if($nums==$nums_e){
        //file_put_contents(CMF_ROOT.'data/log/sendCode_tx_api_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').' 提交参数信息 e:'.json_encode('')."\r\n",FILE_APPEND);
        $rs['code']=1002;
        //$rs['msg']=$gets['SubmitResult']['msg'];
        $rs['msg']="发送失败";
        return $rs;
    }

    return $rs;
}

/* 下课时发布作业 */
function releaseTask($uid,$courseid,$lessonid=0){
    Db::name('task')->where([['uid','=',$uid],['courseid','=',$courseid],['lessonid','=',$lessonid]])->update(['status'=>'1']);
    return 1;
}

/* 内容形式 */
function getTypes($k=''){
    $type=[
        '1'=>'图文自学',
        '2'=>'视频自学',
        '3'=>'音频自学',
    ];
    if($k==''){
        return $type;
    }
    return isset($type[$k])? $type[$k] : '' ;
}

/* 处理课程信息 */
function handelInfo($v){
    $v['thumb']=get_upload_path($v['thumb']);
    $nowtime=time();
    $payval='免费';
    $lesson='';
    $sort=$v['sort'];
    /* 内容 */
    if($sort==0){
        $lesson=getTypes($v['type']);
    }

    /* 课程 */
    if($sort==1){
        if($v['lessons']>0){
            $lesson=$v['lessons'].'课时';
        }
    }

    /* 直播 */
    if($sort>=2){
        if($v['islive']==0){
            $lesson=handelsvctm($v['starttime']);
        }
        if($v['islive']==1){
            $lesson='正在直播';
        }
        if($v['islive']==2){
            $lesson='直播结束';
        }
    }

    $paytype=$v['paytype'];
    if($paytype==1){
        //$payval=number_format($v['payval'],2);
        $payval=$v['payval'];
    }

    if($paytype==2){
        $payval='密码';
    }


    $v['payval']=$payval;
    $v['lesson']=$lesson;
    unset($v['status']);
    unset($v['shelvestime']);
    unset($v['lessons']);
    unset($v['starttime']);

    if(isset($v['addtime'])){
        $v['add_time']=date('Y-m-d',$v['addtime']);
    }

    return $v;
}


/* 处理上架时间 用于显示 */
function handelsvctm($svctm){
    $nowtime=time();
    $today_start=strtotime(date('Ymd',$nowtime));
    $svctm_start=strtotime(date('Ymd',$svctm));

    if($today_start<$svctm_start){
        $length=($svctm_start - $today_start) / (60*60*24);

        $hs=date('H:i',$svctm);
        if($length==0){
            return '今天'.' '.$hs;
        }

        if($length==1){
            return '明天'.' '.$hs;
        }

        if($length==2){
            return '后天'.' '.$hs;
        }

        return date("m-d",$svctm).' '.$hs;

    }

    $length=($today_start - $svctm_start) / (60*60*24);

    $hs=date('H:i',$svctm);
    if($length==0){
        return '今天'.' '.$hs;
    }

    if($length==1){
        return '昨天'.' '.$hs;
    }

    if($length==2){
        return '前天'.' '.$hs;
    }
    return date("m-d",$svctm).' '.$hs;


}

/* curl GET 请求 */
function curl_get($url,$header = false) {
    $ch = curl_init($url);
    curl_setopt($ch,CURLOPT_HEADER,0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //返回数据不直接输出
    //add header
    if(!empty($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    //add ssl support
    if(substr($url, 0, 5) == 'https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    //SSL 报错时使用
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    //SSL 报错时使用
    }
    //add 302 support
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $return_str = curl_exec($ch); //执行并存储结果

    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    return json_decode($return_str,true);
}


/* []字符串拆分
*  string $str  [1],[2]
*/
function handelSetToArr($str){
    $list=[];
    if($str==''){
        return $list;
    }
    $list=explode(',',$str);
    foreach ($list as $k=>$v){
        $v=str_replace('[','',$v);
        $v=str_replace(']','',$v);
        $list[$k]=$v;
    }
    $list=array_values($list);

    return $list;
}
/* []字符串生成
*  array $arr [1,2,3]
*/
function handelSetToStr($arr){
    $str='';
    if(!$arr){
        return $str;
    }

    foreach ($arr as $k=>$v){
        $v='['.$v.']';
        $arr[$k]=$v;
    }
    $str=implode(',',$arr);

    return $str;
}

/* 获取年级 */
function getGrade(){
    $key='getcoursegrade';
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }
    $list=getcaches($key);
    if(!$list){
        $list=DB::name('course_grade')
            ->order("list_order asc")
            ->select();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
    }
    $GLOBALS[$key]=$list;
    return $list;

}

/* 获取某年级信息 */
function getGradeInfo($gradeid){

    $info=[];

    if($gradeid<1){
        return $info;
    }
    $list=getGrade();

    foreach($list as $k=>$v){
        if($v['id']==$gradeid){
            unset($v['list_order']);
            $info=$v;
            break;
        }
    }

    return $info;
}

/* 更新考试 统计 */
function upTestsNums($testsid){

    if($testsid<1){
        return !1;
    }

    $ans_nums=Db::name('tests_user')->where([['testsid','=',$testsid]])->count();
    $total=Db::name('tests_user')->where([['testsid','=',$testsid],['ishandel','=',1]])->count();
    $review=Db::name('tests_user')->where([['testsid','=',$testsid],['actiontime','<>',0]])->count();

    Db::name('tests')->where('id',$testsid)->update(['ans_nums'=>$ans_nums,'total'=>$total,'review'=>$review]);
    return 1;
}

/* 处理支付订单 */
function handelPay($where,$data=[]){
    $orderinfo=Db::name("orders")->where($where)->find();
    if(!$orderinfo){
        return 0;
    }

    if($orderinfo['status']!=0){
        return 1;
    }

    /* 更新 订单状态 */
    $status=1;
    /* 普通订单 且 无需发货 直接标记完成 */
    if($orderinfo['ordertype']==0){
        $status=2;
    }
    $data['status']=$status;
    $data['paytime']=time();
    Db::name("orders")->where("id='{$orderinfo['id']}'")->update($data);

    $uid=$orderinfo['uid'];

    /* 拼团订单处理 */
    if($orderinfo['ordertype']==1){
        $pinkid=$orderinfo['pinkid'];
        $where2=['uid'=>$uid,'pinkid'=>$pinkid];
        $pink_up=['status'=>1];
        Db::name("pink_users")->where($where2)->update($pink_up);

        /* 所需团员数减1 */
        $nowtime=time();
        $where3=[
            ['id','=',$pinkid],
            ['nums_no','>=',1],
            ['endtime','>',$nowtime],
        ];
        $res=Db::name("pink_users")->where($where3)->dec('nums_no',1)->update();
        if(!$res){
            /* 拼团未成功-取消 */
            ordersBack($where,3);
            return 1;
        }

        /* 拼团支付成功-验证是否拼团成功 */
        handelPinkOrders($pinkid);
        return 2;
    }

    /* 订单完成 处理商品 */
    //if($status==2){
    handelOrderGoods($orderinfo);
    //}

    return 2;
}

/* 订单完成，处理订单商品 */
function handelOrderGoods($orderinfo){

    if(!$orderinfo){
        return 0;
    }
    $uid=$orderinfo['uid'];

    /* 处理累计消费及其他 */
    upConsumption($uid,$orderinfo['money'],1,$orderinfo['id']);

    /* 裂变海报 */
    if($orderinfo['fissionid'] > 0 && $orderinfo['fissionuid'] > 0 ){
        setCoinByFisson($orderinfo['fissionuid'],$orderinfo['fissionid']);
    }

    if($orderinfo['money']>0){
        getByFixedBuy($uid);
    }

    /* 处理课程、套餐 */
    $list=Db::name("orders_good")->where("orderno='{$orderinfo['orderno']}'")->select()->toArray();
    foreach($list as $k=>$v){
        $type=$v['type'];
        $typeid=$v['typeid'];

        if($type==1){
            $pinfo=Db::name("course_package")->where("id='{$typeid}'")->find();
            if(!$pinfo){
                continue;
            }
            $info=json_decode($v['info'],true);

            /* 处理套餐 */
            $data=[
                'uid'=>$orderinfo['uid'],
                'packageid'=>$info['id'],
                'type'=>$orderinfo['type'],
                'money'=>$info['money'],
                'orderno'=>$orderinfo['orderno'],
                'status'=>1,
                'addtime'=>time(),
                'paytime'=>time(),
            ];

            $where2=[
                'uid'=>$orderinfo['uid'],
                'packageid'=>$info['id'],
            ];
            $payinfo=Db::name("course_package_users")->where($where2)->find();

            if($payinfo){
                if($payinfo['status']!=1){
                    $where3=[
                        'id'=>$payinfo['id']
                    ];
                    Db::name("course_package_users")->where($where3)->update($data);
                }
            }else{
                Db::name("course_package_users")->insert($data);
            }
            /* 处理套餐 */
            if($orderinfo['money']>0){
                getByFixedBuy($uid,1,$info['id']);
            }

            /* 秒杀处理 */
            if($info['isseckill']==1 && $info['seckillid']>0){
                Db::name('seckill')->where('id',$info['seckillid'])->inc('nums_ok',1)->update();
            }

            /* 套餐内课程 */
            $courseid_a=handelSetToArr($pinfo['courseids']);
            if(!$courseid_a){
                continue;
            }

            $where=[
                ['id','in',$courseid_a],
            ];

            $course=Db::name("course")->where($where)->select()->toArray();

        }else{
            $info=json_decode($v['info'],true);
            /* 秒杀处理 */
            if($info['isseckill']==1 && $info['seckillid']>0){
                Db::name('seckill')->where('id',$info['seckillid'])->inc('nums_ok',1)->update();
            }

            $where=[
                ['id','=',$typeid],
            ];

            $course=Db::name("course")->where($where)->select()->toArray();

        }

        /* 清除购物车 */
        $where4=[
            'uid'=>$uid,
            'type'=>$type,
            'typeid'=>$typeid,
        ];
        Db::name("cart")->where($where4)->delete();

        foreach($course as $k2=>$v2){
            $paytype=$v2['paytype'];
            $money=$v2['payval'];
            if($paytype!=1){
                $money=0;
            }
            $data=[
                'uid'=>$orderinfo['uid'],
                'sort'=>$v2['sort'],
                'paytype'=>$paytype,
                'courseid'=>$v2['id'],
                'liveuid'=>$v2['uid'],
                'type'=>$orderinfo['type'],
                'money'=>$money,
                'orderno'=>$orderinfo['orderno'],
                'status'=>1,
                'ispack'=>$type,
                'addtime'=>time(),
                'paytime'=>time(),
            ];

            $where2=[
                'uid'=>$orderinfo['uid'],
                'courseid'=>$v2['id'],
            ];
            $payinfo=Db::name("course_users")->where($where2)->find();

            if($payinfo){
                if($payinfo['status']!=1){
                    $where3=[
                        'id'=>$payinfo['id']
                    ];
                    Db::name("course_users")->where($where3)->update($data);
                }
            }else{
                Db::name("course_users")->insert($data);
            }

            /* 优惠券 */
            if($orderinfo['money']>0){
                getByFixedBuy($uid,0,$v2['id']);
            }
        }
    }

    return 1;
}

/* 订单取消返还
    $type 0手动取消、支付超时取消  1拼团超时取消
 */
function ordersBack($where,$type=0){

    $where2 = $where;
    $orderinfo=Db::name("orders")->where($where)->select()->toArray();
    foreach ($orderinfo as $k=>$v){
        if($v['status']==-1){
            continue;
        }

        if($v['status']==2){
            continue;
        }

        $uid=$v['uid'];
        $nowtime=time();
        /* 退回优惠券 */
        if($v['couponid']>0){
            $where=[
                ['id','=',$v['couponid']],
                ['uid','=',$uid],
                ['usetime','<>',0],
            ];
            Db::name("coupon_user")->where($where)->update(['usetime'=>0]);
        }
        /* 退回积分 */
        if($v['integral']>0){
            upIntegral($uid,$v['integral'],1);
            $record=[
                'uid'=>$uid,
                'type'=>'1',
                'action'=>'4',
                'integral'=>$v['integral'],
                'money'=>0,
                'actionid'=>$v['id'],
                'addtime'=>$nowtime,
            ];
            setIntegralRecord($record);
        }
        /* 退回余额--仅支付成功 */
        if($v['status']==1 && $v['money']>0){
            upCoin($uid,$v['money'],1);

            /* 余额记录 */
            $record=[
                'type'=>'1',
                'action'=>'2',
                'uid'=>$uid,
                'actionid'=>$v['id'],
                'nums'=>'1',
                'total'=>$v['money'],
                'addtime'=>$nowtime,
            ];
            setCoinRecord($record);

        }
        /* 拼团订单-处理 */
        if($v['ordertype']==1){
            $up=['status'=>-1];
            if($type==1){
                $where2=['id'=>$v['id']];
                Db::name("orders")->where($where2)->update(['pinkstatus'=>1]);
                $up['status']=3;
            }

            $where3=['uid'=>$v['uid'],'pinkid'=>$v['pinkid']];
            Db::name("pink_users")->where($where3)->update($up);
        }
    }
    Db::name("orders")->where($where2)->update(['status'=>-1]);

    return 2;
}

/* 拼团订单支付成功时 检测拼团是否完成 并处理 */
function handelPinkOrders($pinkid){
    if(!$pinkid){
        return 0;
    }
    $nowtime=time();

    $where=['id'=>$pinkid];
    $pinkinfo=Db::name('pink_users')->where($where)->find();
    if(!$pinkinfo){
        return 0;
    }
    if($pinkinfo['status']!=1){
        return 0;
    }
    if($pinkinfo['endtime'] <= $nowtime){
        return 0;
    }
    if($pinkinfo['nums_no']!=0){
        return 0;
    }

    $where2=['pinkid'=>$pinkid,'status'=>1];
    $up=['status'=>2];
    Db::name('pink_users')->where($where2)->update($up);

    /*处理订单*/
    $where3=['ordertype'=>1,'pinkid'=>$pinkid,'status'=>1];
    $orders=Db::name('orders')->where($where3)->select()->toArray();
    foreach ($orders as $k=>$v){
        handelOrderGoods($v);
    }
    $up2=['status'=>2];
    Db::name('orders')->where($where3)->update($up2);

    return 1;
}

/* 更新累计消费
    type 0减 1加
*/
function upConsumption($uid,$money,$type=1,$actionid=0){
    if($uid<1 || $money<=0){
        return !1;
    }

    $db=Db::name('users')
        ->where('id',$uid);
    if($type==0){
        $db->where(['consumption','>=',$money]);
        $money=0-$money;
    }

    $res=$db->inc('consumption',$money)->update();

    if($type==1){
        /* 增加累计消费时处理*/
        integralByFee($uid,$money,$actionid);
        integralByAgent($uid,$money,$actionid);
        AgentByFee($uid);
    }

    return $res;

}

/* 积分更新 */
function upIntegral($uid,$integral,$type=1){

    if($uid<1 || $integral<=0){
        return !1;
    }

    $db=Db::name('users')
        ->where('id',$uid);
    if($type==0){
        $db->where(['integral','>=',$integral]);
        $integral=0-$integral;
    }

    $res=$db->inc('integral',$integral)->update();

    return $res;

}

/* 积分-消费返还 */
function integralByFee($uid,$money,$actionid){

    if($uid<1 || $money<=0){
        return !1;
    }

    $configpri=getConfigPri();

    $integral_rate=$configpri['integral_rate'];
    if($integral_rate<=0){
        return !1;
    }

    $integral=floor($money*$integral_rate)*0.01;
    if($integral<=0){
        return !1;
    }

    upIntegral($uid,$integral,1);

    $record=[
        'uid'=>$uid,
        'type'=>'1',
        'action'=>'1',
        'integral'=>$integral,
        'money'=>$money,
        'actionid'=>$actionid,
        'addtime'=>time(),
    ];
    setIntegralRecord($record);

    return  1;
}

/* 积分-下级返还 */
function integralByAgent($uid,$money,$actionid){

    if($uid<1 || $money<=0){
        return !1;
    }

    $configpri=getConfigPri();

    $agent_rate=$configpri['agent_rate'];
    if($agent_rate<=0){
        return !1;
    }

    $agent=Db::name('agent')->where('uid',$uid)->find();
    if(!$agent){
        return !1;
    }

    $touid=$agent['pid'] ?? 0;
    if($touid<=0){
        return !1;
    }

    $agentInfo=Db::name('agent_code')->where('uid',$touid)->find();
    if(!$agentInfo){
        return !1;
    }

    if($agentInfo['isagent']!=1){
        return !1;
    }

    $integral=floor($money*$agent_rate)*0.01;
    if($integral<=0){
        return !1;
    }

    upIntegral($touid,$integral,1);

    $record=[
        'uid'=>$touid,
        'type'=>'1',
        'action'=>'2',
        'integral'=>$integral,
        'money'=>$money,
        'actionid'=>$actionid,
        'addtime'=>time(),
    ];
    setIntegralRecord($record);

    return  1;
}

/* 成为推广员-消费 */
function AgentByFee($uid){

    if($uid<1){
        return !1;
    }

    $configpri=getConfigPri();

    $agent_switch=$configpri['agent_switch'];
    if($agent_switch==0){
        return !1;
    }

    $agent_status=$configpri['agent_status'];
    if($agent_status==3){
        return !1;
    }

    $agentinfo=Db::name('agent_code')->where('uid',$uid)->find();
    if(!$agentinfo){
        return !1;
    }

    if($agentinfo['isagent']==1){
        return !1;
    }

    $userinfo=Db::name('users')->field('consumption')->where('id',$uid)->find();
    if($agent_status==1 && $userinfo['consumption']<=0){
        return !1;
    }

    if($agent_status==2 && $userinfo['consumption']<$configpri['agent_money']){
        return !1;
    }

    Db::name('agent_code')->where('uid',$uid)->update(['isagent'=>1,'istips'=>1]);

    return 1;
}

/* 生成积分记录 */
function setIntegralRecord($data){
    Db::name('integral_record')->insert($data);
}
/* 会员时长阶梯 */
function getVipLength($key=''){
    $type=[
        '1'=>[
            'type'=>'1',
            'name'=>'7天',
            'length'=>60*60*24*7,
            'day'=>7,
        ],
        '2'=>[
            'type'=>'2',
            'name'=>'1个月',
            'length'=>60*60*24*30,
            'day'=>30,
        ],
        '3'=>[
            'type'=>'3',
            'name'=>'3个月',
            'length'=>60*60*24*30*3,
            'day'=>30*3,
        ],
        '4'=>[
            'type'=>'4',
            'name'=>'6个月',
            'length'=>60*60*24*30*6,
            'day'=>30*6,
        ],
        '5'=>[
            'type'=>'5',
            'name'=>'12个月',
            'length'=>60*60*24*30*12,
            'day'=>30*12,
        ],
    ];

    if($key==''){
        return $type;
    }

    return $type[$key] ?? [];
}


/* VIP列表 */
function getVipList() {

    $key  = 'vip_list';
    if(isset($GLOBALS[$key])){
        return $GLOBALS[$key];
    }
    $list = getcaches($key);
    if (!$list) {
        $list = Db::name('vip')->order('list_order asc')->select()->toArray();
        setcaches($key, $list);
    }

    foreach ($list as $k=>$v){
        $price=[];
        if($v['price']!=''){
            $price=json_decode($v['price'],true);
        }
        foreach ($price as $k2=>$v2){
            $type=getVipLength($v2['type']);
            if(!$type){
                unset($price);
                continue;
            }

            $v2['name']=$type['name'];
            $ave=floor($v2['money']*100/$type['day'])*0.01;
            $v2['ave']='每天仅需'.$ave.'元';
            $price[$k2]=$v2;
        }
        $price=array_values($price);
        $v['price']=$price;

        $v['discount_cid']=handelSetToArr($v['discount_cid']);
        $v['discount_pid']=handelSetToArr($v['discount_pid']);

        $v['free_cid']=handelSetToArr($v['free_cid']);
        $v['free_pid']=handelSetToArr($v['free_pid']);

        $list[$k]=$v;
    }
    $GLOBALS[$key]=$list;
    return $list;
}

/* VIP配置信息 */
function getVipInfo($vipid) {
    $info=[];
    $list=getVipList();
    foreach ($list as $k=>$v){
        if($v['id']!=$vipid){
            continue;
        }
        $info=$v;
        break;
    }

    return $info;
}

/* 根据条件获取专享ID
    $type=-1 获取全部信息
    $type=0  课程  $sort=0 内容 $sort=1 课程 $sort=2语音直播 $sort=3 视频直播
    $type=1  套餐
 */
function getCourseIds($type=-1,$sort=-1){
    $rs=[];

    $key  = 'vip_course';
    $list = getcaches($key);
    if (!$list) {
        $list = Db::name('vip_course')->order('id desc')->select()->toArray();
        setcaches($key, $list);
    }
    if(!$list){
        return $rs;
    }
    if($type==-1 && $sort==-1){
        return $list;
    }

    foreach ($list as $k=>$v){
        if($v['type']!=$type){
            continue;
        }
        if($sort!=-1){
            if($sort==-2){
                if($v['sort']!=2 && $v['sort']!=3 ){
                    continue;
                }
            }else{
                if($v['sort']!=$sort){
                    continue;
                }
            }
        }
        $rs[]=$v['cid'];
    }

    return $rs;
}
/* 是否VIP专享 */
function checkCourseVip($type,$cid){
    $cids=getCourseIds($type);

    if(in_array($cid,$cids)){
        return '1';
    }
    return '0';
}

/* 某用户会员信息 */
function getVip($uid) {

    $nowtime=time();
    $key  = 'vip_'.$uid;
    $info = getcaches($key);
    if ($info) {
        if($info['endtime']>$nowtime){
            $end_time=date('Y.m.d',$info['endtime']).'到期';
            $info['end_time']=$end_time;
            return $info;
        }
    }


    $info=[
        'vipid'=>'0',
        'name'=>'暂未开通会员',
        'endtime'=>'0',
        'end_time'=>'',
    ];

    $viplist=getVipList();

    $viplistre=array_reverse($viplist);
    $where=[
        ['uid','=',$uid],
        ['endtime','>',$nowtime],
    ];

    $uservip=Db::name('vip_user')->where($where)->select()->toArray();
    if(!$uservip){
        return $info;
    }

    $isok=0;
    foreach ($viplistre as $k=>$v){
        foreach ($uservip as $k2=>$v2){
            if($v['id']==$v2['vipid']){
                $isok=1;
                $info['vipid']=$v['id'];
                $info['name']=$v['name'];
                $info['endtime']=$v2['endtime'];
            }
        }
        if($isok){
            break;
        }
    }
    setcaches($key, $info);

    $end_time='';
    if($info['endtime']>0){
        $end_time=date('Y.m.d',$info['endtime']).'到期';
    }

    $info['end_time']=$end_time;
    return $info;
}

/* 某用户会员享受的某商品折扣价 */
function getUserDiscount($vipid,$type,$cid,$money) {
    $rs=[
        'ifvip'=>'0',
        'type'=>'0',
        'money_vip'=>$money,
        'discount'=>'0',
    ];

    if($vipid==0){
        return  $rs;
    }

    $rs['ifvip']='1';

    $vipinfo=getVipInfo($vipid);
    if(!$vipinfo){
        return $rs;
    }

    if($vipinfo['free_type']==0){
        $rs['ifvip']='1';
        $rs['type']='2';
        $rs['money_vip']='0';
        return $rs;
    }

    if($vipinfo['free_type']==1){
        if($type==0 && in_array($cid,$vipinfo['free_cid'])){
            $rs['ifvip']='1';
            $rs['type']='2';
            $rs['money_vip']='0';
            return $rs;
        }

        if($type==1 && in_array($cid,$vipinfo['free_pid'])){
            $rs['ifvip']='1';
            $rs['type']='2';
            $rs['money_vip']='0';
            return $rs;
        }

        /* 判断包含本课程的套餐是否免费 */
        if($type==0){
            $where=[];
            $where[]=['courseids','like','%['.$cid.']%'];
            $cids=Db::name('course_package')->field('id')->where($where)->select()->toArray();

            $isfree='0';
            foreach ($cids as $k=>$v){
                if(in_array($v['id'],$vipinfo['free_pid'])){
                    $isfree='1';
                    break;
                }
            }
            if($isfree==1){
                $rs['ifvip']='1';
                $rs['type']='2';
                $rs['money_vip']='0';
                return $rs;
            }
        }
        /* 判断套餐内的课程是否全免费 */
        if($type==1){
            $where=['id'=>$cid];
            $info=Db::name('course_package')->field('courseid_a')->where($where)->find();

            if($info){
                $isfree='1';
                foreach ($info['courseid_a'] as $k=>$v){
                    if(!in_array($v,$vipinfo['free_cid'])){
                        $isfree='0';
                        break;
                    }
                }
                if($isfree==1){
                    $rs['ifvip']='1';
                    $rs['type']='2';
                    $rs['money_vip']='0';
                    return $rs;
                }
            }
        }
    }

    if($vipinfo['discount_type']==-1){
        return $rs;
    }
    $discount=$vipinfo['discount'];
    if($discount==0){
        $rs['ifvip']='1';
        $rs['type']='2';
        $rs['money_vip']='0';
        return $rs;
    }

    if($vipinfo['discount_type']==1){
        if($type==0 && !in_array($cid,$vipinfo['discount_cid'])){
            return $rs;
        }

        if($type==1 && !in_array($cid,$vipinfo['discount_pid'])){
            return $rs;
        }
    }

    if($money==0){
        return $rs;
    }

    $money_vip=floor($money*$discount*0.1*100)*0.01;
    if($money_vip<=0){
        $money_vip='0.01';
    }

    $rs['type']='1';
    $rs['money_vip']=$money_vip;

    return $rs;
}

/* 检测课程是否享有查看权限
    return  0无-不可看 1已买-可看 2会员专享-不可看 3会员免费-可看  4已是会员-不可看
 */
function checkCourse($uid,$courseid,$paytype='-1'){

    if($paytype==-1){
        $info=Db::name('course')->field('paytype')->where('id',$courseid)->find();
        if(!$info){
            return '0';
        }
        $paytype=$info['paytype'];
    }
    if($paytype!=0){
        $where2=[
            'uid'=>$uid,
            'courseid'=>$courseid,
            'status'=>1,
        ];
        $ispay=Db::name('course_users')->where($where2)->find();
        if($ispay){
            return '1';
        }
    }

    $uservip=getVip($uid);
    $vipid=$uservip['vipid'];

    /* 会员专享 */
    $isvip=checkCourseVip(0,$courseid);
    if($isvip==1 && $vipid==0){
        return '2';
    }

    /* 免费 */
    if($paytype==0){
        return '1';
    }

    /* 会员免费 */
    if($vipid>0){
        $vipmoney=getUserDiscount($vipid,0,$courseid,0);
        if($vipmoney['type']==2){
            return '3';
        }
        return '4';
    }

    return '0';
}

/* 定向优惠券-注册 */
function getByFixedReg($uid){
    $nowtime=time();
    $where=[
        ['status','=',1],
        ['isfixed','=',1],
        ['fixed_type','=',1],
        ['nums','>',0],
        ['fixed_start','<=',$nowtime],
        ['fixed_end','>',$nowtime],
    ];
    $list= Db::name('coupon')->field('*')->where($where)->select()->toArray();
    if(!$list){
        return 0;
    }
    foreach ($list as $k=>$v){
        setBuyFixed($uid,$v);
    }
    return 1;
}
/* 定向优惠券-消费
    $type   0课程 1套餐
    $typeid   0有消费 非0 课程/套餐ID
*/
function getByFixedBuy($uid,$type=0,$typeid=0){
    $nowtime=time();

    if($typeid==0){
        $where=[
            ['status','=',1],
            ['isfixed','=',1],
            ['fixed_type','=',2],
            ['nums','>',0],
            ['fixed_start','<=',$nowtime],
            ['fixed_end','>',$nowtime],
        ];
        $list= Db::name('coupon')->field('*')->where($where)->select()->toArray();
    }

    if($typeid>0){
        $where=[
            ['status','=',1],
            ['isfixed','=',1],
            ['fixed_type','=',3],
            ['nums','>',0],
            ['fixed_start','<=',$nowtime],
            ['fixed_end','>',$nowtime],
        ];
        if($type==1){
            $where[]=['fixed_pids','like',"[{$typeid}]"];
        }else{
            $where[]=['fixed_cids','like',"[{$typeid}]"];
        }
        $list= Db::name('coupon')->field('*')->where($where)->select()->toArray();
    }

    if($list){
        foreach ($list as $k=>$v){
            setBuyFixed($uid,$v);
        }
    }

    return 1;
}
function setBuyFixed($uid,$v){

    $where2=[
        'uid'=>$uid,
        'couponid'=>$v['id'],
    ];
    $isexist=Db::name('coupon_user')->where($where2)->find();
    if($isexist){
        return 0;
    }

    $where3=[
        ['id','=',$v['id']],
        ['nums','>=',1],
    ];
    $isok=Db::name('coupon')->where($where3)->dec('nums',1)->update();
    if(!$isok){
        return 0;
    }
    $nowtime=time();
    $endtime=$v['use_end'];
    if($v['use_type']==2){
        $endtime=$nowtime + $v['use_lenth'] * 60*60*24;
    }

    $insert=[
        'uid'=>$uid,
        'couponid'=>$v['id'],
        'endtime'=>$endtime,
        'addtime'=>$nowtime,
        'name'=>$v['name'],
        'type'=>$v['type'],
        'limit_type'=>$v['limit_type'],
        'limit_val'=>$v['limit_val'],
        'limit_rate'=>$v['limit_rate'],
        'limit_money'=>$v['limit_money'],
        'isall'=>$v['isall'],
        'cids'=>$v['cids'],
        'pids'=>$v['pids'],
    ];

    Db::name('coupon_user')->insert($insert);

    return 1;
}


/* 二维码生成
    $filepath  生成的二维码图片地址
    $url       二维码包含信息
    $size       二维码大小  $url 内容的多少会影响 size=1 时的基数大小 内容越多图片越大 拼接图片时 注意处理缩放
    $level      容错等级  L M Q H
*/
function qcodeCreate($filepath,$url,$size=1,$level='L'){
    require_once CMF_ROOT.'sdk/phpqrcode/phpqrcode.php';
    //生成二维码图片
    QRcode::png($url,$filepath , $level, $size, 2);

    return $filepath;
}

/* 删除证书 */
function delCert($where){
    Db::name('tests_cert')->where($where)->delete();
}
/* 生成考试证书 */
function createCert($uid,$score,$testsinfo){
    $testid=$testsinfo['id'];
    $where=[
        'uid'=>$uid,
        'testid'=>$testid,
    ];


    if($testsinfo['cert_switch']==0){
        delCert($where);
        return 1;
    }

    if($testsinfo['cert_config']==''){
        delCert($where);
        return 2;
    }
    if($testsinfo['cert']==''){
        delCert($where);
        return 5;
    }
    $cert_config=json_decode($testsinfo['cert_config'],true);
    if(!$cert_config){
        delCert($where);
        return 3;
    }
    $des='';
    foreach ($cert_config as $k=>$v){
        if($k==0){
            if(!($v['start'] <= $score && $score <= $v['end']) ){
                continue;
            }
        }else{
            if(!($v['start'] < $score && $score <= $v['end']) ){
                continue;
            }
        }

        $des=$v['des'];
        break;
    }
    if($des==''){
        delCert($where);
        return 4;
    }

    require_once CMF_ROOT.'sdk/Imghandel/Imghandel.php';

    $up_path=CMF_ROOT . 'public/upload/cert';
    if (!is_dir($up_path)) {
        @mkdir($up_path);
    }

    $Img=new \Imghandel();

    $userinfo=getUserInfo($uid);
    $name=$testsinfo['cert_name'];
    $uname=$userinfo['user_nickname'];
    $avatar=$userinfo['avatar'];
    $title=$testsinfo['title'];
    //$score=90;
    //$des='这里显示的是设置的评语，可设置不同得分段显示不同评语。字数控制在50字以内！';
    $code_img=$up_path.'/code_'.$testid.'_'.$uid.'.png';
    $code_des=$testsinfo['cert_des'];
    $bg=CMF_ROOT.'cert_bg.png';
    $bgimg=$Img->create($bg);
    $bg2=get_upload_path($testsinfo['cert']);
    $bg2=$Img->create($bg2);

    $bg2_w=$Img->getX($bg2);
    $bg2_h=$Img->getY($bg2);

    if(1125/$bg2_w > 2001/$bg2_h){
        $to_w=$bg2_w * 2001/$bg2_h;
        $to_h= 2001;
    }else{
        $to_w= 1125;
        $to_h=$bg2_h * 1125/$bg2_w;
    }

    $bg2=$Img->zoom($bg2,$to_w,$to_h);

    $left= (1125- $to_w)/2;
    $top= (2001- $to_h)/2;

    $bgimg=$Img->merge($bgimg, $bg2, $left, $top);

    /* 证书名称 */
    $bgimg=$Img->addstringByCenter($bgimg,50,0,334,[67,66,68],$name,4);

    /* 头像 */
    $avatarimg=$Img->create($avatar);
    $avatarimg_zoom=$Img->zoom($avatarimg,190,190);
    $avatarimg_fillet=$Img->fillet($avatarimg_zoom);
    $bgimg=$Img->merge($bgimg,$avatarimg_fillet,468,490);

    /* 昵称 */
    $bgimg=$Img->addstringByCenter($bgimg,40,0,723,[50,50,50],$uname,4);

    /* 标题 */
    if(mb_strlen($title)>10){
        $title=mb_substr($title,0,4).'...'.mb_substr($title,-4,4);
    }
    $newtitle='在《'.$title.'》中获得了';
    $bgimg=$Img->addstringByCenter($bgimg,31.5,0,833,[50,50,50],$newtitle);

    /* 得分 */
    $font_size_score=75;
    $score_size=$Img->getStringSize($score,$font_size_score);
    $score2='分';
    $font_size_score2=34;
    $score2_size=$Img->getStringSize($score2,$font_size_score2);

    $left_score=(imagesx($bgimg) - $score_size['width'] - $score2_size['width'] ) / 2 -15;
    $top_score=960 + $score_size['height'];
    $bgimg=$Img->addstring($bgimg,$font_size_score,0,$left_score,$top_score,[50,50,50],$score,4);

    $left_score2=(imagesx($bgimg) - $score_size['width'] - $score2_size['width'] ) / 2 +15 + $score_size['width'];
    $top_score2=992 + $score2_size['height'];
    $bgimg=$Img->addstring($bgimg,$font_size_score2,0,$left_score2,$top_score2,[50,50,50],$score2);

    /* 评语 */
    for($i=0;$i<4;$i++){
        $nums=15;
        $start=$i*$nums;
        $str=mb_substr($des,$start,$nums);
        if($str==''){
            break;
        }
        $top=1179;
        $top=$top+$i*77;

        $bgimg=$Img->addstring($bgimg,40,0,159,$top,[50,50,50],$str,4);
    }

    /* 二维码 */
    $url=get_upload_path('/appapi/share/index');
    qcodeCreate($code_img,$url,9);
    $codeimg=$Img->create($code_img);
    $codeimg=$Img->zoom($codeimg,267,267);
    $bgimg=$Img->merge($bgimg,$codeimg,168,1491);
    @unlink($code_img);
    /* 介绍 */
    $font_size_des2=38;
    for($i=0;$i<4;$i++){
        $nums=10;
        $start=$i*$nums;
        $str=mb_substr($code_des,$start,$nums);
        if($str==''){
            break;
        }
        $top=1552;
        $top=$top+$i*70;
        $bgimg=$Img->addstring($bgimg,$font_size_des2,0,470,$top,[50,50,50],$str);
    }

    $new_img_path='cert_'.$testid.'_'.$uid.'.png';
    imagepng($bgimg, $up_path.'/'.$new_img_path);

    $cert= '/upload/cert/'.$new_img_path;
    $nowtime=time();
    $up=[
        'img'=>$cert.'?t='.$nowtime,
        'addtime'=>$nowtime,
        'isnew'=>1,
    ];
    $isok=Db::name('tests_cert')->where(['uid'=>$uid,'testid'=>$testid])->update($up);
    if(!$isok){
        $up['uid']=$uid;
        $up['testid']=$testid;
        Db::name('tests_cert')->insert($up);
    }

    return 0;
}

/* 裂变海报收益 */
function setCoinByFisson($uid,$fid){
    if($uid<1 || $fid <1 ){
        return 0;
    }

    $where=[
        'id'=>$fid,
    ];
    $info= Db::name('fissionposter')->where($where)->find();
    if(!$info){
        return 0;
    }
    $nowtime=time();

    if($info['starttime'] > $nowtime || $info['endtime'] <= $nowtime){
        return 0;
    }

    $type=$info['type'];
    $cid=$info['cid'];
    $money=0;
    if($type==1){
        $where2=['id'=>$cid];
        $cinfo=Db::name('course_package')->field('price')->where($where2)->find();
        if(!$cinfo){
            return 0;
        }
        $money=$cinfo['price'];
    }else{
        $where2=['id'=>$cid];
        $cinfo=Db::name('course')->field('id,paytype,payval')->where($where2)->find();
        if(!$cinfo){
            return 0;
        }
        if($cinfo['paytype']!=1){
            return 0;
        }
        $money=$cinfo['payval'];
    }

    if($money<=0){
        return 0;
    }

    $rate=$info['rate'];

    $rate_money=floor($money * $rate) * 0.01;
    if($rate_money<=0){
        return 0;
    }

    $res=upCoin($uid,$rate_money,1);
    if(!$res){
        return 0;
    }

    /* 余额记录 */
    $record=[
        'type'=>'1',
        'action'=>'6',
        'uid'=>$uid,
        'actionid'=>$fid,
        'nums'=>'1',
        'total'=>$rate_money,
        'addtime'=>$nowtime,
    ];
    setCoinRecord($record);
    return 1;
}

/**
 *  @desc 获取推拉流地址
 *  @param int $cdn_switch cdn类型
 *  @param string $host 协议，如:http、rtmp
 *  @param string $stream 流名,如有则包含 .flv、.m3u8
 *  @param int $type 类型，0表示播流，1表示推流
 */
function getCdnUrl($cdn_switch,$host,$stream,$type){
    $cdn_switch=(int)$cdn_switch;
    switch($cdn_switch){
        case 1:
            $url=PrivateKey_tx($host,$stream,$type);
            break;
        default:
            $url='';
    }


    return $url;
}

/**
 *  @desc 腾讯云推拉流地址
 *  @param string $host 协议，如:http、rtmp
 *  @param string $stream 流名,如有则包含 .flv、.m3u8
 *  @param int $type 类型，0表示播流，1表示推流
 */
function PrivateKey_tx($host,$stream,$type){
    $configpri=getConfigPri();
    $bizid=$configpri['tx_bizid'];
    $push_url_key=$configpri['tx_push_key'];
    $play_url_key=$configpri['tx_play_key'];
    $push=$configpri['tx_push'];
    $pull=$configpri['tx_pull'];

    $stream_a=explode('.',$stream);
    $streamKey =  $stream_a[0] ?? '';
    $ext =  $stream_a[1] ?? '';

    $live_code = $streamKey;

    $now=time();
    $play_safe_url='';
    //后台开启了播流鉴权
    if($configpri['tx_play_switch']){
        //播流鉴权时间
        $play_auth_time=$now+(int)$configpri['tx_play_time'];
        $txPlayTime = dechex($play_auth_time);
        $txPlaySecret = md5($play_url_key . $live_code . $txPlayTime);
        $play_safe_url = "?txSecret=" .$txPlaySecret."&txTime=" .$txPlayTime;
    }

    if($type==1){
        $now_time = $now + 3*60*60;
        $txTime = dechex($now_time);

        $txSecret = md5($push_url_key . $live_code . $txTime);
        $safe_url = "?txSecret=" .$txSecret."&txTime=" .$txTime;

        //$push_url = "rtmp://" . $bizid . ".livepush2.myqcloud.com/live/" .  $live_code . "?bizid=" . $bizid . "&record=flv" .$safe_url;	可录像
        $url = "rtmp://{$push}/live/" . $live_code . $safe_url;
    }else{

        if($ext==''){
            $ext='flv';
        }
        $url = "http://{$pull}/live/" . $live_code . ".".$ext.$play_safe_url;

        $configpub=getConfigPub();

        if(strstr($configpub['site_url'],'https')){
            $url=str_replace('http:','https:',$url);
        }
    }

    return $url;
}

/**
 * 微信sign拼装获取
 */
function wxsign($param,$key){
    $sign = "";
    foreach($param as $k => $v){
        $sign .= $k."=".$v."&";
    }
    $sign .= "key=".$key;
    $sign = strtoupper(md5($sign));
    return $sign;
}
/**
 * xml转为数组
 */
function xmlToArray($xmlStr){
    $msg = array();
    $postStr = $xmlStr;
    $msg = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    return $msg;
}

/* 拍乐云音视频-生成token */
function pano_token($stream,$uid){
    $configpri=getConfigPri();

    $appId =  $configpri['pano_appid'];
    $appSecret = $configpri['pano_secret'];

    $timestamp = time();
// joinParams 请按实际情况配置，此处仅为示例
    $joinParams = ["channelId" => $stream, "userId" => $uid, "channelDuration" => 0, "privileges" => 0, "duration" => 86400, "size" => 100, "delayClose" => 60*60];

    $version = "02";
    $params = base64_encode(json_encode($joinParams));
    $signData = $version . $appId . $timestamp . $params;
    $signature = base64_encode(hash_hmac("sha256", $signData, $appSecret, true));
    return $version . "." . $appId . "." . $timestamp . "." . $params . "." . $signature;

}

/* 腾讯云点播上传sign */
function getTxVodSign(){

    $config = getConfigPri();

    // 确定 App 的云 API 密钥
    $secret_id = $config['tx_trans_api_secretid'];
    $secret_key = $config['tx_trans_api_secretkey'];
// 确定签名的当前时间和失效时间
    $current = time();
    $expired = $current + 86400;  // 签名有效期：1天
// 向参数列表填入参数
    $arg_list = array(
        "secretId" => $secret_id,
        "currentTimeStamp" => $current,
        "expireTime" => $expired,
        "random" => rand());
// 计算签名
    $original = http_build_query($arg_list);
    $signature = base64_encode(hash_hmac('SHA1', $original, $secret_key, true).$original);
    return $signature;
}

/* 腾讯云点播 视频转码 */
function tx_sendTrans($file_id){

    $path=CMF_DATA.'log/txvod/';
    if (!is_dir($path)) {
        @mkdir($path);
    }

    file_put_contents(CMF_DATA.'log/txvod/'.'sendtrans_'.date('Ymd').'.log',date('Ymd H:i:s').'data:'.json_encode($file_id).PHP_EOL,FILE_APPEND);

    $host = "vod.tencentcloudapi.com";
    $service = "vod";
    $version = "2018-07-17";
    $action = "ProcessMediaByProcedure";
    $region = "";

    $httpRequestMethod = "POST";

    $param = [
        'FileId'=>$file_id,
        'ProcedureName'=>'LongVideoPreset',
    ];

    $response=tencentApi($host,$httpRequestMethod,$service,$version,$action,$region,$param);

    file_put_contents($path.'sendtrans_'.date('Ymd').'.log',date('Ymd H:i:s').'data:'.json_encode($response).PHP_EOL,FILE_APPEND);

}

/* tp框架上传-仅本地上传 */
function upload_tp_local($type='image'){

    $rs=['code'=>1000,'data'=>[],'msg'=>'上传失败'];

    require_once CMF_ROOT.'sdk/UploadLocal.php';

    $uploader = new cmf\lib\UploadLocal();
    $uploader->setFileType($type);
    $result = $uploader->upload();

    if ($result === false) {
        $rs['msg']=$uploader->getError();
        return $rs;
    }

    /* $result=[
        'filepath'    => $arrInfo["file_path"],
        "name"        => $arrInfo["filename"],
        'id'          => $strId,
        'preview_url' => cmf_get_root() . '/upload/' . $arrInfo["file_path"],
        'url'         => cmf_get_root() . '/upload/' . $arrInfo["file_path"],
    ]; */

    $rs['code']=0;
    $rs['data']=$result;
    return $rs;
}

/* EXCEL导入 */
function excel_import($file,$row=2){
    require_once CMF_ROOT.'sdk/PHPExcel/PHPExcel/IOFactory.php';

    //加载excel文件
    $filename = CMF_ROOT.'public/upload/'.$file;
    $objPHPExcelReader = PHPExcel_IOFactory::load($filename);

    $sheet = $objPHPExcelReader->getSheet(0);        // 读取第一个工作表(编号从 0 开始)
    $highestRow = $sheet->getHighestRow();           // 取得总行数
    $highestColumn = $sheet->getHighestColumn();     // 取得总列数

    $arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M', 'N','O','P','Q','R','S','T','U','V','W','X','Y','Z');

    $key=array_search($highestColumn,$arr);
    // 一次读取一列
    $res_arr = [];
    for ($row = $row; $row <= $highestRow; $row++) {
        $row_arr = [];
        for ($column = 0; $column <= $key; $column++) {
            $val = $sheet->getCellByColumnAndRow($column, $row)->getValue();
            if($val==NULL ){
                $val='';
            }
            $row_arr[] = $val;
        }

        $res_arr[] = $row_arr;
    }

    return $res_arr;
}

/*
 * 腾讯API 公共请求方法
 * string host 请求域名
 * string httpRequestMethod 请求方式 GET/POST
 * string service 产品名 需与请求域名一致
 * string version 接口版本
 * string action 方法名
 * string region 地域 例：ap-shanghai
 * array param 请求参数
 * */
function tencentApi($host,$httpRequestMethod,$service,$version,$action,$region,$param){
    $config = getConfigPri();

    $tx_api_secretid= $config['tx_api_secretid'];
    $tx_api_secretkey= $config['tx_api_secretkey'];

    $timestamp = time();
    $algorithm = "TC3-HMAC-SHA256";

    $url='https://'.$host;
    $datastr='';

    // step 1: build canonical request string
    $canonicalUri = "/";
    $canonicalQueryString = "";
    $requestPayload = "";
    if($httpRequestMethod=='GET'){
        $canonicalQueryString=http_build_query($param);
        $url.='?'.$canonicalQueryString;
    }
    if($httpRequestMethod=='POST'){
        $requestPayload=json_encode($param);
        $datastr=$requestPayload;
    }

    $canonicalHeaders = implode("\n", [
        "content-type:application/json; charset=utf-8",
        "host:" . $host,
        "x-tc-action:" . strtolower($action),
        ""
    ]);
    $signedHeaders = implode(";", [
        "content-type",
        "host",
        "x-tc-action",
    ]);

    $hashedRequestPayload = hash("SHA256", $requestPayload);

    $canonicalRequest = $httpRequestMethod . "\n"
        . $canonicalUri . "\n"
        . $canonicalQueryString . "\n"
        . $canonicalHeaders . "\n"
        . $signedHeaders . "\n"
        . $hashedRequestPayload;

    // step 2: build string to sign
    $date = gmdate("Y-m-d", $timestamp);
    $credentialScope = $date . "/" . $service . "/tc3_request";
    $hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
    $stringToSign = $algorithm . "\n"
        . $timestamp . "\n"
        . $credentialScope . "\n"
        . $hashedCanonicalRequest;

    // step 3: sign string
    $secretDate = hash_hmac("SHA256", $date, "TC3" . $tx_api_secretkey, true);
    $secretService = hash_hmac("SHA256", $service, $secretDate, true);
    $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
    $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

    // step 4: build authorization
    $authorization = $algorithm
        . " Credential=" . $tx_api_secretid . "/" . $credentialScope
        . ", SignedHeaders=" . $signedHeaders . ", Signature=" . $signature;



    $headers=[
        'Authorization: '.$authorization,
        'Content-Type: application/json; charset=utf-8',
        'Host: '.$host,
        'X-TC-Action: '.$action,
        'X-TC-Timestamp: '.$timestamp,
        'X-TC-Version: '.$version,
        'X-TC-Region: '.$region,
    ];

    return curl_post($url,$datastr,$headers);

}


function tree($data,$pid=0){
  $list = [];
  foreach($data as $k=>$v){
    if($v['pid'] == $pid){
        $v['son'] = tree($data,$v['id']);
        $list[] = $v;
    }
  }
  return $list;
}


function handelList($list, $pid = 0)
{
  $rs = [];
  foreach ($list as $k => $v) {
    if ($v['pid'] == $pid) {
      $rs[]=$v;
    }
  }

  return $rs;
}



function getName($type,$id){
    if($type == 1){
        $key = "patriarch" . $id;
        $name = getcaches($key);
        if(!$name){
            $relation = getcaches('getrelation');
            if(!$relation){
                $relation = Db::name('relation')->order('list_order asc')->select()->toArray();
                setcaches('getrelation',$relation);
            }
            $relation = array_column($relation,null,'id');
            $info = Db::name('class_patriarch')->where(['id'=>$id])->find();
            $student_id = $info['student_id'];
            $student = Db::name('student')->where(['id'=>$student_id])->find();
            $name = $student['name'] . $relation[$info['relation_id']]['name'] ?? $info['relation_id'];
            setcaches($key,$name,86400 * 5);
        }
    }else{
        $key = "teacher" . $id;
        $name = getcaches($key);
        if(!$name){
            $info = Db::name('class_teacher')->where(['id'=>$id])->find();
            $name = $info['name'].'老师';
            setcaches($key,$name,86400 * 5);
        }
    }
    return $name;
}




function getClassUserInfo($uid,$user_type,$teacher_id=0,$patriarch_id=0) {

        if($user_type == 1){
            $key2 = 'getrelation';
            $relation = getcaches($key2);
            if(!$relation){
                $relation = Db::name('relation')->order('list_order asc')->select()->toArray();
                setcaches($key2,$relation);
            }
            $userinfo = Db::name('class_patriarch')->where(['id'=>$patriarch_id])->find();
            if(!$userinfo){
                return 0;
            }
            $student = Db::name('student')->where('id',$userinfo['student_id'])->find();
            $userinfo['name'] = $student['name'];
            $userinfo['relation'] = $relation[$userinfo['relation_id']]['name'];
        }elseif($user_type == 2){
            $userinfo = Db::name('class_teacher')->where(['id'=>$teacher_id])->find();
            if(!$userinfo){
                return 0;
            }
        }
    $userinfo['user_type'] = $user_type;
    $userinfo['user_type_id'] = $userinfo['id'];

    return 	$userinfo;
}