<?php
/**
 * 图片处理.
 */

namespace Lib;

use Lib\Util as LUtil;
use MobLib\Resolution;

/**
 * 图片处理.
 * @package Lib
 */
class Image
{
    const IMG_SELF_WIDTH = '${_selfWidth}';
    const IMG_SELF_HEIGHT = '${_selfHeight}';
    public static $platform = '';
    protected $config;
    protected static $list;
    protected static $acaProductList = array(); // 防伪码商品.

    /**
     * Image constructor.
     *
     * @param array $config Config.
     *
     * @throws \Exception
     */
    public function __construct($config = null)
    {
        self::setPlatform(\Registry::get('platform'));
        if (empty($config)) {
            $this->config = LUtil::getApiConfig('ImageConf');
        } else {
            $this->config = $config;
        }
    }

    /**
     * 自动根据平台，位置自动压缩图片.
     *
     * @param string $imgTag   对应conf中的图片位置唯一标志。例如product_single,用于产品信息中single位置.
     * @param array  $options  参见下方示例.
     * @param string $platform Iphone/ipad/android..
     *
     * array(
     * 		'url' => 'http://demo.host/path/{$key1}/{$key2}/{$key3}_demo_'.self::IMG_SELF_WIDTH.'_'.self::IMG_SELF_HEIGHT.'.jpg', //图片地址
     * 		'rules' => array(
     * 			'key1' => 'value1',
     * 			'key2' => 'value2',
     * 			'key3' => 'value3',
     * 		),//图片地址中变量替换的原则
     * 		'custom' => array(
     * 			'640' => 'http://demo.host/path/special/url.jpg',//自定义某个分辨率下的地址形式（用于跟图片宽高无关的地址）
     * 		),
     *      'custom_compress_rate' => 0.5,
     * );.
     *
     * @return array: 图片列表.
     */
    public static function autoCompressImg($imgTag, $options, $platform = '')
    {
        if (empty($platform)) {
            $platform = self::$platform;
        }

        $imageConf = LUtil::getApiConfig('ImageConf');
        $resolutionSetting = $imageConf['resolutionSetting'];
        if (!empty($resolutionSetting[$platform])) {
            $platformSetting = $resolutionSetting[$platform];
        } else {
            $platformSetting = call_user_func_array('array_merge', $resolutionSetting);
        }

        $allCompressSetting = $imageConf['compressSetting'];
        $allCompressSetting = $allCompressSetting[$imgTag];

        if (!empty($allCompressSetting[$platform])) {
            $compressSetting = $allCompressSetting[$platform];
        }elseif (!empty($allCompressSetting['default'])) {
            $compressSetting = $allCompressSetting['default'];
        } else {
            $compressSetting = call_user_func_array('array_merge', $allCompressSetting);
        }

        // 合并临时配置
        if (!empty($options['custom_compress_rate'])) {
            $compressRate = $options['custom_compress_rate'];
        } else {
            $compressRate = 1;
        }

        $imgList = array();
        if (!empty($platformSetting) && !empty($compressSetting)) {
            $search = array();
            $replace = array();
            if (!empty($options['rules'])) {
                foreach ($options['rules'] as $k => $v) {
                    $search[] = '{$' . $k . '}';
                    $replace[] = $v;
                }
            }

            foreach ($platformSetting as $resolutionWidth) {
                $tmpSetting = !empty($compressSetting[$resolutionWidth]) ? $compressSetting[$resolutionWidth] : (!empty($compressSetting['default']) ? $compressSetting['default'] : 1);
                $tmpSetting = $tmpSetting * $compressRate;
                if (!empty($tmpSetting)) {
                    $tmpUrl = '';
                    if (!empty($options['custom'][$resolutionWidth])) {
                        $tmpUrl = $options['custom'][$resolutionWidth];
                    } elseif (!empty($options['url'])) {
                        $tmpUrl = $options['url'];
                    }
                    if (!empty($tmpUrl)) {
                        $imgList[$resolutionWidth] = self::getCompressImgUrl($tmpUrl, $resolutionWidth * (float)$tmpSetting);
                    }
                }
            }
        }
        return self::getImageByResolution($imgList);
    }

