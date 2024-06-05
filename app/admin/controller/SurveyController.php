<?php

namespace  app\admin\controller;



use cmf\controller\AdminBaseController;
use think\Db;

class SurveyController extends  AdminBaseController{

    /* 类型 */
    protected function getTypes($k=''){
        $type=[
            '1'=>'单选题',
            '2'=>'多选题',
            '3'=>'简答题',
        ];
        if($k===''){
            return $type;
        }
        return isset($type[$k])? $type[$k] : '' ;
    }


    public function index(){
        $param = $this->request->param();
        $keyword = $param['keyword'] ?? 0;
        $where = [];
        if($keyword){
            $where[] = ['title','like',"%{$keyword}%"];
        }
        $list = Db::name('class_survey')->where('types',3)->where($where)->order('id desc')->paginate(20);
        foreach ($list as $k=>$v){
            $value = [
                'id' => $v['id'],
                'title' => $v['title'],
                'addtime' => date('Y-m-d H:i:s',$v['addtime']),
                'detail_people'=>'',
                'end_time' => date('Y-m-d H:i:s',$v['end_time']),
            ];
            if($v['school_show']){
                $value['detail_people'].= '学校后台 <br/>';
            }
            if($v['teacher_show']){
                $value['detail_people'].= '老师 <br/>';
            }
            if($v['patriarch_show']){
                $value['detail_people'].= '家长 <br/>';
            }
            $list[$k] = $value;
        }
        $page = $list->render();
        $this->assign("page", $page);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function add(){
        $school_info = Db::name('school')->select();
        $this->assign('school_info',$school_info);
        $this->assign('type', $this->getTypes());

        return $this->fetch();
    }

    public function addpost(){
        $param = $this->request->param();


       if( getcaches($this->request->server('HTTP_COOKIE'))){
           return;
       }
        setcaches( $this->request->server('HTTP_COOKIE'),1,2);
        $title = $param['title'] ?? 0;
        $content = $param['content'] ?? 0;
        $end_time = $param['end_time'] ?? 0;
        $school_id = $param['school_id'] ?? [];
        $people_type = $param['people_type'] ?? 0;
        if(!$title){
            $this->error('请填写标题');
        }
        if(!$content){
            $this->error('请填写内容');
        }

        if(!$end_time){
            $this->error('请选择截止时间');
        }
        if(!$people_type){
            $this->error('请选择接收人员');
        }

        foreach ($content as $k=>$v){
            foreach ($v as $key=>$item){
                $value=[
                    'type' => $key,
                    'title' => $item['title'] ?? ''
                ];
                if($key != 3){
                    $value['info'] = $item['option'] ?? [];
                }
            }
            $type = $value['type'] ?? '';
            if(!in_array($type,[1,2,3])){
                $this->error('数据异常');
            }
            $content[$k] = $value;
        }
        foreach ($content as $k=>$v){
            if(!$v['title']){
                $this->error('请填写题目');
            }
            if($v['type'] == 1 || $v['type'] == 2){
                if(!is_array($v['info'])){
                    $this->error('信息有误');
                }
                if(!count($v['info'])){
                    $this->error('请添加选项');
                }
                $info = $v['info'];
                $detail = [];
                foreach ($info as $key=>$item){
                    $detail[$key] = 0;
                }
            }elseif($v['type'] == 3){
                $detail = [0];
            }else{
                $this->error('信息有误');
            }
            if($v['type'] == 2){
                if(count($v['info']) < 2){
                    $this->error('多选项至少添加两项选择');
                }
            }

            $v['detail'] = $detail;
            $content[$k] = $v;
        }
        $end_time =strtotime($end_time);
        $insert = [
            'class_id' => 0,
            'uid' => 0,
            'title'=>$title,
            'type' => 1,
            'end_time' => $end_time,
            'content' => json_encode($content,true),
            'patriarch_id' => json_encode([] ,true),
            'addtime' => time(),
            'patriarch_show' => 0,
            'teacher_show' => 0 ,
            'school_show' => 0,
            'school_id' => json_encode($school_id,true),
            'types' => 3
        ];
        $http_url = getConfigPri()['http_url'].'/Push/eliminate';
        Db::startTrans();
        try{
            $where = [];
            if($school_id){
                $where['school_id'] = $school_id;
            }
            foreach ($people_type as $k=>$v){
                if($v == 1){
                    $insert['patriarch_show'] = 1;
                }elseif($v == 2){
                    $insert['teacher_show'] = 1;
                }else{
                    $insert['school_show'] = 1;
                }
            }
            $survey_id = Db::name('class_survey')->insertGetId($insert);
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error('错误');
        }

        foreach ($people_type as $k=>$v){
            if($v == 1){
                Db::name('class_patriarch')->where($where)->setInc('survey_count', 1);
                $param_post = [
                    'field'=>'clearSurvey',
                    'type' => '1',
                    'patriarch_id' => json_encode(Db::name('class_patriarch')->where($where)->column('id')),
                    'survey_id' => $survey_id
                ];
                curl_post($http_url,$param_post);
            }elseif($v == 2){
                Db::name('class_teacher')->where($where)->setInc('survey_count', 1);
                $param_post = [
                    'uid' => json_encode(Db::name('class_teacher')->where($where)->column('uid')),
                    'field'=>'clearSurvey',
                    'type' => '2',
                    'survey_id' => $survey_id
                ];
                curl_post($http_url,$param_post);
            }
        }
        $this->success('成功');
    }


    public function content(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_survey')->where('types',3)->where(['id'=>$id])->find();
        if(!$info){
            $this->error('数据异常');
        }
        $info['end_time'] = date('Y-m-d H:i:s',$info['end_time']);
        $info['content'] = json_decode($info['content'],true);
        $info['school_id'] = json_decode($info['school_id'],true);
        $school_info = Db::name('school')->select();
        $this->assign('select_list',['A','B','C','D','E','F','G','H','I','J','K','L','M','N']);
        $this->assign('school_info',$school_info);
        $this->assign('info',$info);
        return $this->fetch();
    }



    public function detail(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_survey')->where('types',3)->where(['id'=>$id])->find();
        if(!$info){
            $this->error('数据异常');
        }
        $info['end_time'] = date('Y-m-d H:i:s',$info['end_time']);
        $content = json_decode($info['content'],true);
        $info['content'] = $content;
        $count = 0;
        $school_id = json_decode($info['school_id'],true);
        if($info['school_show']){
            $count += count($school_id);
        }
        if($info['teacher_show']){
            $where = [];
            if($school_id){
                $where['school_id'] = $school_id;
            }
            $count += Db::name('class_teacher')->where($where)->group('uid')->count();
        }
        if($info['patriarch_show']){
            $where = [];
            if($school_id){
                $where['school_id'] = $school_id;
            }
            $patriarch_info = Db::name('class_patriarch')->where($where)->order('id desc')->select();
            $survey_user_info= Db::name('class_survey_user')->where(['survey_id'=>$id])->select();
            $patriarch_id = array_column($survey_user_info->toArray(),'patriarch_id');
            $read_count = $unread_count = 0;
            foreach ($patriarch_info as $k=>$v){
                if(in_array($v['id'],$patriarch_id)){
                    $read_count += 1;
                }else{
                    $unread_count += 1;
                }
            }
            $count += $read_count;
            $count += $unread_count;
        }

        foreach ($content as $k=>$v){
            $detail = $v['detail'];
            $info_val= $v['info'] ?? '';
            if($v['type'] ==1 || $v['type'] ==2){
                foreach ($info_val as $key=>$item){
                    $ratio = $detail[$key] ?  ($detail[$key] / $count) * 100 : 0;
                    $info_val[$key] = [
                        'option' => $item,
                        'count' => $detail[$key] ?? 0,
                        'ratio' =>$ratio > 100 ? 100 : (int)$ratio
                    ];
                }
            }else{
                $ratio = $detail[0] ? ($detail[0] / $count) * 100 : 0;
                $info_val = [
                    'count' => $detail[0] ?? 0,
                    'ratio' =>  $ratio > 100 ? 100 : (int)$ratio
                ];
            }
            unset($v['detail']);
            $v['info'] = $info_val;
            $content[$k] = $v;
        }

        $info['content'] = $content;
        $info['school_id'] = json_decode($info['school_id'],true);
        $school_info = Db::name('school')->select();
        $this->assign('select_list',['A','B','C','D','E','F','G','H','I','J','K','L','M','N']);
        $this->assign('school_info',$school_info);
        $this->assign('info',$info);
        return $this->fetch();
    }


    public function export(){
        $id = $this->request->param('id',0,'intval');
        if(!$id){
            $this->error('参数异常');
        }
        $info = Db::name('class_survey')->where('types',3)->where(['id'=>$id])->find();
        if(!$info){
            $this->error('数据异常');
        }
        $info['end_time'] = date('Y-m-d H:i:s',$info['end_time']);
        $content = json_decode($info['content'],true);
        $info['content'] = $content;
        $count = 0;
        $school_id = json_decode($info['school_id'],true);
        if($info['school_show']){
            $count += count($school_id);
        }
        if($info['teacher_show']){
            $where = [];
            if($school_id){
                $where['school_id'] = $school_id;
            }
            $count += Db::name('class_teacher')->where($where)->group('uid')->count();
        }
        if($info['patriarch_show']){
            $where = [];
            if($school_id){
                $where['school_id'] = $school_id;
            }
            $patriarch_info = Db::name('class_patriarch')->where($where)->order('id desc')->select();
            $survey_user_info= Db::name('class_survey_user')->where(['survey_id'=>$id])->select();
            $patriarch_id = array_column($survey_user_info->toArray(),'patriarch_id');
            $read_count = $unread_count = 0;
            foreach ($patriarch_info as $k=>$v){
                if(in_array($v['id'],$patriarch_id)){
                    $read_count += 1;
                }else{
                    $unread_count += 1;
                }
            }
            $count += $read_count;
            $count += $unread_count;
        }

        foreach ($content as $k=>$v){
            $detail = $v['detail'];
            $info_val= $v['info'] ?? '';
            if($v['type'] ==1 || $v['type'] ==2){
                foreach ($info_val as $key=>$item){
                    $ratio = $detail[$key] ?  ($detail[$key] / $count) * 100 : 0;
                    $info_val[$key] = [
                        'option' => $item,
                        'count' => $detail[$key] ?? 0,
                        'ratio' =>$ratio > 100 ? 100 : (int)$ratio
                    ];
                }
            }else{
                $ratio = $detail[0] ? ($detail[0] / $count) * 100 : 0;
                $info_val = [
                    'count' => $detail[0] ?? 0,
                    'ratio' =>  $ratio > 100 ? 100 : (int)$ratio
                ];
            }
            unset($v['detail']);
            $v['info'] = $info_val;
            $content[$k] = $v;
        }

        $this->exportExcel($content);
    }

    private function exportExcel($xlsData)
    {

        require_once CMF_ROOT . 'sdk/PHPExcel/PHPExcel.php';

        $fileName = md5("问卷表" . time()) . ".xlsx";
        // //创建新的PHPExcel对象
        $objPHPExcel = new \PHPExcel();
        $objProps = $objPHPExcel->getProperties();


        $objActSheet = $objPHPExcel->getActiveSheet();

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);//类型
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);//问答名称
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(25);//统计信息


