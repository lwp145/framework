<?php
if (!defined('JM_DEBUG')) {
    @define('JM_DEBUG',DEBUG);
}
if (get_magic_quotes_gpc()) {
    die('Please check "magic quotes gpc"');
}

class ClassAutoLoader
{
    protected static $PrefixRules = array();
    const PrefixRuleType_PathPrefix = 'PathPrefix';
    const PrefixRuleType_Callback = 'Callback';

    /**
     * @param $name
     * @return bool
     */
    public static function Load($name)
    {
        $a = explode('_', $name);
        $classNamePrefix = $a[0];
        if (isset(self::$PrefixRules[$classNamePrefix])) {
            $rule = self::$PrefixRules[$classNamePrefix]['Rule'];
            if (self::$PrefixRules[$classNamePrefix]['Type'] == self::PrefixRuleType_PathPrefix) {
                $fn = $rule . join('/', $a) . '.class.php';
            } elseif (self::$PrefixRules[$classNamePrefix]['Type'] == self::PrefixRuleType_Callback) {
                return call_user_func($rule, $name);
            } else {
                $fn = JM_APP_ROOT . join('/', $a) . '.class.php';
            }

            if (file_exists($fn)) {
                require_once $fn;
                return true;
            }
        } else {
            $getClassFile = function ($fillArray) {
                foreach ($fillArray as $file) {
                    if (file_exists($file)) {
                        return $file;
                    }
                }
                return false;
            };

            $className = join('/', $a);
            $fillArray = array(
                JM_WEB_FRAMEWORK_ROOT . $className . '.php',
                JM_APP_ROOT . $className . '.php',
                JM_APP_ROOT . $className . '.class.php',
                JM_APP_ROOT . $className . '.action.php',
            );

            if (defined('JM_COMMON')) {
                array_push($fillArray, JM_COMMON . join('/', $a) . '.php', JM_COMMON . join('/', $a) . '.class.php');
            }

            $fn = $getClassFile($fillArray);
            if ($fn === false) {
                return false;
            }

            System::SafeUntaint($fn);
            require_once $fn;
            return true;
        }
        return false;
    }

    /**
     * @param $classNamePrefix
     * @param $type
     * @param $rule
     */
    public static function SetPrefixRule($classNamePrefix, $type, $rule)
    {
        self::$PrefixRules[$classNamePrefix] = array(
            'Type' => $type,
            'Rule' => $rule,
        );
    }

    /**
     * @param $name
     * @return bool
     */
    public static function PrefixRuleCallback_RpcClient($name)
    {
        eval('
            class ' .$name . ' extends JMRpcClient
            {
                static $Instance;
                static public function Instance()
                {
                    $class = "' . $name . '";
                    if (empty(self::$Instance)) {
                        self::$Instance = new $class();
                        return self::$Instance;
                    } 
                }
            }
        ');

        return true;
    }
}

spl_autoload_register(array('ClassAutoLoader', 'Load'));


class PrefixSession
{
    private $keyPrefix;

    /**
     * PrefixSession constructor.
     *
     * @param null $keyPrefix
     */
    public function __construct($keyPrefix = null)
    {
        $this->setKeyPrefix($keyPrefix);
    }

    /**
     * @param $keyPrefix
     */
    public function setKeyPrefix($keyPrefix)
    {
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * @param $key
     * @param $val
     */
    public function set($key, $val) {
        $_SESSION[$this->keyPrefix . $key] = $val;
    }

    /**
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    public function get($key, $default = null)
    {
        return isset($_SESSION[$this->keyPrefix . $key]) ? $_SESSION[$this->keyPrefix . $key] : $default;
    }

    /**
     * @param $key
     */
    public function delete($key)
    {
        unset($_SESSION[$this->keyPrefix . $key]);
    }

    /**
     * @param null $keyPrefix
     * @return PrefixSession
     */
    public static function GetInstance($keyPrefix = null)
    {
        return new PrefixSession($keyPrefix);
    }
}


class System
{
    public static $ErrorLogHandler;
    public static $ErrorLogIgnoreExportFunctionPatterns = array();
    public static $ErrorLogVarExport = array('System', 'VarExportScalarAndArray');

