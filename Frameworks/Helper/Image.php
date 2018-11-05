<?php
/**
 * 图片路径相关方法.
 *
 * 代码来自原 jumei_web 的 modules/JumeiHelper/CJumeiHelper.inc 文件.
 *
 * @author Xiangheng Li <xianghengl@jumei.com>
 */

/**
 * 图片相关操作助手类.
 */
class Helper_Image
{

    /**
     * 获取配置参数.
     *
     * @return Utility_KeyValue
     */
    public static function config()
    {
        static $config = null;
        if ($config === null) {
            $config = new Utility_KeyValue(JMRegistry::get('CDNBaseURL'));
        }
        return $config;
    }

    /**
     * POP 相关的地址.
     *
     * @param integer $id POP ID.
     *
     * @return string
     */
    public static function popBaseUrl($id)
    {
        return self::config()->get('pop') . $id;
    }

    /**
     * 产品相关的地址.
     *
     * @param integer $product_id 产品 ID.
     *
     * @return string
     */
    public static function productBaseUrl($product_id)
    {
        $path_array = str_split(substr('000000000' . $product_id, -9), 3);  // 假设总数不超过 10 亿.
        $path_array = array_slice($path_array, 0, 2);
        if (self::config()->get('product_image.enable')) {
            $url  = self::config()->get('product_image.subdomain_prefix') .
                ($product_id % self::config()->get('product_image.count')) .
                self::config()->get('product_image.subdomain_suffix') .
                join('/', $path_array) . '/' . $product_id;
        } else {
            $url = self::config()->get('product') . join('/', $path_array) . '/' . $product_id;
        }

        return $url;
    }

    /**
     * 产品图片.
     *
     * @param integer $product_id 产品 ID.
     * @param string  $type       图片类型, 可选参数: main, sidebar, thumb.
     *
     * @return string
     */
    public static function productUrl($product_id, $type = 'main')
    {
        return self::productBaseUrl($product_id) . '-' . $type . '.jpg';
    }

    /**
     * 指定尺寸的产品图片.
     *
     * @param integer $product_id 产品 ID.
     * @param integer $size       图片尺寸, 可选参数: 960(400), 400, 350, 320, 200, 160, 100, 60.
     *
     * @return string
     */
    public static function productUrlBySize($product_id, $size)
    {
        $height = ($size == 960) ? 400 : $size;
        return sprintf('%s_std/%d_%d_%d.jpg', self::productBaseUrl($product_id), $product_id, $size, $height);
    }

    /**
     * Deal 相关路径.
     *
     * @param string $hash_id Deal has ID.
     *
     * @return string
     */
    public static function dealBaseUrl($hash_id)
    {
        $hash_id = strval($hash_id);
        $fake_hash_id = $hash_id;
        if (strlen($fake_hash_id) < 4) {
            $delta = 4 - strlen($fake_hash_id);
            $fake_hash_id = $fake_hash_id . str_repeat('0', $delta);
        }
        $path_array = array(
            substr($fake_hash_id, 0, 1) . substr($fake_hash_id, -1),
            substr($fake_hash_id, 1, 1) . substr($fake_hash_id, -2, 1),
            $hash_id,
        );
        if (self::config()->get('deal_product.enable')) {
            $url = self::config()->get('deal_product_image.subdomain_prefix') .
                (sprintf('%u', crc32($hash_id)) % self::config()->get('deal_product_image.count')) .
                self::config()->get('deal_product_image.subdomain_suffix') .
                implode('/', $path_array) . '/' . $hash_id;
        } else {
            $url = self::config()->get('deal_product') . implode('/', $path_array) . '/' . $hash_id;
        }
        return $url;
    }

    /**
     * Deal 图片地址.
     *
     * @param string $hash_id Deal hash ID.
     * @param string $type    图片类型, 可选参数: main, focus_main, grid, luxury, sidedeal,
     *                        edm, mid, related, thumb, partner, partner_new, applist.
     *
     * @return string
     */
    public static function dealUrl($hash_id, $type = 'main')
    {
        return self::dealBaseUrl($hash_id) . '-' . $type . '.jpg';
    }

    /**
     * Deal 切换图片地址.
     *
     * @param string  $hash_id Deal hash ID.
     * @param integer $index   切换序号, 可选参数：1, 2.
     *
     * @return string
     */
    public static function dealSwitchUrl($hash_id, $index = 1)
    {
        return self::dealBaseUrl($hash_id) . '-225_300_' . $index . '.jpg';
    }

    /**
     * Deal 焦点图片地址.
     *
     * @param string  $hash_id Deal hash ID.
     * @param integer $id      图片序号.
     * @param boolean $thumb   是否为缩略图.
     *
     * @return boolean
     */
    public static function dealFocusUrl($hash_id, $id, $thumb = false)
    {
        return sprintf('%s-focus_main%s_%d.jpg', self::dealBaseUrl($hash_id), ($thumb ? '_thumb' : ''), $id);
    }

}
