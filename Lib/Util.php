<?php
namespace Lib;
/**
 * 工具函数类.
 */



use Response;
use Moblib\Iplib as MIPLib;

/**
 * 工具函数类.
 * @package Lib
 */
class Util
{
    // XSS攻击 JS绑定on事件<xxx onload = 'foo()'>
    const XSS_JS = "/(<(\\/?)(script|i?frame|style|body|title|link|meta|object|\\?|\\%)([^>]*?))|(<[^>]*)on[a-zA-Z]+\s*=([^>]*>)>/isU";

    // XSS攻击 SQL注入
    const XSS_SQL = "/select\b|insert\b|update\b|delete\b|drop\b|;|\"|\'|\/\*|\*|\.\.\/|\.\/|union|into|load_file|outfile|dump/is";


    /**
     * 设置COOKIE.
     *
     * @param string $key   键.
     * @param string $value 值.
     * @param string $ttl   有效期.
     *
     * @return void
     */
    public static function setCookie($key, $value, $ttl)
    {
        $domain = self::getConfig('app','top_domain');
        setcookie($key, $value, $ttl,'/', $domain);
    }

    /**
     * 获取COOKIE.
     *
     * @param string $key 键.
     *
     * @return string
     */
    public static function getCookie($key)
    {
        $val = isset($_COOKIE[$key]) ? $_COOKIE[$key] : '';
        return $val;
    }

    /**
     * 获取配置.
     *
     * @param string $conf 配置.
     * @param string $key  键.
     *
     * @return array|mixed
     */
    public static function getConfig($conf, $key = '')
    {
        $class = '\\Config\\' . ucfirst($conf);

        $conf = (array) new $class;

        if (!empty($key)) {
            $conf = $conf[$key];
        }

        return $conf;
    }

    /**
     * 检查数组元素是否存在的函数.
     *
     * @param array  $array   源.
     * @param string $key     键。
     * @param string $type    类型.
     * @param string $default 默认值.
     *
     * @return array|int|mixed|string
     */
    public static function checkIsset(array $array, $key, $type = 'string', $default = '')
    {
        $tmp = array_key_exists($key, $array) ? $array[$key] : $default;

        switch ($type) {
            case 'json':
                $ret = empty($tmp) ? array() : json_decode($tmp);
                break;
            case 'int':
                $ret = empty($tmp) ? 0 : intval($tmp);
                break;
            case 'array':
                $ret = empty($tmp) ? array() : (array)$tmp;
                break;
            default:
                $ret = (string)$tmp;
        }

        return $ret;
    }

    /**
     * 获取用户IP.
     *
     * @return string
     */
    public static function getUserIp()
    {
        $x_real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? '' : $_SERVER['HTTP_X_REAL_IP'];

        if (!empty($x_real_ip)) {
            $ips = explode(',',$x_real_ip);
            $client_ip = trim($ips[0]);
        } else {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        }

        return $client_ip;
    }

