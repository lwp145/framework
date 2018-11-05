<?php

/**
 * 模板中通用的helper工具
 * @author Heng Lo<hengl@jumei.com>
 */
class Helper_Util {
    const CSRF_DEFAULT_FORM_NAME = '_form_';
    const CSRF_COOKIE_NAME = 'form_token';
    private static $csrfTokens = array();

    public static function timeDiff($timestamp) {
        $seconds = time() - $timestamp;
        if($seconds < 60){
            return $seconds.'秒前';
        }elseif($seconds < 3600){
            return floor($seconds/60).'分钟前';
        }elseif($seconds < 86400){
            return floor($seconds/3600).'小时前';
        }else{
            return floor($seconds/86400).'天前';
        }
    }


    public static function truncate($string, $length = 80, $etc = '...', $break_words = false, $middle = false) {
        if ($length == 0)
            return '';
        $string = trim(strip_tags($string));

        if (is_callable('mb_strlen')) {

            if (mb_detect_encoding($string, 'UTF-8, ISO-8859-1') === 'UTF-8') {

                // $string has utf-8 encoding
                if (mb_strlen($string, 'UTF-8') > $length) {

                    if (!$break_words && !$middle) {
                        $string = preg_replace('/\s+?$/u', '', mb_substr($string, 0, $length + 1, 'UTF-8'));
                    }

                    if (!$middle) {

                        return mb_substr($string, 0, $length, 'UTF-8') . $etc;
                    } else {
                        return mb_substr($string, 0, $length / 2, 'UTF-8') . $etc . mb_substr($string, - $length / 2, 'UTF-8');
                    }
                } else {
                    return $string;
                }
            }
        }
        // $string has no utf-8 encoding
        if (strlen($string) > $length) {
            $length -= min($length, strlen($etc));
            if (!$break_words && !$middle) {
                $string = preg_replace('/\s+?(\S+)?$/', '', substr($string, 0, $length + 1));
            }
            if (!$middle) {
                return substr($string, 0, $length) . $etc;
            } else {
                return substr($string, 0, $length / 2) . $etc . substr($string, - $length / 2);
            }
        } else {
            return $string;
        }
    }

    public static function mbStrReplace($search, $replace, $subject, &$count=0) {
        if (!is_array($search) && is_array($replace)) {
            return false;
        }
        if (is_array($subject)) {
            // call mb_replace for each single string in $subject
            foreach ($subject as &$string) {
                $string = &self::mbStrReplace($search, $replace, $string, $c);
                $count += $c;
            }
        } elseif (is_array($search)) {
            if (!is_array($replace)) {
                foreach ($search as &$string) {
                    $subject = self::mbStrReplace($string, $replace, $subject, $c);
                    $count += $c;
                }
            } else {
                $n = max(count($search), count($replace));
                while ($n--) {
                    $subject = self::mbStrReplace(current($search), current($replace), $subject, $c);
                    $count += $c;
                    next($search);
                    next($replace);
                }
            }
        } else {
            $parts = mb_split(preg_quote($search), $subject);
            $count = count($parts)-1;
            $subject = implode($replace, $parts);
        }
        return $subject;
    }

