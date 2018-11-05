<?php
/**
 * 和错误相关的处理.
 *
 * @author Peng Wang<pengw@jumei.com>
 * @date: 2013-09-22
 * @version 0.2
 */

/**
 * Class Helper_Session.
 */
class Helper_Error
{
    /**
     * 将错误信息保存在session中.
     *
     * @param string $message 错误信息.
     *
     * @return void
     */

    public static function saveSessionMessageError($message)
    {
        Utility_UniqueSession::instance()->SessionMessageError = $message;
    }

    /**
     * 获取session 中的错误信息.
     *
     * @return string
     */
    public static function getSessionMessageError()
    {
        return Utility_UniqueSession::instance()->SessionMessageError;
    }

    /**
     * 将错误信息保存在session中.
     *
     * @return void
     */
    public static function clearSessionMessageError()
    {
        Utility_UniqueSession::instance()->SessionMessageError = null;
    }

}
