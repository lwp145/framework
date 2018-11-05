<?php
/**
 * Ajax 输出封装.
 *
 * @author jichaow <jichaow2@jumei.com>
 * @date 2013-10-10
 * @version 0.1
 */

/**
 * JSON 输出封装.
 */
class Helper_Ajax
{
    const DEFAULT_SUCCESS_FLAG = 'success';
    const DEFAULT_ERROR_FLAG   = 'unknow_error';
    const DEFAULT_ERROR_MSG    = '未知错误';

    /**
     * 生成ajax请求的返回数据.
     *
     * @param string|array $errorCode 错误码.
     * @param string       $message   错误消息.
     * @param array|null   $data      额外数据.
     *
     * @return array            返回数据格式
     */
    public static function buildResponeData($errorCode, $message, $data = null)
    {
        $respone = array();
        if ( is_array($errorCode) ) {
            if (isset($errorCode['error'])) {
                if (isset($errorCode['data']) ) {
                    if (is_array($data)) {
                        $data = array_merge($errorCode['data'], $data);
                    } else {
                        $data = $errorCode['data'];
                    }

                }
                if (isset($errorCode['message'])) {
                    $message = $errorCode['message'];
                }
                $errorCode = $errorCode['error'];
            }
        }

        if ($errorCode === true) {
            $errorCode = self::DEFAULT_SUCCESS_FLAG;
        }
        if ($errorCode === false) {
            $errorCode = self::DEFAULT_ERROR_FLAG;
        }
        $respone['error'] = $errorCode;
        $respone['message'] = $message;
        $respone['data']    = $data;
        return $respone;
    }

    /**
     * 生成ajax请求的返回数据,格式为Json.
     *
     * @param string     $errorCode 错误码.
     * @param string     $message   错误消息.
     * @param array|null $data      额外数据.
     *
     * @return string            返回Json
     */
    public static function buildJsonResponeData($errorCode, $message, $data = null)
    {
        return json_decode(self::buildResponeData($errorCode, $message, $data));
    }

    /**
     * 生成ajax请求的返回数据,格式为数组,消息获取使用回调函数.
     *
     * @param string     $errorCode 错误码.
     * @param string     $callName  错误信息回调函数.
     * @param array|null $data      额外数据.
     *
     * @return array            返回数据格式
     */
    public static function buildResponeDataByCallBack($errorCode, $callName, $data = null)
    {
        $message = self::DEFAULT_ERROR_MSG;
        if (function_exists($callName)) {
            try {
                $message = call_user_func($callName, $errorCode, $data);
            } catch (Exception $e) {

            }
        }

        return (self::buildResponeData($errorCode, $message, $data));
    }

}
