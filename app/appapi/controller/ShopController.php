<?php

/* 商品详情 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class ShopController extends HomebaseController{


    public function detail() {

        $id      = $this->request->param('id', 0, 'intval');

        $info=Db::name('shop')->where('id',$id)->find();

        $this->assign('uid', '');
        $this->assign('token', '');

        if (empty($info)) {
            $this->assign('reason', lang('信息错误'));
            return $this->fetch(':error');
        }
        

        $this->assign('page', $info);


        return $this->fetch();
	}	


}