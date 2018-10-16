<?php
if (!defined('JM_WEB_FRAMEWORK_ROOT')) define('JM_WEB_FRAMEWORK_ROOT',__DIR__.DIRECTORY_SEPARATOR);
if (!defined('JM_APP_ROOT')) {
    throw new Exception("'JM_APP_ROOT' not defined, it is your project root directory which should contains the 'Controller','Public','View' directories and etc.");
}

require_once (JM_WEB_FRAMEWORK_ROOT.'FrameworkWeb.php');

class ViewController_WebManagementBase extends ViewController
{
    protected $pageInfo = array();
    protected $siteInfo = array();

    protected static $postInitializeHooks = array();

    public static function registerPostInitializeHook($callback)
    {
        static::$postInitializeHooks[] = $callback;
    }

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $siteInfo = Registry::get('SiteInfo');
        $this->siteInfo = $siteInfo['Site'][$this->siteEngine->getSiteName()];

        $this->siteInfo['DocumentRoot'] = JM_APP_ROOT;
        $this->siteInfo['WebRoot'] = $this->siteEngine->getWebRoot();

        $this->siteInfo['TemplateRootDirPath'] = defined('JM_VIEW') ? JM_VIEW : JM_APP_ROOT . 'View/';
        $this->siteInfo['RemoteIp'] = System::GetRemoteIp();
        $this->siteInfo['RemoteIpWithProxy'] = System::GetRemoteIpWithProxy();

        $this->pageInfo['Title'] = $this->siteInfo['FriendlyName'];
        $this->pageInfo['Keywords'] = '';
        $this->pageInfo['Description'] = '';

        if (file_exists(JM_APP_ROOT . 'Public/assets/')) {
            $this->siteInfo['rewWebPath'] = $this->siteInfo['WebRoot'] . '/assets/';
        } else {
            $this->siteInfo['rewWebPath'] = $this->siteInfo['WebRoot'] . 'Site';
        }

        $siteInfo['Site'][$this->siteEngine->getSiteName()] = &$this->siteInfo;
        Registry::set('SiteInfo', $siteInfo);
    }

    /**
     * @param $name
     *
     * @return array
     * @throws Exception
     */
    public function __get($name)
    {
        switch ($name) {
            case 'templateEngine':
                return $this->getTemplateEngine();
                continue;
            case 'siteInfo':
                return $this->siteInfo;
                continue;
            case 'pageInfo':
                return $this->pageInfo;
                continue;
            default:
                if (property_exists($this, $name)) {
                    throw new Exception('Access to protected property ' . get_called_class() . '::' . $name . 'is not allowed via magic __get.');
                } else {
                    trigger_error(new Exception('Accessing undefined proterty' . get_called_class() . '::' . $name . 'is not allowed via magic __get.'), E_USER_NOTICE);
                }
        }
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        list($prefix,) = explode('_', $name);
        if ($prefix == 'ajax' || $prefix == 'action') {
            $className = 'Controller_' . $this->getSiteEngine()->getDefaultRoutePathBaseName();
            $c = new $className($this->siteEngine);
            $c->initialize();
            foreach (static::$postInitializeHooks as $callback) {
                call_user_func_array($callback, array($c));
            }

            if (!method_exists($c, 'action_pageNotFound')) {
                header('Content-type: text/html; charset=utf-8', true, 404);
                if ($prefix != 'ajax') {
                    echo '<h1>Page not found!</h1>';
                }
                if (!DEBUG) {
                    die;
                }
            } else {
                return $c->action_PageNotFound();
            }
        }

        throw new Exception('unknown function: ' . $name);
    }

    public function beforeDisplayActionTemplate()
    {

    }

    public function displayActionTemplate($data = null, $templateName = null)
    {
        $config = Registry::get('SiteInfo');
        if (empty($this->pageInfo['Title'])) {
            $this->pageInfo['Title'] = $config['Site'][$this->siteEngine->getSiteName()]['FriendlyName'];
        }
        $this->getTemplateEngine()->assign('PageInfo', $this->pageInfo)->assign('SiteInfo', $this->siteInfo);
        $this->beforeDisplayActionTemplate();

        if ($data) {
            if (!is_array($data)) {
                throw new InvalidArgumentException('data must be an array for smarty assign');
            }
            $this->getTemplateEngine()->assign($data);
        }

        if (empty($templateName)) {
            $templateName = join('/', $this->actionPathFields);
        }

        $this->pageInfo['MasterPageView']['CurrentAction'] = $this->getTemplateEngine()->template($templateName);
        $this->getTemplateEngine()->assign('PageInfo', $this->pageInfo);
        header('Content-type: text/html; charset=utf-8');
        $this->getTemplateEngine()->display($templateName);
    }

    public function setPageInfo($var, $value)
    {
        $this->pageInfo[$var] = $value;
    }
}


class AuthLdapSignon extends AuthInterface
{
    const SESSION_KEY_AUTH_INFO = 'AuthLdaoSignon_auth_info';

    protected $signonConfig;

    /**
     * AuthLdapSignon constructor.
     *
     * @param $siteEngine
     */
    public function __construct($siteEngine)
    {
        parent::__construct($siteEngine);
        $config = Registry::get('serverConfig');
        if (isset($config['Site'][$this->siteEngine->getSiteName()]['Signon'])) {
            $this->signonConfig = $config['Site'][$this->siteEngine->getSiteName()]['Signon'];
        } else {
            $this->signonConfig = $config['LDAP'];
        }
    }

