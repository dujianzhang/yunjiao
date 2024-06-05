<?
namespace app\admin\controller;

use app\models\CityModel;
use cmf\controller\AdminBaseController;


class AreaController extends AdminBaseController{

  public function index(){
    $data = $this->request->param();
    $id = $data['id'] ?? 0;
    if(!$id){
      $this->error('非法请求');
    }
    $map = [['pid', '=', $id]];
    $list = CityModel::where($map)
      ->order("pid asc,list_order asc,id asc")
      ->paginate(20);
    $list->appends($data);
    // 获取分页显示
    $page = $list->render();
    $this->assign('list', $list);
    $this->assign('page', $page);
    $this->assign('pid', $id);

    // 渲染模板输出
    return $this->fetch('index');
  }


  public function add(){
    $data = $this->request->param();
    $pid = $data['pid'] ?? 0;
    $this->assign('pid', $pid);
    return $this->fetch();
  }


  public function addPost()
  {
    if ($this->request->isPost()) {

      $data = $this->check();
      $data['is_area'] = 1;
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

    return $this->fetch();
  }


  public function editPost()
  {
    if ($this->request->isPost()) {

      $data = $this->check();
      unset($data['pid']);
      $rs = CityModel::update($data);

      CityModel::resetcache();

      $this->success("保存成功！");
    }
  }


  public function check()
  {

    $data = $this->request->param();

    $pid = $data['pid'] ?? 0;

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
 
    $isexist = CityModel::field('id')->where($map)->find();
    if ($isexist) {
      $this->error('地区编号已存在');
    }

    return $data;
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