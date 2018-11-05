<?php
/**
 * 一些工具集.
 */
class Utility_Util
{

    /**
     * 递归查找一个needle是否存在于一个数组中.
     *
     * @param mixed $needle   目标.
     * @param array $haystack 所需搜索的数组.
     *
     * @return boolean 返回true为成功查找.
     */
    public static function arraySearchRecursive($needle, array $haystack)
    {
        foreach ($haystack as $key => $item) {
            if (is_array($item)) {
                self::arraySearchRecursive($needle, $item);
            } else {
                if ($item == $needle) {
                    return $key;
                }
            }
        }
        return false;
    }

    // Get top level branch for the array tree

    /**
     * 非递归查询.
     *
     * @param mixed $needle   查找的东西.
     * @param array $haystack 从这里面查找.
     *
     * @return miexd 如果顶层数组中存再或者顶层不是数组但是就是要查询的东西,那就返回顶层数组.
     */
    public static function arrayGetBranch($needle, array $haystack)
    {
        foreach ($haystack as $key => $item) {
            if (is_array($item)) {
                if (in_array($needle, $item)) {
                    return $key;
                }
            } else {
                if ($item == $needle) {
                    return $key;
                }
            }
        }
    }

    /**
     * 其实我很疑惑，为什么会再这里出现sql拼接这样的事情.cart是前端,按理说不应改处理这样的事情.况且处理手法也很山寨.
     *
     * Deprecated by sunyuw.
     *
     * @param array $elements 查询的字段.
     *
     * @deprecated since version 0.1
     * @return string $range sql查询字段字符串.
     */
    public static function array2Sqlrange(array $elements)
    {
        $range = "(";
        foreach ($elements as $element) {
            $range .= "'" . $element . "',";
        }
        $range = substr($range, 0, - 1);
        $range .= ")";
        return $range;
    }

    /**
     * 递归转意对象.这名字多难看呀，建议换个名字.
     *
     * 注意：如果传入的为对象,则对象将被克隆再转移每个属性.
     *
     * @param array|object|string $input 需要进行递归转意的对象或者数组.
     *
     * @return array|object|string 转意之后的返回值.
     */
    public static function ggAddslashes($input)
    {
        if (is_array($input)) {
            foreach ($input as $key => $val) {
                $input [$key] = self::ggAddslashes($val);
            }
        } elseif (is_object($input)) {
            $tmp = clone $input;
            foreach ($tmp as $key => $val) {
                $tmp->$key = self::ggAddslashes($val);
            }
            $input = $tmp;
        } else {
            $input = addslashes($input);
        }
        return $input;
    }

    /**
     * 转object为array.
     *
     * 注意:如果对象为复合对象将会进行递归调用来转换object.
     *
     * @param object $object 需要转换为array的object.
     *
     * @return array 深度转换之后的array.
     */
    public static function object2Array($object)
    {
        if (is_object($object)) {
            $return = array ();
            foreach ($object as $key => $value) {
                if (sizeof($value) > 1) {
                    $return [$key] = self::object2Array($value);
                } else {
                    $return [$key] = (string) $value;
                }
            }
        } else {
            $return = $object;
        }
        return $return;
    }

