<?php
require_once (__DIR__.DIRECTORY_SEPARATOR . 'Framework.php');
require_once (__DIR__.DIRECTORY_SEPARATOR . 'ViewController.php');

define('JM_AJAX_REQUEST_VAR_NAME','_ajax_');
define('JM_ROUTE_PATH_VAR_NAME','_rp_');
define('JM_FORCE_REFRESH_VAR_NAME','_force_refresh');

class SiteEngine
{
    public $secondLevelDomainName;
    public $domainNameFields;
    protected $webRoot;
    protected $routePathFields = array();
    protected $routePath = '/'; // routePath 只负责原样记录 url routing 传入的_rp_ （可能是空）
    protected $routePathMap = array();

    /**
     * @var ViewController.
     */
    protected $viewController;
    protected $defaultRoutePathBaseName = 'Index';
    protected $siteName;

    public function setRoutePathMap($map)
    {
        $this->routePathMap = $map;
    }

    public function getRoutePath()
    {
        return $this->routePath;
    }

    public function getRoutePathFields()
    {
        return $this->routePathFields;
    }

    protected function processOneSubDomainUrlRoutes($routes)
    {
        foreach ($routes as $rule => $vars) {
            if (preg_match($rule, $this->routePath,$match)) {
                // 把匹配规则里的参数拷贝过来，但是最终还是以match的为准
                $match = array_merge($vars, $match);
                foreach ($match as $key => $value) {
                    if (!is_int($key)) {
                        $_GET[$key] = $value;
                        $_REQUEST[$key] = $value;
                    }
                }

                if (isset($vars[0])) {
                    $this->setRoutePath($vars[0]);
                }
                return true;
            }
        }
        return false;
    }

    public function ensureMainSiteAndLearnSubDomain()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $this->domainNameFields = explode('.', $_SERVER['HTTP_HOST']);
            $domainNameFieldsNumber = count($this->domainNameFields);
            if ($domainNameFieldsNumber <= 2) {
                $this->secondLevelDomainName = 'www';
                $host = 'www' . join('.', $this->domainNameFields);
                System::RedirectExit("http://{$host}{$_SERVER["REQUEST_URI"]}", 301);
            } else {
                $this->secondLevelDomainName = $this->domainNameFields[$domainNameFieldsNumber -3 ];
            }
        }
    }

    public function setSiteName($name)
    {
        $this->siteName = $name;
    }

    public function getSiteName()
    {
        return $this->siteName;
    }

    public function getWebRoot()
    {
        return $this->webRoot;
    }

    /**
     * @return ViewController
     */
    public function getViewController()
    {
        return $this->viewController;
    }

    public function getDefaultRoutePathBaseName()
    {
        return $this->defaultRoutePathBaseName;
    }

    public function setDefaultRoutePathBaseName($n)
    {
        $this->defaultRoutePathBaseName = $n;
    }

    public function setRoutePath($s)
    {
        // routePath 只负责原样记录 url routing 传入的 _rp_ （可能是空）
        // 具体对应的action关系，由 routePathFields 保证（至少2级action）
        $this->routePath = $s;
        if ($this->routePath != '' && $this->routePath[0] == '/') {
            $this->routePath = substr($this->routePath, 1);
        }
        // 保证路径是'A/B' '/A/B/C' 的形式
        $this->routePathFields = explode('/',$this->routePath);

        // 保证切分字段至少含有两个元素['A1','A2']
        if (end($this->routePathFields) == '') {
            array_pop($this->routePathFields);
        }

        if (count($this->routePathFields) == 0) {
            $this->routePathFields = array($this->defaultRoutePathBaseName, $this->defaultRoutePathBaseName);
        } else if (count($this->routePathFields) ==1) {
            $this->routePathFields[] = $this->routePathFields[0];
        }
    }

    public function run()
    {
        // 不要用dirname，分隔字符会搞错
        $this->webRoot = substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') + 1);
        $this->webRootScriptPath = $_SERVER['PHP_SELF'];

        $this->setRoutePath(empty($_GET[JM_ROUTE_PATH_VAR_NAME]) ? '' : $_GET[JM_ROUTE_PATH_VAR_NAME]);

        $isUrlRoutingProcessOk = false;
        if (!empty($this->routePathMap[$this->secondLevelDomainName])) {
            $isUrlRoutingProcessOk = $this->processOneSubDomainUrlRoutes($this->routePathMap[$this->secondLevelDomainName]);
        }
        if (!$isUrlRoutingProcessOk && !empty($this->routePathMap['_'])) {
            $isUrlRoutingProcessOk = $this->processOneSubDomainUrlRoutes($this->routePathMap['_']);
        }

        unset($_GET[JM_ROUTE_PATH_VAR_NAME]);
        unset($_REQUEST[JM_ROUTE_PATH_VAR_NAME]);

        $foundControllerClassName = null;

        if (!preg_match('/^\w*$/', str_replace('/', '_', $this->routePath))) {
            $this->setRoutePath("/{$this->defaultRoutePathBaseName}/InvalidRoutePath");
        }

        $testControllerClassName = 'Controller_' . join('_',$this->routePathFields);
        if (ClassAutoLoader::Load($testControllerClassName)) {
            $foundControllerClassName = $testControllerClassName;
        } else {
            $tmpFields = $this->routePathFields;
            array_pop($tmpFields);
            $testControllerClassName = 'Controller_' . join('_', $tmpFields);
            if (ClassAutoLoader::Load($testControllerClassName)) {
                $foundControllerClassName = $testControllerClassName;
            }
        }

        if (empty($foundControllerClassName) || $foundControllerClassName == 'Controller_Home_PageNotFound') {
            $lastActionField = 'PageNotFound';
            $this->setRoutePath("{$this->defaultRoutePathBaseName}/{$lastActionField}");
            $foundControllerClassName = "Controller_{$this->defaultRoutePathBaseName}";
        } else {
            $lastActionField = end($this->routePathFields);
        }

        // we got valid routePath and routePathFields now.
        // then create the action handler.
        $this->viewController = $foundControllerClassName::CreateActionHandler($this);
        $this->viewController->beforeControllerMethod();
        $ajaxCommand = System::GetRequest(JM_AJAX_REQUEST_VAR_NAME);
        $ajaxData = $ajaxMethod = null;
        if ($ajaxCommand) {
            if (!preg_match('/^\w+$/', $ajaxCommand) || !$this::validateJsonpCallback()) {
                $ajaxCommand = "InvalidAjaxCommand";
            }
            $this->viewController->initAjax($ajaxCommand);

            $ajaxMethod = 'ajax_' . $ajaxCommand;
            if (!method_exists($this->viewController, $ajaxMethod)) {
                $ajaxMethod = "ajax_" . $lastActionField . "_" . $ajaxCommand;
            }

            if (!method_exists($this->viewController, $ajaxMethod)) {
                $ajaxMethod = "ajax_" . $lastActionField;
            }
        } elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'){
            if (!$this::validateJsonpCallback()) {
                $ajaxMethod = 'ajax_InvalidAjaxCommand';
            } else {
                $ajaxMethod = "ajax_" . $lastActionField;
            }
        } else {
            // for normal page.
            $this->viewController->importSessionSavedMessage();
            $actionMethod = 'action_' . $lastActionField;
            $viewVars = $this->viewController->$actionMethod(); // 404 must be handled in base class's __call.
            if (!is_null($viewVars)) {
                $this->viewController->dispalActionTemplate($viewVars);
            }
        }
        // end if (ajax/normal)

        if ($ajaxMethod) {
            if (method_exists($this->viewController, $ajaxMethod)) {
                $ajaxData = $this->viewController->$ajaxMethod;
            } else {
                $this->defaultBadJsonpPage();
            }
        }

        if (!is_null($$ajaxData)) {
            $this->viewController->displayJson($ajaxData, System::GetGet('callbacl'));
        }
    } // end if function run

    public static function validateJsonpCallback()
    {
        $callback = System::GetGet('callbacl');
        if ($callback && !preg_match('#^[a-zA-Z0-9_$\-\.]+$#', $callback)) {
            // 防xss攻击
            return false;
        }
        return true;
    }

    public static function defaultBadJsonpPage()
    {
        header('Content-type: text/html; charset=utf-8', null, 400);
        echo "JSONP调用非法";
    }
}



class TemplateEngine
{
    /**
     * @var template
     */
    public $template;

