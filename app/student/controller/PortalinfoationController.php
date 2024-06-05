<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use app\student\model\PortalArticleModel;

/**
 * 动态咨询
 * Class PortalInfoation
 * @package app\student\controller
 */
class PortalinfoationController extends StudentBaseController
{
    protected $beforeActionList = [
        'checkMyLogin' => ['only' => ''],//需要验证登录的方法
    ];

    public function index()
    {
        $PortalArticleModel = new PortalArticleModel();

        $articleWhere = [
            ['recommended', '=', 1],
            ['delete_time', '=', 0],
            ['post_type', '=', 1],
            ['post_status', '=', 1],
        ];
        $recommended = $PortalArticleModel//推荐
        ->getList($articleWhere)
            ->select()
            ->toArray();

        $cateGoryList = $PortalArticleModel
            ->getList([
                ['delete_time', '=', 0],
                ['post_type', '=', 1],
                ['post_status', '=', 1],
            ])
            ->select()
            ->each(function ($item, $key) {
            })
            ->toArray();

        $this->assign([
            'recommended' => $recommended,
            'cateGoryList' => $cateGoryList,
        ]);
        return $this->fetch();
    }

    public function read()
    {
        $id = input('id/d') ?? 0;

        $PortalArticleModel = new PortalArticleModel();
        $articleWhere = [
            ['recommended', '=', 1],
            ['delete_time', '=', 0],
        ];

        unset($articleWhere[0]);
        $articleWhere = [['id', '=', $id]];

        $info = $PortalArticleModel = $PortalArticleModel
            ->getList($articleWhere)
            ->find();
        $PortalArticleModel->where('id', $id)->setInc('post_hits');
        $info['cate_name'] = $info->category_info[0]['name'] ?? '';
        $info['cate_id'] = $info->category_info[0]['id'] ?? 0;
        $this->assign([
            'info' => $info,
        ]);
        return $this->fetch();

    }
}