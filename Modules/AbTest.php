<?php
/**
 * AB测试.
 *
 * @author Xyh<yinghuix@jumei.com>
 */

namespace Modules;

use \Lib\Util as LUtil;
use \Lib\Cache as LCache;
use JMRegistry as JMRegistry;
use \Modules\Account as MAccount;
use \MobLib\Storage as VRedisStorage;

/**
 * AB测试.
 */
class AbTest extends Base
{
    const NORMAL = 'normal';

    protected static $Config = array();

    protected static $Map = array();

    /**
     * 实例.
     *
     * @return static
     */
    public static function Instance()
    {
        $obj = parent::Instance();
        self::getActivatedConfig();
        return $obj;
    }

    /**
     * 初始化被激活的AB测试项目.
     *
     * @return void
     */
    public static function getActivatedConfig()
    {
        $time = time();

        // 获取ab测试的配置
        $abConfig = LUtil::getApiConfig('AbTestConf');

        $platform = JMRegistry::get('platform');
        $client_v = JMRegistry::get('client_v');

        if (!empty($abConfig)) {
            foreach ($abConfig as $id => $config) {
                if (!isset($config['enable']) ||
                    !isset($config['tag']) || empty($config['tag']) ||
                    !isset($config['case']) || empty($config['case']) ||
                    !isset($config['limit']) || empty($config['limit']) ||
                    !isset($config['end_time']) || empty($config['end_time']) ||
                    !isset($config['start_time']) || empty($config['start_time']) ||
                    $config['enable'] != true ||
                    strtotime($config['end_time']) <= $time ||
                    strtotime($config['start_time']) > $time ||
                    $config['end_time'] <= $config['start_time'] ||
                    !array_key_exists($platform, $config['limit']) ||
                    (isset($config['limit'][$platform]['min']) && $client_v < $config['limit'][$platform]['min']) ||
                    (isset($config['limit'][$platform]['max']) && $client_v > $config['limit'][$platform]['max'])
                ) {
                    continue;
                }

                self::$Config[$id] = array(
                    'id' => $id,
                    'tag' => $config['tag'],
                    'case' => $config['case'],
                );

                self::$Map[$config['tag']] = $id;
            }
        }
    }

    /**
     * 根据标签获取AB测试的内容.
     *
     * @param string $tag AB测试标记.
     *
     * @return array 配置内容
     */
    public function getConfigByTag($tag)
    {
        $data = array();
        $id = isset(self::$Map[$tag]) ? self::$Map[$tag] : 0;
        if ($id) {
            $data = self::$Config[$id];
        }
        return $data;
    }

    /**
     * 获取当前tag对于分配给client的case.
     *
     * @param string $tag 标记.
     *
     * @return string
     */
    public function getClientCaseByTag($tag)
    {
        $case = self::NORMAL;
        $id = isset(self::$Map[$tag]) ? self::$Map[$tag] : 0;
        if (!empty($id)) {
            $cases = $this->getAllClientCase();
            if (!empty($cases) && isset($cases[$id]) && $cases[$id] != $case && array_key_exists($cases[$id], self::$Config[$id]['case'])) {
                $case = $cases[$id];
            }

            // 按设备号分的AB测试
            $tagArr = array('GuangGuangIcon');
            if (in_array($tag, $tagArr)) {
                $case = $this->getAbByDevice($tag);
            }

            // 白名单处理
            $case = $this->isInWhiteList(array('tag' => $tag), $case);
        }

        return $case;
    }

    /**
     * 分配AB测试(1.根据url参数，整理接口回传的ab配置，有则保留，无则分配 2.客户端要留存数据，并在请求是回传改数据).
     *
     * @return array
     */
    public function assign()
    {
        // 当前无AB测试
        if (empty(self::$Config)) {
            return array();
        }

        // 获取客户端已分配的AB
        $clientConfig = $this->getAllClientCase();

        // 清理非激活的ab测试
        if (!empty($clientConfig)) {
            foreach ($clientConfig as $id => $case) {
                if (!isset(self::$Config[$id])) {
                    // 删掉无用的测试
                    unset($clientConfig[$id]);
                } else {
                    // 检查case是否正常，不正常重新分配
                    if (!array_key_exists($clientConfig[$id],self::$Config[$id]['case']) && $clientConfig[$id] != self::NORMAL) {
                        $clientConfig[$id] = $this->assignById($id);
                    }
                }
            }
        }

        // 验证缺少的配置
        foreach (self::$Config as $id => $config) {
            if (!isset($clientConfig[$id])) {
                $clientConfig[$id] = $this->assignById($id);
            }
        }

        // AB 白名单
        foreach ($clientConfig as $id => $case) {
            if (isset(self::$Config[$id]) && is_array(self::$Config[$id]) && isset(self::$Config[$id]['tag'])) {
                $clientConfig[$id] = $this->isInWhiteList(self::$Config[$id], $case);
            }
        }

        return $clientConfig;
    }

