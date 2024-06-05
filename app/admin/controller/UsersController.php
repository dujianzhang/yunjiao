<?php
/* 用户管理 */

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use think\db\Query;

class UsersController extends AdminBaseController
{

  public function index()
  {

    $data = $this->request->param();
    $map = [];

    $type = isset($data['type']) ? $data['type'] : '0';
    if ($type != '') {
      $map[] = ['type', '=', $type];
    }

    $start_time = isset($data['start_time']) ? $data['start_time'] : '';
    $end_time = isset($data['end_time']) ? $data['end_time'] : '';

    if ($start_time != "") {
      $map[] = ['create_time', '>=', strtotime($start_time)];
    }

    if ($end_time != "") {
      $map[] = ['create_time', '<=', strtotime($end_time) + 60 * 60 * 24];
    }


    $isban = isset($data['isban']) ? $data['isban'] : '';
    if ($isban != '') {
      if ($isban == 1) {
        $map[] = ['user_status', '=', 0];
      } else {
        $map[] = ['user_status', '<>', 0];
      }
    }


    $keyword = isset($data['keyword']) ? $data['keyword'] : '';
    if ($keyword != '') {
      $map[] = ['user_login|user_nickname|mobile', 'like', '%' . $keyword . '%'];
    }

    $uid = isset($data['uid']) ? $data['uid'] : '';
    if ($uid != '') {
      $map[] = ['id', '=', $uid];
    }

    $nums = Db::name("users")->where($map)->count();

    $list = Db::name("users")
      ->where($map)
      ->order("id desc")
      ->paginate(20);

    $list->each(function ($v, $k) {

      $v['user_login'] = m_s($v['user_login']);
      $v['mobile'] = m_s($v['mobile']);

      $v['avatar'] = get_upload_path($v['avatar']);

      $v['signory'] = getSignory($v['signoryid']);
      $v['identitys'] = getIdentity($v['identity']);

      return $v;
    });

    $list->appends($data);
    // 获取分页显示
    $page = $list->render();
    $this->assign('list', $list);
    $this->assign('page', $page);

    $this->assign('nums', $nums);
    $this->assign('type', $type);
    // 渲染模板输出
    return $this->fetch('index');
  }

  function teacher()
  {

    return $this->index();
  }

  function del()
  {

    $id = $this->request->param('id', 0, 'intval');

    $user_login = DB::name('users')->where(["id" => $id])->value('user_login');
    $rs = DB::name('users')->where(["id" => $id])->delete();
    if ($rs === false) {
      $this->error("删除失败！");
    }

    DB::name('users_token')->where(["user_id" => $id])->delete();
    delcache("userinfo_" . $id);
    delcache("token_" . $id);

    /* 删除关注关系 */
    DB::name('users_attention')->where(["uid|touid" => $id])->delete();

      DB::name('class_teacher')->where(["uid" => $id])->delete();
      DB::name('class_patriarch')->where(["uid" => $id])->delete();

    $this->success("删除成功！");
  }


  public function listOrder()
  {
    $model = DB::name('users');
    parent::listOrders($model);
    $this->success("排序更新成功！");
  }

  /**
   * 本站用户拉黑
   */
  public function ban()
  {
    $id = input('param.id', 0, 'intval');
    if ($id) {
      $result = Db::name("users")->where(["id" => $id])->setField('user_status', 0);
      if ($result) {

        DB::name('users_token')->where(["user_id" => $id])->delete();
        delcache("userinfo_" . $id);
        delcache("token_" . $id);

        $this->success("会员拉黑成功！");
      } else {
        $this->error('会员拉黑失败,会员不存在');
      }
    } else {
      $this->error('数据传入失败！');
    }
  }

  /**
   * 本站用户启用
   */
  public function cancelBan()
  {
    $id = input('param.id', 0, 'intval');
    if ($id) {
      Db::name("users")->where(["id" => $id])->setField('user_status', 1);
      $this->success("会员启用成功！");
    } else {
      $this->error('数据传入失败！');
    }
  }

  function add()
  {
    return $this->fetch();
  }
  function addPost()
  {
    if ($this->request->isPost()) {

      $data      = $this->request->param();

      $user_login = $data['user_login'];

      if ($user_login == "") {
        $this->error("请填写手机号");
      }

      if (!checkMobile($user_login)) {
        $this->error("请填写正确手机号");
      }

      $isexist = DB::name('users')->where(['user_login|mobile' => $user_login])->value('id');
      if ($isexist) {
        $this->error("该账号已存在，请更换");
      }

      $user_pass = $data['user_pass'];
      if ($user_pass == "") {
        $this->error("请填写密码");
      }

      if (!checkPass($user_pass)) {
        $this->error("密码为6-20位字母数字组合");
      }

      $data['user_pass'] = cmf_password($user_pass);


      $user_nickname = $data['user_nickname'];
      if ($user_nickname == "") {
        $this->error("请填写昵称");
      }

      $isexist = DB::name('users')->where([['user_nickname', '=', $user_nickname]])->find();
      if ($isexist) {
        $this->error("该昵称已存在，请更换");
      }

      $avatar = $data['avatar'];
      $avatar_thumb = $data['avatar_thumb'];
      if (($avatar == "" || $avatar_thumb == '') && ($avatar != "" || $avatar_thumb != '')) {
        $this->error("请同时上传头像 和 头像小图  或 都不上传");
      }

      if ($avatar == '' && $avatar_thumb == '') {
        $data['avatar'] = '/default.png';
        $data['avatar_thumb'] = '/default_thumb.png';
      }

      $data['create_time'] = time();
      $data['mobile'] = $user_login;


        for ($i=0;$i<1;){
            $account = random(8, 1);
            if(!Db::name('users')->where(['account'=>$account])->find()){
                break;
            }
        }
      $data['account'] =$account;
      $id = DB::name('users')->insertGetId($data);
      if (!$id) {
        $this->error("添加失败！");
      }


      $this->success("添加成功！");
    }
  }
  function edit()
  {

    $id   = $this->request->param('id', 0, 'intval');

    $data = Db::name('users')
      ->where("id={$id}")
      ->find();
    if (!$data) {
      $this->error("信息错误");
    }

    $data['user_login'] = m_s($data['user_login']);
    //$data['mobile']=m_s($data['mobile']);
    $this->assign('data', $data);
    return $this->fetch();
  }

