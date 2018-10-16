<?php
/**
 * 版本控制接口.
 *
 * @author xyh<yinghuix@jumei.com>
 */

namespace Modules;

use Registry;

/**
 * 版本控制接口.
 */
class VersionCtrl extends Base
{

    /**
     * 实例.
     *
     * @return static
     */
    public static function Instance()
    {
        return parent::Instance();
    }

    /**
     * 平台.
     *
     * @return mixed
     */
    public static function platform()
    {
        return Registry::get('platform');
    }

    /**
     * 版本.
     *
     * @return mixed
     */
    public static function client()
    {
        return Registry::get('client_v');
    }

    /**
     * 应用类型.
     *
     * @return mixed
     */
    public static function applet()
    {
        return JMRegistry::get('applet');
    }

    /**
     * 卡片返回数据格式 iPhone/Android新结构， iPad／h5 老结构.
     *
     * @return boolean
     */
    public static function isSupportNewAdCardStruct()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '2.66') ||
            (self::platform() == 'android' && self::client() >= '2.45') ||
            (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp') ||
            (self::platform() == 'ipad') ||
            (self::platform() == 'toutiao')
        ) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * Iphone 小于4.4的版本.
     *
     * @return boolean
     */
    public static function unSupportClientIPhone44()
    {
        if (
            (JMRegistry::get('platform') == 'iphone' && JMRegistry::get('client_v') < '4.45') ||
            (JMRegistry::get('platform') == 'android' && JMRegistry::get('client_v') < '4.4')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否是H5.
     *
     * @return boolean
     */
    public static function supportRequestFromWap()
    {
        if (JMRegistry::get('platform') == 'wap') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否是小程序.
     *
     * @return boolean
     */
    public static function supportRequestFromWxApp()
    {
        if (JMRegistry::get('platform') == 'jm+') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持新的热搜词.
     *
     * @return boolean
     * @see    https://echo.int.jumei.com/issues/138509
     */
    public static function isSupportNewSearchHotWords()
    {
        return self::isGtOrEqualVersion48();
    }

    /**
     * 版本是否大于等于 4.8 .
     *
     * @return boolean
     */
    public static function isGtOrEqualVersion48()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '4.8') ||
            (self::platform() == 'android' && self::client() >= '4.8') ||
            (self::platform() == 'jm+') ||
            (self::platform() == 'ipad') ||
            (self::platform() == 'quickapp') ||
            (self::platform() == 'toutiao')
        ) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 版本是否大于等于 4.9 .
     *
     * @return boolean
     */
    public static function isGtOrEqualVersion49()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '4.9') ||
            (self::platform() == 'android' && self::client() >= '4.9')
        ) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 版本是否大于等于 4.91 .
     *
     * @return boolean
     */
    public static function isGtOrEqualVersion491()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '4.91') ||
            (self::platform() == 'android' && self::client() >= '4.91')
        ) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 版本是否大于等于 510 .
     *
     * @return boolean
     */
    public static function isGtOrEqualVersion510()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '5.102') ||
            (self::platform() == 'android' && self::client() >= '5.103')
        ) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 下拉刷新图版本大于等于 5.0 .
     *
     * @return boolean
     */
    public static function isLoadingBgFromHome50()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '5.0') || (self::platform() == 'android' && self::client() >= '5.0')) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 下拉刷新图版本大于等于 5.0 .
     *
     * @return boolean
     */
    public static function isCall2FH5UrlFromHome50()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() == '5.0')) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 解决IOS Crash.
     *
     * @return boolean
     */
    public static function isForCrashFromIOS()
    {
        $flag = false;
        if (self::platform() == 'iphone' && in_array(JMRegistry::get('platform_v'), array('11.0')) && in_array(JMRegistry::get('device_model'), array('iPhone10,1', 'iPhone10,4', 'iPhone10,2', 'iPhone10,5'))) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 是否支持购物车扩展信息.
     *
     * @return boolean
     */
    public static function isSupportCartInfoExtends()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '4.6') ||
            (self::platform() == 'android' && self::client() >= '4.6') ||
            self::platform() == 'all'
        ) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 是否是 App.
     *
     * @return boolean
     */
    public static function isApp()
    {
        $flag = false;

        if (self::platform() === 'iphone' || self::platform() === 'android') {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 专柜标签.
     *
     * @return boolean
     */
    public static function isServiceCounters54()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '5.400') ||
            (self::platform() == 'android' && self::client() >= '5.400')
        ) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 版本.
     *
     * @return boolean
     */
    public static function isServiceCounters56()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '5.600') ||
            (self::platform() == 'android' && self::client() >= '5.600')
        ) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 版本.
     *
     * @return boolean
     */
    public static function isServiceCounters58()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '5.800') ||
            (self::platform() == 'android' && self::client() >= '5.800')
        ) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 是否是 快应用.
     *
     * @return boolean
     */
    public static function isQuickApp()
    {
        $flag = false;

        if (self::applet() === 'quickapp') {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 是否是客户端（包括小程序).
     *
     * @return boolean
     */
    public static function isClient()
    {
        $flag = false;
        if (self::platform() === 'iphone' || self::platform() === 'android' || self::platform() === 'jm+' || self::platform() === 'quickapp' || self::platform() === 'toutiao') {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 版本.
     *
     * @return boolean
     */
    public static function isService585()
    {
        $flag = false;
        if ((self::platform() == 'iphone' && self::client() >= '5.850') ||
            (self::platform() == 'android' && self::client() >= '5.850')
        ) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 详情页公告统一的版本控制.
     *
     * @param array $config NoticeConfig.
     *
     * @return boolean
     */
    public static function isSupportNoticeClient($config)
    {
        if (isset($config) && !empty($config) && is_array($config)) {
            if ((isset($config['iphone']) && self::platform() == 'iphone' && self::client() >= $config['iphone']) ||
                (isset($config['android']) && self::platform() == 'android' && self::client() >= $config['android']) ||
                (isset($config['wx']) && self::platform() == 'jm+' && $config['wx'] == '1') ||
                (isset($config['wx']) && self::platform() == 'quickapp' && $config['wx'] == '1') ||
                (isset($config['wap']) && self::platform() == 'wap' && $config['wap'] == '1') ||
                (isset($config['ipad']) && self::platform() == 'ipad' && $config['ipad'] == '1') ||
                (isset($config['ipad']) && self::platform() == 'toutiao' && $config['wx'] == '1') ||
                self::platform() == 'all'
            ) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 详情页价格区间.
     *
     * @return boolean
     */
    public static function isSupportCounterClientV59()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '5.900') ||
            (self::platform() == 'android' && self::client() >= '5.900')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 千人千面增加帖子类型.
     *
     * @return boolean
     */
    public static function isHaveTiezi()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.0') ||
            (self::platform() == 'android' && self::client() >= '6.0')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 专柜购品牌接口.
     *
     * @return boolean
     */
    public static function isNewShoppeBrandList()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.1') ||
            (self::platform() == 'android' && self::client() >= '6.1')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 千人千面帖子增加立即结算.
     *
     * @return boolean
     */
    public static function isHomeTieziDirectpay()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.1') ||
            (self::platform() == 'android' && self::client() >= '6.1')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 搜索列表样式ab版本控制.
     *
     * @return boolean
     */
    public static function isSearchListShowStyle()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.1') ||
            (self::platform() == 'android' && self::client() >= '6.1')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 搜索聚合专场版本控制.
     *
     * @return boolean
     */
    public static function isSearchAggAct53()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '5.300') ||
            (self::platform() == 'android' && self::client() >= '5.300')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持新的搜索筛选器.
     *
     * @return boolean
     */
    public static function isSupportNewSearchFilter()
    {
        if ((self::platform() == 'iphone' ) || (self::platform() == 'android')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * D 4.7.
     *
     * @return boolean
     */
    public static function isSupportClientV47()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportOver388.
     *
     * @return boolean
     */
    public static function supportOver388()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportOver387.
     *
     * @return boolean
     */
    public static function supportOver387()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportOver59.
     *
     * @return boolean
     */
    public static function supportOver59()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持无果次优化.
     *
     * @return boolean
     */
    public static function isSupportForQueryChange52()
    {
        return ((in_array(self::platform(), array('iphone', 'android')))) ? true : false;
    }

    /**
     * D 5.9版本.
     *
     * @return boolean
     */
    public static function isSupportClientV59()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '5.900') ||
            (self::platform() == 'android' && self::client() >= '5.900')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * IsWap.
     *
     * @return boolean
     */
    public static function isWap()
    {
        if (self::platform() == 'wap') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportOver54.
     *
     * @return boolean
     */
    public static function supportOver54()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '5.4') ||
            (self::platform() == 'android' && self::client() >= '5.4')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 详情页请求默认专柜接口.
     *
     * @return boolean
     */
    public static function isSupportCounterClientV62h()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.2') ||
            (self::platform() == 'android' && self::client() >= '6.206')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 搜索分类版本控制.
     *
     * @return boolean
     */
    public static function isSupportSearchCategoryV62()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.221') ||
            (self::platform() == 'android' && self::client() >= '6.221')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 详情页专柜优化.
     *
     * @return boolean
     */
    public static function isSupportCounterClientV63()
    {
        if (
            (self::platform() == 'iphone' && self::client() <= '6.3') ||
            (self::platform() == 'android' && self::client() <= '6.3')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 首页：每日必看+今日团购整合.
     *
     * @return boolean
     */
    public static function support_HomePage_Over388()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all') ||
            (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportOver386.
     *
     * @return boolean
     */
    public static function supportOver386()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            self::platform() == 'wap' || (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportClientV340.
     *
     * @return boolean.
     */
    public static function supportClientV340()
    {
        if ((self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportClientV3870.
     *
     * @return boolean
     */
    public static function supportClientV3870()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 支持手机新样式.
     *
     * @return boolean
     */
    public static function supportNewStyle()
    {
        if ((self::platform() == 'android' ) || (self::platform() == 'iphone') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Support389ExceptIpad.
     *
     * @return boolean
     */
    public static function support389ExceptIpad()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持当deal抢光时间大于当前时间时隐藏抢光时间.
     *
     * @return boolean
     */
    public static function supportHideEndTime()
    {
        if (in_array(self::platform(), array('iphone', 'android', 'all'))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持列表页信息中直接返回促销信息.
     *
     * @return boolean
     */
    public static function supportListWithPromos()
    {
        if (
            (self::platform() == 'quickapp') ||
            (self::platform() == 'jm+') ||
            (self::platform() == 'ipad' && self::client() >= '2.0') ||
            (in_array(self::platform(), array('iphone', 'android', 'all')))
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持列表页显示预售.
     *
     * @return boolean
     */
    public static function supportListShowPresaleDeals()
    {
        if ((in_array(self::platform(), array('iphone', 'android', 'all')) && self::client() >= '3.1') ||
            (self::platform() == 'jm+') || (self::platform() == 'quickapp')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Support 4.0.
     *
     * @return boolean
     */
    public static function supportClientV40()
    {
        if (self::platform() == 'iphone' || self::platform() == 'android' ||
            self::platform() == 'all' || self::platform() == 'wap' ||
            self::platform() == 'jm+' || self::platform() == 'quickapp'
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 支持iphone 3.45 android 4.0（当时android将3.6版本延至4.0.
     *
     * @return boolean
     */
    public static function support345or346()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 支持ipad新样式.
     *
     * @return boolean
     */
    public static function supportIpadNewStyle()
    {
        if ((self::platform() == 'ipad' && self::client() >= '2.0')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Support3980.
     *
     * @return boolean
     */
    public static function support3980()
    {
        if (self::platform() == 'iphone'  || self::platform() == 'android'||
            self::platform() == 'jm+' || self::platform() == 'wap' ||
            self::platform() == 'quickapp'
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 问大家版本控制.
     *
     * @return boolean
     */
    public static function supportAskEveryoneForDetail63()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.3') ||
            (self::platform() == 'android' && self::client() >= '6.3')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 聚合详情页调整.
     *
     * @return boolean
     */
    public static function isSupportCounterClientVAPP59()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '5.9') ||
            (self::platform() == 'android' && self::client() >= '5.9')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 首页缓存.
     *
     * @return boolean
     */
    public static function isSupportCounterClientVAPP63()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.3') ||
            (self::platform() == 'android' && self::client() >= '6.3')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 专柜购6.4.
     *
     * @return boolean
     */
    public static function shoppeListVlientV64()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.4') ||
            (self::platform() == 'android' && self::client() >= '6.4')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 详情页分期购.
     *
     * @return boolean
     */
    public static function isSupportCounterClientV64()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.4') ||
            (self::platform() == 'android' && self::client() >= '6.4')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 新版首页 - 卡片 千人千面标题调整.
     *
     * @return boolean
     */
    public static function isNewHomePageV64()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.41') ||
            (self::platform() == 'android' && self::client() >= '6.41')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 详情页领券6.4.
     *
     * @return boolean
     */
    public static function detailConponlientV64()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '6.4') ||
            (self::platform() == 'android' && self::client() >= '6.4')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 搜索中标题.
     *
     * @return boolean
     */
    public static function isSupportMiddleNameV70()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.000') ||
            (self::platform() == 'android' && self::client() >= '7.000')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 千人千面增加帖子.
     *
     * @return boolean
     */
    public static function isHomeTieziV70()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.0') ||
            (self::platform() == 'android' && self::client() >= '7.0')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 聚合详情页分期优化.
     *
     * @return boolean
     */
    public static function isSupportCounterClientV63Fenqi()
    {
        if (
            (self::platform() == 'iphone' && self::client() > '6.3') ||
            (self::platform() == 'android' && self::client() > '6.3')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断isIpad.
     *
     * @return boolean
     */
    public static function isIpad()
    {
        if (self::platform() == 'ipad') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 小程序小于1.301.
     *
     * @return boolean
     */
    public static function wxAppLt1301()
    {
        if (
        (self::platform() == 'jm+' && self::client() < '1.301')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否是 app 并版本小于 4.4. .
     *
     * @return boolean
     */
    public static function isAppAndLt44()
    {
        if (
            (self::platform() == 'iphone' && self::client() < '4.4') ||
            (self::platform() == 'android' && self::client() < '4.4')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持列表页专柜自提标签.
     *
     * @return boolean
     */
    public static function isShowServiceCountersTag()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '5.200') ||
            (self::platform() == 'android' && self::client() >= '5.200')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否支持列表页组件化.
     *
     * @return boolean
     */
    public static function supportComponentForList()
    {
        if (
            (self::platform() == 'iphone') ||
            (self::platform() == 'android')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 兼容Android版本4.456以前BUG问题.
     *
     * @return boolean
     */
    public static function isNotSuportPromoCapsule()
    {
        if (self::platform() == 'android' && self::client() <= '4.456') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Support 4.1.
     *
     * @return boolean.
     */
    public static function supportClientV41()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '4.1') ||
            (self::platform() == 'android' && self::client() >= '4.1') ||
            (self::platform() == 'all') ||
            (self::platform() == 'wap') || (self::client() == 'jm+')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否是小程序.
     *
     * @return boolean.
     */
    public static function isWxMini()
    {
        if (self::platform() == 'jm+' || (self::platform() == 'quickapp') || (self::platform() == 'toutiao')) {
            return true;
        }
        return false;
    }

    /**
     * 详情页客服7.0.
     *
     * @return boolean
     */
    public static function isSupportBaClientV70()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.1') ||
            (self::platform() == 'android' && self::client() >= '7.1')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 搜索卖点文案.
     *
     * @return boolean
     */
    public static function isSupportSellingPointV71()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.1') ||
            (self::platform() == 'android' && self::client() >= '7.1')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 千人千面帖子.
     *
     * @return boolean
     */
    public static function isHomeTieziV71()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.1') ||
            (self::platform() == 'android' && self::client() >= '7.1')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 安卓特定版本特定渠道包显示特定帖子.
     *
     * @return boolean
     */
    public static function isAndroidTieziV71()
    {
        if (
        (self::platform() == 'android' && self::client() == '7.105')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Iphone 3.94以下店铺使用老参数.
     *
     * @return boolean
     */
    public static function supportClientIphoneLowerV394()
    {
        if (
            self::platform() == 'iphone' && self::client() < '3.94'
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * SupportClientV42.
     *
     * @return boolean
     */
    public static function supportClientV42()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '4.2') ||
            (self::platform() == 'android' && self::client() >= '4.2') ||
            (self::platform() == 'all') || (self::platform() == 'jm+') ||
            (self::platform() == 'quickapp')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * IsSupportClientV46.6.
     *
     * @return boolean
     */
    public static function isSupportClientV46()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '4.6') ||
            (self::platform() == 'android' && self::client() >= '4.6')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 搜索改版.
     *
     * @return boolean
     */
    public static function isSearchListV72()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.3') ||
            (self::platform() == 'android' && self::client() >= '7.3')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * IsSupportClientV73.
     *
     * @return boolean
     */
    public static function isSupportClientV73()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.3') ||
            (self::platform() == 'android' && self::client() >= '7.3')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 中国音乐公告牌.
     *
     * @return boolean
     */
    public static function isSupportSearchIdolHits()
    {
        if (
            (self::platform() == 'iphone' && self::client() >= '7.205') ||
            (self::platform() == 'android' && self::client() >= '7.206')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 部分渠道包需求.
     *
     * @return boolean
     */
    public static function isSupportClientV7301()
    {
        if (
            (self::platform() == 'iphone' && self::client() > '7.301') ||
            (self::platform() == 'android' && self::client() > '7.301')
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 关闭IOS开屏.
     *
     * @return boolean
     */
    public static function isSupportClientV7300ForIOS()
    {
        if (
        (self::platform() == 'iphone' && self::client() >= '7.300')
        ) {
            return true;
        } else {
            return false;
        }
    }

}