    /**
     * 转换配置成字符串.
     *
     * @return string
     */
    public function assignToString()
    {
        $abs = '';
        $data = $this->assign();
        if (!empty($data)) {
            ksort($data);
            foreach ($data as $k => $v) {
                $abs = $abs . '|' . $k . ':' . $v;
            }
            $abs = ltrim($abs, '|');
        }
        return $abs;
    }

    /**
     * 分配AB测试case.
     *
     * @param integer $id AB测试ID.
     *
     * @return string
     */
    public function assignById($id)
    {
        $case = self::NORMAL;
        $config = self::$Config[$id];
        $rand   = $this->abRand();
        if (!empty($config['case'])) {
            $limit = 0;
            foreach ($config['case'] as $key => $val) {
                $limit += $val;
                if ($rand <= $limit) {
                    $case = $key;
                    break;
                }
            }

            // 按设备号分的AB测试
            if (isset($config['tag']) && !empty($config['tag'])) {
                $tagArr = array('GuangGuangIcon');
                if (in_array($config['tag'], $tagArr)) {
                    $case = $this->getAbByDevice($config['tag']);
                }
            }

            // 只有首次登陆用户才分的ab
            if (isset($config['tag']) && !empty($config['tag'])) {
                $tagArr = array('NewUserGuide');
                if (in_array($config['tag'], $tagArr)) {
                    $case = $this->getAbByNewUser($config);
                }
            }

            // 社区看视频得红包
            if (isset($config['tag']) && !empty($config['tag'])) {
                if ($config['tag'] == 'WatchVideoGetRedEnvelope') {
                    $case = $this->getAbWatchVideoGetRedEnvelope();
                }
            }

            // 顶部导航AB测试
            if (isset($config['tag']) && !empty($config['tag'])) {
                if ($config['tag'] == 'NavTopABCaseForUser') {
                    $case = $this->getABNavTopABCaseForUser();
                }
            }

            // 仅新用户测试组
            if (isset($config['tag']) && !empty($config['tag'])) {
                if ($config['tag'] == 'AbTestForUserInfoForLoginV2') {
                    $case = $this->getAbTestForUserInfoForLoginV2($config);
                }
            }

        }

        return $case;
    }

    /**
     * 只新用户分配AB测试.
     *
     * @param string $config Config.
     *
     * @return string
     */
    public function getAbByNewUser($config)
    {
        $case = self::NORMAL;

        $platform = JMRegistry::get('platform');
        $is_first_launch = JMRegistry::get('is_first_launch');
        $firstInstall = JMRegistry::get('appfirstinstall');
        $is_first_open = '0'; // 是否新用户 1新用户 0非新用户
        if ($platform == 'android' && ($firstInstall == '1' || $is_first_launch == '1')) {
            $is_first_open = '1';
        }
        if ($platform == 'iphone' && $firstInstall == '1') {
            $is_first_open = '1';
        }
        if ($is_first_open == '1') {
            $rand   = $this->abRand();
            if (!empty($config['case'])) {
                $limit = 0;
                foreach ($config['case'] as $key => $val) {
                    $limit += $val;
                    if ($rand <= $limit) {
                        $case = $key;
                        break;
                    }
                }
            }
        }

        return $case;
    }

    /**
     * 只新用户分配AB测试.
     *
     * @param string $config Config.
     *
     * @return string
     */
    public function getAbTestForUserInfoForLoginV2($config)
    {
        $case = self::NORMAL;
        $is_first_launch = JMRegistry::get('is_first_launch');
        if ($is_first_launch == '1') {
            $rand   = $this->abRand();
            if (!empty($config['case'])) {
                $limit = 0;
                foreach ($config['case'] as $key => $val) {
                    $limit += $val;
                    if ($rand <= $limit) {
                        $case = $key;
                        break;
                    }
                }
            }
        }
        return $case;
    }