    /**
     * @return bool
     */
    public static function IsRequestMethodPost()
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetRequestInt($r, $default = 0)
    {
        return isset($_REQUEST[$r]) ? intval($_REQUEST[$r]) : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetRequestFloat($r, $default = 0)
    {
        return isset($_REQUEST[$r]) ? floatval($_REQUEST[$r]) : $default;
    }

    /**
     * @param      $r
     * @param null $default
     *
     * @return null
     */
    public static function GetRequest($r, $default = null)
    {
        return isset($_REQUEST[$r]) ? $_REQUEST[$r] : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetPostInt($r, $default = 0)
    {
        return isset($_POST[$r]) ? intval($_POST[$r]) : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return float|int
     */
    public static function GetPostFloat($r, $default = 0)
    {
        return isset($_POST[$r]) ? floatval($_POST[$r]) : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetPost($r, $default = 0)
    {
        return isset($_POST[$r]) ? $_POST[$r] : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetGetInt($r, $default = 0)
    {
        return isset($_GET[$r]) ? intval($_GET[$r]) : $default;
    }

    /**
     * @param $r
     * @param $default
     *
     * @return float
     */
    public static function GetGetFloat($r, $default)
    {
        return isset($_GET[$r]) ? floatval($_GET[$r]) : $default;
    }

    /**
     * @param $r
     * @param $default
     *
     * @return mixed
     */
    public static function GetGet($r, $default = null)
    {
        return isset($_GET[$r]) ? $_GET[$r] : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetSessionInt($r, $default = 0)
    {
        return isset($_SESSION[$r]) ? intval($_SESSION[$r]) : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return float|int
     */
    public static function GetSessionFloat($r, $default = 0)
    {
        return isset($_SESSION[$r]) ? floatval($_SESSION[$r]) : $default;
    }

    /**
     * @param     $r
     * @param int $default
     *
     * @return int
     */
    public static function GetSession($r, $default = 0)
    {
        return isset($_SESSION[$r]) ? $_SESSION[$r] : $default;
    }

    /**
     * @param      $n
     * @param null $def
     *
     * @return null
     */
    public static function GetCookie($n, $def = null)
    {
        return isset($_COOKIE[$n]) ? $_COOKIE[$n] : $def;
    }

    /**
     * @param     $url
     * @param int $code
     */
    public static function Redirect($url, $code = 302)
    {
        header('Location: ' . $url, true, $code);
    }

    /**
     * @param     $url
     * @param int $code
     */
    public static function RedirectExit($url, $code = 302)
    {
        self::Redirect($url, $code);
        exit();
    }

    /**
     * @param $fn
     */
    public static function RequireOnceProjectFile($fn)
    {
        require_once JM_PROJECT_ROOT . $fn;
    }

    /**
     * @param $fn
     */
    public static function RequireOnceAppFile($fn)
    {
        require_once JM_APP_ROOT . $fn;
    }

    /**
     * @return bool
     */
    public static function IsHttpUserAgentSpider()
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $is_spider = strpos($agent, 'Googlebot') !== false ||
            strpos($agent, 'Baiduspider') !== false ||
            strpos($agent, 'Sosospider') !== false ||
            strpos($agent, 'Sogou web spider') !== false ||
            strpos($agent, 'Yahoo! Slurp') !== false ||
            strpos($agent, 'msnbot') !== false ||
            strpos($agent, 'Huaweisymantecspider') !== false ||
            strpos($agent, 'ia_archiver') !== false ;

        return $is_spider;
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsHttpUserAgentContains($s)
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return strpos($agent, $s) !== false;
    }

    /**
     * @return string
     */
    public static function GetRemoteIpWithProxy()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '';
    }

    /**
     * @return string
     */
    public static function GetRemoteIp()
    {
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '';
    }

    /**
     * @param $type
     * @param $charset
     */
    public static function SetHeaderContentTypeCharset($type, $charset)
    {
        header("Content-type: {$type}; charset={$charset}", true);
    }

    /**
     * @return string
     */
    public static function GetHttpProxyForwardedIp()
    {
        $headers = array(
            'X_REAL_IP',
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR_IP',
            'VIA',
            'X_FORWARDED_FOR',
            'FORWARDED_FOR',
            'X_FORWARDED',
            'FORWARDED',
            'CLIENT_IP',
            'FORWARDED_FOR_IP',
        );

        $ips = array();
        foreach($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ips[] = $_SERVER[$header];
            }
        }
        return join(',', $ips);
    }

    /**
     * Get the Client/Agent Ip from HTTP_X_REAL_IP, ProxyForward, REMOTE_ADDR
     *
     * @return array|string
     */
    public static function GetClientIp()
    {
        $clientIp = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : '';
        if ($clientIp) {
            $ips = explode(',', $clientIp);
            $clientIp = trim($ips[0]);
            return $clientIp;
        } elseif ($clientIp = self::GetHttpProxyForwardedIp()) {
            $clientIp = explode(',', $clientIp);
            return $clientIp[0];
        } else {
            return self::GetRemoteIp();
        }
    }

    /**
     * only export scalar and array. return the class name if meets an object
     *
     * @param     $v
     * @param int $depth
     *
     * @return mixed|string
     */
    public static function VarExportScalarAndArrayRecursive(&$v, $depth = 0)
    {
        $leftPadding = str_repeat('  ', $depth);
        if (is_array($v)) {
            $mark = '__JM_array_recursive_mark';
            if (isset($v[$mark])) {
                return '*RECURSION*';
            }
            $result = "array {\n";
            $v[$mark] = 1;
            foreach ($v as $k => &$e) {
                if ($k !== $mark) {
                    $ks = is_string($k) ? '"' . addslashes($k) . '"' : $k;
                    $result .= $leftPadding . " $ks => " . self::VarExportScalarAndArrayRecursive($e, $depth + 1) . ",\n";
                }
            }
            unset($v[$mark]);
            $result .= $leftPadding . "}";
            return $result;
        } elseif (is_object($v)) {
            return get_class($v);
        }
        return var_export($v, true);
    }

    /**
     * @param $v
     *
     * @return mixed|string
     */
    public static function VarExportScalarAndArray($v)
    {
        return self::VarExportScalarAndArrayRecursive($v);
    }

    /**
     * @param $s
     */
    public static function SafeUntaint(&$s)
    {
        static $hasUntaint = null;
        if ($hasUntaint === null) {
            $hasUntaint = function_exists('untaint');
        }
        if ($hasUntaint) {
            untaint($s);
        }
    }

    function JMGetRequestInt($r,$default=0) {
        return isset($_REQUEST[$r]) ? intval($_REQUEST[$r]) : $default;
    }

    function JMGetRequestFloat($r,$default=0) {
        return isset($_REQUEST[$r]) ? floatval($_REQUEST[$r]) : $default;
    }

    function JMGetRequest($r,$default=null) {
        return isset($_REQUEST[$r]) ? $_REQUEST[$r] : $default;
    }

    function JMGetPostInt($r,$default=0) {
        return isset($_POST[$r]) ? intval($_POST[$r]) : $default;
    }

    function JMGetPostFloat($r,$default=0) {
        return isset($_POST[$r]) ? floatval($_POST[$r]) : $default;
    }

    function JMGetPost($r,$default=null) {
        return isset($_POST[$r]) ? $_POST[$r] : $default;
    }

    function JMGetGetInt($r,$default=0) {
        return isset($_GET[$r]) ? intval($_GET[$r]) : $default;
    }

    function JMGetGetFloat($r,$default=0) {
        return isset($_GET[$r]) ? floatval($_GET[$r]) : $default;
    }

    function JMGetGet($r,$default=null) {
        return isset($_GET[$r]) ? $_GET[$r] : $default;
    }

    function JMGetSessionInt($r,$default=0) {
        return isset($_SESSION[$r]) ? intval($_SESSION[$r]) : $default;
    }

    function JMGetSessionFloat($r,$default=0) {
        return isset($_SESSION[$r]) ? floatval($_SESSION[$r]) : $default;
    }

    function JMGetSession($r,$default=null) {
        return isset($_SESSION[$r]) ? $_SESSION[$r] : $default;
    }

    function JMGetCookie($n, $def = null) {
        return isset($_COOKIE[$n]) ? $_COOKIE[$n] : $def;
    }

}


class Utility
{
    /**
     * 保留a的内容的前提下，把只存在于b的内容复制到a中
     *
     * @param $a
     * @param $b
     *
     * @return mixed
     */
    public static function ArrayAddRecursive($a, $b)
    {
        foreach ($a as $k => &$v) {
            if (is_array($v) && isset($b[$k])) {
                $v = self::ArrayAddRecursive($v, $b[$k]);
            }
        }

        $a = $a + $b;
        return $a;
    }

    /**
     * @param $a
     * @param $b
     * @param $key
     *
     * @return mixed
     */
    public static function ArraySubAddByKey($a, $b, $key)
    {
        $b = self::ArrayReindex($b, $key);
        foreach ($a as $k => $v) {
            if (isset($b[$v[$key]])) {
                $a[$k] = $v + $b[$v[$key]];
            }
        }
        return $a;
    }

    /**
     * @param $a
     *
     * @return mixed
     */
    public static function ArrayValueDefault($a)
    {
        $keys = func_get_args();
        array_shift($keys);
        $def = array_pop($keys);
        foreach ($keys as $k) {
            if (!isset($a[$k])) {
                return $def;
            }
            $a = $a[$k];
        }
        return $a;
    }

    /**
     * @param $a
     *
     * @return null
     */
    public static function ArrayValue($a)
    {
        $keys = func_get_args();
        array_shift($keys);
        foreach ($keys as $k) {
            if (!isset($a[$k])) {
                return null;
            }
            $a = $a[$k];
        }
        return $a;
    }

    /**
     * @param $a
     * @param $keys
     *
     * @return array
     */
    public static function ArraySub($a, $keys)
    {
        $r = array();
        foreach ($keys as $k => $v) {
            if (is_int($k)) {
                if (isset($a[$v])) {
                    $r[$v] = $a[$v];
                }
            } else {
                $r[$k] = isset($a[$k]) ? $a[$k] : $v;
            }
        }
        return $r;
    }

    /**
     * @param $ary
     * @param $k
     *
     * @return array
     */
    public static function ArrayColumnValues($ary, $k)
    {
        $a = array();
        foreach ($ary as $row) {
            $a[] = $row[$k];
        }
        return $a;
    }

    /**
     * @param $ary
     * @param $k
     *
     * @return array
     */
    public static function ArrayColuneGroup($ary, $k)
    {
        $a = array();
        foreach ($ary as $row) {
            $a[$row[$k]][] = $row;
        }
        return $a;
    }

    /**
     * @param $ary
     * @param $k
     *
     * @return mixed
     */
    public static function ArrayColumnJsonEncode($ary, $k)
    {
        foreach ($ary as $key => $val) {
            $ary[$key][$k] = json_encode($ary[$key][$k]);
        }
        return $ary;
    }

    /**
     * @param      $ary
     * @param      $k
     * @param bool $assoc
     *
     * @return mixed
     */
    public static function ArrayColumnJsonDecode($ary, $k, $assoc = false)
    {
        foreach ($ary as $key => $val) {
            $ary[$key][$k] = json_decode($ary[$key][$k], $assoc);
        }
        return $ary;
    }

    /**
     * @param $ary
     * @param $k
     *
     * @return mixed
     */
    public static function ArrayColumnClear($ary, $k)
    {
        foreach ($ary as $key => $val) {
            unset($val[$k]);
            $ary[$key] = $val;
        }
        return $ary;
    }

    /**
     * @param $ary
     * @param $keys
     *
     * @return array
     */
    public static function ArrayColumnKeep($ary, $keys)
    {
        $a = array();
        foreach ($ary as $key => $val) {
            $item = array();
            foreach ($keys as $k) {
                $item[$k] = $val[$k];
            }
            $a[$key] = $item;
        }
        return $a;
    }

    /**
     * @param $ary
     * @param $k
     *
     * @return array
     */
    public static function ArrayColumnCount($ary, $k)
    {
        $result = array();
        foreach ($ary as $key => $val) {
            $v = $val[$k];
            $result[$v] = isset($result[$v]) ? $result[$v] + 1 : 1;
        }
        return $result;
    }

    /**
     * @param      $ary
     * @param      $k
     * @param      $v
     * @param null $returnKey
     * @param bool $strict
     *
     * @return bool|int|string
     */
    public static function ArrayColumnSearch($ary, $k, $v, $returnKey = null, $strict = false)
    {
        if ($strict) {
            foreach ($ary as $key => $val) {
                if (isset($val[$k]) && $val[$k] === $v) {
                    if ($returnKey === null) {
                        return $key;
                    }
                    return $val[$returnKey];
                }
            }
        } else {
            foreach ($ary as $key => $val) {
                if (isset($val[$k]) && $val[$k] == $v) {
                    if ($returnKey === null) {
                        return $key;
                    }
                    return $val[$returnKey];
                }
            }
        }

        return false;
    }

    /**
     * @param      $ary
     * @param null $key
     *
     * @return array
     */
    public static function ArrayReindex($ary, $key = null)
    {
        $a = array();
        if ($key === null) {
            foreach ($ary as $v) {
                $a[] = $v;
            }
        } else {
            foreach ($ary as $v) {
                $a[$v[$key]] = $v;
            }
        }
        return $a;
    }

    /**
     * @param       $s
     * @param array $columnNames
     *
     * @return array
     */
    public static function ExplodeLines($s, $columnNames = array())
    {
        $lineSeperator = "\n";
        if (strpos($s, $lineSeperator) === false) {
            $lineSeperator = "\r";
        }

        $columnSeperator = "\t";
        if (strpos($s, $columnSeperator) === false) {
            $columnSeperator = ",";
        }

        $lines = explode($lineSeperator, $s);
        $result = array();
        foreach ($lines as $line) {
            $line = trim($line);
            $cells = explode($columnSeperator, $line);
            $resultRow = array();
            foreach ($cells as $key => $val) {
                $k = empty($columnNames[$key]) ? $key : $columnNames[$key];
                $resultRow[$k] = $val;
            }
            if ($resultRow) {
                $result[] = $resultRow;
            }
        }
        return $result;
    }

    /**
     * @param      $re
     * @param      $string
     * @param null $key
     *
     * @return bool
     */
    public static function PregValues($re, $string, $key = null)
    {
        if (preg_match_all($re, $string, $matches) === false) {
            return false;
        }

        if (is_null($key)) {
            if (count($matches) == 1) {
                return $matches[0];
            }

            if (count($matches) == 2 && isset($matches[1])) {
                return $matches[1];
            }
        } else {
            if (isset($matches[$key])) {
                return $matches[$key];
            }
        }
        return false;
    }

    /**
     * @param      $len
     * @param null $chars
     *
     * @return string
     */
    public static function RandomString($len, $chars = null)
    {
        $t = '';
        if (!$chars) {
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        }
        $min = 0;
        $max = strlen($chars) -1;

        for ($i = 0; $i < $len; $i++) {
            $t .= $chars[mt_rand($min, $max)];
        }
        return $t;

    }

    /**
     * @param $s
     * @param $a
     *
     * @return null|string|string[]
     */
    public static function Format($s, $a)
    {
        return preg_replace('/\{%(\w+)([^}]*)\}/e', '$a["\1"]\2', $s);
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidName($s)
    {
        return preg_match('/^\w+$/', $s) != 0;
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidCodeSymbol($s)
    {
        return preg_match('/^\w+$/', $s) != 0;
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidEMail($s)
    {
        return preg_match('/^[^@]+@[^@]+\.[^@]+$/', $s) != 0;
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidDate($s)
    {
        return preg_match('/^\d+-\d+-\d+$/', $s) != 0;
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidDatetime($s)
    {
        return preg_match('/^\d+-\d+-\d+ \d+:\d+:\d+$/', $s) != 0 || self::IsValidDate($s);
    }

    /**
     * @param $s
     *
     * @return false|int
     */
    public static function IsValidDateLongFormat($s)
    {
        return preg_match('/^\d\d\d\d-\d\d-\d\d$/', $s);
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidTime($s)
    {
        return preg_match('/^(\d+:)*\d+$/', $s) != 0;
    }

    /**
     * @param $s
     *
     * @return bool
     */
    public static function IsValidMobilePhone($s)
    {
        return strlen($s) >= 11;
    }
}

function JMSystemErrorLog($level, $class, $title, $content = null)
{
    static $depth = 0;
    static $count = 0;

    if($depth >= 3)
        return false;

    $depth ++;

    $datetime = date('Y-m-d H:i:s');

    if( ! is_null($content) && ! is_string($content))
        $content = serialize($content);

    if($depth >= 2)
    {
        if($depth == 2)
        {
            if($content)
                error_log("[$datetime][$level][$class](too deep) $title\n$content\n\n");
            else
                error_log("[$datetime][$level][$class](too deep) $title\n\n");
        }
        $depth --;
        return false;
    }


    $ignoreExportFunctionPatterns = System::$ErrorLogIgnoreExportFunctionPatterns;
    $ignoreExportFunctionPatterns[] = '/^Smarty/';
    $ignoreExportFunctionPatterns[] = '/^JM/';
    $ignoreExportFunctionPatterns[] = '/^Mono/';
    $ignoreExportFunctionPatterns[] = '/^PDO->__construct/';

    $count++;

    $backtrace = debug_backtrace();
    $backtraceString = '';
    if(defined('DEBUG') && DEBUG){
        foreach($backtrace as $i => $call)
        {
            $fullClassFunction = '';
            if(isset($call['class']))
                $fullClassFunction = $call['class'] . $call['type'];
            if(isset($call['function']))
                $fullClassFunction .= $call['function'];

            $backtrace[$i]['fullClassFunction'] = $fullClassFunction;
            $call['fullClassFunction'] = $fullClassFunction;

            $exportedArgs = '';

            $shouldIgnoreExport = ($i == 0);  // ignore the first call (this function ...)
            foreach($ignoreExportFunctionPatterns as $pattern)
            {
                if(preg_match($pattern, $fullClassFunction))
                {
                    $shouldIgnoreExport  = true;
                    break;
                }
            }

            if( ! $shouldIgnoreExport )
            {
                $exportedArgs = array();
                if(isset($call['args']))
                {
                    foreach($call['args'] as $j=>$arg)
                    {
                        $exportedArgs[] = call_user_func(System::$ErrorLogVarExport, $arg);
                    }
                }
                $exportedArgs = join(',', $exportedArgs);
            }
            $fileLine = '';
            if(isset($call['file']))
                $fileLine .= $call['file'];
            $fileLine .= ':';
            if(isset($call['line']))
                $fileLine .= $call['line'];

            $backtrace[$i]['fileLine'] = $fileLine;
            $backtraceString .= "#[$i] {$fullClassFunction}($exportedArgs) @ [$fileLine]\n";
        }
    }
    $isLogHandled = false;
    if( ! empty(System::$ErrorLogHandler) )
    {
        try
        {
            if (defined('DEBUG') && DEBUG) {
                $isLogHandled = call_user_func(System::$ErrorLogHandler, compact('level', 'class', 'title', 'content', 'backtrace', 'backtraceString'));
            } else {
                $isLogHandled = call_user_func(System::$ErrorLogHandler, compact('level', 'class', 'title', 'content'));
            }
        }
        catch (Exception $e)
        {
            error_log("[$datetime]" . $e->getMessage());
        }
    }

    if( ! $isLogHandled)
    {
        if($content)
            $isLogHandled = error_log("[$datetime][$level][$class] $title\n$content\n$backtraceString\n\n");
        else
            $isLogHandled = error_log("[$datetime][$level][$class] $title\n$backtraceString\n\n");
    }

    $depth --;
    return $isLogHandled;
}


function SystemErrorHandler($errno, $errstr, $errfile, $errline)
{
    // E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING can not be handled
    $errors = array(
        1 => 'E_ERROR',
        2 => 'E_WARNING',
        4 => 'E_PARSE',
        8 => 'E_NOTICE',
        16 => 'E_CORE_ERROR',
        32 => 'E_CORE_WARNING',
        64 => 'E_COMPILE_ERROR',
        128 => 'E_COMPILE_WARNING',
        256 => 'E_USER_ERROR',
        512 => 'E_USER_WARNING',
        1024 => 'E_USER_NOTICE',
        2048 => 'E_STRICT',
        4096 => 'E_RECOVERABLE_ERROR',
        8192 => 'E_DEPRECATED',
        16384 => 'E_USER_DEPRECATED',
    );


    $loglevel = JM_LOG_WARNING;
    if($errno == E_ERROR
        || $errno == E_PARSE
        || $errno == E_CORE_ERROR
        || $errno == E_COMPILE_ERROR
        || $errno == E_USER_ERROR
        || $errno == E_RECOVERABLE_ERROR
    ) {
        $loglevel = JM_LOG_ERR;
    }
    if (isset($errors[$errno])) {
        $type = $errors[$errno];
    } else {
        $type = $errno;
    }

    $logtitle = "$type: $errstr @ [$errfile:$errline]";

    SystemErrorLog($loglevel, "PHP", $logtitle);

    // 'false' to continue build-in handler (show error messages), some document said "null" is wrong.
    return false;
}

function SystemExceptionLog($exception)
{
    $s = $exception->getTraceAsString();
    $s = str_replace(array("\r\n","\n","\r"), '\n', $s);
    $m = $exception->getMessage();
    $m = str_replace(array("\r\n","\n","\r"), '\n', $m);
    $log = 'PHP Exception: '."'".get_class($exception)."'".' with message '."'".$m."' trace:{$s} in ".$exception->getFile().' on line '.$exception->getLine();
    error_log($log);
}

function SystemExceptionHandler($exception)
{
    /* @var Exception $exception */
    SystemExceptionLog($exception);
    if (function_exists('fb')) {
        fb($exception);
    }
    $s = "An exception occurs: " . $exception->getMessage() . ", please contact with us. Thank you.\n";
    if (JM_DEBUG) {
        $s = "<pre style='border:1px solid black;padding:5px;'>\n";
        $s .= 'Exception: ' . get_class($exception)  . "<br />\n";
        $s .= 'ExceptionCode: ' . $exception->getCode()  . "<br />\n";
        $s .= 'ExceptionMessage: ' . $exception->getMessage()  . "<br />\n";
        $s .= $exception->getTraceAsString()  . "<br />\n";
        if ( $exception instanceof JMRpcDelegatedException) {
            $s .= 'DelegatedException: ' . $exception->getDelegatedExceptionClass()  . "<br />\n";
            $s .= 'DelegatedExceptionCode: ' . $exception->getDelegatedCode()  . "<br />\n";
            $s .= 'DelegatedExceptionMessage: ' . $exception->getDelegatedMessage()  . "<br />\n";
            $s .= $exception->getDelegatedTraceAsString() . "<br />\n";
        }
        $s .= "</pre>\n";
    }
    // protect passwords
    $s = preg_replace('/PDO->__construct\(.*\)/i', 'PDO->__construct()', $s);
    $s = preg_replace('/connect\(.*\)/i', 'connect()', $s);
    $s = preg_replace('/login\(.*\)/i', 'login()', $s);
    die($s);
}
// disable custom handler for unified log collection.
// set_error_handler('JMSystemErrorHandler', E_ALL);
// set_exception_handler('JMSystemExceptionHandler');
