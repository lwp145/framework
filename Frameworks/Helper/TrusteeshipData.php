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
class Helper_TrusteeshipData
{

    /**
     * 返回调用.
     *
     * @return object
     */
    public static function initClient()
    {
        // \PHPClient\Text::instance('TrusteeshipData');
        $client = \PHPClient\Text::inst('TrusteeshipData');
        $client->setClass('TrusteeshipData');
        return $client;
    }

    /**
     * 根据手机号 取加密id.
     *
     * @param string $data 手机号.
     *
     * @return integer|boolean
     */
    public static function getTrusteeshippDataId($data)
    {
        $trusteeShipData = self::initClient()->isExist($data);
        if ($trusteeShipData['error'] == 0) {
            return $trusteeShipData['msg'];
        }
        return false;
    }

    /**
     * 加密数据.
     *
     * @param string $data 待加密字符串.
     *
     * @return integer|boolean 返回加密串ID.
     */
    public static function encryptData($data)
    {
        $trusteeShipData = self::initClient()->encryptData($data);
        if ($trusteeShipData['error'] == 0) {
            return $trusteeShipData['msg'];
        }
        return false;
    }

    /**
     * 批量加密数据.
     *
     * @param array $dataArr 待加密字符串数组.
     *
     * @return array|boolean 返回ID数组.
     */
    public static function encryptDataBatch(array $dataArr)
    {
        $trusteeShipData = self::initClient()->encryptDataBatch($dataArr);
        if ($trusteeShipData['error'] == 0) {
            return $trusteeShipData['msg'];
        }
        return false;
    }

    /**
     * 取配置文件.
     *
     * @return [type] [description]
     */
    public static function getConfig()
    {
        $decryptConfig = JMRegistry::get('decryptConfig');
        return $decryptConfig;
    }

    /**
     * 获取解密数据.
     *
     * @param integer $dataId 加密Id.
     *
     * @return string
     * @throws RpcBusinessException If 解密错误.
     */
    public static function getDecryptData($dataId)
    {
        $decryptConfig = self::getConfig();
        $timestamp = time();
        $appId = $decryptConfig['appId'];
        $appKey = $decryptConfig['appKey'];
        $token = md5($appId . $appKey . $dataId . $timestamp);

        $result = self::initClient()->getDecryptData($dataId, $appId, $timestamp, $token);

        if ($result['error'] == 0) {
            return $result['msg'];
        }
        return false;
    }

    /**
     * 批量获取解密数据.
     *
     * @param array $dataIdArr 加密Id数组.
     *
     * @return array
     * @throws RpcBusinessException If 解密错误.
     */
    public static function getDecryptDataBatch(array $dataIdArr)
    {
        $decryptConfig = self::getConfig();
        $timestamp = time();
        $appId = $decryptConfig['appId'];
        $appKey = $decryptConfig['appKey'];
        $token = md5($appId . $appKey . implode(',', $dataIdArr) . $timestamp);
        $result = self::initClient()->getDecryptDataBatch($dataIdArr, $appId, $timestamp, $token);
        if ($result['error'] == 0) {
            return $result['msg'];
        }
        return false;
    }

    /**
     * 解密用户相关信息.
     *
     * @param array $data        用户地址加密ID信息,eg:array('address' => '23123', 'hp' => '3231', array('address')).
     * @param array $decryptKeys 待解密字段.
     *
     * @return array 用户地址相关解密信息.
     * @throws Exception 解密异常.
     */
    public static function getDecryptDataWithKey(array $data, array $decryptKeys = array())
    {
        $result = array();
        if (empty($data)) {
            return $result;
        }

        $decryptData = array();
        foreach ($data as $key => $val) {
            if (!in_array($key, $decryptKeys) || empty($val) || !is_numeric($val)) {
                $result[$key] = $val;
                continue;
            }
            $decryptData[$key] = $val;
        }

        $tempRes = self::getDecryptDataBatch($decryptData);

        if ($tempRes !== false) {
            if (!empty($decryptData)) {
                foreach ($decryptData as $key => $val) {
                    $result[$key] = $tempRes[$val];
                }
            }
        } else {
            throw new Exception("decrypt failed.");
        }

        return $result;
    }

    /**
     * 获取解密数据.
     *
     * @param integer $dataId 加密Id.
     *
     * @return string
     * @throws RpcBusinessException If 解密错误.
     */
    public static function getDecryptPhoneNumber($dataId)
    {
        if ($dataId) {
            $result = self::initClient()->getDecryptPhoneNumber($dataId);

            if ($result['error'] == 0) {
                return $result['msg'];
            }
        }
        return false;
    }

}
