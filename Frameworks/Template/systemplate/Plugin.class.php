<?php

/**
 * @package Template
 *
 * Plugin 插件父类
 *
 * 系统要求 php5.3+
 *
 * @Author: Jiang Lin <linj@jumei.com>
 */
class Plugin
{

    /**
     *
     *
     * 模板对象
     *
     * @var unknown_type
     */
    protected $mainView;

    public $tpl;

    /**
     * 插件构造函数
     */
    public function __construct($class, $tpl)
    {

        $this->mainView = $class;
        $this->tpl = $tpl;

    }

    public function displayPlugin($args = array(), $tpl='')
    {
        if(!empty($tpl)) {
            $this->tpl = $tpl;
        }
        if(!empty($this->tpl)) {

            $fileName = '';
            if(file_exists($this->tpl)&&is_readable($this->tpl)) {

                $fileName = $this->tpl;
            } elseif ($result = $this->mainView->findPath('plugins_template_path', $this->tpl . '.php')) {
                $fileName = $result;
            }
            else if($result=$this->mainView->findPath('plugins_template_path',$this->tpl.'.tpl.php')){
                $fileName = $result;
            }
            else {
                return $this->mainView->error('ERR_PLUGIN_TPL');
            }
            if (!empty($fileName)) {


                if (is_array($args)) {
                    extract($args);
                }

                include $fileName;
            }
        }
        return false;

    }

    public function setTpl($tpl) {
        $this->tpl = $tpl;
        return $this;
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {


    }

}
