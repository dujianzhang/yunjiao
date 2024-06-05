<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class SchoolController extends AdminBaseController
{

  public function index()
  {
    $city = getcaches('citylist');
    if (!$city) {
      $city = \app\models\CityModel::getList();
      setcaches('citylist', $city);
    }
    $city = array_column($city, null, 'id');
    $list = Db::name('school')->order('id desc')->paginate(20);
    $page = $list->render();
    $this->assign('list', $list);
    $this->assign('city', $city);
    $this->assign('page', $page);
    return $this->fetch();
  }


  public function add()
  {
    $list = getcaches('citylist');
    if (!$list) {
      $list = \app\models\CityModel::getList();
      setcaches('citylist', $list);
    }
    $province = handelList($list, 0);
    $city = ($province[0]['id'] ?? 0) ? handelList($list, $province[0]['id']) : [];
    $area = ($city[0]['id'] ?? 0) ? handelList($list, $city[0]['id']) : [];
    $this->assign('province', $province);
    $this->assign('city', $city);
    $this->assign('area', $area);
    return $this->fetch();
  }

  public function edit()
  {
    $id = $this->request->param('id', 0, 'intval');
    if (!$id) {
      $this->error('参数异常');
    }
    $data = Db::name('school')->where('id', $id)->find();
    $data['type'] = json_decode($data['type'],true);
    $this->assign('data', $data);
    $list = getcaches('citylist');
    if (!$list) {
      $list = \app\models\CityModel::getList();
      setcaches('citylist', $list);
    }
    $province = handelList($list, 0);
    $city = handelList($list, $data['province_id']);
    $area = ($city[0]['id'] ?? 0) ? handelList($list, $city[0]['id']) : [];
    $this->assign('province', $province);
    $this->assign('city', $city);
    $this->assign('area', $area);
    return $this->fetch();
  }

  public function editPost()
  {
    $param = $this->request->param();
    $id = $param['id'] ?? 0;
    if (!$id) {
      $this->error('参数异常');
    }
    $password = $param['password'] ?? 0;
    if ($password) {
      $param['password'] = cmf_password($param['password']);
    }
    $this->check($param);
      $param['type'] = json_encode($param['type'],true);
    $result = Db::name('school')->update($param);
    if ($result === false) {
      $this->error('添加失败');
    }
    $this->success('成功');
  }

  public function addPost()
  {
    $param = $this->request->param();
    $account = $param['account'] ?? 0;
    $password = $param['password'] ?? 0;
    if (!$account) {
      $this->error('账号不能为空');
    }
    if (!$password) {
      $this->error('密码不能为空');
    }
      $school_code = $param['school_code'] ?? 0;
      if (!$school_code) {
          $this->error('学校号不能为空');
      }
    $this->check($param);
    $param['password'] = cmf_password($param['password']);
      $param['type'] = json_encode($param['type'],true);
    $id = Db::name('school')->insertGetId($param);
    if ($id === false) {
      $this->error('添加失败');
    }
      Db::name('school_config_research')->insert(['school_id'=>$id]);
      Db::name('school_grade')->insert(['school_id'=>$id]);
    $this->success('成功');
  }


  public function del()
  {
    $id = $this->request->param('id', 0, 'intval');
    if (!$id) {
      $this->error('参数异常');
    }
      $exist = Db::name('class')->where('school_id', $id)->find();
    if($exist){
        $this->error('请联系学校删除班级后在尝试删除');
    }
    $result = Db::name('school')->where('id', $id)->delete();
    if ($result === false) {
      $this->error('删除失败');
    }

    $this->success('成功');
  }

  public function check($param)
  {
    $name = $param['name'] ?? 0;
    $province_id = $param['province_id'] ?? 0;
    $city_id = $param['city_id'] ?? 0;
    $area_id = $param['area_id'] ?? 0;
    $type = $param['type'] ?? 0;

    if (!$name) {
      $this->error('学校名称不能为空');
    }

    if (!$type) {
      $this->error('请选择阶段');
    }
    if (!$province_id) {
      $this->error('请选择省份');
    }
    if (!$city_id) {
      $this->error('请选择城市');
    }
    if (!$area_id) {
      $this->error('请选择区域');
    }
  }

  public function city_data()
  {
    $province_id = $this->request->param('province_id', 0, 'intval');
    if (!$province_id) {
      $this->error('参数异常');
    }
    $list = getcaches('citylist');
    if (!$list) {
      $list = \app\models\CityModel::getList();
      setcaches('citylist', $list);
    }
    $city = handelList($list, $province_id);
    $area = ($city[0]['id'] ?? 0) ? handelList($list, $city[0]['id']) : [];
    $data = [
      'city' => $city,
      'area' => $area
    ];
    $this->success('', '', $data);
  }


  public function listOrder()
  {
    $model = DB::name('school');
    parent::listOrders($model);
    $this->success("排序更新成功！");
  }
}
