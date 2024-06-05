<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;

class BrushController extends StudentBaseController
{
    public function index()
    {

        $s = 'Brush.GetIndex';
        $queryData = [
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];

        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }

        $info=$res_info['info'][0];

        $record=[];
        $s2 = 'Brush.GetList';
        $queryData2 = [
        ];
        $res2= $this->requestInterface($s2, $queryData2);

        $res_info2=$res2['data'] ?? [];

        if($res_info2){
            $record=$res_info2['info'] ?? [];
            if($record){
                $record=array_slice($record,0,5);
            }
        }

        $uid=session('student.id');
        $gradeid=session('student.gradeid');

        $wrong_nums=db('wrongbook')->where([ ['uid','=',$uid],['gradeid','=',$gradeid],['classid','>=',0] ])->count();
        $collect_nums=db('question_fav')->where([ ['uid','=',$uid],['gradeid','=',$gradeid],['testid','=',0] ])->count();

        $this->assign([
            'navid' => 4,
            'info' => $info,
            'record' => $record,
            'wrong_nums' => $wrong_nums,
            'collect_nums' => $collect_nums,
        ]);
        return $this->fetch();

    }

    public function setNums(){
        $data = $this->request->param();

        $nums=$data['nums'] ?? 0;
        if($nums<=0){
            $this->error('参数错误');
        }

        $s = 'Brush.SetNums';
        $queryData = [
            'nums' => $nums
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }

        $this->success($res_info['msg']);

    }

    public function detail()
    {

        $id = input('id/d', 0);
        if (!$id) {
            $this->error('参数错误');
        }

        $s = 'Brush.GetDetail';
        $queryData = [
            'brushid' => $id
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }


        $info=$res_info['info'][0];

        $length=$info['length'];

        $iz=floor($length/60);
        $hz=floor($iz/60);
        $dz=floor($hz/24);

        /* 秒 */
        $s=$length%60;
        /* 分 */
        $i=floor($iz%60);
        /* 时 */
        $h=floor($hz%24);

        if($length<60){
            $length_s= lang('<span>{:s}</span>秒',['s'=>$s]);
            $length_s2=lang('{:s}秒钟',['s'=>$s]);
        }else if($iz<60){
            $length_s= lang('<span>{:i}</span>分<span>{:s}</span>秒',['i'=>$iz,'s'=>$s]);
            $length_s2=lang('{:i}分钟',['i'=>$iz]);
        }else if($hz<24){
            $length_s= lang('<span>{:h}</span>时<span>{:i}</span>分',['h'=>$hz,'i'=>$i]);
            $length_s2=lang('{:h}小时',['h'=>$hz]);
        }else{
            $length_s= lang('<span>{:d}</span>天<span>{:h}</span>时',['d'=>$dz,'h'=>$h]);
            $length_s2=lang('{:h}小时',['h'=>$hz]);
        }


        $this->assign([
            'navid' => 4,
            'info' => $info,
            'length_s' => $length_s,
            'length_s2' => $length_s2,
        ]);
        return $this->fetch();
    }

    public function record()
    {

        $s = 'Brush.GetList';
        $queryData = [
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }


        $list=$res_info['info'];


        $this->assign([
            'navid' => 4,
            'record' => $list,
        ]);
        return $this->fetch();
    }

    public function wrong()
    {

        $s = 'Wrongbook.GetCat';
        $queryData = [
            'type'=>1,
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }


        $list=$res_info['info'];


        $this->assign([
            'navid' => 4,
            'cat' => $list,
        ]);
        return $this->fetch();
    }

    public function collect()
    {

        $s = 'Topic.GetCat';
        $queryData = [
            'type'=>1,
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }


        $list=$res_info['info'];


        $this->assign([
            'navid' => 4,
            'cat' => $list,
        ]);
        return $this->fetch();
    }

    public function begin(){

        $data = $this->request->param();

        $classid=$data['classid'] ?? 0;

        if($classid<0){
            $this->error('信息错误');
        }

        $this->assign([
            'navid' => 4,
            'type' => 0,
            'classid' => $classid,
        ]);
        return $this->fetch('brush');

    }

    public function goon(){

        $data = $this->request->param();

        $type=$data['type'] ?? 0;
        $brushid=$data['brushid'] ?? 0;

        if($brushid<0 || !in_array($type,[1,2,3]) ){
            $this->error('信息错误');
        }

        $this->assign([
            'navid' => 4,
            'type' => $type,
            'brushid' => $brushid,
        ]);
        return $this->fetch('brush');
    }

    public function getTopic(){
        $data = $this->request->param();

        $type=$data['type'] ?? 0;
        $classid=$data['classid'] ?? 0;
        $brushid=$data['brushid'] ?? 0;

        if(!in_array($type,[0,1,2,3])){
            $this->error('信息错误');
        }

        if($type==0 && $classid<1){
            $this->error('信息错误');
        }

        if($type != 0 && $brushid<1){
            $this->error('信息错误');
        }

        if($type==0){
            $s = 'Brush.GetToplist';
            $queryData = [
                'classids'=>$classid,
            ];
            $res= $this->requestInterface($s, $queryData);
        }

        if($type!=0){
            $s = 'Brush.GetBrushTopic';
            $queryData = [
                'type'=>$type,
                'brushid'=>$brushid,
            ];
            $res= $this->requestInterface($s, $queryData);
        }

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }

        $info=$res_info['info'][0];

        $this->success('','',$info);
    }

    public function setAnswer(){
        $data = $this->request->param();

        $brushid=$data['brushid'] ?? 0;
        $rs=$data['rs'] ?? [];

        $s = 'Brush.SetAnswer';
        $queryData = [
            'brushid'=>$brushid,
            'result'=>$rs,
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }

        $this->success($res_info['msg']);
    }

    public function end(){
        $data = $this->request->param();

        $brushid=$data['brushid'] ?? 0;
        $length=$data['length'] ?? 0;


        $s = 'Brush.End';
        $queryData = [
            'length'=>$length,
            'brushid'=>$brushid,
        ];
        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }

        $this->success($res_info['msg']);
    }

    public function uptime(){
        $data = $this->request->param();

        $brushid=$data['brushid'] ?? 0;
        $length=$data['length'] ?? 0;


        $s = 'Brush.UpTime';
        $queryData = [
            'length'=>$length,
            'brushid'=>$brushid,
        ];
        $userinfo = session('student');

        $signdata=[
            'uid'=>$userinfo['id'],
            'token'=>$userinfo['token'],
            'length'=>$length,
            'brushid'=>$brushid,
        ];
        $sign=getSign($signdata);
        $queryData['sign']=$sign;


        $res= $this->requestInterface($s, $queryData);

        $res_info=$res['data'] ?? [];
        if(!$res_info){
            $this->error('信息错误');
        }
        if($res_info['code']!=0){
            $this->error($res_info['msg']);
        }

        $this->success($res_info['msg']);
    }

    public function wrong_begin(){

        $data = $this->request->param();

        $classid=$data['classid'] ?? 0;

        $this->assign([
            'navid' => 4,
            'type' => 4,
            'classid' => $classid,
        ]);
        return $this->fetch('practice');

    }

    public function collect_begin(){

        $data = $this->request->param();
        $classid=$data['classid'] ?? 0;
        $this->assign([
            'navid' => 4,
            'type' => 5,
            'classid' => $classid,
        ]);
        return $this->fetch('practice');

    }
}
