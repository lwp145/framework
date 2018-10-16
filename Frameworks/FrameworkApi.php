<?php
/**
 * Api框架.
 */

require_once (JM_WEB_FRAMEWORK_ROOT.'FrameworkWebManagement.php');

use \Lib\Util as LUtil;
use \Modules\VersionCtrl as VersionCtrl;

abstract class ViewController_Api extends ViewController_WebManagementBase
{
    // 是否需要登录
    protected $is_need_login = false;

    // 入参统一控制（设置私有）获取参数使用getParams
    protected static $params = array();

    /**
     * ViewController_Api constructor.
     * @param SiteEngine $siteEngine
     * @throws Exception
     */
    public function __construct(SiteEngine $siteEngine)
    {
        // 初始化系统配置
        parent::__construct($siteEngine);

        // 设置session-id
        self::setCookieId();

        // 参数初始化
        self::setParams();

        // 注册全局参数
        self::setRegistryParams();

        // 登录
        self::needLogin();

        // Varnish控制
        self::setVarnishTTL();
    }

    /**
     * 设置cookie.
     *
     * @return void
     * @throws Exception
     */
    public static function setCookieId()
    {

        $cookie_id = LUtil::getCookie('PHPSESSID');
        if (empty($cookie_id)) {
            $cookie_id = md5(mt_rand(1, 10000000) . time());
            LUtil::setCookie('PHPSESSID', $cookie_id, time() + 86400);
        }
        Registry::set('PHPSESSID',$cookie_id);
    }

    /**
     * 设置参数.
     *
     * @return void
     */
    public static function setParams()
    {
        list($control, $dir, $class) = explode('_', get_called_class());

        $params_conf = LUtil::getApiConfig('ParamsConf');

        $params_base = isset($params_conf['base']) ? $params_conf['base'] : array();

        $params_class = (isset($params_conf[$dir][$class])) && is_array($params_conf[$dir][$class]) ? $params_conf[$dir][$class] : array();

        $params_all = array_merge($params_base, $params_class);

        foreach ($params_all as $key => $conf) {
            // 判断是否是必须参数
            if ($conf['default'] == 'necessary') {
                if (isset($_REQUEST[$key])) {
                    // 判断类型
                    self::$params[$key] = self::formatParams($key, $conf);
                } else {
                    // 缺少参数
                    LUtil::response(array(), 'Missing Param ' . $key . '.', 'toast');
                }
            } else {
                if (isset($_REQUEST[$key])) {
                    self::$params[$key] = self::formatParams($key, $conf);
                } else {
                    self::$params[$key] = $conf['default'];
                }
            }
        }
        // 禁用全局使用，所有参数必须列入Api/Config/ParamsConf.php白名单中.
        unset($_GET,$_POST,$_REQUEST);
    }

    /**
     * 格式化传入参数.
     *
     * @param string $key       参数名.
     * @param array  $paramConf 参数配置.
     *
     * @return int|null|string|string[]
     */
    public static function formatParams($key, $paramConf)
    {
        if ($paramConf['type'] == 'int') {
            // 转成整型
            $value = intval($_REQUEST[$key]);
        } else {
            // 获取字符
            $value = (string)$_REQUEST[$key];

            // 必传参数为空检测
            if($paramConf['default'] == 'necessary' && empty($value)) {
                LUtil::response(array(), 'Param ' . $key . ' is Empty.', 'toast', 40001);
            }

            // 长度检测
            if (strlen($value) > $paramConf['len']) {
                LUtil::response(array(), 'Params Max Length: ' . $key . ' more than ' . $paramConf['len'] . '.');
            }

            // 验证特殊字符
            if ($paramConf['validate'] == true) {
                if (LUtil::validateXSSParams($value)) {
                    LUtil::response(array(), 'Param Validate Error:' . $key . '.');
                }
            }

            // 过滤特殊字符
            if ($paramConf['filter'] == true) {
                $value = LUtil::filterXSSParams($value);
            }
        }

        return $value;
    }

    /**
     * 获取参数.
     *
     * @param string $key 参数.
     *
     * @return string
     */
    public static function getParams($key)
    {
        return (string)self::$params[$key];
    }

