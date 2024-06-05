<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\Db;
/**
 * 名师堂
 */
class CartController extends StudentBaseController {

    //首页
    public function index() {
//判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');
        //购物车数量
        $url = $this->siteUrl.'/api/?s=Cart.GetNums&uid='.$userinfo['id'].'&token='.$userinfo['token'];


        $info = curl_get($url);
        $this->assign('nums',$info['data']['info'][0]['nums']);

        //购物车列表
        $url = $this->siteUrl.'/api/?s=Cart.GetList&uid='.$userinfo['id'].'&token='.$userinfo['token'];

        $info = curl_get($url);

        $selectnum = 0;//选中的个数
        $totalmoney = 0.00; //选中的购物车物品总价值
        $isAllSelect = 1;//是不是全部选中
        $isaddr = 0;//是不是有教材
        $allmoney = 0;//是不是有教材
        $cartids = '';
        foreach($info['data']['info'][0]['list'] ?? [] as $k=>$v){
            if($v['isselect'] == 1){
                $selectnum = $selectnum+1;
                if($v['carttype'] == 0){
                    //$totalmoney = $totalmoney+$v['payval'];
                    $totalmoney = $totalmoney+$v['money'];
                }else{
                    //$totalmoney = $totalmoney+$v['price'];
                    $totalmoney = $totalmoney+$v['money'];
                }
            }else{
                $isAllSelect = 0;
            }
            $cartids = $v['cartid'].','.$cartids;

            if($v['ismaterial'] == 1 && $v['isselect'] == 1){
                $isaddr = 1;
            }
            $allmoney = $allmoney+$v['money'];
        }

        if($isAllSelect == 1 && count($info['data']['info'][0]['list'])==0){
            $isAllSelect = 0;
        }
        $cartids = substr($cartids,0,-1);
        $this->assign('cartids',$cartids);
        $this->assign('isAllSelect',$isAllSelect);
        $this->assign('selectnum',$selectnum);
        $this->assign('totalmoney',$totalmoney);
        $this->assign('allmoney',$allmoney);
        $this->assign('cartlist',$info['data']['info'][0]['list']);
        //$this->assign('isaddr',$info['data']['info'][0]['isaddr']);
        $this->assign('isaddr', $isaddr);

        $this->assign('navid',3);
        return $this->fetch();
    }


    //切换科目老师
    public function chooseTeachers(){
        $data = $this->request->param();

        if(isset($data['p'])){
            $p = $data['p'];
        }else{
            $p = 1;
        }
        $classid = $data['id'];


        $where = 'signoryid = '.$classid;

        $techerslist = $this->GetTeachers($p,$where);


        $this->success('','',$techerslist);
    }


    //获取老师列表
    protected function GetTeachers($p,$where){

        if($p<1){
            $p=1;
        }

        $nums=20;


        $start=($p-1) * $nums;

		$list=Db::name('users')
				->field('id,user_nickname,avatar,avatar_thumb,sex,signature,birthday,type,signoryid,identity')
                ->where('type=1 and user_status!=0 ')
                ->where($where)
				->order('courses desc,list_order asc')
                ->limit($start,$nums)
                ->select()
                ->toArray();


        $userinfo = session('student');
        foreach($list as $k=>$v){
            $v=handleUser($v);

            if($userinfo){
                $v['isAttent'] = isAttent($userinfo['id'],$v['id']);
            }else{
                $v['isAttent'] = 0;
            }


            $list[$k]=$v;
        }


        return $list;
    }


}


