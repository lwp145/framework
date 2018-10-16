<?php
/**
 * 定义全局变量.
 */

define('APPLICATION_DIR',__DIR__); // Applications目录
//var_dump(__DIR__);die;
define('JM_VENDOR_DIR',__DIR__.'/../Vendor/'); // 和Applications并列的Vendor目录
define('JM_WEB_FRAMEWORK_ROOT',__DIR__.'/../Frameworks/'); // 和Applications并列的Frameworks目录

if (!defined('DEBUG')) {
    define('DEBUG', true);
}