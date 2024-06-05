<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace app\teacher\controller;

use cmf\controller\HomeBaseController;
use think\Db;

/**
 * 登录
 */
class LoginController extends HomebaseController
{
    protected $siteUrl;

    public function initialize()
    {
        parent::initialize();
        $siteInfo = cmf_get_site_info();
        $this->siteUrl = $siteInfo['site_url'];

    }

    //首页
    public function index()
    {
        $redirect = $this->request->param("redirect");
        if (empty($redirect)) {
            $redirect = $this->request->server('HTTP_REFERER');
        } else {
            if (strpos($redirect, '/') === 0 || strpos($redirect, 'http') === 0) {
            } else {
                $redirect = base64_decode($redirect);
            }
        }
        if (!empty($redirect)) {
            session('login_http_referer', $redirect);
        }

        if (session('teacher')) { //已经登录时直接跳到首页
            return redirect(cmf_url("teacher/index/index"));
        } else {
            return $this->fetch();
        }
    }

    /* 手机验证码 */
    public function getCode()
    {

        $rs = ['code' => 0, 'msg' => '', 'info' => []];

        $data = $this->request->param();
        $type = isset($data['type']) ? $data['type'] : '0';
        $mobile = isset($data['mobile']) ? $data['mobile'] : '';

        $mobile = checkNull($mobile);

        if ($mobile == '') {
            $this->error('请输入手机号');
        }

        $where['user_login'] = $mobile;

        $checkuser = checkUser($where);

        if ($type == 1) {
            /* 忘记密码 */
            if (!$checkuser) {
                $this->error('该手机号尚未注册，请先注册');
            }

            $s_a = 'forget_account';
            $s_c = 'forget_code';
            $s_e = 'forget_expiretime';
        } else {
            /* 登录 */
            if (!$checkuser) {
                $this->error('该手机号尚未注册，请先注册');
            }

            $s_a = 'login_account';
            $s_c = 'login_code';
            $s_e = 'login_expiretime';
        }

        $nowtime = time();

        if (session($s_a) == $mobile && session($s_e) > $nowtime) {
            $this->error('验证码5分钟有效，请勿多次发送');
        }

        $mobile_code = random(6, 1);

        //密码可以使用明文密码或使用32位MD5加密
        $result = sendCode($mobile, $mobile_code);
        if ($result['code'] == 0) {
            session($s_a, $mobile);
            session($s_c, $mobile_code);
            session($s_e, time() + 60 * 5);

        } else if ($result['code'] == 667) {
            session($s_a, $mobile);
            session($s_c, $result['msg']);
            session($s_e, time() + 60 * 5);

            $this->error("验证码为：{$result['msg']}");
        } else {
            $this->error($result['msg']);
        }

        $this->success('验证码已送');
    }


    /* 密码登录 */
    public function loginbypass()
    {

        $data = $this->request->param();
        $name = isset($data['name']) ? $data['name'] : '';
        $pass = isset($data['pass']) ? $data['pass'] : '';
        $name = checkNull($name);
        $pass = checkNull($pass);

        if ($name == '') {
            $this->error('请输入手机号');
        }
        if ($pass == '') {
            $this->error('请输入密码');
        }


        $user_pass = cmf_password($pass);

        $where['user_login|mobile'] = $name;
        $userinfo = Db::name('users')->where($where)->find();

        if (!$userinfo || $userinfo['user_pass'] != $user_pass) {
            $this->error('账号或密码错误');
        }

        $this->handleInfo($userinfo);

        $this->success('登陆成功');

    }

    /* 验证码登录 */
    public function loginbycode()
    {

        $data = $this->request->param();
        $name = isset($data['name']) ? $data['name'] : '';
        $code = isset($data['code']) ? $data['code'] : '';
        $name = checkNull($name);
        $code = checkNull($code);

        if ($name == '') {
            $this->error('请输入手机号');
        }
        if ($code == '') {
            $this->error('请输入验证码');
        }

        if (!session('?login_account') || !session('?login_code')) {
            $this->error('请先获取验证码');
        }

        if (!session('?login_expiretime') || session('login_expiretime') < time()) {
            $this->error('验证码已过期');
        }

        if ($name != session('login_account')) {
            $this->error('手机号码不一致');
        }

        if ($code != session('login_code')) {
            $this->error('验证码错误');
        }

        $where['user_login|mobile'] = $name;
        $userinfo = Db::name('users')->where($where)->find();


        $this->handleInfo($userinfo);

        $this->success('登陆成功');
    }