    /**
     * 直接进行post请求.
     *
     * @param string  $host     主机.
     * @param string  $url      地址.
     * @param array   $postdata 传输的内容的数组.
     * @param integer $port     请求端口.
     *
     * @return nuill 无返回值.
     * @throws Exception 无法连接地址.
     */
    public static function redirectPost($host, $url, array $postdata, $port = 80)
    {
        define('REQ_TIME_OUT', 30);
        $fp = fsockopen($host, $port, $errno, $errstr, REQ_TIME_OUT);

        if ($fp) {
            fputs($fp, "POST $url HTTP/1.1\r\n");
            fputs($fp, "Host: $host\r\n");
            fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
            $out = "";
            while (list ($k, $v) = each($postdata)) {
                if (strlen($out) != 0)
                    $out .= "&";
                $out .= rawurlencode($k) . "=" . rawurlencode($v);
            }
            // echo $out;
            $out = trim($out);
            fputs($fp, "Content-length: " . strlen($out) . "\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, "$out");
            fputs($fp, "\n");
            fclose($fp);
        } else {
            throw new Exception("Unable to connect: host: $host, port: $port, url: $url");
        }
        return null;
    }

    /**
     * 认证....算法....
     *
     * @param string $string    加密串.
     * @param string $operation 加密操作.
     * @param string $key       加密key.
     *
     * @return string 解密后的串.
     */
    public static function authcode($string, $operation, $key = '')
    {
        $authkey = 'sidngxzcvnOI';

        $key = md5($key ? $key : md5($authkey . $_SERVER ['HTTP_USER_AGENT']));
        $key_length = strlen($key);

        $string = $operation == 'DECODE' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
        $string_length = strlen($string);

        $rndkey = $box = array ();
        $result = '';

        for ($i = 0; $i <= 255; $i ++) {
            $rndkey [$i] = ord($key [$i % $key_length]);
            $box [$i] = $i;
        }

        for ($j = $i = 0; $i < 256; $i ++) {
            $j = ($j + $box [$i] + $rndkey [$i]) % 256;
            $tmp = $box [$i];
            $box [$i] = $box [$j];
            $box [$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i ++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box [$a]) % 256;
            $tmp = $box [$a];
            $box [$a] = $box [$j];
            $box [$j] = $tmp;
            $result .= chr(ord($string [$i]) ^ ($box [($box [$a] + $box [$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
                return substr($result, 8);
            } else {
                return '';
            }
        } else {
            return str_replace('=', '', base64_encode($result));
        }
    }

    /**
     * 将十六进制的字符串转换为数字.
     *
     * @param string $hex 十六进制字符串.
     *
     * @return integer 转换之后数字.
     */
    public static function bchexdec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i ++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex [$i - 1])), bcpow('16', strval($len - $i))));
        }
        return $dec;
    }

    /**
     * 进行base62编码.
     *
     * @param string $dec 原文串.
     *
     * @return string 编码之后的串.
     */
    public static function bcDecbase62($dec)
    {
        $base62 = "";
        $base = 62;
        while ($dec != 0) {
            $mod = bcmod($dec, $base);
            if (($mod >= 10) && ($mod < 36)) {
                $base62 .= chr($mod + 55);
            } elseif (($mod >= 36) && ($mod < 62)) {
                $base62 .= chr($mod + 61);
            } else {
                $base62 .= $mod;
            }
            $dec = bcdiv($dec, $base);
        }
        return strrev($base62);
    }

    /**
     * Base62解码.
     *
     * @param string $base62 编码的串.
     *
     * @return string $dec 解码后的串.
     */
    public static function bcBase62dec($base62)
    {
        $base = 62;
        $dec = "";
        for ($i = 0; $i <= strlen($base62) - 1; $i ++) {
            $char = substr($base62, $i, 1);
            if (ord($char) <= 57) {
                $dec = bcadd($dec, bcmul($char, bcpow($base, strlen($base62) - $i - 1)));
            } elseif ((ord($char) >= 65) && (ord($char) <= 90)) {
                $dec = bcadd($dec, bcmul((ord($char) - 55), bcpow($base, strlen($base62) - $i - 1)));
            } else {
                $dec = bcadd($dec, bcmul((ord($char) - 61), bcpow($base, strlen($base62) - $i - 1)));
            }
        }
        return $dec;
    }

    /**
     * 向日志系统传输以便记录log以及track.
     *
     * @param string $category  Log种类.
     * @param string $subsystem 子系统.
     * @param array  $params    参数.
     *
     * @return null 无返回值.
     */
    public static function log2Track($category, $subsystem, array $params)
    {
        $http = new HttpRequest('http://track-int.reemake.com/' . $category . '.php', HTTP_METH_GET);
        $params ['subsystem'] = $subsystem;
        $params ['ip'] = CRegistry::Singleton()->get('SERVER')->get('REMOTE_ADDR');

        $http->addQueryData($params);
        try {
            $http->send();
        } catch (Exception $ex) {
            // do nothing
        }
        return null;
    }


    /**
     * 取得网站URL.
     *
     * @staticvar string $url
     *
     * @return string 网站url.
     */
    public static function getWebsiteUrl()
    {
        static $url = '';
        if (! $url) {
            // the "dummy" is for "http://host/", to make dirname work
            $url = "http://" . $_SERVER ['HTTP_HOST'] . dirname($_SERVER ['PHP_SELF'] . "dummy");
            $url = str_replace('\\', '/', $url);
        }
        return $url;
    }

    /**
     * 递归追加数组.但是不会覆盖数组a中的内容.
     *
     * @param array $a 目标数组.
     * @param array $b 需要添加的内容.
     *
     * @return array 保留a的内容的前提下，把只存在于b的内容复制到a中
     */
    public static function arrayAddRecursive(array $a, array $b)
    {
        $a = $a + $b;

        foreach ($a as $key => & $v) {
            if (is_array($v) && isset($b [$key])) {
                $v = arrayAddRecursive($v, $b [$key]);
            }
        }

        foreach ($b as $key => $val) {
            if (! isset($a [$key]))
                $a [$key] = $val;
        }
        return $a;
    }

    /**
     * 默认从web service层返回的simplexml对象无法用于序列化，无法放入cache.
     *
     * 因此，我们需要先通过某个方法，转换成stdClass才能用于缓存处理 TODO: 以后吧web
     *
     * service层更加优化一下，避免simplexml，这里就不再需要这个额外的转换.
     *
     * @param object $obj 需要转化的simplexml.
     *
     * @return object 转化之后的stdClass对象.
     */
    public static function xmlObj2StdClass($obj)
    {
        return json_decode(json_encode($obj));
    }

    /**
     * 递归转化传入对象从simplexml到stdClass.
     *
     * @param object|array $data 需要转化的对象.
     *
     * @return array|object 转化之后的数组或者对象.
     */
    public static function advXmlObj2StdClass($data)
    {
        $return = array ();

        $is_obj = is_object($data);
        if (! ($is_obj || is_array($data))) {
            return $data;
        }

        $data = (array) $data;
        if (empty ($data)) {
            return '';
        }

        foreach ($data as $k => $v) {
            $v = self::advXmlObj2StdClass($v);
            $return [$k] = $v;
        }
        if ($is_obj)
            return (object) $return;
        return $return;
    }

    /**
     * 重新索引数组，以原有的某个键.
     *
     * @param array $a   需要进行重新索引的数组或者对象.
     * @param mixed $key 新的键.
     *
     * @return array|object 重新索引之后的对象或者数组.
     */
    public static function reindexArray(array $a, $key)
    {
        $result = array ();
        foreach ($a as $item) {
            if (is_object($item))
                $result [$item->$key] = $item;
            else
                $result [$item [$key]] = $item;
        }
        return $result;
    }

    /**
     * This is to fix the empty xml object problem,注意,这个方法只会返回其第一个value.
     *
     * @param mixed $param 传入的object.
     *
     * @return string|mixed
     */
    public static function parseStdObj2String($param)
    {
        if (is_string($param)) {
            return $param;
        } else {
            if (! empty ($param)) {
                foreach ($param as $value) {
                    return $value;
                }
            } else {
                return '';
            }
        }
    }

    /**
     * 应该是用于读取文件信息的,很有可能是配置文件,首行为#会被认为是注释忽略.
     *
     * @param string $text     一个文本内容.
     * @param string $interval 分割符,但是还是要按找行进行分割.
     *
     * @return array $r 文本有效行组成的数组.
     */
    public static function explodeLines($text, $interval = ',')
    {
        $rows = explode("\n", $text);
        $r = array ();
        foreach ($rows as $row) {
            if (substr($row, 0, 1) == '#')
                continue;

            $row = explode("{$interval}", trim($row));
            $r [] = $row;
        }
        return $r;
    }

    /**
     * 解析xml文件为array.
     *
     * @param string $filepath 需要解析的文件的路径.
     *
     * @return array xml文件的解析array.
     */
    public static function convertXmlfile2Array($filepath)
    {
        return self::object2Array(simplexml_load_file($filepath));
    }

    /**
     * 获得语言xml文件路径.
     *
     * 这个方法暂时还不能使用，如果需要使用请配置依赖文件JM_APP_ROOT . 'Site/View/language_pack.xml'.
     *
     * @return string 语言xml文件路径.
     */
    public static function getLanguagePackPath()
    {
        return JM_APP_ROOT . 'Site/View/language_pack.xml';
    }

    /**
     * 将cookie设置为全站范围.
     *
     * 依赖：
     *
     * $siteInfo ['Site'] ['Main'] ['TopLevelDomainName'].
     *
     * @param array   $valuePairArray Coockie要存入的k-v对.
     * @param integer $expiration     Coockie过期时间.
     * @param string  $domain         作用域(可选，不填写代表全站).
     *
     *@return null 无返回.
     */
    public static function setCookiesWholeDomain(array $valuePairArray, $expiration, $domain = null)
    {
        if (! $domain) {
            $siteInfo = JMRegistry::get('SiteInfo');
            $domain = $siteInfo ['Site'] ['Main'] ['TopLevelDomainName'];
        }
        if ($domain [0] != '.')
            $domain = "." . $domain;
        foreach ($valuePairArray as $key => $value) {
            setcookie($key, $value, $expiration, '/', $domain);
        }
        return null;
    }

    /**
     * 从html中取得转意过后的内容.
     *
     * @param string  $content     内容......
     * @param integer $chars_count 取得内容的字符总数.
     *
     * @return string 转意后的截取到的内容.
     */
    public static function getAbstractInputFromHtml($content, $chars_count)
    {
        $content = html_entity_decode(strip_tags(stripslashes($content)), ENT_QUOTES, "UTF-8");
        $content = preg_replace('/[\x00-\x20]+/', ' ', $content); // WARNING:
        // /\s+/ in
        // php
        // 5.2.9
        // is
        // buggy,
        // which
        // will
        // replace
        // 0xA0 to
        // 0x20
        $content  = str_replace('　', ' ', $content);
        $content = preg_replace("/(^\s+)|(\s+$)/us", "", $content);
        $content = trim($content);
        $content = Helper_Util::truncate($content, $chars_count);
        return addslashes(trim($content));
    }

    /**
     * 截取字符串.
     *
     * @param string  $str      被截取的字符串.
     * @param integer $count    截取总数.
     * @param string  $encoding 编码方式.
     *
     * @return string 截取并转码后的字符串.
     */
    public static function cutStr($str, $count, $encoding = 'utf-8')
    {
        if (mb_strlen($str,$encoding) > $count) {
            $str = mb_substr($str, 0, $count, $encoding).'...';
        }
        return $str;
    }

    /**
     * 表格的下载相关的html.
     *
     * @param array  $data     表格数据.
     * @param array  $keynames 列名.
     * @param string $name     文件名.
     *
     * @return null 无返回.
     */
    public static function downXls(array $data, array $keynames, $name)
    {
        $xls [] = "<html><meta http-equiv=content-type content=\"text/html; charset=UTF-8\"><body><table border='1'>";
        $xls [] = "<tr><td>" . implode("</td><td>", array_values($keynames)) . '</td></tr>';
        foreach ($data as $o) {
            $xls_row = '<tr>';
            foreach ($keynames as $k => $dummy_v) {
                if (is_string($o [$k]) && is_numeric($o [$k])) {
                    $xls_row .= '<td style="mso-number-format:\@">';
                } else {
                    $xls_row .= '<td>';
                }
                $xls_row .= htmlspecialchars($o [$k]) . '</td>';
            }
            $xls_row .= '</tr>';
            $xls [] .= $xls_row;
        }
        $xls [] = '</table></body></html>';
        $xls = join("\r\n", $xls);
        header("Content-Type: application/vnd.ms-excel");

        $download_name = $name . '.xls';

        // Ensure UTF8 characters in filename are encoded correctly.
        if (preg_match("/MSIE/", $_SERVER ["HTTP_USER_AGENT"]))
            $download_name = urlencode($download_name);

        header("Content-Disposition: attachment; filename=\"$download_name\";");
        die (mb_convert_encoding($xls, 'UTF-8', 'UTF-8'));
        return null;
    }

    /**
     * 验证是否是公司的内部IP.
     *
     * @param string $ip IP地址.
     *
     * @return boolean 是否是公司内部的IP.
     */
    public static function validateJumeiInternalIp($ip)
    {
        // $ipList = SCRM::get('StaffIpList');
        $ipList = SCRM::get('StaffIpList');
        foreach ($ipList as $whiteIP) {
            if (self::isIpInRange($ip, $whiteIP)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断IP是否在指定的IP范围内.
     *
     * Eg.
     *
     * 127.0.0.1/127.0.0.255
     *
     * @param string $ip    IP地址.
     * @param string $range 范围的字符串.
     *
     * @return boolean 是否在制定的IP范围内.
     */
    public static function isIpInRange($ip, $range)
    {
        // For IP/MASK format.
        if (strpos($range, '/') !== false) {
            list ($range, $mask) = explode('/', $range, 2);
            // mask is 255.0.0.0 format
            if (strpos($mask, '.') !== false) {
                $mask = str_replace('*', '0', $mask);
                $decMask = ip2long($mask);
                return ((ip2long($ip) & $decMask) == (ip2long($range) & $decMask));
            } else {
                // Mask is CIDR size block
                $x = explode('.', $range);
                while (count($x) < 4)
                    $x [] = '0';
                list ($a, $b, $c, $d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty ($a) ? '0' : $a, empty ($b) ? '0' : $b, empty ($c) ? '0' : $c, empty ($d) ? '0' : $d);
                $decRange = ip2long($range);
                $decIP = ip2long($ip);
                $decWildcard = pow(2, (32 - $mask)) - 1;
                $decMask = ~ $decWildcard;

                return (($decIP & $decMask) == ($decRange & $decMask));
            }
        } else {
            return $ip == $range;
        }
        return false;
    }

    /**
     * 通过id和dimension_name取交集.
     *
     * @param array $arr1 数组A.
     * @param array $arr2 数组B.
     *
     * @return boolean|array 如果可以取交集就返回交集,否则就返回false.
     */
    public static function getIntersectByKey(array $arr1, array $arr2)
    {
        if (! is_array($arr1) || ! is_array($arr2)) {
            return false;
        }

        $arr = array ();
        if (is_array($arr2)) {
            foreach ($arr2 as $item) {
                if (isset($arr1 [$item ['id']])) {
                    $item ['name'] = $arr1 [$item ['id']] ['dimension_name'];
                    $arr [] = $item;
                }
            }
        }
        if (empty ($arr)) {
            return false;
        }
        return $arr;
    }

    /**
     * 干什么的我就不说了，名字很清楚.
     *
     * @param integer $number 需要转换的数字.
     *
     * @return string 汉字数字.
     */
    public static function number2Chinese($number)
    {
        $teamUnit = array('', '万', '亿');
        $bitUnit = array('', '十', '百', '千');
        $chMap = array('零', '一', '二', '三', '四 ', '五', '六', '七', '八', '九');

        // cut number inputted to 4bits a team
        $teams = explode(' ', trim(strrev(chunk_split(strrev($number), 4, ' '))));
        // translate 4bit team logic
        foreach ($teams as &$team) {
            $strLen = strlen($team);
            $zeroLastTime = false;
            $teamCh = '';
            for ($i = 0; $i < $strLen; $i++) {
                if ($team[$i] == 0) {
                    // now zero
                    if (! $zeroLastTime) {
                        // last isn't zero
                        $teamCh .= '零';
                    } else {
                        // last is zero
                        // continue
                    }
                    $zeroLastTime = true;
                } else {
                    // now isn't zero, translate as bit
                    $teamCh .= $chMap[ $team[$i] ] . $bitUnit[ $strLen - 1 - $i ];
                    $zeroLastTime = false;
                }
            }
            $team = rtrim($teamCh, '零');
        }

        $teamCnt = count($teams);
        for ($i = 0; $i < $teamCnt; $i++) {
            if (isset($teams[$i]) && !empty($teams[$i])) {
                $teams[$i] .= $teamUnit[ $teamCnt - 1 - $i ];
            }
        }

        return implode('', $teams);
    }

    /**
     * 获取调整过来站点的分站信息.
     *
     * @return string 二级域名.
     */
    public static function getSubDomain()
    {
        $host = isset($_SERVER['HTTP_REFERER']) ? strtolower($_SERVER['HTTP_REFERER']) : '';
        $hostFields = explode('.', $host);
        $hostFieldsNum = count($hostFields);
        $subDomain = $hostFieldsNum >= 3 ? $hostFields[0] : 'www';
        return ltrim($subDomain, 'http://');
    }

    /**
     * 购物流程增加紧迫感所需要的倒计时，前端需求'X分X秒'格式的文本.
     *
     * @param integer $mtime 购物车最后添加商品时间.
     * @param integer $delay 补齐时间到.
     *
     * @return string.
     */
    public static function mkCartCountDown($mtime, $delay = 0)
    {
        $t = self::calcCartCountDownSec($mtime);
        if ($delay > 0 && $t < $delay) {
            $t = $delay;
        }
        if ($t <= 0) {
            return '00分00秒';
        }

        $min = floor($t / 60);
        $sec = $t % 60;
        $str = sprintf("%02d分%02d秒", $min, $sec);
        return $str;
    }

    /**
     * 根据购物车最后添加商品时间截，计算倒计时的秒数.
     *
     * @param integer $mtime 购物车最后添加商品时间.
     *
     * @return integer.
     */
    public static function calcCartCountDownSec($mtime)
    {
        if ($mtime <= 0) {
            return 0;
        }

        $t = 1200 - (time() - $mtime);
        return $t <= 0 ? 0 : $t;
    }

}
