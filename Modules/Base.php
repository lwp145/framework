<?php
/**
 * Module基类.
 *
 * @author song xuemei<xuemeis@jumei.com>
 */

namespace Modules;

use JMRegistry;
use Lib\JMException;
use Lib\Util as LUtil;
use Lib\Cache;
use \Modules\Product as MProduct;
use \Lib\Cache as LCache;
use Modules\Detail as MDetail;

/**
 * Module基类.
 */
abstract class Base
{
    // 实例集
    protected static $instances;
    protected static $phase;

    const COLOR_JM_RED = '#FE4070';
    const COLOR_BLACK = '#333333';
    const COLOR_GRAY = '#999999';
    const COLOR_GRAY_BLACK = '#666666';

    const TAG_ID_PHONE_COST = '32';
    const TAG_ID_BILL_COST = '21';
    const TAG_ID_HUAFEI_COST = '86';
    const TAG_ID_WEBFLOW_COST = '90';

    /**
     * 实例.
     *
     * @return static
     */
    public static function Instance()
    {
        $class = get_called_class();

        if (empty(self::$instances[$class])) {
            self::$instances[$class] = new $class;
            self::$phase = LUtil::getConfig('app', 'phase');
        }

        return self::$instances[$class];
    }

    /**
     * 频繁调用检测check whether users call the event frequently.
     *
     * @param string $eventTag EventTag.
     *
     * @return mixed
     *
     * @throws JMException JMException.
     */
    public static function checkEventFrequencyByUid($eventTag)
    {
        $uid = (int)JMRegistry::get('uid');
        $key    = 'check_frequency_for_'.$eventTag.'_'.$uid;
        $Config = LUtil::getConfig('common', 'FrequencyCtrl');
        // 没有相对应的key给一个默认时间2s
        $ttl    = isset($Config[$eventTag]) ? $Config[$eventTag] : 2;
        $check  = Cache::memGet($key);
        if (empty($check)) {
            Cache::memSet($key, $uid, $ttl);
        }
        return empty($check) ? true : false;
    }

    /**
     * 各个列表页支持凑团deal, 包含首页以及各个频道单品团,搜索,店铺,店铺内搜索.
     *
     * @param array  &$item_lists 单个deal.
     * @param array  $yqtHashIds  Field-判断是否为一起团deal的字段.
     * @param string $field       SellingForms.
     * @param string $card_type   卡片或列表类型.
     *
     * @return void
     */
    public static function getYqtItemInfo(&$item_lists = array(), $yqtHashIds = array(), $field = 'selling_forms', $card_type = '')
    {
        $isHomePage = false; // 表示是否为首页列表

        if (!empty($yqtHashIds)) {
            $yqtInfo = LUtil::call('TextRpc_Mob_Activity::getYqtInfoByHashIds', array($yqtHashIds));
            foreach ($item_lists as &$tmpYqtItem) {
                if (self::isYqtDeal($tmpYqtItem, $field)) {
                    if (!isset($tmpYqtItem['item_id']) || !isset($yqtInfo[$tmpYqtItem['item_id']])) {
                        continue;
                    }

                    // 这块逻辑涉及到所有的列表页(包括首页,搜索,专场,店铺),修改的话慎重
                    // 首页列表凑团商品左下角和右下角的信息用的是single_price_desc(左) 和 product_desc(右), 其他列表页用的是product_desc(左), time_desc(右)
                    $tmpYqtItem['name_tag']['pre_or_presale'] = '[' . $yqtInfo[$tmpYqtItem['item_id']]['num'] .'人团]';
                    $tmpYqtItem['name_tag']['authorization'] = $yqtInfo[$tmpYqtItem['item_id']]['is_new_tuan'] == 1 ? '邀新团' : '';
                    $fakerBuyerNumber = (string)$yqtInfo[$tmpYqtItem['item_id']]['buyer_number'];

                    $tmpYqtItem['time_desc'] = isset($yqtInfo[$tmpYqtItem['item_id']]['buyer_number']) && $yqtInfo[$tmpYqtItem['item_id']]['buyer_number'] >= 2 ? self::formatPersonNum($fakerBuyerNumber) . '人已参与' : '';
                    $tmpYqtItem['name_tag']['notice'] = '邀请'.(intval($yqtInfo[$tmpYqtItem['item_id']]['num']) - 1) . '位好友即可成团';

                    if (in_array($card_type, array('call_pagelist', 'call_deal', 'input_deal', 'call_activity_pagelist_deal','call_qrqm_dealactlist','call_dealact_mixed'))) {
                        $isHomePage = true;
                        $tmpYqtItem['name_tag']['pre_or_presale_text'] = $yqtInfo[$tmpYqtItem['item_id']]['num'] .'人团';
                        $tmpYqtItem['name_tag']['pre_or_presale'] = '';
                        $tmpYqtItem['product_desc'] = $tmpYqtItem['time_desc']; // 首页凑团商品好用的这个字段
                        if (isset($tmpYqtItem['status']) && ($tmpYqtItem['status'] == 'soldout' || $tmpYqtItem['status'] == 'expired')) {
                            $tmpYqtItem['product_desc'] = '';
                        }
                        $tmpYqtItem['yqt_buyer_number'] = isset($yqtInfo[$tmpYqtItem['item_id']]['buyer_number']) ? $yqtInfo[$tmpYqtItem['item_id']]['buyer_number'] : '0';
                    }

                    $single_buy_price = self::formatPrice($yqtInfo[$tmpYqtItem['item_id']]['jm_price']);

                    if ($tmpYqtItem['market_price'] != 0) {
                        if (self::shouldHideMarketPrice($tmpYqtItem['market_price'], $single_buy_price)) {
                            if (JMRegistry::get('platform') == 'android') {
                                $tmpYqtItem['market_price'] = '';
                            } elseif (JMRegistry::get('platform') == 'iphone') {
                                $tmpYqtItem['market_price'] = '-1';
                            } else {
                                $tmpYqtItem['market_price'] = '-1';
                            }

                        }
                    }

                    if (self::shouldHideMarketPrice($yqtInfo[$tmpYqtItem['item_id']]['jm_price'], $tmpYqtItem['jumei_price'])) {
                        $single_buy_price = '';
                    }

                    $tmpYqtItem['single_price_desc'] = '';

                    if ($isHomePage) {
                        if (!empty($single_buy_price)) {
                            if ($isHomePage) {
                                $tmpYqtItem['single_price_desc'] = '(单买价：¥' . $single_buy_price . ')';
                            }
                            if ($card_type == 'call_dealact_mixed') {
                                if ($isHomePage) {
                                    $tmpYqtItem['single_price_desc'] = '单买价¥' . $single_buy_price;
                                }
                            }
                        }
                        $tmpYqtItem['time_desc'] = '';
                    } else {
                        // 组件化冗余数据
                        $tmpYqtItem['yqt_single_price_desc'] = !empty($single_buy_price) ? '单买价 ¥'.$single_buy_price : '';
                        $tmpYqtItem['yqt_buyer_number_desc'] = $tmpYqtItem['time_desc'];

                        // 这块逻辑千万不要改动, 会影响除了首页之外的所有列表页
                        if (empty($single_buy_price)) {
                            $tmpYqtItem['product_desc'] = $tmpYqtItem['time_desc'];
                            $tmpYqtItem['time_desc'] = '';
                        } else {
                            $tmpYqtItem['product_desc'] = '单买价¥' . $single_buy_price;
                        }
                    }

                    // 凑团deal状态(客户端展示信息只需要两种状态,如果更改需要通知客户端)
                    $yqtDealStatus = 'onsell';

                    if (time() > $tmpYqtItem['end_time']) {
                        $yqtDealStatus = 'expired';
                    }
                    if ((time() > $tmpYqtItem['start_time']) && (time() < $tmpYqtItem['end_time']) && $tmpYqtItem['status'] == '2') {
                        $yqtDealStatus = 'expired';
                    }

                    $tmpYqtItem['status'] = $yqtDealStatus;
                }


            }
        }

    }