    /**
     * 压缩图片.
     *
     * @param string $url   图片地址.
     * @param string $width 分辨率宽度.
     *
     * @return string
     */
    public static function getCompressImgUrl($url, $width)
    {
        // 分辨率
        $width = (int)$width;

        // 获取裁图服务商
        $service = self::getConfigService();

        // 解析地址
        $urlAry = parse_url($url);

        if ($service == 'qcloud') {
            // 腾讯 http://mp2.jmstatic.com/mobile/ads/9676/9676_1536_2048_001-ipad2048.jpg?imageView2/2/w/400
            $urlAry['host'] = 'mp2.jmstatic.com';
            $urlAry['query'] = !empty($urlAry['query']) ? 'imageView2/2/w/' . $width . '&' . $urlAry['query'] : 'imageView2/2/w/' . $width;
        } elseif ($service == 'qiniu_mp5') {
            // 七牛 http://mp5.jmstatic.com/jmstore/image/000/002/2034_std/58478c17a4209_2048_847.jpg?imageView2/2/w/640/q/80
            $urlAry['host'] = 'mp5.jmstatic.com';
            $urlAry['query'] = !empty($urlAry['query']) ? $urlAry['query'] . '&imageView2/2/w/' . $width . '/q/90' : 'imageView2/2/w/' . $width .'/q/90';
        } elseif ($service == 'qiniu_mp6') {
            // 七牛 http://mp6.jmstatic.com/jmstore/image/000/002/2034_std/58478c17a4209_2048_847.jpg?imageView2/2/w/640/q/80
            $urlAry['host'] = 'mp6.jmstatic.com';
            $urlAry['query'] = !empty($urlAry['query']) ? $urlAry['query'] . '&imageView2/2/w/' . $width . '/q/90' : 'imageView2/2/w/' . $width .'/q/90';
        } elseif ($service == 'ksyun_mp4') {
            // 金山云 http://mp7.jmstatic.com/jmstore/image/000/002/2034_std/58478c17a4209_2048_847.jpg@base@tag=imgScale&w=200?12345
            $urlAry['host'] = 'mp4.jmstatic.com';
            $urlAry['path'] = $urlAry['path'] . '@base@tag=imgScale&w=' . $width . '&q=90';
        } elseif ($service == 'ksyun_mp7') {
            // 金山云 http://mp7.jmstatic.com/jmstore/image/000/002/2034_std/58478c17a4209_2048_847.jpg@base@tag=imgScale&w=200?12345
            $urlAry['host'] = 'mp7.jmstatic.com';
            $urlAry['path'] = $urlAry['path'] . '@base@tag=imgScale&w=' . $width . '&q=90';
        } else {
            // 聚美图床 http://mp1.jmstatic.com/q_mini,c_zoom,w_320,f_webp/mobile/new_ads/item_9_1520_622-ipad2048.jpg?t=1403517172
            $urlAry['host'] = 'mp4.jmstatic.com';
            $urlAry['path'] = $urlAry['path'] . '@base@tag=imgScale&w=' . $width . '&q=80';
        }

        return http_build_url($urlAry);
    }

    /**
     * To get image by resolution.
     *
     * @param array $imgList Image list.
     *
     * @return array
     */
    public static function getImageByResolution($imgList)
    {
        if (!self::isStatic()) {
            // 获取分辨率
            $width  = self::getResolution('width');
            if (!empty($width)) {
                $min    = false;
                $key    = '';
                foreach ($imgList as $tmpWidth => $tmpData) {
                    $tmp = abs($width - $tmpWidth);
                    if ($min !== false && $tmp <= $min) {
                        $min = $tmp;
                        $key = $tmpWidth;
                    } elseif ($min === false) {
                        $min = $tmp;
                        $key = $tmpWidth;
                    } else {
                        continue;
                    }
                }

                $imgList = array($key => $imgList[$key]);
            }
        }

        return $imgList;
    }

    /**
     * 返回分辨率相关信息.
     *
     * @param string $key The key of Resolution.
     *
     * @return array
     */
    public static function getResolution($key = '')
    {
        $resolution = array();
        $cookie     = LUtil::getCookie('resolution');
        if (!empty($cookie)) {
            $tmpResolution = explode("*", $cookie);
            $resolution     = array(
                'resolution' => $cookie,
                'width' => $tmpResolution[0],
                'height' => $tmpResolution[1],
            );
        }

        return !empty($key) ? (!empty($resolution[$key]) ? $resolution[$key] : null) : $resolution;
    }

