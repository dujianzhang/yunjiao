<?php
namespace app\school\controller;

use cmf\controller\SchoolBaseController;
use think\Db;
use think\Exception;

class IndexController extends SchoolBaseController{



  public function initialize(){
    parent::initialize();
    $this->assign('cur','index');
  }

  public function index(){
    $school_id = session('school.id');
    $data = Db::name('class_grade_system')->where('school_id', $school_id)->order('id desc')->paginate(20);
     $year =  array_column(Db::name('year')->select()->toArray(),null,'id');

     foreach ($data as $k=>$v){
         $name = $this->changeKey($v['key'],$v['year_id'],$v['id'],$v['school_id']);
         $v['old_name'] = $this->configs[$v['key']]['name'];
         $v['name'] = $name;
         $v['year'] = $year[$v['year_id']]['name'];
         $data[$k] = $v;
     }

    $page = $data->render();
    $this->assign('page',$page);
    $this->assign('list', $data);
    return $this->fetch();
  }

  public function edit()
  {
    $id = $this->request->param('id',0,'intval');
    if(!$id){
      $this->error('参数异常');
    }
    $school_id = session('school.id');
    $where = ['school_id' => $school_id, 'id' => $id];
    $class_grade_system_info = Db::name('class_grade_system')->where($where)->find();
    if(!$class_grade_system_info){
      $this->error('数据不存在');
    }
    $max = $class_grade_system_info['max'] ;
      $count = Db::name('class_config')->where('key','count')->find()['content'] ?? 0;
    $this->assign('count', $count);
    $this->assign('max', $max);
      $this->assign('id', $id);
    return $this->fetch();
  }


  public function add(){
      $school_id = session('school.id');
      $content = Db::name('school_grade')->field('content')->where('school_id',$school_id)->find()['content'] ?? "[]";
      $content = json_decode($content,true);
      $data = [];
      foreach ($content as $k=>$v){
          $value = $v;
          $info = $this->config[$k];
          foreach ($info as $key => $item){
              if(in_array($item['key'],$value)){
                  $data[] = $item;
              }
          }
      }
      $count = Db::name('class_config')->where('key','count')->find()['content'] ?? 0;
      $year = Db::name('year')->order('name desc')->select();
      $this->assign('year',$year);
      $this->assign('data',$data);
      $this->assign('count',$count);
      return $this->fetch();
  }


  public function addpost(){
    $data = $this->request->param();
    $key = $data['key'] ?? 0;
    $count = $data['count'] ?? 0;
    $year_id = $data['year_id'] ?? 0;
      if(!$year_id){
          $this->error('请选择入学年级');
      }
    if(!$key){
      $this->error('请选择年级');
    }
    if (!$count) {
      $this->error('请选择创建班级数量');
    }
    $school_id = session('school.id');
    $insertAll = [];
    $pid = Db::name('class_grade_system')->insertGetId(['school_id'=> $school_id,'ini_key'=>$key, 'key'=> $key,'count'=>$count, 'max'=> $count,'year_id'=>$year_id]);

    $ClassNumber = $this->getcode($school_id,$year_id);

    for($i=1;$i<=$count;$i++){
        $code = $school_id . $key . $i . random(8 - count($key) - count($school_id) - count($i));
        $insertAll[] = [
          'class' => $i,
          'code'=>$code,
          'key'=> $key,
        'school_id'=> $school_id,
            'pid'=>$pid,
            'year_id' => $year_id,
            'ClassNumber'=>$ClassNumber."_0".$i,
        ];
    }
    $result = Db::name('class')->insertAll($insertAll);
    if($result === false){
      $this->error('创建失败');
    }
    $this->success('成功');
  }


  public function editpost(){
        $param = $this->request->param();
        $id  = $param['id'] ?? 0;
        if(!$id){
            $this->error('参数异常');
        }
        $count = $param['count'] ?? 0;
        if(!$count){
            $this->error('请选择最大班级号');
        }
        $school_id = session('school.id');
        $info = Db::name('class_grade_system')->where(['school_id' => $school_id, 'id' => $id])->find();
        if(!$info){
            $this->error('该年级不属于您的学校');
        }
      $year_id = $info['year_id'] ?? 0;
      $key = $info['key'] ?? 0;
        $max = $info['max'] + 1;
        $insertAll = [];
      $ClassNumber = $this->getcode($school_id,$year_id);
      for($i=$max;$i<=$count;$i++){
          $code = $school_id . $key . $i . random(8 - count($key) - count($school_id) - count($i));
          $insertAll[] = [
              'class' => $i,
              'code'=>$code,
              'key'=> $info['key'],
              'school_id'=> $school_id,
              'pid'=>$info['id'],
              'year_id' => $info['year_id'],
              'ClassNumber'=>$ClassNumber."_0".$i
          ];
      }
      $result = Db::name('class')->insertAll($insertAll);
      if($result === false){
          $this->error('创建失败');
      }
      $where = ['school_id'=> $school_id, 'id'=> $id];
      $update = ['max'=>$count,'count'=>count(Db::name('class')->where(['school_id'=> $school_id, 'pid'=> $id])->select()->toArray())];
      Db::name('class_grade_system')->where($where)->update($update);
      $this->success('成功');
  }