    /**
     * 注册全局函数.
     *
     * @return void
     */
    protected static function setRegistryParams()
    {
        // 平台
        Registry::set('platform', self::getParams('platform'));
        // 版本
        Registry::set('client_v', self::getParams('client_v'));
        // AB测试
        Registry::set('ab', self::getParams('ab'));
        // 渠道
        Registry::set('source', self::getParams('source'));
        // 用户标签
        Registry::set('user_tag_id', self::getParams('user_tag_id'));
        // 首次安装
        Registry::set('appfirstinstall', self::getParams('appfirstinstall'));
        // 注册IP
        Registry::set('user_ip', LUtil::getUserIp());
        // 注册缓存穿透函数
        Registry::set('_r', self::getParams('_r'));
        // 注册环境
        Registry::set('phase', LUtil::getPhase());
        // APP
        Registry::set('appid', LUtil::getCookie('app_id'));
        Registry::set('app_name', 'jumei');
        Registry::set('urlscheme', 'jumeimall');
        // 型号
        Registry::set('model', LUtil::getCookie('model'));
        // IMEI
        Registry::set('imei', LUtil::getCookie('imei'));
        // Mac
        Registry::set('mac', LUtil::getCookie('mac'));
        // Android唯一设备号
        Registry::set('device_uid', LUtil::getCookie('device_uid'));
        Registry::set('unique_device_id', LUtil::getCookie('unique_device_id'));
        // iPhone设备
        Registry::set('idfa', LUtil::getCookie('idfa'));
        Registry::set('idfv', LUtil::getCookie('idfv'));
        // Touch的JSONP请求CallBack
        Registry::set('callback', self::getParams('callback'));
        // 安卓CPU
        Registry::set('cpu_type', self::getParams('cpu_type'));
        // 系统版本
        Registry::set('platform_v', self::getParams('platform_v'));
        // 手机型号
        Registry::set('model', self::getParams('model'));
        // IOS手机型号
        Registry::set('device_model', self::getParams('device_model'));

        // sellparams
        Registry::set('sellparams', self::getParams('sellparams'));

        // applet
        Registry::set('applet', self::getParams('applet'));

        // provider
        Registry::set('provider', self::getParams('provider'));

        // 分站
        Registry::set('site', self::getParams('site'));
        // sellType.
        Registry::set('sellType', self::getParams('sellType'));
        // sellLabel.
        Registry::set('sellLabel', self::getParams('sellLabel'));
        // 专场ID
        Registry::set('store_id', isset(self::$params['store_id']) ? self::getParams('store_id') : '');

        // 第一次安装
        Registry::set('is_first_launch', self::getParams('is_first_launch'));
        // app_owen_data
        Registry::set('app_owen_data', self::getParams('app_owen_data'));

    }

    /**
     * 登录处理.
     *
     * @throws Exception
     */
    public function needLogin()
    {
        if ($this->is_need_login) {
            if (!MAccount::Instance()->isLogin()) {
                throw new LJMException(40000);
            }
        }
    }

    /**
     * 自动完成Varnish Cache 时间处理.
     *
     * @return void
     */
    public function setVarnishTTL()
    {
        // 根据class获取缓存时间，单位秒
        $config = LUtil::getConfig('common', 'varnish');
        $varnish_conf = LUtil::getConfig('common', 'varnish_conf'); // 客户端首页缓存

        // 开启客户端首页缓存
        if (VersionCtrl::isSupportCounterClientVAPP63() && isset($varnish_conf['is_nav_var']) && $varnish_conf['is_nav_var'] == 1
            && ((self::getParams('platform') == 'android' && self::getParams('client_v') >= $varnish_conf['client_android']) || (self::getParams('platform') == 'iphone' && self::getParams('client_v') >= $varnish_conf['client_iphone']))) {
            // 获取当前方法配置
            $max_age = '';
            $is_show_var = 0;
            $ttl = '';
            $ttl_app = '';
            if (isset($config[get_called_class()]) && !empty($config[get_called_class()])) {
                // 获取varnish过期时间
                $ttl = $config[get_called_class()];
                $diff = strtotime(date('Y-m-d H', strtotime('+1 hour')) . ':00') - time();
                if ($diff < $ttl) {
                    $ttl = $diff + mt_rand(1, 30);
                }
                $ttl = intval($ttl);
                $is_show_var = 1;
            }

            if (isset($varnish_conf[get_called_class()]) && !empty($varnish_conf[get_called_class()])) {
                // 获取APP过期时间
                $ttl_app = $varnish_conf[get_called_class()];
                $diff = strtotime(date('Y-m-d H', strtotime('+1 hour')) . ':00') - time();
                if ($diff < $ttl_app) {
                    $ttl_app = $diff + mt_rand(1, 30);
                }
                $ttl_app = intval($ttl_app);

                // 大促期间提前一小时结束缓存
                if ($varnish_conf['var_end_time'] > 0 && time() >= $varnish_conf['var_end_time'] && time() <= $varnish_conf['var_start_time']) {
                    $max_age = '';
                } else {
                    // 根据客户端版本,低版本不返回缓存时间
                    if (self::getParams('platform') == 'android') {
                        $max_age = (self::getParams('client_v') >= $varnish_conf['client_android']) ? 'max-age=' . $ttl_app . ', public; ' : '';
                    }

                    if (self::getParams('platform') == 'iphone') {
                        $max_age = (self::getParams('client_v') >= $varnish_conf['client_iphone']) ? 'max-age=' . $ttl_app . ', public; ' : '';
                    }
                }
            }

            if ($is_show_var) {
                header('page-time: ' . $ttl);
                header('Cache-Control: '.$max_age.'max-age-var=' . $ttl);
            } elseif (!empty($max_age)) {
                header('page-time: ' . $ttl_app);
                header('Cache-Control: '.$max_age);
            }
        } else {
            // 原逻辑 max-age
            if (isset($config[get_called_class()]) && !empty($config[get_called_class()])) {
                // 获取 过期时间
                $ttl = $config[get_called_class()];
                $diff = strtotime(date('Y-m-d H' ,strtotime('+1 hour')) . ':00') - time();
                if ($diff < $ttl) {
                    $ttl = $diff + mt_rand(1, 30);
                }
                $ttl = intval($ttl);
                header('page-time: ' . $ttl);
                header('Cache-Control: max-age=' . $ttl . ', public');
            }
        }

    }

}