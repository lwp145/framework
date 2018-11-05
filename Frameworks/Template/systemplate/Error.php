<?php
/**
 * Throws PHP5 exceptions for Template.
 *
 * 系统要求 php5.3+
 */

/**
 * Provides a simple error class for Template.
 */
class JMTemplate_Exception extends Exception
{
    /**
     * 错误代码.
     *
     * @access public
     *
     * @var string
     */
    public $code = null;

    /**
     * 错误信息.
     *
     * @access public
     *
     * @var array
     */
    public $info = array();

    /**
     * 错误级别.
     *
     * @access public
     *
     * @var int
     */
    public $level = E_USER_ERROR;

    /**
     * 错误跟踪.
     *
     * @access public
     *
     * @var array
     */
    public $trace = null;

    public function __construct($conf = array())
    {
        // set public properties
        foreach ($conf as $key => $val) {
            $this->$key = $val;
        }

        // add a backtrace
        if ($conf['trace'] === true) {
            $this->trace = debug_backtrace();
        }
    }

    public function __toString()
    {
        ob_start();
        echo get_class($this) . ': ';
        print_r(get_object_vars($this));
        return ob_get_clean();
    }
}