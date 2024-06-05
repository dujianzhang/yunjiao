<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\Db;

if (!session_id()) session_start();

/**
 * 登录
 */
class LoginController extends StudentBaseController
{

    /* 手机验证码 */
    public function getCode()
    {

        $rs = ['code' => 0, 'msg' => '', 'info' => []];

        $data = $this->request->param();
        $type = isset($data['type']) ? $data['type'] : '0';  // 0登录 1注册 2忘记密码
        $mobile = isset($data['mobile']) ? $data['mobile'] : '';

        $checkdata = array(
            'account' => $mobile,
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
        $url = $this->siteUrl . '/api/?s=Login.GetCode&account=' . $mobile . '&type=' . $type . '&sign=' . $sign;
        $info = curl_get($url);

        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }


        $this->success($info['data']['msg']);
    }


    /* 注册 */
    public function reg()
    {

        $data = $this->request->param();
        $name = isset($data['name']) ? $data['name'] : '';
        $pass = isset($data['pass']) ? $data['pass'] : '';
        $code = isset($data['code']) ? $data['code'] : '';
        $name = checkNull($name);
        $pass = checkNull($pass);
        $code = checkNull($code);


        //请求接口进行注册
        $url = $this->siteUrl . '/api/?s=Login.Reg&username=' . $name . '&code=' . $code . '&pass=' . $pass . '&source=0';
        $info = curl_get($url);
        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }


        //查询学级
        $list = Db::name('course_grade')->order('list_order asc')->select()->toArray();
        foreach ($list as $k => $v) {
            unset($v['list_order']);
            $list[$k] = $v;
        }
        $list = $this->handelGrade($list);
        if (isset($list[0]['list'][0])) {
            $userinfo = $info['data']['info'][0];
            $uid = $userinfo['id'];
            //修改用户的选择年级
            $gradeid = $list[0]['list'][0]['id'];
            $gradename = $list[0]['list'][0]['name'];
            DB::name("users")
                ->where("id={$uid}")
                ->update(array('gradeid' => $gradeid));
            $userinfo['gradename'] = $gradename;
            $userinfo['gradeid'] = $gradeid;
        }

        session('student', $userinfo);
        $this->success($info['data']['msg']);
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

    /* 密码登录 */
    public function loginbypass()
    {

        $data = $this->request->param();
        $name = isset($data['name']) ? $data['name'] : '';
        $pass = isset($data['pass']) ? $data['pass'] : '';
        $ten_days = isset($data['ten_days']) ? $data['ten_days'] : 0;
        $gradeid = isset($data['gradeid']) ? $data['gradeid'] : 0;
        $name = checkNull($name);
        $pass = checkNull($pass);

        //请求密码登录接口
        $url = $this->siteUrl . '/api/?s=Login.Login&username=' . $name . '&pass=' . $pass;
        $info = curl_get($url);

        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $userinfo = $info['data']['info'][0];


        session('student', $userinfo);
        $this->SetGrade($gradeid);

        if ($ten_days > 0) {
            cookie('PHPSESSID',cookie('PHPSESSID'),time() + 10 * 24 * 3600);
        }
        $this->success($info['data']['msg']);

    }

    /* 验证码登录 */
    public function loginbycode()
    {

        $data = $this->request->param();
        $name = isset($data['name']) ? $data['name'] : '';
        $code = isset($data['code']) ? $data['code'] : '';
        $ten_days = isset($data['ten_days']) ? $data['ten_days'] : 0;

        $gradeid = isset($data['gradeid']) ? $data['gradeid'] : 0;

        $name = checkNull($name);
        $code = checkNull($code);


        //请求验证码登录接口
        $url = $this->siteUrl . '/api/?s=Login.LoginByCode&username=' . $name . '&code=' . $code . '&source=0';
        $info = curl_get($url);

        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        if ($ten_days > 0) {
            cookie('PHPSESSID',cookie('PHPSESSID'),time() + 10 * 24 * 3600);
        }

        $userinfo = $info['data']['info'][0];
        session('student', $userinfo);
        $this->SetGrade($gradeid);

        $this->success($info['data']['msg']);
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

        //请求修改密码接口
        $url = $this->siteUrl . '/api/?s=Login.Forget&username=' . $name . '&code=' . $code . '&pass=' . $pass;
        $info = curl_get($url);


        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $this->success('操作成功');

    }

    /* 退出 */
    public function logout()
    {

        session('student', null);

        $this->success('退出成功');
    }

    //qq第三方登录========
    public function qq()
    {
        $href = $_SERVER['HTTP_REFERER'];
        cookie('href', $href, 3600000);
        cookie('identity', 'student', 3600000);
        $referer = $_SERVER['HTTP_REFERER'];
        session('login_referer', $referer);

        require_once CMF_ROOT . 'sdk/qqApi/qqConnectAPI.class.php';

        $gradeid = isset($data['gradeid']) ? $data['gradeid'] : 0;
        session('gradeid', $gradeid);

        $qc1 = new \QC();
        $qc1->qq_login();
    }

    /**
     * 微信登陆
     **/
    public function weixin()
    {
        $configpri = getConfigPri();
        $data = $this->request->param();

        $gradeid = isset($data['gradeid']) ? $data['gradeid'] : 0;

        //-------配置
        $href = $_SERVER['HTTP_REFERER'];
        cookie('href', $href, 3600000);
        $AppID = $configpri['wx_appid_pc'];
        $AppSecret = $configpri['wx_appsecret_pc'];
        $callback = get_upload_path('/student/login/weixin_callback/gradeid/'.$gradeid); //回调地址
        //微信登录
        if (!session_id()) session_start();
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
        $gradeid = isset($_GET['gradeid']) ? $_GET['gradeid'] : '';

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

            $this->loginByThird($type, $openid, $nickname, $avatar,$gradeid);

        }
    }

    protected function loginByThird($type, $openid, $nickname, $avatar,$gradeid)
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
        $url = $this->siteUrl . '/api/?s=Login.LoginByThird&openid=' . $openid . '&type=' . $type . '&sign=' . $sign . '&nicename=' . $nickname . '&avatar=' . $avatar . '&source=0';
        $info = curl_get($url);

        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $userinfo = $info['data']['info'][0];
//        $gradeid = $userinfo['gradeid'];
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
                if ($vipInfo) {
                    $vipId = $vipInfo['vipid'];
                }

                $userinfo['vipid'] = $vipId;
                $userinfo['gradename'] = $gradename;
                $userinfo['gradeid'] = $gradeid;
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
}


