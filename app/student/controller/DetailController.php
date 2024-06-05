<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use mysql_xdevapi\Session;
use think\Db;
use \app\student\model\CourseModel;

/**
 * 课程套餐详情
 */
class DetailController extends StudentBaseController
{

    //套餐详情页面
    public function index()
    {

        $data = $this->request->param();
        $CourseModel = new CourseModel();

        //好课推荐
        $whereSort3 = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['sort', 'IN', [2, 3, 4]],
            ['tx_trans', '=', 1],
            ['isvip', '=', 0],//非会员专享
        ];

        if (session('student')) {
            $whereSort3[] = ['gradeid', '=', session('student.gradeid')];
        }
        //直播课
        $course3list = $CourseModel
            ->getCourseList($whereSort3, 6, 0, 0, 'sort ASC')
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'];
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);

            })
            ->toArray();

        //判断有没有登录
//        $this->checkMyLogin();
        $userinfo = session('student');
        $uid = $userinfo['id'] ?? 0;
        $token = $userinfo['token'] ?? 0;

        $packageid = $data['id'];

        $url = $this->siteUrl . '/api/?s=Package.GetInfo&uid=' . $uid . '&token=' . $token . '&packageid=' . $packageid;

        $info = curl_get($url);
        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }
        $info = $info['data']['info'][0];
        $pink_price = json_encode($info['pink_price']); // 拼团价格列表
        $this->assign('pink_price', $pink_price);
        $this->assign('info', $info);
        $this->assign('navid', -1);
        $this->assign('showVip', $this->showVip($info));
        $this->assign('course3list', $course3list);
        return $this->fetch();
    }

    public function showVip($info)
    {
        $vipdiscounts = '';//vip优惠提示
        $vipopen = '';//vip开通提示
        $original_cost = '';//原价
        $price = $info['price'];//活动购买价

        $time_hint = '';//活动提示(拼团-秒杀)状态提示

        $pinktips = '';//拼团提示
        $active_nums_hint = '';//拼团/秒杀数量提示
        $active_status = 0;//拼团/秒杀活动状态
        $css_class = '';

        $userinfo = session('student');
        $nologin =isset($userinfo['id']) ? 0 : 'nologin';
        $pay_type = $info['paytype'] ?? 'pack';


        if ($info['isshowvip'] == 1) {
            if (!session('student.vipid')) {
                $vipdiscounts = "会员{$info['discount']}折优惠，仅需￥{$info['money_vip']}";
                $vipopen = '开通会员';
            } else {
                if (session('student.vipid') == 0) {
                    $vipdiscounts = "{$info['discount']}折优惠，仅需￥{$info['money_vip']}";
                    $vipopen = '开通会员';
                } elseif (session('student.vipid') == 1) {
                    $vipdiscounts = "{$info['discount']}折优惠，仅需￥{$info['money_vip']}";
                    $vipopen = '您是尊贵会员，可享会员价格';
                } elseif (session('student.vipid') == 2) {
                    $vipdiscounts = "会员特惠 免费";
                    $vipopen = '您是尊贵会员，可免费学习';
                }
            }
        }
        if (($info['isseckill'] == 1) || ($info['isseckill'] == 2)) {//秒杀未开始/已开始
            $original_cost = '¥'.$price;
            $price = $info['money_seckill'];
            if ($info['isseckill'] == 2) {
                $time_hint = '距离结束还有';
            } else {
                $time_hint = '距离开始还有';
            }
            $pinktips = "<span class='tips_pink'>限时秒杀</span>";
            $pinktips .= "<span class='seckil_number'>限量{$info['seckill_nums']}件</span>";
            $pinktips .= "<span class='seckil_taps'>秒杀商品不参与会员折扣</span>";
            $active_nums_hint = "{$info['nums_ok']}人已购买";
            $active_status = 1;
            $css_class = 'seckill';

        } else if (($info['ispink'] == 1) || ($info['ispink'] == 2)) {//拼团未开始/已开始
            $original_cost = '¥'.$price;

            $price = $info['money_pink'];
            $pinktips = "<span class='tips_pink'>拼团享特惠,{$info['pink_price'][0]['nums']}人起拼</span>";
            $active_nums_hint = "{$info['pink_nums']}人正在拼团,<span class='pink2_nowpinknums {$nologin}' >去参团</span> >";
            if ($info['ispink'] == 2) {
                $time_hint = '距离结束还有';
            } else {
                $time_hint = '距离开始还有';
            }
            $active_status = 1;
            $css_class = 'pink';
        }

        return compact('vipdiscounts', 'pay_type','vipopen', 'original_cost', 'price', 'time_hint', 'pinktips', 'active_nums_hint','active_status','css_class');
    }

    //课程详情页面
    public function class()
    {

        $data = $this->request->param();


        //判断有没有登录
//        $this->checkMyLogin();
        $userinfo = session('student');
        $uid = $userinfo['id'] ?? 0;
        $token = $userinfo['token'] ?? 0;

        $courseid = $data['id'];

        //课程详情
        $url = $this->siteUrl . '/api/?s=Course.GetDetail&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid;

        $info = curl_get($url);

        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $info = $info['data']['info'][0];

        $this->assign('info', $info);

        $info['price'] = $info['payval'];
        $this->assign('showVip', $this->showVip($info));

        $pink_price = json_encode($info['pink_price']); // 拼团价格列表
        $this->assign('pink_price', $pink_price);

        //课程课时
        $url = $this->siteUrl . '/api/?s=Course.GetLessonList&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid;

        $lessonlist = curl_get($url);

        $lessonlist = $lessonlist['data']['info'];
        foreach ($lessonlist as $k => $v) {
            $v['url'] = string_decrypt($v['url']);
            $lessonlist[$k] = $v;
        }

        $this->assign('lessonlist', $lessonlist);

        //评价列表
        $url = $this->siteUrl . '/api/?s=Comment.GetList&uid=' . $uid . '&token=' . $token . '&p=1&courseid=' . $courseid;

        $commentlist = curl_get($url);

        $commentlist = $commentlist['data']['info'][0]['list'] ?? [];
        $this->assign('commentlist', $commentlist);


        $isMore = 0;
        if (count($commentlist) >= 20) {
            $isMore = 1;
        }
        $this->assign('isMore', $isMore);

        $this->assign('navid', -1);

        $class_hint = '暂无评价';
        if (($info['comments'] > 0) && !$userinfo) {
            $class_hint = '请登录后查看';
        }
        if (($info['comments'] <= 0) && $userinfo) {
            $class_hint = '暂无评价';
        }

        $this->assign('class_hint', $class_hint);
        return $this->fetch();
    }

    //课程学习页面
    public function classstudy()
    {
        $data = $this->request->param();


        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $lessonid = $data['lessonid'];


        if ($lessonid < 1) {
            $this->error('信息错误');
        }

        $lessoninfo = Db::name('course_lesson')->field('*')->where(["id" => $lessonid])->find();
        if (!$lessoninfo) {
            $this->error('课时不存在');
        }

        $courseid = $lessoninfo['courseid'];
        $courseinfo = Db::name('course')->field('name,sort,type,paytype')->where(["id" => $courseid])->find();
        if (!$courseinfo) {
            $this->error('课程不存在');

        }

        $isbuy = '0';
        $paytype = $courseinfo['paytype'];

        if ($paytype != 0) {
            if ($lessoninfo['istrial'] == 1) {
                $isbuy = 1;
            } else {
                $ispay = Db::name('course_users')->field('id')->where(["uid" => $uid, "courseid" => $courseid, "status" => 1])->find();
                if ($ispay) {
                    $isbuy = 1;
                }
            }

        } else {
            $isbuy = 1;

        }

        if ($isbuy == 0 && $userinfo['vipid'] != 2) {
            $this->error('您无权观看');
        }

        if ($isbuy == 1) {

            $this->setLesson($uid, $courseid, $lessonid);
        }


        $lessoninfo['url'] = get_upload_path($lessoninfo['url']);

        $configpri = getConfigPri();
        $tx_appid = $configpri['tx_trans_appid'];
//        print_r($configpri);
        $this->assign([
            'info' => $lessoninfo,
            'tx_appid' => $tx_appid
        ]);


        $this->assign('navid', -1);
        return $this->fetch();
    }


    //直播课程详情页面
    public function live()
    {
        $data = $this->request->param();

        $userinfo = session('student');
        $uid = $userinfo['id'] ?? 0;
        $token = $userinfo['token'] ?? 0;

        $courseid = $data['id'];

        //直播详情
        $url = $this->siteUrl . '/api/?s=Course.GetDetail&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid;

        $info = curl_get($url);
        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $info = $info['data']['info'][0];

        $pink_price = json_encode($info['pink_price']); // 拼团价格列表
        $this->assign('pink_price', $pink_price);
        $courserModel = new CourseModel();
        $info['sort_type'] = $courserModel->getTypeSort($info['sort'], $info['type']);

        $this->assign('info', $info);

        $info['price'] = $info['payval'];
        $this->assign('showVip', $this->showVip($info));
        //评价列表
        $url = $this->siteUrl . '/api/?s=Comment.GetList&uid=' . $uid . '&token=' . $token . '&p=1&courseid=' . $courseid;

        $commentlist = curl_get($url);

        $commentlist = $commentlist['data']['info'][0]['list'] ?? [];
        $this->assign('commentlist', $commentlist);

        $isMore = 0;
        if (count($commentlist) >= 20) {
            $isMore = 1;
        }
        $this->assign('isMore', $isMore);

        $this->assign('navid', -1);

        $class_hint = '暂无评价';
        if (($info['comments'] > 0) && !$userinfo) {
            $class_hint = '请登录后查看';
        }
        if (($info['comments'] <= 0) && $userinfo) {
            $class_hint = '暂无评价';
        }

        $this->assign('class_hint', $class_hint);

        return $this->fetch();
    }

    //内容详情页面
    public function substance()
    {
        $data = $this->request->param();


        //判断有没有登录
//        $this->checkMyLogin();


        $userinfo = session('student');

        $uid = $userinfo['id'] ?? 0;
        $token = $userinfo['token'] ?? 0;
        $courseid = $data['id'];

        //内容详情
        $url = $this->siteUrl . '/api/?s=Course.GetDetail&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid;

        $info = curl_get($url);
//        print_r($info);
        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }
        $info = $info['data']['info'][0];
        $this->assign('info', $info);
        $info['price'] = $info['payval'];
        $this->assign('showVip', $this->showVip($info));

        $pink_price = json_encode($info['pink_price']); // 拼团价格列表
        $this->assign('pink_price', $pink_price);

        //评价列表
        $url = $this->siteUrl . '/api/?s=Comment.GetList&uid=' . $uid . '&token=' . $token . '&p=1&courseid=' . $courseid;

        $commentlist = curl_get($url);

        $commentlist = $commentlist['data']['info'][0]['list'] ?? [];
        $this->assign('commentlist', $commentlist);

        $isMore = 0;
        if (count($commentlist) >= 20) {
            $isMore = 1;
        }
        $this->assign('isMore', $isMore);

        $this->assign('navid', -1);

        $class_hint = '暂无评价';
        if (($info['comments'] > 0) && !$userinfo) {
            $class_hint = '请登录后查看';
        }
        if (($info['comments'] <= 0) && $userinfo) {
            $class_hint = '暂无评价';
        }

        $this->assign('class_hint', $class_hint);
        return $this->fetch();
    }


    //内容学习页面
    public function substancestudy()
    {
        $data = $this->request->param();


        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $courseid = $data['courseid'];

        //内容详情
        $url = $this->siteUrl . '/api/?s=Course.GetDetail&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid;

        $info = curl_get($url);
        if ($info['data']['code'] != 0) {
            $this->error($info['data']['msg']);
        }

        $info = $info['data']['info'][0];

        if ($userinfo['vipid'] != 2) {
            if (($info['paytype'] != 0 && $info['isbuy'] == 0 && $info['trialtype'] == 0) || $info['sort'] != 0) {
                $this->redirect(cmf_url("student/index/index"));
            }
        }

        $info['url'] = string_decrypt($info['url']);
        $info['tx_fileid'] = string_decrypt($info['tx_fileid']);
        $this->assign('info', $info);


        $nowtime = time();

        $courseinfo = Db::name('course')->field('name,sort,type,paytype,trialtype,trialval,content')->where(["id" => $courseid])->find();
        if (!$courseinfo) {
            $this->error('课程不存在');
        }


        $sort = $courseinfo['sort'];
        $type = $courseinfo['type'];
        $paytype = $courseinfo['paytype'];
        $trialtype = $courseinfo['trialtype'];
        $trialval = $courseinfo['trialval'];
        if ($sort == 0) {
            $isbuy = 0;
            if ($paytype != 0) {
                $ispay = Db::name('course_users')->field('id')->where(["uid" => $uid, "courseid" => $courseid, "status" => 1])->find();
                if ($ispay) {
                    $isbuy = 1;
                }
            } else {
                $isbuy = 1;
            }

            if ($isbuy == 1) {
                $this->setLesson($uid, $courseid);
            }

        }

        $configpri = getConfigPri();
        $tx_appid = $configpri['tx_trans_appid'];
        $this->assign('tx_appid', $tx_appid);

        $this->assign('vipid', $userinfo['vipid']);
        $this->assign('navid', -1);
        return $this->fetch();
    }


    /* 更新进度 */
    protected function setLesson($uid, $courseid, $lessonid = 0)
    {
        $nowtime = time();
        $isview = Db::name('course_views')->where(['uid' => $uid, 'courseid' => $courseid, 'lessonid' => $lessonid])->find();
        if ($isview) {
            Db::name('course_views')->where(["id" => $isview['id']])->setField('addtime', $nowtime);
            return !1;
        }

        $course = Db::name('course')->field('sort,type,paytype,lessons,uid')->where(["id" => $courseid])->find();
        if (!$course) {
            return !1;
        }

        $sort = $course['sort'];

        $data = [
            'uid' => $uid,
            'sort' => $sort,
            'courseid' => $courseid,
            'lessonid' => $lessonid,
            'addtime' => $nowtime
        ];
        Db::name('course_views')->insert($data);

        $nums = Db::name('course_views')->where(['uid' => $uid, 'courseid' => $courseid])->count();
        if ($nums < 2) {
            /* 同一课程下的课时 记一次课程学习数 */
            Db::name('course')->where(["id" => $courseid])->setInc('views', 1);
        }


        $isexist = Db::name('course_users')->where(['uid' => $uid, 'courseid' => $courseid])->find();
        if (!$isexist) {
            /*  */
            $status = 0;
            $paytype = $course['paytype'];
            if ($paytype == 0) {
                $status = 1;
            }
            $data2 = [
                'uid' => $uid,
                'sort' => $course['sort'],
                'paytype' => $paytype,
                'courseid' => $courseid,
                'liveuid' => $course['uid'],
                'status' => $status,
                'addtime' => $nowtime,
                'paytime' => $nowtime,
            ];
            Db::name('course_users')->insert($data2);

            $isexist = Db::name('course_users')->where(['uid' => $uid, 'courseid' => $courseid])->find();
        }

        if ($lessonid > 0) {
            Db::name('course_users')->where(['id' => $isexist['id']])->setInc('lessons', 1);

            $lessons = Db::name('course_users')->where(['id' => $isexist['id']])->value('lessons');
            if ($lessons >= $course['lessons']) {
                /* 看完 */
                Db::name('course_users')->where(['id' => $isexist['id']])->setField('step', 2);
            } else {
                Db::name('course_users')->where(['id' => $isexist['id']])->setField('step', 1);
            }
        } else {
            Db::name('course_users')->where(['id' => $isexist['id']])->setField('step', 2);
        }
    }


    /****购买页面 */
    public function buy()
    {
        $data = $this->request->param();

        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');
        $uid = $userinfo['id'];
        $token = $userinfo['token'];

        $courseid = isset($data['courseid']) ? $data['courseid'] : '';
        $type = isset($data['type']) ? $data['type'] : '';
        $method = isset($data['method']) ? $data['method'] : '0';
        $totalmoney = isset($data['totalmoney']) ? $data['totalmoney'] : '0';

        $ispink = isset($data['ispink']) ? $data['ispink'] : '0'; //是不是拼团 0不是 1是
        $pinkid = isset($data['pinkid']) ? $data['pinkid'] : '0';//拼团id 0自己开团
        $pinknums = isset($data['pinknums']) ? $data['pinknums'] : '0';//拼团人数

        $this->assign('ispink', $ispink);
        $this->assign('pinkid', $pinkid);
        $this->assign('pinknums', $pinknums);

        $ismaterial = $data['ismaterial'];

        if ($courseid) {
            $listinfo = [];

            //查询课程的详细信息
            if ($type == 0) { //课程
                $url = $this->siteUrl . '/api/?s=Course.GetDetail&uid=' . $uid . '&token=' . $token . '&courseid=' . $courseid;
            } else { //套餐
                $url = $this->siteUrl . '/api/?s=Package.GetInfo&uid=' . $uid . '&token=' . $token . '&packageid=' . $courseid;
            }
            $info = curl_get($url);
            if ($ispink != 1) {
                $totalmoney = $info['data']['info'][0]['money'] ?? $totalmoney;
            }

            if ($info['data']['code'] == 0) {
                $infos = $info['data']['info'][0];
                unset($infos['info']);
                unset($infos['content']);
                if ($type == 0) {
                    $infos['lesson'] = $infos['lesson'];
                } else {
                    $infos['lesson'] = $infos['nums'] . '课程';
                }
                array_push($listinfo, $infos);
            }


            $goods = [];
            foreach ($listinfo as $k => $v) {
                $arr = ['type' => $type, 'typeid' => $courseid, 'isseckill' => $v['isseckill'] == 2 && $infos['seckill_nums'] > 0 ? 1 : 0];//isseckill 是秒杀 并且剩余大于0能以秒杀购买
                $goods[] = $arr;
            }

        } else {
            $url = $this->siteUrl . '/api/?s=Cart.GetList&uid=' . $userinfo['id'] . '&token=' . $userinfo['token'];
            $info = curl_get($url);

            $listinfo = $info['data']['info'][0]['list'] ?? [];
            $goods = [];
            foreach ($listinfo as $k => $v) {
                if ($v['isselect'] == 0) {
                    unset($listinfo[$k]);
                    continue;
                }

                $arr = ['type' => $v['carttype'], 'typeid' => $v['id'], 'isseckill' => $v['isseckill'] == 2 ? 1 : 0];
                $goods[] = $arr;
            }


        }

        $this->assign('goods', json_encode($goods));

        //可用优惠券数
        $url2 = $this->siteUrl . '/api/?s=Coupon.GetCanList&uid=' . $uid . '&token=' . $token . '&type=1&p=1&money=' . $totalmoney;
        $couponifno = curl_get($url2);

        $can_coupon_nums = $couponifno['data']['info'][0]['cannums'];//可用优惠券数

        $this->assign('can_coupon_nums', $can_coupon_nums);
        // echo '<pre>';
        // print_r($couponifno);
        // echo '</pre>';


        //积分优惠券折扣
        $url1 = $this->siteUrl . '/api/?s=Cart.GetDeduct&uid=' . $uid . '&token=' . $token . '&discount=0&money=' . $totalmoney . '&goods=' . json_encode($goods);
        $info = curl_get($url1);

        $deduct_integral = 0;
        $deduct_money = 0;
        if ($info['data']['code'] == 0) {
            $deduct_integral = $info['data']['info']['0']['deduct_integral'];//抵扣积分数
            $deduct_money = $info['data']['info']['0']['deduct_money'];//抵扣金额数
        }

        $this->assign('deduct_integral', $deduct_integral);
        $this->assign('deduct_money', $deduct_money);


        //需要支付的金额
        $paymoney = $totalmoney - $deduct_money;
        $this->assign('paymoney', round($paymoney, 2));

        //支付方式
        $info = $this->apiAppCartGetPayList();
        $paylist = $info['data']['info'];


        $this->assign('courseid', $courseid);
        $this->assign('type', $type);
        $this->assign('method', $method);
        $this->assign('totalmoney', $totalmoney);
        $this->assign('ismaterial', $ismaterial);
        $this->assign('listinfo', $listinfo);

        $this->assign('paylist', $paylist);
        $this->assign('navid', -1);
        return $this->fetch();
    }

    /****支付页面 */
    public function payment()
    {

        $data = $this->request->param();
        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $method = isset($data['method']) ? $data['method'] : '0';
        $payid = $data['payid'];
        $goods = $data['goods'];
        $couponid = $data['couponid'];
        $deduct_integral = $data['deduct_integral'];
        $ispink = isset($data['ispink']) ? $data['ispink'] : '0'; //是不是拼团 0不是 1是
        $pinkid = isset($data['pinkid']) ? $data['pinkid'] : '0';//拼团id 0自己开团
        $pinknums = isset($data['pinknums']) ? $data['pinknums'] : '0';//拼团人数
        $addrid = isset($data['addrid']) ? $data['addrid'] : '0';//地址id


        //微信支付为了放置订单号重复
        if ($payid == 2) {
            $payid = 7;
        }
        $url2 = $this->siteUrl . '/api/?s=Cart.Buy&ispink=' . $ispink . '&pinkid=' . $pinkid . '&pinknums=' . $pinknums . '&uid=' . $uid . '&token=' . $token . '&payid=' . $payid . '&addrid=' . $addrid . '&method=' . $method . '&goods=' . $goods . '&source=PC&couponid=' . $couponid . '&deduct_integral=' . $deduct_integral;

        $pay = curl_get($url2);

        if ($pay['data']['code'] != 0) {
            //$this->error($pay['data']['msg']);
            echo json_encode($pay);
            exit;
        }
        $configpri = getConfigPri();

        $configpub = getConfigPub();
        if ($payid == 1) { //支付宝
            require_once CMF_ROOT . 'sdk/alipay/pagepay/service/AlipayTradeService.php';
            require_once CMF_ROOT . 'sdk/alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php';


            $config = [
                //应用ID,您的APPID。
                'app_id' => $configpri['alipc_appid'],

                //商户私钥
                'merchant_private_key' => $configpri['alipc_key'],

                //异步通知地址
                'notify_url' => $configpub['site_url'] . '/appapi/cartpay/notify_pc_ali',

                //同步跳转
                'return_url' => $configpub['site_url'] . '/student',

                //编码格式
                'charset' => "UTF-8",

                //签名方式
                'sign_type' => "RSA2",

                //支付宝网关
                'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

                //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
                'alipay_public_key' => $configpri['alipc_publickey'],
            ];

            //商户订单号，商户网站订单系统中唯一订单号，必填
            $out_trade_no = $pay['data']['info'][0]['orderid'];

            //付款金额，必填
            $total_amount = $pay['data']['info'][0]['money'];

            //构造参数
            $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
            $payRequestBuilder->setBody('购买课程');
            $payRequestBuilder->setSubject('购买课程');
            $payRequestBuilder->setTotalAmount($total_amount);
            $payRequestBuilder->setOutTradeNo($out_trade_no);

            $aop = new \AlipayTradeService($config);

            /**
             * pagePay 电脑网站支付请求
             * @param $builder 业务参数，使用buildmodel中的对象生成。
             * @param $return_url 同步跳转地址，公网可以访问
             * @param $notify_url 异步通知地址，公网可以访问
             * @return $response 支付宝返回的信息
             */
            $response = $aop->pagePay($payRequestBuilder, $config['return_url'], $config['notify_url']);
            echo $response;

            die();
            //获取后台设置的 配置信息
            /*  $siteconfig=M("siteconfig")->where("id='1'")->find(); */
            //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            //合作身份者id，以2088开头的16位纯数字
            $alipay_config['partner'] = $configpri['aliapp_partner'];
            //安全检验码，以数字和字母组成的32位字符
            $alipay_config['key'] = $configpri['aliapp_check'];
            //支付宝账号
            $alipay_config['seller_email'] = $configpri['aliapp_seller_id'];
            //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
            //签名方式 不需修改
            $alipay_config['sign_type'] = strtoupper('MD5');
            //字符编码格式 目前支持 gbk 或 utf-8
            $alipay_config['input_charset'] = strtolower('utf-8');
            //ca证书路径地址，用于curl中ssl校验
            //请保证cacert.pem文件在当前文件夹目录中
            $alipay_config['cacert'] = CMF_ROOT . 'sdk/alipay/cacert.pem';
            //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            $alipay_config['transport'] = 'http';
            //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
            require_once CMF_ROOT . "sdk/alipay/lib/alipay_submit.class.php";

            //支付记录
            /**************************请求参数**************************/
            //支付类型
            $payment_type = "1";
            //必填，不能修改
            //服务器异步通知页面路径
            $notify_url = $configpub['site_url'] . '/appapi/cartpay/notify_pc_ali';
            //需http://格式的完整路径，不能加?id=123这类自定义参数
            //页面跳转同步通知页面路径
            $return_url = $configpub['site_url'] . '/student';
            //需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/
            //商户网站订单系统中唯一订单号，必填
            //订单名称
            $subject = "购买课程";
            //付款金额
            $total_fee = $pay['data']['info'][0]['money'];
            //订单描述
            $body = "购买课程";
            //商品展示地址
            $show_url = $configpub['site_url'];
            //需以http://开头的完整路径，例如：http://www.xxx.com/myorder.html
            //防钓鱼时间戳
            $anti_phishing_key = "";
            //若要使用请调用类文件submit中的query_timestamp函数
            //客户端的IP地址
            $exter_invoke_ip = "";
            //非局域网的外网IP地址，如：221.0.0.1
            /************************************************************/
            //构造要请求的参数数组，无需改动
            $parameter = array(
                "service" => "create_direct_pay_by_user",
                "partner" => trim($alipay_config['partner']),
                "payment_type" => $payment_type,
                "notify_url" => $notify_url,
                "return_url" => $return_url,
                "seller_email" => trim($alipay_config['seller_email']),
                "out_trade_no" => $pay['data']['info'][0]['orderid'],
                "subject" => $subject,
                "total_fee" => $total_fee,
                "body" => $body,
                "show_url" => $show_url,
                "qr_pay_mode" => 2,
                "anti_phishing_key" => $anti_phishing_key,
                "exter_invoke_ip" => $exter_invoke_ip,
                "_input_charset" => trim(strtolower($alipay_config['input_charset']))
            );

            //建立请求
            $alipaySubmit = new \AlipaySubmit($alipay_config);
            $html_text = $alipaySubmit->buildRequestForm($parameter, "get", "1");
//            $html_text = $alipaySubmit->buildRequestPara($parameter);
            echo $html_text;
            exit;
        } else if ($payid == 7) { //微信

            $configpri = getConfigPri();
            if ($configpri['pc_wx_appid'] == "" || $configpri['pc_wx_mchid'] == "" || $configpri['pc_wx_key'] == "") {
                //$this->error('微信支付未配置');
                $arr = ['data' => ['code' => 1001, 'msg' => '微信未配置', 'info' => []]];
                echo json_encode($arr);
                exit;
            }


            $noceStr = md5(rand(100, 1000) . time());//获取随机字符串
            $time = time();
            $orderid = $pay['data']['info'][0]['orderid'];
            // var_dump($orderid);
            $paramarr = array(
                "appid" => $configpri['pc_wx_appid'],
                "body" => '购买课程',
                "mch_id" => $configpri['pc_wx_mchid'],
                "nonce_str" => $noceStr,
                "notify_url" => $configpub['site_url'] . '/appapi/cartpay/notify_pc_wx',
                "out_trade_no" => $orderid,
                "total_fee" => $pay['data']['info'][0]['money'] * 100,
                "trade_type" => "NATIVE"
            );
            $sign = "";
            foreach ($paramarr as $k => $v) {
                $sign .= $k . "=" . $v . "&";
            }

            $sign .= "key=" . $configpri['pc_wx_key'];
            $sign = strtoupper(md5($sign));
            $paramarr['sign'] = $sign;
            $paramXml = "<xml>";
            foreach ($paramarr as $k => $v) {
                $paramXml .= "<" . $k . ">" . $v . "</" . $k . ">";
            }
            $paramXml .= "</xml>";

            $ch = curl_init();
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在
            @curl_setopt($ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/pay/unifiedorder");
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_POST, 1);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $paramXml);
            @$resultXmlStr = curl_exec($ch);
            curl_close($ch);

            $msg = array();
            $postStr = $resultXmlStr;
            $msg = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($msg['result_code'] == 'SUCCESS' && $msg['return_code'] == 'SUCCESS' && $msg['return_msg'] == 'OK') {
                $this->deleteDir(CMF_ROOT . 'public/upload/wxpcpay');

                require_once CMF_ROOT . "vendor/phpqrcode/phpqrcode.php";
                $value = $msg['code_url'];
                // var_dump($value);
                $errorCorrectionLevel = 'L';//容错级别
                $i = 320;
                $j = floor($i / 37 * 100) / 100 + 0.01;
                $matrixPointSize = $j;

                $str = $uid . '_' . time();
                if (!is_dir(CMF_ROOT . 'public/upload/wxpcpay')) {
                    mkdir(CMF_ROOT . 'public/upload/wxpcpay');
                }
                $filename = CMF_ROOT . 'public/upload/wxpcpay/' . $str . '.png';

                $QRcode = new \QRcode();
                $QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
                $QR = $filename;
                $QR = imagecreatefromstring(file_get_contents($QR));
                $resData = [
                    'orderid' => $orderid,
                    'url' => $configpub['site_url'] . '/upload/wxpcpay/' . $str . '.png',
                ];

                $arr = ['data' => ['code' => 0, 'msg' => '', 'info' => $resData]];
                echo json_encode($arr);
                exit;
                //$this->success('', '', $resData);

            } else {
                $arr = ['data' => ['code' => 1001, 'msg' => '请求失败请稍后重试', 'info' => []]];
                echo json_encode($arr);
                exit;
            }
        } else if ($payid == 5) { //余额
            echo json_encode($pay);
            exit;
        }

    }

    /****支付价格为零接口 */
    public function payment_ok()
    {

        $data = $this->request->param();
        //判断有没有登录
        $this->checkMyLogin();

        $userinfo = session('student');

        $uid = $userinfo['id'];
        $token = $userinfo['token'];
        $method = isset($data['method']) ? $data['method'] : '0';
        $goods = $data['goods'];
        $couponid = $data['couponid'];
        $deduct_integral = $data['deduct_integral'];
        $ispink = isset($data['ispink']) ? $data['ispink'] : '0'; //是不是拼团 0不是 1是
        $pinkid = isset($data['pinkid']) ? $data['pinkid'] : '0';//拼团id 0自己开团
        $pinknums = isset($data['pinknums']) ? $data['pinknums'] : '0';//拼团人数
        $addrid = isset($data['addrid']) ? $data['addrid'] : '0';//地址id


        $url2 = $this->siteUrl . '/api/?s=Cart.Buy&ispink=' . $ispink . '&pinkid=' . $pinkid . '&pinknums=' . $pinknums . '&uid=' . $uid . '&token=' . $token . '&payid=0&addrid=' . $addrid . '&method=' . $method . '&goods=' . $goods . '&source=PC&couponid=' . $couponid . '&deduct_integral=' . $deduct_integral;
        $pay = curl_get($url2);

        echo json_encode($pay);
        exit;

    }

    /***
     * 删除目录中的所有文件
     * @param $path
     */
    protected function deleteDir($path)
    {

        if (is_dir($path)) {
            //扫描一个目录内的所有目录和文件并返回数组
            $dirs = scandir($path);

            foreach ($dirs as $dir) {
                //排除目录中的当前目录(.)和上一级目录(..)
                if ($dir != '.' && $dir != '..') {
                    //如果是目录则递归子目录，继续操作
                    $sonDir = $path . '/' . $dir;
                    if (is_dir($sonDir)) {
                        //递归删除
                        deleteDir($sonDir);

                        //目录内的子目录和文件删除后删除空目录
                        @rmdir($sonDir);
                    } else {

                        //如果是文件直接删除
                        @unlink($sonDir);
                    }
                }
            }
        }
    }

    /**
     * 获取微信订单支付状态
     */
    public function wxAjaxGetOrderStatus()
    {
        $this->checkMyLogin();
        $orderid = input('orderid');
        if ($orderid == 0) {
            return [
                'code' => 1,
                'msg' => '参数错误',
                'info' => []
            ];
        }
        $where = [];
        $where[] = ['orderno', '=', $orderid];
        $where[] = ['type', '=', 7];
        $orderinfo = Db::name("orders")->field('status')->where($where)->find();

        if ($orderinfo && in_array($orderinfo['status'], [1, 2])) {//已支付
            return [
                'code' => 0,
                'msg' => '已支付',
                'info' => []
            ];
        } else {//未支付
            return [
                'code' => 3,
                'msg' => '未支付',
                'info' => []
            ];
        }
    }
}


