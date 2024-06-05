<?
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class BookVersionsController extends AdminBaseController
{

  public function index()
  {

    $list = Db::name('book_versions')
      ->order("list_order asc")
      ->paginate(20);

    $page = $list->render();
    $this->assign("page", $page);

    $this->assign('list', $list);

    return $this->fetch();
  }


  public function add()
  {
    return $this->fetch();
  }

  public function addPost()
  {
    if ($this->request->isPost()) {
      $data = $this->request->param();

      $name = $data['name'];

      if ($name == '') {
        $this->error('请填写名称');
      }

      $map[] = ['name', '=', $name];
      $isexist = DB::name('book_versions')->where($map)->find();
      if ($isexist) {
        $this->error('同名分类已存在');
      }



      $id = DB::name('book_versions')->insertGetId($data);
      if (!$id) {
        $this->error("添加失败！");
      }
      $this->resetcache();
      $this->success("添加成功！");
    }
  }

  public function del()
  {
    $id = $this->request->param('id', 0, 'intval');

    $exist = DB::name('school_config_course_class')->where('book_versions_id', $id)->find();
    if($exist){
        $this->error('存在学校使用，无法删除');
    }
    $rs = DB::name('book_versions')->where('id', $id)->delete();
    if (!$rs) {
      $this->error("删除失败！");
    }
    $this->resetcache();
    $this->success("删除成功！");
  }

  public function edit()
  {
    $id = $this->request->param('id', 0, 'intval');

    $data = Db::name('book_versions')
      ->where("id={$id}")
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
      $data = $this->request->param();

      $id = $data['id'];
      $name = $data['name'];

      if ($name == '') {
        $this->error('请填写名称');
      }

      $map[] = ['name', '=', $name];
      $map[] = ['id', '<>', $id];
      $isexist = DB::name('book_versions')->where($map)->find();
      if ($isexist) {
        $this->error('同名分类已存在');
      }



      $rs = DB::name('book_versions')->update($data);

      if ($rs === false) {
        $this->error("保存失败！");
      }
      $this->resetcache();
      $this->success("保存成功！");
    }
  }


  public function listOrder()
  {
    $model = DB::name('book_versions');
    parent::listOrders($model);
    $this->resetcache();
    $this->success("排序更新成功！");
  }


  protected function resetcache()
  {
    $key = 'getbookversions';

    $list = DB::name('book_versions')
      ->order("list_order asc")
      ->select();
    if ($list) {
      setcaches($key, $list);
    } else {
      delcache($key);
    }
  }
}