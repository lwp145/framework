<?php
/**
 * JMTemplate是一个简单的php模板引擎，采用原生php进行解析.
 */

if (!defined('JMTEMPLATE_CORE')) {
    define('JMTEMPLATE_CORE',dirname(__FILE__));
}

include_once JMTEMPLATE_CORE . '/systemplate/Plugin.class.php';

class Template
{
    /**
     *
     *
     * 配置参数
     *
     * @var Array
     */
    protected $__config = array(
        'template_path' => '',
        'plugins_path' => '',
        'plugins_template_path'=>'',
        'plugins' => array(),
        'file_extension' => '.tpl.php',
        'exceptions' => false
    );

    protected $current_template_file;
    /**
     * static variables
     */
    // assigned global tpl vars
    public $tpl_vars = array();

    /**
     * 模板构造函数
     *
     * @param unknown_type $config
     */
    public function __construct($config = null)
    {
        // 强制传入的参数为数组类型
        settype($config, 'array');

        $configKey = array_intersect_key($config, $this->__config);

        $this->__config = $configKey + $this->__config;

    }

    /**
     * Class destructor
     */
    public function __destruct()
    {


    }

    public function currentTemplateFile()
    {
        return $this->current_template_file;
    }

    public function setConfig($var, $value){
        $this->__config[$var] = $value;
    }

    /**
     *
     * @param  键名 $name
     * @return Ambigous <boolean, multitype:>
     */
    public function __get($name)
    {

        return isset($this->tpl_vars[$name]) ? $this->tpl_vars[$name] : false;

    }

    /**
     *
     * @param unknown_type $name
     * @param unknown_type $value
     */
    public function __set($name, $value)
    {
        $this->tpl_vars[$name] = $value;

    }

    /**
     *
     * @param unknown_type $name
     * @param unknown_type $value
     */
    public function assign($tpl_var, $value=null)
    {
        if (is_array($tpl_var)) {
            foreach ($tpl_var as $_key => $_val) {
                if ($_key != '') {
                    $this->$_key = $_val;
                }
            }
        } else {
            if ($tpl_var != '') {
                $this->$tpl_var = $value;
            }
        }

    }

    public function getVar($name){
        return $this->$name;
    }

    /**
     *
     *
     * 获取文件路径
     *
     * @param unknown_type $type
     * @param unknown_type $file
     */
    public function findPath($type, $file)
    {

        $filePath = $this->__config[$type] . $file;

        if (file_exists($filePath) && is_readable($filePath)) {
            return $filePath;
        }
        return false;

    }

    public function template($tpl, &$realpath='')
    {
        $filePath = $this->__config['template_path'] . $tpl . '.php';
        if (!file_exists($filePath)) {
            $filePath = $this->__config['template_path'] . $tpl .
                $this->__config['file_extension'];
        }
        $realpath = $filePath;
        if (file_exists($filePath) && is_readable($filePath)) {
            $this->current_template_file = $filePath;
            return $filePath;
        }
        return false;
    }

    /**
     *
     *
     * 插件调用方法
     *
     * @param String $name
     * @return Ambigous <>
     */
    public function plugin($name,$tpl='')
    {
        if (!array_key_exists($name, $this->__config['plugins'])) {
            $class = "JMPlugin_$name";

            if (!class_exists($class)) {
                $file = "$class.class.php";
                $result = $this->findPath('plugins_path', $file);

                if (!$result) {
                    return $this->error('ERR_PLUGIN');
                }
                else {
                    include_once $result;
                }
            }

            $this->__config['plugins'][$name] = new $class($this,$tpl);
        }

        return $this->__config['plugins'][$name];

    }

    /**
     *
     *
     *
     * 模板渲染
     *
     * @param String $tpl
     * @return html
     */
    public function display($tpl)
    {

        echo $this->fetch($tpl);

    }

    public function fetch($tpl, $withLayout = true)
    {
        $result = $this->template($tpl, $realpath);

        if (!$result) {
            return $this->error('Template "'.$realpath.'" not found!');
        }
        if (defined('PHPUNIT_MODE') && PHPUNIT_MODE) {
            return '';
        }
        extract($this->tpl_vars);
        ob_start();
        include $result;
        $content = ob_get_clean();

        if ($withLayout && !empty($this->layouts)) {
            $layout = array_pop($this->layouts);
            $content = $this->fetch($layout);
        }

        return $content;
    }

    public function error($code) {

        include_once JMTEMPLATE_CORE . '/systemplate/Exception.php';
        throw new JMTemplate_Exception($code);
    }

    protected $layouts = array();
    protected $variables = array();
    protected $blocks = array();

    public function extending($layout)
    {
        array_push($this->layouts, $layout);
    }

    public function block($variable)
    {
        array_push($this->variables, $variable);
        if (!isset($this->blocks[$variable])) {
            $this->blocks[$variable] = array();
        }
        ob_start();
    }

    public function endblock()
    {
        $ctx = ob_get_clean();

        $variable = array_pop($this->variables);
        array_push($this->blocks[$variable], $ctx);

        if (empty($this->layouts)) {
            echo array_shift($this->blocks[$variable]);
        }
    }

    public function includes($template)
    {
        $this->fetch($template, false);
    }
}