    public function forget()
    {

        $data = $this->request->param();
        $name = isset($data['name']) ? $data['name'] : '';
        $pass = isset($data['pass']) ? $data['pass'] : '';
        $code = isset($data['code']) ? $data['code'] : '';
        $name = checkNull($name);
        $pass = checkNull($pass);
        $code = checkNull($code);

        if ($name == '') {
            $this->error('请输入手机号');
        }
        if ($code == '') {
            $this->error('请输入验证码');
        }

        if ($pass == '') {
            $this->error('请输入密码');
        }

        if (!session('?forget_account') || !session('?forget_code')) {
            $this->error('请先获取验证码');
        }

        if (!session('?forget_expiretime') || session('forget_expiretime') < time()) {
            $this->error('验证码已过期');
        }

        if ($name != session('forget_account')) {
            $this->error('手机号码不一致');
        }

        if ($code != session('forget_code')) {
            $this->error('验证码错误');
        }

        $check = checkPass($pass);

        if (!$check) {
            $this->error('密码为6-20位数字与字母组合');
        }

        $user_pass = cmf_password($pass);

        $where['user_login|mobile'] = $name;
        $ifreg = DB::name('users')->field("id")->where($where)->find();
        if (!$ifreg) {
            $this->error('该帐号不存在');
        }
        $result = DB::name('users')->where("id='{$ifreg['id']}'")->setField("user_pass", $user_pass);
        if ($result === false) {
            $this->error('重置失败，请重试');
        }
        $this->success('操作成功');
    }

    /* 退出 */
    public function logout()
    {

        session('teacher', null);

        $this->success('退出登录');
    }

    //qq第三方登录========
    public function qq()
    {
        $href = $_SERVER['HTTP_REFERER'];
        cookie('href', $href, 3600000);
        cookie('identity', 'teacher', 3600000);
        $referer = $_SERVER['HTTP_REFERER'];
        session('login_referer', $referer);

        require_once CMF_ROOT . 'sdk/qqApi/qqConnectAPI.class.php';

        $qc1 = new \QC();
        $qc1->qq_login();
    }

    public function qqCallback()
    {

        require_once CMF_ROOT . 'sdk/qqApi/qqConnectAPI.class.php';

        $qc = new \QC();
        $token = $qc->qq_callback();

        $openid2 = $qc->get_openid();
        $openid = $openid2['openid'];

        $unionid = $openid2['unionid'];

        $qq = new \QC($token, $openid);
        $arr = $qq->get_user_info();


        $type = '1';
        $openid = $unionid;
        $nickname = $arr['nickname'];
        $avatar = $arr['figureurl_qq_2'];

        $identity = cookie('identity');
        if ($identity == 'teacher') {
            $this->loginByThird($type, $openid, $nickname, $avatar);
        } else {
            $this->loginByThirdStudent($type, $openid, $nickname, $avatar);
        }


        $href = cookie('href');
        echo "<meta http-equiv=refresh content='0; url=$href'>";
        exit;
    }

    /**
     * 微信登陆
     **/
    public function weixin()
    {
        $configpri = getConfigPri();

        //-------配置
        $href = $_SERVER['HTTP_REFERER'];
        cookie('href', $href, 3600000);
        $AppID = $configpri['wx_appid_pc'];
        $AppSecret = $configpri['wx_appsecret_pc'];
        $callback = get_upload_path('/teacher/login/weixin_callback'); //回调地址
        //微信登录
        //-------生成唯一随机串防CSRF攻击
        $state = md5(uniqid(rand(), TRUE));
        $_SESSION["wx_state"] = $state; //存到SESSION
        $callback = urlencode($callback);
        $wxurl = "https://open.weixin.qq.com/connect/qrconnect?appid=" . $AppID . "&redirect_uri={$callback}&response_type=code&scope=snsapi_login&state={$state}#wechat_redirect";
        header("Location: $wxurl");
    }

    /**
     * 微信登陆回调
     **/
    public function weixin_callback()
    {
        $configpri = getConfigPri();
        $code = isset($_GET['code']) ? $_GET['code'] : '';
        if ($code != "") {
            $AppID = $configpri['wx_appid_pc'];
            $AppSecret = $configpri['wx_appsecret_pc'];
            $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $AppID . '&secret=' . $AppSecret . '&code=' . $code . '&grant_type=authorization_code';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_URL, $url);
            $json = curl_exec($ch);
            curl_close($ch);
            $arr = json_decode($json, 1);

            if (isset($arr['errcode'])) {
                $this->error($arr['errmsg']);
            }

            //得到 access_token 与 openid
            $url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $arr['access_token'] . '&openid=' . $arr['openid'] . '&lang=zh_CN';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_URL, $url);
            $json = curl_exec($ch);
            curl_close($ch);
            $arr = json_decode($json, 1);
            //得到 用户资料
            // $openid=$arr['openid'];
            $openid = $arr['unionid'];

            $type = '2';
            $openid = $openid;
            $nickname = $arr['nickname'];
            $avatar = $arr['headimgurl'];