  function editPost()
  {
    if ($this->request->isPost()) {

      $data      = $this->request->param();

      $id = $data['id'];
      $user_pass = $data['user_pass'];
      if ($user_pass != "") {
        if (!checkPass($user_pass)) {
          $this->error("密码为6-20位字母数字组合");
        }

        $data['user_pass'] = cmf_password($user_pass);
      } else {
        unset($data['user_pass']);
      }

      $user_nickname = $data['user_nickname'];
      if ($user_nickname == "") {
        $this->error("请填写昵称");
      }

      $isexist = DB::name('users')->where([['user_nickname', '=', $user_nickname], ['id', '<>', $id]])->find();
      if ($isexist) {
        $this->error("该昵称已存在，请更换");
      }

      $mobile = $data['mobile'];
      $isexist = DB::name('users')->where([['user_login|mobile', '=', $mobile], ['id', '<>', $id]])->find();
      if ($isexist) {
        $this->error("该手机号已存在，请更换");
      }

      $avatar = $data['avatar'];
      $avatar_thumb = $data['avatar_thumb'];
      if (($avatar == "" || $avatar_thumb == '') && ($avatar != "" || $avatar_thumb != '')) {
        $this->error("请同时上传头像 和 头像小图  或 都不上传");
      }

      if ($avatar == '' && $avatar_thumb == '') {
        $data['avatar'] = '/default.png';
        $data['avatar_thumb'] = '/default_thumb.png';
      }

      $rs = DB::name('users')->update($data);
      if ($rs === false) {
        $this->error("修改失败！");
      }
      delcache("userinfo_" . $data['id']);
      $this->success("修改成功！");
    }
  }


  /* 取消讲师 */
  public function cancelTeacher()
  {
    $id = input('param.id', 0, 'intval');
    if ($id) {
      $result = Db::name("users")->where(["id" => $id])->update(['type' => 0, 'identity' => '', 'signoryid' => 0]);
      if ($result === false) {
        $this->error('操作失败！');
      }
      delcache("userinfo_" . $id);
      $this->success("操作成功！");
    } else {
      $this->error('数据传入失败！');
    }
  }
  /* 获取专长领域 */
  function signory()
  {

    $id   = $this->request->param('id', 0, 'intval');

    $data = Db::name('users')
      ->field('id,signoryid')
      ->where("id={$id}")
      ->find();
    if (!$data) {
      $this->error("信息错误");
    }

    $list = getSignoryList();

    $this->assign('data', $data);
    $this->assign('list', $list);
    return $this->fetch();
  }

  function setSignory()
  {
    $id = $this->request->param('id', 0, 'intval');
    $signoryid = $this->request->param('signoryid', 0, 'intval');

    if ($id) {
      $result = DB::name("users")->where(['id' => $id])->update(['type' => 1, 'signoryid' => $signoryid]);
      if ($result === false) {
        $this->error('操作失败');
      }
      delcache("userinfo_" . $id);
      $this->success('操作成功');
    } else {
      $this->error('数据传入失败！');
    }
  }

  /* 获取身份标识 */
  function identity()
  {

    $id   = $this->request->param('id', 0, 'intval');

    $data = Db::name('users')
      ->field('id,identity')
      ->where("id={$id}")
      ->find();
    if (!$data) {
      $this->error("信息错误");
    }

    $list = getIdentityList();

    if ($data['identity'] != '') {
      //$data['identity']=explode(',',$data['identity']);
    }

    $this->assign('data', $data);
    $this->assign('list', $list);
    return $this->fetch();
  }

  function setIdentity()
  {
    $id = $this->request->param('id', 0, 'intval');
    //$identitys = $this->request->param('identitys/a');
    $identity = $this->request->param('identity', 0, 'intval');
    if ($id) {
      //$identity='';
      //if($identitys!=''){
      //$identity=implode(',',$identitys);
      // }

      $result = DB::name("users")->where(['id' => $id])->update(['identity' => $identity]);
      if ($result === false) {
        $this->error('操作失败');
      }
      delcache("userinfo_" . $id);
      $this->success('操作成功');
    } else {
      $this->error('数据传入失败！');
    }
  }
}
