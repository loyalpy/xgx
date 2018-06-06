<?php
/**
 * @class pay_weixin
 * @brief 微信支付插件类
 */
/**
 * 微信支付帮助库
 * ====================================================
 * 接口分三种类型：
 * 【请求型接口】--Wxpay_client_
 *      统一支付接口类--UnifiedOrder
 *      订单查询接口--OrderQuery
 *      退款申请接口--Refund
 *      退款查询接口--RefundQuery
 *      对账单接口--DownloadBill
 *      短链接转换接口--ShortUrl
 * 【响应型接口】--Wxpay_server_
 *      通用通知接口--Notify
 *      Native支付——请求商家获取商品信息接口--NativeCall
 * 【其他】
 *      静态链接二维码--NativeLink
 *      JSAPI支付--JsApi
 * =====================================================
 * 【WxPayBase】常用工具：
 *      trimString()，设置参数时需要用到的字符处理函数
 *      createNoncestr()，产生随机字符串，不长于32位
 *      formatBizQueryParaMap(),格式化参数，签名过程需要用到
 *      getSign(),生成签名
 *      arrayToXml(),array转xml
 *      xmlToArray(),xml转 array
 *      postXmlCurl(),以post方式提交xml到对应的接口url
 *      postXmlSSLCurl(),使用证书，以post方式提交xml到对应的接口url
*/
class WxPayConf{
    //=======【基本信息设置】=====================================
    //受理商ID，身份标识
    public static $MCHID = '1347785001';

    //微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
    public static $APPID = 'wx5e8370aa438a29d6';
    
    //商户支付密钥Key。审核通过后，在微信发送的邮件中查看
    public static $KEY = '7a3758fd9e5a00434991c7de70e098c5';
    //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
    public static $APPSECRET = '4f80359cfbbe71a7a91d58f509c2792d';
    
    //=======【JSAPI路径设置】===================================
    //获取access_token过程中的跳转uri，通过跳转将code传入jsapi支付页面
    public static $JS_API_CALL_URL = 'http://home.bajiedns.com/weixinpay2/demo/js_api_call.php';
    
    //=======【证书路径设置】=====================================
    public static $SSL_PATH = "cache/cacert/";
    //证书路径,注意应该填写绝对路径
    public static $SSLCERT_FILE = 'apiclient_cert.pem';
    public static $SSLKEY_FILE = 'apiclient_key.pem';
    
    //=======【异步通知url设置】===================================
    //异步通知url，商户根据实际开发过程设定
    public static $NOTIFY_URL = 'http://home.bajiedns.com/weixinpay2/demo/notify_url.php';

    //=======【curl超时设置】===================================
    //本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
    public static $CURL_TIMEOUT = 30;
}
/**
 * 所有接口的基类
 */
