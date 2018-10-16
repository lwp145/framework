<?php
/**
 * This class provides a series of methods to handle requests from clients.
 */
class Request
{
    /**
     * 获取不同级别的域名.
     *
     * @param int  $level 域名级别。  0 顶级域名(如.com, .cn) 1 一级域名(如: cctv.cn中的cctv), 2 二级域名(如: channel-1.cctv.cn中的channel-1)...一次类推
     * @param bool $lastLevel 是否直接返回最有一级的域名,如果为true，则忽略$level参数.
     *
     * @return bool|string 当获取不到指定级别域名时返回false.
     */
    public static function getDomainLevel($level, $lastLevel = false)
    {
        if (!isset($_SERVER['SERVER_NAME'])) {
            return 'localhost';
        }

        $domainPortions = explode('.', $_SERVER['SERVER_NAME']);
        $levelLength = count($domainPortions);
        if ($lastLevel) {
            $index = 0;
        } else {
            $index = $levelLength - $level - 1;
            if ($index < 0) {
                trigger_error('Required domain level ' . $level . ' is not found in domain ' . $_SERVER['SERVER_NAME']);
                return false;
            }
        }

        return $domainPortions[$index];
    }
}