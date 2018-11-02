<?php
/**
 * AB测试.
 */

namespace Applications\Api\Config;

class AbTestConf {


    public static function getList()
    {
        return array(
            1 => array(
                'tag' => 'demo', // abtest name
                'enable' => false, // 是否启用
                'start_time' => '2015-10-01 00:00:00', // 开始时间
                'end_time' => '2029-10-01 00:00:00', // 结束时间
                'case' => array( // 测试case，值为百分比
                    'testa' => 10,
                    'testb' => 10,
                    'testc' => 80,
                ),
                'limit' => array(
                    'iphone' => array('min' => '4.6'),
                    'android' => array('min' => '4.6'),
                )
            ),

            // 搜索ab测试 新 #124941
            281 => array(
                'tag' => 'MSearchList_1',
                'enable' => true,
                'start_time' => '2017-11-12 00:00:00',
                'end_time' => '2020-12-23 00:00:00',
                'case' => array(
                    'v1' => 5,
                    'v2' => 5,
                    'v3' => 10,
                    'v4' => 10,
                    'v5' => 15,
                    'v6' => 15,
                    'v7' => 20,
                    'v8' => 20,
                ),
                'limit' => array(
                    'iphone' => array('min' => '4.6'),
                    'android' => array('min' => '4.6'),
                ),
            ),

        );
    }
}