    /**
     * 设备号分配AB测试.
     *
     * @param string $tag 标记.
     *
     * @return string
     */
    public function getAbByDevice($tag)
    {
        $case = self::NORMAL;
        $platform = JMRegistry::get('platform');

        // 首页逛逛图标
        if ($tag == 'GuangGuangIcon') {
            $a_range = '01234abcdefghijklm'; // $b = '56789nopqrstuvwxyz';
            if ($platform == 'iphone') {
                $device = md5(LUtil::getCookie('idfa'));
                $last = substr($device, strlen($device) - 1, 1);
                $case = (strpos($a_range, $last) !== false) ? 'a' : 'b';
            } else {
                $device = md5(LUtil::getCookie('unique_device_id'));
                $last = substr($device, strlen($device) - 1, 1);
                $case = (strpos($a_range, $last) !== false) ? 'a' : 'b';
            }
        }
        return $case;
    }

    /**
     * 社区看视频得红包.
     *
     * @return string
     */
    public function getAbWatchVideoGetRedEnvelope()
    {
        $case = self::NORMAL;

        $platform = JMRegistry::get('platform');
        $client_v = JMRegistry::get('client_v');

        if (($platform == 'iphone' && $client_v >= '7.250') || ($platform == 'android' && $client_v >= '7.251')) {

            $case = 'wvgre_a';
            return $case;

            global $_EXTRA_DEBUG;

            // 白名单直接返回
            if ($this->isInDoveWhiteList()) {
                $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['in_white_list'] = '1';
                // 社区看视频得红包
                $case = 'wvgre_a';
                return $case;
            }

            // RedisKey 社区看视频得红包
            $res_hash_key = 'WatchVideoGetRedEnvelope_001';

            // 时间
            $time = date('Y-m-d H:i:s');

            // UID
            $uid = (int)JMRegistry::get('uid');

            // 设备唯一ID
            $device = ($platform == 'iphone') ? LUtil::getCookie('idfa') : LUtil::getCookie('unique_device_id');
            $crc32 = crc32($device);

            // REDIS 实例
            $redis = VRedisStorage::Instance('mobile');

            // 在白名单中
            $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['in_white_list'] = '0';

            // 新用户(App安装24小时 || 新人注册24小时 || 华为渠道)
            $firstInstall = JMRegistry::get('appfirstinstall');
            if ($firstInstall == '1' || $this->isNewUserByRegisterTime() || $this->isHuaWei()) {

                // 老用户从新安装－给原来AB
                $redis_val = $redis->HGET($res_hash_key, $device);
                $redis_val = !empty($redis_val) ? json_decode($redis_val, 1) : array();
                if (isset($redis_val['ab']) && !empty($redis_val['ab'])) {
                    // 老用户获取上次分配值 9月1日100%
                    $case = $redis_val['ab'];
                    // 存储UID
                    if (isset($redis_val['uid']) && empty($redis_val['uid'])) {
                        $redis_val['uid'] = $uid;
                        $redis_val['time'] = $time;
                        $redis->HSET($res_hash_key, $device, json_encode($redis_val));
                    }

                    // 日志
                    $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['redis_first_get'] = $redis_val;
                } else {

                    // 全新没有记录从新分配
                    // $case = 'wvgre_a';

                    // 设置有效期 Redis记录下这个用户分配的方案(以保证过了24小时可以继续看到)
                    // if (!$redis->EXISTS($res_hash_key)) {
                    //    $redis->EXPIRE($res_hash_key, 86400 * 31 * 12);
                    // }

                    // 存储AB值
                    // $redis_val = array('ab' => $case, 'device' => $device, 'uid' => $uid, 'crc32' => $crc32, 'time' => $time);
                    // $redis->HSET($res_hash_key, $device, json_encode($redis_val));

                    // 日志
                    // $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['redis_first_set'] = $redis_val;
                }
            } else {
                // 老用户Redis记录下这个用户分配的方案(以保证过了24小时可以继续看到)
                $redis_val = $redis->HGET($res_hash_key, $device);
                $redis_val = !empty($redis_val) ? json_decode($redis_val, 1) : array();
                if (isset($redis_val['ab']) && !empty($redis_val['ab'])) {
                    // 老用户获取上次分配值
                    $case = $redis_val['ab'];

                    // 存储UID
                    if (isset($redis_val['uid']) && empty($redis_val['uid'])) {
                        $redis_val['uid'] = $uid;
                        $redis_val['time'] = $time;
                        $redis->HSET($res_hash_key, $device, json_encode($redis_val));
                    }
                }
                // 日志
                $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['redis_old'] = $redis_val;
            }

            // 对未分配wvgre_a的人从新按照设备分配
            if ($case != 'wvgre_a') {
                // 分出20%
                $case = $this->getABWVGREByDevice();
                $case = 'wvgre_a';
            }

            // 日志
            $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['redis_val'] = $redis_val;
            $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['ab'] = $case;
            $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['appfirstinstall'] = $firstInstall;
            // $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['redis_all'] = $redis->HGETALL($res_hash_key);
        }
        return $case;
    }