    /**
     * 内部IP.
     *
     * @param string $ip IP地址.
     *
     * @return boolean
     */
    public static function isStaffIp($ip = '')
    {
        $flag = false;

        $ip = empty($ip) ? self::getUserIp() : $ip;

        if ($ip == '127.0.0.1' ||
            substr($ip, 0, 3) == '10.' ||
            substr($ip, 0, 7) == '172.19.' ||
            substr($ip, 0, 7) == '172.20.' ||
            substr($ip, 0, 7) == '182.138.' ||
            substr($ip, 0, 8) == '192.168.' ||
            substr($ip, 0, 10) == '106.38.50.' ||
            substr($ip, 0, 10) == '1.202.151.' ||
            in_array($ip, array('211.150.79.6', '182.138.102.82')) ||
            ('117.79.131.' < $ip && $ip < '117.79.132.')
        ) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 用户名长度(utf-8编码下字符串长度.中文).
     *
     * @param string $str 字符串.
     *
     * @return int
     */
    public static function utf8StrLen($str = null)
    {
        $count = 0;
        for ($i = 0; $i < strlen($str);$i++) {
            $value = ord($str[$i]);
            if ($value > 127) {
                $count++;
                if ($value >= 192 && $value <= 223) {
                    $i++;
                } elseif ($value >= 224 && $value <= 239) {
                    $i = $i + 2;
                } elseif ($value >= 240 && $value <= 247) {
                    $i = $i + 3;
                } else {
                    return 0;
                }
            }
            $count++;
        }
        return $count;
    }

    /**
     * 验证是否含有非法字符.
     *
     * @param string $param 参数.
     *
     * @return boolean
     */
    public static function validateXSSParams($param)
    {
        $flag = false;

        if (preg_match(self::XSS_SQL,$param) || preg_match(self::XSS_JS,$param)) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 过滤非法字符.
     *
     * @param string $param 参数.
     *
     * @return null|string|string[]
     */
    public static function filterXSSParams($param)
    {
        // XSS 攻击 SQL/JS
        $preg = array(self::XSS_SQL,self::XSS_JS);

        // 过滤规则
        $str = preg_replace($preg,'', $param);

        // 剔除标签
        $str = strip_tags($str);

        return $str;
    }

    /**
     * 获取环境变量.
     *
     * @return array|string
     */
    public static function getPhase()
    {
        $phase = self::getConfig('common', 'phase');
        if (!in_array($phase, array('rd', 'sit', 'staging', 'production'))) {
            $phase = 'staging';
        }
        return $phase;
    }

    /**
     * 数据标准化处理(空数组转对象,整型浮点型转字符串).
     *
     * @param array $param 参数.
     *
     * @return array|mixed
     */
    public static function dataStdProcessing($param = array())
    {
        if (is_array($param)) {
            // 数组处理
            if (empty($param)) {
                return (object)$param;
            }
            foreach ($param as $k => $v) {
                if (is_array($v) && empty($v)) {
                    $param[$k] = (object)$v;
                } elseif (is_int($v) || is_float($v)) {
                    $param[$k] = (string)$v;
                } elseif ((is_array($v) || is_object($v)) && !empty($v)) {
                    $param[$k] = self::dataStdProcessing($v);
                }
            }
        } elseif (is_object($param)) {
            // 对象处理
            if (empty($param)) {
                return $param;
            }
            foreach ($param as $k => $v) {
                if (is_array($v) && empty($v)) {
                    $param->$k = (object)$v;
                } elseif (is_int($v) || is_float($v)) {
                    $param->$k = (string)$v;
                } elseif ((is_array($v) || is_object($v)) && !empty($v)) {
                    $param->$k = self::dataStdProcessing($v);
                }
            }
        }
        return $param;
    }

    /**
     * 获取API内配置数据.
     *
     * @param string $conf   类.
     * @param string $method 方法.
     *
     * @return mixed
     */
    public static function getApiConfig($conf, $method = 'getList')
    {
        $file = "\\Applications\\Api\\Config\\$conf";

        return $file::$method();
    }

    /**
     * 无缓存获取数据方式.
     *
     * @param string $method     服务方法.
     * @param array  $parameters 服务参数.
     *
     * @return mixed
     */
    public static function call($method, $parameters = array())
    {
        $service = new Service();

        return $service->call($method, $parameters);
    }

    /**
     * 缓存获取数据方式.
     *
     * @param string $method     服务方法.
     * @param array  $parameters 服务参数.
     *
     * @return mixed
     */
    public static function smart($method, $parameters = array())
    {
        $service = new Service();

        return $service->smart($method, $parameters);
    }

    /**
     * 比例计算.
     *
     * @param array $data 数据.
     *
     * @return string
     */
    public static function randomBaseTimestamp($data = array())
    {
        uasort(
            $data,
            function ($a, $b) {
                return rand(-1, 1);
            }
        );

        $time   = rand(1, 1000);
        $total  = array_sum($data);
        $mod    = $time % $total;

        $result = '';

        foreach ($data as $k => $v) {
            $result = $k;
            $mod -= $v;
            if ($mod < 0) {
                break;
            }
        }

        return $result;
    }

    /**
     * 账户登陆解密.
     *
     * @param string $cipherText 密文.
     * @param string $key        加解密KEY.
     *
     * @return string
     */
    public static function decryptAccount($cipherText, $key = '')
    {
        $key = empty($key) ? self::getConfig('app', 'accountEncKey') : $key;

        $iv_size = mcrypt_get_iv_size(MCRYPT_3DES, MCRYPT_MODE_CBC);

        $tmp = base64_decode($cipherText);

        $iv = substr($tmp, 0, $iv_size);

        $cipher = substr($tmp, $iv_size);

        return rtrim(mcrypt_decrypt(MCRYPT_3DES, $key, $cipher, MCRYPT_MODE_CBC, $iv));
    }

    /**
     * 账户登陆加密.
     *
     * @param string $plainText 明文.
     * @param string $key       加解密KEY.
     *
     * @return string
     */
    public static function encryptAccount($plainText, $key = '')
    {
        $key = empty($key) ? self::getConfig('app', 'accountEncKey') : $key;

        $iv_size = mcrypt_get_iv_size(MCRYPT_3DES, MCRYPT_MODE_CBC);

        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

        $cipher = mcrypt_encrypt(MCRYPT_3DES, $key, $plainText, MCRYPT_MODE_CBC, $iv);

        return base64_encode($iv.$cipher);
    }

    /**
     * 返回数据结构.
     *
     * @param array   $data    结果.
     * @param string  $message 提示文案.
     * @param string  $action  提示方式.
     * @param integer $code    提示编码.
     *
     * @return void
     */
    public static function response($data = array(), $message = '', $action = '', $code = 0)
    {
        $data = (is_array($data) && empty($data)) ? (object)$data : $data;

        $res = array(
            'code'      => (string)$code,
            'action'    => $action,
            'message'   => $message,
            'data'      => $data,
            'extra'     => array('time' => date('Y-m-d H:i:s'))
        );

        // 内网返回各接口返回数据.
        if (self::isStaffIp()) {
            global $_EXTRA_DEBUG;
            $res['extra']['msg'] = '没有打印rpc可能原因：1.没有rpc调用 2.命中缓存(内网使用参数 _r=1 再试^^)';
            $res['extra']['rpc'] = empty($_EXTRA_DEBUG) ? array() : $_EXTRA_DEBUG;
        }

        // 转JSON数据(callback h5-jsonP使用)
        Response::json($res, \Registry::get('callback'));
    }

    /**
     * 错误.
     *
     * @param string  $code       错误码.
     * @param string  $action     动作('', alert, toast).
     * @param string  $msg        文案.
     * @param array   $data       返回数据.
     * @param integer $httpStatus HTTP状态码.
     *
     * @return void
     */
    public static function exceptionResponse($code = '40001', $action = '', $msg = '', $data = array(), $httpStatus = 200)
    {
        $tMsg = '';

        $conf = self::getApiConfig('ErrorCode');
        if (isset($conf[$code])) {
            if (is_array($conf[$code])) {
                // 数组
                $tMsg = !empty($conf[$code]['msg']) ? $conf[$code]['msg'] : '';
                $code = !empty($conf[$code]['code']) ? $conf[$code]['code'] : $msg;
                $action = !empty($conf[$code]['action']) ? $conf[$code]['action'] : $action;
            } else {
                // 字符串
                $tMsg = $conf[$code];
            }
        }

        // 指定文案覆盖配置文案
        if (!empty($msg)) {
            $tMsg = $msg;
        }

        self::response($data, $tMsg, $action, $code);
    }

    /**
     * 服务返回数据.
     *
     * @param array $val 服务返回数据.
     *
     * @return void
     */
    public static function extraDebug($val)
    {
        $key = strval(microtime(true));
        global $_EXTRA_DEBUG;
        $_EXTRA_DEBUG[$key] = $val;
    }

    /**
     * 根据用户ip判断用户是否受限.
     *
     * @param string $ip   IP地址.
     * @param array  $area 省市.
     *
     * @return boolean
     */
    public static function checkUserLimitedByIpAndArea($ip, $area)
    {
        $limited = false;
        if (!empty($area)) {
            $data = MIPLib::Instance($ip);
            $location = (!empty($data)) ? implode('', $data) : '';
            foreach ($area as $v) {
                if (preg_match("/{$v}/", $location)) {
                    $limited = true;
                    break;
                }
            }
        }
        return $limited;
    }
}