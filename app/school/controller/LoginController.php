<?php

namespace app\school\controller;

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

    if (session('school')) { //已经登录时直接跳到首页
      return redirect(cmf_url("school/index/index"));
    } else {
      return $this->fetch();
    }
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
      $this->error('请输入账号');
    }
    if ($pass == '') {
      $this->error('请输入密码');
    }


    $user_pass = cmf_password($pass);

    $where['account'] = trim($name);
    $userinfo = Db::name('school')->where($where)->find();

    if (!$userinfo || $userinfo['password'] != $user_pass) {
      $this->error('账号或密码错误');
    }

    session('school', $userinfo);

    $this->success('登陆成功');
  }


  /* 退出 */
  public function logout()
  {

    session('school', null);

    $this->success('退出登录');
  }
}
