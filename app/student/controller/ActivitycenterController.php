<?php


namespace app\student\controller;

use app\student\model\SlideItemModel;
use cmf\controller\StudentBaseController;
use app\student\model\ActivityModel;
use think\Db;

class ActivitycenterController extends StudentBaseController
{
    public function index()
    {
        $this->assign([
            'navid' => 8,
        ]);
        return $this->fetch();
    }

    public function detail()
    {

        $id = input('id/d', 0);
        if (!$id) {
            return $this->error('参数错误');
        }
        $gradeId = session('gradeid') ?? 0;

        $ActivityModel = new ActivityModel();
        $info = $ActivityModel->getDetail($id, $gradeId);
        if(!$info){
            return $this->error('参数错误或该活动不属于当前年级');
        }

        $hostlist = $ActivityModel->gethostList($gradeId);

        $paylist = [];
        if (session('student')) {
            $paylist = $this->apiAppCartGetPayList();
        }
        $uid = session('student.id') ?? 0;

        $this->assign([
            'info' => $info,
            'hostlist' => $hostlist,
            'paylist' => $paylist['data']['info'] ?? [],
            'status' => $this->checkisenroll($id, $uid)['status'],
        ]);
        return $this->fetch();
    }

    /**
     * 检查是否已报名
     * @param $id
     * @return array|int[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function checkisenroll($id, $uid)
    {
        if ($uid <= 0) {
            return ['status' => 0];
        }
        $res = Db::name('activity_enroll')
            ->where('aid', $id)
            ->where('uid', $uid)
            ->find();

        if (!$res) {
            return ['status' => 0];
        } else {
            return ['status' => $res['status']];
        }
    }

    /**
     * 微信订单状态
     */
    public function ajaxwxorderStatus()
    {
        $this->checkMyLogin(0);

        $orderno = input('orderid');
        if (!$orderno) {
            return $this->success('参数错误', '', ['status' => 0]);
        }

        return $this->success('支付成功', '', $this->checkorderStatus($orderno));
    }

    /**
     * 检查微信订单支付状态
     * @param $orderno
     */
    protected function checkorderStatus($orderno)
    {
        $res = Db::name('activity_enroll')
            ->where('orderno', $orderno)
            ->where('type', 7)
            ->find();

        if (!$res) {
            return ['status' => 0];
        } else {
            return ['status' => $res['status']];
        }
    }

    /**
     * ajax
     */

    public function ajaxgetList()
    {
        $ActivityModel = new ActivityModel();
        $p = input('p/d', 1);
        $gradeId = session('gradeid') ?? 0;
        $list = $ActivityModel->getList($gradeId, $p);

        return $this->success('', '', $list);
    }


    /**
     * 活动报名
     */
    public function ajaxActivityEnroll()
    {
        $this->checkMyLogin(0);

        $id = input('id/d', 0);
        $name = input('name');
        $phone = input('phone', 0);
        $payid = input('payid/d', 0);

        if ($payid == 2) {
            $payid = 7;
        }

        if (!$id || !$name || !$phone) {
            return $this->error('参数错误');
        }

        $res = $this->apiAppActivityEnroll($id, $name, $phone, $payid);

        if ($payid == 7) {//微信支付
            $payres = $this->orderWecahtPay($res);
            return $this->success($payres['msg'], '', [
                'code_url' => $payres['info']['code_url'],
                'orderno' => $res['data']['info'][0]['orderid'],
            ]);

        } else if ($payid == 5) {//余额支付
            return $this->success($res['data']['msg'], '', ['code'=>$res['data']['code']]);
        } else {//免费报名
            return $this->success($res['data']['msg'], '');
        }


    }

    public function activityaliPay()
    {

        $this->checkMyLogin();

        $id = input('id/d', 0);
        $name = input('name');
        $phone = input('phone/d', 0);

        $res = $this->apiAppActivityEnroll($id, $name, $phone, 1);

        $this->alipay($res, '活动购买', '活动购买', $notify_url = '/appapi/activitypay/notify_pc_ali');
    }

