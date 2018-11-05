<?php
/**
 * 获取站点信息.
 *
 * @author Peng Wang <pengw@jumei.com>
 * @date 2013-09-18
 * @version 0.2
 */

/**
 * 获取站点信息的类.
 */
class Helper_Site
{
    /**
     * 获取cookie中保存的站点信息，目前就这种方法最可靠.
     *
     * @return string|null
     */

    public static function getCurrentSite()
    {
        $siteConfig  = JMRegistry::get('SiteInfo');
        $siteVersion = $siteConfig['siteVersion'];
        return JMGetCookie($siteVersion) ? JMGetCookie($siteVersion) : null;
    }

    /**
     * 从配置文件中获取所有的站点.
     *
     * @return array
     */
    public static function getAllSites()
    {
        $siteConfig = JMRegistry::get('SiteInfo');
        return $siteConfig['siteInfo']['shippingMap'];
    }

    /**
     * 重置当前站点.
     *
     * @param string $site Site.
     *
     * @return null
     */
    public static function retrieveLocalSite($site)
    {
        $allSites = self::GetAllSites();
        return isset($allSites[$site]) ? $site : self::getCurrentSite();
    }

    /**
     * 根据仓库信息获取分站信息.
     *
     * @param string $shippingSystemId 仓库信息.
     *
     * @return string
     */
    public static function getSiteByShippingId($shippingSystemId)
    {
        $siteConfig = JMRegistry::get('SiteInfo');

        $result = 'www'; // by default for lottery, activity, coupon, 代发货...

        $shippingMap = $siteConfig['siteInfo']['shipping_map'];
        foreach ($shippingMap as $site => $warehouseList) {
            if (isset($warehouseList[$shippingSystemId])) {
                $result = $site;
                break;
            }
        }
        return $result;
    }

    /**
     * 获取平台信息.
     *
     * @return string
     */
    public static function getPlatFrom()
    {
        return JMGetCookie('platform','www');
    }

    /**
     * 获取refererSite.
     *
     * @return string.
     */
    public static function getRefererSite()
    {
        return JMGetCookie('referer_site', '');
    }

}