    /**
     * 根据设备好获取AB值.
     *
     * @return string
     */
    public function getABWVGREByDevice()
    {
        global $_EXTRA_DEBUG;
        // 设备唯一ID
        $crc32 = '';
        $case = self::NORMAL;
        $platform = JMRegistry::get('platform');
        $client_v = JMRegistry::get('client_v');
        if (($platform == 'iphone' && $client_v >= '7.250') || ($platform == 'android' && $client_v >= '7.251')) {
            $device = ($platform == 'iphone') ? LUtil::getCookie('idfa') : LUtil::getCookie('unique_device_id');
            if (!empty($device)) {
                $crc32 = crc32($device);
                $abVal = $crc32 % 10;



                $uid = (int)JMRegistry::get('uid');
                $source = strtolower(JMRegistry::get('source'));
                if (empty($source)) {
                    $source = strtolower(LUtil::getCookie('source'));
                }
                if ($this->isHuaWei()) {
                    $log_str = date('Y-m-d H:i:s') . '_' . $platform . '_' . $client_v . '_' . $uid . '_'. $source .'____' .$device . '_____' . $crc32 . '_____'. $abVal . '__'. PHP_EOL;
                    error_log($log_str, 3, '/tmp/huawei.log');
                } else {
                    $log_str = date('Y-m-d H:i:s') . '_' . $platform . '_' . $client_v . '_' . $uid . '_'. $source .'____' .$device . '_____' . $crc32 . '_____'. $abVal . '__'. PHP_EOL;
                    error_log($log_str, 3, '/tmp/huawei_other.log');
                }




                // 20% 是 0和1   3, 5, 7, 9
                if ($abVal <= 0) {
                    $case = 'wvgre_a';
                } else {
                    $case = self::NORMAL;
                }

                // 白名单
                $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['white_list'] = '0';
                if ($this->isInDoveWhiteList()) {
                    $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['white_list'] = '1';
                    // 社区看视频得红包
                    $case = 'wvgre_a';
                }
            }

            $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['device'] = $device;
            $_EXTRA_DEBUG['WatchVideoGetRedEnvelope']['crc32'] = $crc32;
        }
        return $case;
    }