  public function del(){
    $param = $this->request->param();
    $id = $param['id'] ?? 0;
    if(!$id){
      $this->error('参数异常');
    }
    $school_id = session('school.id');
    $info = Db::name('class_grade_system')->where(['school_id' => $school_id, 'id' => $id])->find();
    if(!$info){
      $this->error('班级不存在');
    }
    $where  = ['school_id' => $school_id, 'pid' => $info['id']];
    Db::startTrans();
    try{
        Db::name('class_grade_system')->where(['id'=>$id])->delete();
        $class_id = array_column(Db::name('class')->where($where)->select()->toArray(),'id');
        Db::name('class_chat')->where(['class_id'=>$class_id])->delete();
        Db::name('class_chat_log')->where(['class_id'=>$class_id])->delete();
        Db::name('class')->where($where)->delete();
        Db::name('class_home_work')->where(['class_id'=>$class_id])->delete();
        Db::name('class_home_work_user')->where(['class_id'=>$class_id])->delete();
        Db::name('class_log')->where(['class_id'=>$class_id])->delete();
        Db::name('class_patriarch')->where(['class_id'=>$class_id])->delete();
        Db::name('class_teacher')->where(['class_id'=>$class_id])->delete();
        Db::name('student')->where(['class_id'=>$class_id])->delete();
        Db::name('class_survey')->where(['class_id'=>$class_id])->delete();
        Db::name('class_survey_user')->where(['class_id'=>$class_id])->delete();
        Db::name('class_score')->where(['class_id'=>$class_id])->delete();
        Db::name('class_score_info')->where(['class_id'=>$class_id])->delete();
        Db::name('class_inform')->where(['class_id'=>$class_id])->delete();
        Db::name('class_inform_user')->where(['class_id'=>$class_id])->delete();
        foreach ($class_id as $k=>$v){

            $key = 'teacher_class'.$v;
            delcache($key);

            $key = 'patriarch_class'.$v;
            delcache($key);


        }
        Db::commit();
    }catch (\Exception $e){
        Db::rollback();
        $this->error($e->getMessage());
    }
    $this->success('删除成功');
  }



    //导出
    public function exportpartybranch()
    {

        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('异常');
        }
        $school_id = session('school.id');

        $data = Db::name('class')->field('class,code')->where(['school_id'=>$school_id, 'pid'=>$id])->select()->toArray();
        $arrHeader = ['年级','入学年份','班级', '口令'];
        $rowOne = 'class';
        $rowTwo = 'code';
        $class_info = Db::name('class_grade_system')->where('id', $id)->find();
        $grade = $this->changeKey($class_info['key'],$class_info['year_id'],$id,$class_info['school_id']);
        $year =  array_column(Db::name('year')->select()->toArray(),null,'id')[$class_info['year_id']]['name'];
        $this->exportExcel($data, $arrHeader, $rowOne, $rowTwo,$grade,$year);

    }

    private function exportExcel($xlsData, $arrHeader, $rowOne, $rowTwo,$grade,$year)
    {

        require_once CMF_ROOT . 'sdk/PHPExcel/PHPExcel.php';
        //实例化
        $objExcel = new \PHPExcel();

        //居中对齐
        $objExcel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objExcel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        //设置文档属性
        $objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');
        //设置内容
        $objActSheet = $objExcel->getActiveSheet();
        $key = ord("A");
        $letter = explode(',', "A,B,C,D");
        //填充表头信息
        $lenth = count($arrHeader);
        for ($i = 0; $i < $lenth; $i++) {
            $objActSheet->setCellValue("$letter[$i]1", "$arrHeader[$i]");
        };
        //填充表格信息
        foreach ($xlsData as $k => $v) {
            $k += 2;
            //表格内容
            $objActSheet->setCellValue('A' . $k, $grade);
            $objActSheet->setCellValue('B' . $k, $year);
            $objActSheet->setCellValue('C' . $k, $v[$rowOne].'班');
            $objActSheet->setCellValue('D' . $k, $v[$rowTwo]);

            // 表格高度
            $objActSheet->getRowDimension($k)->setRowHeight(20);
        }

        $width = array(20, 20, 15, 10, 10, 30, 10, 15);
        //设置表格的宽度
        $objActSheet->getColumnDimension('A')->setWidth($width[3]);
        $objActSheet->getColumnDimension('B')->setWidth($width[1]);
        $objActSheet->getColumnDimension('C')->setWidth($width[0]);
        $objActSheet->getColumnDimension('D')->setWidth($width[5]);
        $outfile = md5("班级表" . time()) . ".xlsx";
        ob_end_clean();
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="' . $outfile . '"');
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $objWriter->save('php://output');

    }

    private function getcode($school_id,$year_id){
        $school_info = Db::name('school')->where('id',$school_id)->find();
        $area_code =   Db::name('city')->where('id',$school_info['city_id'])->find()['area_code'];
        $year =   Db::name('year')->where('id',$year_id)->find()['name'];
        $school_code = $school_info['school_code'];
        $type = json_decode($school_info['type'],true);
        $stage_code = 0;
        foreach ($type as $k=>$v){
            if($v < 3){
                $stage_code += $v;
            }
        }
        return $area_code.'_'.$stage_code."_".$school_code."_".$year;
    }


    public function groupStatus(){
        $param = $this->request->param();
        $status = $param['status'] ?? 0;

        if(!in_array($status,[0,1])){
            $this->error('状态有误');
        }
        $id = $param['id'] ?? 0;
        if(!$id){
            $this->error('参数异常');
        }
        $result = Db::name('class_grade_system')->where(['id'=>$id])->update(['is_group_open' => $status]);
        if($result === false){
            $this->error('网络异常');
        }
        $this->success('成功');
    }
}