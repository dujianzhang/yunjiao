<?php

/* 新闻资讯 */
namespace app\appapi\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class NewsController extends HomebaseController{


    public function detail() {

        $id      = $this->request->param('id', 0, 'intval');

        $info=Db::name('news')->where('id',$id)->find();

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