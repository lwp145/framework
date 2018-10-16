<?php
/**
 * 参数白名单.
 *
 * @author xyh<yinghuix@jumei.com>
 */

namespace Applications\Api\Config;

/**
 * 参数白名单.
 */
class ParamsConf
{
    // 必须参数(客户端必须传递参数)
    public static $NECESSARY = 'necessary';

    // 整型
    public static $TYPE_INT = 'int';

    // 字符串类型
    public static $TYPE_STRING = 'string';

    /**
     * 参数白名单.
     *
     * @return array
     */
    public static function getList()
    {
        return array(
            // 公共参数，每个接口都可以获取到
            'base' => array(
                'platform'          => array('type' => self::$TYPE_STRING, 'default' => self::$NECESSARY, 'len' => 64, 'validate' => true,  'filter' => false),
                'client_v'          => array('type' => self::$TYPE_STRING, 'default' => self::$NECESSARY, 'len' => 64, 'validate' => true,  'filter' => false),
                'source'            => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 64,  'validate' => false, 'filter' => true),
                'site'              => array('type' => self::$TYPE_STRING, 'default' => 'bj', 'len' => 64,  'validate' => false, 'filter' => true),
                'platform_v'        => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 64,  'validate' => false, 'filter' => true),
                'cpu_type'          => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 64,  'validate' => false, 'filter' => true),
                'model'             => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 64,  'validate' => false, 'filter' => true),
                'user_tag_id'       => array('type' => self::$TYPE_INT,    'default' => '', 'len' => 64,  'validate' => false, 'filter' => true),
                'ab'                => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 1024, 'validate' => false, 'filter' => true),
                'appfirstinstall'   => array('type' => self::$TYPE_INT,    'default' => '', 'len' => 1,   'validate' => false, 'filter' => true),
                'callback'          => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 256, 'validate' => false, 'filter' => true),
                '_r'                => array('type' => self::$TYPE_INT,    'default' => '', 'len' => 1,   'validate' => false, 'filter' => true),
                'device_model'      => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 256,  'validate' => false, 'filter' => true),
                'applet'            => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 64, 'validate' => false,  'filter' => true),
                'provider'          => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 64, 'validate' => false,  'filter' => true),
                'sellparams'        => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 512, 'validate' => false,  'filter' => false),
                'sellType'          => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 512, 'validate' => false,  'filter' => false),
                'sellLabel'         => array('type' => self::$TYPE_STRING, 'default' => '', 'len' => 512, 'validate' => false,  'filter' => false),
                'is_first_launch'   => array('type' => self::$TYPE_INT,    'default' => '', 'len' => 1, 'validate' => false,  'filter' => true),
                'app_owen_data'       => array('type' => self::$TYPE_INT,    'default' => '', 'len' => 64,  'validate' => false, 'filter' => false),
            ),

            'Common' => array(
                // 广告位.
                'Ad' => array(
                    'position' => array('type' => self::$TYPE_STRING, 'default' => self::$NECESSARY, 'len' => 256, 'validate' => true,  'filter' => false),
                ),
            ),
        );
    }

}