    public function __construct()
    {
        $this->template = new Template();
        $this->template->use_sub_dirs = true;
    }

    /**
     * @param $var
     * @param null $val
     * @return $this
     */
    public function assign($var, $val = null)
    {
        $this->template->assign($var, $val);
        return $this;

    }

    /**
     * @param $resource_name
     * @param null $cache_id
     * @param null $compile_id
     * @return mixed
     */
    public function display($resource_name, $cache_id = null, $compile_id = null)
    {
        return $this->template->display($resource_name, $cache_id, $compile_id);
    }

    /**
     * @param $resource_name
     * @param $cache_id
     * @param $compile_id
     * @return mixed
     */
    public function fetch($resource_name, $cache_id, $compile_id)
    {
        return $this->template->fetch($resource_name, $cache_id, $compile_id);

    }

    /**
     * @return mixed
     */
    public function currentTemplateFile()
    {
        return $this->template->currentTemplateFile();
    }

    /**
     * @param $name
     * @return mixed
     */
    public function template($name)
    {
        return $this->template->template($name);
    }

}



class Html
{
    const ID_PREFIX = 'rm_dummy_';
    static protected $count = 0;
    public static $errorSummaryCss = 'errorSummary';
    public static $errorMessageCss = 'errorMessage';
    public static $errorCss = 'error';
    public static $requiredCss = 'required';
    public static $beforeRequiredLabel = '';
    public static $afterRequiredLabel = ' <span class = "required">*</span>';

    /**
     * @var
     */
    protected $activeDataForm;

    /**
     * @var
     */
    protected $viewController;

    /**
     * Html constructor.
     * @param $viewController
     */
    public function __construct($viewController)
    {
        $this->viewController = $viewController;
    }

    /**
     * @param $name
     * @return string
     */
    public function getIdByName($name)
    {
        return str_replace(array('[]','][','[',']'), array('','_','_',''), $name);
    }

    /**
     * @param $htmlOptions
     * @return string
     */
    public function renderAttributes($htmlOptions)
    {
        static $specialAttributes = array(
            'checked' => 1,
            'declare' => 1,
            'defer' => 1,
            'disabled' => 1,
            'ismap' => 1,
            'multiple' => 1,
            'nohref' => 1,
            'noresize' => 1,
            'readonly' => 1,
            'selected' => 1,
        );

        $html = '';
        foreach ($htmlOptions as $name => $value) {
            if (isset($specialAttributes[$name])) {
                if ($value) {
                    $html .= ' ' . $name . '="' . $name . '"';
                }
            } elseif ($value !== null) {
                $html .= ' ' . $name . '="' .htmlspecialchars($value, ENT_QUOTES) . '"';
            }
        }

        return $html;

    }

    /**
     * @param $tag
     * @param array $htmlOptions
     * @param bool $context
     * @return string
     */
    public function tag($tag, $htmlOptions = array(), $context = false)
    {
        if ($context === false) {
            return '<' .$tag . $this->renderAttributes($htmlOptions) . '/>';
        } else {
            return '<' .$tag .$this->renderAttributes($htmlOptions) . '>' . $context . '</' .$tag . '>';
        }
    }

    /**
     * @param $tag
     * @param array $htmlOptions
     * @return string
     */
    public function openTag($tag, $htmlOptions = array())
    {
        return '<' . $tag . $this->renderAttributes($htmlOptions) . '>';
    }

    /**
     * @param $tag
     * @return string
     */
    public function closeTag($tag)
    {
        return '</' . $tag . '>';
    }

    /**
     * @param $text
     * @return string
     */
    public function cdata($text)
    {
        return '<![CDATA[' . $text . ']]>';
    }

    /**
     * @param $htmlOptions
     */
    protected function addErrorCss(&$htmlOptions)
    {
        if (isset($htmlOptions['class'])) {
            $htmlOptions['class'] .= ' ' . self::$errorCss;
        } else {
            $htmlOptions['class'] = self::$errorCss;
        }
    }