    /**
     * 支付宝订单
     * @param $res 支付接口返回的数据
     * @param $subject 订单名称
     * @param $body 订单描述
     * @param $notify_url 服务器异步通知
     */
    protected function alipay($res, $subject, $body, $notify_url = '/appapi/activitypay/notify_pc_ali')
    {
        $configpub = getConfigPub();
        $configpri = getConfigPri();

        require_once CMF_ROOT . 'sdk/alipay/pagepay/service/AlipayTradeService.php';
        require_once CMF_ROOT . 'sdk/alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php';


        $config = [
            //应用ID,您的APPID。
            'app_id' => $configpri['alipc_appid'],

            //商户私钥
            'merchant_private_key' => $configpri['alipc_key'],

            //异步通知地址
            'notify_url' => $configpub['site_url'] . $notify_url,

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
        $out_trade_no = $res['data']['info'][0]['orderid'];

        //付款金额，必填
        $total_amount = $res['data']['info'][0]['money'];

        //构造参数
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
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
        return $response;
    }

    /**
     * 微信支付
     * @param $res 订单支付接口返回的数据
     * @param $body 订单提示
     * @return array|array[]
     */
    protected function orderWecahtPay($res, $body = '购买活动', $notify_url = '/appapi/activitypay/notify_pc_wx')
    {
        $configpub = getConfigPub();
        $configpri = getConfigPri();
        if ($configpri['pc_wx_appid'] == "" || $configpri['pc_wx_mchid'] == "" || $configpri['pc_wx_key'] == "") {
            $arr = ['code' => 1001, 'msg' => '微信未配置', 'info' => []];
            return $arr;
        }

        $noceStr = md5(rand(100, 1000) . time());//获取随机字符
        $orderid = $res['data']['info'][0]['orderid'];
        $paramarr = array(
            "appid" => $configpri['pc_wx_appid'],
            "body" => $body,
            "mch_id" => $configpri['pc_wx_mchid'],
            "nonce_str" => $noceStr,
            "notify_url" => $configpub['site_url'] . $notify_url,
            "out_trade_no" => $orderid,
            "total_fee" => $res['data']['info'][0]['money'] * 100,
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

        $postStr = $resultXmlStr;
        $payres = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (array_key_exists('return_code', $payres) && $payres['return_code'] == 'SUCCESS') {//微信支付请求成功
            return ['code' => 0, 'msg' => '请求微信NATIVE支付成功', 'info' => $payres];
        } else {
            return ['code' => 1, 'msg' => $payres['return_msg'] ?? '微信支付发起失败', 'info' => $payres];
        }
    }


    /**
     * API
     */

    /**
     * 我的活动列表
     * @param $p
     * @param $gradeid
     */
    protected function apiAppActivityGetList($p, $gradeid)
    {
        $s = 'App.Activity.GetList';
        $queryData = [
            'gradeid' => $gradeid,
            'p' => $p
        ];
        return $this->requestInterface($s, $queryData);
    }


    /**
     * 活动详情
     * @param $id
     * @param $gradeid
     */
    protected function apiAppActivityGetDetail($id, $gradeid)
    {
        $s = 'App.Activity.GetDetail';
        $queryData = [
            'gradeid' => $gradeid,
            'id' => $id
        ];
        return $this->requestInterface($s, $queryData);
    }

    /**
     * 活动报名
     * @param $id
     * @param $name
     * @param $mobile
     * @param $payid
     * @param string $openid
     * @return mixed
     */
    protected function apiAppActivityEnroll($id, $name, $mobile, $payid, $openid = '')
    {
        $s = 'App.Activity.Enroll';
        $queryData = [
            'id' => $id,
            'name' => $name,
            'mobile' => $mobile,
            'payid' => $payid,
            'openid' => $openid,
        ];
        return $this->requestInterface($s, $queryData);
    }
}
