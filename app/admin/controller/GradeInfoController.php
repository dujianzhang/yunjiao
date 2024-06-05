<?

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class GradeInfoController  extends AdminBaseController
{


  public function index()
  {
    $this->assign('list', $this->configs);

    return $this->fetch();
  }


    public function change(){
      $key = $this->request->param('key',0,'intval');
      if(!$key){
          $this->error('信息有误');
      }
      $info = Db::name('class_grade')->where('key',$key)->find();
      if(!$info){
          $this->error('系统错误');
      }
      $this->assign('info',$info);
      return $this->fetch();
    }

    public function Setchange(){
        $param = $this->request->param();
        $key = $param['key'] ?? 0;
        $change_time = $param['change_time'] ?? 0;
        if(!$key || !$change_time){
            $this->error('信息有误');
        }
        $result= Db::name('class_grade')->where('key',$key)->update(['change_time'=>$change_time]);
        if($result === false){
            $this->error('失败');
        }
        $this->success('成功');
    }

    public function count(){
        $data = Db::name('class_config')->where('key','count')->find();
        $this->assign('data',$data);
        return $this->fetch();
    }

    public function setCount(){
      $count = $this->request->param('count',0,'intval');
      if(!$count){
          $this->error('班级数量不能为空');
      }
        $result = Db::name('class_config')->where('key','count')->update(['content'=>$count]);
        if($result === false){
            $this->error('失败');
        }
      $this->success('成功');
    }
}
