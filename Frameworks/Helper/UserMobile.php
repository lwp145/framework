<?php
/**
 * 用户手机相关辅助项.
 *
 * @author jichaow <jichaow2@jumei.com>
 * @date 2013-10-10
 * @version 0.1
 */

/**
 * 用户手机相关辅助项.
 */
class Helper_UserMobile
{
    const MOBILE_SEND_SUCCESS                        = 'success';
    const MOBILE_SEND_ERROR_FORMAT_ERROR             = 'format_error';
    const MOBILE_SEND_ERROR_UNSAFE                   = 'unsafe'; // 手机号不安全
    const MOBILE_SEND_ERROR_SEND_COUNT_OUT           = 'send_count_out';
    const MOBILE_SEND_ERROR_CHECK_TIME_FAILED        = 'check_time_failed';
    const MOBILE_SEND_ERROR_NO_USER                  = 'no_user';
    const MOBILE_SEND_ERROR_NO_MOBILE                = 'no_mobile';
    const MOBILE_SEND_ERROR_MOBILE_USED_BY_OTHER     = 'mobile_used_by_other';

    const MOBILE_VALIDATE_SUCCESS                    = 'success';
    const MOBILE_VALIDATE_ERROR_ERROR_SMS_CODE       = 'err_sms_code';
    const MOBILE_VALIDATE_ERROR_SMS_CODE_FAILED      = 'sms_code_failed'; // 校验码失效
    const MOBILE_VALIDATE_ERROR_SMS_CODE_UNKNOW      = 'sms_code_unknow'; // 校验码未知
    const MOBILE_VALIDATE_ERROR_UNKNOW_ERROR         = 'unknow_error';
    const MOBILE_VALIDATE_ERROR_NO_USER              = 'no_user';

    const MOBILE_BIND_SUCCESS                        = 'success';
    const MOBILE_BIND_ERROR_HASE_BINED_THAT_PHONE    = 'binded';
    const MOBILE_BIND_ERROR_PHONE_BINDED_TIMES_LIMIT = 'phone_binded_times_out';
    const MOBILE_BIND_ERROR_NO_MOBILE                = 'no_mobile';
    const MOBILE_BIND_ERROR_PHONE_BINDED_OTHER_USER  = 'bind_other_user';
    const MOBILE_BIND_ERROR_UNBINED_BEFORE           = 'unbined';
    const MOBILE_BIND_ERROR_USER_BINDED_OTHER_PHONE  = 'bind_other_phone';
    const MOBILE_BIND_ERROR_UNKNOW_ERROR             = 'unknow_error';
    const MOBILE_BIND_ERROR_OLD_PHONE_CHECK_FAILED   = 'old_phone_check_failed';
    const MOBILE_BIND_ERROR_NO_USER                  = 'no_user';

    /**
     * 获取发送的错误消息.
     *
     * @param string|array $errorCode 错误码.
     * @param array|null   $extraData 额外信息.
     *
     * @return string           返回错误信息
     */
    public static function getSendErrorMsg($errorCode, $extraData = null)
    {
        $msg = '短信发送出现未知错误,请稍后再试';
        if (is_array($errorCode)) {
            if (isset($errorCode['error'])) {
                if (isset($errorCode['data'])) {
                    if (is_array($extraData)) {
                        $extraData = array_merge($errorCode['data'], $extraData);
                    } else {
                        $extraData = $errorCode['data'];
                    }
                }
                $errorCode = $errorCode['error'];
            }
        }
        switch ($errorCode) {
            case self::MOBILE_SEND_ERROR_FORMAT_ERROR:
                $msg = '请使用正确的11位手机号码';
                break;
            case self::MOBILE_SEND_ERROR_UNSAFE:
                $msg = '您使用的手机号码可能存在安全隐患，为保证账户余额安全，请您联系聚美客服400-123-8888，以完成绑定手机操作。小美为给您带来的不便表示抱歉。';
                break;
            case self::MOBILE_SEND_ERROR_SEND_COUNT_OUT:
                $msg = '您的手机号发送验证码次数过多。';
                break;
            case self::MOBILE_SEND_ERROR_CHECK_TIME_FAILED:
                $msg = '请不要频繁点击发送按钮';
                break;
            case self::MOBILE_SEND_ERROR_NO_USER:
                $msg = '用户不存在';
                break;
            case self::MOBILE_SEND_ERROR_NO_MOBILE:
                $msg = '请先绑定手机再进行发送';
                break;
            case self::MOBILE_SEND_SUCCESS:
                $markedPhone = self::getMarkedPhoneNumberById($extraData['phone']);
                $msg = '校验码短信已发送至'.$markedPhone.'请注意查收';
                break;
            case self::MOBILE_SEND_ERROR_MOBILE_USED_BY_OTHER:
                $msg = '您使用的手机号码已被其他用户使用,请更换手机号再试';
                break;
            case self::MOBILE_SEND_ERROR_MOBILE_USED_BY_OTHER:
                $msg = '您使用的手机号码已被其他用户使用,请更换手机号再试';
                break;
        }
        return $msg;
    }

