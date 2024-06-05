<?php

namespace  app\teacher\controller;



use cmf\controller\TeacherBaseController;
use think\Db;

class HomeinformController extends  TeacherBaseController{

    public $class_id;

    public function initialize()
    {
        parent::initialize();
        $this->assign('cur','HomeSchool');
        $action = $this->request->action();
        if(!in_array($action,['uploadimg'])){
            $class_id = $this->request->param('class_id',0,'intval');
            if(!$class_id){
                $this->error($action);
            }
            $this->class_id = $class_id;
            $this->assign('class_id',$class_id);
        }

    }


    public function index(){
        $param = $this->request->param();
        $teacher_id = $param['teacher_id'] ?? 0;
        $keyword = $param['keyword'] ?? 0;
        $where = [];
        if($teacher_id){
            $where[] = ['uid','=',$teacher_id];
        }
        if($keyword){
            $where[] =  ['title' , 'like' , "%{$keyword}%"];
        }
        $teacher = Db::name('class_teacher')->where('class_id',$this->class_id)->select()->toArray();
        $this->assign("teacher", $teacher);
        $teacher = array_column($teacher,null,'uid');
        $list = Db::name('class_inform')->where('class_id',$this->class_id)->where($where)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $value = [
                'id' => $v['id'],
                'title' => $v['title'],
                'addtime' => date('Y-m-d H:i:s',$v['addtime']),
                'teacher_name' => $teacher[$v['uid']]['name']
            ];
            $list[$k] = $value;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function add(){
        return $this->fetch();
    }

    public function addpost(){
        $param = $this->request->param();
        $class_id = $param['class_id'];
        $title = $param['title'] ?? 0;
        $content = $param['content'] ?? 0;
        $images = $param['images'] ?? 0;
        if(!$title){
            $this->error('请填写标题');
        }
        if(!$content){
            $this->error('请填写内容');
        }
        if(count($images) < 1 || !$images){
            $this->error('请上传通知图片');
        }
        $images = json_encode($images);
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'title' => $title,
            'content' => $content,
            'images' => $images
        ];
        $url = $this->siteUrl.'/api/?s=School.News.InformAdd';
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $this->success('成功');
        return $this->fetch();
    }


    public function detail(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('网络异常',url('index',['class_id'=>$this->class_id]));
        }
        $info = Db::name('class_inform')->where('id',$id)->find();
        if(!$info){
            $this->error('信息不存在',url('index',['class_id'=>$this->class_id]));
        }
        $images = $info['images'] ? json_decode($info['images'],true) : [];
        foreach ($images as $k=>$v){
            $images[$k] = get_upload_path($v);
        }
        $info['images'] = $images;
        $this->assign('info',$info);
        return $this->fetch();
    }

    public function info(){
        $param = $this->request->param();
        $id = $param['id'] ?? 0;
        $class_id = $param['class_id'] ?? 0;
        if(!$id){
            $this->error('网络异常',url('index',['class_id'=>$this->class_id]));
        }
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'id' => $id
        ];
        $url = $this->siteUrl.'/api/?s=School.News.InformDetail';
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $info = $data['info'];

        $this->assign('id',$id);
        $this->assign('list',$info);
        return $this->fetch();
    }

    public function uploadImg()
    {

        $file = isset($_FILES['file']) ? $_FILES['file'] : '';
        if (!$file) {
            $this->error('请选择图片');
        }
        if ($file['size'] == 0) {
            $this->error('不能上传空文件');
        }
        $res = upload($file, 'image');
        if ($res['code'] != 0) {
            $this->error($res['msg']);
        }
        $url = $res['url'];

        $rs = [
            'url' => get_upload_path($url),
            'path' => $url
        ];
        $this->success('', '', $rs);
    }



    //导出单位排行榜
    public function exportpartybranch()
    {

        $param = $this->request->param();
        $id = $param['id'] ?? 0;
        $class_id = $param['class_id'] ?? 0;
        if(!$id){
            $this->error('网络异常',url('index',['class_id'=>$this->class_id]));
        }
        $class_info = Db::name("class")->where(['id'=>$class_id])->find();
        $year = Db::name("year")->where(['id'=>$class_info['year_id']])->find();
        $userinfo = session('teacher');
        $field = [
            'uid' => $userinfo['id'],
            'token' => $userinfo['token'],
            'class_id' => $class_id,
            'id' => $id
        ];
        $url = $this->siteUrl.'/api/?s=School.News.InformDetail';
        $data = json_decode(curl_post($url,$field)['res'],true)['data'];
        if($data['code']){
            $this->error($data['msg'],url('index'));
        }
        $info = $data['info'];
        $info = array_merge($info['read'],$info['unread']);
        $arrHeader = ['年级','班级','入学年份','是否已读','是否家长', '学生姓名' , '关系' , "手机号"];
        $this->exportExcel($info, $arrHeader,$class_info,$year);

    }

    private function exportExcel($xlsData, $arrHeader,$class_info,$year)
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
        $letter = explode(',', "A,B,C,D,E,F,G,H");
        //填充表头信息
        $lenth = count($arrHeader);
        for ($i = 0; $i < $lenth; $i++) {
            $objActSheet->setCellValue("$letter[$i]1", "$arrHeader[$i]");
        };
        //填充表格信息
        foreach ($xlsData as $k => $v) {
            $k += 2;
            $relation = $v['relation'] ?? 0;
            $objActSheet->setCellValue('A' . $k, $this->configs[$class_info['key']]['name']);
            $objActSheet->setCellValue('B' . $k, $class_info['class']."班");
            $objActSheet->setCellValue('C' . $k, $year['name']."年");
            $objActSheet->setCellValue('D' . $k, $relation ? '已读' : '未读');
            $objActSheet->setCellValue('E' . $k, $relation ? '是' : '否');
            $objActSheet->setCellValue('F' . $k, $v['name']);
            $objActSheet->setCellValue('G' . $k, $relation ? $relation : '');
            $objActSheet->setCellValue('H' . $k, $v['phone'] ??'');

            // 表格高度
            $objActSheet->getRowDimension($k)->setRowHeight(20);
        }

        $width = array(20, 20, 15, 10, 10, 30, 10, 15);
        //设置表格的宽度
        $objActSheet->getColumnDimension('A')->setWidth($width[3]);
        $objActSheet->getColumnDimension('B')->setWidth($width[1]);
        $objActSheet->getColumnDimension('C')->setWidth($width[0]);
        $objActSheet->getColumnDimension('D')->setWidth($width[5]);
        $objActSheet->getColumnDimension('H')->setWidth($width[5]);
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


}