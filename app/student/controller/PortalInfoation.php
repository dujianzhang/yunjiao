<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;


/**
 * 动态咨询
 * Class PortalInfoation
 * @package app\student\controller
 */
class PortalInfoation extends StudentBaseController
{
    protected $beforeActionList = [
        'checkMyLogin' => ['only' => 'index'],
    ];

    public function index()
    {
        
    }
}