            $this->loginByThird($type, $openid, $nickname, $avatar);

        }
    }

    protected function loginByThirdStudent($type, $openid, $nickname, $avatar)
    {
        $checkdata = array(
            'type' => $type,
            'openid' => $openid
        );
        $key = '400d069a791d51ada8af3e6c2979bcd7';
        $str = '';
        ksort($checkdata);
        foreach ($checkdata as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str .= $key;
        $sign = md5($str);
        //请求获取验证码接口
        $url = $this->siteUrl . '/api/?s=Login.LoginByThird&openid=' . $openid . '&type=' . $type . '&sign=' . $sign . '&nicename=' . $nickname . '&avatar=' . urlencode($avatar) . '&source=0';
        $info = curl_get($url);

        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $userinfo = $info['data']['info'][0];
        $gradeid = $userinfo['gradeid'];
        $gradeid = session('gradeid');

        if ($gradeid == 0) {
            //查询学级
            $list = Db::name('course_grade')->order('list_order asc')->select()->toArray();
            foreach ($list as $k => $v) {
                unset($v['list_order']);
                $list[$k] = $v;
            }
            $list = $this->handelGrade($list);
            if (isset($list[0]['list'][0])) {
                $uid = $userinfo['id'];
                //修改用户的选择年级
                $gradeid = $list[0]['list'][0]['id'];
                $gradename = $list[0]['list'][0]['name'];
                DB::name("users")
                    ->where("id={$uid}")
                    ->update(array('gradeid' => $gradeid));

                $vipId = 0;
                $vipInfo = DB::name("vip_user")
                    ->where('uid', $uid)
                    ->where('endtime', '>', time())
                    ->find();
                if($vipInfo){
                    $vipId = $vipInfo['vipid'];
                }

                $userinfo['gradename'] = $gradename;
                $userinfo['gradeid'] = $gradeid;
                $userinfo['vipid'] = $vipId;
            }
        }
        session('student', $userinfo);
        $this->SetGrade($gradeid);

        $href = cookie('href');
        echo "<meta http-equiv=refresh content='0; url=$href'>";
        exit;
    }

    //选择学级
    public function SetGrade($gradeid)
    {
        $userId = session('student.id');
        if ($userId) {
            $data = array(
                'gradeid' => $gradeid
            );
            $result = Db::name('users')->where(['id' => $userId])->update($data);

            $gradeinfo = Db::name('course_grade')->where(['id' => $gradeid])->find();

            if ($gradeinfo) {
                $gradename = $gradeinfo['name'];
            } else {
                $gradename = '';
            }

            session('student.gradeid',$gradeid);
            session('student.gradename',$gradename);

        }
    }

    protected function loginByThird($type, $openid, $nickname, $avatar)
    {

        $userinfo = DB::name('users')
            ->where("openid='{$openid}' and login_type='{$type}'")
            ->find();

        $this->handleInfo($userinfo);

        $href = cookie('href');
        echo "<meta http-equiv=refresh content='0; url=$href'>";

        exit;
    }

    /* 更新token 登陆信息 */
    protected function handleInfo($userinfo)
    {
        if (!$userinfo) {
            $this->error('账号未注册');
        }

        if ($userinfo['user_status'] == 0) {
            $this->error('账号已被禁用');
        }

//        if ($userinfo['type'] != 1) {
//            $this->error('您还不是讲师,无权登录');
//        }

        $exist = Db::name('class_teacher')->where('uid',$userinfo['id'])->find();
        if(!$exist){
            $this->error('您不是任何班级的老师');
        }
        $token = md5(md5($userinfo['id'] . $userinfo['user_login'] . time()));
        $userinfo['token'] = $token;


        $this->updateToken($userinfo['id'], $userinfo['token']);

        session('teacher', $userinfo);


    }

    /* 更新token 登陆信息 */
    protected function updateToken($uid, $token)
    {
        $nowtime = time();

        $expiretime = $nowtime + 60 * 60 * 24 * 300;

        DB::name("users")
            ->where("id={$uid}")
            ->update(array('last_login_time' => $nowtime, "last_login_ip" => get_client_ip(0, true)));

        $isok = DB::name("users_token")
            ->where("user_id={$uid}")
            ->update(array("token" => $token, "expire_time" => $expiretime, "create_time" => $nowtime));
        if (!$isok) {
            DB::name("users_token")
                ->insert(array("user_id" => $uid, "token" => $token, "expire_time" => $expiretime, "create_time" => $nowtime));
        }
        $users = Db::name('users')->where(['id'=>$uid])->find();
        $token_info = array(
            'uid' => $uid,
            'token' => $token,
            'expire_time' => $expiretime,
            'is_authentication' => $users['is_authentication'],
            'account_update' => $users['account_update'],
        );

        setcaches("token_" . $uid, $token_info);
        /* 删除PUSH信息 */
        DB::name("users_pushid")->where("uid={$uid}")->delete();

        return 1;
    }

    public function handelGrade($list = [], $pid = 0)
    {
        $rs = [];

        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                unset($list[$k]);
                $v['list'] = $this->handelGrade($list, $v['id']);
                $rs[] = $v;
            }
        }

        return $rs;
    }
}


