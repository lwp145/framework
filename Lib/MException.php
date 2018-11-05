<?php
/**
 * 异常处理.
 */

namespace Lib;
use Lib\Util as LUtil;

/**
 * 异常处理.
 */
class MException extends \Exception
{
    private $extData = array();
    private $extCode = '10000';
    private $extAction = 'toast';
    private $originalCode = '';
    private $httpStatus = 400;

    /**
     * 返回数据结构.
     *
     * @param string  $msg        提示文案.
     * @param array   $data       返回数据.
     * @param string  $code       自定义状态码.
     * @param string  $action     客户端指令('', alert, toast).
     * @param integer $httpStatus HTTP状态码.
     *
     * throw Exception.
     */
    public function __construct($msg, $data = array(),  $code = '40001', $action = '', $httpStatus = 200)
    {
        // 检查msg，如果是int，check error配置，是否存在该错误码
        if (is_int($msg)) {
            $error_msg = LUtil::getApiConfig('ErrorCode');
            if (!empty($error_msg)) {
                $this->originalCode = $msg;
                if (is_array($error_msg)) {
                    $code = !empty($error_msg['code']) ? $error_msg['code'] : $msg;
                    $msg = !empty($error_msg['msg']) ? $error_msg['msg'] : '';
                } else {
                    $code = $msg;
                    $msg = $error_msg;
                }
            }
        } else {
            $this->originalCode = $msg;
        }

        parent::__construct((string)$msg);
        $this->extData = $data;
        $this->extCode = $code;
        $this->extAction = $action;
        $this->httpStatus = $httpStatus;

        header('HTTP/1.1' . $httpStatus);
    }

    /**
     * 错误码.
     *
     * @return mixed
     */
    public function getExtCode()
    {
        return $this->extCode;
    }

    /**
     * 显示数据.
     *
     * @return array
     */
    public function getExtData()
    {
        return $this->extData;
    }

    /**
     * Http状态码.
     *
     * @return int
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * 原始状态.
     *
     * @return string
     */
    public function getOriginalCode()
    {
        return $this->originalCode;
    }

    /**
     * 客户端执行动作('',toast,alert).
     *
     * @return string
     */
    public function getExtAction()
    {
        return $this->extAction;
    }
}