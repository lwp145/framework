<?php
/**
 * 缓存白名单.
 */

namespace Applications\Api\Config;

class CacheConf {

    /**
     * 接口名 单位分钟.
     * @return array
     */
    public static function getList()
    {
        return array(
            'TextRpc_Wing_MobileAdItems::getAdDetailMetroItems' => 5,
        );
    }
}