class WxPayBase{
    function __construct() {
    }
    function trimString($value){
        $ret = null;
        if (null != $value){
            $ret = $value;
            if (strlen($ret) == 0) 
            {
                $ret = null;
            }
        }
        return $ret;
    }    
    /**
     *  作用：产生随机字符串，不长于32位
     */
    public function createNoncestr( $length = 32 ){
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {  
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
        }  
        return $str;
    }    
    /**
     *  作用：格式化参数，签名过程需要使用
     */
    function formatBizQueryParaMap($paraMap, $urlencode){
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
               $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) 
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }    
    /**
     *  作用：生成签名
     */
    public function getSign($Obj){
        foreach ($Obj as $k => $v){
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo '【string1】'.$String.'</br>';
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".WxPayConf::$KEY;
        //echo "【string2】".$String."</br>";
        //签名步骤三：MD5加密
        $String = md5($String);
        //echo "【string3】 ".$String."</br>";
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        //echo "【result】 ".$result_."</br>";
        return $result_;
    }
    
    /**
     *  作用：array转xml
     */
    function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val){
            if (is_numeric($val)){
               $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">"; 
            } 
        }
        $xml.="</xml>";
        return $xml; 
    }    
    /**
     *  作用：将xml转为array
     */
    public function xmlToArray($xml){       
        //将XML转为array        
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);      
        return $array_data;
    }
    /**
     *  作用：以post方式提交xml到对应的接口url
     */
    public function postXmlCurl($xml,$url,$second=30){       
        //初始化curl        
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        }else{ 
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error"."<br>"; 
            curl_close($ch);
            return false;
        }
    }

    /**
     *  作用：使用证书，以post方式提交xml到对应的接口url
     */
    function postXmlSSLCurl($xml,$url,$second=30){
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch,CURLOPT_HEADER,FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        //设置证书
        //使用证书：cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, ROOT_PATH.WxPayConf::$SSL_PATH.WxPayConf::$SSLCERT_FILE);
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, ROOT_PATH.WxPayConf::$SSL_PATH.WxPayConf::$SSLKEY_FILE);
        //post提交方式
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else { 
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error"."<br>"; 
            curl_close($ch);
            return false;
        }
    }
    
    /**
     *  作用：打印数组
     */
    function printErr($wording='',$err=''){
        print_r('<pre>');
        echo $wording."</br>";
        var_dump($err);
        print_r('</pre>');
    }
}

/**
 * 请求型接口的基类
 */
class Wxpay_client extends WxPayBase{
    var $parameters;//请求参数，类型为关联数组
    public $response;//微信返回的响应
    public $result;//返回参数，类型为关联数组
    var $url;//接口链接
    var $curl_timeout;//curl超时时间
    
    /**
     *  作用：设置请求参数
     */
    function setParameter($parameter, $parameterValue){
        $this->parameters[$this->trimString($parameter)] = $this->trimString($parameterValue);
    }
    
    /**
     *  作用：设置标配的请求参数，生成签名，生成接口参数xml
     */
    function createXml(){
        $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
        $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
        $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
        $this->parameters["sign"] = $this->getSign($this->parameters);//签名
        return  $this->arrayToXml($this->parameters);
    }
    
    /**
     *  作用：post请求xml
     */
    function postXml(){
        $xml = $this->createXml();
        $this->response = $this->postXmlCurl($xml,$this->url,$this->curl_timeout);
        return $this->response;
    }
    
    /**
     *  作用：使用证书post请求xml
     */
    function postXmlSSL(){   
        $xml = $this->createXml();
        $this->response = $this->postXmlSSLCurl($xml,$this->url,$this->curl_timeout);
        return $this->response;
    }

    /**
     *  作用：获取结果，默认不使用证书
     */
    function getResult(){       
        $this->postXml();
        $this->result = $this->xmlToArray($this->response);
        return $this->result;
    }
}


/**
 * 统一支付接口类
 */
class WxUnifiedOrder extends Wxpay_client{   
    function __construct(){
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;
    }
    
    /**
     * 生成接口参数xml
     */
    function createXml(){
        try{
            //检测必填参数
            if($this->parameters["out_trade_no"] == null){
                throw new Exception("缺少统一支付接口必填参数out_trade_no！"."<br>");
            }elseif($this->parameters["body"] == null){
                throw new Exception("缺少统一支付接口必填参数body！"."<br>");
            }elseif ($this->parameters["total_fee"] == null ){
                throw new Exception("缺少统一支付接口必填参数total_fee！"."<br>");
            }elseif ($this->parameters["notify_url"] == null){
                throw new Exception("缺少统一支付接口必填参数notify_url！"."<br>");
            }elseif ($this->parameters["trade_type"] == null){
                throw new Exception("缺少统一支付接口必填参数trade_type！"."<br>");
            }elseif ($this->parameters["trade_type"] == "JSAPI" &&
                $this->parameters["openid"] == NULL){
                throw new Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！"."<br>");
            }
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->parameters["spbill_create_ip"] = $_SERVER['REMOTE_ADDR'];//终端ip      
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (Exception $e){
            die($e->getMessage());
        }
    }
    
    /**
     * 获取prepay_id
     */
    function getPrepayId(){
        $this->postXml();
        $this->result = $this->xmlToArray($this->response);
        $prepay_id = $this->result["prepay_id"];
        return $prepay_id;
    }    
}

/**
 * 订单查询接口
 */
class WxOrderQuery extends Wxpay_client{
    function __construct(){
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/pay/orderquery";
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;      
    }