    /**
     * 获取短信验证失败的错误消息.
     *
     * @param string $errorCode 错误代码.
     *
     * @return string            错误信息
     */
    public static function getSmsValidateErrorMsg($errorCode)
    {
        if (is_array($errorCode)) {
            if (isset($errorCode['error'])) {
                if (isset($errorCode['data'])) {

                }
                $errorCode = $errorCode['error'];
            }
        }
        $msg = '手机验证码验证发生未知错误,请稍后再试';
        switch ($errorCode) {
            case self::MOBILE_VALIDATE_ERROR_NO_USER:
                $msg = '用户不存在';
                break;
            case self::MOBILE_VALIDATE_ERROR_SMS_CODE_FAILED:
                $msg = '验证码已失效请重新获取';
                break;
            case self::MOBILE_VALIDATE_SUCCESS:
                $msg = '验证码校验成功';
                break;
            case self::MOBILE_VALIDATE_ERROR_ERROR_SMS_CODE:
                $msg = '手机验证码错误';
                break;
            case self::MOBILE_VALIDATE_ERROR_SMS_CODE_UNKNOW:
                $msg = '请先获取手机验证码';
                break;
            case self::MOBILE_VALIDATE_ERROR_UNKNOW_ERROR:

        }
        return $msg;
    }

    /**
     * 获取绑定的错误信息.
     *
     * @param string     $errorCode 返回的错误码.
     * @param array|null $extraData 额外信息.
     *
     * @return string            错误信息
     */
    public static function getBindErrorMsg($errorCode, $extraData = null)
    {
        if (is_array($errorCode)) {
            if (isset($errorCode['error'])) {
                if (isset($errorCode['data'])) {
                    if (is_array($extraData)) {
                        $extraData = array_merge($errorCode['data'], $extraData);
                    } else {
                        $extraData = $errorCode['data'];
                    }
                }
                $errorCode = $errorCode['error'];
            }
        }
        if ($errorCode === false) {
            return '绑定手机号失败，请稍后再试';
        } else if ($errorCode === true) {
            return '绑定成功';
        }
        $msg = '绑定时发生未知错误,请稍后再试';
        switch ($errorCode) {
            case self::MOBILE_BIND_SUCCESS:
                $msg = '绑定成功';
                break;
            case self::MOBILE_BIND_ERROR_NO_MOBILE:
                $msg = '待绑定手机号不存在！';
                break;
            case self::MOBILE_BIND_ERROR_NO_USER:
                $msg = '用户不存在';
                break;
            case self::MOBILE_VALIDATE_ERROR_ERROR_SMS_CODE:
            case self::MOBILE_VALIDATE_ERROR_SMS_CODE_FAILED:
            case self::MOBILE_VALIDATE_ERROR_SMS_CODE_UNKNOW:
                $msg = self::getSmsValidateErrorMsg($errorCode);
                break;
            case self::MOBILE_BIND_ERROR_UNBINED_BEFORE:
                $msg = '请先绑定手机号';
                break;
            case self::MOBILE_BIND_ERROR_PHONE_BINDED_OTHER_USER:
                $msg = '该手机号已绑定在其他用户,继续绑定该用户的绑定信息将丢失,是否强制绑定?';
                break;
            case self::MOBILE_BIND_ERROR_HASE_BINED_THAT_PHONE:
                $msg = '您已绑定该手机号，无需重复绑定';
                break;
            case self::MOBILE_BIND_ERROR_USER_BINDED_OTHER_PHONE:
                $msg = '您已绑定其他手机号，是否重新绑定到当前手机号?';
                break;
            case self::MOBILE_BIND_ERROR_PHONE_BINDED_TIMES_LIMIT:
                $msg = '一个手机号每天只能绑定'.Module_User::MOBILE_BIND_LIMIT_COUNT.'次';
                break;
            case self::MOBILE_BIND_ERROR_OLD_PHONE_CHECK_FAILED:
                $msg = '您尚未使用之前绑定的手机进行短信验证或验证已失效,请重新验证.';
                break;
            case self::MOBILE_BIND_ERROR_UNKNOW_ERROR:
            default:
                break;
        }
        return $msg;
    }

    /**
     * 获取masked之后的电话号码字符串.
     *
     * @param string $mobile 待处理手机号.
     *
     * @return [type]         [description]
     */
    public static function getMarkedPhoneNumber($mobile)
    {
        if ( $mobile && is_string($mobile)) {
            if ( strlen($mobile) > 7 ) {
                $maskedBindedMobile = substr_replace($mobile, "****", 3, 4);
            } else {
                $maskedBindedMobile = $mobile;
            }
            return $maskedBindedMobile;
        }
        return $mobile;
    }

    /**
     * 取座机的加掩码.
     *
     * @param string $phone 座机.
     *
     * @return string
     */
    public static function getMarkedTelephoneNumber($phone)
    {
        if ( $phone && is_string($phone)) {
            $phoneItems = explode('-', $phone);
            if ( isset($phoneItems[1]) && strlen($phoneItems[1]) >= 7 ) {
                $phoneItems[1] = substr_replace($phoneItems[1], "***", 3, 3);
            }
            return implode('-', $phoneItems);
        }
        return $phone;
    }

    /**
     * 根据id 取mark后的电话号码.
     *
     * @param integer $mobileId 手机id.
     *
     * @return string.
     */
    public static function getMarkedPhoneNumberById($mobileId)
    {
        if (empty($mobileId)) {
            return false;
        }
        return Helper_TrusteeshipData::getDecryptPhoneNumber($mobileId);
    }

    /**
     * 根据手机号生成id.
     *
     * @param integer $mobile 手机.
     *
     * @return string.
     */
    public static function getIdByMobile($mobile)
    {
        if (empty($mobile)) {
            return false;
        }
        return Helper_TrusteeshipData::encryptData($mobile);
    }

    /**
     * 分隔纯数字手机号.
     *
     * @param string $hp 手机号.
     *
     * @return string
     */
    public static function dealHpStr($hp)
    {
        if (strpos($hp, '-')) {
            return $hp;
        }
        $arr = str_split($hp);
        $res = '';
        foreach ($arr as $k => $v) {
            $res .= in_array($k, array(2,6)) ? $v . '-' : $v;
        }
        return $res;
    }

}
