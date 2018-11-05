<?php
/**
 * 缓存处理.
 */

namespace Lib;

use Registry;
use Lib\Util as LUtil;
use Memcache\Pool as MPool;

class Cache
{
    /**
     * Memcache缓存.
     *
     * @param string $key             缓存key.
     * @param string $ttl             缓存时间.
     * @param array  $function        类方法array(className,methodName).
     * @param array  $function_params 参数array('1','fsfs').
     * @param bool   $flush           穿透.
     *
     * @return mixed
     * @throws \Memcache\Exception
     */
    public static function memCache($key, $ttl, $function = array(), $function_params = array(), $flush = false)
    {
        if ($flush) {
            $res = call_user_func_array($function, $function_params);
        } else {
            // 获取环境key
            $real_key = md5($key);
            // 实例
            $mem = MPool::instance();
            // 获取缓存数据
            $res = $mem->get($real_key);

            if ($res === false || (LUtil::isStaffIp() && Registry::get('_r'))) {
                // 存储数据
                if (!empty($function)) {
                    $res = call_user_func_array($function, $function_params);
                    $mem->set($real_key, $res, 0, $ttl);
                }
            }
        }
        return $res;
    }

    /**
     * Memecache Set.
     *
     * @param string $key  缓存key.
     * @param array  $data 缓存数据.
     * @param int    $ttl  缓存时间.
     *
     * @return boolean
     * @throws \Memcache\Exception
     */
    public static function memSet($key, $data, $ttl = 10)
    {
        // 获取环境key
        $real_key = md5($key);

        // 实例
        $mem = MPool::instance();

        // 设置
        $flag = $mem->set($real_key, $data, $ttl);

        return $flag;
    }

    /**
     * Memcache Get.
     *
     * @param string $key 缓存key.
     *
     * @return mixed
     * @throws \Memcache\Exception
     */
    public static function memGet($key)
    {
        // 获取环境key
        $real_key = md5($key);

        // 实例
        $mem = MPool::instance();

        // 获取
        $data = $mem->get($real_key);

        return $data;
    }
}