    /**
     * 生成接口参数xml
     */
    function createXml(){
        try{
            //检测必填参数
            if($this->parameters["out_trade_no"] == null && 
                $this->parameters["transaction_id"] == null){
                throw new Exception("订单查询接口中，out_trade_no、transaction_id至少填一个！"."<br>");
            }
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (Exception $e){
            die($e->getMessage());
        }
    }

}

/**
 * 退款申请接口
 */
class WxRefund extends Wxpay_client{    
    function __construct() {
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;      
    }
    
    /**
     * 生成接口参数xml
     */
    function createXml(){
        try{
            //检测必填参数
            if($this->parameters["out_trade_no"] == null && $this->parameters["transaction_id"] == null) {
                throw new Exception("退款申请接口中，out_trade_no、transaction_id至少填一个！"."<br>");
            }elseif($this->parameters["out_refund_no"] == null){
                throw new Exception("退款申请接口中，缺少必填参数out_refund_no！"."<br>");
            }elseif($this->parameters["total_fee"] == null){
                throw new Exception("退款申请接口中，缺少必填参数total_fee！"."<br>");
            }elseif($this->parameters["refund_fee"] == null){
                throw new Exception("退款申请接口中，缺少必填参数refund_fee！"."<br>");
            }elseif($this->parameters["op_user_id"] == null){
                throw new Exception("退款申请接口中，缺少必填参数op_user_id！"."<br>");
            }
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (Exception $e){
            die($e->getMessage());
        }
    }
    /**
     *  作用：获取结果，使用证书通信
     */
    function getResult(){       
        $this->postXmlSSL();
        $this->result = $this->xmlToArray($this->response);
        return $this->result;
    }    
}

/**
 * 退款查询接口
 */
class WxRefundQuery extends Wxpay_client{    
    function __construct() {
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/pay/refundquery";
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;      
    }    
    /**
     * 生成接口参数xml
     */
    function createXml(){       
        try{
            if($this->parameters["out_refund_no"] == null &&
                $this->parameters["out_trade_no"] == null &&
                $this->parameters["transaction_id"] == null &&
                $this->parameters["refund_id "] == null){
                throw new Exception("退款查询接口中，out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个！"."<br>");
            }
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (Exception $e){
            die($e->getMessage());
        }
    }

    /**
     *  作用：获取结果，使用证书通信
     */
    function getResult(){       
        $this->postXmlSSL();
        $this->result = $this->xmlToArray($this->response);
        return $this->result;
    }

}

/**
 * 对账单接口
 */
class WxDownloadBill extends Wxpay_client{
    function __construct(){
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/pay/downloadbill";
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;      
    }
    /**
     * 生成接口参数xml
     */
    function createXml(){       
        try{
            if($this->parameters["bill_date"] == null ){
                throw new Exception("对账单接口中，缺少必填参数bill_date！"."<br>");
            }
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (Exception $e){
            die($e->getMessage());
        }
    }    
    /**
     *  作用：获取结果，默认不使用证书
     */
    function getResult(){       
        $this->postXml();
        $this->result = $this->xmlToArray($this->result_xml);
        return $this->result;
    }
}

/**
 * 短链接转换接口
 */
class WxShortUrl extends Wxpay_client{
    function __construct(){
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/tools/shorturl";
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;      
    }
    /**
     * 生成接口参数xml
     */
    function createXml(){       
        try{
            if($this->parameters["long_url"] == null ){
                throw new Exception("短链接转换接口中，缺少必填参数long_url！"."<br>");
            }
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (Exception $e){
            die($e->getMessage());
        }
    }
    
    /**
     * 获取prepay_id
     */
    function getShortUrl(){
        $this->getResult();
        $short_url = $this->result["short_url"];
        return $short_url;
    }
    
}

/**
 * 响应型接口基类
 */
class Wxpay_server extends WxPayBase{
    public $data;//接收到的数据，类型为关联数组
    var $returnParameters;//返回参数，类型为关联数组
    