    /**
     * 新用户判断(近24小注册用户).
     *
     * @return boolean.
     */
    public function isNewUserByRegisterTime()
    {
        $flag = false;
        $register_time = JMRegistry::get('register_time');
        if (empty($register_time)) {
            $uid = JMRegistry::get('uid');
            if (!empty($uid)) {
                // 已登录了
                $userInfo = LCache::memCache('userInfo_is_new_user_' . $uid, 30, array(MAccount::Instance(), 'getUserByUid'), array($uid));
                if (!empty($userInfo) && isset($userInfo['register_time'])) {
                    $register_time = $userInfo['register_time'];
                }
            } else {
                // 执行登录
                if (MAccount::isLogin()) {
                    // 获取登录时间
                    $register_time = JMRegistry::get('register_time');
                    if (empty($register_time)) {
                        $uid = JMRegistry::get('uid');
                        $userInfo = LCache::memCache('userInfo_is_new_user_' . $uid, 30, array(MAccount::Instance(), 'getUserByUid'), array($uid));
                        if (!empty($userInfo) && isset($userInfo['register_time'])) {
                            $register_time = $userInfo['register_time'];
                        }
                    }
                }
            }
        }

        // 24小时内
        $time = time() - 86400;
        if ($register_time >= $time) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * 华为手机.
     *
     * @return bool.
     */
    public function isHuaWei()
    {
        $flag = false;
        $source = strtolower(JMRegistry::get('source'));
        if (empty($source)) {
            $source = strtolower(LUtil::getCookie('source'));
        }

        if ($source == 'huawei') {
            $flag = true;
        }
        return $flag;
    }

    /**
     * 白名单.
     *
     * @return string
     */
    public function isInDoveWhiteList()
    {
        $flag = false;
        $uid = (int)JMRegistry::get('uid');
        $idfa = strtolower(LUtil::getCookie('idfa'));
        $idfv = strtolower(LUtil::getCookie('idfv'));
        $imei = strtolower(LUtil::getCookie('imei'));
        $whiteList = LUtil::getConfig('common', 'ab_test_white_list');
        if (is_array($whiteList) && !empty($whiteList) &&
            (
                (!empty($idfa) && in_array($idfa, $whiteList)) ||
                (!empty($imei) && in_array($imei, $whiteList)) ||
                (!empty($idfv) && in_array($idfv, $whiteList)) ||
                (!empty($uid) && in_array($uid, $whiteList))
            )
        ) {
            $flag = true;
        }
        return $flag;
    }

    /**
     * AB白名单.
     *
     * @param array  $config AB配置.
     * @param string $case   AB方案.
     *
     * @return string
     */
    public function isInWhiteList($config, $case = '')
    {
        $platform = JMRegistry::get('platform');
        $client_v = JMRegistry::get('client_v');

        $uid = (int)JMRegistry::get('uid');

        $idfa = strtolower(LUtil::getCookie('idfa'));

        $idfv = strtolower(LUtil::getCookie('idfv'));

        $imei = strtolower(LUtil::getCookie('imei'));

        $whiteList = LUtil::getConfig('common', 'ab_test_white_list');

        if (is_array($whiteList) && !empty($whiteList) &&
            (
                (!empty($idfa) && in_array($idfa, $whiteList)) ||
                (!empty($imei) && in_array($imei, $whiteList)) ||
                (!empty($idfv) && in_array($idfv, $whiteList)) ||
                (!empty($uid) && in_array($uid, $whiteList))
            )
        ) {
            // 推荐频道ab测试
            if (isset($config['tag']) && $config['tag'] == 'DealActListQRQM') {
                $case = 'f';
            }

            // 逛逛视频入口
            if (isset($config['tag']) && $config['tag'] == 'HomeGuangGuangCard') {
                $case = 'a';
            }

            // 轻奢放到母婴前面
            if (isset($config['tag']) && $config['tag'] == 'NavTopQingSheBaby') {
                $case = 'a';
            }

            // 搜索列表样式展示ab测试
            if (isset($config['tag']) && $config['tag'] == 'SearchListOneOrTwo') {
                $case = 'b';
            }

            // 问大家
            if (isset($config['tag']) && $config['tag'] == 'AskEveryOneDetail') {
                $case = 'a';
            }

            // 搜索分类为你推荐ab测试 #153300
            if (isset($config['tag']) && $config['tag'] == 'SearchCategoryRcommendedForYou') {
                $case = 'b';
            }

            // 搜索列表样式展示ab测试
            if (isset($config['tag']) && $config['tag'] == 'NewHomeStyle') {
                $case = 'a'; // a新版 b旧版
            }

            // 详情页ba展示ab测试
            if (isset($config['tag']) && $config['tag'] == 'DetailBaAB') {
                $case = 'a'; // a客服, b没客服, c没客服
            }

            // 搜索列表卖点文案
            if (isset($config['tag']) && $config['tag'] == 'MainSearchForSellingPoint') {
                $case = 'a'; // a显示
            }

            // 用户中心白名单
            if (isset($config['tag']) && $config['tag'] == 'AbForUserInfo') {
                $case = 'a';
                $list = array(
                    strtolower('714820E9-E559-4357-81B4-51E28A3FC55A'),
                    '765f61db-dbec-4361-a548-c2ef98834ec5',
                );
                if (in_array($idfa, $list) || in_array($idfv, $list)) {
                    $case = self::NORMAL;
                }
            }

            // 社区看视频得红包
            if (isset($config['tag']) && $config['tag'] == 'WatchVideoGetRedEnvelope') {
                if (($platform == 'iphone' && $client_v >= '7.250') || ($platform == 'android' && $client_v >= '7.251')) {
                    $case = 'wvgre_a'; // wvgre_a显示
                }
            }

            // 发现H5视频引导展示ab测试
            if (isset($config['tag']) && $config['tag'] == 'SheQuRedEnvelopeCaseV2Home') {
                $case = 'a7';
            }

            // 用户中心白名单
            if (isset($config['tag']) && $config['tag'] == 'AbTestForUserInfoForLogin') {
                $case = 'a';
            }

            // 发现商品立即结算ab
            if (isset($config['tag']) && $config['tag'] == 'AbTestForFaXianDirectlyPay') {
                $case = 'a';
            }

            // 发现视频ab 展示手动配置推荐
            if (isset($config['tag']) && $config['tag'] == 'VideoTypeAB4') {
                $case = 'video4t20';
            }

            // 全部用户测试组-新版红包
            if (isset($config['tag']) && $config['tag'] == 'AbTestForUserInfoForLoginV1') {
                $case = 'a5';
            }

        }

        return $case;
    }

    /**
     * AB测试概率计算.
     *
     * @return integer.
     */
    public function abRand()
    {
        return $this->abRandByDevice();

        // return rand(1,100);
    }

    /**
     * AB测试概率计算.
     *
     * @return integer.
     */
    public function abRandByDevice()
    {
        // 设备唯一ID
        if (JMRegistry::get('platform') == 'iphone') {
            $device = (JMRegistry::get('idfa')) ? JMRegistry::get('idfa') : LUtil::getCookie('idfa');
        } else {
            $device = (JMRegistry::get('unique_device_id')) ? JMRegistry::get('unique_device_id') : LUtil::getCookie('unique_device_id');
        }
        $crc32 = crc32($device);
        $val = ($crc32 % 100) + 1;
        return $val;
    }

    /**
     * 获取客户端已分配的AB测试, 1:normal|5:test(主要是要保留已分配的客户端AB Case，避免重复分配).
     *
     * @return array
     */
    public function getAllClientCase()
    {
        $data = array();

        // 获取AB值
        $params = LUtil::getCookie('ab');
        if (empty($params)) {
            $params = JMRegistry::get('ab');
        }

        if (!empty($params)) {

            $abs = explode('|', urldecode($params));

            // 判断 1.开关是否开启 2.时间内 3.版本内
            $using_case = array_values(self::$Map);

            foreach ($abs as $v) {
                if (preg_match("/(?P<id>\d+):(?P<case>\w+)/", $v, $match)) {
                    if (in_array($match['id'], $using_case)) {
                        $data[$match['id']] = $match['case'];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 增长广告位.
     *
     * @param integer $uid 用户UID.
     *
     * @return array
     */
    public function getMaterialByUid($uid)
    {
        $result = array();
        $model = $source = $idfa = '';
        if (!empty($uid)) {
            // 2017-09-11 研发需求 #138825 增加 机型model／渠道来源source／设备标识idfa 参数
            if (JMRegistry::get('platform') == 'iphone') {
                $model = 'iphone';
                $idfa = LUtil::getCookie('idfa') ? LUtil::getCookie('idfa') : '';
            } elseif (JMRegistry::get('platform') == 'android') {
                $model = LUtil::getCookie('model') ? LUtil::getCookie('model') : '';
                $source = LUtil::getCookie('source') ? LUtil::getCookie('source') : '';
            }

            $data = LUtil::call('TextRpc_Growth_ActivityAD::getMaterialByUid',array($uid, $model, $source, $idfa));

            if ($data['code'] == 0 && $data['result']) {
                $result = $data['result'];
            }
        }

        return $result;
    }

    /**
     * 顶部导航AB测试.
     *
     * @return string
     */
    public function getABNavTopABCaseForUser()
    {
        $case = self::NORMAL;

        $platform = JMRegistry::get('platform');
        $client_v = JMRegistry::get('client_v');
        if (
            ($platform == 'iphone' && $client_v >= '7.205') ||
            ($platform == 'android' && $client_v >= '7.206')
        ) {
            if (MAccount::isLogin()) {
                $uid = JMRegistry::get('uid');
                if (!empty($uid)) {
                    // 在REIDS中
                    $redis = VRedisStorage::Instance('mobile');
                    $key = 'REDIS_NavTopABCaseForUserList';
                    $val = $redis->hget($key, $uid);
                    if ($val == '1') {
                        $mod = $uid % 100;
                        if ($mod >= 0 && $mod < 20) {
                            $case = 'a1';
                        }

                        if ($mod >= 20 && $mod < 40) {
                            $case = 'a2';
                        }

                        if ($mod >= 40 && $mod < 60) {
                            $case = 'a3';
                        }

                        if ($mod >= 60 && $mod < 80) {
                            $case = 'a4';
                        }

                        if ($mod >= 80 && $mod < 100) {
                            $case = 'a5';
                        }
                    }
                }
                // 白名单
                if (in_array($uid, array('106480129', '45182158', '7114937'))) {
                    $case = 'a1';
                }
            }
        }
        return $case;
    }

}
