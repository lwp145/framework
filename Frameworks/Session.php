<?php
/**
 * Session class file.
 */

/**
 * This class provides a series of methods for session processing.
 *
 * @uses JM_CART_SESSION_KEY
 */
class Session
{
    const SESSION_NAME = 'PHPSESSID';
    protected static $instance;

    protected function __construct($params)
    {
        $sessionName = self::SESSION_NAME;
        session_name($sessionName);
        $this->setParams($params);
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Get instance of Session.
     *
     * @param array $params Refer to {@link session_set_cookie_params}.
     * All options are:
     * e.g.
     * <pre>
     * array('lifetime' =>0,
     *       'path' => '/',
     *       'domain' => '.domain.net',
     *       'secure' => false,
     *       'httponly' => false
     *      )
     * <pre>
     *
     * @return JMSession
     */
    public static function instance(array $params = array())
    {
        if (!self::$instance) {
            self::$instance = new static($params);
        }
        return self::$instance;
    }

    /**
     * Get/Set name for the current session.
     *
     * @param string $name session name.
     * @return string
     */
    public function name($name = null)
    {
        if (is_null($name)) {
            return session_name();
        }
        return session_name($name);
    }

    /**
     * Get/Set id for the current session.
     *
     * @param string $id session id.
     * @return string
     */
    public function id($id=null)
    {
        if (is_null($id)) {
            return session_id();
        }
        return session_id($id);
    }

    public function &__get($name)
    {
        if (!isset($_SESSION[$name])) {
            $null;
            return $null;
        }
        return $_SESSION[$name];
    }

    public function &__set($name, $value)
    {
        $_SESSION[$name] = $value;
        return $_SESSION[$name];
    }

    /**
     * Set cookie params. Refer to {@link session_set_cookie_params}.
     * All options are:
     * e.g.
     * <pre>
     * array('lifetime' =>0,
     *       'path' => '/',
     *       'domain' => '.domain.net',
     *       'secure' => false,
     *       'httponly' => false
     *      )
     * <pre>
     *
     * @param array $params name and value pairs.
     * @global $serverConfig['session']
     */
    protected function setParams(array $params = array())
    {
        $serverConfig = Registry::get('serverConfig');
        $defaultOptions = array('lifetime'=>0, 'path'=>'/', 'domain'=>'', 'secure'=>false, 'httponly'=>false);
        if (isset($serverConfig['session']) && is_array($serverConfig['session'])) {
            $defaultOptions = array_merge($defaultOptions, $serverConfig['session']);
        }
        $options = array_merge($defaultOptions, $params);
        session_set_cookie_params($options['lifetime'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);
    }

    /**
     * Delete an item from session.
     * <pre>
     * //delete only an item from array
     * JMSession::instance()->del(array('a','b','c');
     * //above equals to  unset($_SESSION['a']['b']['c']
     * </pre>
     * @param string|array $name item name
     */
    public function del($name)
    {
        if (is_array($name)) {
            $name = array_map(function($e){return "['{$e}']";}, $name);
            $name = implode('', $name);
            eval('unset($_SESSION'.$name.');');
        } else {
            unset($_SESSION[$name]);
        }
    }

    /**
     * Checks if an item exists in Session.
     *
     * @param string $name item name
     * @return boolean
     */
    public function exists($name)
    {
        return isset($_SESSION[$name]);
    }
}