    /**
     * 将微信的请求xml转换成关联数组，以方便数据处理
     */
    function saveData($xml){
        $this->data = $this->xmlToArray($xml);
    }
    
    function checkSign(){
        $tmpData = $this->data;
        unset($tmpData['sign']);
        $sign = $this->getSign($tmpData);//本地签名
        if ($this->data['sign'] == $sign) {
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * 获取微信的请求数据
     */
    function getData(){       
        return $this->data;
    }
    
    /**
     * 设置返回微信的xml数据
     */
    function setReturnParameter($parameter, $parameterValue){
        $this->returnParameters[$this->trimString($parameter)] = $this->trimString($parameterValue);
    }
    
    /**
     * 生成接口参数xml
     */
    function createXml(){
        return $this->arrayToXml($this->returnParameters);
    }
    
    /**
     * 将xml数据返回微信
     */
    function returnXml(){
        $returnXml = $this->createXml();
        return $returnXml;
    }
}


/**
 * 通用通知接口
 */
class WxNotify extends Wxpay_server{
}

/**
 * 请求商家获取商品信息接口
 */
class WxNativeCall extends Wxpay_server{
    /**
     * 生成接口参数xml
     */
    function createXml(){
        if($this->returnParameters["return_code"] == "SUCCESS"){
            $this->returnParameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->returnParameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $this->returnParameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->returnParameters["sign"] = $this->getSign($this->returnParameters);//签名
        }
        return $this->arrayToXml($this->returnParameters);
    }
    
    /**
     * 获取product_id
     */
    function getProductId(){
        $product_id = $this->data["product_id"];
        return $product_id;
    }    
}

/**
 * 静态链接二维码
 */
class WxNativeLink  extends WxPayBase{
    var $parameters;//静态链接参数
    var $url;//静态链接

    function __construct(){
    }
    
    /**
     * 设置参数
     */
    function setParameter($parameter, $parameterValue){
        $this->parameters[$this->trimString($parameter)] = $this->trimString($parameterValue);
    }
    
    /**
     * 生成Native支付链接二维码
     */
    function createLink(){
        try{       
            if($this->parameters["product_id"] == null){
                throw new Exception("缺少Native支付二维码链接必填参数product_id！"."<br>");
            }           
            $this->parameters["appid"] = WxPayConf::$APPID;//公众账号ID
            $this->parameters["mch_id"] = WxPayConf::$MCHID;//商户号
            $time_stamp = time();
            $this->parameters["time_stamp"] = "$time_stamp";//时间戳
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名          
            $bizString = $this->formatBizQueryParaMap($this->parameters, false);
            $this->url = "weixin://wxpay/bizpayurl?".$bizString;
        }catch (Exception $e){
            die($e->getMessage());
        }
    }
    
    /**
     * 返回链接
     */
    function getUrl(){       
        $this->createLink();
        return $this->url;
    }
}

/**
* JSAPI支付——H5网页端调起支付接口
*/
class WxJsApi extends WxPayBase{
    var $code;//code码，用以获取openid
    var $openid;//用户的openid
    var $parameters;//jsapi参数，格式为json
    var $prepay_id;//使用统一支付接口得到的预支付id
    var $curl_timeout;//curl超时时间

    function __construct(){
        //设置curl超时时间
        $this->curl_timeout = WxPayConf::$CURL_TIMEOUT;
    }
    
    /**
     *  作用：生成可以获得code的url
     */
    function createOauthUrlForCode($redirectUrl){
        $urlObj["appid"] = WxPayConf::$APPID;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->formatBizQueryParaMap($urlObj, false);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     *  作用：生成可以获得openid的url
     */
    function createOauthUrlForOpenid(){
        $urlObj["appid"] = WxPayConf::$APPID;
        $urlObj["secret"] = WxPayConf::$APPSECRET;
        $urlObj["code"] = $this->code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->formatBizQueryParaMap($urlObj, false);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }
    
    
    /**
     *  作用：通过curl向微信提交code，以获取openid
     */
    function getOpenid(){
        $url = $this->createOauthUrlForOpenid();
        //初始化curl
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOP_TIMEOUT, $this->curl_timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //运行curl，结果以jason形式返回
        $res = curl_exec($ch);
        curl_close($ch);
        //取出openid
        $data = json_decode($res,true);
        $this->openid = $data['openid'];
        return $this->openid;
    }

    /**
     *  作用：设置prepay_id
     */
    function setPrepayId($prepayId){
        $this->prepay_id = $prepayId;
    }

    /**
     *  作用：设置code
     */
    function setCode($code_){
        $this->code = $code_;
    }

    /**
     *  作用：设置jsapi的参数
     */
    public function getParameters(){
        $jsApiObj["appId"] = WxPayConf::$APPID;
        $timeStamp = time();
        $jsApiObj["timeStamp"] = "$timeStamp";
        $jsApiObj["nonceStr"] = $this->createNoncestr();
        $jsApiObj["package"] = "prepay_id=$this->prepay_id";
        $jsApiObj["signType"] = "MD5";
        $jsApiObj["paySign"] = $this->getSign($jsApiObj);
        $this->parameters = json_encode($jsApiObj);
        
        return $this->parameters;
    }
}
/*
* 微信接口
*/
/*********************************************************************************/
class pay_weixin extends paymentPlugin{

	//支付插件名称
    var $name = '微信支付';//
    //支付插件logo
    var $logo = 'weixin';
    //版本号
    var $version = 20130902;
    //支付插件字符集
    var $charset = 'utf-8';
	//支付提交的地址
    var $submitUrl = '/payplus/pay_wxnative';
    //支付提交按钮的图片
    var $submitButton = '';
    //支付插件所支持的货币单位
    var $supportCurrency = array("CNY"=>"01");
    //支付支持的地区
    var $supportArea =  array("AREA_CNY");
    //支付插件排序
    var $orderby = 3;
	//支付html头部的字符集
    var $head_charset='utf-8';
    //支付插件ID
    var $payment_id = 0;
    /**
    * @brief 初始化支付宝类
    */
    function __construct(){
    	//初始化父类
        parent::__construct();
        //获取IP地址
        $regIp=isset($_SERVER['SERVER_ADDR'])?$_SERVER['SERVER_ADDR']:$_SERVER['HTTP_HOST'];
        //设置支付宝详细信息
        $this->intro='';

        if($this->payment_id == 0){
            $name = str_replace("pay_", "", __CLASS__);
            $this->payment_id = M('payment as a,@pay_plugin as b')->get_one('b.file_path = \''.$name.'\' and a.plugin_id = b.id',"a.id");
        }
        //如果未找到payment_id 退出中断
        if(empty($this->payment_id)){
           // die("no find pay method!");
        }

        //更新微信相关配置
        if(empty(WxPayConf::$MCHID)){
            WxPayConf::$MCHID = $this->get_conf($this->payment_id,"MCHID");
            WxPayConf::$APPID = $this->get_conf($this->payment_id,"APPID");
            WxPayConf::$KEY = $this->get_conf($this->payment_id,"PrivateKey");
            WxPayConf::$APPSECRET = $this->get_conf($this->payment_id,"APPSECRET");
            WxPayConf::$SSL_PATH = $this->get_conf($this->payment_id,"SSL_PATH");
        }
    }
    //微信支付CALL
    function native_call(){
        //使用native通知接口
        $nativeCall = new WxNativeCall();
        //接收微信请求
        $xml = isset($GLOBALS['HTTP_RAW_POST_DATA'])?$GLOBALS['HTTP_RAW_POST_DATA']:"";
        if($xml){
            $nativeCall->saveData($xml);
            if($nativeCall->checkSign() == FALSE){
                $nativeCall->setReturnParameter("return_code","FAIL");//返回状态码
                $nativeCall->setReturnParameter("return_msg","签名失败");//返回信息
            }else{
                //提取product_id
                $product_id = $nativeCall->getProductId();        
                //根据不同的$product_id设定对应的下单参数，此处只举例一种
                if($product_id){
                    $recharge_row = M("recharge")->get_row("recharge_no = '{$product_id}'");
                }
                if(isset($recharge_row['recharge_no']) && $recharge_row['status'] == 0){
                    //使用统一支付接口
                    $unifiedOrder = new WxUnifiedOrder();  
                    $body = ($recharge_row['order_no'] && $recharge_row['inpay'] == 1)?"八戒DNS支付购买订单-{$recharge_row['order_no']}":"在线充值";
                    $unifiedOrder->setParameter("body",$body);//商品描述
                    //自定义订单号，此处仅作举例
                    $unifiedOrder->setParameter("out_trade_no","{$recharge_row['recharge_no']}");//商户订单号             $unifiedOrder->setParameter("product_id","$product_id");//商品ID
                    $unifiedOrder->setParameter("total_fee",intval($recharge_row['amount']*100));//总金额
                    $unifiedOrder->setParameter("notify_url",$this->server_callback_url);//通知地址 
                    $unifiedOrder->setParameter("trade_type","NATIVE");//交易类型
                    $unifiedOrder->setParameter("product_id","$product_id");//用户标识
                    //非必填参数，商户可根据实际情况选填
                    //$unifiedOrder->setParameter("sub_mch_id","XXXX");//子商户号  
                    //$unifiedOrder->setParameter("device_info","XXXX");//设备号 
                    //$unifiedOrder->setParameter("attach","XXXX");//附加数据 
                    //$unifiedOrder->setParameter("time_start","XXXX");//交易起始时间
                    //$unifiedOrder->setParameter("time_expire","XXXX");//交易结束时间 
                    //$unifiedOrder->setParameter("goods_tag","XXXX");//商品标记 
                    //$unifiedOrder->setParameter("openid","XXXX");//用户标识
                        
                    //获取prepay_id
                    $prepay_id = $unifiedOrder->getPrepayId();
                    //设置返回码
                    //设置必填参数
                    //appid已填,商户无需重复填写
                    //mch_id已填,商户无需重复填写
                    //noncestr已填,商户无需重复填写
                    //sign已填,商户无需重复填写
                    $nativeCall->setReturnParameter("return_code","SUCCESS");//返回状态码
                    $nativeCall->setReturnParameter("result_code","SUCCESS");//业务结果
                    $nativeCall->setReturnParameter("prepay_id","$prepay_id");//预支付ID
                }else{
                    $nativeCall->setReturnParameter("return_code","SUCCESS");//返回状态码
                    $nativeCall->setReturnParameter("result_code","FAIL");//业务结果
                    $nativeCall->setReturnParameter("err_code_des","此商品无效");//业务结果
                }
            }
        }else{
            $nativeCall->setReturnParameter("return_code","FAIL");//返回状态码
            $nativeCall->setReturnParameter("return_msg","不支持GET方法");//返回信息
        }       
        //将结果返回微信
        $returnXml = $nativeCall->returnXml();
        return $returnXml;
    }
    //获取支付二维码连接
    function get_url($recharge_no=""){
        $nativeLink = new WxNativeLink();    
        //设置静态链接参数
        //设置必填参数
        //appid已填,商户无需重复填写
        //mch_id已填,商户无需重复填写
        //noncestr已填,商户无需重复填写
        //time_stamp已填,商户无需重复填写
        //sign已填,商户无需重复填写
        $product_id = $recharge_no;//自定义商品id
        $nativeLink->setParameter("product_id","$product_id");//商品id
        //获取链接
        $product_url = $nativeLink->getUrl();

        //使用短链接转换接口
        $shortUrl = new WxShortUrl();
        //设置必填参数
        //appid已填,商户无需重复填写
        //mch_id已填,商户无需重复填写
        //noncestr已填,商户无需重复填写
        //sign已填,商户无需重复填写
        $shortUrl->setParameter("long_url","{$product_url}");//URL链接
        //获取短链接
        return $shortUrl->getShortUrl();
    }
    /**
    * @brief form提交事件
	* @param array 订单的详细信息
	× @return array 返回支付需提交的详细信息
    */
    function toSubmit($payment){
		//订单总价
        $amount = number_format($payment['M_Amount'],2,".","");
		//初始化返回值
        $return = array();
        //交易接口名称
        $return['Payid'] = $payment['M_Paymentid'];
        $return['Ordid'] = $payment['M_OrderId'];
        $return['Ordno'] = $payment['M_OrderNO'];
        $return['Ordamt']  = $amount*100;
        $return['Orddate']  = tTime::get_datetime("Ymd",$payment ['M_Time']);
		//付完款后跳转的页面 要用 http://格式的完整路径，不允许加?id=123这类自定义参数
        $return['Return_url'] = $this->callback_url;
		//交易过程中服务器通知的页面 要用 http://格式的完整路径，不允许加?id=123这类自定义参数
        $return['Notify_url'] = $this->server_callback_url;
        return $return;
    }

    function callback($in,&$paymentId,&$money,&$message,&$tradeno){
        //使用通用通知接口
        $notify = new WxNotify();

        //存储微信的回调
        $xml = isset($GLOBALS['HTTP_RAW_POST_DATA'])?$GLOBALS['HTTP_RAW_POST_DATA']:"";
        if(empty($xml)){
            return PAY_FAILED;
        }
        $notify->saveData($xml);
        if($notify->checkSign() == FALSE){
            $notify->setReturnParameter("return_code","FAIL");//返回状态码
            $notify->setReturnParameter("return_msg","签名失败");//返回信息

            $message = $notify->returnXml();
            return PAY_FAILED;
        }else{
            $notify->setReturnParameter("return_code","SUCCESS");//设置返回码
            $message = $notify->returnXml();
            if ($notify->data["return_code"] == "FAIL") {
                return PAY_INVALID;
            }elseif($notify->data["result_code"] == "FAIL"){
                return PAY_INVALID;
            }else{
                $tradeno = $notify->data['out_trade_no'];
                $money   = floatval($notify->data['total_fee']/100);
                return PAY_SUCCESS;
            }           
        }
    }

    function getfields(){
        return array(
                'MCHID'=>array(
                        'label'=>'商户ID(MCHID)',
                        'type'=>'string'
                    ),
                'PrivateKey'=>array(
                        'label'=>'交易安全校验码(MD5key)',
                        'type'=>'string'
                ),
                'APPID'=>array(
                        'label'=>'公众号ID(APPID)',
                        'type'=>'string'
                ),
                'APPSECRET'=>array(
                        'label'=>'公众号密钥(APPSECRET)',
                        'type'=>'string'
                ),
                'SSL_PATH'=>array(
                        'label'=>'证书路径',
                        'type'=>'string',
                ),
            );
    }
}
?>