    /**
     * 获取Dove配置裁图服务商(CDN服务商->回源裁图服务商[存储聚美原图-下次不再请求聚美原图]->聚美原图，域名是指裁剪服务商).
     *
     * @return string
     */
    public static function getConfigService()
    {
        $imageConf = LUtil::getApiConfig('ImageConf');
        $service = $imageConf['autoCompressService'];

        $percent = LUtil::getConfig('common', 'splitCompressService');

        if ($percent['open'] == true) {
            $service = self::randomBaseTimestamp($percent['percent']);
        }

        return $service;
    }

    /**
     * 比例分配.
     *
     * @param array $data 比例数组.
     *
     * @return string
     */
    public static function randomBaseTimestamp($data = array())
    {
        uasort(
            $data,
            function ($a, $b) {
                return rand(-1, 1);
            }
        );

        $time = rand(1, 1000);

        $total = array_sum($data);

        $mod = $time % $total;

        $result = '';

        foreach ($data as $k => $v) {
            $result = $k;
            $mod -= $v;
            if ($mod < 0) {
                break;
            }
        }

        return (string)$result;
    }

    /**
     * 测试.
     *
     * @return string
     */
    public static function getTest()
    {
        return 'Lib';
    }

    /**
     *  Check request method.
     *
     *  @return string
     */
    public static function isStatic()
    {
        $isStatic = $_SERVER['REQUEST_METHOD'] == 'GET' ? true : false;

        return $isStatic;
    }

    /**
     * 设置平台.
     *
     * @param string $platform 平台.
     *
     * @return boolean
     */
    public static function setPlatform($platform)
    {
        self::$platform = $platform;
        return true;
    }

    /**
     * 获取商品产地国旗.
     *
     * @param string $countries Countries.
     * @param string $type      Type .
     *
     * @return string.
     */
    public static function getAreaFlagImagePath($countries, $type = 'mobile')
    {
        $area_code = sprintf("%09d", $countries);
        $dir1 = substr($countries, 0, 3);
        $dir2 = substr($countries, 3, 3);
        $path = 'area/' . $dir1 . '/' . $dir2 . '/' . substr($countries, -3);

        $path = $type == 'normal' ? $path . '.jpg' : "_{$type}.jpg";
        $config = LUtil::getApiConfig('ImageConf');

        return self::autoDealImg('area_flag_icon', array('url' => $config['base'] . $path,));
    }

    /**
     * 自动根据平台，位置计算出适合的不同分辨率对应的图片url.
     *
     * @param string $imgTag   对应conf中的图片位置唯一标志。例如product_single,用于产品信息中single位置.
     * @param array  $options  参见下方示例.
     * @param string $platform Iphone/ipad/android.
     *
     * @return array: 图片列表
     */
    public static function autoDealImg($imgTag, $options, $platform = '')
    {
        if (empty($platform)) {
            $platform = self::$platform;
        }
        $config = LUtil::getApiConfig('ImageConf');
        $resolutionSetting = $config['resolutionSetting'];
        if (!empty($resolutionSetting[$platform])) {
            $platformSetting = $resolutionSetting[$platform];
        } else {
            $platformSetting = call_user_func_array("array_merge", $resolutionSetting);
        }
        $positionSetting = $config['images.positionSetting.' . $imgTag];
        $imgList = array();
        if (!empty($resolutionSetting) && !empty($positionSetting)) {
            $search = array();
            $replace = array();
            if (!empty($options['rules'])) {
                foreach ($options['rules'] as $k => $v) {
                    $search[] = '{$' . $k . '}';
                    $replace[] = $v;
                }
            }

            foreach ($platformSetting as $resolutionWidth) {
                $tmpSeting = !empty($positionSetting[$resolutionWidth]) ? $positionSetting[$resolutionWidth] : (!empty($positionSetting['default']) ? $positionSetting['default'] : '');
                if (!empty($tmpSeting)) {
                    list($_selfWidth, $_selfHeight) = explode("*", $tmpSeting);
                    if (!empty($options['custom'][$resolutionWidth])) {
                        $imgList[$resolutionWidth] = $options['custom'][$resolutionWidth];
                    } elseif (!empty($options['url'])) {
                        $imgList[$resolutionWidth] = str_replace(array_merge($search, array(self::IMG_SELF_WIDTH, self::IMG_SELF_HEIGHT)), array_merge($replace, array($_selfWidth, $_selfHeight)), $options['url']);
                    }
                }
            }
        }
        return self::getImageByResolution($imgList);
    }
}