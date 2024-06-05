<?php

/* 城市管理 */
namespace app\admin\controller;

use app\models\CityModel;
use app\models\SubstationModel;
use cmf\controller\AdminBaseController;
use think\Db;

class CityController extends AdminBaseController
{

  public function index()
  {
    $data = $this->request->param();
    $map = [];

    $pid = $data['pid'] ?? 0;
    $data['pid'] = $pid;
    $map[] = ['pid', '=', $pid];

    $keyword = $data['keyword'] ?? '';
    if ($keyword != '') {
      $map[] = ['name', 'like', "%{$keyword}%"];
    }

    $list = CityModel::where($map)
      ->order("pid asc,list_order asc,id asc")
      ->paginate(20);


    $list->appends($data);
    // 获取分页显示
    $page = $list->render();
    $this->assign('list', $list);
    $this->assign('page', $page);
    $this->assign('pid', $pid);

    // 渲染模板输出
    return $this->fetch('index');
  }

  public function add()
  {
    $data = $this->request->param();
    $pid = $data['pid'] ?? 0;
    $this->assign('pid', $pid);

    $this->assign('list', CityModel::getLevelOne());

    return $this->fetch();
  }

  public function addPost()
  {
    if ($this->request->isPost()) {

      $data = $this->check();

      $model = new CityModel;

      $id = $model->save($data);
      if (!$id) {
        $this->error("添加失败！");
      }

      CityModel::resetcache();

      $this->success("添加成功！");
    }
  }

  public function edit()
  {
    $id = $this->request->param('id', 0, 'intval');

    $data = CityModel::where("id={$id}")
      ->find();
    if (!$data) {
      $this->error("信息错误");
    }

    $this->assign('data', $data);
    $this->assign('list', CityModel::getLevelOne());

    return $this->fetch();
  }

  public function editPost()
  {
    if ($this->request->isPost()) {

      $data = $this->check();

      $rs = CityModel::update($data);

      CityModel::resetcache();

      $this->success("保存成功！");
    }
  }

  public function check()
  {

    $data = $this->request->param();

    $id = $data['id'] ?? 0;
    $pid = $data['pid'];
    if ($id > 0 && $pid == $id) {
      $this->error('类型上级不能选择当前城市');
    }
    $name = $data['name'];
    if ($name == '') {
      $this->error('请填写名称');
    }

    $area_code = $data['area_code'];
    if ($area_code == '') {
      $this->error('请填写地区编号');
    }


    $map[] = ['pid', '=', $pid];
    $map[] = ['area_code', '=', $area_code];
    if ($id > 0) {
      $map[] = ['id', '<>', $id];
    }
    $isexist = CityModel::field('id')->where($map)->find();
    if ($isexist) {
      $this->error('地区编号已存在');
    }

    return $data;
  }

  public function listOrder()
  {
    $model = new CityModel;
    parent::listOrders($model);
    CityModel::resetcache();
    $this->success("排序更新成功！");
  }

  public function setStatus()
  {
    $id = $this->request->param('id', 0, 'intval');
    $status = $this->request->param('status', 0, 'intval');

    if ($status) {
      $status = 1;
    } else {
      $status = 0;
    }
    $rs = CityModel::where('id', $id)->update(['status' => $status]);
    if (!$rs) {
      $this->error("操作失败！");
    }
    CityModel::resetcache();
    $this->success("操作成功！");
  }

  public function setShopStatus()
  {
    $id = $this->request->param('id', 0, 'intval');
    $status = $this->request->param('status', 0, 'intval');

    if ($status) {
      $status = 1;
    } else {
      $status = 0;
    }
    $rs = CityModel::where('id', $id)->update(['status_shop' => $status]);
    if (!$rs) {
      $this->error("操作失败！");
    }
    CityModel::resetcache();
    $this->success("操作成功！");
  }

  public function enter()
  {
    $id = $this->request->param('id', 0, 'intval');


    $rs = CityModel::where('id', $id)->field('status')->find();
    if (!$rs) {
      $this->error("城市不存在");
    }

    if ($rs['status'] != 1) {
      $this->error("城市尚未开通");
    }

    $sinfo = SubstationModel::where(['cityid' => $id])->field('id,user_status')->find();
    if (!$sinfo) {
      $this->error("该城市尚未添加管理员账号");
    }

    /*if($sinfo['user_status']==0){
        $this->error("该城市尚未添加管理员账号");
    }*/

    session('substationid', $sinfo['id']);

    return $this->redirect(url("substation/index/index"));

  }

  public function del()
  {
    $id = $this->request->param('id', 0, 'intval');

    $rs = CityModel::where('id', $id)->delete();
    if (!$rs) {
      $this->error("删除失败！");
    }
    CityModel::resetcache();
    $this->success("删除成功！");
  }


}
