<?php
/**
 * VIP购买回调
 */

namespace app\appapi\controller;
use cmf\controller\HomeBaseController;
use think\Db;

class VippayController extends HomebaseController {
	//支付宝 回调-app
	public function notify_ali() {
        $configpri=getConfigPri();
        $alipay_config = array (
            //应用ID,您的APPID。
            'app_id' => $configpri['aliapp_appid'],
            //商户私钥
            'merchant_private_key' => $configpri['aliapp_key'],
            //编码格式
            'charset' => "UTF-8",
            //签名方式
            'sign_type'=>"RSA2",
            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => $configpri['aliapp_publickey'],
        );
        require_once(CMF_ROOT."sdk/alipay/pagepay/service/AlipayTradeService.php");

        $alipaySevice = new \AlipayTradeService($alipay_config);
        $result = $alipaySevice->check($_POST);
		$this->logali("ali_data:".json_encode($_POST));
		if($result) {//验证成功
			//商户订单号
			$out_trade_no = $_POST['out_trade_no'];
			//支付宝交易号
			$trade_no = $_POST['trade_no'];
			//交易状态
			$trade_status = $_POST['trade_status'];
			
			//交易金额
			$total_fee = $_POST['total_amount'];
			
			if($_POST['trade_status'] == 'TRADE_FINISHED') {
				//判断该笔订单是否在商户网站中已经做过处理
				//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
				//如果有做过处理，不执行商户的业务程序
					
				//注意：
				//退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
				//请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的

				//调试用，写文本函数记录程序运行情况是否正常
				//logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
		
			}else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
				//判断该笔订单是否在商户网站中已经做过处理
				//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
				//如果有做过处理，不执行商户的业务程序
					
				//注意：
				//付款完成后，支付宝系统发送该交易状态通知
				//请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的

				//调试用，写文本函数记录程序运行情况是否正常
				//logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
                
                $where['orderno']=$out_trade_no;
                $where['type']=1;
                
                $data=[
                    'trade_no'=>$trade_no
                ];

				$this->logali("where:".json_encode($where));	
                $res=$this->handelPay($where,$data);
				if($res==0){
                    $this->logali("orderno:".$out_trade_no.' 订单信息不存在');	
                    echo "fail";
                    exit;
				}
                
                $this->logali("成功");
                echo "success";		//请不要修改或删除
                exit;										
			}
			//——请根据您的业务逻辑来编写程序（以上代码仅作参考）——

			echo "fail";		//请不要修改或删除			
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		}else {
			$this->logali("验证失败");		
			//验证失败
			echo "fail";
			//调试用，写文本函数记录程序运行情况是否正常
			//logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
		}			
		
    }

    //支付宝 回调-pc
    public function notify_pc_ali() {
        $configpri=getConfigPri();
        $alipay_config = array (
            //应用ID,您的APPID。
            'app_id' => $configpri['alipc_appid'],
            //商户私钥
            'merchant_private_key' => $configpri['alipc_key'],
            //编码格式
            'charset' => "UTF-8",
            //签名方式
            'sign_type'=>"RSA2",
            //支付宝网关
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => $configpri['alipc_publickey'],
        );

        require_once(CMF_ROOT."sdk/alipay/pagepay/service/AlipayTradeService.php");

        $alipaySevice = new \AlipayTradeService($alipay_config);
        $result = $alipaySevice->check($_POST);
        $this->logali("ali_data:".json_encode($_POST));
        if($result) {//验证成功
            //商户订单号
            $out_trade_no = $_POST['out_trade_no'];
            //支付宝交易号
            $trade_no = $_POST['trade_no'];
            //交易状态
            $trade_status = $_POST['trade_status'];

            //交易金额
            $total_fee = $_POST['total_amount'];

            if($_POST['trade_status'] == 'TRADE_FINISHED') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //如果有做过处理，不执行商户的业务程序

                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
                //请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的

                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");

            }else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //如果有做过处理，不执行商户的业务程序

                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
                //请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的

                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");

                $where['orderno']=$out_trade_no;
                $where['type']=1;

                $data=[
                    'trade_no'=>$trade_no
                ];

                $this->logali("where:".json_encode($where));
                $res=$this->handelPay($where,$data);
                if($res==0){
                    $this->logali("orderno:".$out_trade_no.' 订单信息不存在');
                    echo "fail";
                    exit;
                }

                $this->logali("成功");
                echo "success";		//请不要修改或删除
                exit;
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——

            echo "fail";		//请不要修改或删除
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        }else {
            $this->logali("验证失败");
            //验证失败
            echo "fail";
            //调试用，写文本函数记录程序运行情况是否正常
            //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
        }

    }

	/* 微信支付 */
    private $wxDate = null;
	public function notify_wx(){
		$configpri=getConfigPri();
		//$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
		$xmlInfo=file_get_contents("php://input"); 

		//解析xml
		$arrayInfo = $this -> xmlToArray($xmlInfo);
		$this -> wxDate = $arrayInfo;
		$this -> logwx("wx_data:".json_encode($arrayInfo));//log打印保存
		if($arrayInfo['return_code'] == "SUCCESS"){
			if(isset($arrayInfo['return_msg']) && $arrayInfo['return_msg'] != null){
				echo $this -> returnInfo("FAIL","签名失败");
				$this -> logwx("签名失败:");//log打印保存
				exit;
			}else{
				$wxSign = $arrayInfo['sign'];
				unset($arrayInfo['sign']);
				$arrayInfo['appid']  =  $configpri['wx_appid'];
				$arrayInfo['mch_id'] =  $configpri['wx_mchid'];
				$key =  $configpri['wx_key'];
				ksort($arrayInfo);//按照字典排序参数数组
				$sign = $this -> sign($arrayInfo,$key);//生成签名
				$this -> logwx("数据打印测试签名signmy:".$sign.":::微信sign:".$wxSign);//log打印保存
				if($this -> checkSign($wxSign,$sign)){
					echo $this -> returnInfo("SUCCESS","OK");
					$this -> logwx("签名验证结果成功:".$sign);//log打印保存
					$this -> orderServer(2);//订单处理业务逻辑
					exit;
				}else{
					echo $this -> returnInfo("FAIL","签名失败");
					$this -> logwx("签名验证结果失败:本地加密：".$sign.'：：：：：三方加密'.$wxSign);//log打印保存
					exit;
				}
			}
		}else{
			echo $this -> returnInfo("FAIL","签名失败");
			$this -> logwx($arrayInfo['return_code']);//log打印保存
			exit;
		}			
	}

    /* 小程序支付 */
    public function notify_small(){
        $configpri=getConfigPri();
        //$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xmlInfo=file_get_contents("php://input");

        //解析xml
        $arrayInfo = $this -> xmlToArray($xmlInfo);
        $this -> wxDate = $arrayInfo;
        $this -> logsmall("small_data:".json_encode($arrayInfo));//log打印保存
        if($arrayInfo['return_code'] == "SUCCESS"){
            if(isset($arrayInfo['return_msg']) && $arrayInfo['return_msg'] != null){
                echo $this -> returnInfo("FAIL","签名失败");
                $this -> logsmall("签名失败:");//log打印保存
                exit;
            }else{
                $smallSign = $arrayInfo['sign'];
                unset($arrayInfo['sign']);
                $arrayInfo['appid']  =  $configpri['small_appid'];
                $arrayInfo['mch_id'] =  $configpri['small_mchid'];
                $key =  $configpri['small_key'];
                ksort($arrayInfo);//按照字典排序参数数组
                $sign = $this -> sign($arrayInfo,$key);//生成签名
                $this -> logsmall("数据打印测试签名signmy:".$sign.":::小程序sign:".$smallSign);//log打印保存
                if($this -> checkSign($smallSign,$sign)){
                    echo $this -> returnInfo("SUCCESS","OK");
                    $this -> logsmall("签名验证结果成功:".$sign);//log打印保存
                    $this -> orderServer(3);//订单处理业务逻辑
                    exit;
                }else{
                    echo $this -> returnInfo("FAIL","签名失败");
                    $this -> logsmall("签名验证结果失败:本地加密：".$sign.'：：：：：三方加密'.$smallSign);//log打印保存
                    exit;
                }
            }
        }else{
            echo $this -> returnInfo("FAIL","签名失败");
            $this -> logsmall($arrayInfo['return_code']);//log打印保存
            exit;
        }
    }

    /* UNIAPP端h5支付 */
    public function notify_hfive(){
        $configpri=getConfigPri();
        //$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xmlInfo=file_get_contents("php://input");

        //解析xml
        $arrayInfo = $this -> xmlToArray($xmlInfo);
        $this -> wxDate = $arrayInfo;
        $this -> loghfive("h5_data:".json_encode($arrayInfo));//log打印保存
        if($arrayInfo['return_code'] == "SUCCESS"){
            if(isset($arrayInfo['return_msg']) && $arrayInfo['return_msg'] != null){
                echo $this -> returnInfo("FAIL","签名失败");
                $this -> loghfive("签名失败:");//log打印保存
                exit;
            }else{
                $hfiveSign = $arrayInfo['sign'];
                unset($arrayInfo['sign']);

                $arrayInfo['appid']  =  $configpri['pc_wx_appid'];
                $arrayInfo['mch_id'] =  $configpri['pc_wx_mchid'];
                $key =  $configpri['pc_wx_key'];
                ksort($arrayInfo);//按照字典排序参数数组
                $sign = $this -> sign($arrayInfo,$key);//生成签名
                $this -> loghfive("数据打印测试签名signmy:".$sign.":::h5sign:".$hfiveSign);//log打印保存
                if($this -> checkSign($hfiveSign,$sign)){
                    echo $this -> returnInfo("SUCCESS","OK");
                    $this -> loghfive("签名验证结果成功:".$sign);//log打印保存
                    $this -> orderServer(4);//订单处理业务逻辑
                    exit;
                }else{
                    echo $this -> returnInfo("FAIL","签名失败");
                    $this -> loghfive("签名验证结果失败:本地加密：".$sign.'：：：：：三方加密'.$hfiveSign);//log打印保存
                    exit;
                }
            }
        }else{
            echo $this -> returnInfo("FAIL","签名失败");
            $this -> loghfive($arrayInfo['return_code']);//log打印保存
            exit;
        }
    }

    /*微信内H5支付 */
    public function notify_mp(){
        $configpri=getConfigPri();
        //$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xmlInfo=file_get_contents("php://input");

        //解析xml
        $arrayInfo = $this -> xmlToArray($xmlInfo);
        $this -> wxDate = $arrayInfo;
        $this -> logmp("small_data:".json_encode($arrayInfo));//log打印保存
        if($arrayInfo['return_code'] == "SUCCESS"){
            if(isset($arrayInfo['return_msg']) && $arrayInfo['return_msg'] != null){
                echo $this -> returnInfo("FAIL","签名失败");
                $this -> logmp("签名失败:");//log打印保存
                exit;
            }else{
                $smallSign = $arrayInfo['sign'];
                unset($arrayInfo['sign']);
                $arrayInfo['appid']  =  $configpri['mp_appid'] ?? '';
                $arrayInfo['mch_id'] =  $configpri['mp_mchid'] ?? '';
                $key =  $configpri['mp_key'] ?? '';
                ksort($arrayInfo);//按照字典排序参数数组
                $sign = $this -> sign($arrayInfo,$key);//生成签名
                $this -> logmp("数据打印测试签名signmy:".$sign.":::小程序sign:".$smallSign);//log打印保存
                if($this -> checkSign($smallSign,$sign)){
                    echo $this -> returnInfo("SUCCESS","OK");
                    $this -> logmp("签名验证结果成功:".$sign);//log打印保存
                    $this -> orderServer(8);//订单处理业务逻辑
                    exit;
                }else{
                    echo $this -> returnInfo("FAIL","签名失败");
                    $this -> logmp("签名验证结果失败:本地加密：".$sign.'：：：：：三方加密'.$smallSign);//log打印保存
                    exit;
                }
            }
        }else{
            echo $this -> returnInfo("FAIL","签名失败");
            $this -> logmp($arrayInfo['return_code']);//log打印保存
            exit;
        }
    }

    /***微信PC支付 */
    public function notify_pc_wx(){
        $configpri=getConfigPri();
        //$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xmlInfo=file_get_contents("php://input");
        //解析xml
        $arrayInfo = $this -> xmlToArray($xmlInfo);
        $this -> wxDate = $arrayInfo;
        $this -> logwxpc("wx_data:".json_encode($arrayInfo));//log打印保存
        if($arrayInfo['return_code'] == "SUCCESS"){
            if(isset($arrayInfo['return_msg']) && $arrayInfo['return_msg'] != null){
                echo $this -> returnInfo("FAIL","签名失败");
                $this -> logwxpc("签名失败:");//log打印保存
                exit;
            }else{
                $wxSign = $arrayInfo['sign'];
                unset($arrayInfo['sign']);
                $arrayInfo['appid']  =  $configpri['pc_wx_appid'];
                $arrayInfo['mch_id'] =  $configpri['pc_wx_mchid'];
                $key =  $configpri['pc_wx_key'];
                ksort($arrayInfo);//按照字典排序参数数组
                $sign = $this -> sign($arrayInfo,$key);//生成签名
                $this -> logwxpc("数据打印测试签名signmy:".$sign.":::微信sign:".$wxSign);//log打印保存
                if($this -> checkSign($wxSign,$sign)){
                    echo $this -> returnInfo("SUCCESS","OK");
                    $this -> logwxpc("签名验证结果成功:".$sign);//log打印保存
                    $this -> orderServer(7);//订单处理业务逻辑
                    exit;
                }else{
                    echo $this -> returnInfo("FAIL","签名失败");
                    $this -> logwxpc("签名验证结果失败:本地加密：".$sign.'：：：：：三方加密'.$wxSign);//log打印保存
                    exit;
                }
            }
        }else{
            echo $this -> returnInfo("FAIL","签名失败");
            $this -> logwxpc($arrayInfo['return_code']);//log打印保存
            exit;
        }
    }
    
	private function returnInfo($type,$msg){
		if($type == "SUCCESS"){
			return $returnXml = "<xml><return_code><![CDATA[{$type}]]></return_code></xml>";
		}else{
			return $returnXml = "<xml><return_code><![CDATA[{$type}]]></return_code><return_msg><![CDATA[{$msg}]]></return_msg></xml>";
		}
	}		
	
	//签名验证
	private function checkSign($sign1,$sign2){
		return trim($sign1) == trim($sign2);
	}
	/* 订单查询加值业务处理
	 * @param orderNum 订单号	   
	 */
	private function orderServer($type=2){
		$info = $this -> wxDate;
		$this->logwx("info:".json_encode($info));
        
        $where['orderno']=$info['out_trade_no'];
        $where['type']=$type;
        
        $trade_no=$info['transaction_id'];
        
        $data=[
            'trade_no'=>$trade_no
        ];
        
        $this->logwx("where:".json_encode($where));	
        $res=$this->handelPay($where,$data);
        if($res==0){
            $this->logwx("orderno:".' 订单信息不存在');
            return false;
        }

        $this->logwx("成功");
        return true;

	}		
	/**
	* sign拼装获取
	*/
	private function sign($param,$key){
		
		$sign = "";
		foreach($param as $k => $v){
			$sign .= $k."=".$v."&";
		}
	
		$sign .= "key=".$key;
		$sign = strtoupper(md5($sign));
		return $sign;
	
	}
	/**
	* xml转为数组
	*/
	private function xmlToArray($xmlStr){
		$msg = array(); 
		$postStr = $xmlStr; 
		$msg = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA); 
		return $msg;
	}

	/* 微信支付 */
    
    /* public function test(){
        $where=[
            'orderno'=>'48_200530104114164',
            'type'=>'1',
        ];
        
        $data=['trade_no'=>'ceshi123'];
        
        handelPay($where,$data);
    } */

    /* 处理支付订单 */
    protected function handelPay($where,$data=[]){
        $orderinfo=Db::name("vip_orders")->where($where)->find();
        if(!$orderinfo){
            return 0;
        }

        if($orderinfo['status']!=0){
            return 1;
        }

        /* 更新 订单状态 */
        $data['status']=1;
        $data['paytime']=time();
        Db::name("vip_orders")->where("id='{$orderinfo['id']}'")->update($data);

        /* 修改vip信息 */
        $nowtime=time();
        $uid=$orderinfo['uid'];
        $vipid=$orderinfo['vipid'];
        $length=$orderinfo['length'];
        $money=$orderinfo['money'];

        $endtime=$nowtime+$length;

        $updata=[
            'uid'=>$uid,
            'vipid'=>$vipid,
            'starttime'=>$nowtime,
            'endtime'=>$endtime,
        ];
        $where2=[
            'uid'=>$uid,
            'vipid'=>$vipid,
        ];
        $vipinfo=Db::name('vip_user')->where($where2)->find();
        if($vipinfo){
            if($vipinfo['endtime']>$nowtime){
                Db::name('vip_user')->where($where2)->inc('money',$money)->inc('endtime',$length)->update();
            }else{
                Db::name('vip_user')->where($where2)->inc('money',$money)->update($updata);
            }

        }else{
            $updata['money']=$money;
            Db::name('vip_user')->insert($updata);
        }

        if($vipid==2){
            /* 低等级顺延 */
            $where3=[
                'uid'=>$uid,
                'vipid'=>1,
            ];
            $vipinfo=Db::name('vip_user')->where($where3)->find();
            if($vipinfo){
                if($vipinfo['endtime']>$nowtime){
                    Db::name('vip_user')->where($where3)->inc('endtime',$length)->update();
                }
            }
        }

        /* 清楚缓存 */
        $key  = 'vip_'.$uid;
        delcache($key);

        return 2;
    }

    /* 打印log */
	protected function logali($msg){
		file_put_contents(CMF_ROOT.'data/log/vip_logali_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').'  msg:'.$msg."\r\n",FILE_APPEND);
	}
    
	/* 打印log */
	protected function logwx($msg){
		file_put_contents(CMF_ROOT.'data/log/vip_logwx_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').'  msg:'.$msg."\r\n",FILE_APPEND);
	}

    /* 打印小程序支付log */
    protected function logsmall($msg){
        file_put_contents(CMF_ROOT.'data/log/vip_logsmall_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').'  msg:'.$msg."\r\n",FILE_APPEND);
    }

    /* 打印h5支付log */
    protected function loghfive($msg){
        file_put_contents(CMF_ROOT.'data/log/vip_loghfive_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').'  msg:'.$msg."\r\n",FILE_APPEND);
    }

    /* 打印微信PC支付log */
    protected function logwxpc($msg){
        file_put_contents(CMF_ROOT.'data/log/vip_wxpc_'.date('Y-m-d').'.txt',date('Y-m-d H:i:s').'  msg:'.$msg."\r\n",FILE_APPEND);
    }
    
}


