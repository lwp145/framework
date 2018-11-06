<?php
/**
 * 服务请求包装.
 *
 * @author xyh<yinghuix@jumei.com>
 */

namespace Lib;

use Registry;
use PHPClient\Text;
use Lib\Util as LUit;
use PHPClient\JMTextRpcClient;
use Thrift\Client as ThriftClient;
use Memcache\Pool as MemCachePool;
use Redis\RedisCache as RedisCache;
use Lib\MException as LMException;

/**
 * 服务请求包装.
 */
class Service
{

    const SERVICE_TYPE_THRIFT = 'Thrift';
    const SERVICE_TYPE_RPC_CLIENT = 'RpcClient';
    const SERVICE_TYPE_TEXT_RPC_CLIENT = 'TextRpc';

    protected $config;
    protected $debug;
    protected $forceRefresh;
    protected $logger;

    private static $lastCode   = 0;
    private static $lastMsg    = '';
    private static $codeAry    = array();
    private static $msgAry     = array();

    private static $pageNum    = 50;
    private static $useListCache = false;
    private static $useListMethodArray = array();

    /**
     * 初始化.
     */
    public function __construct()
    {
        // 获取配置
        if (empty($config)) {
            $this->config['rpc']    = Util::getConfig('PHPClient');
            $this->config['thrift'] = Util::getConfig('Thrift');
            $this->config['cache']  = Util::getApiConfig('CacheConf');
        }

        // 调试模式
        $this->debug = Util::getConfig('app', 'debug');

        // 内网IP判断刷新
        $refresh = Registry::get('_r');
        $this->forceRefresh = (Util::isStaffIp() && !empty($refresh)) ? true : false;

        // 载入RPC配置
        JMTextRpcClient::config($this->config['rpc']);

        // 载入Thrift配置
        ThriftClient::config($this->config['thrift']);
    }

    /**
     * 无缓存获取数据方式.
     *
     * @param string $method     服务方法.
     * @param array  $parameters 服务参数.
     *
     * @return mixed
     */
    public function call($method, $parameters = array())
    {
        $parameters = is_array($parameters) ? $parameters : array($parameters);

        return $this->run($method, $parameters);
    }

    /**
     * 整点自动过期.
     *
     * @param string  $method     服务方法.
     * @param array   $parameters 方法参数.
     * @param boolean $autoExpire 自动过期.
     *
     * @return mixed
     * @throws MException 异常.
     */
    public function smart($method, $parameters = array(), $autoExpire = false)
    {
        // 是否设置缓存时间在缓存配置中CacheConf
        if ($this->isSetConfigCache($method) === false) {
            throw new LJMException('Service ' . $method . ' Not In Api/Config/CacheConf');
        }

        // 获取环境
        $phase = Util::getPhase();

        // 获取缓存时间 (防止同时穿透增加随机1-3秒)
        $ttl = $this->getTTL($method, $autoExpire);

        // Mem缓存KEY
        $key = 'service:' . $method . ':' . $phase .':' . md5(json_encode($parameters));

        // 获取本地MemCache数据
        $data = MemCachePool::instance()->get($key);

        if ($data === false || $this->forceRefresh) {

            // Redis防止数据完全打到一台远程RedisDB上
            $redisKey = $key . ':rand_' . mt_rand(1, 5);

            // 获取远程到Redis数据
            $data = RedisCache::getInstance('cache')->GET($redisKey);

            if ($data === false || $this->forceRefresh) {
                // 获取Service数据
                $data = $this->call($method, $parameters);
                $data = json_encode($data);
                // 存储远程到Redis中
                RedisCache::getInstance('cache')->SETEX($redisKey, $ttl, $data);
            }

            // 存储到MemCache防止并发
            MemCachePool::instance()->set($key, $data, 0, 6);
        }

        $data = json_decode($data, true);

        return $data;
    }

    /**
     * 获取过期时间.
     *
     * @param string  $method     服务方法.
     * @param boolean $autoExpire 自动过期时间.
     *
     * @return integer
     */
    public function getTTL($method, $autoExpire = false)
    {
        // 避免整点的时候同时穿透缓存 对后端造成压力，加0-3秒延时
        $randTtl = mt_rand(0, 3);

        // 获取缓存时间
        if (isset($this->config['cache'][$method])) {
            // 配置过期时间
            $min = $this->config['cache'][$method];
            $ttl = $min * 60 + $randTtl;
        } else {
            $ttl = 30 + $randTtl;
        }

        // 整点过期
        if ($autoExpire) {
            // 获取临近下个整点时间(例：9:20 临近10点)
            $willExpireTime = strtotime(date('Y-m-d H', strtotime('+1 hour')) . ':00');

            // 获取当前距离整点时间
            $willExpireTtl = $willExpireTime - time();

            // 过期时间
            $ttl = $willExpireTtl > $ttl ? $ttl : $willExpireTtl;
        }

        return intval($ttl);
    }

