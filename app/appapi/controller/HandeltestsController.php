<?php

/* 处理考试 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class HandeltestsController extends HomebaseController{

    public function upTestsUser(){
        $nowtime=time();
        $where=[
            ['ishandel','=',0],
            ['endtime','=',0],
            ['expiretime','<>',0],
            ['expiretime','<',$nowtime],

        ];
        $list=Db::name('tests_user')->where($where)->select()->toArray();
        foreach ($list as $k=>$v){

            $testsinfo=Db::name('tests')->where('id',$v['testsid'])->find();
            if(!$testsinfo){
                Db::name('tests_user')->where('id',$v['id'])->delete();
                continue;
            }
            $endtime=$v['expiretime'];
            $updata=[
                'endtime'=>$endtime,
                'error_nums'=>$testsinfo['nums'],
                'actiontime'=>$nowtime,
                'ishandel'=>1,
            ];

            if($v['answer']==''){
                Db::name('tests_user')->where('id',$v['id'])->update($updata);
                upTestsNums($v['testsid']);
                continue;
            }

            $answer_a=json_decode($v['answer'],true);
            if(!$answer_a){
                Db::name('tests_user')->where('id',$v['id'])->update($updata);
                upTestsNums($v['testsid']);
                continue;
            }

            $content_a=json_decode($testsinfo['content'],true);

            $nums=count($content_a);
            $correct_nums=0;
            $error_nums=0;
            $score=0;
            $isreview=1;
            foreach ($content_a as $k2=>$v2){
                $n=$k2+1;
                $type=$v2['type'];

                $rs_user=$answer_a[$n] ?? [];
                if(!$rs_user){
                    continue;
                }

                $rs_user['isok']='0';
                $rs_user['score']='0';

                $rs=$v2['answer']['rs'];

                if($type==3){
                    /* 简答 */
                    $isreview=0;
                }else if($type==4){
                    /* 填空 */
                    $rs_score=0;
                    $ans=$v2['answer']['ans'];
                    foreach ($ans as $k3=>$v3){
                        $rs_set=$rs_user['rs'][$k3] ?? '';
                        if(in_array($rs_set,$v3)){
                            $rs_score+=$v2['score2'];
                        }
                    }
                    if($rs_score==$v2['score']){
                        $rs_user['isok']='1';
                    }
                    $rs_user['score']=(string)$rs_score;

                }else if($type==5 || $type==2){
                    /* 不定项选择 */
                    $rs_user_a=explode(',',$rs_user['rs']);
                    $rs_a=explode(',',$rs);
                    $isok=0;
                    foreach ($rs_user_a as $k3=>$v3){
                        if(in_array($v3,$rs_a)){
                            $isok=1;
                        }else{
                            $isok=-1;
                            break;
                        }
                    }
                    if($isok==1){
                        if(count($rs_user_a)==count($rs_a)){
                            $rs_user['isok']='1';
                            $rs_user['score']=$v2['score'];
                        }

                        if($type==5 && count($rs_user_a)!=count($rs_a)){
                            $rs_user['score']=$v2['score2'];
                        }
                    }

                }else {
                    if ($rs_user['rs'] == $rs) {
                        $rs_user['isok'] = '1';
                        $rs_user['score'] = $v2['score'];
                    }
                }
                $score += $rs_user['score'];
                $answer_a[$n]=$rs_user;

                if($isreview==1 && $rs_user['isok']==1){
                    $correct_nums++;
                }
            }
            if($isreview==1){
                $error_nums=$nums-$correct_nums;
            }

            $updata['answer']=json_encode($answer_a);
            $updata['correct_nums']=$correct_nums;
            $updata['error_nums']=$error_nums;
            $updata['score']=$score;
            if($isreview==0){
                $updata['actiontime']=0;
            }

            Db::name('tests_user')->where('id',$v['id'])->update($updata);
            upTestsNums($v['testsid']);

            if($isreview==1){
                createCert($v['uid'],$score,$testsinfo);
            }
        }

        echo 'OK';
        exit;
    }
	

}