        $objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(30);   // 设置行高
        $objPHPExcel->getActiveSheet()->getRowDimension(2)->setRowHeight(30);   // 设置行高

        $objPHPExcel->setActiveSheetIndex(0) ->setCellValue('A1', '类型');
        $objPHPExcel->setActiveSheetIndex(0) ->setCellValue('B1', '问答名称');
        $objPHPExcel->setActiveSheetIndex(0) ->setCellValue('C1', '统计信息');

        $type = [1=>'单选',2=>'多选',3=>'问答'];

        //exit;
        $column = 2;
        foreach($xlsData as $key => $value){
            $column = $key+2;
            $objPHPExcel->getActiveSheet()->getRowDimension($column)->setRowHeight(80);   // 设置行高
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A'.$column,$type[$value['type']]);

            $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B'.$column,$value['title']);

            $info = '';
            if(in_array($value['type'],[1,2])){
                $data = $value['info'];
                foreach ($data as $index=>$item){
                    $option = $item['option'];
                    $count = $item['count'];
                    $ratio = $item['ratio'];
                    $info.= "选择 $option : 完成人数$count , 成百分比$ratio%\r\n";
                }
            }else{
                $data = $value['info'];
                $count = $data['count'];
                $ratio = $data['ratio'];
                $info = "完成人数$count , 成百分比$ratio";
            }


            $objPHPExcel->getActiveSheet()->getStyle('C'.$column)->getAlignment()->setWrapText(true);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue('C'.$column,$info);

        }

        $objActSheet->getColumnDimension('A')->setWidth(20);
        $objActSheet->getColumnDimension('B')->setWidth(20);
        $objActSheet->getColumnDimension('C')->setWidth(150);


        $objPHPExcel->getActiveSheet()->getStyle('A1:P'.$column)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER); //  单元格居中
        $objPHPExcel->getActiveSheet()->getStyle('A1:P'.$column)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //  垂直居中
        $fileName = iconv("utf-8", "gb2312", $fileName);
        //重命名表
        $objPHPExcel->getActiveSheet()->setTitle('稿件列表');
        //设置活动单指数到第一个表,所以Excel打开这是第一个表
        $objPHPExcel->setActiveSheetIndex(0);
        ob_end_clean();
        ob_start();
        //将输出重定向到一个客户端web浏览器(Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

        $objWriter->save('php://output');
        exit;


    }

}