    public function ensureLogin()
    {
        $defaultRoutePathBaseName = $this->siteEngine->getDefaultRoutePathBaseName();
        if ($this->siteEngine->getRoutePath() == "$defaultRoutePathBaseName/AuthSignonLoginCallback") {
            if ($this->processAuthSignonLoginCallback()) {
                System::RedirectExit(GetRequest('redirect_url', '/'), 302);
            }

            die("Your login is invalid. Please use a valid account to <a target='_top' href='/$defaultRoutePathBaseName/AuthSignonLogout'>login</a>");
        } elseif ($this->siteEngine->getRoutePath() == "$defaultRoutePathBaseName/AuthSignonLogout") {
            $this->logout('/');
        } elseif ( empty ($_SESSION[self::SESSION_KEY_AUTH_INFO])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
            if($_SERVER['SERVER_PORT'] == 443) $protocol = 'https';

            $redirect_url = "$protocol://{$_SERVER['HTTP_HOST']}" . $_SERVER['REQUEST_URI'];
            $login_url = "$protocol://{$_SERVER['HTTP_HOST']}/$defaultRoutePathBaseName/AuthSignonLoginCallback?redirect_url=" . rawurldecode($redirect_url);
            System::RedirectExit("{$this->signonConfig['BaseUrl']}auth/login?camefrom=" . rawurlencode($this->signonConfig['AppName']) . '&login_url=' . rawurlencode($login_url), 302);
        }
        //now, seems valid login, just continue to other modules
    }

    protected function processAuthSignonLoginCallback()
    {
        if (isset($_GET['token']) && isset($_GET['username'])) {
            $token = $_GET['token'];
            $username = trim($_GET['username']);
            $ldap_session_id = sha1($token . $this->signonConfig['AppKey'] . $username);

            //{"logged_in": true, "message": "Error: No group found for dealplan, please contact LDAP administrator", "fullname": "\u732a\u6cb9", "groups": []}
            //{"logged_in": true, "fullname": "\u732a\u6cb9", "message": "", "groups": ["admin"]}
            $result = file_get_contents($this->signonConfig['BaseUrl'] . 'auth/info?session_id=' . $ldap_session_id);
            $auth_info = @json_decode($result, true);
            if ($auth_info && ! empty($auth_info['logged_in']) && ! empty($auth_info['groups']) ) {
                $_SESSION[self::SESSION_KEY_AUTH_INFO] = $auth_info;
                $_SESSION[self::SESSION_KEY_AUTH_INFO]['signon_session_id'] = $ldap_session_id;
                return true;
            }
        }
        return false;
    }

    public function getCurrentAuthInfo($name)
    {
        return isset($_SESSION[self::SESSION_KEY_AUTH_INFO][$name]) ? $_SESSION[self::SESSION_KEY_AUTH_INFO][$name] : null;
    }

    public function getCurrentUsername()
    {
        return $this->getCurrentAuthInfo('username');
    }

    public function getCurrentFullname()
    {
        return $this->getCurrentAuthInfo('fullname');
    }

    public function getCurrentGroups()
    {
        return $this->getCurrentAuthInfo('groups');
    }

    public function getCurrentSignonSessionId()
    {
        return $this->getCurrentAuthInfo('signon_session_id');
    }

    public function isInAuthGroup($group)
    {
        $groups = $this->getCurrentAuthInfo('groups');
        return is_array($groups) && in_array($group, $groups);
    }

    public function getMembersInGroup($group)
    {
        //$url = $this->signonConfig['BaseUrl'] . "groups/$group?" . http_build_query(array('session_id'=>$this->getCurrentSignonSessionId()), '', '&');
        $url = $this->signonConfig['BaseUrl'] . "groups/$group?" . http_build_query(array(
                'app_key' => $this->signonConfig['AppKey'],
                'app_name' => $this->signonConfig['AppName'],
            ), '', '&');

        $info = file_get_contents($url);
        $info = json_decode($info, true);
        return $info['members'];
    }

    public function getMembersDetailInGroup($group)
    {
        //$url = $this->signonConfig['BaseUrl'] . "groups/$group?" . http_build_query(array('session_id'=>$this->getCurrentSignonSessionId()), '', '&');
        $url = $this->signonConfig['BaseUrl'] . "groups/$group?" . http_build_query(array(
                'app_key' => $this->signonConfig['AppKey'],
                'app_name' => $this->signonConfig['AppName'],
            ), '', '&');

        $info = file_get_contents($url);
        $info = json_decode($info, true);
        return $info['details'];
    }

    public function hasValidAuthGroups()
    {
        return ! ! $this->getCurrentAuthInfo('groups');
    }

    public function logout($gotoUrl = '/')
    {
        if (isset($_SESSION[self::SESSION_KEY_AUTH_INFO])) {
            unset($_SESSION[self::SESSION_KEY_AUTH_INFO]);
        }

        if (empty($gotoUrl)) {
            trigger_error ('unknown logout goto url');
        }

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
        if ($_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https';
        }
        if (strpos($gotoUrl, '://') === false) {
            $gotoUrl = "$protocol://{$_SERVER['HTTP_HOST']}$gotoUrl";
        }

        System::RedirectExit("{$this->signonConfig['BaseUrl']}auth/logout?camefrom=" . rawurlencode($this->signonConfig['AppName']) . '&goto_url=' . rawurlencode($gotoUrl), 302);
    }
}