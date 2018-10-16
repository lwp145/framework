<?php
/**
 * 入口.
 */

define('SITE_NAME','Api');
define('JM_APP_NAME','jumei_mapi');

require_once(__DIR__.'/../../config.common.php'); // APPLICATION_DIR 在这个文件定义的

define('JM_APP_ROOT',APPLICATION_DIR.'/Api/'); //Applications目录下Api目录

//var_dump(JM_VENDOR_DIR.'Bootstrap/Autoloader.php');die;
// 引入公用（跨项目）类库加载器
require JM_VENDOR_DIR.'Bootstrap/Autoloader.php';
Bootstrap\Autoloader::instance()->init();
JmArchiTracker\Tracker::init(); // 这个地方就用到了上一行中的spl_autoload_register中的loadByNamespace这个方法

// 路由控制器 第9行引入中定义
require_once (JM_WEB_FRAMEWORK_ROOT.'FrameworkApi.php');

// 输出RPC服务数据
global $_EXTRA_DEBUG;

// 映射器UrlController.
$routes = array('api' => array());

// 注册站点信息 注册信息.
Registry::set('SiteInfo', array('Site' => array('Api' => array('TopLevelDomainName' => '', 'FriendlyName' => ''))));

// 异常捕获记录.
/*
\MNLogger\TraceLogger::instance('trace')->HTTP_SR();
\MNLogger\TraceLogger::setUp(array('trace' => Config\MNLogger::$trace));
\MNLogger\EXLogger::setUp(array('exception' => Config\MNLogger::$exception));
*/
try {
    // 框架初始化运行.
    $siteEngine = new SiteEngine();

    $siteEngine->setRoutePathMap($routes);
    $siteEngine->setSiteName(SITE_NAME);
    $siteEngine->ensureMainSiteAndLearnSubDomain();
    $siteEngine->setDefaultRoutePathBaseName('Home_PageNotFound');
    $siteEngine->run();

} catch (\Lib\JMException $ex) {
    \Lib\Util::exceptionResponse($ex->getExtCode(), $ex->getExtAction(), $ex->getMessage(), $ex->getExtData(), $ex->getHttpStatus());
}
