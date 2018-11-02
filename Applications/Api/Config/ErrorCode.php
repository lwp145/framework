<?php
/**
 * 错误码.
 */

namespace Applications\Api\Config;

class ErrorCode {

    public static function getList()
    {
        return array(
            // 访问接口路径错误
            10000 => array('code' => 10000, 'action' => 'toast', 'msg' => '请求接口路径不存在！'),
            // API层服务级别错误，debug使用 4xxxx
            40000 => '用户未登录',
            40001 => 'API错误',
            40010 => '参数错误,无法找到该商品',
            40012 => '卡片不存在',
            41300 => '联合登陆-参数错误',
            41301 => '联合登陆-openId验证失败',
            41302 => '联合登陆-code验证失败',
            41303 => '联合登陆-绑定验证错误[1]',
            41304 => '联合登陆-token验证失败',
            41305 => '联合登陆-绑定验证错误[2]',
            41306 => '联合登陆-失败，请稍后尝试[3]',
            31300 => '服务器错误，请稍后再试',
            31301 => array('code' => 31301, 'action' => 'toast', 'msg' => '对不起，最多只能收藏300个商品！'),
            31302 => array('code' => 31302, 'action' => 'toast', 'msg' => '您已经收藏了该商品'),
            31305 => array('code' => 31305, 'action' => 'toast', 'msg' => '亲，您添加收藏好辛苦，休息一下~'),
            31306 => array('code' => 31306, 'action' => 'toast', 'msg' => '亲，您删除收藏好辛苦，休息一下~'),
            31401 => array('code' => 31401, 'action' => 'toast', 'msg' => '亲，您添加心愿好辛苦，休息一下~'),
            31402 => array('code' => 31402, 'action' => 'toast', 'msg' => '亲，您删除心愿好辛苦，休息一下~'),
            31501 => array('code' => 31501, 'action' => 'toast', 'msg' => '订阅失败'),
            31502 => array('code' => 31501, 'action' => 'toast', 'msg' => '您已经订阅过本产品了'),
            31503 => array('code' => 31501, 'action' => 'toast', 'msg' => '您的订阅数量已经达到上限'),
            31504 => array('code' => 31501, 'action' => 'toast', 'msg' => '该手机号码不存在'),
            31505 => array('code' => 31501, 'action' => 'toast', 'msg' => '无效的商品'),
            31506 => array('code' => 31501, 'action' => 'toast', 'msg' => '取消订阅失败'),
            31150 => array('code' => 40000, 'msg' => 'ACCOUNT_VALIDATE_INVALID_FIELDS'),
            31151 => array('code' => 40000, 'msg' => 'ACCOUNT_VALIDATE_INVALID_TK'),
            31152 => array('code' => 40000, 'msg' => 'ACCOUNT_VALIDATE_INVALID_USERINFO'),
            31153 => array('code' => 40000, 'msg' => 'ACCOUNT_VALIDATE_INVALID_PASSWORD'),
            31154 => array('code' => 40000, 'msg' => 'ACCOUNT_VALIDATE_INVALID_ACCOUNT'),
            33550 => array('code' => 33550, 'action' => 'toast', 'msg' => '星店最多添加300个商品，若添加请先删除部分已添加商品'), // 星店
            33551 => array('code' => 33551, 'action' => 'toast', 'msg' => '星店添加失败'),
            // 登录注册错误
            41000 => '', // Service返回错误信息
            // 客户端版本升级
            31200 => '当前版本是最新版本，不需要升级',
            31201 => '当前版本不是最新版本，需要升级',
        );
    }
}