    /**
     * 解析 RPC or Thrift 方法.
     *
     * @param string $method 方法.
     *
     * @return array
     * @throws \Exception 异常.
     */
    protected function parseMethod($method)
    {
        $tmp = explode('_', $method, 3);

        switch ($tmp[0]) {
            case self::SERVICE_TYPE_RPC_CLIENT:
                list($class,$function) = explode('::', $method);
                $endpoint = $tmp[1];
                if (!class_exists($class)) {
                    eval("class $class extends \\PHPClient\\JMTextRpcClient{}");
                }
                return array($endpoint, $class::instance($this->config['rpc']), $function);
            case self::SERVICE_TYPE_THRIFT:
                list($class,$function) = explode('::', $method);
                $endpoint = substr($class, 7);
                $client = \Thrift\Client::instance($endpoint);
                return array($endpoint, $client, $function);
                break;
            case self::SERVICE_TYPE_TEXT_RPC_CLIENT:
                list($class,$function) = explode('::', $tmp[2]);
                $class = Text::inst($tmp[1])->setClass($class);
                return array('', $class, $function);
            default:
                throw new \Exception('Service Type Is Not Support');
        }
    }

    /**
     * 调用远程服务.
     *
     * @param string $tag        方法.
     * @param array  $parameters 参数.
     *
     * @return mixed
     */
    protected function run($tag, $parameters = array())
    {
        list($endpoint, $class, $method) = $this->parseMethod($tag);

        $res = call_user_func_array(array($class, $method), $parameters);

        // 内网打印数据
        if (LUit::isStaffIp()) {
            LUit::extraDebug(array('call' => $tag, 'param' => $parameters, 'data' => $res));
        }

        return $this->parseServiceResult($tag, $res);
    }

    /**
     * 解析结果.
     *
     * @param string $tag    方法.
     * @param array  $result 结果.
     *
     * @return mixed
     */
    protected function parseServiceResult($tag, $result)
    {
        if (strpos($tag, self::SERVICE_TYPE_TEXT_RPC_CLIENT.'_Mob_') !== false) {
            if (isset($result['code'])) {
                $this->setCode($tag, $result['code']);
            }

            if (isset($result['message'])) {
                $this->setMessage($tag, $result['message']);
            }

            $result = isset($result['result']) ? $result['result'] : $result;
        }
        return $result;
    }

    /**
     * 强制刷新.
     *
     * @return boolean
     */
    public function isForceRefresh()
    {
        return $this->forceRefresh;
    }

    /**
     * 返回状态.
     *
     * @param string $tag  方法.
     * @param string $code 状态码.
     *
     * @return void
     */
    private function setCode($tag, $code)
    {
        self::$lastCode        = $code;
        self::$codeAry[$tag]   = $code;
    }

    /**
     * 返回文案.
     *
     * @param string $tag     方法.
     * @param string $message 文案.
     *
     * @return void
     */
    private function setMessage($tag, $message)
    {
        self::$lastMsg         = $message;
        self::$msgAry[$tag]    = $message;
    }

    /**
     * 获取状态码.
     *
     * @param string $tag 方法.
     *
     * @return integer
     */
    public function getCode($tag = '')
    {
        return !empty($tag) ? (!empty(self::$codeAry[$tag]) ? self::$codeAry[$tag] : 0) : self::$lastCode;
    }

    /**
     * 返回文案.
     *
     * @param string $tag 文案.
     *
     * @return string
     */
    public function getMessage($tag = '')
    {
        return !empty($tag) ? (!empty(self::$msgAry[$tag]) ? self::$msgAry[$tag] : '') : self::$lastMsg;
    }

    /**
     * 判断是配置缓存时间.
     *
     * @param string $method 方法.
     *
     * @return boolean
     */
    private function isSetConfigCache($method)
    {
        $flag = false;
        if (isset($this->config['cache'][$method]) || !empty($this->config['cache'][$method])) {
            $flag = true;
        }
        return $flag;
    }

}