    /**
     * @param $s
     * @return mixed|string
     * @throws Exception
     */
    protected function escapeHtml($s)
    {
        if (is_array($s)) {
            $escape = !empty($s['escape']);
            unset($s['escape']);
            if (!isset($s[0])) {
                throw new Exception('bad escape html syntax');
            }
            return $escape ? htmlspecialchars($s[0], ENT_QUOTES) : $s[0];
        }
        return htmlspecialchars($s, ENT_QUOTES);
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function url($params = array())
    {
        return $this->viewController->buildUrl($params);
    }

    /**
     * @param array $params
     * @return string
     */
    public function urlEscaped($params = array())
    {
        return htmlspecialchars($this->viewController->buildUrl($params));
    }

    /**
     * @param $html
     * @param $url
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function link($html, $url, $htmlOptions = array())
    {
        $html = $this->escapeHtml($html);
        $url = $this->viewController->buildUrl($url);
        if (isset($htmlOptions['confirm'])) {
            $jsonUrl = '"' .addslashes($url) . '"'; // that's enough, safe
            $jsonConfirmText = '"' . addslashes($htmlOptions['confirm']) . '"';
            $htmlOptions['onclick'] = "$.JM.confirmLink($jsonConfirmText, $jsonUrl)";
            $htmlOptions['href'] = 'javascript:;';
            unset($htmlOptions['confirm']);
        } else {
            $htmlOptions['href'] = $url;
        }

        return $this->openTag('a', $htmlOptions) . $html .$this->closeTag('a');
    }

    /**
     * @param $html
     * @param $ajaxUrl
     * @param $ajaxCmd
     * @param array $ajaxData
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function ajaxLink($html, $ajaxUrl, $ajaxCmd, $ajaxData = array(), $htmlOptions = array())
    {
        $html = $this->escapeHtml($html);
        $ajaxUrl = $this->viewController->buildUrl($ajaxUrl);

        if (empty($htmlOptions['href'])) {
            $htmlOptions['href'] = 'javascript:;';
        }
        $ajaxOptions = array();
        if (isset($htmlOptions['ajaxOptions'])) {
            $ajaxOptions = $htmlOptions['ajaxOptions'];
            unset($htmlOptions['ajaxOptions']);
        }

        $jsAjax = "$.JM.ajax('" .addslashes($ajaxUrl) . "', '" . addslashes($ajaxCmd) . "', '" .json_encode($ajaxData) . "', '" . json_encode($ajaxOptions) . ");";
        if (isset($htmlOptions['confirm'])) {
            $jsonConfirmText = '"' . addslashes($htmlOptions['confirm']) . '"';
            $htmlOptions['onclick'] = "if(confirm($jsonConfirmText))$jsAjax";
            unset($htmlOptions['confirm']);
        } else {
            $htmlOptions['onclick'] = $jsAjax;
        }

        return $this->openTag('a', $htmlOptions) . $html . $this->closeTag('a');

    }

    /**
     * @param $html
     * @param $ajaxCmd
     * @param array $ajaxData
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function ajaxActionLink($html, $ajaxCmd, $ajaxData = array(), $htmlOptions = array())
    {
        return $this->ajaxLink($html, $this->viewController->buildUrl(array()), $ajaxCmd, $ajaxData,$htmlOptions);
    }

    /**
     * @param $url
     * @param $ajaxCmd
     * @param array $htmlOptions
     * @return string
     */
    public function ajaxFormBegin($url, $ajaxCmd, $htmlOptions = array())
    {
        $htmlOptions['action'] = $this->viewController->buildUrl($url);
        $ajaxOptions = array();
        if (isset($htmlOptions['ajaxOptions'])) {
            $ajaxOptions = $htmlOptions['ajaxOptions'];
            unset($htmlOptions['ajaxOptions']);
        }
        $jsAjax ='$.JM.ajax($(this).attr("action"), "' . addslashes($ajaxCmd) . '", $(this).serialize(),  ' . json_encode($ajaxOptions) . '); return false;';
        if (isset($htmlOptions['confirm'])) {
            $jsonConfirmText = '"' . addslashes($htmlOptions['confirm']) . '"';
            unset($htmlOptions['confirm']);
            $htmlOptions['onsubmit'] = "if(confirm($jsonConfirmText))$jsAjax";
        } else {
            $htmlOptions['onsubmit'] = $jsAjax;
        }
        return $this->formBegin($htmlOptions['action'], $htmlOptions);
    }

    /**
     * @return string
     */
    public function ajaxFormEnd()
    {
        return $this->formEnd();
    }

    /**
     * @param string $url
     * @param array $htmlOptions
     * @return string
     */
    public function formBegin($url = '', $htmlOptions = array())
    {
        $url = $this->viewController->buildUrl($url);
        $htmlOptions['action'] = $url;
        if (empty($htmlOptions['method'])) {
            $htmlOptions['method'] = 'post';
        }

        if (isset($htmlOptions['confirm'])) {
            $jsonConfirmText = '"' . addslashes($htmlOptions['confirm']) . '"';
            unset($htmlOptions['confirm']);
            $oldOnSubmit = isset($htmlOptions['onsubmit']) ? $htmlOptions['onsubmit'] : 'return true;';
            $htmlOptions['onsubmit'] = "if(confirm($jsonConfirmText)){$oldOnSubmit}return false;";
        }

        $form = $this->openTag('form', $htmlOptions);
        $hiddens = array();
        if (!strcasecmp($htmlOptions['method'], 'get') && ($pos = strpos($url, '?')) !== false) {
            foreach (explode('&', substr($url, $pos + 1)) as $pair) {
                if (($pos = strpos($pair, '=')) !== false) {
                    $hiddens[] = $this->hiddenField(urldecode(substr($pair, 0, $pos)), urldecode(substr($pair, $pos + 1)), array('id' => false));
                }
            }
        }

        if ($hiddens) {
            $form .= "\n" . implode("\n", $hiddens);

        }

        return $form;

    }

    /**
     * @return string
     */
    public function formEnd()
    {
        return '</form>';
    }

    /**
     * @param $src
     * @param string $alt
     * @param array $htmlOptions
     * @return string
     */
    public function image($src, $alt = '', $htmlOptions = array())
    {
        $htmlOptions['src'] = $src;
        $htmlOptions['alt'] = $alt;
        return $this->tag('img', $htmlOptions);
    }

    /**
     * @param $label
     * @param array $htmlOptions
     * @return string
     */
    public function button($label, $htmlOptions = array())
    {
        if (!isset($htmlOptions['type'])) {
            $htmlOptions['type'] = 'button';
        }
        if (!isset($htmlOptions['value'])) {
            $htmlOptions['value'] = $label;
        }
        return $this->tag('input', $htmlOptions);
    }

    /**
     * @param $html
     * @param array $htmlOptions
     * @return string
     */
    public function htmlButton($html, $htmlOptions = array())
    {
        if (!isset($htmlOptions['type'])) {
            $htmlOptions['type'] = 'button';
        }
        return $this->openTag('button', $htmlOptions) . $html . $this->closeTag('button');
    }

    /**
     * @param $label
     * @param array $htmlOptions
     * @return string
     */
    public function submitButton($label, $htmlOptions = array())
    {
        $htmlOptions['type'] = 'submit';
        return $this->button($label, $htmlOptions);
    }

    /**
     * @param $label
     * @param array $htmlOptions
     * @return string
     */
    public function resetButton($label, $htmlOptions = array())
    {
        $htmlOptions['type'] = 'Rest';
        return $this->button($label, $htmlOptions);
    }

    /**
     * @param $src
     * @param array $htmlOptions
     * @return string
     */
    public function imageButton($src, $htmlOptions = array())
    {
        $htmlOptions['src'] = $src;
        $htmlOptions['type'] = 'image';
        return $this->button('submit', $htmlOptions);
    }

    /**
     * @param $html
     * @param $for
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function label($html, $for, $htmlOptions = array())
    {
        $html = $this->escapeHtml($html);
        if ($for === false) {
            unset($htmlOptions['for']);
        } else {
            $htmlOptions['for'] = $for;
        }

        if (isset($htmlOptions['required'])) {
            if ($htmlOptions['required']) {
                if (isset($htmlOptions['class'])) {
                    $htmlOptions['class'] .= ' ' . self::$requiredCss;
                } else {
                    $htmlOptions['class'] = self::$requiredCss;
                }
                $html = self::$beforeRequiredLabel . $html . self::$afterRequiredLabel;
            }
            unset($htmlOptions['required']);
        }
        return $this->tag('label', $htmlOptions, $html);
    }

    /**
     * @param $type
     * @param $name
     * @param $value
     * @param $htmlOptions
     * @return string
     */
    protected function inputField($type, $name, $value, & $htmlOptions)
    {
        $htmlOptions['type']=$type;
        $htmlOptions['value']=$value;
        $htmlOptions['name']=$name;
        if (!isset($htmlOptions['id'])) {
            $htmlOptions['id'] = $this->getIdByName($name);
        } elseif ($htmlOptions['id'] === false) {
            unset($htmlOptions['id']);
        }
        return $this->tag('input',$htmlOptions);
    }

    /**
     * @param $name
     * @param string $value
     * @param array $htmlOptions
     * @return string
     */
    public function textField($name, $value = '', $htmlOptions = array())
    {
        return $this->inputField('text', $name, $value, $htmlOptions);
    }

    /**
     * @param $name
     * @param string $value
     * @param array $htmlOptions
     * @return string
     */
    public function hiddenField($name, $value = '', $htmlOptions = array())
    {
        return $this->inputField('hidden', $name, $value, $htmlOptions);
    }

    /**
     * @param $name
     * @param string $value
     * @param array $htmlOptions
     * @return string
     */
    public function passwordField($name, $value = '', $htmlOptions = array())
    {
        return $this->inputField('password', $name, $value, $htmlOptions);
    }

    /**
     * @param $name
     * @param string $value
     * @param array $htmlOptions
     * @return string
     */
    public function fileField($name, $value = '', $htmlOptions = array())
    {
        return $this->inputField('file', $name, $value, $htmlOptions);
    }

    /**
     * @param $name
     * @param string $value
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function textarea($name, $value = '', $htmlOptions = array())
    {
        $htmlOptions['name'] = $name;
        if (!isset($htmlOptions['id'])) {
            $htmlOptions['id'] = $this->getIdByName($name);
        } elseif ($htmlOptions['id'] === false) {
            unset($htmlOptions['id']);
        }
        return $this->tag('textarea', $htmlOptions, $this->escapeHtml($value));
    }

    /**
     * @param $name
     * @param bool $checked
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function radioBox($name, $checked = false, $htmlOptions = array())
    {
        if ( ! array_key_exists('id', $htmlOptions)) {
            $htmlOptions['id'] = self::ID_PREFIX . (self::$count++);
        }

        if ($checked) {
            $htmlOptions['checked'] = 'checked';
        } else {
            unset($htmlOptions['checked']);
        }
        $value = isset($htmlOptions['value']) ? $htmlOptions['value'] : 1;

        if (array_key_exists('uncheckedValue', $htmlOptions)) {
            $unchecked = $htmlOptions['uncheckedValue'];
            unset($htmlOptions['uncheckedValue']);
        } else {
            $unchecked = null;
        }

        if ($unchecked !== null) {
            // add a hidden field so that if the radio button is not selected, it still submits a value
            if (isset($htmlOptions['id']) && $htmlOptions['id'] !== false) {
                $uncheckedOptions = array('id' => self::ID_PREFIX . $htmlOptions['id']);
            } else {
                $uncheckedOptions = array('id' => false);
            }
            $hidden = $this->hiddenField($name, $unchecked, $uncheckedOptions);
        } else {
            $hidden = '';
        }

        $label = '';
        if (isset($htmlOptions['label'])) {
            $labelHtmlOptions = isset($htmlOptions['labelHtmlOptions']) ? $htmlOptions['labelHtmlOptions'] : array();
            $label = $this->label($htmlOptions['label'], $htmlOptions['id'], $labelHtmlOptions );
            unset($htmlOptions['label']);
            unset($htmlOptions['labelHtmlOptions']);
        }
        return $hidden . $this->inputField('radio', $name, $value, $htmlOptions) . $label;
    }

    /**
     * @param $name
     * @param bool $checked
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function checkBox($name, $checked = false, $htmlOptions = array())
    {
        if ( ! array_key_exists('id', $htmlOptions)) {
            $htmlOptions['id'] = self::ID_PREFIX . (self::$count++);
        }

        if ($checked) {
            $htmlOptions['checked'] = 'checked';
        } else {
            unset($htmlOptions['checked']);
        }
        $value = isset($htmlOptions['value']) ? $htmlOptions['value'] : 1;

        if (array_key_exists('uncheckedValue', $htmlOptions)) {
            $unchecked = $htmlOptions['uncheckedValue'];
            unset($htmlOptions['uncheckedValue']);
        } else {
            $unchecked = null;
        }

        if ($unchecked !== null) {
            // add a hidden field so that if the radio button is not selected, it still submits a value
            if (isset($htmlOptions['id']) && $htmlOptions['id'] !== false) {
                $uncheckedOptions = array('id' => self::ID_PREFIX . $htmlOptions['id']);
            } else {
                $uncheckedOptions = array('id' => false);
            }
            $hidden = $this->hiddenField($name, $unchecked, $uncheckedOptions);
        } else {
            $hidden = '';
        }

        $label = '';
        if(isset($htmlOptions['label'])) {
            $labelHtmlOptions = isset($htmlOptions['labelHtmlOptions']) ? $htmlOptions['labelHtmlOptions'] : array();
            $label = $this->label($htmlOptions['label'], $htmlOptions['id'], $labelHtmlOptions );
            unset($htmlOptions['label']);
            unset($htmlOptions['labelHtmlOptions']);
        }
        return $hidden . $this->inputField('checkbox', $name, $value, $htmlOptions) . $label;
    }


    /**
     * @param $name
     * @param $select
     * @param $data
     * @param array $htmlOptions
     * @return string
     */
    public function selectBox($name, $select, $data, $htmlOptions = array())
    {
        $htmlOptions['name'] = $name;
        if (!isset($htmlOptions['id'])) {
            $htmlOptions['id'] = $this->getIdByName($name);
        } elseif ($htmlOptions['id'] === false) {
            unset($htmlOptions['id']);
        }
        $options = "\n" . $this->listOptions($select, $data, $htmlOptions);
        return $this->openTag('select', $htmlOptions) . $options . $this->closeTag('select');
    }

    /**
     * @param $name
     * @param $select
     * @param $data
     * @param array $htmlOptions
     * @return string
     */
    public function listBox($name, $select, $data, $htmlOptions = array())
    {
        if (!isset($htmlOptions['size'])) {
            $htmlOptions['size'] = 4;
        }
        if (isset($htmlOptions['multiple'])) {
            if (substr($name, -2) !== '[]') {
                $name .= '[]';
            }
        }
        return $this->selectBox($name, $select, $data, $htmlOptions);
    }

    /**
     * @param $name
     * @param $select
     * @param $data
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function checkBoxList($name, $select, $data, $htmlOptions = array())
    {
        $template = isset($htmlOptions['htmlTemplate']) ? $htmlOptions['htmlTemplate'] : '{input} {label}';
        $separator = isset($htmlOptions['htmlSeparator']) ? $htmlOptions['htmlSeparator'] : "<br/>\n";
        unset($htmlOptions['htmlTemplate'], $htmlOptions['htmlSeparator']);

        if (substr($name, -2) !== '[]') {
            $name .= '[]';
        }

        $labelOptions = isset($htmlOptions['labelOptions']) ? $htmlOptions['labelOptions'] : array();
        unset($htmlOptions['labelOptions']);

        $items = array();
        $baseID = $this->getIdByName($name);
        $id = 0;
        foreach ($data as $value => $label) {
            $checked = !is_array($select) && !strcmp($value, $select) || is_array($select) && in_array($value, $select);
            $htmlOptions['value'] = $value;
            $htmlOptions['id'] = $baseID . '_' . $id++;
            $option = $this->checkBox($name, $checked, $htmlOptions);
            $label = $this->label($label, $htmlOptions['id'], $labelOptions);
            $items[] = strtr($template, array('{input}' => $option, '{label}' => $label));
        }
        return implode($separator, $items);
    }

    /**
     * @param $name
     * @param $select
     * @param $data
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function radioBoxList($name, $select, $data, $htmlOptions = array())
    {
        $template = isset($htmlOptions['htmlTemplate']) ? $htmlOptions['htmlTemplate'] : '{input} {label}';
        $separator = isset($htmlOptions['htmlSeparator']) ? $htmlOptions['htmlSeparator'] : "<br/>\n";
        unset($htmlOptions['htmlTemplate'], $htmlOptions['htmlSeparator']);

        $labelOptions = isset($htmlOptions['labelOptions']) ? $htmlOptions['labelOptions'] : array();
        unset($htmlOptions['labelOptions']);

        $items = array();
        $baseID = $this->getIdByName($name);
        $id = 0;
        foreach ($data as $value => $label) {
            $checked = !strcmp($value, $select);
            $htmlOptions['value'] = $value;
            $htmlOptions['id'] = $baseID . '_' . $id++;
            $option = $this->radioBox($name, $checked, $htmlOptions);
            $label = $this->label($label, $htmlOptions['id'], $labelOptions);
            $items[] = strtr($template, array('{input}' => $option, '{label}' => $label));
        }
        return implode($separator, $items);
    }

    /**
     * @param $dataForm
     */
    public function activeSetDataForm($dataForm)
    {
        $this->activeDataForm = $dataForm;
    }

    /**
     * @param $dataForm
     * @param string $action
     * @param array $htmlOptions
     * @return string
     */
    public function activeFormBegin($dataForm, $action = '', $htmlOptions = array())
    {
        $this->activeDataForm = $dataForm;
        return $this->formBegin($action, $htmlOptions);
    }

    /**
     * @return string
     */
    public function activeFormEnd()
    {
        return $this->formEnd();
    }

    /**
     * @param $label
     * @param $attribute
     * @param $type
     * @return string
     */
    protected function activeBeginFormRowLabelInput($label, $attribute, $type)
    {
        $args = func_get_args();
        $funcMap = array(
            'div'=>'activeDivField',
            'text'=>'activeTextField',
            'textfield'=>'activeTextField',
            'password'=>'activePasswordField',
            'textarea'=>'activeTextarea',
            'check'=>'activeCheckBox',
            'checkbox'=>'activeCheckBox',
            'checklist'=>'activeCheckBoxList',
            'radio'=>'activeRadioBox',
            'radiobox'=>'activeRadioBox',
            'radiolist'=>'activeRadioBoxList',
            'select'=>'activeSelectBox',
            'list'=>'activeListBox',
        );
        $func = $funcMap[strtolower($type)];
        $funcArgs = $args;
        array_splice($funcArgs, 0, 3);
        array_unshift($funcArgs, $attribute);

        $activeId = $this->activeId($attribute);
        $s = "<div class='form_row $activeId'>\n";
        $s .= "<div class='form_label $activeId'>" . $this->activeLabel($label, $attribute) . "</div>\n";
        $s .= "<div class='form_input $activeId'>" . call_user_func_array(array($this, $func), $funcArgs) . "</div>\n";
        return $s;
    }

    /**
     * @param $label
     * @param $attribute
     * @param $type
     * @param string $tip
     * @return mixed|string
     * @throws Exception
     */
    public function activeFormRowWithTip($label, $attribute, $type, $tip = '')
    {
        $args = func_get_args();
        array_splice($args, 3, 1);
        $activeId = $this->activeId($attribute);
        $s = call_user_func_array(array($this, 'activeBeginFormRowLabelInput'), $args);
        $s .= "<div class='form_tip $activeId'>";
        {
            $s .= "<div class='form_tip_message $activeId'>" . $this->escapeHtml($tip) . "</div>";

            $a = $attribute;
            $error = $this->activeDataForm->getError($this->resolveName($a));
            if($error) {
                $s .= $this->tag('div', array('class'=>'form_tip_error'), $this->escapeHtml($error));
            }
        }
        $s .= "</div>\n";

        $s .= "</div>\n"; // for activeBeginFormRowLabelInput
        return $s;
    }

    /**
     * @param $label
     * @param $attribute
     * @param $type
     * @return mixed|string
     */
    public function activeFormRow($label, $attribute, $type)
    {
        $args = func_get_args();
        $s = call_user_func_array(array($this, 'activeBeginFormRowLabelInput'), $args);
        $s .= "</div>\n"; // for activeBeginFormRowLabelInput
        return $s;
    }

    /**
     * @param $label
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeLabel($label, $attribute, $htmlOptions = array())
    {
        if (isset($htmlOptions['for'])) {
            $for = $htmlOptions['for'];
            unset($htmlOptions['for']);
        } else {
            $for = $this->getIdByName($this->resolveName($attribute));
        }

        if ($this->activeDataForm->hasErrors($attribute)) {
            $this->addErrorCss($htmlOptions);
        }

        return $this->label($label, $for, $htmlOptions);
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeDivField($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        $text = $this->resolveValue($attribute, $htmlOptions);
        return $this->tag('div', $htmlOptions, $this->escapeHtml($text));
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     */
    public function activeTextField($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);

        // we need the disabled value ( form won't submit it to us )
        $hidden = (isset($htmlOptions['disabled']) && $htmlOptions['disabled'])
            ? $this->hiddenField($htmlOptions['name'], $this->resolveValue($attribute, $htmlOptions), array('id'=>false)) : '';

        return $this->activeInputField('text', $attribute, $htmlOptions) . $hidden;
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     */
    public function activeHiddenField($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        return $this->activeInputField('hidden', $attribute, $htmlOptions);
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     */
    public function activePasswordField($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        // $this->clientChange('change', $htmlOptions);
        return $this->activeInputField('password', $attribute, $htmlOptions);
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeTextarea($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        if ($this->activeDataForm->hasErrors($attribute)) {
            $this->addErrorCss($htmlOptions);
        }
        $text = $this->resolveValue($attribute, $htmlOptions);

        // we need the disabled value ( form won't submit it to us )
        $hidden = (isset($htmlOptions['disabled']) && $htmlOptions['disabled'])
            ? $this->hiddenField($htmlOptions['name'], $text, array('id'=>false)) : '';
        return $this->tag('textarea', $htmlOptions, $this->escapeHtml($text)) . $hidden;
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     */
    public function activeFileField($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        // add a hidden field so that if a model only has a file field, we can
        // still use isset($_POST[$modelClass]) to detect if the input is submitted
        $hiddenOptions = isset($htmlOptions['id']) ? array('id' => self::ID_PREFIX . $htmlOptions['id']) : array('id' => false);
        return $this->hiddenField($htmlOptions['name'], '', $hiddenOptions)
            . $this->activeInputField('file', $attribute, $htmlOptions);
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeRadioBox($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        if (!isset($htmlOptions['value'])) {
            $htmlOptions['value'] = 1;
        }
        if (!isset($htmlOptions['checked']) && $this->resolveValue($attribute, $htmlOptions) == $htmlOptions['value']) {
            $htmlOptions['checked'] = 'checked';
        }
        // $this->clientChange('click', $htmlOptions);

        if (array_key_exists('uncheckedValue', $htmlOptions)) {
            $unchecked = $htmlOptions['uncheckedValue'];
            unset($htmlOptions['uncheckedValue']);
        } else {
            $unchecked = '0';
        }

        $hiddenOptions = isset($htmlOptions['id']) ? array('id' => self::ID_PREFIX . $htmlOptions['id']) : array('id' => false);
        $hidden = $unchecked !== null ? $this->hiddenField($htmlOptions['name'], $unchecked, $hiddenOptions) : '';

        $label = '';
        if(isset($htmlOptions['label'])) {
            $labelHtmlOptions = isset($htmlOptions['labelHtmlOptions']) ? $htmlOptions['labelHtmlOptions'] : array();
            $label = $this->label($htmlOptions['label'], $htmlOptions['id'], $labelHtmlOptions );
            unset($htmlOptions['label']);
            unset($htmlOptions['labelHtmlOptions']);
        }
        return $hidden . $this->activeInputField('radio', $attribute, $htmlOptions) . $label;
    }

    /**
     * @param $attribute
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeCheckBox($attribute, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        if (!isset($htmlOptions['value'])) {
            $htmlOptions['value'] = 1;
        }
        if (!isset($htmlOptions['checked']) && $this->resolveValue($attribute, $htmlOptions) == $htmlOptions['value']) {
            $htmlOptions['checked'] = 'checked';
        }
        // $this->clientChange('click', $htmlOptions);

        if (array_key_exists('uncheckedValue', $htmlOptions)) {
            $unchecked = $htmlOptions['uncheckedValue'];
            unset($htmlOptions['uncheckedValue']);
        } else {
            $unchecked = '0';
        }

        $hiddenOptions = isset($htmlOptions['id']) ? array('id' => self::ID_PREFIX . $htmlOptions['id']) : array('id' => false);
        $hidden = $unchecked !== null ? $this->hiddenField($htmlOptions['name'], $unchecked, $hiddenOptions) : '';

        $label = '';
        if(isset($htmlOptions['label'])) {
            $labelHtmlOptions = isset($htmlOptions['labelHtmlOptions']) ? $htmlOptions['labelHtmlOptions'] : array();
            $label = $this->label($htmlOptions['label'], $htmlOptions['id'], $labelHtmlOptions );
            unset($htmlOptions['label']);
            unset($htmlOptions['labelHtmlOptions']);
        }

        return $hidden . $this->activeInputField('checkbox', $attribute, $htmlOptions) . $label;
    }

    /**
     * @param $attribute
     * @param $data
     * @param array $htmlOptions
     * @return string
     */
    public function activeSelectBox($attribute, $data, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        $selection = $this->resolveValue($attribute, $htmlOptions);
        if ( ! empty($htmlOptions['size']) && $htmlOptions['size'] > 1) {
            if (empty($selection)) {
                $selection = array();
            } elseif (is_string($selection)) {
                $selection = json_decode($selection, true);
            }
        }
        $options = "\n" . $this->listOptions($selection, $data, $htmlOptions);
        // $this->clientChange('change', $htmlOptions);
        if ($this->activeDataForm->hasErrors($attribute)) {
            $this->addErrorCss($htmlOptions);
        }
        if (isset($htmlOptions['multiple'])) {
            if (substr($htmlOptions['name'], -2) !== '[]') {
                $htmlOptions['name'] .= '[]';
            }
        }
        return $this->tag('select', $htmlOptions, $options);
    }

    /**
     * @param $attribute
     * @param $data
     * @param array $htmlOptions
     * @return string
     */
    public function activeListBox($attribute, $data, $htmlOptions = array())
    {
        if (!isset($htmlOptions['size'])) {
            $htmlOptions['size'] = 4;
        }
        return $this->activeSelectBox($attribute, $data, $htmlOptions);
    }

    /**
     * @param $attribute
     * @param $data
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeCheckBoxList($attribute, $data, $htmlOptions = array())
    {
        $this->resolveNameID($attribute, $htmlOptions);
        $selection = $this->resolveValue($attribute, $htmlOptions);
        if (empty($selection)) {
            $selection = array();
        } elseif (is_string($selection)) {
            $selection = json_decode($selection, true);
        }
        if ($this->activeDataForm->hasErrors($attribute)) {
            $this->addErrorCss($htmlOptions);
        }
        $name = $htmlOptions['name'];
        unset($htmlOptions['name']);

        if (array_key_exists('uncheckedValue', $htmlOptions)) {
            $unchecked = $htmlOptions['uncheckedValue'];
            unset($htmlOptions['uncheckedValue']);
        } else {
            $unchecked = '';
        }

        $hiddenOptions = isset($htmlOptions['id']) ? array('id' => self::ID_PREFIX . $htmlOptions['id']) : array('id' => false);
        $hidden = $unchecked !== null ? $this->hiddenField($name, $unchecked, $hiddenOptions) : '';

        return $hidden . $this->checkBoxList($name, $selection, $data, $htmlOptions);
    }

    /**
     * @param $attribute
     * @param $data
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function activeRadioBoxList($attribute, $data, $htmlOptions = array())
    {
        $sep = isset($htmlOptions['sep']) ? $htmlOptions['sep'] : ',';
        unset($htmlOptions['sep']);

        $this->resolveNameID($attribute, $htmlOptions);
        $selection = $this->resolveValue($attribute, $htmlOptions);
        if ($this->activeDataForm->hasErrors($attribute)) {
            $this->addErrorCss($htmlOptions);
        }
        $name = $htmlOptions['name'];
        unset($htmlOptions['name']);

        if (array_key_exists('uncheckedValue', $htmlOptions)) {
            $unchecked = $htmlOptions['uncheckedValue'];
            unset($htmlOptions['uncheckedValue']);
        } else {
            $unchecked = '';
        }

        $hiddenOptions = isset($htmlOptions['id']) ? array('id' => self::ID_PREFIX . $htmlOptions['id']) : array('id' => false);
        $hidden = $unchecked !== null ? $this->hiddenField($name, $unchecked, $hiddenOptions) : '';

        return $hidden . self::radioBoxList($name, $selection, $data, $htmlOptions);
    }

    /**
     * @param $models
     * @param $valueField
     * @param $textField
     * @param string $groupField
     * @return array
     */
    public function listData($models, $valueField, $textField, $groupField = '')
    {
        $listData = array();
        if ($groupField === '') {
            foreach ($models as $model) {
                $value = $this->listDataValue($model, $valueField);
                $text = $this->listDataValue($model, $textField);
                $listData[$value] = $text;
            }
        } else {
            foreach ($models as $model) {
                $group = $this->listDataValue($model, $groupField);
                $value = $this->listDataValue($model, $valueField);
                $text = $this->listDataValue($model, $textField);
                $listData[$group][$value] = $text;
            }
        }
        return $listData;
    }

    /**
     * @param $model
     * @param $attribute
     * @param null $defaultValue
     * @return mixed|null
     */
    public function listDataValue($model, $attribute, $defaultValue = null)
    {
        if (strpos($attribute, '[') !== false) {
            $attributeKeys = explode('[', str_replace(']', '', $attribute));
        } else {
            $attributeKeys = array($attribute);
        }

        foreach ($attributeKeys as $name) {
            if (is_object($model)) {
                $model = $model->$name;
            } elseif (is_array($model) && isset($model[$name])) {
                $model = $model[$name];
            } else {
                return $defaultValue;
            }
        }
        return $model;
    }

    /**
     * @param $attribute
     * @return string
     */
    public function activeId($attribute)
    {
        return $this->getIdByName($this->activeName($attribute));
    }

    /**
     * @param $attribute
     * @return string
     */
    public function activeName($attribute)
    {
        $a = $attribute; // because the attribute name may be changed by resolveName
        return $this->resolveName($a);
    }

    /**
     * @param $type
     * @param $attribute
     * @param $htmlOptions
     * @return string
     */
    protected function activeInputField($type, $attribute, $htmlOptions)
    {
        $htmlOptions['type'] = $type;

        if ($type === 'file') {
            unset($htmlOptions['value']);
        } elseif (!isset($htmlOptions['value'])) {
            $htmlOptions['value'] = $this->resolveValue($attribute, $htmlOptions);
        }
        if ($this->activeDataForm->hasErrors($attribute)) {
            $this->addErrorCss($htmlOptions);
        }

        return $this->tag('input', $htmlOptions);
    }

    /**
     *  //FIXME: fix me ...
     *
     * @param $selection
     * @param $listData
     * @param array $htmlOptions
     * @return string
     * @throws Exception
     */
    public function listOptions($selection, $listData, &$htmlOptions = array())
    {
        $content = '';
        if (isset($htmlOptions['prompt'])) {
            $content .= '<option value="">' . $this->escapeHtml($htmlOptions['prompt']) . "</option>\n";
            unset($htmlOptions['prompt']);
        }
        if (isset($htmlOptions['empty'])) {
            if (!is_array($htmlOptions['empty'])) {
                $htmlOptions['empty'] = array('' => $htmlOptions['empty']);
            }
            foreach ($htmlOptions['empty'] as $value => $label) {
                $content .= '<option value="' . $this->escapeHtml($value) . '">' . $this->escapeHtml($label) . "</option>\n";
            }
            unset($htmlOptions['empty']);
        }

        if (isset($htmlOptions['options'])) {
            $options = $htmlOptions['options'];
            unset($htmlOptions['options']);
        } else {
            $options = array();
        }

        $key = isset($htmlOptions['key']) ? $htmlOptions['key'] : 'id';
        if (is_array($selection)) {
            if( isset($selection[0])) {
                foreach ($selection as $i => $item) {
                    if (is_object($item)) {
                        $selection[$i] = $item->$key;
                    }
                    if (is_array($item)) {
                        $selection[$i] = $item[$key];
                    }
                }
            } else {
                $selection = $selection[$key];
            }
        } elseif (is_object($selection)) {
            $selection = $selection->$key;
        }

        foreach ($listData as $key => $value) {
            if (is_array($value)) {
                $content .= '<optgroup label="' . $this->escapeHtml($key) . "\">\n";
                $sub_options = array('options' => $options);
                $content .= $this->listOptions($selection, $value, $sub_options);
                $content .= '</optgroup>' . "\n";
            } else {
                $attributes = array('value' => (string)$key);
                if (!is_array($selection) && !strcmp($key, $selection) || is_array($selection) && in_array($key, $selection)) {
                    $attributes['selected'] = 'selected';
                }
                if (isset($options[$key])) {
                    $attributes = array_merge($attributes, $options[$key]);
                }
                $content .= $this->tag('option', $attributes, $this->escapeHtml($value)) . "\n";
            }
        }

        unset($htmlOptions['key']);

        return $content;
    }

    /**
     * @param $attribute
     * @param $htmlOptions
     */
    public function resolveNameID(&$attribute, &$htmlOptions)
    {
        if (!isset($htmlOptions['name'])) {
            $htmlOptions['name'] = $this->resolveName($attribute);
        }
        if (!isset($htmlOptions['id'])) {
            $htmlOptions['id'] = $this->getIdByName($htmlOptions['name']);
        } elseif ($htmlOptions['id'] === false) {
            unset($htmlOptions['id']);
        }
    }

    /**
     * @param $attribute
     * @return string
     */
    public function resolveName( & $attribute)
    {
        $modelName = $this->activeDataForm->getName();
        $firstName = null;
        $otherNames = '';
        if (($pos = strpos($attribute, '[')) !== false) {
            if ($pos !== 0) { // e.g. name[a][b]
                $firstName = substr($attribute, 0, $pos);
                $otherNames = substr($attribute, $pos);
            } else {
                throw new LogicException('bad attribute name');
            }
        }
        if(is_null($firstName)) {
            $firstName = $attribute;
        }
        if($modelName) {
            $attribute = $modelName . '[' . $firstName . ']' . $otherNames;
        } else {
            $attribute = $firstName . $otherNames;
        }

        return $attribute;
    }

    /**
     * @param $attribute
     * @param $htmlOptions
     * @return false|string
     */
    public function resolveValue($attribute, & $htmlOptions)
    {
        $value = $this->activeDataForm->get($attribute);
        if (isset($htmlOptions['valueStringFormat'])) {
            $fmt = $htmlOptions['valueStringFormat'];
            unset($htmlOptions['valueStringFormat']);
            $value = sprintf($fmt, $value);
        }
        if (isset($htmlOptions['valueDateFormat'])) {
            $fmt = $htmlOptions['valueDateFormat'];
            unset($htmlOptions['valueDateFormat']);
            if ($fmt === true || $fmt === 'true') {
                $value = date('Y-m-d H:i:s', $value);
            } elseif(strpos($fmt, '%') !== false) {
                $value = strftime ($fmt, $value);
            } else {
                $value = date($fmt, $value);
            }
        }
        return $value;
    }

    /**
     * @var string
     */
    protected $pageNumberDummy = "_PAGE.NUMBER_";  //only letter, number, '_', '.' is safe char (RFC)

    /**
     *
     */
    const PAGE_NUMBER_DUMMY = "_PAGE.NUMBER_";

    /**
     * @param $baseurl
     * @param $dummy
     * @param $n
     * @return mixed|string
     */
    protected function pagerHelperGetEscapedUrl($baseurl, $dummy, $n)
    {
        $s = str_replace($dummy, $n, $baseurl, $count);
        if ($count == 0) {
            $s = $baseurl . $n;
        }
        return $s;
    }

    /**
     * @param $pageBaseUrl
     * @param $pageResult
     * @param array $options
     * @return string
     */
    public function pagerSimple($pageBaseUrl, $pageResult, $options = array()) {
        $pageBaseUrl = $this->viewController->buildUrl($pageBaseUrl);
        $page_number = $pageResult->pageNumber;
        $page_count = $pageResult->pageCount;

        $button_count = 9;

        $first_page_text = "&laquo;";
        $last_page_text = "&raquo;";
        $previous_page_text = "&lsaquo;";
        $next_page_text = "&rsaquo;";

        if ($page_number == 0) {
            $page_number = 1;
        }

        //导航页码在当前页码左右均分
        $half_button_count = intval($button_count/2);
        $button_page_number_start = $page_number - $half_button_count;
        $button_page_number_end = $page_number + $half_button_count;

        //如果左边越界，则扩展右边页码
        if ($button_page_number_start < 1) {
            $button_page_number_end += ( 1 - $button_page_number_start);
            if ($button_page_number_end > $page_count) {
                $button_page_number_end = $page_count;
            }
            $button_page_number_start = 1;
        }

        //如果右边越界，则扩展左边页码
        if ($button_page_number_end > $page_count) {
            $button_page_number_start -= ($button_page_number_end - $page_count);
            if ($button_page_number_start < 1) {
                $button_page_number_start = 1;
            }

            $button_page_number_end = $page_count;
        }

        // 如果首页和尾页页码不足以放省略号，则强制不用省略号
        if ($button_page_number_start <= 3) {
            $button_page_number_start = 1;
        }
        if ($page_count - $button_page_number_end + 1<= 3) {
            $button_page_number_end = $page_count;
        }


        $html_buttons = "";
        $html_buttons .= "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, 1) . "'>{$first_page_text}</a></li>";
        if ($page_number > 1) {
            $html_buttons .= "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $page_number - 1) . "'>{$previous_page_text}</a></li>";
        } else {
            $html_buttons .= "<li><a>{$previous_page_text}</a></li>";
        }

        if ($button_page_number_start > 3) {
            for ($i = 1; $i <= 2; $i++) {
                $html_buttons .= "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $i) . "'>$i</a></li>";
            }
            $html_buttons .= "<li>...</li>";
        }
        for ($i = $button_page_number_start; $i <= $button_page_number_end; $i++) {
            if ($i == $page_number) {
                $s = "<li class='current'><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $i) . "'>$i</a></li>";
            } else {
                $s = "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $i) . "'>$i</a></li>";
            }
            $html_buttons .= $s;
        }
        if ($page_count - $button_page_number_end + 1 > 3) {
            $html_buttons .= "<li>...</li>";
            for ($i = $page_count - 1; $i <= $page_count; $i++) {
                $html_buttons .= "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $i) . "'>$i</a></li>";
            }
        }
        if ($page_number < $page_count) {
            $html_buttons .= "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $page_number + 1) . "'>{$next_page_text}</a></li>";
        } else {
            $html_buttons .= "<li><a>{$next_page_text}</a></li>";
        }
        $html_buttons .= "<li><a href='" . $this->pagerHelperGetEscapedUrl($pageBaseUrl, self::PAGE_NUMBER_DUMMY, $page_count) . "'>{$last_page_text}</a></li>";
        return $html_buttons;
    }
}


class DataFormCheck
{
    public $dataForm;
    public $key;
    public $value;
    public $result;

    /**
     * DataFormCheck constructor.
     * @param $dataForm
     * @param $k
     * @param $v
     */
    public function __construct($dataForm, $k, $v)
    {
        $this->dataForm = $dataForm;
        $this->key = $k;
        $this->value = $v;
        $this->result = true;
    }

    /**
     * @param $msg
     * @return $this
     */
    public function addMessageIfError($msg)
    {
        $this->dataForm->addMessageIfError($msg);
        return $this;
    }

    /**
     * @return $this
     */
    public function not()
    {
        $this->result = !$this->result;
        return $this;
    }

    /**
     * @return $this
     */
    public function isExisting()
    {
        $this->result = $this->result && $this->dataForm->keyExists($this->key);
        return $this;
    }

    /**
     * @return $this
     */
    public function isNotEmpty()
    {
        $this->result = $this->result && !empty($this->value);
        return $this;
    }

    /**
     * @return $this
     */
    public function isValidEmail()
    {
        $this->result = $this->result && Utility::IsValidEMail($this->value);
        return $this;
    }

    /**
     * @return $this
     */
    public function isValidDate()
    {
        $this->result = $this->result && Utility::IsValidDate($this->value);
        return $this;
    }

    /**
     * @return $this
     */
    public function isValidDatetime()
    {
        $this->result = $this->result && Utility::IsValidDatetime($this->value);
        return $this;
    }

    /**
     * @return $this
     */
    public function isValidCodeSymbol()
    {
        $this->result = $this->result && Utility::IsValidCodeSymbol($this->value);
        return $this;
    }

    /**
     * @param $a
     * @param $b
     * @return $this
     */
    public function isBetween($a, $b)
    {
        $ret = true;
        if ($a !== null) {
            $ret = $ret && ($a <= $this->value);
        }
        if ($b !== null) {
            $ret = $ret && ($this->value <= $b);
        }
        $this->result = $this->result && $ret;
        return $this;
    }

    /**
     * @param $a
     * @param $b
     * @return $this
     */
    public function isLengthBetween($a, $b)
    {
        $l = strlen($this->value);
        $ret = true;
        if ($a !== null) {
            $ret = $ret && ($a <= $l);
        }
        if ($b !== null) {
            $ret = $ret && ($l <= $b);
        }
        $this->result = $this->result && $ret;
        return $this;
    }

    /**
     * @param $a
     * @param null $strict
     * @return $this
     */
    public function isInArray($a, $strict = null)
    {
        $this->result = $this->result && in_array($this->value, $a, $strict);
        return $this;
    }

    /**
     * @param $v
     * @return $this
     */
    public function isEqualTo($v)
    {
        $this->result = $this->result && ($this->value == $v);
        return $this;
    }

    /**
     * @param $v
     * @return $this
     */
    public function isEqualToStrictly($v)
    {
        $this->result = $this->result && ($this->value === $v);
        return $this;
    }

    /**
     * @param $anotherKey
     * @return $this
     */
    public function isEqualToKey($anotherKey)
    {
        $this->result = $this->result && ($this->value == $this->dataForm->$anotherKey);
        return $this;
    }

    /**
     * @param $anotherKey
     * @return $this
     */
    public function isEqualToKeyStrictly($anotherKey)
    {
        $this->result = $this->result && ($this->value === $this->dataForm->$anotherKey);
        return $this;
    }
}


class DataForm
{
    // none should be public. a __get is there
    protected $name;
    protected $data = array();
    protected $tipMessageManager = null;
    protected $currentChecking = null;
    protected $keys;
    protected $ignoredKeysForDbSave = array();

    /**
     * @param $inputs
     * @param $keys
     * @param string $name
     * @return DataForm
     */
    static public function FromInput($inputs, $keys, $name = '')
    {
        $f = new DataForm();
        $f->initFromInput($inputs, $keys, $name);
        return $f;
    }

    /**
     * DataForm constructor.
     */
    public function __construct()
    {
        $this->tipMessageManager = TipMessageManager::GetDefault();
    }

    /**
     * @param $inputs
     * @param $keys
     * @param $name
     */
    protected function initFromInput($inputs, $keys, $name)
    {
        $this->name = $name;
        $this->keys = $keys;
        if ( ! empty($this->name) ) {
            $inputs = isset($inputs[$this->name]) ? $inputs[$this->name] : array();
        }
        foreach($keys as $k=>$def) {
            if (is_int($k)) {
                $k = $def;
                $def = null;
            }

            if (isset($inputs[$k])) {
                if (is_float($def)) {
                    $this->data[$k] = floatval ($inputs[$k]);
                } elseif (is_int($def)) {
                    $this->data[$k] = intval ($inputs[$k]);
                } elseif (is_array($def)) {//TODO: merge with default
                    $this->data[$k] = $inputs[$k];
                } else {
                    $this->data[$k] = $inputs[$k];
                }
            } else {
                $this->data[$k] = $def;
            }
        }
    }

    /**
     *
     */
    public function varDump()
    {
        var_dump($this->data);
    }

    /**
     * @param $row
     * @return bool
     */
    public function appendFromDatabaseRow($row)
    {
        if (empty($row)) {
            return false;
        }

        foreach ($row as $k=>$v) {
            $is_array = isset($this->keys[$k]) && is_array($this->keys[$k]);
            if (!array_key_exists($k, $this->data)) {
                if ( $is_array ) {
                    $this->data[$k] = is_array($v) ? $v : json_decode($v, true);
                } else {
                    $this->data[$k] = $v;
                }
            }
        }
        return true;
    }

    /**
     * @param $row
     * @return bool
     */
    public function fillFromDatabaseRow($row)
    {
        if ( empty($row) ) {
            return false;
        }

        foreach ($row as $k=>$v) {
            $is_array = isset($this->keys[$k]) && is_array($this->keys[$k]);
            if ( $is_array ) {
                $this->data[$k] = is_array($v) ? $v : json_decode($v, true);
            } else {
                $this->data[$k] = $v;
            }
        }
        return true;
    }

    /**
     * @param $key
     */
    public function ignoreDbSave($key)
    {
        if (is_string($key)) {
            $this->ignoredKeysForDbSave[] = $key;
        } else {
            $this->ignoredKeysForDbSave[] = array_merge($this->ignoredKeysForDbSave, $key);
        }
    }

    /**
     * @return array
     */
    public function toDbSaveWithoutNull()
    {
        $a = array();
        foreach($this->data as $k=>$v) {
            if (!is_null($v) && !in_array($k, $this->ignoredKeysForDbSave)) {
                $a[$k] = is_array($v) ? json_encode($v) : $v;
            }
        }
        return $a;
    }

    /**
     * @param $m
     */
    public function setTipMessageManager($m)
    {
        $this->tipMessageManager = $m;
    }

    /**
     * @param null $keys
     */
    public function trim($keys = null)
    {
        if (is_null($keys)) {
            foreach ($this->data as $k=>$v) {
                if (is_string($v)) {
                    $this->data[$k] = trim($v);
                }
            }
        } else {
            foreach ($keys as $k) {
                if (is_string($this->data[$k])) {
                    $this->data[$k] = trim($this->data[$k]);
                }
            }
        }
    }

    /**
     * @param $key
     * @return array
     */
    protected function parseKey($key)
    {
        if (strpos($key, '[') !== false) {
            $key = str_replace(']', '', $key);
            return explode('[', $key);
        }
        return array($key);
    }

    /**
     * @param null $keys
     * @return array
     */
    public function getData($keys = null)
    {
        if (is_null($keys)) {
            return $this->data;
        } elseif (is_array($keys)) {
            foreach ($keys as $k) {
                $a[$k] = $this->data[$k];
            }
            return $a;
        }
    }

    /**
     * @param $k
     * @return array|mixed
     */
    public function &get($k)
    {
        $a = $this->parseKey($k);
        $v = & $this->data;
        foreach ($a as $k) {
            if (is_array($v) && isset($v[$k])) {
                $v = & $v[$k];
            } else {
                $v[$k] = null;
                $v = & $v[$k];
            }
        }
        return $v;
    }

    /**
     * @param $k
     * @return bool
     */
    public function __isset($k)
    {
        return isset($this->data[$k]);
    }

    /**
     * @param $k
     */
    public function __unset($k)
    {
        unset($this->data[$k]);
    }

    /**
     * @param $k
     * @return array|mixed
     */
    public function &__get($k)
    {
        return $this->get($k);
    }

    /**
     * @param $k
     * @param $v
     */
    public function __set($k, $v)
    {
        $a = $this->parseKey($k);
        $r = & $this->data;
        foreach ($a as $k) {
            if (!is_array($r)) {
                return;
            }
            if (!isset($r[$k])) {
                $r[$k] = null;
            }
            $r = &$r[$k];
        }
        $r = $v;
    }

    /**
     * @param $k
     * @return bool
     */
    public function keyExists($k)
    {
        $a = $this->parseKey($k);
        $v = $this->data;
        foreach ($a as $k) {
            if (is_array($v) && array_key_exists($k, $v)) {
                $v = $v[$k];
                continue;
            }
            return false;
        }
        return true;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $k
     * @return mixed
     */
    public function hasErrors($k)
    {
        return $this->tipMessageManager->hasMessageError($k);
    }

    /**
     * @param $k
     * @return null
     */
    public function getError($k)
    {
        $m = $this->tipMessageManager->getMessagesError($k);
        if ( ! empty($m)) {
            return $m[0]['message'];
        }
        return null;
    }

    /**
     * @param $k string
     * @return DataFormCheck
     */
    public function check($k)
    {
        if (!empty($this->currentChecking)) {
            $this->currentChecking->dataForm = null;
        }

        $this->currentChecking = new DataFormCheck($this, $k, $this->get($k));
        return $this->currentChecking;
    }

    /**
     * @param $msg
     */
    public function addMessageIfError($msg)
    {
        if (!$this->currentChecking->result) {
            $this->tipMessageManager->addMessageError($msg, $this->currentChecking->key, $this->currentChecking->value);
        }
        $this->currentChecking->result = true; // reset the check
    }
}


class TipMessageManager
{
    protected $messages = array();

    const Type_Error = 'Error';
    const Type_Warning = 'Warning';
    const Type_Success = 'Success';
    const Type_Info = 'Info';
    const Type_All = 'All';

    /**
     * @param $data
     */
    public function import($data)
    {
        $this->messages = $data;
    }

    /**
     * @return array
     */
    public function export()
    {
        return $this->messages;
    }

    /**
     * @param null $type
     * @param null $for
     * @return bool
     */
    public function hasMessages($type = null, $for = null)
    {
        if ($type) {
            if (!empty($this->messages[$type])) {
                if (empty($for)) {
                    return true;
                }
                foreach ($this->messages[$type] as $one) {
                    if ($one['for'] == $for) {
                        return true;
                    }
                }
            }
            return false;
        }
        return $this->hasMessages('Error', $for) || $this->hasMessages('Warning', $for) || $this->hasMessages('Success', $for) || $this->hasMessages('Info', $for);
    }

    /**
     * @param null $for
     * @return bool
     */
    public function hasMessageError($for = null)
    {
        return $this->hasMessages('Error', $for);
    }

    /**
     * @param null $for
     * @return array|mixed
     */
    public function getMessagesError($for = null)
    {
        return $this->getMessages('Error', $for);
    }

    /**
     * @param string $type
     * @param null $for
     * @return array|mixed
     */
    public function getMessages($type = 'All', $for = null)
    {
        if (!empty($this->messages[$type])) {
            if (empty($for)) {
                return $this->messages[$type];
            }

            $m = array();
            foreach ($this->messages[$type] as $one) {
                if ($one['for'] == $for) {
                    $m[] = $one;
                }
            }
            return $m;
        }
        return array();
    }

    /**
     * @param $type
     * @param $for
     * @return null
     */
    public function getOneMessageContent($type, $for)
    {
        if (!empty($this->messages[$type])) {
            foreach ($this->messages[$type] as $one) {
                if ($one['for'] == $for) {
                    return $one['content'];
                }
            }
        }
        return null;
    }

    /**
     * @param $type
     * @param $for
     * @param $sep
     * @return null|string
     */
    public function getMessageContents($type, $for, $sep)
    {
        if( ! empty($this->messages[$type]))
        {
            $contents = array();
            foreach($this->messages[$type] as $one)
            {
                if($one['for'] == $for)
                    $contents[] = $one['content'];
            }
            return join($sep, $contents);
        }
        return null;
    }

    public function addMessage($type, $content, $for, $data)
    {
        $msg = array('type'=>$type, 'message'=>$content, 'content'=>$content,  'for'=>$for, 'data'=>$data);

        //TODO: 完善上限机制
        $maxNumber = null;
        if(isset($this->messages[$type]) && ! is_null($maxNumber))
        {
            $count = count($this->messages[$type]);

            //ignore too many messages
            if($count > $maxNumber)
                return;

            //add one for dummy
            if($count == $maxNumber)
                $msg = array('type'=>$type, 'message'=>'... ...',  'content'=>'... ...', 'for'=>$for, 'data'=>$data);
        }

        $this->messages[$type][] = $msg;
        $this->messages[self::Type_All][] = $msg;
    }
    public function addMessageError($content, $for = null, $data = null)
    {
        $this->addMessage('Error', $content, $for, $data);
    }
    public function addMessageWarning($content, $for = null, $data = null)
    {
        $this->addMessage('Warning', $content, $for, $data);
    }
    public function addMessageSuccess($content, $for = null, $data = null)
    {
        $this->addMessage('Success', $content, $for, $data);
    }
    public function addMessageInfo($content, $for = null, $data = null)
    {
        $this->addMessage('Info', $content, $for, $data);
    }
    public function addMessageSuccessOrError($cond, $success, $error, $for = null, $data = null)
    {
        if($cond)
            $this->addMessageSuccess($success, $for, $data);
        else
            $this->addMessageError($error, $for, $data);
    }

    public function clear()
    {
        $this->messages = array();
    }

    protected static $default = null;
    /**
     * @static
     * @return TipMessageManager
     */
    static public function GetDefault()
    {
        if( ! self::$default)
            self::$default = new TipMessageManager();
        return self::$default;
    }
}

