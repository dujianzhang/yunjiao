<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use think\facade\Request;

/**
 * 收藏
 * Class CollectController
 * @package app\student\controller
 */
class CollectController extends StudentBaseController
{
    public function initialize()
    {
        parent::initialize();
        //判断有没有登录
        $this->checkMyLogin();
    }

    /**
     * 题目收藏展示页
     */
    public function index()
    {

        $baseinfo = $this->getBaseInfo();
        $list = $this->getFavList();
        $info = $list['data']['info'] ?? [];
        $tmp = ['rs' => ''];
        foreach ($info as $key => $value) {
            $info[$key]['rs_user'] = $tmp;
            $info[$key]['isfav'] = '1';
        }
        $list['data']['info'] = $info;

        $this->assign([
            'mynavid' => 13,
            'navid' => -1,
            'baseinfo' => $baseinfo['data']['info'][0],
            'topic_list' => json_encode($list),
            'hint' => '暂无收藏',
            'wrong_topic_this_type' => 3,//收藏中添加错题本

        ]);
        return $this->fetch();
    }

    /**
     * 收藏列表
     */
    protected function getFavList()
    {
        $s = 'App.Topic.GetFavList';
        return $this->requestInterface($s);
    }

    /**
     * 获取用户信息
     */
    protected function getBaseInfo()
    {
        $s = 'User.GetBaseInfo';
        return $this->requestInterface($s);
    }
}