<?php


namespace app\student\controller;

use cmf\controller\StudentBaseController;
use app\student\model\ShopCatModel;
use think\Db;

/**
 * 教辅商城
 * Class TeachingmallController
 * @package app\student\controller
 */
class TeachingmallController extends StudentBaseController
{

    public function initialize()
    {
        parent::initialize();
        //判断有没有登录
//        $this->checkMyLogin();
    }

    public function index()
    {
        $ShopCatModel = new ShopCatModel();


        $gradeId = session('gradeid') ?? 0;
        $catList = $ShopCatModel->getList($gradeId);

        $this->assign([
            'catList' => $catList,
            'navid' => 7
        ]);
        return $this->fetch();
    }

    /**
     * 教辅商城详情页 列表
     */
    protected function getHostList($limit = 6)
    {
        $list = Db::name('shop')
            ->where('status', 1)
            ->limit($limit)
            ->order('sales DESC')
            ->select()->toArray();
        foreach ($list as $key => &$value) {
            $value['thumb'] = get_upload_path($value['thumb']);
        }
        return $list;
    }

    public function ajaxgetList()
    {
        $p = input('p/d', 0);
        $cid = input('cid/d', 0);
        if ($cid == 0) {
            return [
                'data' => [
                    'code' => '',
                    'info' => [],
                    'msg' => '',
                ],
                'msg' => '',
                'ret' => 200,
            ];
        }
        return $this->apiAppShopGetList($cid, $p);
    }

    public function detail()
    {

        $id = input('id/d', 0);
        if ($id <= 0) {
            return $this->error('参数错误');
        }
        $hostList = $this->getHostList();

        $info = $this->apiAppShopGetDetail($id);
        $this->assign([
            'info' => $info['data']['info'][0],
            'host_list' => $hostList
        ]);
        return $this->fetch();
    }

    /**
     * 购买页面
     */
    public function buy()
    {
        $this->checkMyLogin();

        $id = input('id/d', 0);
        if ($id <= 0) {
            return $this->error('参数错误');
        }

        //支付方式
        $info = $this->apiAppCartGetPayList();
        $paylist = $info['data']['info'];

        $info = $this->apiAppShopGetDetail($id);
        $this->assign([
            'paylist' => $paylist,
            'info' => $info['data']['info'][0]
        ]);

        return $this->fetch();
    }

    /**
     * 商品下单
     */
    public function ajaxPlaceAnOrder()
    {
        $this->checkMyLogin();


        $addrid = input('addrid/d', 0);
        $shopid = input('shopid/d', 0);
        $payid = input('payid/d', 0);

        if(($shopid <= 0)){
            return $this->error('商品不存在');
        }

        if(($addrid <= 0)){
            return $this->error('请先选择地址');
        }

        if (!in_array($payid, [1, 2, 5])) {
            return $this->error('请选择正确的支付方式');
        }

        //微信支付为了放置订单号重复
        if ($payid == 2) {
            $payid = 7;
        }

        if ($payid == 5) {//余额支付
            $res = $this->apiAppShopordersCreate($payid, $addrid, $shopid);
            if ($res['data']['code'] !== 0) {
                return $this->error($res['data']['msg']);
            }
            return $this->success($res['data']['msg'], '', '');
        } else if ($payid == 1) {//支付宝支付
            return $this->error('参数错误');
        } elseif ($payid == 7) {//微信支付
            $res = $this->apiAppShopordersCreate($payid, $addrid, $shopid);
            if ($res['data']['code'] !== 0) {
                return $this->error($res['data']['msg']);
            }

            $pay = $this->orderWecahtPay($res);
            if ($pay['code'] !== 0) {
                return $this->error($pay['msg']);
            }

            return $this->success($pay['msg'], '', [
                'code_url' => $pay['info']['code_url'],
                'orderid' => $res['data']['info'][0]['orderid'],
            ]);
        }
    }

    /**
     * 支付宝商品下单
     */
    public function alipayBuy()
    {

        $this->checkMyLogin();

        $addrid = input('addrid/d', 0);
        $shopid = input('shopid/d', 0);
        $payid = input('payid/d', 0);

        if (($addrid <= 0) || ($shopid <= 0) || ($payid != 1)) {
            return $this->error('参数错误');
        }

        $res = $this->apiAppShopordersCreate($payid, $addrid, $shopid);
        if ($res['data']['code'] !== 0) {
            return $this->error($res['data']['msg']);
        }

        return $this->alipay($res, '购买教辅资料', '购买教辅资料');

    }


    /**
     * 获取订单状态
     */
    public function getOrderStatus()
    {
        $this->checkMyLogin();
        $orderno = input('orderno');
        if (!$orderno) {
            return $this->success('参数错误', '', ['status' => -1]);
        }
        $where = [];
        $where[] = ['orderno', '=', $orderno];
        $where[] = ['type', '=', 7];
        $orderinfo = Db::name("shop_orders")->field('status')->where($where)->find();

        if ($orderinfo && $orderinfo['status'] == 1) {//已支付
            return $this->success('支付成功', '', ['status' => 1]);

        } else {//未支付
            return $this->success('未支付', '', ['status' => 2]);
        }
    }

    /**
     * 支付宝订单
     * @param $res 支付接口返回的数据
     * @param $subject 订单名称
     * @param $body 订单描述
     * @param $notify_url 服务器异步通知
     */
    protected function alipay($res, $subject, $body, $notify_url = '/appapi/shoppay/notify_pc_ali')
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
    protected function orderWecahtPay($res, $body = '购买教辅资料', $notify_url = '/appapi/shoppay/notify_pc_wx')
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
     * 商品下单接口
     * @param $payid
     * @param $addrid
     * @param $shopid
     * @param string $openid
     */
    public function apiAppShopordersCreate($payid, $addrid, $shopid, $openid = '')
    {
        $s = 'App.Shoporders.Create';
        $queryData = [
            'payid' => $payid,
            'addrid' => $addrid,
            'shopid' => $shopid,
            'openid' => $openid,
            'source' => 0,
        ];
        return $this->requestInterface($s, $queryData);
    }

    /**
     * 商品分类
     * @param $gradeid
     */
    protected function apiAppShopGetCat($gradeid)
    {
        $s = 'App.Shop.GetCat';
        $queryData = [
            'gradeid' => $gradeid
        ];
        return $this->requestInterface($s, $queryData);
    }


    /**
     * 商品列表
     * @param $catid
     * @param int $p
     */
    protected function apiAppShopGetList($catid, $p = 1)
    {
        $s = 'App.Shop.GetList';
        $queryData = [
            'catid' => $catid,
            'p' => $p
        ];
        return $this->requestInterface($s, $queryData);
    }

    /**
     * 商品详情
     * @param $id
     */
    protected function apiAppShopGetDetail($id)
    {
        $s = 'App.Shop.GetDetail';
        $queryData = [
            'id' => $id,
        ];
        return $this->requestInterface($s, $queryData);
    }

}