    public static function escape($string, $escType = 'html', $charset = 'UTF-8') {
        switch ($escType) {
            case 'html':
                if (preg_match("#^.#us", $string) == 1) {
                    return htmlspecialchars($string, ENT_QUOTES, $charset);
                } else {
                    return '';
                }

            case 'htmlall':
                return htmlentities($string, ENT_QUOTES, $charset);

            case 'url':
                return rawurlencode($string);

            case 'urlpathinfo':
                return str_replace('%2F', '/', rawurlencode($string));

            case 'quotes':
                // escape unescaped single quotes
                return preg_replace("%(?<!\\\\)'%", "\\'", $string);

            case 'hex':
                // escape every character into hex
                $return = '';
                for ($x = 0; $x < strlen($string); $x++) {
                    $return .= '%' . bin2hex($string[$x]);
                }
                return $return;

            case 'hexentity':
                $return = '';
                for ($x = 0; $x < strlen($string); $x++) {
                    $return .= '&#x' . bin2hex($string[$x]) . ';';
                }
                return $return;

            case 'decentity':
                $return = '';
                for ($x = 0; $x < strlen($string); $x++) {
                    $return .= '&#' . ord($string[$x]) . ';';
                }
                return $return;

            case 'javascript':
                // escape quotes and backslashes, newlines, etc.
                return strtr($string, array('\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\r" => '\\r', "\n" => '\\n', '</' => '<\/'));

            case 'mail':
                return self::mbStrReplace(array('@', '.'), array(' [AT] ', ' [DOT] '), $string);

            case 'nonstd':
                // escape non-standard chars, such as ms document quotes
                $_res = '';
                for ($_i = 0, $_len = strlen($string); $_i < $_len; $_i++) {
                    $_ord = ord(substr($string, $_i, 1));
                    // non-standard char, escape it
                    if ($_ord >= 126) {
                        $_res .= '&#' . $_ord . ';';
                    } else {
                        $_res .= substr($string, $_i, 1);
                    }
                }
                return $_res;

            default:
                return $string;
        }
    }


    public static function encrypt($id,$withApcCache=true){
        if ($withApcCache) {
            static $cacheKeyPrefix = 'cryptUid';
            $cacheKey = $cacheKeyPrefix . $id;
            $cacheData = apc_fetch($cacheKey);
            if (false !== $cacheData)
                return $cacheData;
        }
        $Account = JMRegistry::get('Account');
        $data = $id;
        $key = $Account['UidSecretKey'];
        $data = str_pad($data, 8, chr(0));
        $encrypted = mcrypt_encrypt(MCRYPT_3DES, $key, $data, MCRYPT_MODE_ECB);
        list(, $hex) = unpack("H16", $encrypted);
        $return = 'U' . $hex;
        if ($withApcCache) {
            apc_store($cacheKey, $return, 3600 * 24 * 7);
        }
        return $return;
    }

    public static function formatArrayWithField($arr, $field='id') {

        if(!empty($arr)&&  is_array($arr)) {
            $keys = array_map(function($a)use($field){return isset($a[$field]) ? $a[$field] : null;}, $arr);

            return array_combine($keys, $arr);
        }
        return array();
    }

    public static function getArrayFieldValue($arr, $field='id'){
        if(!empty($arr)&&  is_array($arr)) {
            $keys = array_map(function($a)use($field){return isset($a[$field]) ? $a[$field] : null;}, $arr);

            return $keys;
        }
        return array();
    }

    /**
     * @example: {{html_pager
    page_result=$page_result  ||   (page_count=10 row_count=100 rows_per_page=10 page_number=1)
    baseurl=""
    button_count=10
     *
    }}
     */
    //这里写static是因为之前曾经有个php或smarty的bug，会导致 html_pager_simple 里面 $this-> 无法调用
    static public function htmlPagerHelperGetEncodedUrl($baseurl, $pageNumber,$htmlFilter=true) {
        if ($htmlFilter === false) {
            // 取消 htmlspecialchars 解决过滤test.html?a=1&b2 中的&被过滤
            // 注意关闭过滤 参数需要自己过滤
            return str_replace ( '{%page_number}', $pageNumber, $baseurl );
        }
        return htmlspecialchars ( str_replace ( '{%page_number}', $pageNumber, $baseurl ) );
    }

    static public function getReportFilter($filterString) {

        $params = explode('-', $filterString, 6);

        $filter['rating'] = !empty($params[0])?rawurldecode($params[0]):0;
        $filter['skin_type'] = isset($params[1])?intval($params[1]):0;
        $filter['age'] = isset($params[2])?intval($params[2]):0;
        $filter['order_by'] = isset($params[3])?rawurldecode($params[3]):'valuable';
        $filter['pic'] = isset($params[4])?intval($params[4]):0;
        $filter['page'] = isset($params[5])?intval($params[5]):1;
        return $filter;
    }

    static public function buildReportFilterUrl($params,$filter,$pageBar = false,$head = 'report_list') {
        if(isset($filter['productId']) && $filter['productId'] > 0){

            $filter['url'] = $head.'-'.$filter['productId'];
        }else{
            $filter['url'] = 'reviews';
        }
        $filter['rating'] = isset($params['rating'])?rawurlencode($params['rating']):$filter['rating'];
        $filter['skin_type'] = isset($params['skin_type'])?rawurlencode($params['skin_type']):$filter['skin_type'];
        $filter['age'] = isset($params['age'])?$params['age']:$filter['age'];
        $filter['order_by'] = isset($params['order_by'])?rawurlencode($params['order_by']):$filter['order_by'];
        $filter['pic'] = isset($params['pic'])?rawurlencode($params['pic']):$filter['pic'];
        $filter['page'] = isset($params['page'])?rawurlencode($params['page']):1;
        if($pageBar) {
            return "/{$filter['url']}-{$filter['rating']}-{$filter['skin_type']}-{$filter['age']}-{$filter['order_by']}-{$filter['pic']}-{%page_number}.html";
        }

        return "/{$filter['url']}-{$filter['rating']}-{$filter['skin_type']}-{$filter['age']}-{$filter['order_by']}-{$filter['pic']}-{$filter['page']}.html";
    }

    static public function htmlPagerSimple($params) {
        $htmlFilter = isset ( $params ['html_filter'] ) && $params ['html_filter'] == false ? false : true; // htmlspecialchars
        // 开关
        if (isset ( $params ['page_result'] )) {
            $params ['row_count'] = $params ['page_result']->rowCount;
            $params ['page_count'] = $params ['page_result']->pageCount;
            $params ['rows_per_page'] = $params ['page_result']->rowsPerPage;
            $params ['page_number'] = $params ['page_result']->pageNumber;
            //
            if ($params ['page_result']->pageCount <= 1) {
                return '';
            }
        } else {
            if (! isset ( $params ['page_count'] )) {
                $params ['page_count'] = ceil ( $params ['row_count'] / $params ['rows_per_page'] );
            }
        }
        $baseurl = $params ['baseurl'];
        $params = $params + array (
                'button_count' => 9
            );

        if (! isset ( $params ['page_number'] ) or ! $params ['page_number'])
            $params ['page_number'] = 1;

        // 导航页码在当前页码左右均分
        $halfButtonCount = intval ( $params ['button_count'] / 2 );
        $buttonPageNumberStart = $params ['page_number'] - $halfButtonCount;
        $buttonPageNumberEnd = $params ['page_number'] + $halfButtonCount;

        // 如果左边越界，则扩展右边页码
        if ($buttonPageNumberStart < 1) {
            $buttonPageNumberEnd += (1 - $buttonPageNumberStart);
            if ($buttonPageNumberEnd > $params ['page_count'])
                $buttonPageNumberEnd = $params ['page_count'];
            $buttonPageNumberStart = 1;
        }
        // 如果右边越界，则扩展左边页码
        if ($buttonPageNumberEnd > $params ['page_count']) {
            $buttonPageNumberStart -= ($buttonPageNumberEnd - $params ['page_count']);
            if ($buttonPageNumberStart < 1)
                $buttonPageNumberStart = 1;

            $buttonPageNumberEnd = $params ['page_count'];
        }

        // 如果首页和尾页页码不足以放省略号，则强制不用省略号
        if ($buttonPageNumberStart <= 3)
            $buttonPageNumberStart = 1;
        if ($params ['page_count'] - $buttonPageNumberEnd + 1 <= 3)
            $buttonPageNumberEnd = $params ['page_count'];

        $htmlButtons = "";
        if ($params ['page_number'] > 1)
            $htmlButtons .= "<li><a class='pre' href='" . self::htmlPagerHelperGetEncodedUrl ( $baseurl, $params ['page_number'] - 1, $htmlFilter ) . "'>上一页</a></li>";

        if ($buttonPageNumberStart > 3) {
            for($i = 1; $i <= 2; $i ++)
                $htmlButtons .= "<li><a href='" . self::htmlPagerHelperGetEncodedUrl ( $baseurl, $i, $htmlFilter ) . "'>$i</a></li>";
            $htmlButtons .= "<li>...</li>";
        } else {
            // if($params['page_number'] <= 3)
        }
        for($i = $buttonPageNumberStart; $i <= $buttonPageNumberEnd; $i ++) {
            if ($i == $params ['page_number'])
                $s = "<li><span>" . $i . "</span></li>";
            else
                $s = "<li><a href='" . self::htmlPagerHelperGetEncodedUrl ( $baseurl, $i, $htmlFilter ) . "'>$i</a></li>";
            $htmlButtons .= $s;
        }
        if ($params ['page_count'] - $buttonPageNumberEnd >= 3) {
            $htmlButtons .= "<li>...</li>";

            // 超过 1000 页就不再显示最后两个末页了，否则对数据库冲击太大
            if ($params ['page_count'] < 1000) {
                for($i = $params ['page_count'] - 1; $i <= $params ['page_count']; $i ++)
                    $htmlButtons .= "<li><a href='" . self::htmlPagerHelperGetEncodedUrl ( $baseurl, $i, $htmlFilter ) . "'>$i</a></li>";
            } else {
                $htmlButtons .= "<li>共{$params['page_count']}页</li>";
            }
        }
        if ($params ['page_number'] < $buttonPageNumberEnd)
            $htmlButtons .= "<li><a class='next' href='" . self::htmlPagerHelperGetEncodedUrl ( $baseurl, $params ['page_number'] + 1, $htmlFilter ) . "'>下一页</a></li>";

        return $htmlButtons;
    }

    /**
     * 过滤ids参数
     * @example (100, 120, aa, 0, 1k23, 100) ==> (100,120)
     */
    static public function arrayFilter($arrs=array())
    {
        $res = array();
        foreach($arrs as $k=>$v)
        {
            $v_new =  intval(trim($v));
            if(!empty($v_new) && !in_array($v_new, $res))
            {
                $res[$k] = $v_new;
            }
        }
        return $res;
    }

    public static function getCdnFilePath($filePath) {
        if (defined('DEBUG') && DEBUG) return JM_CSS_ROOT.$filePath;
        $cssConfig = JMRegistry::get('cssConfig');
        if (!$cssConfig) return $filePath;
        if ($filePath[0] == '/') {
            $relativePath = substr($filePath, 1);
        } else {
            $relativePath = $filePath;
        }
        $realPath = $relativePath;
        if (isset($cssConfig['path'])) {
            $realPath = rtrim($cssConfig['path'],'/').'/'.$relativePath;
        }
        if (isset($cssConfig['version'])) {
            if (strpos($realPath, '?')) {
                $realPath .= '&v='.$cssConfig['version'];
            } else {
                $realPath .= '?v='.$cssConfig['version'];
            }
        }
        // if ( isset( $cssConfig[$relativePath] ) && $cssConfig[$relativePath] && isset($cssConfig[$relativePath]['name'])) {
        //     return $cssConfig[$relativePath]['name'];
        // }
        return $realPath;
    }

    public static function getCdnImagePath($filePath) {
        if (defined('DEBUG') && DEBUG) return $filePath;
        $imgConfig = JMRegistry::get('imgList');
        if (!$imgConfig) return $filePath;
        if ($filePath[0] == '/') {
            $relativePath = substr($filePath, 1);
        } else {
            $relativePath = $filePath;
        }
        // if ( isset( $imgList[$relativePath] ) && $imgList[$relativePath] && isset($imgList[$relativePath]['name'])) {
        //     return $imgList[$relativePath]['name'];
        // }
        $realPath = '/'.$relativePath;
        if (isset($imgConfig['path'])) {
            $realPath = rtrim($imgConfig['path'],'/').'/'.$relativePath;
        }
        if (isset($imgConfig['version'])) {
            if (strpos($realPath, '?')) {
                $realPath .= '&v='.$imgConfig['version'];
            } else {
                $realPath .= '?v='.$imgConfig['version'];
            }
        }
        return $realPath;
    }

    public static function getCdnJsPath($filePath) {
        if (defined('DEBUG') && DEBUG) return JM_JS_ROOT.$filePath;
        $jsList = JMRegistry::get('jsList');
        if (!$jsList) return $filePath;
        if ($filePath[0] == '/') {
            $relativePath = substr($filePath, 1);
        } else {
            $relativePath = $filePath;
        }
        $realPath = '/'.$relativePath;
        if (isset($jsList['path'])) {
            $realPath = rtrim($jsList['path'],'/').'/'.$relativePath;
        }
        if (isset($jsList['version'])) {
            if (strpos($realPath, '?')) {
                $realPath .= '&v='.$jsList['version'];
            } else {
                $realPath .= '?v='.$jsList['version'];
            }
        }
        // if ( isset( $jsList[$relativePath] ) && $jsList[$relativePath] && isset($jsList[$relativePath]['name'])) {
        //     return $jsList[$relativePath]['name'];
        // }
        return $realPath;
    }

    public static function getParams($key = null, $val = null) {
        $get = array_filter($_GET);
        if (isset($get[$key])) unset ($get[$key]);
        if ($val) $get[$key] = $val;
        $result = current(explode('?', $_SERVER['REQUEST_URI']));
        if (!empty($get)) {
            $result .= '?' . http_build_query($get);
        }
        return $result;
    }

    /**
     * 检查是否来自本站
     * @return bool 是否来自本站
     */
    public static function IsHttpRefererComeFromSelf() {
        $referer = isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : '';
        $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : '';
        if ($referer && $host) {
            $domainNameFields = explode('.', strtolower($host));
            $domainNameFieldsCount = count($domainNameFields);
            $refererHost = strtolower(preg_replace("/^(https?:\/\/)?(.+?)(:[0-9]+)?(\/.*)?$/i", "\\2", $referer));
            $refererHostNameFields = explode('.', $refererHost);
            $refererHostNameFieldsCount = count($refererHostNameFields);
            if ($domainNameFieldsCount <= 2 || $refererHostNameFieldsCount <= 2) {
                return false;
            } else {
                $domainSecond = implode('.', array_slice($domainNameFields, -2));
                $refererSecond = implode('.', array_slice($refererHostNameFields, -2));
                return $domainSecond == $refererSecond ? true : false;
            }
        } else {
            return false;
        }
    }

    /**
     * 根据表单名称生成一个token存到cookie中,表单名若为空则cookie中使用默认的键self::CSRF_DEFAULT_FORM_NAME
     * @param string $formName cookie中的表单名称, 不传递则使用self::CSRF_DEFAULT_FORM_NAME
     * @return string 提交表单需要的token
     */
    public static function generateCsrfToken($formName = null) {
        $formName = is_null($formName) ? self::CSRF_DEFAULT_FORM_NAME : $formName;
        $token = substr(md5(uniqid(mt_rand(1000, 9999))), 0, 8);
        $tokenInCookie = self::getCsrfTokenInCookie();
        self::$csrfTokens[$formName] = self::encryptToken($token);
        foreach (self::$csrfTokens as $k => $v) {
            $tokenInCookie[$k] = $v;
        }
        setcookie(self::CSRF_COOKIE_NAME, json_encode($tokenInCookie), time() + 86400, '/');
        return $token;
    }


    /**
     * 验证表单中包含的token是否正确
     * @param string $token 表单提交的token
     * @param string $formName cookie中的表单名字
     * @return boolean 验证通过返回true,不通过返回false
     */
    public static function validateCsrfToken($token = false, $formName = null) {
        $result = false;
        if (!$token) return $result;
        $formName = is_null($formName) ? self::CSRF_DEFAULT_FORM_NAME : $formName;
        $tokenInCookie = self::getCsrfTokenInCookie();
        if (isset($tokenInCookie[$formName]) && self::encryptToken($token) == $tokenInCookie[$formName]) {
            unset($tokenInCookie[$formName]);
            if (!empty($tokenInCookie)) {
                setcookie(self::CSRF_COOKIE_NAME, json_encode($tokenInCookie), time() + 86400, '/');
            } else {
                setcookie(self::CSRF_COOKIE_NAME, '', time() - 86400, '/');
            }
            $result = true;
        }
        return $result;
    }

    /**
     * 获取cookie中的token
     * @return array 没有的话是空array
     */
    public static function getCsrfTokenInCookie() {
        return isset($_COOKIE[self::CSRF_COOKIE_NAME]) && $_COOKIE[self::CSRF_COOKIE_NAME] ? json_decode($_COOKIE[self::CSRF_COOKIE_NAME], true) : array();
    }

    public static function encryptToken($token) {
        $account = JMRegistry::get('Account');
        $key = $account['UidSecretKey'];
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $result = md5($token . $key . $agent);
        return $result;
    }
}