<?php

namespace  app\appapi\controller;



use cmf\controller\HomeBaseController;

class ShareDownController extends HomeBaseController{
    public function index(){
        $config = getConfigPub();
        $siteName = $config['site_name'] ?? '';
        $sharetrace_key = getConfigPri()['sharetrace'] ?? '';
        $url_ipa = $config['ipa_url'];
        $url_apk = $config['apk_url'];
        $data=$this->request->param();

        $id=$data['id'] ?? 0;
        $sort=$data['sort'] ?? 0;
        return $this->fetch('',
            compact('siteName','sharetrace_key','url_apk','url_ipa','id','sort')
        );
    }
}