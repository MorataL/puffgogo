<?php
/**
 * Created by PhpStorm.
 * User: Peak
 * Date: 2018/7/19
 * Time: 11:06
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Lib\Common;
use App\Lib\Validate;
use Symfony\Component\HttpFoundation\Session\Session;

use App\Models\User\UserLogin;
class ApiController extends BaseController
{


    protected $_parameter;
    protected $_headerParams;
    protected $need_auth_action = array(); //需要验证授权的方法名
    public    $_userId=0;
    public    $_userInfo=[];
    protected $_aesKey='1AF1A61C8AABAD1E';
    protected $_aesIv='wM#*2@4S4bG05o6l';
    public $_token = '';
    protected $_session = '';
    public  $_source = '';
    /**
     * 初始化
     * code  0是失败 1是获取数据成功
     */
    public function initialize(string $controller,string $action='index') {

        parent::initialize($controller,$action);
        $this->_aesIv = config('api.aes_iv')?:$this->_aesIv;
        //获取hedear头参数,并完成验证
        $params = $this->getHeaderData();

        if(is_string($params)) return $this->getResult($params,[],0,0);

        $this->_headerParams = $params;

        $this->_userId = $this->session('user_id');

        //加载密钥
        if(!empty($this->body['_debug']) && $this->body['_debug']==config('api.debug_key')) {
            $this->_parameter = $this->body;
        }else{
            //自动解密传来的参数
            $this->_parameter = empty($this->body['parameter']) ? array(): json_decode($this->aesDecrypt($this->body['parameter']),true);
        }

        $this->_token = empty($this->_parameter['Sign-Sn']) ? '' : $this->_parameter['Sign-Sn'];

        $this->_userId = $this->getLoginedMemberId();

        if(!empty($this->_token)){ //token登录
            if(empty($this->_userId)){
                $last_login_ip = Common::getClientIp();//登录ip
                //查询用户数据
                $r = UserLogin::model()->dologin(array('token'=>$this->_token,'last_login_ip'=>$last_login_ip));
                if(!empty($r['data'])){
                    $uInfo = $r['data'];
                    $this->_userId = $uInfo['id'];
                    //redis保存用户数据
                    $this->session(['user_id'=>$uInfo['id'], 'userInfo'=>$uInfo]);
                }else{
                    return $this->getResult($r['msg'],[],0,$r['code']);
                }
            }else{
                $this->_userInfo = $this->session('userInfo');
                $uInfo = $this->_userInfo;
            }
        }

       // if(!empty($uInfo) && empty($uInfo['user_mobile'])) return $this->getResult('手机号不能为空',[],0,'1003');

//        if(empty($this->_token) && $this->_headerParams['Source'] === 'wx'){
//                return $this->getResult("需要授权",[],0,32554);
//        }
        //登录验证
        $need_auth_actions = array_change_key_case(array_flip($this->need_auth_action),CASE_LOWER); //处理需要验证登录方法
        if (isset($need_auth_actions[strtolower($this->_actionName)])){ //判断方法是否需要登录
            //判断是否登录成功
            if(empty($this->_userId)) return $this->getResult('您尚未登录',[],0,0);
        }
        return $this->getResult();
    }
    /**
     * @param array|string|null $key
     * @return string|array|boolean
     */
    public function session($key=null){
        if(!$this->_token) return '';
        if(is_array($key) && !empty($key)) {
            foreach ($key as $k=>$v) {
                 Common::redis('session')->hSet($this->_token,$k, is_array($v)? json_encode($v, JSON_UNESCAPED_UNICODE):$v );
            }
            $ret = true;
        }else if(is_string($key)) {
            $r = Common::redis('session')->hGet($this->_token,$key);
            $ret =  Validate::isJson($r)? json_decode($r, true):$r;
            } else $ret =  Common::redis('session')->hGetAll($this->_token);
     //   Common::redis('session')->expire($this->_token, config('session.lifetime'));

        return $ret;
    }

    /**
     * 订单数据
     */
    public function order($key=null) {
        if(is_array($key) && !empty($key)) {
            foreach ($key as $k=>$v) {
                Common::redis('order')->hSet('order',$k, is_array($v)? json_encode($v, JSON_UNESCAPED_UNICODE):$v);
            }
            $ret = true;
        }else if(is_string($key)) {
            $r = Common::redis('order')->hGet('order',$key);
            $ret =  Validate::isJson($r)? json_decode($r, true):$r;
        }else{
            $ret =  Common::redis('order')->hGetAll('order');
        };
        return $ret;
    }

    /**
     * 获取已登UID
     */
    public function getLoginedMemberId() :int {
        return empty($this->session('user_id')) ? 0 : intval($this->session('user_id'));
    }

    /*
    * 获取hedear头参数,并完成验证
    */
    public function getHeaderData(){
       //验证规则
        $field_header = array(
          //  'Language'   =>array('name'=>'语言种类',         '_validate'=>'default:zh|maxLength:4'),
          //  'Source'     =>array('name'=>'来源版本',         '_validate'=>'require|maxLength:40'),
            //'Time'       =>array('name'=>'请求时间',         '_validate'=>'require|maxLength:64'),
           // 'Sign-Sn'    =>array('name'=>'token参数',        '_validate'=>'maxLength:255'),
        //    'Udid'       =>array('name'=>'设备唯一ID',       '_validate'=>'require|maxLength:64'),
         //   'Idfa'       =>array('name'=>'广告标示符',       '_validate'=>'require|maxLength:64'),
          //  'Platform'   =>array('name'=>'设备种类',         '_validate'=>'default:app|maxLength:25'),
            //'Aes-Key'       =>array('name'=>'AES动态Key',    '_validate'=>'require'),
        );
        $params = Common::getallheaders(); //获取header参数
        //验证header头参数
        //$r = \App\Lib\Validate::validParams($params,$field_header);
        //if($r!==true) return $r;
        //$this->_aesKey = $this->rsaPriDecrypt($params['Aes-Key']); //动态获取AesKey
//        if(empty($this->body['_debug']) || $this->body['_debug']!=config('api.debug_key')){
//            //解密token参数
//            $params['Sign-Sn'] = empty($params['Sign-Sn'])? '': $params['Sign-Sn'];
//        }

        return $params;
    }


    /*
    * 输出错误
    * msg 错误提示
    * status 错误状态
    * msgKey 是否转换英文参数异常
    */
    public function appError($msg,$status=0,$data=''){
        return $this->appDisplay($data,$status, $msg);
    }

    /*
    * 结果输出
    * msg 错误提示
    * status 错误状态
    * data 输出数据
    */
    public function appDisplay($data='', $status=1, $msg='success'){

      //  $version = $this->_headerParams['Platform'] === 'ios' ? Common::mbConfig('app_ios_version') : Common::mbConfig('app_android_version');
        $ret = array('code'=>$status,'msg'=>$msg,'data'=>\App\Lib\Util\Arrays::setDataValStr($data));

//        $ret =['ida'=>'1','id'=>'2'];
        //更新下载地址
//        if (substr($this->_headerParams['Source'],-5) < '7.0.0'){
//            $app_url = $this->_headerParams['Platform'] === 'ios' ? 'https://itunes.apple.com/cn/app/id444433493' : 'https://img.meicicdn.com/mc/bargains/20160714/app-release_620_UMENG_CHANNEL_美西主站_meici_sign.apk';
//            $ret = array('code'=>100,'msg'=>'','data'=>'','version'=>$version,'is_update'=>1,'app_url'=>$app_url,'update_msg'=>'尊敬的美西会员！因系统全新升级，旧版已停用，请更新至8.0版','update_button_msg'=>'进入市场');
//        }
        //输出参数
      $echo = json_encode($ret);

       return (!empty($this->body['_debug']) && $this->body['_debug']==config('api.debug_key')) ? $echo : $this->aesEncrypt($echo);
    //    return $echo;
    }

    //AES 加密
    public function aesEncrypt($data) {
//        print_r();
//        exit;
//        print_r($this->_aesIv);
        //$key  = openssl_random_pseudo_bytes(32);
       // $iv = config('appapi.aes_iv');//openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, config('api.aes_cipher'), $this->_aesKey, OPENSSL_RAW_DATA, $this->_aesIv);
//        $keyByRsa = $this->rsaPriEncrypt($key);
       // return $keyByRsa.'|||'.base64_encode($encrypted);
        return base64_encode($encrypted);
    }



    //AES 解密
    public function aesDecrypt($encrypted,$key='') {
        $this->_aesKey = $key ? : $this->_aesKey;
        $encrypted = base64_decode($encrypted);
        $decrypted = openssl_decrypt($encrypted, config('api.aes_cipher'),$this->_aesKey, OPENSSL_RAW_DATA, $this->_aesIv);
        return $decrypted;
    }



    //RSA 私钥加密
    public function rsaPriEncrypt($data) {
        $pri_key = openssl_pkey_get_private(file_get_contents(config('api.rsa_private_key')));
        openssl_private_encrypt($data, $encrypted, $pri_key);
        return base64_encode($encrypted);
    }

    //RSA 私钥解密
    public function rsaPriDecrypt($encrypted) {
        $encrypted = base64_decode($encrypted);
        $pri_key = openssl_pkey_get_private(file_get_contents(config('api.rsa_private_key')));
        openssl_private_decrypt($encrypted, $decrypted, $pri_key);
        return $decrypted;
    }

    //RSA 公钥加密
    public function rsaPubEncrypt($data) {
        $pri_key = openssl_pkey_get_public(file_get_contents(config('api.rsa_public_key')));
        openssl_public_encrypt($data, $encrypted, $pri_key);
        return base64_encode($encrypted);
    }

    //RSA 公钥解密
    public function rsaPubDecrypt($encrypted) {
        $encrypted = base64_decode($encrypted);
        $pri_key = openssl_pkey_get_public(file_get_contents(config('api.rsa_public_key')));
        openssl_public_decrypt($encrypted, $decrypted, $pri_key);
        return $decrypted;
    }


    //生成订单
    public function order_id($date){
        $random=rand(1, 3);
        $orderRand = $this->str_rand(4);
        $order_id = 'E'.$date.$orderRand.'000'.$random;
        $data = $this->session($order_id);
        if(!empty($data)){
            $this->order_id(date('YmdHis'));
        }
        return $order_id;
    }


    /*
    3  * 生成随机字符串
    4  * @param int $length 生成随机字符串的长度
    5  * @param string $char 组成随机字符串的字符串
    6  * @return string $string 生成的随机字符串
    7  */
    function str_rand($length = 32, $char = '0123456789') {
        if(!is_int($length) || $length < 0) {
            return false;
        }
        $string = '';
        for($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }
        return $string;
    }


    /**
     * 判断传入的参数是否为数组 并且是否为空
     *
     *                                  错误码: 80000 - 数组有空值
     *                                  错误吗: 90000 - 传入非数组
     * @param $array
     * @return array
     */
    public function arrayIsEmpty($array,$column = []){

        $code = $msg = '';

        if(is_array($array)){

            foreach($array as $key => $val){

                if(in_array($key,$column)){ continue;}

                if(empty($val)){
                    $code[] = 80000;
                    $msg[] = $key.'为空!';
                }
            }

            if((!is_array($code))){
                $code = 200;
                $msg = '操作成功';
            }
        }else{
            $code = 90000;
            $msg = '传入非数组!';
        }

        return ['code'=>$code,
                'message'=>$msg,
                'data'=>$array];
    }
}