    /**
     * 新首页组件化.
     *
     * @param array  $data Data.
     * @param string $page Page.
     *
     * @return array
     */
    public static function componentForFlowHomelList($data, $page = '1')
    {
        $result = array();
        $format = self::getComponentFontColorForMixed();
        $key_count = 0;
        // 开关
        $is_show_country_flag_single = LUtil::getConfig('common','is_show_country_flag_single');
        foreach ($data as $key => $item) {
            // icon 部分 1-1
            // Top三图片 1-1
            $key_count = $key_count + 1;
            if ($key_count < 4 && $page == 1) {
                $tmp['icons'][] = array(
                    'position' => 'top_left',
                    'type' => 'image',
                    'img' => array(
                        '1200' => 'http://mp5.jmstatic.com/mobile/api/icon/top_plus_multi.png',
                    ),
                );
            }

            // 专场 暂时保持不变
            if ($item['type'] == 'jmstore') {
                $tmp['type'] = 'jmstore_multi';
                // 专场id
                $tmp['itemid'] = $item['itemid'];
                // symbol
                $tmp['label'] = $item['label'];
                // 详情链接
                $tmp['scheme'] = $item['url'];
                // 专场图片3-5
                $tmp['image_url_set'] = $item['image_url_set'];

                if (isset($item['live']) && !empty($item['live'])) {
                    // 直播头像 3-1
                    $tmp['live']['mark1'] = array(
                        'img' => array(
                            '1200' => isset($item['live']['avatar_small']) ? $item['live']['avatar_small'] : $item['live']['default_img'],
                        ),
                    );
                    // 直播昵称 3-2
                    $tmp['live']['mark2'] = array(
                        'desc' => isset($item['live']['nickname']) ? $item['live']['nickname'] : $item['live']['default_title'],
                        'font_color' => $format['live']['mark2']['font_color'],
                        'font_size' => $format['live']['mark2']['font_size'],
                    );
                    // 直播等级 3-3
                    $tmp['live']['mark3'] = array(
                        'img' => isset($item['live']['vipLogo']) ? $item['live']['vipLogo'] : '',
                    );
                    // 直播icon 3-4
                    $tmp['live']['mark4'] = array(
                        'img' => array(
                            '1200' => 'http://mp5.jmstatic.com/mobile/api/icon/living_plus_multi.png?100',
                        ),
                    );
                }

            } elseif ($item['type'] == 'image_multi') {
                $tmp = $item;
            } else {
                // 1-0
                $tmp['type'] = 'product_multi';
                // 1-0
                $tmp['info'] = array(
                    'type' => $item['type'],
                    'item_id' => $item['item_id'],
                    'status' => $item['status'],
                );

                // icon 部分 1-1、1-7
                // 国旗图片 1-5
                if (isset($item['countries_icon_mixed']) && !empty($item['countries_icon_mixed']) && $is_show_country_flag_single) {
                    $tmp['icons'][] = array(
                        'position' => 'top_right',
                        'type' => 'image',
                        'img' => $item['countries_icon_mixed'],
                    );
                }
                // 左下直播中ICON 1-7
                if (!empty($item['is_live_now']) && isset($item['is_live_now']) && $item['is_live_now'] == '1') {
                    $tmp['icons'][] = array(
                        'position' => 'bottom_left',
                        'type' => 'image',
                        'img' => array(
                            '1200' => 'http://mp5.jmstatic.com/mobile/api/icon/living_plus_multi.png',
                        ),
                    );
                }

                // 图片部分 1-6
                if (isset($item['image_url_set']['dx_image']['url'])) {
                    $tmp['img']['dx_image'] = $item['image_url_set']['dx_image']['url'];
                } elseif (isset($item['image_url_set']['single']['url'])) {
                    $tmp['img']['single'] = $item['image_url_set']['single']['url'];
                }

                // 标题部分
                // 1-2
                if (!empty($item['discount']) && $item['discount'] != '-1' && round($item['discount']) > 0 && round($item['discount']) < 10) {
                    $tmp['title'][]  = array(
                        'desc' => round($item['discount']).'折',
                        'type' => 'header',
                        'font_color' => $format['title']['header']['font_color'],
                        'font_size' => $format['title']['header']['font_size'],
                    );
                }
                // 1-3
                $tmp['title'][] = array(
                    'desc' => $item['short_name'],
                    'type' => 'main',
                    'font_color' => $format['title']['main']['font_color'],
                    'font_size' => $format['title']['main']['font_size'],
                );
                // 1-4 商品库新加的字段home_sale_name
                if (!empty($item['home_sale_name'])) {
                    $tmp['title'][]  = array(
                        'desc' => $item['home_sale_name'],
                        'type' => 'bottom',
                        'font_color' => $format['title']['bottom']['font_color'],
                        'font_size' => $format['title']['bottom']['font_size'],
                    );
                } elseif (!empty($item['qrshare_product_name'])) {
                    $tmp['title'][]  = array(
                        'desc' => $item['qrshare_product_name'],
                        'type' => 'bottom',
                        'font_color' => $format['title']['bottom']['font_color'],
                        'font_size' => $format['title']['bottom']['font_size'],
                    );
                }

                $sellparams = isset($item['add_url_sellparams']) ? $item['add_url_sellparams'] : '';
                $sellparams = MProduct::encodeVerticalLine($sellparams);

                // 价格区域 1-8、1-9、2-8、2-9、2-14、2-15
                // 凑团单买价
                if ( $item['selling_forms'] == 'yqt') {
                    // 2-8及其后面"拼团价"
                    $tmp['price']['price_mark0'] = array(
                        'price' => array(
                            'desc' => $item['jumei_price'],
                            'font_color' => $format['price']['jumei_price']['price']['font_color'],
                            'font_size' => $format['price']['jumei_price']['price']['font_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $format['price']['jumei_price']['unit']['font_color'],
                            'font_size' => $format['price']['jumei_price']['unit']['font_size'],
                        ),
                        'icons' => array(
                            'desc' => '拼团价',
                            'font_color' => $format['price']['jumei_price']['icons']['font_color'],
                            'font_size' => $format['price']['jumei_price']['icons']['font_size'],
                        ),
                        'ui_type' => '0',
                    );

                    // 2-9
                    if (!empty($item['market_price']) && $item['market_price'] != '-1') {
                        $tmp['price']['price_mark1'] = array(
                            'desc' => '￥' . $item['market_price'],
                            'font_color' => $format['price']['mark1']['font_color'],
                            'font_size' => $format['price']['mark1']['font_size'],
                            'ui_type' => '1',
                        );
                    }
                    // 2-14 2-15
                    if (isset($item['single_price_desc']) && !empty($item['single_price_desc'])) {
                        // 2-14
                        $tmp['price']['price_mark2'] = array(
                            'desc' => $item['single_price_desc'],
                            'font_color' => $format['price']['mark2']['font_color'],
                            'font_size' => $format['price']['mark2']['font_size'],
                            'ui_type' => '0',
                        );
                    }
                    if ( isset($item['yqt_scheme']) && !empty($item['yqt_scheme'])) {
                        // 2-15
                        $tmp['price']['price_mark3'] = array(
                            'desc' => '不拼团直接买>',
                            'font_color' => $format['price']['mark3']['font_color'],
                            'font_size' => $format['price']['mark3']['font_size'],
                            'scheme' => $item['yqt_scheme'],
                            'ui_type' => '3',
                        );
                    }

                } elseif ( $item['selling_forms'] == 'presale' ) {
                    $tmp['price']['price_mark0'] = array(
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $format['price']['jumei_price']['unit']['font_color'],
                            'font_size' => $format['price']['jumei_price']['unit']['font_size'],
                        ),
                        'price' => array(
                            'desc' => $item['presale_price'],
                            'font_color' => $format['price']['jumei_price']['price']['font_color'],
                            'font_size' => $format['price']['jumei_price']['price']['font_size'],
                        ),
                        'icons' => array(
                            'desc' => '订金',
                            'font_color' => $format['price']['jumei_price']['icons']['font_color'],
                            'font_size' => $format['price']['jumei_price']['icons']['font_size'],
                        ),
                        'ui_type' => '0',
                    );

                    if (!empty($item['jumei_price']) && $item['jumei_price'] != '-1') {
                        $tmp['price']['price_mark1'] = array(
                            'desc' => '总价￥' . $item['jumei_price'],
                            'font_color' => $format['price']['mark1']['font_color'],
                            'font_size' => $format['price']['mark1']['font_size'],
                            'ui_type' => '0',
                        );
                    }

                    if (!empty($item['market_price']) && $item['market_price'] != '-1') {
                        $tmp['price']['price_mark2'] = array(
                            'desc' => '￥' .$item['market_price'],
                            'font_color' => $format['price']['mark2']['font_color'],
                            'font_size' => $format['price']['mark2']['font_size'],
                            'ui_type' => '1',
                        );
                    }

                } else {
                    // 1-8、2-8
                    $tmp['price']['jumei_price'] = array(
                        'price' => array(
                            'desc' => $item['jumei_price'],
                            'font_color' => $format['price']['jumei_price']['price']['font_color'],
                            'font_size' => $format['price']['jumei_price']['price']['font_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $format['price']['jumei_price']['unit']['font_color'],
                            'font_size' => $format['price']['jumei_price']['unit']['font_size'],
                        ),
                        'ui_type' => '0',
                    );
                    // 1-9
                    if (!empty($item['market_price']) && $item['market_price'] != '-1') {
                        $tmp['price']['market_price'] = array(
                            'price' => array(
                                'desc' => $item['market_price'],
                                'font_color' => $format['price']['market_price']['price']['font_color'],
                                'font_size' => $format['price']['market_price']['price']['font_size'],
                            ),
                            'unit' => array(
                                'desc' => '¥',
                                'font_color' => $format['price']['market_price']['unit']['font_color'],
                                'font_size' => $format['price']['market_price']['unit']['font_size'],
                            ),
                            'ui_type' => '1',
                        );
                    }
                }

                // 促销 1-10  promo
                if ($item['selling_forms'] != 'yqt') {
                    if (!empty($item['name_tag']['pre_or_presale_text'])) {
                        $tmp['promo'][] = array(
                            'desc' => $item['name_tag']['pre_or_presale_text'],
                            'type' => 'cycle',
                            'font_color' => $format['promo']['cycle']['font_color'],
                            'font_size' => $format['promo']['cycle']['font_size']
                        );
                    }

                    if (!empty($item['name_tag']['notice'])) {
                        $tmp['promo'][] = array(
                            'desc' => $item['name_tag']['notice'],
                            'type' => 'desc',
                            'font_color' => $format['promo']['desc']['font_color'],
                            'font_size' => $format['promo']['desc']['font_size'],
                        );
                    }
                }

                // 购买人数||参团人数 1-12、2-12
                if ($item['selling_forms'] == 'yqt') {
                    if (!empty($item['yqt_buyer_number']) && $item['yqt_buyer_number'] >= 10) {
                        $tmp['tips'][] = array(
                            'position' => 'mark1',
                            'desc' => self::formatNum($item['yqt_buyer_number']),
                            'font_color' => $format['tips']['mark1']['font_color'],
                            'font_size' => $format['tips']['mark1']['font_size'],
                        );
                        $tmp['tips'][] = array(
                            'position' => 'mark2',
                            'desc' => '人正在火拼',
                            'font_color' => $format['tips']['mark2']['font_color'],
                            'font_size' => $format['tips']['mark2']['font_size'],
                        );
                    }
                } elseif ($item['selling_forms'] == 'presale') {
                    $tmp['tips'][] = array(
                        'position' => 'mark1',
                        'desc' => self::formatNum($item['buyer_number']),
                        'font_color' => $format['tips']['mark1']['font_color'],
                        'font_size' => $format['tips']['mark1']['font_size'],
                    );
                    $tmp['tips'][] = array(
                        'position' => 'mark2',
                        'desc' => '人已预订',
                        'font_color' => $format['tips']['mark2']['font_color'],
                        'font_size' => $format['tips']['mark2']['font_size'],
                    );
                } else {
                    if (!empty($item['buyer_number']) && $item['buyer_number'] >= 10) {
                        $tmp['tips'][] = array(
                            'position' => 'mark1',
                            'desc' => self::formatNum($item['buyer_number']),
                            'font_color' => $format['tips']['mark1']['font_color'],
                            'font_size' => $format['tips']['mark1']['font_size'],
                        );
                        $tmp['tips'][] = array(
                            'position' => 'mark2',
                            'desc' => '人已购买',
                            'font_color' => $format['tips']['mark2']['font_color'],
                            'font_size' => $format['tips']['mark2']['font_size'],
                        );
                    }
                }
                // Todo 线上口碑tag56，rd431
                if (!empty($item['koubei_desc'])) {
                    $tmp['tips'][] = array(
                        'position' => 'mark3',
                        'desc' => self::formatPersonNum($item['koubei_desc']),
                        'font_color' => $format['tips']['mark3']['font_color'],
                        'font_size' => $format['tips']['mark3']['font_size'],
                    );
                    $tmp['tips'][] = array(
                        'position' => 'mark4',
                        'desc' => '条评论',
                        'desc_ret' => '收起评价',
                        'font_color' => $format['tips']['mark4']['font_color'],
                        'font_size' => $format['tips']['mark4']['font_size'],
                    );
                } else {
                    $tmp['tips'][] = array(
                        'position' => 'mark4',
                        'desc' => '暂无评论',
                        'desc_ret' => '',
                        'font_color' => $format['tips']['mark4']['font_color'],
                        'font_size' => $format['tips']['mark4']['font_size'],
                    );
                }

                // 购物车&&拼团按钮 1-11、2-11
                if ($item['selling_forms'] != 'redemption' && isset($item['show_purchase_button']) && $item['show_purchase_button'] == 1) {
                    // 按钮type处理
                    $tmp['right_icon']['type'] = 'add_cart_plus'; // 默认给大的图
                    if (isset($item['status']) && $item['status'] == 'wish') {
                        $tmp['right_icon']['type'] = 'add_wish_plus';
                    }

                    // 拼接scheme
                    $add_scheme = 'jumeimall://page/add-cart?item_id='.$item['item_id'].'&type='.$item['type'];

                    // 预售
                    if (isset($item['selling_forms']) && $item['selling_forms'] == 'presale') {
                        $add_scheme .= '&is_presell=1';
                    } else {
                        $add_scheme .= '&is_presell=0';
                    }
                    // 直接结算
                    if (isset($item['settling_accounts_forms']) && $item['settling_accounts_forms'] == 'direct_pay') {
                        $add_scheme .= '&is_directpay=1';
                    } else {
                        $add_scheme .= '&is_directpay=0';
                    }
                    // 心愿单
                    if (isset($item['status']) && $item['status'] == 'wish' && !empty($item['start_time'])) {
                        $add_scheme .= '&start_time='.$item['start_time'];
                    }
                    // 商品状态
                    if (isset($item['status']) && in_array($item['status'], array('wish', 'onsell', 'soldout', 'expired', 'offshelf'))) {
                        $add_scheme .= '&pro_status='.$item['status'];
                    }

                    if (isset($item['shipping_system_id']) && in_array($item['shipping_system_id'], array('2754', '2967'))) {
                        $add_scheme .= '&is_dm=1';
                    } else {
                        $add_scheme .= '&is_dm=0';
                    }

                    if (isset($item['sellparams'])) {
                        $add_scheme .= $item['sellparams'];
                    } else {
                        $add_scheme .= '&sell_type=dealmixed&sell_label=home_main';

                        $add_scheme .= '&sellparams=' . $sellparams;
                    }

                    $tmp['right_icon']['scheme'] = $add_scheme;

                }

                // 凑团&&预售按钮
                if ($item['selling_forms'] == 'yqt') {
                    $tmp['right_icon'] = array(
                        'type' => 'yqt',
                        'img' => array('1200' => 'http://mp5.jmstatic.com/mobile/api/icon/yqt_plus_multi.png'),
                        'scheme' => $item['url_scheme'],
                    );
                }
                if ($item['selling_forms'] == 'presale') {
                    $tmp['right_icon'] = array(
                        'type' => 'presale',
                        'img' => array('1200' => 'http://mp5.jmstatic.com/mobile/api/icon/presale_plus_multi.png'),
                        'scheme' => $item['url_scheme'],
                    );
                }

                // 详情链接
                $tmp['scheme'] = $item['url_scheme'];

            }
            $result[$key] = $tmp;
            unset($tmp);
        }
        return $result;
    }

    /**
     * 帖子时间处理.
     *
     * @param string $time Time.
     *
     * @return string
     */
    public static function setTieziCreatTime($time)
    {
        $now_time = time();
        if ($now_time > $time) {
            $hour = floor(($now_time - $time) / 60 / 60);
            $minute = floor(($now_time - $time) / 60);
            if ($minute < 1 && $hour < 1) {
                $string = '刚刚';
            } elseif ($hour < 1) {
                $string = $minute . '分钟前';
            } elseif ( 1 <= $hour && $hour < 24) {
                $string = $hour . '小时前';
            } else {
                $string = date('m-d',$time);
            }
        } else {
            $string = date('m-d',$time);
        }
        return $string;
    }

    /**
     * 单品团类型组件化.
     *
     * @param array $data    Data.
     * @param array $special Special.
     * @param array $card    CardInfo.
     *
     * @return array
     */
    public static function componentForActDealList($data, $special = array(), $card = array())
    {

        $isShowSelfOrPop = LUtil::getConfig('common', 'isShowSelfOrPop');
        if (!empty($card) && count($card) != count($card,1)) {
            $card = $card[0];
        }
        $result = array();
        $actType = ''; // 专场大货架类型
        $actSymbol = ''; // 专场标识
        $cardTypes = isset($card['type']) ? $card['type'] : ''; // 卡片类型
        $cardID = isset($card['id']) ? $card['id'] : ''; // 卡片ID
        $cardLable = isset($card['page_label']) ? $card['page_label'] : ''; // 卡片ID
        if (!empty($special)) {
            if (isset($special['modelInfo']) && !empty($special['modelInfo'])) {
                $actType = isset($special['modelInfo']['model_type']) && $special['modelInfo']['model_type'] == 'bigShelf' ? $special['modelInfo']['model_type'] : '';
            }
            if (isset($special['activityInfo']) && !empty($special['activityInfo'])) {
                $actSymbol = isset($special['activityInfo']['symbol']) ? $special['activityInfo']['symbol'] : '';
            }
        }
        $format = self::getComponentFontColor();
        // 开关
        $is_show_country_flag_single = LUtil::getConfig('common','is_show_country_flag_single');
        foreach ($data as $key => $item) {
            // 专场 暂时保持不变
            if ($item['type'] == 'jmstore') {
                $result[] = $item;
            } elseif ($item['type'] == 'image') {
                $result[] = $item;
            } elseif ($item['type'] == 'tiezi') {
                $tmp = array();
                // 公共部分
                $tmp['item_id'] = $item['item_id'];
                $tmp['type'] = 'tiezi';
                $tmp['content_type'] = $item['post_type'];
                $tmp['detail_scheme'] = $item['detail_scheme'];
                $tmp['detail_comment_scheme'] = $item['detail_comment_scheme'];

                // 用户部分 用户头像\用户加V头像\用户昵称
                $tmp['user_info'] = array(
                    'uid' => $item['uid'],
                    'avatar'   => !empty($item['avatar']) ? $item['avatar'] : (object)array(),
                    'vip_logo' => !empty($item['vip_logo']) ? $item['vip_logo'] : (object)array(),
                    'auth_logo' => !empty($item['auth_logo']) ? $item['auth_logo'] : (object)array(),
                    'user_name' => isset($item['nickname']) ? $item['nickname'] : '',
                    'user_scheme' => 'jumeimall://page/socialotheruser?userid='.$item['uid'],
                );

                // 发布时间
                $tmp['creat_time'] = self:: setTieziCreatTime($item['create_time']);

                // 帖子内容
                $tmp['content'] = $item['description'];

                // 标签
                if (!empty($item['title_label'])) {
                    $tmp['title_label'] = array(
                        'id'         => isset($item['title_label']['id']) ? $item['title_label']['id'] : '',
                        'name'       => isset($item['title_label']['name']) ? $item['title_label']['name'] : '',
                        'scheme'     => isset($item['title_label']['scheme']) ? $item['title_label']['scheme'] : '',
                        'color'      => '#457AC8',
                    );
                }

                // 帖子视频
                $tmp['video'] = array(
                    'cover_url' => $item['major_pic'],
                    'width' => $item['video_w'],
                    'height' => $item['video_h'],
                    'scheme' => $item['detail_scheme'],
                    'video_url' => $item['video_url'],
                    'is_auto_play' => LUtil::getConfig('common','tiezi_video_auto_play'), // 1自动播放,0不自动播放
                );

                // 帖子图片 缩略图
                $tmp['small_img'] = isset($item['img_url']['small_img']) ? $item['img_url']['small_img'] : (object)array();
                // 帖子图片 大图
                $tmp['large_img'] = isset($item['img_url']['large_img']) ? $item['img_url']['large_img'] : (object)array();

                // 分享地址
                $tmp['share_info'] = $item['share_info'];

                // 帖子产品信息
                $tmp['product_list'] = $item['product_info'];

                $result[] = $tmp;
                unset($tmp);
            } else {
                // 是否自营
                if (isset($item['doc_type'])) {
                    // 搜索返回的有doc_type
                    if (in_array($item['doc_type'], array('mall_product', 'deal', 'global_mall'))) {
                        $item['is_proprietary'] = '1';
                    } elseif ($item['doc_type'] == 'global_deal' && isset($item['category']) && $item['category'] != 'global') {
                        $item['is_proprietary'] = '1';
                    } else {
                        $item['is_proprietary'] = '0';
                    }
                } else {
                    // 调用deal没有doc_type
                    if (in_array($item['type'], array('pop_deal','pop_mall', 'global_pop','global_pop_mall', 'jumei_pop'))) {
                        $item['is_proprietary'] = '0';
                    } else {
                        $item['is_proprietary'] = '1';
                    }
                }

                $tmp['type'] = 'product';
                $tmp['info'] = array(
                    'type' => $item['type'],
                    'item_id' => $item['item_id'],
                    'status' => $item['status'],
                );

                // icon 部分
                $tmp['icons'] = array();
                if (isset($item['has_short_video']) && $item['has_short_video'] == 1) {
                    // 中部视频ICON
                    $tmp['icons'][] = array(
                        'position' => 'middle',
                        'type' => 'image',
                        'single' => array(
                            'width' => '90',
                            'height' => '90',
                            'img' => array(
                                '1080' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png',
                                '1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png',
                            ),
                        ),
                    );
                }
                // #136455 #137930
                if (isset($item['countries_icon']) && !empty($item['countries_icon'])  && $is_show_country_flag_single) {
                    // if (isset($item['countries_icon']['1200'])) {
                    // $item['countries_icon']['1200'] = str_replace('single_ios', 'single_ios_p' , $item['countries_icon']['1200']);
                    // }
                    $tmp['icons'][] = array(
                        'position' => 'top_right',
                        'type' => 'image',
                        'single' => array(
                            'width' => $format['icons']['top_right']['wight'],
                            'height' => $format['icons']['top_right']['height'],
                            'img' => $item['countries_icon'],
                        ),
                    );
                }

                // 左下抢光ICON --高优先级于直播ICON
                if (in_array($item['status'],array('soldout','expired','offshelf'))) {
                    $tmp['icons'][] = array(
                        'position' => 'bottom_left',
                        'type' => 'image',
                        'single' => array(
                            'width' => '105',
                            'height' => '105',
                            'img' => array(
                                '1080' => 'http://p12.jmstatic.com/mcms/46d90dcc2684d743b684faacbce4cfc7.png',
                                '1200' => 'http://p12.jmstatic.com/mcms/46d90dcc2684d743b684faacbce4cfc7.png',
                            ),
                        ),
                    );
                } else {
                    // 直播判断
                    if (!empty($item['live']) && isset($item['live']->isLiveNow) && $item['live']->isLiveNow == '1') {
                        // 左下直播中ICON
                        $tmp['icons'][] = array(
                            'position' => 'bottom_left',
                            'type' => 'image',
                            'single' => array(
                                'width' => '168',
                                'height' => '60',
                                'img' => array(
                                    '1080' => 'http://p12.jmstatic.com/mcms/fa2897a606fdf0c224570ae105f818d5.png',
                                    '1200' => 'http://p12.jmstatic.com/mcms/fa2897a606fdf0c224570ae105f818d5.png',
                                ),
                            ),
                        );
                    }
                }

                // 图片部分
                if (isset($item['image_url_set']['dx_image']['url'])) {
                    $tmp['img']['dx_image'] = $item['image_url_set']['dx_image']['url'];
                } elseif (isset($item['image_url_set']['single']['url'])) {
                    $tmp['img']['single'] = $item['image_url_set']['single']['url'];
                }

                $tmp['tag_ids'] = isset($item['tag_ids']) ? $item['tag_ids'] : array(); // tag_id

                if (!empty($actType)) {
                    // 标题部分
                    if ($isShowSelfOrPop == 0) {
                        if (!empty($item['name_tag']['authorization'])) {
                            $tmp['title'][]  = array(
                                'desc' => $item['name_tag']['authorization'],
                                'type' => 'mark1',
                                'font_color' => $format['title']['mark1']['font_color']
                            );
                        }
                    }
                    if ($isShowSelfOrPop == 1) {
                        if (!$item['is_proprietary']) {
                            $tmp['title'][] = array(
                                'desc' => '非自营',
                                'type' => 'mark1',
                                'font_color' => $format['title']['mark1']['font_color'],
                            );
                        } else {
                            $tmp['title'][] = array(
                                'desc' => strpos($item['type'], 'global') === false ? '自营' : '海外自营',
                                'type' => 'mark1',
                                'font_color' => $format['title']['mark1']['font_color'],
                            );
                        }
                    }

                    if (!empty($item['name_tag']['pre_or_presale']) && !isset($item['name_tag']['notice'])) {
                        $tmp['title'][]  = array('desc' => $item['name_tag']['pre_or_presale'], 'type' => 'mark2', 'font_color' => $format['title']['mark2']['font_color']);
                    }
                    if (!empty($item['discount']) && $item['discount'] != '-1') {
                        $tmp['title'][]  = array(
                            'desc' => $item['discount'].'折/',
                            'type' => 'header',
                            'font_color' => $format['title']['header']['font_color']
                        );
                    }
                    $tmp['title'][] = array('desc' => $item['qrshare_product_name'], 'type' => 'main', 'font_color' => $format['title']['main']['font_color']);

                    // 凑团信息
                    if (isset($item['name_tag']['pre_or_presale_text']) && !empty($item['name_tag']['pre_or_presale_text'])) {
                        $tmp['promo'][] = array(
                            'desc' => $item['name_tag']['pre_or_presale_text'],
                            'type' => 'cycle',
                            'font_color' => $format['promo']['cycle']['font_color']
                        );
                    }
                    if (isset($item['name_tag']['notice']) && !empty($item['name_tag']['notice'])) {
                        $tmp['promo'][] = array (
                            'desc' => $item['name_tag']['notice'],
                            'type' => 'desc',
                            'font_color' => $format['promo']['desc']['font_color']
                        );
                    }
                    // 促销
                    if (!empty($item['item_promo_info'])) {
                        $tmp['promo'][] = array(
                            'desc' => isset($item['item_promo_info'][0]) ? $item['item_promo_info'][0] : '',
                            'type' => 'cycle',
                            'font_color' => $format['promo']['cycle']['font_color']
                        );
                        $tmp['promo'][] = array (
                            'desc' => isset($item['item_promo_info'][1]) ? $item['item_promo_info'][1][0] : '',
                            'type' => 'desc',
                            'font_color' => $format['promo']['desc']['font_color']
                        );
                    }
                    // 比平时省
                    if (isset($item['selling_forms']) && $item['selling_forms'] == 'presale' && isset($item['saved_amount']) && $item['saved_amount'] >= 5) {
                        $tmp['promo'][] = array (
                            'desc' => '比平时省 ¥'.(string)round($item['saved_amount'], 2),
                            'type' => 'desc',
                            'font_color' => $format['promo']['desc']['font_color']
                        );
                    }
                } else {
                    // 标题部分
                    if ($isShowSelfOrPop == 0) {
                        $is_show_zhuangui = \Modules\Product::Instance()->isServiceCounters($item['tag_ids']);
                        if ($is_show_zhuangui) {
                            $desc = "";

                            if ($is_show_zhuangui == 1) {
                                $desc = "专柜自提";
                            }
                            if ($is_show_zhuangui == 2) {
                                $desc = "专柜发货";
                            }
                            if ($is_show_zhuangui == 3) {
                                $desc = "专柜购";
                            }
                            $tmp['title'][] = array(
                                'desc' => $desc,
                                'type' => 'mark1',
                                'font_color' => $format['title']['mark1']['font_color']
                            );
                        } else {
                            if (!empty($item['name_tag']['authorization'])) {
                                $tmp['title'][] = array(
                                    'desc' => $item['name_tag']['authorization'],
                                    'type' => 'mark1',
                                    'font_color' => $format['title']['mark1']['font_color']
                                );
                            }
                        }
                    }
                    if ($isShowSelfOrPop == 1) {
                        if (!$item['is_proprietary']) {
                            $tmp['title'][] = array(
                                'desc' => '非自营',
                                'type' => 'mark1',
                                'font_color' => $format['title']['mark1']['font_color'],
                            );
                        } else {
                            $tmp['title'][] = array(
                                'desc' => strpos($item['type'], 'global') === false ? '自营' : '海外自营',
                                'type' => 'mark1',
                                'font_color' => $format['title']['mark1']['font_color'],
                            );
                        }
                    }
                    if (!empty($item['name_tag']['pre_or_presale'])) {
                        $tmp['title'][]  = array('desc' => $item['name_tag']['pre_or_presale'], 'type' => 'mark2', 'font_color' => $format['title']['mark2']['font_color']);
                    }
                    if (!empty($item['discount']) && $item['discount'] != '-1') {
                        $tmp['title'][]  = array(
                            'desc' => $item['discount'].'折/',
                            'type' => 'header',
                            'font_color' => $format['title']['header']['font_color']
                        );
                    }
                    $tmp['title'][]      = array('desc' => $item['qrshare_product_name'], 'type' => 'main', 'font_color' => $format['title']['main']['font_color']);

                    // 促销or凑团信息
                    if (!empty($item['name_tag']['pre_or_presale_text'])) {
                        $tmp['promo'][] = array(
                            'desc' => $item['name_tag']['pre_or_presale_text'],
                            'type' => 'cycle',
                            'font_color' => $format['promo']['cycle']['font_color']
                        );
                    }

                    if (!empty($item['name_tag']['notice'])) {
                        $tmp['promo'][] = array(
                            'desc' => $item['name_tag']['notice'],
                            'type' => 'desc',
                            'font_color' => $format['promo']['desc']['font_color']
                        );
                    }
                }

                // 价格区域
                $tmp['price']['jumei_price'] = array(
                    'price' => array(
                        "desc" => $item['jumei_price'],
                        "font_color" => $format['price']['jumei_price']['price']['font_color'],
                        "font_size" => $format['price']['jumei_price']['price']['font_size'],
                        "point_size" => $format['price']['jumei_price']['price']['point_size'],
                    ),
                    "unit" => array(
                        "desc" => '¥',
                        "font_color" => $format['price']['jumei_price']['unit']['font_color'],
                        "font_size" => $format['price']['jumei_price']['unit']['font_size'],
                    ),
                    "ui_type" => '0',
                );
                if ( $item['selling_forms'] == 'presale' ) {
                    $tmp['price']['jumei_price'] = array(
                        'price' => array(
                            "desc" => $item['presale_price'],
                            "font_color" => $format['price']['jumei_price']['price']['font_color'],
                            "font_size" => $format['price']['jumei_price']['price']['font_size'],
                            "point_size" => $format['price']['jumei_price']['price']['point_size'],
                        ),
                        "content" => array(
                            "desc" => '订金',
                            "font_color" => $format['price']['jumei_price']['content']['font_color'],
                            "font_size" => $format['price']['jumei_price']['content']['font_size'],
                        ),
                        "unit" => array(
                            "desc" => '¥',
                            "font_color" => $format['price']['jumei_price']['unit']['font_color'],
                            "font_size" => $format['price']['jumei_price']['unit']['font_size'],
                        ),
                        "ui_type" => '0',
                    );
                    $tmp['price']['market_price'] = array(
                        'price' => array(
                            "desc" => $item['jumei_price'],
                            "font_color" => $format['price']['jumei_price']['price']['font_color'],
                            "font_size" => $format['price']['jumei_price']['price']['font_size'],
                            "point_size" => $format['price']['jumei_price']['price']['point_size'],
                        ),
                        "content" => array(
                            "desc" => '总价',
                            "font_color" => $format['price']['jumei_price']['content']['font_color'],
                            "font_size" => $format['price']['jumei_price']['content']['font_size'],
                        ),
                        "unit" => array(
                            "desc" => '¥',
                            "font_color" => $format['price']['jumei_price']['unit']['font_color'],
                            "font_size" => $format['price']['jumei_price']['unit']['font_size'],
                        ),
                        "ui_type" => '0',
                    );

                } else {
                    if (!empty($item['market_price']) && $item['market_price'] != '-1') {
                        $tmp['price']['market_price'] = array(
                            'price' => array(
                                "desc" => $item['market_price'],
                                "font_color" => $format['price']['market_price']['price']['font_color'],
                                "font_size" => $format['price']['market_price']['price']['font_size'],
                                'point_size' => $format['price']['market_price']['price']['point_size']
                            ),
                            "unit" => array(
                                "desc" => '¥',
                                "font_color" => $format['price']['market_price']['unit']['font_color'],
                                "font_size" => $format['price']['market_price']['unit']['font_size'],
                            ),
                            "ui_type" => '1',
                        );
                    }
                }
                // new_qrqm兼容新版千人千面
                if (in_array($cardTypes, array('call_pagelist', 'call_deal', 'input_deal', 'call_activity_pagelist_deal','call_qrqm_dealactlist'))) {
                    // 凑团单买价
                    if ( $item['selling_forms'] == 'yqt' && isset($item['single_price_desc'])) {
                        $tmp['price']['right_text'] = array(
                            'desc' => $item['single_price_desc'],
                            'font_color' => $format['price']['right_text']['font_color'],
                            "font_size" => $format['price']['right_text']['font_size'],
                        );
                    }

                    // 左下文案
                    $tmp['tips'] = array();
                    // 购买人数||参团人数
                    if (!empty($item['product_desc'])) {
                        $tmp['tips']['single'][] = array(
                            'position' => 'left1',
                            'desc' => $item['product_desc'],
                            'font_color' => $format['tips']['single']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                } elseif (!empty($actType)) {
                    // 凑团单买价
                    if ($item['selling_forms'] == 'yqt' && isset($item['single_price_desc'])) {
                        $tmp['price']['right_text'] = array(
                            'desc' => $item['single_price_desc'],
                            'font_color' => $format['price']['right_text']['font_color'],
                            "font_size" => $format['price']['right_text']['font_size'],
                        );
                    }

                    // 左下文案
                    $tmp['tips'] = array();
                    // 购买人数||参团人数
                    if (!empty($item['product_desc'])) {
                        $tmp['tips']['single'][] = array(
                            'position' => 'left1',
                            'desc' => $item['product_desc'],
                            'font_color' => $format['tips']['single']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                } else {
                    // 凑团单买价
                    if ( $item['selling_forms'] == 'yqt' && isset($item['product_desc'])) {
                        $tmp['price']['right_text'] = array(
                            'desc' => strpos($item['product_desc'], '单买价') === false ? '' : $item['product_desc'], // 非指定卡片product_desc会返回人数
                            'font_color' => $format['price']['right_text']['font_color'],
                            "font_size" => $format['price']['right_text']['font_size'],
                        );
                    }

                    // 左下文案
                    $tmp['tips'] = array();
                    $buy_num = !empty($item['product_desc']) && strpos($item['product_desc'], '单买价') === false ? $item['product_desc'] : $item['time_desc'];
                    // 购买人数||参团人数
                    if (!empty($buy_num)) {
                        $tmp['tips']['single'][] = array(
                            'position' => 'left1',
                            'desc' => $buy_num,
                            'font_color' => $format['tips']['single']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                }

                // 右下按钮
                if (!in_array($item['selling_forms'],array('yqt','presale','redemption')) && ($item['type'] != 'red_envelope' || isset($item['show_category']) && $item['show_category'] != 'coupon') &&
                    ($item['type'] != 'second_kill' || (isset($item['show_category']) && $item['show_category'] != 'seckill')) &&
                    $item['status'] == 'onsell' && isset($item['show_purchase_button']) && $item['show_purchase_button'] == 1
                ) {

                    // 按钮type处理
                    $tmp['add_icon']['type'] = 'add_cart_plus'; // 默认给大的图
                    if (isset($item['status']) && $item['status'] == 'wish') {
                        $tmp['add_icon']['type'] = 'add_wish_plus';
                    }

                    // 新版千人千面数据源已处理好sellparams
                    if (isset($item['sellparams'])) {
                        $sellparams = !empty($item['sellparams']) ? $item['sellparams'] : '';
                    } elseif ($cardTypes == 'input_deal' && !empty($cardID)) {
                        $sellparams = isset($item['add_url_sellparams']) ? $item['add_url_sellparams'] : '';
                    } else {
                        $sellparams = !empty($card_id) ? 'card:'.$card_id : '';
                        $sellparams = MProduct::Instance()->addSellParams($sellparams);
                    }
                    // 添加购物车时使用 需要这个tag_id
                    $tag_ids = !empty($item['tag_ids']) && is_array($item['tag_ids']) ? '|tag:' . implode(',', $item['tag_ids']) : '';
                    $sellparams = !empty($sellparams) ? $sellparams.$tag_ids : trim($tag_ids,'|');
                    // 拼接scheme
                    $add_scheme = 'jumeimall://page/add-cart?item_id='.$item['item_id'].'&type='.$item['type'];

                    // 预售
                    if (isset($item['selling_forms']) && $item['selling_forms'] == 'presale') {
                        $add_scheme .= '&is_presell=1';
                    } else {
                        $add_scheme .= '&is_presell=0';
                    }
                    // 直接结算
                    if (isset($item['settling_accounts_forms']) && $item['settling_accounts_forms'] == 'direct_pay') {
                        $add_scheme .= '&is_directpay=1';
                    } else {
                        $add_scheme .= '&is_directpay=0';
                    }
                    // 心愿单
                    if (isset($item['status']) && $item['status'] == 'wish' && !empty($item['start_time'])) {
                        $add_scheme .= '&start_time='.$item['start_time'];
                    }
                    // 商品状态
                    if (isset($item['status']) && in_array($item['status'], array('wish', 'onsell', 'soldout', 'expired', 'offshelf'))) {
                        $add_scheme .= '&pro_status='.$item['status'];
                    }

                    if (!empty($actType)) {
                        $add_scheme .= '&sell_type=activity_native&sell_label='.$actSymbol;
                    } else {
                        $add_scheme .= '&sell_type=deallist&sell_label='.$cardLable;
                    }
                    if (isset($item['shipping_system_id']) && in_array($item['shipping_system_id'], array('2754', '2967'))) {
                        $add_scheme .= '&is_dm=1';
                    } else {
                        $add_scheme .= '&is_dm=0';
                    }

                    // 列表页 聚合商品的加购 需要在加购jumeimall链接中新增聚合详情页的product_id字段，传给购物流程.
                    if (isset($item['product_id']) && (in_array('76', $item['tag_ids']) || in_array('78', $item['tag_ids'])) && $item['type'] == 'jumei_mall') {
                        $add_scheme .= '&pid=' . $item['product_id'];
                    }

                    $sellparams = self::AddCountersToSellParams($item, $sellparams, $item['tag_ids']);

                    $sellparams = MProduct::encodeVerticalLine($sellparams);
                    $add_scheme .= '&sellparams=' . $sellparams;

                    $tmp['add_icon']['scheme'] = $add_scheme;

                    $direct_scheme = 'jumeimall://page/paycenter/directpay?items=' . $item['item_id'] . ',1&type=' . $item['type'] . '&confirm_type=' . $item['selling_forms'];
                    $tmp['add_icon']['direct_scheme'] = $direct_scheme;

                    if (VersionCtrl::isHomeTieziDirectpay()) {
                        $tmp['add_icon']['show_directpay'] = '1';  // 立即结算开关，高版本
                    } else {
                        $tmp['add_icon']['show_directpay'] = '0';  // 立即结算开关，低版本关闭
                    }

                }

                // 详情链接
                $tmp['scheme'] = $item['url_schema'];

                $tmp['statistic_info']['buried_type'] = isset($item['buried_type']) ? $item['buried_type'] : ''; // 宝洁
                $tmp['statistic_info']['exposure_link'] = isset($item['exposure_link']) ? $item['exposure_link'] : ''; // 曝光链接
                $tmp['statistic_info']['click_link'] = isset($item['click_link']) ? $item['click_link'] : ''; // 点击链接

                // 预留字段
                $tmp['app_owen_data'] = '';

                $result[$key] = $tmp;
                unset($tmp);
            }
        }
        return $result;
    }

    /**
     * 专柜商品增加埋点.
     *
     * @param array  $v           V.
     * @param string $_sellParams SellParams.
     * @param array  $tag_id      Tag_id.
     *
     * @return string.
     */
    public static function AddCountersToSellParams($v, $_sellParams, $tag_id)
    {

        $real_type = MProduct::getPrdType($v['item_id'], $v['type']);

        if ($real_type == 'jumei_mall' && (in_array('76', $tag_id) || in_array('78', $tag_id))) {
            $_sellParams .= '|product_type:aggregate_mall'; // 聚合mall
        }
        if (in_array($real_type, array('pop_mall', 'global_pop_mall')) && (in_array('76', $tag_id) || in_array('78', $tag_id))) {
            $_sellParams .= '|product_type:shoppe_only_mall'; // 专柜单卖mall
        }
        if (in_array('76', $tag_id) && !in_array('78', $tag_id)) {
            $_sellParams .= '|delivery_mode:self_pickup'; // 专柜自提
        } elseif (in_array('78', $tag_id) && !in_array('76', $tag_id)) {
            $_sellParams .= '|delivery_mode:shoppe_deliver'; // 专柜发货
        } elseif (in_array('76', $tag_id) && in_array('78', $tag_id)) {
            $_sellParams .= '|delivery_mode:self_pickup,shoppe_deliver'; // 专柜自提,专柜发货
        } elseif (($real_type == 'jumei_mall' || $real_type == 'global_mall') && !in_array('76', $tag_id) && !in_array('78', $tag_id)) {
            $_sellParams .= '|delivery_mode:jumei'; // 聚美发货
        }
        $show_id = \JMRegistry::get('show_id');
        if (!empty($show_id)) {
            $_sellParams .= '|show_id:' . $show_id;
        }
        $show_type = \JMRegistry::get('show_type');
        if (!empty($show_type)) {
            $_sellParams .= '|show_type:' . $show_type;
        }
        return $_sellParams;
    }

    /**
     * 各个列表页判断是否为凑团deal.
     *
     * @param array  $item  Item.
     * @param string $field Field.
     *
     * @return boolean
     */
    protected static function isYqtDeal($item, $field = 'selling_forms')
    {
        if ($field == 'selling_forms') {
            return isset($item['selling_forms']) && $item['selling_forms'] == 'yqt';
        } elseif ($field == 'yqt_show') {
            return isset($item['yqt_show']) && $item['yqt_show'] == 1;
        } else {
            return false;
        }
    }

    /**
     * 处理人数的显示,比如13200, 显示成1.3万.
     *
     * @param integer $num Num.
     *
     * @return string
     */
    public static function formatPersonNum($num = 0)
    {
        $lenNum = strlen($num);
        $formatNum = $num;
        if ($lenNum >= 5 && $lenNum < 7) {
            $formatNum = sprintf("%.1f", $num / 10000) . '万';
        }

        if ($lenNum >= 7 && $lenNum < 9) {
            $formatNum = sprintf("%.1f", $num / 1000000) . '百万';
        }


        return $formatNum;
    }

    /**
     * 处理人数的显示,小于10，不展示；大于等10小于10000，正常展示；大于10000，显示成1.万.
     *
     * @param string $num Num.
     *
     * @return string
     */
    public static function formatNum($num = '0')
    {
        $lenNum = strlen($num);
        $formatNum = $num;
        if ($lenNum >= 5 && $lenNum < 7) {
            $formatNum = sprintf("%.1f", $num / 10000) . '万';
        }

        if ($lenNum >= 7 && $lenNum < 9) {
            $formatNum = sprintf("%.1f", $num / 1000000) . '百万';
        }

        if ( $num < 10) {
            $formatNum = '0';
        }

        return $formatNum;
    }

    /**
     * 标题相关颜色字号处理.
     *
     * @return array
     */
    private static function getComponentFontColor()
    {
        $platform = JMRegistry::get('platform');

        $format = array();
        if ($platform == 'iphone') {
            $format['title']['mark1']['font_color'] = '#FFFFFF';
            $format['title']['mark2']['font_color'] = self::COLOR_JM_RED;
            $format['title']['header']['font_color'] = self::COLOR_BLACK;
            $format['title']['main']['font_color'] = self::COLOR_BLACK;

            $format['promo']['cycle']['font_color'] = self::COLOR_JM_RED;
            $format['promo']['desc']['font_color'] = self::COLOR_JM_RED;

            $format['price']['jumei_price']['price']['font_color'] = self::COLOR_JM_RED;
            $format['price']['jumei_price']['price']['font_size'] = '36';
            $format['price']['jumei_price']['price']['point_size'] = '24';

            $format['price']['jumei_price']['unit']['font_color'] = self::COLOR_JM_RED;
            $format['price']['jumei_price']['unit']['font_size'] = '24';

            $format['price']['jumei_price']['content']['font_color'] = self::COLOR_BLACK;
            $format['price']['jumei_price']['content']['font_size'] = '22';

            $format['price']['right_text']['font_color'] = self::COLOR_BLACK;
            $format['price']['right_text']['font_size'] = '24';

            $format['price']['market_price']['price']['font_color'] = self::COLOR_GRAY;
            $format['price']['market_price']['price']['font_size'] = '24';
            $format['price']['market_price']['price']['point_size'] = '24';

            $format['price']['market_price']['unit']['font_color'] = self::COLOR_GRAY;
            $format['price']['market_price']['unit']['font_size'] = '24';

            $format['tips']['single']['left1']['font_color'] = self::COLOR_GRAY_BLACK;

            $format['icons']['top_right']['wight'] = '72';
            $format['icons']['top_right']['height'] = '88.5';
        } else {
            $format['title']['mark1']['font_color'] = '#FFFFFF';
            $format['title']['mark2']['font_color'] = self::COLOR_JM_RED;
            $format['title']['header']['font_color'] = self::COLOR_BLACK;
            $format['title']['main']['font_color'] = self::COLOR_BLACK;

            $format['promo']['cycle']['font_color'] = self::COLOR_JM_RED;
            $format['promo']['desc']['font_color'] = self::COLOR_JM_RED;

            $format['price']['jumei_price']['price']['font_color'] = self::COLOR_JM_RED;
            $format['price']['jumei_price']['price']['font_size'] = '54';
            $format['price']['jumei_price']['price']['point_size'] = '36';

            $format['price']['jumei_price']['unit']['font_color'] = self::COLOR_JM_RED;
            $format['price']['jumei_price']['unit']['font_size'] = '36';

            $format['price']['jumei_price']['content']['font_color'] = self::COLOR_BLACK;
            $format['price']['jumei_price']['content']['font_size'] = '30';

            $format['price']['right_text']['font_color'] = self::COLOR_BLACK;
            $format['price']['right_text']['font_size'] = '36';

            $format['price']['market_price']['price']['font_color'] = self::COLOR_GRAY;
            $format['price']['market_price']['price']['font_size'] = '36';
            $format['price']['market_price']['price']['point_size'] = '36';

            $format['price']['market_price']['unit']['font_color'] = self::COLOR_GRAY;
            $format['price']['market_price']['unit']['font_size'] = '36';

            $format['tips']['single']['left1']['font_color'] = self::COLOR_GRAY_BLACK;

            $format['icons']['top_right']['wight'] = '79';
            $format['icons']['top_right']['height'] = '80';
        }
        return $format;
    }

    /**
     * 新样式相关字号、颜色处理.
     *
     * @return array
     */
    private static function getComponentFontColorForMixed()
    {
        $platform = JMRegistry::get('platform');

        $format = array();
        if ($platform == 'iphone') {
            $format['live']['mark2']['font_color'] = '#181818';
            $format['live']['mark2']['font_size'] = '24';

            $format['title']['header']['font_color'] = '#E93862';
            $format['title']['header']['font_size'] = '40';
            $format['title']['main']['font_color'] = '#1e1e1e';
            $format['title']['main']['font_size'] = '40';
            $format['title']['bottom']['font_color'] = '#878787';
            $format['title']['bottom']['font_size'] = '30';

            $format['tips']['mark1']['font_color'] = '#1e1e1e';
            $format['tips']['mark1']['font_size'] = '24';
            $format['tips']['mark2']['font_color'] = '#878787';
            $format['tips']['mark2']['font_size'] = '24';
            $format['tips']['mark3']['font_color'] = '#1e1e1e';
            $format['tips']['mark3']['font_size'] = '24';
            $format['tips']['mark4']['font_color'] = '#878787';
            $format['tips']['mark4']['font_size'] = '24';

            $format['promo']['cycle']['font_color'] = '#E93862';
            $format['promo']['cycle']['font_size'] = '20';
            $format['promo']['desc']['font_color'] = '#1e1e1e';
            $format['promo']['desc']['font_size'] = '26';

            $format['price']['jumei_price']['unit']['font_color'] = '#E93862';
            $format['price']['jumei_price']['unit']['font_size'] = '46';
            $format['price']['jumei_price']['price']['font_color'] = '#E93862';
            $format['price']['jumei_price']['price']['font_size'] = '46';
            $format['price']['jumei_price']['icons']['font_color'] = '#E93862';
            $format['price']['jumei_price']['icons']['font_size'] = '20';

            $format['price']['market_price']['unit']['font_color'] = '#1e1e1e';
            $format['price']['market_price']['unit']['font_size'] = '26';
            $format['price']['market_price']['price']['font_color'] = '#1e1e1e';
            $format['price']['market_price']['price']['font_size'] = '26';

            $format['price']['mark1']['font_color'] = '#1e1e1e';
            $format['price']['mark1']['font_size'] = '26';
            $format['price']['mark2']['font_color'] = '#1e1e1e';
            $format['price']['mark2']['font_size'] = '26';
            $format['price']['mark3']['font_color'] = '#1e1e1e';
            $format['price']['mark3']['font_size'] = '26';

        } else {
            $format['live']['mark2']['font_color'] = '#181818';
            $format['live']['mark2']['font_size'] = '36';

            $format['title']['header']['font_color'] = '#E93862';
            $format['title']['header']['font_size'] = '60';
            $format['title']['main']['font_color'] = '#1e1e1e';
            $format['title']['main']['font_size'] = '60';
            $format['title']['bottom']['font_color'] = '#878787';
            $format['title']['bottom']['font_size'] = '45';

            $format['tips']['mark1']['font_color'] = '#1e1e1e';
            $format['tips']['mark1']['font_size'] = '36';
            $format['tips']['mark2']['font_color'] = '#878787';
            $format['tips']['mark2']['font_size'] = '36';
            $format['tips']['mark3']['font_color'] = '#1e1e1e';
            $format['tips']['mark3']['font_size'] = '36';
            $format['tips']['mark4']['font_color'] = '#878787';
            $format['tips']['mark4']['font_size'] = '36';

            $format['promo']['cycle']['font_color'] = '#E93862';
            $format['promo']['cycle']['font_size'] = '30';
            $format['promo']['desc']['font_color'] = '#1e1e1e';
            $format['promo']['desc']['font_size'] = '39';

            $format['price']['jumei_price']['unit']['font_color'] = '#E93862';
            $format['price']['jumei_price']['unit']['font_size'] = '69';
            $format['price']['jumei_price']['price']['font_color'] = '#E93862';
            $format['price']['jumei_price']['price']['font_size'] = '69';
            $format['price']['jumei_price']['icons']['font_color'] = '#E93862';
            $format['price']['jumei_price']['icons']['font_size'] = '30';

            $format['price']['market_price']['unit']['font_color'] = '#1e1e1e';
            $format['price']['market_price']['unit']['font_size'] = '39';
            $format['price']['market_price']['price']['font_color'] = '#1e1e1e';
            $format['price']['market_price']['price']['font_size'] = '39';

            $format['price']['mark1']['font_color'] = '#1e1e1e';
            $format['price']['mark1']['font_size'] = '39';
            $format['price']['mark2']['font_color'] = '#1e1e1e';
            $format['price']['mark2']['font_size'] = '39';
            $format['price']['mark3']['font_color'] = '#1e1e1e';
            $format['price']['mark3']['font_size'] = '39';
        }
        return $format;
    }

    /**
     * 埋点统一处理.
     *
     * @param string $type   类型(详情页/首页/专场等标记 product_detail/home_main/activitylist).
     *
     * @param string $url    地址(jumeimall://page/index).
     *
     * @param array  $params 埋点参数array('selllabel', 'selltype').
     *
     * @param string $extend 扩展参数(from=hunpai).
     *
     * @return string
     */
    public static function getSellParams($type, $url, $params = array(), $extend = '')
    {
        switch ($type) {
            case 'top_page':
                // TODO
                break;
            case 'product_detail':
                // TODO
                break;
            case 'search':
                // TODO
                break;
            case 'activity_detail':
                // TODO
                break;
        }
        return $url;
    }

    /**
     * 直播开关.
     *
     * @param string $position 控制位置(总开关all, 直播卡片card, 列表页list, 详情页product_detail, 专场详情activity_detail).
     *
     * @return string
     */
    public static function isShowLiveInfoSwitch($position = 'activity_detail')
    {
        $flag = '0';
        $conf = LUtil::getConfig('common', 'live');
        // 总开关
        if (isset($conf['all']) && $conf['all'] == 1) {
            // 分控开关
            if (isset($conf[$position]) && $conf[$position] == 1) {
                $flag = '1';
            }
        }
        return $flag;
    }

    /**
     * 组件化公用字段.
     *
     * @return mixed
     */
    public static function getIconMapForList()
    {
        $config = LUtil::getApiConfig('ProductConf');
        return $config['act_icon_map'];
    }

    /**
     * 组件化公用字段加购按钮for mixed.
     *
     * @return mixed
     */
    public static function getIconMapForMixedList()
    {
        $config = LUtil::getApiConfig('ProductConf');
        return $config['act_icon_map_mixed'];
    }

    /**
     * 获取官方授权（官方授权 (#57172 海淘商品，判断是否为品牌授权，详情页和列表页加上【官方授权】，需要满足：‘海淘授权’或'聚美和海淘同时授权')）.
     *
     * @param array &$deals Deals.
     *
     * @return void
     */
    public static function getAllAuthorizationBrand(&$deals)
    {
        $all_auth = MProduct::getAllAuthorizationBrand();

        $platform = JMRegistry::get('platform');

        if (!empty($deals)) {
            foreach ($deals as &$data) {
                $auth = isset($all_auth[$data['brand_id']]) ? $all_auth[$data['brand_id']] : '';
                if (isset($data['brand_id']) && in_array($data['type'], array('global_deal', 'global_pop', 'global_mall', 'global_combination_deal', 'global_combination_mall'))) {
                    $add_str = '';
                    if (!empty($auth) && in_array($auth, array('global_auth', 'jumei_global_auth'))) {
                        if (!(isset($data['mall_real_type']) && $data['mall_real_type'] != 'global_pop_mall')) {
                            $add_str .= '【官方授权】';
                        }
                    }

                    if (isset($item['shipping_system_id']) && $item['shipping_system_id'] == 2754) {
                        $add_str .= '【香港直邮】';
                    }

                    if (isset($item['shipping_system_id']) && $item['shipping_system_id'] == 2967) {
                        $add_str .= '【澳门直邮】';
                    }
                    $data['name'] = $add_str . $data['name'];

                    // 获取配置中屏蔽的直邮仓id
                    $special_warehouse_ids = LUtil::getConfig('account', 'special_hide_warehouse_ids');
                    if (is_array($special_warehouse_ids) && isset($item['shipping_system_id']) && in_array($item['shipping_system_id'], $special_warehouse_ids)) {
                        // 直邮 提示
                        if ($platform == 'iphone') {
                            $data['is_dm'] = true;
                        }
                    }
                }
                // 官方授权
                switch ($data['type']) {
                    case 'jumei_deal':
                        // 国内自营deal
                        $authorization = in_array($auth, array('jumei_auth', 'jumei_global_auth')) ? '官方授权' : '';
                        break;
                    case 'jumei_pop':
                        // 国内popdeal
                        $authorization = in_array($auth, array('jumei_auth', 'jumei_global_auth')) ? '官方授权' : '';
                        break;
                    case 'global_deal':
                        // 海淘自营deal
                        $authorization = in_array($auth, array('global_auth', 'jumei_global_auth')) ? '官方授权' : '';
                        break;
                    case 'global_mall':
                        // 海淘自营mall
                        $authorization = in_array($auth, array('global_auth', 'jumei_global_auth')) && (!isset($data['mall_real_type']) || $data['mall_real_type'] != 'global_pop_mall') ? '官方授权' : '';
                        break;
                    case 'jumei_mall':
                        // 国内自营mall、国内popmall
                        $authorization = in_array($auth, array('jumei_auth', 'jumei_global_auth')) ? '官方授权' : '';
                        break;
                    case 'global_combination_deal':
                        // 新组合购deal
                        $authorization = in_array($auth, array('global_auth', 'jumei_global_auth')) ? '官方授权' : '';
                        break;
                    case 'global_combination_mall':
                        // 新组合购mall
                        $authorization = in_array($auth, array('global_auth', 'jumei_global_auth')) ? '官方授权' : '';
                        break;
                    default:
                        $authorization = '';
                        break;
                }
                $data['authorization'] = $authorization;
                $data['name_tag']['authorization'] = $authorization;

            }
        }
    }

    /**
     * 通过方法名和参数获取memcache缓存数据.
     *
     * @param string  $method        方法名.
     * @param array   $params        参数数组.
     * @param integer $ttl           过期时间 单位 秒.
     * @param boolean $force_refresh 是否强制刷新.
     * @param boolean $smart         Smart.
     *
     * @return array
     */
    public static function getMemCache($method, $params = array(), $ttl = 30, $force_refresh = false, $smart = false)
    {
        // 这块代码冲突的话不要丢了, rd和staging去掉缓存
        $phase = LUtil::getConfig('common','phase');
        if (in_array($phase, array('rd', 'staging'))) {
            $force_refresh = true;
        }
        $json_params = json_encode($params);
        $date = date('Y-m-d H');
        $key = $method . '_' .md5($json_params.$date);
        $data = LCache::memGet($key);
        if (empty($data) || $force_refresh == true) {
            if ($smart) {
                $data = LUtil::smart($method, $params);
            } else {
                $data = LUtil::call($method, $params);
            }
            if (!empty($data)) {
                LCache::memSet($key, $data, $ttl);
            }
        }
        return $data;
    }

    /**
     * 价格统一处理(保留两位小数).
     *
     * @param string $price Price.
     *
     * @return string
     */
    public static function formatPrice($price)
    {
        return (string)round($price, 2);
    }

    /**
     * 直播开关.
     *
     * @param string $doveKey DoveKey.
     *
     * @return mixed
     */
    public static function isShowLiveInfo($doveKey = 'activity_detail_show_live')
    {
        $config = LUtil::getConfig('Common',$doveKey);
        return $config['4.4'];
    }

    /**
     * 口碑数 历史销量 30天销量 AB测试.
     *
     * @param array  $data         口碑评论数.
     * @param string $product_desc 描述.
     * @param string $type         类型(home/search/store).
     *
     * @return string.
     */
    public  function getDealCommentsNumberProductDesc($data, $product_desc = '', $type = 'home')
    {
        // 获取AB方案 (首页 口碑数 / 历史总购买人数 / 30天购买人数)
        $ab = 'koubei';

        $whiteList = self::getIdfaWhiteList();

        if ($whiteList == 'kb' || $ab == 'koubei') {
            $product_desc = self::getKouBeiProductDesc($data['deal_comments_number'], $product_desc, $type);
        } elseif ($whiteList == 'bt' || $ab == 'buyer_total') {
            // 预热商品不替换销量
            if ('wish' !== $data['status']) {
                $product_desc = self::getBuyerTotalProductDesc($data['total_sales_number'], $product_desc, $type);
            }
        } elseif ($ab == 'buyer_30day') {
            // 现有30天方案(不需要处理)
        } else {
            // 现有30天方案(不需要处理)
        }

        return $product_desc;
    }

    /**
     * 白名单.
     *
     * @return string.
     */
    public function getIdfaWhiteList()
    {
        $flag = '';
        $conf = LUtil::getConfig('common', 'show_total_sales_number');
        $idfa = LUtil::getCookie('idfa');
        if (!empty($idfa) && !empty($conf['white_list'])) {
            $white_list = $conf['white_list'];
            if (isset($white_list[$idfa]) && in_array($white_list[$idfa], array('kb', 'bt'))) {
                $flag = $white_list[$idfa];
            }
        }
        return $flag;
    }

    /**
     * 口碑数.
     *
     * @param integer $deal_comments_number 口碑评论数.
     * @param string  $product_desc         描述.
     * @param string  $type                 类型(home/search/store).
     *
     * @return string.
     */
    public function getKouBeiProductDesc($deal_comments_number, $product_desc = '', $type = 'home')
    {
        $conf = LUtil::getConfig('common', 'show_koubei_number');
        if (is_array($conf) && isset($conf[$type]) && $conf[$type] == 1) {
            $product_desc = '';
            $deal_comments_number = intval($deal_comments_number);
            switch ($type) {
                case 'home':
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) . '条评论' : '暂无评论';
                    break;
                case 'global':
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'pagelist':
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'cards':
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'search':
                    // TODO
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'shelf':
                    // TODO
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'store':
                    // TODO
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'starstore':
                    // TODO
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '暂无评论';
                    break;
                case 'coutuan':
                    // TODO
                    $product_desc = $deal_comments_number > 0 ? self::formatBuyNum($deal_comments_number) .'条评论' : '';
                    break;
                default:
                    // TODO
                    break;
            }
        }
        return $product_desc;
    }

    /**
     * 历史总购买人数.
     *
     * @param integer $total_sales_number 口碑评论数.
     * @param string  $product_desc       描述.
     * @param string  $type               类型(home/search/store).
     *
     * @return string.
     */
    public function getBuyerTotalProductDesc($total_sales_number, $product_desc = '', $type = 'home')
    {
        $conf = LUtil::getConfig('common', 'show_total_sales_number');
        if (is_array($conf) && isset($conf[$type]) && $conf[$type] == 1) {
            $product_desc = '';
            $total_sales_number = intval($total_sales_number);
            switch ($type) {
                case 'home':
                    $product_desc = $total_sales_number > 0 ? '累计'.self::formatBuyNum($total_sales_number) . '人已购买' : '';
                    break;
                case 'search':
                    // TODO
                    break;
                case 'shelf':
                    // TODO
                    break;
                case 'store':
                    // TODO
                    break;
                case 'starstore':
                    // TODO
                    break;
                default:
                    // TODO
                    break;
            }
        }
        return $product_desc;
    }

    /**
     * 处理人数的显示,比如13200, 显示成1.3万.
     *
     * @param integer $num 数量.
     *
     * @return mixed.
     */
    public static function formatBuyNum($num = 0)
    {
        $lenNum = strlen($num);

        $formatNum = $num;

        if ($lenNum >= 5) {
            $formatNum = sprintf("%.1f", $num / 10000) . '万';
        }

        return $formatNum;
    }

    /**
     * Product格式化之后判断一个商品是否为大促deal.
     *
     * @param array  $item Item.
     * @param string $type Type.
     *
     * @return bool.
     */
    public static function isBigSaleDeal($item, $type)
    {
        $conf = LUtil::getConfig('common', 'big_sale_conf');
        $time = time();
        if ($conf['is_show'] && $conf['start_time'] < $time && $conf['end_time'] > $time) {
            if ($type == 'search') {
                return isset($item['tag_id']) && in_array($conf['tag_id'], $item['tag_id']);
            } elseif ($type == 'product') {
                return isset($item['tag_ids']) && in_array($conf['tag_id'], $item['tag_ids']);
            }
        }

        return false;

    }

    /**
     * 是否在大促期间,在大促期间返回配置.
     *
     * @return null.
     */
    public static function getBigSaleConf()
    {
        $conf = LUtil::getConfig('common','big_sale_conf');
        $time = time();
        if ($conf['is_show'] && $conf['start_time'] < $time  &&  $conf['end_time'] > $time) {
            return $conf;
        } else {
            return null;
        }
    }

    /**
     * 是否是话费商品.
     *
     * @param array $tagIds TagIds.
     *
     * @return boolean
     */
    public static function isPhoneCostGoods(array $tagIds)
    {
        if (in_array(self::TAG_ID_PHONE_COST, $tagIds, false) || in_array(self::TAG_ID_WEBFLOW_COST, $tagIds, false)) {
            return true;
        }
        return false;
    }

    /**
     * 关联deal配置.
     *
     * @return array
     */
    public static function getBigSaleRelateDealConf()
    {
        $conf = LUtil::getConfig('common','big_sale_conf');
        $time = time();
        if (isset($conf['relate_deal']) && $conf['relate_deal'] && isset($conf['relate_deal_start_time']) && $conf['relate_deal_start_time'] < $time  && isset($conf['relate_deal_end_time']) && $conf['relate_deal_end_time'] > $time) {
            return $conf;
        } else {
            return null;
        }
    }

    /**
     * 根据折扣或价格差价计算是否隐藏，传折扣只传一个参数，传价格传两个参数.
     *
     * @param float $marketPrice 折扣或者市场价.
     * @param float $jumeiPrice  售价.
     * @param float $discount    折扣.
     *
     * @return boolean
     */
    public static function shouldHideMarketPrice($marketPrice = 0.0, $jumeiPrice = 0.0, $discount = 0.0)
    {
        // discount 优先
        if ($discount > 0) {
            if ($discount <= 9.5) {
                return false;
            }
        }

        if ($jumeiPrice > 0 && $marketPrice > 0) {
            if (($marketPrice - $jumeiPrice) >= 5) {
                return false;
            }

            $discount = $jumeiPrice / $marketPrice * 10;
            if ($discount > 0 && $discount <= 9.5) {
                return false;
            }
        }
        return true;
    }

    /**
     * 隐藏市场价.
     *
     * @param array &$data Data.
     *
     * @return mixed
     */
    public static function hideMarketPrice(&$data)
    {
        // 开关控制是否显示市场价
        if (LUtil::getConfig('common','hideMarketPrice')) {
            if (isset($data['market_price'])) {
                $data['market_price'] = '-1';
            }

            if (!empty($data['size']) && is_array($data['size'])) {
                foreach ($data['size'] as &$oneSku) {
                    $oneSku['market_price'] = '-1';
                }
            }
        }

        if (isset($data['status']) && $data['status'] == 'wish' && isset($data['display_price']) && $data['display_price'] == '0') {
            $data['market_price'] = '-1';
        }

        if (!empty($data['size']) && is_array($data['size'])) {
            foreach ($data['size'] as &$oneSku) {
                if (empty($oneSku['market_price'])) {
                    continue;
                } else {
                    if (self::shouldHideMarketPrice($oneSku['market_price'], $oneSku['jumei_price'])) {
                        $oneSku['market_price'] = '-1';
                    }
                }

            }

            foreach ($data['size'] as &$oneSku) {
                if (($data['status'] == 'wish' && isset($data['display_price']) && $data['display_price'] == '0')) {
                    $oneSku['market_price'] = '-1';
                }
            }

        }

    }

    /**
     * 库存置零.
     *
     * @param array &$products Products.
     *
     * @return void
     */
    public static function formatSizeStock(&$products)
    {
        if (isset($products['size']) && is_array($products['size'])) {
            foreach ($products['size'] as &$size) {
                $size['stock'] = '0';
            }
        }
    }

    /**
     * 格式化size里的url_scheme.
     *
     * @param array &$data Data.
     *
     * @return void
     */
    public static function formatButtonScheme(&$data)
    {
        // 针对除了开售提醒的其他任何情况
        if (!(isset($data['status']) && in_array($data['status'], array('soldout', 'expired', 'offshelf')) && isset($data['selling_forms']) && $data['selling_forms'] != 'presale' && $data['type'] != 'red_envelope' && $data['type'] != 'lottery_custom')) {
            if (!empty($data['size'])) {
                foreach ($data['size'] as &$size) {
                    $url = '';

                    if (isset($data['tag_ids'])) {
                        $tagIds = $data['tag_ids'];
                    } elseif (isset($data['tag_id'])) {
                        $tagIds = $data['tag_id'];
                    } else {
                        $tagIds = self::getDealTagIdByItemId($data['item_id']);
                    }
                    // 普通商品话费结算 url
                    if (self::isPhoneCostGoods($tagIds)) {
                        $url = sprintf(
                            'jumeimall://page/paycenter/directpay?itemKeys=%s,%s,1&type=%s&confirm_type=recharge',
                            $size['sku'],
                            $data['item_id'],
                            $data['type']
                        );
                    }
                    $size['url_scheme'] = $url;
                }
            }
        }
    }

    /**
     * 获取单个商品的 deal tagIds.
     *
     * @param string $itemId ItemId.
     *
     * @return array
     */
    public static function getDealTagIdByItemId($itemId)
    {
        /** @var array $tagIds */
        $tagIds = LUtil::smart('TextRpc_JumeiProduct_JumeiProduct_Read_TagOperation::getTagIdByParams', array(array('5' => array($itemId))));
        return isset($tagIds['5'][$itemId]) ? $tagIds['5'][$itemId] : array();
    }

    /**
     * 获取skuimg.
     *
     * @param string $url Url.
     *
     * @return string
     */
    public static function getSkuImg($url)
    {
        $image = '';
        if (!empty($url)) {
            $ImageConf = LUtil::getApiConfig('ImageConf');
            $url_default = $ImageConf['sku_default'];
            $list = Image::autoCompressImg('product_detail_single_sku', array('url' => $url_default . $url));
            $image = array_shift($list);    // 取第一个
        }
        return $image;
    }

    /**
     * 价格统一处理(保留两位小数).
     *
     * @param string $price Price.
     *
     * @return string
     */
    public static function formatPriceV2($price)
    {
        return (string)round($price, 2);
    }

    /**
     * 价格统一处理(保留1位小数).
     *
     * @param string $price Price.
     *
     * @return string
     */
    public static function formatPriceV3($price)
    {
        return (string)round($price, 1);
    }

    /**
     * 专柜详情页跳转地址.
     *
     * @param string $shipping_system_id 专柜id.
     * @param string $brand_id           品牌id.
     * @param string $pop_brand_id       Pop品牌id.
     *
     * @return string
     */
    public static function getCountersListUrl($shipping_system_id = "", $brand_id = "", $pop_brand_id = "")
    {
        // 获取页数配置
        $buttonLink = "";
        $conf = LUtil::getConfig('common','shoppe_detail_item_per_page');
        $conf = !empty($conf) ? $conf : 20;
        if (!empty($shipping_system_id)) {
            $param = array(
                'shipping_system_id' => array($shipping_system_id),
            );
            $buttonLink = "jumeimall://page/shoppe/video?shipping_system_ids=" . $shipping_system_id . "&search_type=0&search_source=shoppe&sort=11&special_activity=0&item_per_page=".$conf."&mall_sale_mode=2&is_juhe=0,1&shoppe_list_params=shoppe_source:shoppe&sellType=video&sellLabel=video_list&show_id=video_" . $shipping_system_id . "&show_type=video_shoppe";
        } elseif (!empty($brand_id)) {
            $param = array(
                'brand_id' => array($brand_id),
            );
            $buttonLink = "jumeimall://page/shoppe/video?search_type=0&search_source=shoppe&sort=11&special_activity=0&item_per_page=".$conf."&mall_sale_mode=2&is_juhe=0,1&shoppe_list_params=shoppe_source:shoppe&sellType=video&sellLabel=video_list&show_type=video_shoppe&brand_id=".$brand_id;
        } elseif (!empty($pop_brand_id)) {
            $param = array(
                'pop_brand_id' => array($pop_brand_id),
            );
            $buttonLink = "jumeimall://page/shoppe/video?search_type=0&search_source=shoppe&sort=11&special_activity=0&item_per_page=".$conf."&mall_sale_mode=2&is_juhe=0,1&shoppe_list_params=shoppe_source:shoppe&sellType=video&sellLabel=video_list&show_type=video_shoppe&pop_brand_id=".$pop_brand_id;
        }
        return $buttonLink;
    }

    /**
     * 大促icon.
     *
     * @param array &$product_array Product_array.
     * @param array $items          Items.
     *
     * @return void
     */
    public static function getBigSaleProductInfo(&$product_array, $items = array())
    {
        $conf = self::getBigSaleRelateDealConf();

        if (!empty($conf)) {
            foreach ($items as &$tmp) {
                $tmp['type'] = self::getPrdType($tmp['id'], $tmp['type']);
            }

            $relationItems = LUtil::smart('TextRpc_JumeiProduct_JumeiProduct_Read_Deals::getAppointedTagDealInfoByCond', array($items,$conf['tag_id']));

            $now = time();
            foreach ($product_array as &$item) {
                $_key = self::getPrdType($item['item_id'], $item['type']) . '_' . $item['item_id'];
                if (isset($relationItems[$_key]) &&
                    ($item['status'] == 'wish' || $item['status'] == 'onsell') &&
                    $relationItems[$_key]['start_time'] > $now &&
                    ($item['jumei_price'] > 0 && false === self::shouldHideMarketPrice($item['jumei_price'], $relationItems[$_key]['discounted_price']))
                ) {
                    $item['single_festival_title'] = $conf['single_festival_title']; // 一行一列
                    $item['double_festival_title'] = $conf['double_festival_title']; // 一行两列
                    $item['festival_price'] = '￥' . self::formatPrice($relationItems[$_key]['discounted_price']);
                }
            }
        }
    }

    /**
     * 产品类型处理.
     *
     * @param string $item_id Item_id.
     * @param string $type    Type.
     *
     * @return mixed|string
     */
    public static function getPrdType($item_id, $type)
    {
        // 组合购 转换 global_deal/global_mall
        $type = ($type == 'global_combination_deal' || $type == 'global_combination_mall') ? str_replace('_combination', '', $type) : $type;

        // 还原global_mall类型(global_pop_mall)
        if ($type == 'global_mall') {
            $res = LUtil::smart('TextRpc_JumeiProduct_JumeiProduct_Mobile_Read::getGlobalMallSaleType', array($item_id, 'mall_id'));
            if (isset($res[$item_id]) && $res[$item_id] == 'global_pop_mall') {
                $type = 'global_pop_mall';
            }
        }

        // 还原pop_mall类型(jumei_mall)
        if ($type == 'jumei_mall') {
            $type = \Modules\Mall::Instance()->getProductCategoryByProductIds($item_id);
        }

        // 现金券红包抽奖都是jumei_deal
        if (in_array($type, array('promo_cards', 'red_envelope', 'lottery_custom'))) {
            $type = 'jumei_deal';
        }

        return $type;
    }

    /**
     * 列表页是否显示加购按钮.
     *
     * @param array &$data Data.
     *
     * @return void
     */
    public static function batchCheckIsShowCartButton(&$data)
    {
        if (!empty($data)) {
            // 彩票礼包－直接结算
            $giftlist = LUtil::getConfig('common', 'gift_package_deal');

            $items = array();
            foreach ($data as &$v) {
                $v['show_purchase_button'] = '1';
                if (isset($v['type']) && isset($v['item_id']) && in_array($v['type'], array('jumei_deal', 'global_deal', 'global_combination_deal', 'jumei_pop', 'global_pop', 'red_envelope', 'promo_cards'))) {
                    if (!empty($giftlist) && in_array($v['item_id'], $giftlist)) {
                        $v['show_purchase_button'] = '0';
                    } else {
                        $items[] = $v['item_id'];
                    }
                }
            }

            if (!empty($items)) {
                $res = array();
                foreach (array_chunk($items, 50) as $param) {
                    $tagParam = array('5' => $param);
                    $tmp = LUtil::smart('TextRpc_JumeiProduct_JumeiProduct_Read_TagOperation::getTagIdByParams', array($tagParam));
                    $res = array_merge($res, $tmp['5']);
                }
                foreach ($data as &$item) {
                    if (isset($res[$item['item_id']]) && is_array($res[$item['item_id']])) {
                        if (in_array('26', $res[$item['item_id']])) {
                            $item['show_purchase_button'] = '0';
                        }
                    }
                }
            }
        }

    }

    /**
     * 促销信息组件化处理.
     *
     * @param array  $product Product.
     * @param array  $promos  Promos.
     * @param string $item    Item.
     *
     * @return array
     */
    public static function promoComponentFormat($product, $promos, $item = '')
    {
        $iconPromoInfo = array();
        if ((isset($product['status']) && $product['status'] != 'wish' && $product['status'] != 'onsell') || !isset($product['promo'])) {
            return $iconPromoInfo;
        }

        if (!empty($promos)) {
            $promoList = array(
                '不封顶满减' => 0,
                '满减' => 1,
                '金额满就折' => 2,
                '第二件打折' => 3,
                '件数满就折' => 4,
                '满X免Y件' => 5,
                'X元Y件' => 6,
                '不封顶满返' => 7,
                '满返' => 8,
            );
            $promoStrs = array_keys($promoList);
            $sortPromo = array();
            if (empty($item) && isset($product['type'])) {
                if (in_array($product['type'], array('global_mall', 'global_pop_mall'))) {
                    $item = isset($product['product_id']) ? $product['product_id'] . '_' . $product['type'] : '';
                } else {
                    $item = isset($product['item_id']) ? $product['item_id'] . '_' . $product['type'] : '';
                }
            }
            $promo = isset($promos[$item]) ? $promos[$item] : array();
            foreach ($promo as $key => $value) {
                $value['full_type_name'] = trim($value['full_type_name']);
                if (!in_array($value['full_type_name'], $promoStrs)) {
                    continue;
                }
                if (!isset($sortPromo[$promoList[$value['full_type_name']]])) {
                    $sortPromo[$promoList[$value['full_type_name']]] = array();
                }
                if (in_array($promoList[$value['full_type_name']], array(0, 1, 7, 8))) {
                    $sortPromo[$promoList[$value['full_type_name']]] = array_merge($sortPromo[$promoList[$value['full_type_name']]], $value['short_desc_all']);
                } elseif (in_array($promoList[$value['full_type_name']], array(2, 3, 4))) {
                    $sortPromo[$promoList[$value['full_type_name']]] = array(array_shift($value['short_desc_all']));
                } else {
                    $sortPromo[$promoList[$value['full_type_name']]] = array_merge($sortPromo[$promoList[$value['full_type_name']]], $value['short_desc_all']);
                }
            }
            // 组件化促销数据
            if (!empty($sortPromo[0])) {
                $iconPromoInfo = $sortPromo[0];
            } elseif (!empty($sortPromo[1])) {
                $iconPromoInfo = $sortPromo[1];
            } elseif (!empty($sortPromo[7])) {
                $iconPromoInfo = $sortPromo[7];
            } elseif (!empty($sortPromo[8])) {
                $iconPromoInfo = $sortPromo[8];
            }
        }
        return $iconPromoInfo;
    }

    /**
     * 获取组件化样式配置（字号，颜色，宽高）.
     *
     *  @return array
     */
    public static function getStyleConfigByComponent()
    {
        $platform = JMRegistry::get('platform');
        $styleConfMap = array();
        if ($platform == 'iphone') {
            $styleConfMap['icons']['middle']['image']['img_small_width'] = '70';
            $styleConfMap['icons']['middle']['image']['img_small_height'] = '70';
            $styleConfMap['icons']['middle']['image']['img_big_width'] = '105';
            $styleConfMap['icons']['middle']['image']['img_big_height'] = '105';
            $styleConfMap['icons']['bottom']['status']['soldout_font_color'] = '#FFFFFF';
            $styleConfMap['icons']['bottom']['status']['soldout_background_color'] = '#979797';
            $styleConfMap['icons']['bottom']['promotion']['font_color'] = '#FE4070';
            $styleConfMap['icons']['bottom']['promotion']['background_color'] = '#FFEBF0';
            $styleConfMap['icons']['bottom']['promotion']['saved_font_color'] = '#FE4070';
            $styleConfMap['icons']['bottom']['promotion']['saved_background_color'] = '#FFEBF0';
            $styleConfMap['icons']['bottom']['promotion']['font_size_double'] = '12';
            $styleConfMap['icons']['bottom']['promotion']['font_size_single'] = '10';
            $styleConfMap['icons']['bottom']['festival']['font_size_double'] = '12';
            $styleConfMap['icons']['bottom']['festival']['font_size_single'] = '10';
            $styleConfMap['icons']['bottom']['status']['font_size_double'] = '11';
            $styleConfMap['icons']['bottom']['status']['font_size_single'] = '11';
            $styleConfMap['icons']['top_left']['top_icon']['width'] = '84';
            $styleConfMap['icons']['top_left']['top_icon']['height'] = '75';

            $styleConfMap['title']['mark1']['font_color'] = '#FFFFFF';
            $styleConfMap['title']['mark1']['background_color'] = '#FE4070';
            $styleConfMap['title']['mark2']['font_color'] = '#FE4070';
            $styleConfMap['title']['mark2']['background_color'] = '#FFFFFF';
            $styleConfMap['title']['header']['font_color'] = '#333333';
            $styleConfMap['title']['header']['background_color'] = '#FFFFFF';
            $styleConfMap['title']['main']['font_color'] = '#333333';
            $styleConfMap['title']['main']['background_color'] = '#FFFFFF';

            $styleConfMap['tag']['font_color'] = '#999999';

            $styleConfMap['price']['jumei_price']['price_font_color'] = '#FE4070';
            $styleConfMap['price']['jumei_price']['price_font_size'] = '36';
            $styleConfMap['price']['jumei_price']['price_point_size'] = '24';
            $styleConfMap['price']['jumei_price']['content_font_color'] = '#333333';
            $styleConfMap['price']['jumei_price']['content_font_size'] = '22';
            $styleConfMap['price']['jumei_price']['unit_font_color'] = '#FE4070';
            $styleConfMap['price']['jumei_price']['unit_font_size'] = '24';
            $styleConfMap['price']['market_price']['price_font_color'] = '#999999';
            $styleConfMap['price']['market_price']['price_font_size'] = '22';
            $styleConfMap['price']['market_price']['price_point_size'] = '22';
            $styleConfMap['price']['market_price']['content_font_color'] = '#333333';
            $styleConfMap['price']['market_price']['content_font_size'] = '22';
            $styleConfMap['price']['market_price']['unit_font_color'] = '#999999';
            $styleConfMap['price']['market_price']['unit_font_size'] = '22';
            $styleConfMap['price']['market_price']['presale_price_font_color'] = '#FE4070';
            $styleConfMap['price']['market_price']['presale_price_font_size'] = '36';
            $styleConfMap['price']['market_price']['presale_price_point_size'] = '24';
            $styleConfMap['price']['market_price']['presale_content_font_size'] = '22';
            $styleConfMap['price']['market_price']['presale_unit_font_color'] = '#FE4070';
            $styleConfMap['price']['market_price']['presale_unit_font_size'] = '24';

            $styleConfMap['promo']['cycle']['font_color'] = '#FE4070';
            $styleConfMap['promo']['capsule']['font_color'] = '#FE4070';
            $styleConfMap['promo']['tag']['font_color'] = '#FFFFFF';
            $styleConfMap['promo']['tag']['background_color'] = '#FE4070';

            $styleConfMap['tips']['left1']['font_color'] = '#999999';
            $styleConfMap['tips']['left2']['font_color'] = '#999999';
            $styleConfMap['tips']['right']['font_color'] = '#999999';
        } elseif ($platform == 'android') {
            $styleConfMap['icons']['middle']['image']['img_small_width'] = '70';
            $styleConfMap['icons']['middle']['image']['img_small_height'] = '70';
            $styleConfMap['icons']['middle']['image']['img_big_width'] = '105';
            $styleConfMap['icons']['middle']['image']['img_big_height'] = '105';
            $styleConfMap['icons']['bottom']['status']['soldout_font_color'] = '#FFFFFF';
            $styleConfMap['icons']['bottom']['status']['soldout_background_color'] = '#979797';
            $styleConfMap['icons']['bottom']['promotion']['font_color'] = '#FE4070';
            $styleConfMap['icons']['bottom']['promotion']['background_color'] = '#FFEBF0';
            $styleConfMap['icons']['bottom']['promotion']['saved_font_color'] = '#FE4070';
            $styleConfMap['icons']['bottom']['promotion']['saved_background_color'] = '#FFEBF0';
            $styleConfMap['icons']['bottom']['promotion']['font_size_double'] = '36';
            $styleConfMap['icons']['bottom']['promotion']['font_size_single'] = '30';
            $styleConfMap['icons']['bottom']['festival']['font_size_double'] = '33';
            $styleConfMap['icons']['bottom']['festival']['font_size_single'] = '33';
            $styleConfMap['icons']['bottom']['status']['font_size_double'] = '30';
            $styleConfMap['icons']['bottom']['status']['font_size_single'] = '30';
            $styleConfMap['icons']['top_left']['top_icon']['width'] = '84';
            $styleConfMap['icons']['top_left']['top_icon']['height'] = '75';

            $styleConfMap['title']['mark1']['font_color'] = '#FFFFFF';
            $styleConfMap['title']['mark1']['background_color'] = '#FE4070';
            $styleConfMap['title']['mark2']['font_color'] = '#FE4070';
            $styleConfMap['title']['mark2']['background_color'] = '#FFFFFF';
            $styleConfMap['title']['header']['font_color'] = '#333333';
            $styleConfMap['title']['header']['background_color'] = '#FFFFFF';
            $styleConfMap['title']['main']['font_color'] = '#333333';
            $styleConfMap['title']['main']['background_color'] = '#FFFFFF';

            $styleConfMap['tag']['font_color'] = '#999999';

            $styleConfMap['price']['jumei_price']['price_font_color'] = '#FE4070';
            $styleConfMap['price']['jumei_price']['price_font_size'] = '54';
            $styleConfMap['price']['jumei_price']['price_point_size'] = '36';
            $styleConfMap['price']['jumei_price']['content_font_color'] = '#333333';
            $styleConfMap['price']['jumei_price']['content_font_size'] = '30';
            $styleConfMap['price']['jumei_price']['unit_font_color'] = '#FE4070';
            $styleConfMap['price']['jumei_price']['unit_font_size'] = '36';
            $styleConfMap['price']['market_price']['price_font_color'] = '#999999';
            $styleConfMap['price']['market_price']['price_font_size'] = '30';
            $styleConfMap['price']['market_price']['price_point_size'] = '30';
            $styleConfMap['price']['market_price']['content_font_color'] = '#333333';
            $styleConfMap['price']['market_price']['content_font_size'] = '30';
            $styleConfMap['price']['market_price']['unit_font_color'] = '#999999';
            $styleConfMap['price']['market_price']['unit_font_size'] = '30';
            $styleConfMap['price']['market_price']['presale_price_font_color'] = '#FE4070';
            $styleConfMap['price']['market_price']['presale_price_font_size'] = '54';
            $styleConfMap['price']['market_price']['presale_price_point_size'] = '36';
            $styleConfMap['price']['market_price']['presale_content_font_size'] = '30';
            $styleConfMap['price']['market_price']['presale_unit_font_color'] = '#FE4070';
            $styleConfMap['price']['market_price']['presale_unit_font_size'] = '36';

            $styleConfMap['promo']['cycle']['font_color'] = '#FE4070';
            $styleConfMap['promo']['capsule']['font_color'] = '#FE4070';
            $styleConfMap['promo']['tag']['font_color'] = '#FFFFFF';
            $styleConfMap['promo']['tag']['background_color'] = '#FE4070';

            $styleConfMap['tips']['left1']['font_color'] = '#999999';
            $styleConfMap['tips']['left2']['font_color'] = '#999999';
            $styleConfMap['tips']['right']['font_color'] = '#999999';
        }

        return $styleConfMap;
    }

    /**
     * 店铺组件.
     *
     * @param array $data  Data.
     * @param array $style Style.
     *
     * @return array.
     */
    public static function componentForStoreData($data, $style)
    {
        $list = array();
        if (!empty($data) && is_array($data)) {
            $sellType = 'store_native';
            $sellLabel = JMRegistry::get('store_id');
            // 添加购物车时使用 需要这个tag_id
            $sellParams = '';
            $sellParams = MProduct::addSellParams($sellParams);
            $platform = JMRegistry::get('platform');
            foreach ($data as $k => $v) {

                $tmpComponent = array();
                // 商品基本信息
                $tmpComponent['type'] = 'product';
                $tmpComponent['info'] = array(
                    'type' => isset($v['type']) ? $v['type'] : '',
                    'item_id' => isset($v['item_id']) ? $v['item_id'] : '',
                    'selling_forms' => isset($v['selling_forms']) ? $v['selling_forms'] : '',
                    'date' => isset($v['date']) ? $v['date'] : '',
                );

                // 商品icon
                $tmpComponent['icons'] = array();
                $iconTopLeft = $iconMiddle = $iconBottom = array();
                // 顶部icon
                if ($platform == 'iphone') {
                    // 顶部icon
                    if (!empty($v['single_bigdev_small_icon'])) {
                        $iconTopLeft['position'] = 'top_left';
                        $iconTopLeft['type'] = 'image';
                        $iconSingle = array();
                        $iconSingle['width'] = isset($v['single_bigdev_small_icon_size']['width']) ? $v['single_bigdev_small_icon_size']['width'] : '';
                        $iconSingle['height'] = isset($v['single_bigdev_small_icon_size']['height']) ? $v['single_bigdev_small_icon_size']['height'] : '';
                        $iconSingle['img'] = array(
                            '1200' => $v['single_bigdev_small_icon'],
                        );
                        $iconTopLeft['single'] = $iconSingle;
                    }
                    if (!empty($v['double_bigdev_big_icon'])) {
                        $iconTopLeft['position'] = 'top_left';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = isset($v['double_bigdev_big_icon_size']['width']) ? $v['double_bigdev_big_icon_size']['width'] : '';
                        $iconDouble['height'] = isset($v['double_bigdev_big_icon_size']['height']) ? $v['double_bigdev_big_icon_size']['height'] : '';
                        $iconDouble['img'] = array(
                            '1200' => $v['double_bigdev_big_icon'],
                        );
                        $iconTopLeft['double'] = $iconDouble;
                    }
                    if (isset($v['countries_icon_single']) && !empty($v['countries_icon_single'])) {
                        $iconTopLeft['position'] = 'top_right';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = '69';
                        $iconDouble['height'] = '36';
                        $iconDouble['img'] = array(
                            '1200' => $v['countries_icon_single'],
                        );
                        $iconTopLeft['single'] = $iconDouble;
                    }
                    if (isset($v['countries_icon_double']) && !empty($v['countries_icon_double'])) {
                        $iconTopLeft['position'] = 'top_right';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = '99';
                        $iconDouble['height'] = '81';
                        $iconDouble['img'] = array(
                            '1200' => $v['countries_icon_double'],
                        );
                        $iconTopLeft['double'] = $iconDouble;
                    }
                } elseif ($platform == 'android') {
                    // 顶部icon
                    if (!empty($v['small_left_icon'])) {
                        $iconTopLeft['position'] = 'top_left';
                        $iconTopLeft['type'] = 'image';
                        $iconSingle = array();
                        $iconSingle['width'] = isset($v['small_left_icon_size']['width']) ? $v['small_left_icon_size']['width'] : '';
                        $iconSingle['height'] = isset($v['small_left_icon_size']['height']) ? $v['small_left_icon_size']['height'] : '';
                        $iconSingle['img'] = array(
                            '1080' => $v['small_left_icon'],
                        );
                        $iconTopLeft['single'] = $iconSingle;
                    }
                    if (!empty($v['big_left_icon'])) {
                        $iconTopLeft['position'] = 'top_left';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = isset($v['big_left_icon_size']['width']) ? $v['big_left_icon_size']['width'] : '';
                        $iconDouble['height'] = isset($v['big_left_icon_size']['height']) ? $v['big_left_icon_size']['height'] : '';
                        $iconDouble['img'] = array(
                            '1080' => $v['big_left_icon'],
                        );
                        $iconTopLeft['double'] = $iconDouble;
                    }
                    if (isset($v['countries_icon_single']) && !empty($v['countries_icon_single'])) {
                        $iconTopLeft['position'] = 'top_right';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = '96';
                        $iconDouble['height'] = '36';
                        $iconDouble['img'] = array(
                            '1080' => $v['countries_icon_single'],
                        );
                        $iconTopLeft['single'] = $iconDouble;
                    }
                    if (isset($v['countries_icon_double']) && !empty($v['countries_icon_double'])) {
                        $iconTopLeft['position'] = 'top_right';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = '99';
                        $iconDouble['height'] = '81';
                        $iconDouble['img'] = array(
                            '1080' => $v['countries_icon_double'],
                        );
                        $iconTopLeft['double'] = $iconDouble;
                    }
                }
                if (!empty($iconTopLeft)) {
                    $tmpComponent['icons'][] = $iconTopLeft;
                }
                // 中间icon
                if (isset($v['has_short_video']) && $v['has_short_video'] == '1') {
                    $iconMiddle['position'] = 'middle';
                    $iconMiddle['type'] = 'image';
                    if ($platform == 'iphone') {
                        $iconMiddle['single'] = array();
                        $iconMiddle['single']['width'] = isset($style['icons']['middle']['image']['img_small_width']) ? $style['icons']['middle']['image']['img_small_width'] : '';
                        $iconMiddle['single']['height'] = isset($style['icons']['middle']['image']['img_small_height']) ? $style['icons']['middle']['image']['img_small_height'] : '';
                        $iconMiddle['single']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/743201b9df6b969d7e2b07ad8dbe513a.png');
                        $iconMiddle['double'] = array();
                        $iconMiddle['double']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                        $iconMiddle['double']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                        $iconMiddle['double']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                    } elseif ($platform == 'android') {
                        $iconMiddle['single'] = array();
                        $iconMiddle['single']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                        $iconMiddle['single']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                        $iconMiddle['single']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                        $iconMiddle['double'] = array();
                        $iconMiddle['double']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                        $iconMiddle['double']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                        $iconMiddle['double']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                    }
                    $tmpComponent['icons'][] = $iconMiddle;
                }
                // 底部icon
                if (isset($v['status']) && in_array($v['status'], array('soldout', 'expired', 'offshelf'))) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'status';
                    $iconBottom['desc'] = '已抢光';
                    $iconBottom['font_color'] = $style['icons']['bottom']['status']['soldout_font_color'];
                    $iconBottom['background_color'] = $style['icons']['bottom']['status']['soldout_background_color'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['status']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['status']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                } elseif (isset($v['selling_forms']) && $v['selling_forms'] == 'presale' && isset($v['saved_amount']) && $v['saved_amount'] >= 5) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'promotion';
                    $iconBottom['descriptions_list'] = array('比平时省 ¥' . $v['saved_amount']);
                    $iconBottom['font_color'] = $style['icons']['bottom']['promotion']['saved_font_color'];
                    $iconBottom['background_color'] = $style['icons']['bottom']['promotion']['saved_background_color'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['promotion']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['promotion']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                } elseif (!empty($v['icon_promo_info']) && is_array($v['icon_promo_info'])) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'promotion';
                    $iconBottom['font_color'] = $style['icons']['bottom']['promotion']['font_color'];
                    $iconBottom['background_color'] = $style['icons']['bottom']['promotion']['background_color'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['promotion']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['promotion']['font_size_double'];
                    $iconBottom['descriptions_list'] = $v['icon_promo_info'];
                    $tmpComponent['icons'][] = $iconBottom;
                } elseif ((!empty($v['single_festival_title']) || !empty($v['double_festival_title'])) && !empty($v['festival_price'])) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'festival';
                    $iconBottom['festival_small_left'] = isset($v['single_festival_title']) ? $v['single_festival_title'] : '';
                    $iconBottom['festival_big_left'] = isset($v['double_festival_title']) ? $v['double_festival_title'] : '';
                    $iconBottom['festival_left'] = isset($v['single_festival_title']) ? $v['single_festival_title'] : '';   // 兼容安卓4.4
                    $iconBottom['festival_right'] = $v['festival_price'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['festival']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['festival']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                }

                // 商品图片
                $tmpComponent['img'] = array();
                $tmpComponent['img']['single'] = !empty($v['image_url_set']) ? $v['image_url_set'] : '';
                $tmpComponent['img']['double'] = !empty($v['image_url_set']) ? $v['image_url_set'] : '';

                // 商品标题
                $tmpComponent['title'] = array();
                if (isset($v['service_counters_type']) && $v['service_counters_type'] > 0) {
                    if (1 === $v['service_counters_type']) {
                        $tmpComponent['title'][] = array(
                            'desc' => '专柜自提',
                            'type' => 'mark1',
                            'font_color' => $style['title']['mark1']['font_color'],
                            'background_color' => $style['title']['mark1']['background_color'],
                        );
                    } elseif (2 === $v['service_counters_type']) {
                        $tmpComponent['title'][] = array(
                            'desc' => '专柜发货',
                            'type' => 'mark1',
                            'font_color' => $style['title']['mark1']['font_color'],
                            'background_color' => $style['title']['mark1']['background_color'],
                        );
                    } elseif ((1 + 2) === $v['service_counters_type']) {
                        $tmpComponent['title'][] = array(
                            'desc' => '专柜购',
                            'type' => 'mark1',
                            'font_color' => $style['title']['mark1']['font_color'],
                            'background_color' => $style['title']['mark1']['background_color'],
                        );
                    }
                } elseif (!empty($v['name_tag']['authorization'])) {
                    // 官方授权or邀新团（之前会有逻辑判定是否为邀新团，覆盖文案存于authorization）
                    $tmpComponent['title'][] = array(
                        'desc' => $v['name_tag']['authorization'],
                        'type' => 'mark1',
                        'font_color' => $style['title']['mark1']['font_color'],
                        'background_color' => $style['title']['mark1']['background_color'],
                    );
                } elseif (!empty($v['is_proprietary'])) {
                    $tmpComponent['title'][] = array(
                        'desc' => '自营',
                        'type' => 'mark1',
                        'font_color' => $style['title']['mark1']['font_color'],
                        'background_color' => $style['title']['mark1']['background_color'],
                    );
                }
                // 预热or预售or几人团
                if (!empty($v['name_tag']['pre_or_presale'])) {
                    $tmpComponent['title'][] = array(
                        'desc' => $v['name_tag']['pre_or_presale'],
                        'type' => 'mark2',
                        'font_color' => $style['title']['mark2']['font_color'],
                        'background_color' => $style['title']['mark2']['background_color'],
                    );
                }
                // 折扣
                if (isset($v['discount']) &&
                    $v['discount'] > 0 &&
                    false === self::shouldHideMarketPrice(
                        isset($v['market_price']) ? $v['market_price'] : 0,
                        isset($v['jumei_price']) ? $v['jumei_price'] : 0,
                        $v['discount']
                    )
                ) {
                    $tmpComponent['title'][] = array(
                        'desc' => $v['discount'] . '折/',
                        'type' => 'header',
                        'font_color' => $style['title']['header']['font_color'],
                        'background_color' => $style['title']['header']['background_color'],
                    );
                }
                // 商品短标题
                if (!empty($v['short_name'])) {
                    $tmpComponent['title'][] = array(
                        'desc' => $v['short_name'],
                        'type' => 'main',
                        'font_color' => $style['title']['main']['font_color'],
                        'background_color' => $style['title']['main']['background_color'],
                    );
                }

                // Tag签
                $tmpComponent['tag'] = array();

                if (isset($v['service_counters_type'])) {
                    if (2 === ($v['service_counters_type'] & 2)) {
                        $tmpComponent['tag'][] = array(
                            'desc' => '专柜发货',
                            'font_color' => $style['tag']['font_color'],
                        );
                    }

                    if (1 === ($v['service_counters_type'] & 1)) {
                        $tmpComponent['tag'][] = array(
                            'desc' => '专柜自提',
                            'font_color' => $style['tag']['font_color'],
                        );
                    }
                }

                // 特卖
                if (isset($v['is_deal']) && $v['is_deal'] == 1) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '特卖',
                        'font_color' => $style['tag']['font_color'],
                    );
                }
                // 香港/澳门直邮、极速免税
                if (isset($v['shipping_system_id']) && $v['shipping_system_id'] == 2754) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '香港直邮',
                        'font_color' => $style['tag']['font_color'],
                    );
                } elseif (isset($v['shipping_system_id']) && $v['shipping_system_id'] == 2967) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '澳门直邮',
                        'font_color' => $style['tag']['font_color'],
                    );
                } elseif (strpos($v['type'], 'global') !== false) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '极速免税',
                        'font_color' => $style['tag']['font_color'],
                    );
                }
                // 带防伪码
                if (!empty($v['aca_alliance']) || !empty($v['aca_brand'])) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '带防伪码',
                        'font_color' => $style['tag']['font_color'],
                    );
                }
                if (isset($v['sku_last_sale_time']) && $v['sku_last_sale_time'] > 0 && !in_array($v['type'], array('global_combination_deal', 'global_combination_mall'))) {
                    $desc = self::getFirstUpTimeDesc($v['sku_last_sale_time']);
                    if ($desc) {
                        $tmpComponent['tag'][] = array(
                            'desc' => $desc,
                            'font_color' => $style['tag']['font_color'],
                        );
                    }
                }
                $tmpComponent['sku_last_sale_time'] = isset($v['sku_last_sale_time']) ? $v['sku_last_sale_time'] : null;
                $tmpComponent['tag'] = array_slice($tmpComponent['tag'], 0, 3); // 最多取3个标签

                // 价格
                $tmpComponent['price'] = array();
                // 预售展示订金和总价、非预售展示聚美价和划线价
                if (isset($v['selling_forms']) && $v['selling_forms'] == 'presale') {
                    $tmpComponent['price']['jumei_price'] = array(
                        'price' => array(
                            'desc' => $v['presale_price'],
                            'font_color' => $style['price']['jumei_price']['price_font_color'],
                            'font_size' => $style['price']['jumei_price']['price_font_size'],
                            'point_size' => $style['price']['jumei_price']['price_point_size'],
                        ),
                        'content' => array(
                            'desc' => '订金',
                            'font_color' => $style['price']['jumei_price']['content_font_color'],
                            'font_size' => $style['price']['jumei_price']['content_font_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $style['price']['jumei_price']['unit_font_color'],
                            'font_size' => $style['price']['jumei_price']['unit_font_size'],
                        ),
                        'ui_type' => '0',
                    );
                    $tmpComponent['price']['market_price'] = array(
                        'price' => array(
                            'desc' => $v['jumei_price'],
                            'font_color' => $style['price']['market_price']['presale_price_font_color'],
                            'font_size' => $style['price']['market_price']['presale_price_font_size'],
                            'point_size' => $style['price']['market_price']['presale_price_point_size'],
                        ),
                        'content' => array(
                            'desc' => '总价',
                            'font_color' => $style['price']['market_price']['content_font_color'],
                            'font_size' => $style['price']['market_price']['presale_content_font_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $style['price']['market_price']['presale_unit_font_color'],
                            'font_size' => $style['price']['market_price']['presale_unit_font_size'],
                        ),
                        'ui_type' => '0',
                    );
                } else {
                    $tmpComponent['price']['jumei_price'] = array(
                        'price' => array(
                            'desc' => $v['jumei_price'],
                            'font_color' => $style['price']['jumei_price']['price_font_color'],
                            'font_size' => $style['price']['jumei_price']['price_font_size'],
                            'point_size' => $style['price']['jumei_price']['price_point_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $style['price']['jumei_price']['unit_font_color'],
                            'font_size' => $style['price']['jumei_price']['unit_font_size'],
                        ),
                        'ui_type' => '0',
                    );
                    if (!empty($v['market_price']) && $v['market_price'] > 0) {
                        $tmpComponent['price']['market_price'] = array(
                            'price' => array(
                                'desc' => $v['market_price'],
                                'font_color' => $style['price']['market_price']['price_font_color'],
                                'font_size' => $style['price']['market_price']['price_font_size'],
                                'point_size' => $style['price']['market_price']['price_point_size'],
                            ),
                            'unit' => array(
                                'desc' => '¥',
                                'font_color' => $style['price']['market_price']['unit_font_color'],
                                'font_size' => $style['price']['market_price']['unit_font_size'],
                            ),
                            'ui_type' => '1',
                        );
                    }
                }

                // promo标签
                $tmpComponent['promo'] = array();
                // 促销
                if (!empty($v['promo']) && is_array($v['promo'])) {
                    // 重新排序
                    self::promoReSort($v['promo']);
                    $promoNum = 0;
                    foreach ($v['promo'] as $v_promo) {
                        if ($promoNum >= 3) break;
                        if (!empty($v_promo['simple_name'])) {
                            $tmpComponent['promo'][] = array(
                                'desc' => $v_promo['simple_name'],
                                'type' => 'cycle',
                                'font_color' => $style['promo']['cycle']['font_color'],
                            );
                            $promoNum++;
                        }
                    }
                }
                // 包邮
                if (isset($v['policy']) && $v['policy'] == '1') {
                    $tmpComponent['promo'][] = array(
                        'desc' => '包邮',
                        'type' => 'cycle',
                        'font_color' => $style['promo']['cycle']['font_color'],
                    );
                }
                if (!empty($v['single_package_price'])) {
                    // 紧急修复Android促销BUG
                    if (VersionCtrl::isNotSuportPromoCapsule()) {
                        $tmpComponent['promo'][] = array(
                            'desc' => '单件|¥' . $v['single_package_price'],
                            'type' => 'capsule',
                            'font_color' => $style['promo']['capsule']['font_color'],
                        );
                    }
                }

                // 加购按钮（店铺加购新逻辑，暂时不上）
                $tmpComponent['add_icon'] = array();
                if (
                    false &&
                    !empty($v['item_id']) && in_array($v['status'], array('onsell')) &&
                    !empty($v['type']) && $v['type'] != 'red_envelope' &&
                    isset($v['selling_forms']) && !in_array($v['selling_forms'], array('presale', 'yqt')) &&
                    isset($v['show_purchase_button']) && $v['show_purchase_button'] == '1'
                ) {
                    // 按钮类型
                    $tmpComponent['add_icon']['type'] = 'add_cart_plus';
                    if (isset($v['status']) && $v['status'] == 'wish') {
                        $tmpComponent['add_icon']['type'] = 'add_wish_plus';
                    } elseif (!empty($v['is_dm'])) {
                        $tmpComponent['add_icon']['type'] = 'direct_pay_plus';
                    }
                    // 拼接加购scheme
                    $add_cart_scheme = 'jumeimall://page/add-cart?item_id=' . $v['item_id'] . '&type=' . $v['type'];
                    // @TODO加购车是否需要弹出直邮提示，不需要直邮提示，可以不传参数
                    if (isset($v['shipping_system_id']) && in_array($v['shipping_system_id'], array('2754', '2967'))) {
                        $add_cart_scheme .= '&is_dm=1';
                    } else {
                        $add_cart_scheme .= '&is_dm=0';
                    }
                    // 是否预售
                    if (isset($v['selling_forms']) && $v['selling_forms'] == 'presale') {
                        $add_cart_scheme .= '&is_presell=1';
                    } else {
                        $add_cart_scheme .= '&is_presell=0';
                    }
                    // 是否直接结算
                    if (isset($v['settling_accounts_forms']) && $v['settling_accounts_forms'] == 'direct_pay') {
                        $add_cart_scheme .= '&is_directpay=1';
                    } else {
                        $add_cart_scheme .= '&is_directpay=0';
                    }
                    // 心愿商品加入心愿单设置闹钟
                    if (isset($v['status']) && $v['status'] == 'wish' && !empty($v['start_time'])) {
                        $add_cart_scheme .= '&start_time=' . $v['start_time'];
                    }
                    // 商品状态
                    if (isset($v['status']) && in_array($v['status'], array('wish', 'onsell', 'soldout', 'expired', 'offshelf'))) {
                        $add_cart_scheme .= '&pro_status=' . $v['status'];
                    }
                    // 埋点
                    $tag_ids = !empty($v['tag_ids']) && is_array($v['tag_ids']) ? 'tag:' . implode(',', $v['tag_ids']) : '';
                    $add_cart_scheme .= '&sell_type=' . $sellType;
                    $add_cart_scheme .= '&sell_label=' . $sellLabel;
                    $_sellParams = empty($sellParams) ? $tag_ids : trim($sellParams . '|' . $tag_ids, '|');
                    if (isset($v['service_counters_type'])) {
                        $_sellParams .= (empty($_sellParams) ? '' : '|') .
                            Product::getSellParamsForShoppe($v['type'], $v['service_counters_type']);
                    }

                    if (!VersionCtrl::unSupportClientIPhone44()) {
                        $add_cart_scheme .= '&sellparams=' . $_sellParams;
                    }

                    $tmpComponent['add_icon']['scheme'] = $add_cart_scheme;
                }

                // Tips
                $tmpComponent['tips'] = array('single' => array(), 'double' => array());
                if (isset($v['selling_forms']) && $v['selling_forms'] == 'yqt') {
                    if (!empty($v['yqt_single_price_desc'])) {
                        $tmpComponent['tips']['single'][] = array(
                            'position' => 'left1',
                            'desc' => $v['yqt_single_price_desc'],
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                        $tmpComponent['tips']['double'][] = array(
                            'position' => 'left1',
                            'desc' => $v['yqt_single_price_desc'],
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                    $v['yqt_buyer_number_desc'] = isset($v['yqt_buyer_number_desc']) ? $v['yqt_buyer_number_desc'] : '';
                    $desc = self::getDealCommentsNumberProductDesc(array('deal_comments_number' => $v['deal_comments_number'], 'total_sales_number' => $v['fake_total_sales_number']), $v['yqt_buyer_number_desc'], 'store');
                    if (!empty($desc)) {
                        $tmpComponent['tips']['single'][] = array(
                            'position' => 'left2',
                            'desc' => $desc == '30' ? $v['yqt_buyer_number_desc'] : $desc,
                            'font_color' => $style['tips']['left2']['font_color'],
                            'icon' => '',
                        );
                        $tmpComponent['tips']['double'][] = array(
                            'position' => 'right',
                            'desc' => $desc == '30' ? $v['yqt_buyer_number_desc'] : $desc,
                            'font_color' => $style['tips']['right']['font_color'],
                            'icon' => '',
                        );
                    }
                } else {
                    $v['product_desc'] = isset($v['product_desc']) ? $v['product_desc'] : '';
                    $desc = self::getDealCommentsNumberProductDesc(array('deal_comments_number' => $v['deal_comments_number'], 'total_sales_number' => $v['fake_total_sales_number']), $v['product_desc'], 'store');
                    if (!empty($desc)) {
                        $tmpComponent['tips']['single'][] = array(
                            'position' => 'left1',
                            'desc' => $desc == '30' ? $v['product_desc'] : $desc,
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                        $tmpComponent['tips']['double'][] = array(
                            'position' => 'left1',
                            'desc' => $desc == '30' ? $v['product_desc'] : $desc,
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                }

                // 补充信息，原样抛回给客户端
                $tmpComponent['app_owen_data'] = JMRegistry::get('app_owen_data');
                $list[] = $tmpComponent;
            }
        }
        return $list;
    }

    /**
     * 首次上架时间组件化.
     *
     * @param integer $firstUpTime 时间戳.
     * @param integer $days        Days.
     *
     * @return string.
     */
    public static function getFirstUpTimeDesc($firstUpTime = 0, $days = 10)
    {
        $desc = '';
        if (empty($firstUpTime)) {
            return $desc;
        }

        // 校验传入时间与当前时间相差天数
        $time1 = time();
        $n = ceil(($time1 - $firstUpTime) / 86400); // 60s*60min*24h
        if ($n <= $days) {
            $desc = '新品上架';
        }
        return $desc;

    }

    /**
     * 组件化促销排序.
     *
     * @param array &$promoArr PromoArr.
     *
     *  @return void
     */
    public static function promoReSort(&$promoArr)
    {
        if (!empty($promoArr) && is_array($promoArr)) {
            foreach ($promoArr as &$v_promo) {
                if (empty($v_promo['type'])) {
                    continue;
                }
                if ($v_promo['type'] == 'reduce') {
                    $v_promo['component_sort'] = '1';
                } elseif ($v_promo['type'] == 'reduce_no_cap') {
                    $v_promo['component_sort'] = '2';
                } elseif ($v_promo['type'] == 'reduce_price') {
                    $v_promo['component_sort'] = '3';
                } elseif ($v_promo['type'] == 'reduce_num') {
                    $v_promo['component_sort'] = '4';
                } elseif ($v_promo['type'] == 'discount_for_Nth') {
                    $v_promo['component_sort'] = '5';
                } elseif ($v_promo['type'] == 'buy_x_free_y') {
                    $v_promo['component_sort'] = '6';
                } elseif ($v_promo['type'] == 'over_specialoffer') {
                    $v_promo['component_sort'] = '7';
                } elseif ($v_promo['type'] == 'over_qty_offer_best_price') {
                    $v_promo['component_sort'] = '8';
                } elseif ($v_promo['type'] == 'card') {
                    $v_promo['component_sort'] = '9';
                } elseif ($v_promo['type'] == 'card_no_cap') {
                    $v_promo['component_sort'] = '10';
                } elseif ($v_promo['type'] == 'gift') {
                    $v_promo['component_sort'] = '11';
                }
            }
            usort($promoArr, 'self::psort');
        }
    }

    /**
     * 搜索折扣组件化.
     *
     * @param array $v             V.
     * @param array &$tmpComponent TmpComponent.
     * @param array $style         Style.
     *
     * @return void
     */
    public static function getSearchDiscountComponent($v, &$tmpComponent, $style)
    {
        if (isset($v['discount']) && $v['discount'] > 0) {
            $jumei_price = isset($v['jumei_price']) ? $v['jumei_price'] : 0;
            $market_price = isset($v['market_price']) ? $v['market_price'] : 0;
            if (self::shouldHideMarketPrice($market_price, $jumei_price, $v['discount']) === false) {
                $tmpComponent['title'][] = array(
                    'desc' => $v['discount'] . '折/',
                    'type' => 'header',
                    'font_color' => $style['title']['header']['font_color'],
                    'background_color' => $style['title']['header']['background_color'],
                );
            }
        }
    }

    /**
     * 专场组件.
     *
     * @param array $data    Data.
     * @param array $style   Style.
     * @param array $extInfo ExtInfo.
     *
     * @return array.
     */
    public static function componentForActivityData($data, $style, $extInfo)
    {
        $list = array();
        if (!empty($data) && is_array($data)) {
            $platform = JMRegistry::get('platform');
            $sellType = 'activity_native';
            $sellLabel = isset($extInfo['activityInfo']['symbol']) ? $extInfo['activityInfo']['symbol'] : '';
            $sellParams = !empty($extInfo['modelInfo']['page_id']) ? 'page_id:' . $extInfo['modelInfo']['page_id'] : '';
            $sellParams = MProduct::addSellParams($sellParams);

            foreach ($data as $k => $v) {
                $tmpComponent = array();

                // 商品基本信息
                $tmpComponent['type'] = 'product';
                $tmpComponent['info'] = array(
                    'type' => isset($v['type']) ? $v['type'] : '',
                    'item_id' => isset($v['item_id']) ? $v['item_id'] : '',
                    'selling_forms' => isset($v['selling_forms']) ? $v['selling_forms'] : '',
                );

                // 商品icon
                $tmpComponent['icons'] = array();
                $iconTopLeft = $iconTopRight = $iconMiddle = $iconBottom = array();
                // 顶部icon
                if ($platform == 'iphone') {
                    // 顶部icon
                    if (isset($v['top_icon']) && $v['top_icon'] == 1) {
                        $iconTopLeft['position'] = 'top_left';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = isset($style['icons']['top_left']['top_icon']['width']) ? $style['icons']['top_left']['top_icon']['width'] : '';
                        $iconDouble['height'] = isset($style['icons']['top_left']['top_icon']['height']) ? $style['icons']['top_left']['top_icon']['height'] : '';
                        $iconDouble['img'] = array(
                            '1200' => 'http://mp5.jmstatic.com/mobile/api/icon/top.png',
                        );
                        $iconTopLeft['double'] = $iconDouble;
                    } else {
                        // 大促icon
                        if (!empty($v['single_bigdev_small_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconSingle = array();
                            $iconSingle['width'] = isset($v['single_bigdev_small_icon_size']['width']) ? $v['single_bigdev_small_icon_size']['width'] : '';
                            $iconSingle['height'] = isset($v['single_bigdev_small_icon_size']['height']) ? $v['single_bigdev_small_icon_size']['height'] : '';
                            $iconSingle['img'] = array(
                                '1200' => $v['single_bigdev_small_icon'],
                            );
                            $iconTopLeft['single'] = $iconSingle;
                        }
                        if (!empty($v['double_bigdev_big_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconDouble = array();
                            $iconDouble['width'] = isset($v['double_bigdev_big_icon_size']['width']) ? $v['double_bigdev_big_icon_size']['width'] : '';
                            $iconDouble['height'] = isset($v['double_bigdev_big_icon_size']['height']) ? $v['double_bigdev_big_icon_size']['height'] : '';
                            $iconDouble['img'] = array(
                                '1200' => $v['double_bigdev_big_icon'],
                            );
                            $iconTopLeft['double'] = $iconDouble;
                        }
                    }
                    // 国旗
                    if (!empty($v['countries'])) {
                        $singleUrl = \Lib\Image::getAreaFlagImagePath($v['countries'], 'component11');
                        $doubleUrl = \Lib\Image::getAreaFlagImagePath($v['countries'], 'component12');

                        $iconTopRight['position'] = 'top_right';
                        $iconTopRight['type'] = 'image';
                        $iconSingle = array();
                        $iconSingle['width'] = '46';
                        $iconSingle['height'] = '24';
                        $iconSingle['img'] = $singleUrl;

                        $iconDouble = array();
                        $iconDouble['width'] = '99';
                        $iconDouble['height'] = '81';
                        $iconDouble['img'] = $doubleUrl;

                        $iconTopRight['single'] = $iconSingle;
                        $iconTopRight['double'] = $iconDouble;
                    }

                } elseif ($platform == 'android') {
                    // 顶部icon
                    if (isset($v['top_icon']) && $v['top_icon'] == 1) {
                        $iconTopLeft['position'] = 'top_left';
                        $iconTopLeft['type'] = 'image';
                        $iconDouble = array();
                        $iconDouble['width'] = isset($style['icons']['top_left']['top_icon']['width']) ? $style['icons']['top_left']['top_icon']['width'] : '';
                        $iconDouble['height'] = isset($style['icons']['top_left']['top_icon']['height']) ? $style['icons']['top_left']['top_icon']['height'] : '';
                        $iconDouble['img'] = array(
                            '1080' => 'http://mp5.jmstatic.com/mobile/api/icon/top.png',
                        );
                        $iconTopLeft['double'] = $iconDouble;
                    } else {
                        // 大促icon
                        if (!empty($v['small_left_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconSingle = array();
                            $iconSingle['width'] = isset($v['small_left_icon_size']['width']) ? $v['small_left_icon_size']['width'] : '';
                            $iconSingle['height'] = isset($v['small_left_icon_size']['height']) ? $v['small_left_icon_size']['height'] : '';
                            $iconSingle['img'] = array(
                                '1080' => $v['small_left_icon'],
                            );
                            $iconTopLeft['single'] = $iconSingle;
                        }
                        if (!empty($v['big_left_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconDouble = array();
                            $iconDouble['width'] = isset($v['big_left_icon_size']['width']) ? $v['big_left_icon_size']['width'] : '';
                            $iconDouble['height'] = isset($v['big_left_icon_size']['height']) ? $v['big_left_icon_size']['height'] : '';
                            $iconDouble['img'] = array(
                                '1080' => $v['big_left_icon'],
                            );
                            $iconTopLeft['double'] = $iconDouble;
                        }
                    }

                    // 国旗
                    if (!empty($v['countries'])) {
                        $singleUrl = \Lib\Image::getAreaFlagImagePath($v['countries'], 'component11');
                        $doubleUrl = \Lib\Image::getAreaFlagImagePath($v['countries'], 'component12');

                        $iconTopRight['position'] = 'top_right';
                        $iconTopRight['type'] = 'image';
                        $iconSingle = array();
                        $iconSingle['width'] = '96';
                        $iconSingle['height'] = '36';
                        $iconSingle['img'] = $singleUrl;
                        $iconDouble = array();
                        $iconDouble['width'] = '99';
                        $iconDouble['height'] = '81';
                        $iconDouble['img'] = $doubleUrl;


                        $iconTopRight['single'] = $iconSingle;
                        $iconTopRight['double'] = $iconDouble;
                    }
                }
                if (!empty($iconTopLeft)) {
                    $tmpComponent['icons'][] = $iconTopLeft;
                }
                if (!empty($iconTopRight)) {
                    $tmpComponent['icons'][] = $iconTopRight;
                }
                // 中间icon
                if (isset($v['has_short_video']) && $v['has_short_video'] == '1') {
                    $iconMiddle['position'] = 'middle';
                    $iconMiddle['type'] = 'image';
                    if ($platform == 'iphone') {
                        $iconMiddle['single'] = array();
                        $iconMiddle['single']['width'] = isset($style['icons']['middle']['image']['img_small_width']) ? $style['icons']['middle']['image']['img_small_width'] : '';
                        $iconMiddle['single']['height'] = isset($style['icons']['middle']['image']['img_small_height']) ? $style['icons']['middle']['image']['img_small_height'] : '';
                        $iconMiddle['single']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/743201b9df6b969d7e2b07ad8dbe513a.png');
                        $iconMiddle['double'] = array();
                        $iconMiddle['double']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                        $iconMiddle['double']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                        $iconMiddle['double']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                    } elseif ($platform == 'android') {
                        $iconMiddle['single'] = array();
                        $iconMiddle['single']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                        $iconMiddle['single']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                        $iconMiddle['single']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                        $iconMiddle['double'] = array();
                        $iconMiddle['double']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                        $iconMiddle['double']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                        $iconMiddle['double']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                    }
                    $tmpComponent['icons'][] = $iconMiddle;
                }
                // 底部icon
                if (isset($v['status']) && in_array($v['status'], array('soldout', 'expired', 'offshelf'))) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'status';
                    $iconBottom['desc'] = '已抢光';
                    $iconBottom['font_color'] = $style['icons']['bottom']['status']['soldout_font_color'];
                    $iconBottom['background_color'] = $style['icons']['bottom']['status']['soldout_background_color'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['status']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['status']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                } elseif (isset($v['selling_forms']) && $v['selling_forms'] == 'presale' && isset($v['saved_amount']) && $v['saved_amount'] >= 5) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'promotion';
                    $iconBottom['descriptions_list'] = array('比平时省 ¥' . $v['saved_amount']);
                    $iconBottom['font_color'] = $style['icons']['bottom']['promotion']['saved_font_color'];
                    $iconBottom['background_color'] = $style['icons']['bottom']['promotion']['saved_background_color'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['promotion']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['promotion']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                } elseif (!empty($v['icon_promo_info']) && is_array($v['icon_promo_info'])) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'promotion';
                    $iconBottom['descriptions_list'] = $v['icon_promo_info'];
                    $iconBottom['font_color'] = $style['icons']['bottom']['promotion']['font_color'];
                    $iconBottom['background_color'] = $style['icons']['bottom']['promotion']['background_color'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['promotion']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['promotion']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                } elseif ((!empty($v['single_festival_title']) || !empty($v['double_festival_title'])) && !empty($v['festival_price'])) {
                    $iconBottom['position'] = 'bottom';
                    $iconBottom['type'] = 'festival';
                    $iconBottom['festival_small_left'] = isset($v['single_festival_title']) ? $v['single_festival_title'] : '';
                    $iconBottom['festival_big_left'] = isset($v['double_festival_title']) ? $v['double_festival_title'] : '';
                    $iconBottom['festival_left'] = isset($v['single_festival_title']) ? $v['single_festival_title'] : '';   // 兼容安卓4.4
                    $iconBottom['festival_right'] = $v['festival_price'];
                    $iconBottom['font_size_single'] = $style['icons']['bottom']['festival']['font_size_single'];
                    $iconBottom['font_size_double'] = $style['icons']['bottom']['festival']['font_size_double'];
                    $tmpComponent['icons'][] = $iconBottom;
                }

                // 商品图片
                $tmpComponent['img'] = array();
                $tmpComponent['img']['single'] = !empty($v['image_url_set']['single']['url']) ? $v['image_url_set']['single']['url'] : '';
                $tmpComponent['img']['double'] = !empty($v['image_url_set']['single']['url']) ? $v['image_url_set']['single']['url'] : '';

                // 商品标题
                $tmpComponent['title'] = array();
                if (isset($v['service_counters_type']) && $v['service_counters_type'] > 0) {
                    if (1 === $v['service_counters_type']) {
                        $tmpComponent['title'][] = array(
                            'desc' => '专柜自提',
                            'type' => 'mark1',
                            'font_color' => $style['title']['mark1']['font_color'],
                            'background_color' => $style['title']['mark1']['background_color'],
                        );
                    } elseif (2 === $v['service_counters_type']) {
                        $tmpComponent['title'][] = array(
                            'desc' => '专柜发货',
                            'type' => 'mark1',
                            'font_color' => $style['title']['mark1']['font_color'],
                            'background_color' => $style['title']['mark1']['background_color'],
                        );
                    } elseif ((1 + 2) === $v['service_counters_type']) {
                        $tmpComponent['title'][] = array(
                            'desc' => '专柜购',
                            'type' => 'mark1',
                            'font_color' => $style['title']['mark1']['font_color'],
                            'background_color' => $style['title']['mark1']['background_color'],
                        );
                    }
                } elseif (!empty($v['name_tag']['authorization'])) {
                    // 官方授权or邀新团（之前会有逻辑判定是否为邀新团，覆盖文案存于authorization）
                    $tmpComponent['title'][] = array(
                        'desc' => $v['name_tag']['authorization'],
                        'type' => 'mark1',
                        'font_color' => $style['title']['mark1']['font_color'],
                        'background_color' => $style['title']['mark1']['background_color'],
                    );
                }
                // 预热or预售or几人团
                if (!empty($v['name_tag']['pre_or_presale'])) {
                    $tmpComponent['title'][] = array(
                        'desc' => $v['name_tag']['pre_or_presale'],
                        'type' => 'mark2',
                        'font_color' => $style['title']['mark2']['font_color'],
                        'background_color' => $style['title']['mark2']['background_color'],
                    );
                }
                // 折扣
                if (isset($v['discount']) &&
                    $v['discount'] > 0 &&
                    false === self::shouldHideMarketPrice(
                        isset($v['market_price']) ? $v['market_price'] : 0,
                        isset($v['jumei_price']) ? $v['jumei_price'] : 0,
                        $v['discount']
                    )
                ) {
                    $tmpComponent['title'][] = array(
                        'desc' => $v['discount'] . '折/',
                        'type' => 'header',
                        'font_color' => $style['title']['header']['font_color'],
                        'background_color' => $style['title']['header']['background_color'],
                    );
                }
                // 商品短标题
                if (!empty($v['short_name'])) {
                    $tmpComponent['title'][] = array(
                        'desc' => $v['short_name'],
                        'type' => 'main',
                        'font_color' => $style['title']['main']['font_color'],
                        'background_color' => $style['title']['main']['background_color'],
                    );
                }

                // Tag签
                $tmpComponent['tag'] = array();
                if (isset($v['service_counters_type'])) {
                    if (2 === ($v['service_counters_type'] & 2)) {
                        $tmpComponent['tag'][] = array(
                            'desc' => '专柜发货',
                            'font_color' => $style['tag']['font_color'],
                        );
                    }

                    if (1 === ($v['service_counters_type'] & 1)) {
                        $tmpComponent['tag'][] = array(
                            'desc' => '专柜自提',
                            'font_color' => $style['tag']['font_color'],
                        );
                    }
                }

                // 特卖
                if (isset($v['is_deal']) && $v['is_deal'] == 1) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '特卖',
                        'font_color' => $style['tag']['font_color'],
                    );
                }
                // 香港/澳门直邮、极速免税
                if (isset($v['shipping_system_id']) && $v['shipping_system_id'] == 2754) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '香港直邮',
                        'font_color' => $style['tag']['font_color'],
                    );
                } elseif (isset($v['shipping_system_id']) && $v['shipping_system_id'] == 2967) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '澳门直邮',
                        'font_color' => $style['tag']['font_color'],
                    );
                } elseif (strpos($v['type'], 'global') !== false) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '极速免税',
                        'font_color' => $style['tag']['font_color'],
                    );
                }
                // 带防伪码
                if (!empty($v['aca_alliance']) || !empty($v['aca_brand'])) {
                    $tmpComponent['tag'][] = array(
                        'desc' => '带防伪码',
                        'font_color' => $style['tag']['font_color'],
                    );
                }
                // 新品上架
                if (isset($v['sku_last_sale_time']) && $v['sku_last_sale_time'] > 0 && !in_array($v['type'], array('global_combination_deal', 'global_combination_mall'))) {
                    $desc = self::getFirstUpTimeDesc($v['sku_last_sale_time']);
                    if ($desc) {
                        $tmpComponent['tag'][] = array(
                            'desc' => $desc,
                            'font_color' => $style['tag']['font_color'],
                        );
                    }
                }
                $tmpComponent['tag'] = array_slice($tmpComponent['tag'], 0, 3); // 最多取3个标签

                // 价格
                $tmpComponent['price'] = array();
                // 预售展示订金和总价、非预售展示聚美价和划线价
                if (isset($v['selling_forms']) && $v['selling_forms'] == 'presale') {
                    $tmpComponent['price']['jumei_price'] = array(
                        'price' => array(
                            'desc' => $v['presale_price'],
                            'font_color' => $style['price']['jumei_price']['price_font_color'],
                            'font_size' => $style['price']['jumei_price']['price_font_size'],
                            'point_size' => $style['price']['jumei_price']['price_point_size'],
                        ),
                        'content' => array(
                            'desc' => '订金',
                            'font_color' => $style['price']['jumei_price']['content_font_color'],
                            'font_size' => $style['price']['jumei_price']['content_font_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $style['price']['jumei_price']['unit_font_color'],
                            'font_size' => $style['price']['jumei_price']['unit_font_size'],
                        ),
                        'ui_type' => '0',
                    );
                    $tmpComponent['price']['market_price'] = array(
                        'price' => array(
                            'desc' => $v['jumei_price'],
                            'font_color' => $style['price']['market_price']['presale_price_font_color'],
                            'font_size' => $style['price']['market_price']['presale_price_font_size'],
                            'point_size' => $style['price']['market_price']['presale_price_point_size'],
                        ),
                        'content' => array(
                            'desc' => '总价',
                            'font_color' => $style['price']['market_price']['content_font_color'],
                            'font_size' => $style['price']['market_price']['presale_content_font_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $style['price']['market_price']['presale_unit_font_color'],
                            'font_size' => $style['price']['market_price']['presale_unit_font_size'],
                        ),
                        'ui_type' => '0',
                    );
                } else {
                    $tmpComponent['price']['jumei_price'] = array(
                        'price' => array(
                            'desc' => $v['jumei_price'],
                            'font_color' => $style['price']['jumei_price']['price_font_color'],
                            'font_size' => $style['price']['jumei_price']['price_font_size'],
                            'point_size' => $style['price']['jumei_price']['price_point_size'],
                        ),
                        'unit' => array(
                            'desc' => '¥',
                            'font_color' => $style['price']['jumei_price']['unit_font_color'],
                            'font_size' => $style['price']['jumei_price']['unit_font_size'],
                        ),
                        'ui_type' => '0',
                    );
                    if (!empty($v['market_price']) && $v['market_price'] > 0) {
                        $tmpComponent['price']['market_price'] = array(
                            'price' => array(
                                'desc' => $v['market_price'],
                                'font_color' => $style['price']['market_price']['price_font_color'],
                                'font_size' => $style['price']['market_price']['price_font_size'],
                                'point_size' => $style['price']['market_price']['price_point_size'],
                            ),
                            'unit' => array(
                                'desc' => '¥',
                                'font_color' => $style['price']['market_price']['unit_font_color'],
                                'font_size' => $style['price']['market_price']['unit_font_size'],
                            ),
                            'ui_type' => '1',
                        );
                    }
                }

                // promo标签
                $tmpComponent['promo'] = array();
                // 促销
                if (!empty($v['promo']) && is_array($v['promo'])) {
                    // 重新排序
                    self::promoReSort($v['promo']);
                    $promoNum = 0;
                    foreach ($v['promo'] as $v_promo) {
                        if ($promoNum >= 3) break;
                        if (!empty($v_promo['simple_name'])) {
                            $tmpComponent['promo'][] = array(
                                'desc' => $v_promo['simple_name'],
                                'type' => 'cycle',
                                'font_color' => $style['promo']['cycle']['font_color'],
                            );
                            $promoNum++;
                        }
                    }
                }
                // 包邮
                if (isset($v['policy']) && $v['policy'] == '1') {
                    $tmpComponent['promo'][] = array(
                        'desc' => '包邮',
                        'type' => 'cycle',
                        'font_color' => $style['promo']['cycle']['font_color'],
                    );
                }
                if (!empty($v['single_package_price'])) {
                    // 紧急修复Android促销BUG
                    if (VersionCtrl::isNotSuportPromoCapsule()) {
                        $tmpComponent['promo'][] = array(
                            'desc' => '单件|¥' . $v['single_package_price'],
                            'type' => 'capsule',
                            'font_color' => $style['promo']['capsule']['font_color'],
                        );
                    }
                }

                // 加购按钮
                $tmpComponent['add_icon'] = array();
                if (
                    !empty($v['item_id']) && in_array($v['status'], array('onsell')) &&
                    !empty($v['type']) && $v['type'] != 'red_envelope' &&
                    isset($v['selling_forms']) && !in_array($v['selling_forms'], array('presale', 'yqt')) &&
                    (!isset($v['show_category']) || $v['show_category'] != 'seckill') &&
                    isset($v['show_purchase_button']) && $v['show_purchase_button'] == '1'
                ) {
                    // 按钮类型
                    $tmpComponent['add_icon']['type'] = 'add_cart_plus';
                    if (isset($v['status']) && $v['status'] == 'wish') {
                        $tmpComponent['add_icon']['type'] = 'add_wish_plus';
                    } elseif (!empty($v['is_dm'])) {
                        $tmpComponent['add_icon']['type'] = 'direct_pay_plus';
                    }
                    // 拼接加购scheme
                    $add_cart_scheme = 'jumeimall://page/add-cart?item_id=' . $v['item_id'] . '&type=' . $v['type'];
                    // @TODO加购车是否需要弹出直邮提示，不需要直邮提示，可以不传参数
                    if (isset($v['shipping_system_id']) && in_array($v['shipping_system_id'], array('2754', '2967'))) {
                        $add_cart_scheme .= '&is_dm=1';
                    } else {
                        $add_cart_scheme .= '&is_dm=0';
                    }
                    // 是否预售
                    if (isset($v['selling_forms']) && $v['selling_forms'] == 'presale') {
                        $add_cart_scheme .= '&is_presell=1';
                    } else {
                        $add_cart_scheme .= '&is_presell=0';
                    }
                    // 是否直接结算
                    if (isset($v['settling_accounts_forms']) && $v['settling_accounts_forms'] == 'direct_pay') {
                        $add_cart_scheme .= '&is_directpay=1';
                    } else {
                        $add_cart_scheme .= '&is_directpay=0';
                    }
                    // 心愿商品加入心愿单设置闹钟
                    if (isset($v['status']) && $v['status'] == 'wish' && !empty($v['start_time'])) {
                        $add_cart_scheme .= '&start_time=' . $v['start_time'];
                    }
                    // 商品状态
                    if (isset($v['status']) && in_array($v['status'], array('wish', 'onsell', 'soldout', 'expired', 'offshelf'))) {
                        $add_cart_scheme .= '&pro_status=' . $v['status'];
                    }
                    // 埋点
                    $tag_ids = !empty($v['tag_ids']) && is_array($v['tag_ids']) ? 'tag:' . implode(',', $v['tag_ids']) : '';

                    $add_cart_scheme .= '&sell_type=' . $sellType;
                    $add_cart_scheme .= '&sell_label=' . $sellLabel;
                    $_sellParams = empty($sellParams) ? $tag_ids : trim($sellParams . '|' . $tag_ids, '|');
                    if (isset($v['service_counters_type'])) {
                        $_sellParams .= (empty($_sellParams) ? '' : '|') .
                            Product::getSellParamsForShoppe($v['type'], $v['service_counters_type']);
                    }

                    if (!VersionCtrl::unSupportClientIPhone44()) {
                        $add_cart_scheme .= '&sellparams=' . $_sellParams;
                    }

                    $tmpComponent['add_icon']['scheme'] = $add_cart_scheme;
                }

                // Tips
                $tmpComponent['tips'] = array('single' => array(), 'double' => array());
                if (isset($v['selling_forms']) && $v['selling_forms'] == 'yqt') {
                    if (!empty($v['yqt_single_price_desc'])) {
                        $tmpComponent['tips']['single'][] = array(
                            'position' => 'left1',
                            'desc' => $v['yqt_single_price_desc'],
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                        $tmpComponent['tips']['double'][] = array(
                            'position' => 'left1',
                            'desc' => $v['yqt_single_price_desc'],
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                    $v['yqt_buyer_number_desc'] = isset($v['yqt_buyer_number_desc']) ? $v['yqt_buyer_number_desc'] : '';
                    $desc = self::getDealCommentsNumberProductDesc(array('deal_comments_number' => $v['deal_comments_number'], 'total_sales_number' => $v['fake_total_sales_number']), $v['yqt_buyer_number_desc'], 'shelf');
                    if (!empty($desc)) {
                        $tmpComponent['tips']['single'][] = array(
                            'position' => 'left2',
                            'desc' => $desc == '30' ? $v['yqt_buyer_number_desc'] : $desc,
                            'font_color' => $style['tips']['left2']['font_color'],
                            'icon' => '',
                        );
                        $tmpComponent['tips']['double'][] = array(
                            'position' => 'right',
                            'desc' => $desc == '30' ? $v['yqt_buyer_number_desc'] : $desc,
                            'font_color' => $style['tips']['right']['font_color'],
                            'icon' => '',
                        );
                    }
                } else {
                    $v['product_desc'] = isset($v['product_desc']) ? $v['product_desc'] : '';
                    $desc = self::getDealCommentsNumberProductDesc(array('deal_comments_number' => $v['deal_comments_number'], 'total_sales_number' => $v['fake_total_sales_number']), $v['product_desc'], 'shelf');
                    if (!empty($desc)) {
                        $tmpComponent['tips']['single'][] = array(
                            'position' => 'left1',
                            // 'desc' => $v['product_desc'],
                            'desc' => $desc == '30' ? $v['product_desc'] : $desc,
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                        $tmpComponent['tips']['double'][] = array(
                            'position' => 'left1',
                            // 'desc' => $v['product_desc'],
                            'desc' => $desc == '30' ? $v['product_desc'] : $desc,
                            'font_color' => $style['tips']['left1']['font_color'],
                            'icon' => '',
                        );
                    }
                }
                // scheme.
                $tmpComponent['scheme'] = isset($v['url_schema']) ? $v['url_schema'] : '';
                // 补充信息，原样抛回给客户端.
                $tmpComponent['app_owen_data'] = JMRegistry::get('app_owen_data');
                $list[] = $tmpComponent;
            }
        }
        return $list;
    }

    /**
     * V4.1版促销信息处理.
     *
     * @param array  $product Product.
     * @param array  $promos  Promos.
     * @param string $item    Item.
     *
     * @return array.
     */
    public static function promoFormatv41($product, $promos, $item = '')
    {
        if ((isset($product['status']) && $product['status'] != 'wish' &&
                $product['status'] != 'onsell') || !isset($product['promo'])) {
            $product['promo_info'] = array();
            $product['promo'] = array();
            return $product;
        }

        if (!empty($promos)) {
            $promoList = array(
                '不封顶满减' => 0,
                '满减' => 0,
                '金额满就折' => 2,
                '第二件打折' => 3,
                '件数满就折' => 4,
                '满X免Y件' => 5,
                'X元Y件' => 6,
                '不封顶满返' => 7,
                '满返' => 7,
            );
            $promoStrs = array_keys($promoList);
            $sortPromo = array();

            $item = empty($item) ? $product['item_id'] . '_' . $product['type'] : $item;
            $promo = isset($promos[$item]) ? $promos[$item] : array();
            foreach ($promo as $key => $value) {
                $value['full_type_name'] = trim($value['full_type_name']);
                if (!in_array($value['full_type_name'], $promoStrs)) {
                    continue;
                }

                if (!isset($sortPromo[$promoList[$value['full_type_name']]])) {
                    $sortPromo[$promoList[$value['full_type_name']]] = array();
                }

                if ($value['full_type_name'] == '不封顶满减' ||
                    $value['full_type_name'] == '不封顶满返') {
                    $sortPromo[$promoList[$value['full_type_name']]] = array_merge($value['short_desc_all'], $sortPromo[$promoList[$value['full_type_name']]]);
                } else {
                    if (in_array($promoList[$value['full_type_name']], array(0, 7))) {
                        $sortPromo[$promoList[$value['full_type_name']]] = array_merge($sortPromo[$promoList[$value['full_type_name']]], $value['short_desc_all']);
                    } elseif (in_array($promoList[$value['full_type_name']], array(2, 3, 4))) {
                        $sortPromo[$promoList[$value['full_type_name']]] = array(array_shift($value['short_desc_all']));
                    } else {
                        $sortPromo[$promoList[$value['full_type_name']]] = array_merge($sortPromo[$promoList[$value['full_type_name']]], $value['short_desc_all']);
                    }
                }
            }

            ksort($sortPromo);
            $promo = array_shift($sortPromo);
            if (count($promo) > 1) {
                $product['promo_info'] = $promo;
            } else {
                $product['promo_info'] = empty($promo) ? array() : $promo;
            }
        } else {
            $product['promo_info'] = array();
        }

        return $product;
    }

    /**
     * Mock41大促.
     *
     * @param array $product Product.
     *
     * @return array.
     */
    public static function mockv41dachu($product)
    {
        if (VersionCtrl::supportClientV41() && isset($product['promo_info']) && !empty($product['promo_info'])) {
            $product['single_festival_title'] = ''; // 一行一列
            $product['double_festival_title'] = ''; // 一行两列
            $product['festival_price'] = '';
        }
        return $product;
    }

    /**
     * 排序.
     *
     * @param array $promoA PromoA.
     * @param array $promoB PromoB.
     *
     * @return integer.
     */
    private static function psort($promoA, $promoB)
    {
        // 权重为空，置到末尾
        if (empty($promoA['component_sort']) && !empty($promoB['component_sort'])) {
            return 1;
        } elseif (!empty($promoA['component_sort']) && empty($promoB['component_sort'])) {
            return -1;
        }
        return ($promoA['component_sort'] < $promoB['component_sort']) ? -1 : 1;
    }

    /**
     * D 5.9版本新促销信息组件化处理.
     *
     * @param array  $product Product.
     *
     * @param array  $promos  Promos.
     *
     * @param string $item    Item.
     *
     * @return string
     */
    public static function promoComponentFormatNew($product, $promos, $item = '')
    {
        $iconPromoInfo = '';
        if (!empty($promos)) {
            $sortPromo = array();
            if (empty($item) && isset($product['type'])) {
                if (in_array($product['type'], array('global_mall', 'global_pop_mall'))) {
                    $item = isset($product['product_id']) ? $product['product_id'].'_'.$product['type'] : '';
                } else {
                    $item = isset($product['item_id']) ? $product['item_id'].'_'.$product['type'] : '';
                }
            }
            $promo = isset($promos[$item]) ? $promos[$item] : array();

            // 增加促销节点 只取第一个促销
            if (isset($promo[0]['short_desc_search']) && !empty($promo[0]['short_desc_search'] )) {
                $iconPromoInfo = $promo[0]['short_desc_search'];
            }
        }
        return $iconPromoInfo;
    }

    /**
     * 列表组件化（搜索专场店铺） 只有某些状态下才会进行如下处理.
     *
     * @param array  $data      Data.
     *
     * @param string $from_type From_type.
     *
     * @param array  $extInfo   ExtInfo.
     *
     * @return mixed
     */
    public static function formatDataListByComponent($data, $from_type, $extInfo = array())
    {
        if (!empty($data)) {
            $style_map = self::getStyleConfigByComponent(); // 获取样式配置
            switch ($from_type) {
                case 'search':
                    $data = self::componentForSearchData($data, $style_map, $extInfo);
                    break;
                case 'activity':
                    $data = self::componentForActivityData($data, $style_map, $extInfo);
                    break;
                case 'store':
                    $data = self::componentForStoreData($data, $style_map);
                    break;
            }
        }
        return $data;
    }

    /**
     * 搜索组件.
     *
     * @param array $data    Data.
     *
     * @param array $style   Style.
     *
     * @param array $extInfo ExtInfo.
     *
     * @return array
     */
    public static function componentForSearchData($data, $style, $extInfo)
    {
        $isShowSelfOrPop = LUtil::getConfig('common','isShowSelfOrPop');
        $list = array();
        if (!empty($data) && is_array($data)) {
            $platform = JMRegistry::get('platform');
            $sellType = isset($extInfo['sellType']) ? $extInfo['sellType'] : '';
            $sellLabel = isset($extInfo['sellLabel']) ? $extInfo['sellLabel'] : '';
            if (empty($sellType)) {
                if (!empty($extInfo['store_id'])) {
                    $sellType = 'store_native';
                    $sellLabel = is_array($extInfo['store_id']) ? current($extInfo['store_id']) : $extInfo['store_id'];
                } else {
                    $sellType = 'mSearch';
                    $sellLabel = !empty($extInfo['search']) ? $extInfo['search'] : '';
                }
            }
            if (empty($sellLabel)) {
                if ($sellType == 'store_native' && !empty($extInfo['store_id'])) {
                    $sellLabel = is_array($extInfo['store_id']) ? current($extInfo['store_id']) : $extInfo['store_id'];
                } else {
                    $sellLabel = !empty($extInfo['search']) ? $extInfo['search'] : '';
                }
            }
            // 添加购物车时使用 需要这个tag_id
            $sellParams = !empty($extInfo['sell_params']) ? $extInfo['sell_params'] : '';
            $sellParams = MProduct::addSellParams($sellParams);

            // 图片库设置平台
            Image::setPlatform($platform);

            // 组件处理
            foreach ($data as $k => $v) {
                $tmpComponent = array();
                if (isset($v['is_activity']) && $v['is_activity'] == '1') {
                    // 普通专场信息
                    $tmpComponent['type'] = 'activity';
                    $tmpComponent['info'] = array(
                        'activity_id' => isset($v['id']) ? $v['id'] : '',
                    );
                    $tmpComponent['img'] = array(
                        'single' => isset($v['single_image_url']) ? $v['single_image_url'] : '',
                        'double' => isset($v['double_image_url']) ? $v['double_image_url'] : '',
                    );
                    $tmpComponent['scheme'] = isset($v['link']) ? $v['link'] : '';
                    $tmpComponent['sku_last_sale_time'] = isset($v['sku_last_sale_time']) ? $v['sku_last_sale_time'] : 0;
                    // 榜单专场 增加字段特殊处理
                    if (isset($v['item_type']) && $v['item_type'] == 'agg_act') {
                        $tmpComponent['item_type'] = 'agg_act'; // 重写活动类型
                        $tmpComponent['activity_type'] = isset($v['activity_type']) ? $v['activity_type'] : '';
                        // 短标题
                        if (!empty($v['index_main_title'])) {
                            $tmpComponent['title'][] = array(
                                'desc' => $v['index_main_title'],
                                'type' => 'main',
                                'font_color' => $style['title']['main']['font_color'],
                                'background_color' => $style['title']['main']['background_color'],
                            );
                        }
                        if (isset($v['meta_text']) && !empty($v['meta_text'])) {
                            $tag_list = explode(',', $v['meta_text']);
                            if (!empty($tag_list)) {
                                foreach ($tag_list as $k1 => $v1) {
                                    $temTag = array(
                                        'desc' => $v1,
                                        'font_color' => $style['tag']['font_color'],
                                    );
                                    $tmpComponent['tag'][] = $temTag;
                                }
                            }
                        }
                        $tmpComponent['sale_promotion_word'] = isset($v['sale_promotion_word']) ? $v['sale_promotion_word'] : '';
                        $tmpComponent['score'] = isset($v['score']) ? $v['score'] : 0;
                        $tmpComponent['detail_word'] = isset($v['detail_word']) ? $v['detail_word'] : '';
                    }
                } elseif (isset($v['type']) && $v['type'] == 'shopping_guide') {
                    $tmpComponent = $v;
                } elseif (isset($v['type']) && $v['type'] == 'extended_searchword') {
                    $tmpComponent = $v;
                } elseif (isset($v['type']) && $v['type'] == 'tiezi') {
                    $tmpComponent = array();

                    $sell_param = 'list_source:' . $extInfo['search_tab'] . '|post_type:' . $v['post_type'];
                    $_sellParams = empty($sellParams) ? $sell_param : trim($sellParams.'|'.$sell_param , '|');
                    $scheme = '&sell_type=' . $sellType .'&sell_label=' . $sellLabel . '&sell_param=' . $_sellParams;

                    // 公共部分
                    $tmpComponent['item_id'] = $v['item_id'];
                    $tmpComponent['type'] = $v['post_type'];
                    $tmpComponent['detail_scheme'] = $v['detail_scheme'] . $scheme;

                    // 用户部分 用户头像\用户加V头像\用户昵称
                    $tmpComponent['user_info'] = array(
                        'uid' => $v['uid'],
                        'avatar'   => !empty($v['avatar']) ? $v['avatar'] : (object)array(),
                        // 'vip_logo' => !empty($v['vip_logo']) ? $v['vip_logo'] : (object)array(),
                        // 'auth_logo' => !empty($v['auth_logo']) ? $v['auth_logo'] : (object)array(),
                        'user_name' => isset($v['nickname']) ? $v['nickname'] : '',
                        'user_scheme' => 'jumeimall://page/socialotheruser?userid='.$v['uid'],
                    );

                    // 交互部分
                    $tmpComponent['mutual'] = array(
                        'praise_count' => $v['praise_count'], // 点赞数
                        'is_praise'    => $v['is_praise'], // 心愿状态
                    );

                    // 发布时间
                    $tmpComponent['creat_time'] = date('Y-m-d',$v['create_time']);

                    // 帖子标题
                    $tmpComponent['title'] = $v['title'];
                    // 帖子内容
                    $tmpComponent['content'] = $v['description'];

                    // 帖子视频
                    $tmpComponent['video'] = array(
                        'cover_url' => (object)$v['major_pic'],
                        'width' => $v['video_w'],
                        'height' => $v['video_h'],
                        'scheme' => $v['detail_scheme'],
                        'video_url' => $v['video_url'],
                        'is_auto_play' => LUtil::getConfig('common','tiezi_video_auto_play'), // 1自动播放,0不自动播放
                    );

                    // 帖子图片 缩略图
                    $tmpComponent['small_img'] = isset($v['img_url']['small_img']) && isset($v['img_url']['small_img'][0]) ? $v['img_url']['small_img'][0] : (object)array();
                    // 帖子图片 大图
                    $tmpComponent['large_img'] = isset($v['img_url']['large_img']) && isset($v['img_url']['large_img'][0]) ? $v['img_url']['large_img'][0] : (object)array();

                    $result[] = $tmpComponent;

                } else {
                    // 预处理折扣
                    MProduct::formatHidePrice($v);
                    // 商品基本信息
                    $tmpComponent['type'] = 'product';
                    $tmpComponent['info'] = array(
                        'type' => isset($v['type']) ? $v['type'] : '',
                        'item_id' => isset($v['item_id']) ? $v['item_id'] : '',
                        'selling_forms' => isset($v['selling_forms']) ? $v['selling_forms'] : '',
                    );
                    if (isset($v['shoppe_product_id']) && !empty($v['shoppe_product_id'])) {
                        $tmpComponent['shoppe_list'] = array(
                            'shoppe_num' => array(
                                'desc' => '查看在售专柜',
                                'font_color' => '#333333',
                                'font_size' => '11',
                                'schema' => 'jumeimall://page/shoppelist?from=mSearch&product_id=' . $v['shoppe_product_id'],
                            ),
                        );
                    }

                    // 商品icon
                    $tmpComponent['icons'] = array();
                    $tmpComponent['sku_last_sale_time'] = isset($v['sku_last_sale_time']) ? $v['sku_last_sale_time'] : 0;
                    $iconTopLeft = $iconTopRight = $iconMiddle = $iconBottom = array();

                    // 国旗图标右上角
                    if (!empty($v['countries']) && $v['countries'] > 0) {
                        $iconTopRight['position'] = 'top_right';
                        $iconTopRight['type'] = 'image';

                        $singleUrl = MProduct::getAreaFlagImagePath($v['countries'], 'component11');
                        $doubleUrl = MProduct::getAreaFlagImagePath($v['countries'], 'component12');

                        if ($platform === 'iphone') {
                            $iconTopRight['single'] = array(
                                'width' => '63',
                                'height' => '36',
                                'img' => array(
                                    '1200' => end($singleUrl),
                                ),
                            );
                            $iconTopRight['double'] = array(
                                'width' => '99',
                                'height' => '81',
                                'img' => array(
                                    '1200' => end($doubleUrl),
                                ),
                            );
                        }

                        if ($platform === 'android') {
                            $iconTopRight['single'] = array(
                                'width' => '54',
                                'height' => '36',
                                'img' => array(
                                    '1080' => end($singleUrl),
                                ),
                            );
                            $iconTopRight['double'] = array(
                                'width' => '99',
                                'height' => '81',
                                'img' => array(
                                    '1080' => end($doubleUrl),
                                ),
                            );
                        }

                        $tmpComponent['icons'][] = $iconTopRight;
                    }

                    if ($platform == 'iphone') {
                        // 顶部icon
                        if (!empty($v['single_bigdev_small_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconSingle = array();
                            $iconSingle['width'] = isset($v['single_bigdev_small_icon_size']['width']) ? $v['single_bigdev_small_icon_size']['width'] : '';
                            $iconSingle['height'] = isset($v['single_bigdev_small_icon_size']['height']) ? $v['single_bigdev_small_icon_size']['height'] : '';
                            $iconSingle['img'] = array(
                                '1200' => $v['single_bigdev_small_icon'],
                            );
                            $iconTopLeft['single'] = $iconSingle;
                        }
                        if (!empty($v['double_bigdev_big_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconDouble = array();
                            $iconDouble['width'] = isset($v['double_bigdev_big_icon_size']['width']) ? $v['double_bigdev_big_icon_size']['width'] : '';
                            $iconDouble['height'] = isset($v['double_bigdev_big_icon_size']['height']) ? $v['double_bigdev_big_icon_size']['height'] : '';
                            $iconDouble['img'] = array(
                                '1200' => $v['double_bigdev_big_icon'],
                            );
                            $iconTopLeft['double'] = $iconDouble;
                        }
                    } elseif ($platform == 'android') {
                        // 顶部icon
                        if (!empty($v['small_left_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconSingle = array();
                            $iconSingle['width'] = isset($v['small_left_icon_size']['width']) ? $v['small_left_icon_size']['width'] : '';
                            $iconSingle['height'] = isset($v['small_left_icon_size']['height']) ? $v['small_left_icon_size']['height'] : '';
                            $iconSingle['img'] = array(
                                '1080' => $v['small_left_icon'],
                            );
                            $iconTopLeft['single'] = $iconSingle;
                        }
                        if (!empty($v['big_left_icon'])) {
                            $iconTopLeft['position'] = 'top_left';
                            $iconTopLeft['type'] = 'image';
                            $iconDouble = array();
                            $iconDouble['width'] = isset($v['big_left_icon_size']['width']) ? $v['big_left_icon_size']['width'] : '';
                            $iconDouble['height'] = isset($v['big_left_icon_size']['height']) ? $v['big_left_icon_size']['height'] : '';
                            $iconDouble['img'] = array(
                                '1080' => $v['big_left_icon'],
                            );
                            $iconTopLeft['double'] = $iconDouble;
                        }
                    }
                    if (!empty($iconTopLeft)) {
                        $tmpComponent['icons'][] = $iconTopLeft;
                    }
                    // 中间icon
                    if (isset($v['has_short_video']) && $v['has_short_video'] == '1') {
                        $iconMiddle['position'] = 'middle';
                        $iconMiddle['type'] = 'image';
                        if ($platform == 'iphone') {
                            $iconMiddle['single'] = array();
                            $iconMiddle['single']['width'] = isset($style['icons']['middle']['image']['img_small_width']) ? $style['icons']['middle']['image']['img_small_width'] : '';
                            $iconMiddle['single']['height'] = isset($style['icons']['middle']['image']['img_small_height']) ? $style['icons']['middle']['image']['img_small_height'] : '';
                            $iconMiddle['single']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/743201b9df6b969d7e2b07ad8dbe513a.png');
                            $iconMiddle['double'] = array();
                            $iconMiddle['double']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                            $iconMiddle['double']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                            $iconMiddle['double']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                        } elseif ($platform == 'android') {
                            $iconMiddle['single'] = array();
                            $iconMiddle['single']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                            $iconMiddle['single']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                            $iconMiddle['single']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                            $iconMiddle['double'] = array();
                            $iconMiddle['double']['width'] = isset($style['icons']['middle']['image']['img_big_width']) ? $style['icons']['middle']['image']['img_big_width'] : '';
                            $iconMiddle['double']['height'] = isset($style['icons']['middle']['image']['img_big_height']) ? $style['icons']['middle']['image']['img_big_height'] : '';
                            $iconMiddle['double']['img'] = array('1200' => 'http://p12.jmstatic.com/mcms/c9f22158622c5b69ca364875d7374b37.png');
                        }
                        $tmpComponent['icons'][] = $iconMiddle;
                    }
                    // 底部icon
                    if (isset($v['status']) && in_array($v['status'], array('soldout', 'expired', 'offshelf'))) {
                        $iconBottom['position'] = 'bottom';
                        $iconBottom['type'] = 'status';
                        $iconBottom['desc'] = '已抢光';
                        $iconBottom['font_color'] = $style['icons']['bottom']['status']['soldout_font_color'];
                        $iconBottom['background_color'] = $style['icons']['bottom']['status']['soldout_background_color'];
                        $iconBottom['font_size_single'] = $style['icons']['bottom']['status']['font_size_single'];
                        $iconBottom['font_size_double'] = $style['icons']['bottom']['status']['font_size_double'];
                        $tmpComponent['icons'][] = $iconBottom;
                    } elseif (!VersionCtrl::isSupportClientV59() && isset($v['selling_forms']) && $v['selling_forms'] == 'presale' && isset($v['saved_amount']) && $v['saved_amount'] >= 5) {
                        $iconBottom['position'] = 'bottom';
                        $iconBottom['type'] = 'promotion';
                        $iconBottom['descriptions_list'] = array('比平时省 ¥'.$v['saved_amount']);
                        $iconBottom['font_color'] = $style['icons']['bottom']['promotion']['saved_font_color'];
                        $iconBottom['background_color'] = $style['icons']['bottom']['promotion']['saved_background_color'];
                        $iconBottom['font_size_single'] = $style['icons']['bottom']['promotion']['font_size_single'];
                        $iconBottom['font_size_double'] = $style['icons']['bottom']['promotion']['font_size_double'];
                        $tmpComponent['icons'][] = $iconBottom;
                    } elseif (!VersionCtrl::isSupportClientV59() && !empty($v['icon_promo_info']) && is_array($v['icon_promo_info'])) {
                        $iconBottom['position'] = 'bottom';
                        $iconBottom['type'] = 'promotion';
                        $iconBottom['descriptions_list'] = $v['icon_promo_info'];
                        $iconBottom['font_color'] = $style['icons']['bottom']['promotion']['font_color'];
                        $iconBottom['background_color'] = $style['icons']['bottom']['promotion']['background_color'];
                        $iconBottom['font_size_single'] = $style['icons']['bottom']['promotion']['font_size_single'];
                        $iconBottom['font_size_double'] = $style['icons']['bottom']['promotion']['font_size_double'];
                        $tmpComponent['icons'][] = $iconBottom;
                    } elseif ((!empty($v['single_festival_title']) || !empty($v['double_festival_title'])) && !empty($v['festival_price'])) {
                        $iconBottom['position'] = 'bottom';
                        $iconBottom['type'] = 'festival';
                        $iconBottom['festival_small_left'] = isset($v['single_festival_title']) ? $v['single_festival_title'] : '';
                        $iconBottom['festival_big_left'] = isset($v['double_festival_title']) ? $v['double_festival_title'] : '';
                        $iconBottom['festival_left'] = isset($v['single_festival_title']) ? $v['single_festival_title'] : '';   // 兼容安卓4.4
                        $iconBottom['festival_right'] = $v['festival_price'];
                        $iconBottom['font_size_single'] = $style['icons']['bottom']['festival']['font_size_single'];
                        $iconBottom['font_size_double'] = $style['icons']['bottom']['festival']['font_size_double'];
                        $tmpComponent['icons'][] = $iconBottom;
                    }

                    // 商品图片
                    $tmpComponent['img'] = array();
                    $tmpComponent['img']['single'] = !empty($v['image_url_set']['single']['url']) ? $v['image_url_set']['single']['url'] : '';
                    $tmpComponent['img']['double'] = !empty($v['image_url_set']['single']['url']) ? $v['image_url_set']['single']['url'] : '';

                    // 商品标题
                    $tmpComponent['title'] = array();
                    // 官方授权or邀新团（之前会有逻辑判定是否为邀新团，覆盖文案存于authorization）
                    // 专柜增加新标签    76 and 78 专柜服务    76专柜自提    78专柜发货
                    if ($isShowSelfOrPop == 0) {
                        $is_show_shoppe = MProduct::isServiceCounters($v['tag_id']);
                        if ($is_show_shoppe > 0) {
                            $shoppe_desc = "";
                            if ($is_show_shoppe == 1) {
                                $shoppe_desc = "专柜自提";
                            } elseif ($is_show_shoppe == 2) {
                                $shoppe_desc = "专柜发货";
                            } elseif ($is_show_shoppe == 3) {
                                $shoppe_desc = "专柜购";
                            }
                            $tmpComponent['title'][] = array(
                                'desc' => $shoppe_desc,
                                'type' => 'mark1',
                                'font_color' => $style['title']['mark1']['font_color'],
                                'background_color' => $style['title']['mark1']['background_color'],
                            );
                        } elseif (!empty($v['is_proprietary']) && isset($v['selling_forms']) && $v['selling_forms'] != 'yqt') {
                            $tmpComponent['title'][] = array(
                                'desc' => '自营',
                                'type' => 'mark1',
                                'font_color' => $style['title']['mark1']['font_color'],
                                'background_color' => $style['title']['mark1']['background_color'],
                            );
                        } elseif (!empty($v['name_tag']['authorization'])) {
                            $tmpComponent['title'][] = array(
                                'desc' => $v['name_tag']['authorization'],
                                'type' => 'mark1',
                                'font_color' => $style['title']['mark1']['font_color'],
                                'background_color' => $style['title']['mark1']['background_color'],
                            );
                        }
                    }

                    if ($isShowSelfOrPop == 1) {
                        $is_show_shoppe = MProduct::isServiceCounters($v['tag_id']);
                        if ($is_show_shoppe > 0) {
                            $shoppe_desc = "";
                            if ($is_show_shoppe == 1) {
                                $shoppe_desc = "专柜自提";
                            } elseif ($is_show_shoppe == 2) {
                                $shoppe_desc = "专柜发货";
                            } elseif ($is_show_shoppe == 3) {
                                $shoppe_desc = "专柜购";
                            }
                            $tmpComponent['title'][] = array(
                                'desc' => $shoppe_desc,
                                'type' => 'mark1',
                                'font_color' => $style['title']['mark1']['font_color'],
                                'background_color' => $style['title']['mark1']['background_color'],
                            );
                        } else {
                            if (!empty($v['is_proprietary'])) {
                                $tmpComponent['title'][] = array(
                                    'desc' => strpos($v['type'], 'global') === false ? '自营' : '海外自营',
                                    'type' => 'mark1',
                                    'font_color' => $style['title']['mark1']['font_color'],
                                    'background_color' => $style['title']['mark1']['background_color'],
                                );
                            } else {
                                $tmpComponent['title'][] = array(
                                    'desc' => '非自营',
                                    'type' => 'mark1',
                                    'font_color' => $style['title']['mark1']['font_color'],
                                    'background_color' => $style['title']['mark1']['background_color'],
                                );
                            }
                        }
                    }

                    // 预热or预售or几人团
                    if (!empty($v['name_tag']['pre_or_presale'])) {
                        $tmpComponent['title'][] = array(
                            'desc' => $v['name_tag']['pre_or_presale'],
                            'type' => 'mark2',
                            'font_color' => $style['title']['mark2']['font_color'],
                            'background_color' => $style['title']['mark2']['background_color'],
                        );
                    }
                    // 折扣
                    self::getSearchDiscountComponent($v, $tmpComponent, $style);

                    // 搜索ab配置
                    $search_ab_config = LUtil::getConfig('common','search_ab_version_for_middle_name');
                    $ab_version = \Modules\AbTest::Instance()->getClientCaseByTag('MSearchList_1');

                    // 是否取中标题,方便测试 ,测试结束后可以删除
                    $tmpComponent['is_middle_name'] = 0;

                    // #156553 主搜列表短标题换成中标题
                    if (isset($extInfo['search_source_ex']) && !empty($extInfo['search_source_ex']) && $extInfo['search_source_ex'] == 'main_search' && VersionCtrl::isSupportMiddleNameV70()) {

                        if (isset($search_ab_config['mediumNameForAPI']) && !empty($search_ab_config['mediumNameForAPI'])) {

                            if (in_array('all',$search_ab_config['mediumNameForAPI']) || in_array($ab_version, $search_ab_config['mediumNameForAPI'])) {

                                // 商品中标题
                                if (!empty($v['middle_name'])) {
                                    $tmpComponent['is_middle_name'] = 1;
                                    $tmpComponent['title'][] = array(
                                        'desc' => $v['middle_name'],
                                        'type' => 'main',
                                        'font_color' => $style['title']['main']['font_color'],
                                        'background_color' => $style['title']['main']['background_color'],
                                    );
                                }
                            } else {
                                // 商品短标题
                                if (!empty($v['short_name'])) {
                                    $tmpComponent['title'][] = array(
                                        'desc' => $v['short_name'],
                                        'type' => 'main',
                                        'font_color' => $style['title']['main']['font_color'],
                                        'background_color' => $style['title']['main']['background_color'],
                                    );
                                }
                            }

                        } else {
                            // 商品短标题
                            if (!empty($v['short_name'])) {
                                $tmpComponent['title'][] = array(
                                    'desc' => $v['short_name'],
                                    'type' => 'main',
                                    'font_color' => $style['title']['main']['font_color'],
                                    'background_color' => $style['title']['main']['background_color'],
                                );
                            }
                        }

                    } else {
                        // 商品短标题
                        if (!empty($v['short_name'])) {
                            $tmpComponent['title'][] = array(
                                'desc' => $v['short_name'],
                                'type' => 'main',
                                'font_color' => $style['title']['main']['font_color'],
                                'background_color' => $style['title']['main']['background_color'],
                            );
                        }
                    }

                    // Tag签
                    $tmpComponent['tag'] = array();
                    $tmpComponent['tag_id'] = isset($v['tag_id']) ? $v['tag_id'] : array();

                    // #156981 卖点文案
                    $selling_point_case = \Modules\Abtest::Instance()->getClientCaseByTag('MainSearchForSellingPoint');

                    if (isset($extInfo['search_source_ex']) && $extInfo['search_source_ex'] == 'main_search' &&
                        $selling_point_case == 'a' && VersionCtrl::isSupportSellingPointV71() && !empty($v['selling_point'])
                    ) {
                        foreach ($v['selling_point'] as $sell_point) {
                            $tmpComponent['tag'][] = array(
                                'desc' => $sell_point,
                                'font_color' => $style['tag']['font_color']
                            );
                        }

                        $tmpComponent['tag'] = array_slice($tmpComponent['tag'], 0, 3); // 最多取3个标签

                    } else {

                        // 专柜自提
                        $is_show_zhuangui = MProduct::isServiceCounters($v['tag_id']);
                        // if($is_show_zhuangui && in_array($v['type'], array('global_mall'))) {
                        if ($is_show_zhuangui) {
                            if ($is_show_zhuangui == 1) {
                                $tmpComponent['tag'][] = array(
                                    'desc' => '专柜自提',
                                    'font_color' => $style['tag']['font_color'],
                                );
                            } elseif ($is_show_zhuangui == 2) {
                                $tmpComponent['tag'][] = array(
                                    'desc' => '专柜发货',
                                    'font_color' => $style['tag']['font_color'],
                                );
                            } elseif ($is_show_zhuangui == 3) {
                                $tmpComponent['tag'][] = array(
                                    'desc' => '专柜自提',
                                    'font_color' => $style['tag']['font_color'],
                                );
                                $tmpComponent['tag'][] = array(
                                    'desc' => '专柜发货',
                                    'font_color' => $style['tag']['font_color'],
                                );
                            }
                        }
                        // 特卖
                        if (isset($v['is_deal']) && $v['is_deal'] == 1) {
                            $tmpComponent['tag'][] = array(
                                'desc' => '特卖',
                                'font_color' => $style['tag']['font_color'],
                            );
                        }
                        // 香港/澳门直邮、极速免税
                        if (isset($v['shipping_system_id']) && $v['shipping_system_id'] == 2754) {
                            $tmpComponent['tag'][] = array(
                                'desc' => '香港直邮',
                                'font_color' => $style['tag']['font_color'],
                            );
                        } elseif (isset($v['shipping_system_id']) && $v['shipping_system_id'] == 2967) {
                            $tmpComponent['tag'][] = array(
                                'desc' => '澳门直邮',
                                'font_color' => $style['tag']['font_color'],
                            );
                        } elseif (strpos($v['type'], 'global') !== false) {
                            $tmpComponent['tag'][] = array(
                                'desc' => '极速免税',
                                'font_color' => $style['tag']['font_color'],
                            );
                        }

                        // 带防伪码
                        $key = 'product_attr_aca_product_id' . $v['product_id'];
                        $acaInfo = LCache::memGet($key);

                        if (!empty($acaInfo['aca_alliance']) || !empty($acaInfo['aca_brand'])) {
                            $tmpComponent['tag'][] = array(
                                'desc' => '带防伪码',
                                'font_color' => $style['tag']['font_color'],
                            );
                        }
                        // 新品上架
                        // $v['sku_last_sale_time'] = 1511263705;
                        // if (isset($v['product_id']) && $v['product_id'] > 0 && isset($v['product_reports_number']) && $v['product_reports_number'] < 5) {
                        if (isset($v['product_id']) && $v['product_id'] > 0 && isset($v['sku_last_sale_time']) && !in_array($v['type'], array('global_combination_deal', 'global_combination_mall'))) {
                            $desc = self::getFirstUpTimeDesc($v['sku_last_sale_time']);
                            if (!empty($desc)) {
                                $tmpComponent['tag'][] = array(
                                    'desc' => '新品上架',
                                    'font_color' => $style['tag']['font_color'],
                                );
                            }
                        }
                        $tmpComponent['tag'] = array_slice($tmpComponent['tag'], 0, 3); // 最多取3个标签
                    }
                    // 价格
                    $tmpComponent['price'] = array();
                    // 预售展示订金和总价、非预售展示聚美价和划线价
                    if (isset($v['selling_forms']) && $v['selling_forms'] == 'presale') {
                        $tmpComponent['price']['jumei_price'] = array(
                            'price' => array(
                                'desc' => $v['presale_price'],
                                'font_color' => $style['price']['jumei_price']['price_font_color'],
                                'font_size' => $style['price']['jumei_price']['price_font_size'],
                                'point_size' => $style['price']['jumei_price']['price_point_size'],
                            ),
                            'content' => array(
                                'desc' => '订金',
                                'font_color' => $style['price']['jumei_price']['content_font_color'],
                                'font_size' => $style['price']['jumei_price']['content_font_size'],
                            ),
                            'unit' => array(
                                'desc' => '¥',
                                'font_color' => $style['price']['jumei_price']['unit_font_color'],
                                'font_size' => $style['price']['jumei_price']['unit_font_size'],
                            ),
                            'ui_type' => '0',
                        );
                        $tmpComponent['price']['market_price'] = array(
                            'price' => array(
                                'desc' => $v['jumei_price'],
                                'font_color' => $style['price']['market_price']['presale_price_font_color'],
                                'font_size' => $style['price']['market_price']['presale_price_font_size'],
                                'point_size' => $style['price']['market_price']['presale_price_point_size'],
                            ),
                            'content' => array(
                                'desc' => '总价',
                                'font_color' => $style['price']['market_price']['content_font_color'],
                                'font_size' => $style['price']['market_price']['presale_content_font_size'],
                            ),
                            'unit' => array(
                                'desc' => '¥',
                                'font_color' => $style['price']['market_price']['presale_unit_font_color'],
                                'font_size' => $style['price']['market_price']['presale_unit_font_size'],
                            ),
                            'ui_type' => '0',
                        );
                    } else {
                        // #148789 展示专柜最低价开关打开
                        if (LUtil::getConfig('common','is_show_counter_price_on_off')) {
                            // 版本大于等于5.4
                            if (VersionCtrl::supportOver54() && !is_null($v['sku_min_price']) && $v['mall_sale_mode'] == 1) {
                                $tmpComponent['price']['jumei_price'] = array(
                                    'price' => array(
                                        'desc' => (string)$v['sku_min_price'],
                                        'font_color' => $style['price']['jumei_price']['price_font_color'],
                                        'font_size' => $style['price']['jumei_price']['price_font_size'],
                                        'point_size' => $style['price']['jumei_price']['price_point_size'],
                                    ),
                                    'unit' => array(
                                        'desc' => '¥',
                                        'font_color' => $style['price']['jumei_price']['unit_font_color'],
                                        'font_size' => $style['price']['jumei_price']['unit_font_size'],
                                    ),
                                    'ui_type' => '0',
                                );
                            } else {
                                $tmpComponent['price']['jumei_price'] = array(
                                    'price' => array(
                                        'desc' => $v['jumei_price'],
                                        'font_color' => $style['price']['jumei_price']['price_font_color'],
                                        'font_size' => $style['price']['jumei_price']['price_font_size'],
                                        'point_size' => $style['price']['jumei_price']['price_point_size'],
                                    ),
                                    'unit' => array(
                                        'desc' => '¥',
                                        'font_color' => $style['price']['jumei_price']['unit_font_color'],
                                        'font_size' => $style['price']['jumei_price']['unit_font_size'],
                                    ),
                                    'ui_type' => '0',
                                );
                            }
                        } else {
                            $tmpComponent['price']['jumei_price'] = array(
                                'price' => array(
                                    'desc' => $v['jumei_price'],
                                    'font_color' => $style['price']['jumei_price']['price_font_color'],
                                    'font_size' => $style['price']['jumei_price']['price_font_size'],
                                    'point_size' => $style['price']['jumei_price']['price_point_size'],
                                ),
                                'unit' => array(
                                    'desc' => '¥',
                                    'font_color' => $style['price']['jumei_price']['unit_font_color'],
                                    'font_size' => $style['price']['jumei_price']['unit_font_size'],
                                ),
                                'ui_type' => '0',
                            );
                        }
                        if (!empty($v['market_price']) && $v['market_price'] > 0) {
                            $tmpComponent['price']['market_price'] = array(
                                'price' => array(
                                    'desc' => $v['market_price'],
                                    'font_color' => $style['price']['market_price']['price_font_color'],
                                    'font_size' => $style['price']['market_price']['price_font_size'],
                                    'point_size' => $style['price']['market_price']['price_point_size'],
                                ),
                                'unit' => array(
                                    'desc' => '¥',
                                    'font_color' => $style['price']['market_price']['unit_font_color'],
                                    'font_size' => $style['price']['market_price']['unit_font_size'],
                                ),
                                'ui_type' => '1',
                            );
                        }
                    }

                    // promo标签
                    $tmpComponent['promo'] = array();
                    // 促销
                    if (!empty($v['promo']) && is_array($v['promo'])) {
                        // 重新排序
                        self::promoReSort($v['promo']);
                        $promoNum = 0;
                        foreach ($v['promo'] as $v_promo) {
                            if ($promoNum >= 3) break;
                            if (!empty($v_promo['simple_name'])) {
                                $tmpComponent['promo'][] = array(
                                    'desc' => $v_promo['simple_name'],
                                    'type' => 'cycle',
                                    'font_color' => $style['promo']['cycle']['font_color'],
                                );
                                $promoNum++;
                            }
                        }
                    }
                    // 包邮
                    if (isset($v['policy']) && $v['policy'] == '1') {
                        $tmpComponent['promo'][] = array(
                            'desc' => '包邮',
                            'type' => 'cycle',
                            'font_color' => $style['promo']['cycle']['font_color'],
                        );
                    }
                    if (!empty($v['single_package_price'])) {
                        // 紧急修复Android促销BUG
                        if (VersionCtrl::isNotSuportPromoCapsule()) {
                            $tmpComponent['promo'][] = array(
                                'desc' => '单件|¥'.$v['single_package_price'],
                                'type' => 'capsule',
                                'font_color' => $style['promo']['capsule']['font_color'],
                            );
                        }
                    }

                    // #149695 搜索列表页促销标签文案显示 聚合商品除外
                    if (isset($v['short_desc_search']) && !empty($v['short_desc_search']) && isset($v['mall_sale_mode']) && $v['mall_sale_mode'] != '1' ) {
                        if (VersionCtrl::isSupportClientV59()) {
                            $tmpComponent['promo'] = array(); // 清空老版本节点
                            $tmpComponent['short_desc_search'] = array(
                                'desc' => $v['short_desc_search'],
                                'type' => 'tag',
                                'font_color' => $style['promo']['tag']['font_color'],
                                'background_color' => $style['promo']['tag']['background_color'],
                            );
                        }
                    }

                    // 聚合mall不显示促销标签
                    if (isset($v['mall_sale_mode']) && $v['mall_sale_mode'] == '1') {
                        $tmpComponent['promo'] = array(); // 清空老版本节点
                        $tmpComponent['short_desc_search'] = array();
                    }

                    // 加购按钮
                    $tmpComponent['add_icon'] = array();
                    if (
                        !empty($v['item_id']) && in_array($v['status'], array('onsell')) &&
                        !empty($v['type']) && $v['type'] != 'red_envelope' &&
                        isset($v['selling_forms']) && !in_array($v['selling_forms'], array('presale', 'yqt')) &&
                        isset($v['show_purchase_button']) && $v['show_purchase_button'] == '1'
                    ) {
                        // 按钮类型
                        $tmpComponent['add_icon']['type'] = 'add_cart_plus';
                        if (isset($v['status']) && $v['status'] == 'wish') {
                            $tmpComponent['add_icon']['type'] = 'add_wish_plus';
                        } elseif (!empty($v['is_dm'])) {
                            $tmpComponent['add_icon']['type'] = 'direct_pay_plus';
                        }
                        // 拼接加购scheme
                        $add_cart_scheme = 'jumeimall://page/add-cart?item_id='.$v['item_id'].'&type='.$v['type'];
                        // @TODO加购车是否需要弹出直邮提示，不需要直邮提示，可以不传参数
                        if (isset($v['shipping_system_id']) && in_array($v['shipping_system_id'], array('2754', '2967'))) {
                            $add_cart_scheme .= '&is_dm=1';
                        } else {
                            $add_cart_scheme .= '&is_dm=0';
                        }
                        // 是否预售
                        if (isset($v['selling_forms']) && $v['selling_forms'] == 'presale') {
                            $add_cart_scheme .= '&is_presell=1';
                        } else {
                            $add_cart_scheme .= '&is_presell=0';
                        }
                        // 是否直接结算
                        if (isset($v['settling_accounts_forms']) && $v['settling_accounts_forms'] == 'direct_pay') {
                            $add_cart_scheme .= '&is_directpay=1';
                        } else {
                            $add_cart_scheme .= '&is_directpay=0';
                        }
                        // 心愿商品加入心愿单设置闹钟
                        if (isset($v['status']) && $v['status'] == 'wish' && !empty($v['start_time'])) {
                            $add_cart_scheme .= '&start_time='.$v['start_time'];
                        }
                        // 商品状态
                        if (isset($v['status']) && in_array($v['status'], array('wish', 'onsell', 'soldout', 'expired', 'offshelf'))) {
                            $add_cart_scheme .= '&pro_status='.$v['status'];
                        }

                        // 列表页 聚合商品的加购 需要在加购jumeimall链接中新增聚合详情页的product_id字段，传给购物流程
                        if (isset($v['product_id']) && (in_array('76',$v['tag_id']) || in_array('78',$v['tag_id'])) && $v['type'] == 'jumei_mall' ) {
                            $add_cart_scheme .= '&pid=' . $v['product_id'];
                        }

                        // 埋点
                        $tag_ids = !empty($v['tag_ids']) && is_array($v['tag_ids']) ? 'tag:' . implode(',', $v['tag_ids']) : '';

                        $add_cart_scheme .= '&sell_type='.$sellType;
                        // $add_cart_scheme .= '&sell_label='.$sellLabel;
                        $_sellParams = empty($sellParams) ? $tag_ids : trim($sellParams.'|'.$tag_ids , '|');

                        if (isset($extInfo['search_tab'])) {
                            $add_cart_scheme .= '&sell_label='.$sellLabel;
                            $_sellParams .= 'list_source:' . $extInfo['search_tab'] . '|post_type:product';
                        }

                        $_sellParams = self::AddCountersToSellParamsV2($v,$_sellParams,$v['tag_id'],$extInfo);

                        if (!VersionCtrl::unSupportClientIPhone44()) {
                            $add_cart_scheme .= '&sellparams='.$_sellParams;
                        }
                        $tmpComponent['add_icon']['scheme'] = $add_cart_scheme;
                    }

                    // Tips
                    $tmpComponent['tips'] = array('single' => array(), 'double' => array());
                    if (isset($v['selling_forms']) && $v['selling_forms'] == 'yqt') {
                        if (!empty($v['yqt_single_price_desc'])) {
                            $tmpComponent['tips']['single'][] = array(
                                'position' => 'left1',
                                'desc' => $v['yqt_single_price_desc'],
                                'font_color' => $style['tips']['left1']['font_color'],
                                'icon' => '',
                            );
                            $tmpComponent['tips']['double'][] = array(
                                'position' => 'left1',
                                'desc' => $v['yqt_single_price_desc'],
                                'font_color' => $style['tips']['left1']['font_color'],
                                'icon' => '',
                            );
                        }
                        $v['yqt_buyer_number_desc'] = isset($v['yqt_buyer_number_desc']) ? $v['yqt_buyer_number_desc'] : '';
                        $desc = self::getDealCommentsNumberProductDesc(array('deal_comments_number' => $v['deal_comments_number'], 'total_sales_number' => $v['fake_total_sales_number']),$v['yqt_buyer_number_desc'],'search');
                        if (!empty($desc)) {
                            $tmpComponent['tips']['single'][] = array(
                                'position' => 'left2',
                                'desc' => $desc == '30' ? $v['yqt_buyer_number_desc'] : $desc,
                                'font_color' => $style['tips']['left2']['font_color'],
                                'icon' => '',
                            );
                            $tmpComponent['tips']['double'][] = array(
                                'position' => 'right',
                                'desc' => $desc == '30' ? $v['yqt_buyer_number_desc'] : $desc,
                                'font_color' => $style['tips']['right']['font_color'],
                                'icon' => '',
                            );
                        }
                    } else {
                        $v['product_desc'] = isset($v['product_desc']) ? $v['product_desc'] : '';
                        $desc = self::getDealCommentsNumberProductDesc(array('deal_comments_number' => $v['deal_comments_number'], 'total_sales_number' => $v['fake_total_sales_number']),$v['product_desc'],'search');
                        if (!empty($desc)) {
                            $tmpComponent['tips']['single'][] = array(
                                'position' => 'left1',
                                // 'desc' => $v['product_desc'],
                                'desc' => $desc == '30' ? $v['product_desc'] : $desc,
                                'font_color' => $style['tips']['left1']['font_color'],
                                'icon' => '',
                            );
                            $tmpComponent['tips']['double'][] = array(
                                'position' => 'left1',
                                'desc' => $desc == '30' ? $v['product_desc'] : $desc,
                                'font_color' => $style['tips']['left1']['font_color'],
                                'icon' => '',
                            );
                        }
                    }
                    if (isset($v['url_scheme'])) {
                        $tmpComponent['scheme'] = $v['url_scheme'];
                    }

                }
                // tag_icon
                if (!empty($v['item_tag']) && is_array($v['item_tag']) && in_array('history_purchase',$v['item_tag'])) {
                    $tmpComponent['tag_icon'][] = array(
                        'position' => 'bottom',
                        'type' => 'mark1',
                        'desc' => '已购买商品',
                        'font_size' => '10',
                        'font_color' => '#FE4070',
                        'background_color' => '#FFFFFF',
                        'frame_color' => '#FE4070',
                    );
                }
                // 补充信息，原样抛回给客户端
                $tmpComponent['app_owen_data'] = JMRegistry::get('app_owen_data');
                // 专柜店铺信息
                if (isset($v['shoppe_name']) && !empty($v['shoppe_name'])) {
                    $shoppe_url = self::getCountersListUrl($v['shipping_system_id']);
                    $tmpComponent['shoppe_info'] = array(
                        'shoppe_name' => array(
                            'title' => $v['shoppe_name'],
                            'font_size' => '11',
                            'font_color' => '#333333'
                        ),
                        'shoppe_text' => array(
                            'title' => '进店',
                            'title_url' => $shoppe_url,
                            'font_size' => '11',
                            'font_color' => '#FE4070',

                        ),
                    );
                }
                $list[] = $tmpComponent;
            }
        }
        return $list;
    }

    /**
     * 专柜商品增加埋点.
     *
     * @param array  $v           V.
     *
     * @param string $_sellParams SellParams.
     *
     * @param array  $tag_id      Tag_id.
     *
     * @param array  $extInfo     ExtInfo.
     *
     * @return string
     */
    public static function AddCountersToSellParamsV2($v, $_sellParams, $tag_id, $extInfo)
    {
        $real_type = MDetail::getPrdType($v['item_id'], $v['type']);

        if ($real_type == 'jumei_mall' && (in_array('76', $tag_id) || in_array('78', $tag_id))) {
            $_sellParams .= '|product_type:aggregate_mall'; // 聚合mall
        }
        if (in_array($real_type, array('pop_mall', 'global_pop_mall')) && (in_array('76', $tag_id) || in_array('78', $tag_id))) {
            $_sellParams .= '|product_type:shoppe_only_mall'; // 专柜单卖mall
        }
        if (in_array('76', $tag_id) && !in_array('78', $tag_id)) {
            $_sellParams .= '|delivery_mode:self_pickup'; // 专柜自提
        } elseif (in_array('78', $tag_id) && !in_array('76', $tag_id)) {
            $_sellParams .= '|delivery_mode:shoppe_deliver'; // 专柜发货
        } elseif (in_array('76', $tag_id) && in_array('78', $tag_id)) {
            $_sellParams .= '|delivery_mode:self_pickup,shoppe_deliver'; // 专柜自提,专柜发货
        } elseif (($real_type == 'jumei_mall' || $real_type == 'global_mall') && !in_array('76', $tag_id) && !in_array('78', $tag_id)) {
            $_sellParams .= '|delivery_mode:jumei'; // 聚美发货
        }

        $show_id = isset($extInfo['show_id']) ? $extInfo['show_id'] : '';
        if (!empty($show_id)) {
            $_sellParams .= '|show_id:' . $show_id;
        }
        $show_type = isset($extInfo['show_type']) ? $extInfo['show_type'] : '';
        if (!empty($show_type)) {
            $_sellParams .= '|show_type:' . $show_type;
        }
        return $_sellParams;
    }

    /**
     * Touch 和 小程序的评论数逻辑.
     *
     * @param array  &$list List.
     *
     * @param string $type  Type.
     *
     * @return void
     */
    protected static function formatDealCommonNumForWap(array &$list, $type)
    {
        foreach ($list as &$item) {
            if (!isset($item['deal_comments_number'])) {
                break;
            }

            $kouBei = self::getDealCommentsNumberProductDesc($item, $item['product_desc'], $type);

            if ($kouBei === $item['product_desc']) {
                break;
            }

            if ('search' === $type) {
                if (self::isYqtDeal($item)) {
                    $item['time_desc'] = $kouBei;
                    // 单买价为空时，去掉 product_desc（购买人数)
                    if (empty($item['yqt_single_price_desc'])) {
                        $item['product_desc'] = '';
                    }
                } else {
                    $item['product_desc'] = $kouBei;
                }
            } elseif ($type === 'home' && 'wish' === $item['status'] && !empty($item['product_desc']) && !empty($kouBei)) {
                $item['product_desc'] = implode(' | ', array($item['product_desc'], $kouBei));
            } else {
                $item['product_desc'] = $kouBei;
            }
        }
    }

    /**
     * 根据折扣或价格差价计算是否隐藏，传折扣只传一个参数，传价格传两个参数  搜索用.
     *
     * @param float $marketPrice 折扣或者市场价.
     * @param float $jumeiPrice  售价.
     * @param float $discount    Discount.
     *
     * @return boolean
     */
    public static function shouldHideMarketPriceV2($marketPrice = 0.0, $jumeiPrice = 0.0, $discount = 0.0)
    {
        // discount 优先
        if ($discount > 0 && $discount > 9.5) {
            return true;
        }

        if ($discount > 0 && $discount <= 9.5) {
            return false;
        }

        if ($jumeiPrice > 0 && $marketPrice > 0) {
            if (($marketPrice - $jumeiPrice) >= 5) {
                return false;
            }

            $discount = $jumeiPrice / $marketPrice * 10;
            if ($discount > 0 && $discount <= 9.5) {
                return false;
            }
        }

        return true;
    }

}
