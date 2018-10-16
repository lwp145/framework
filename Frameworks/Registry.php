<?php
/**
 * Registry class file.
 */

class Registry extends ArrayObject
{
    /**
     * 单件注册对象类的名字.
     *
     * @var string
     */
    private static $_registryClassName = 'Registry';

    /**
     * 提供公共存储和访问的注册对象.
     *
     * @var Registry
     */
    private static $_registry = null;

    /**
     * Registry constructor.
     *
     * @param array $input
     * @param int $flags
     *
     */
    public function __construct($input = array(), $flags = parent::ARRAY_AS_PROPS)
    {
        parent::__construct($input, $flags);
    }

    /**
     * 确定一个元素是否存在.
     *
     * @param mixed $index
     * @return boolean
     */
    public function offsetExists($index)
    {
        return array_key_exists($index, $this);
    }

    /**
     * 获取默认的注册对象实例.
     *
     * @return Registry
     *
     * @throws Exception
     */
    public static function getInstance()
    {
        if (self::$_registry === null) {
            self::init();
        }
        return self::$_registry;
    }

    /**
     * 初始化默认实例.
     *
     * @return void
     *
     * @throws Exception
     */
    public static function init()
    {
        $obj = new self::$_registryClassName();
        if ($obj instanceof Registry) {
            self::setInstance($obj);
        }
    }

    /**
     * 设置默认注册对象实例.
     *
     * @param Registry $registry.
     * @return void
     * @throws Exception 如果实例已经初始化了.
     */
    public static function setInstance(Registry $registry)
    {
        if (self::$_registry !== null) {
            throw new Exception('Registry is already initialized.');
        }

        self::setClassName(get_class($registry));
        self::$_registry = $registry;
    }

    /**
     * 设置实例名字.
     *
     * @param string $registryClassName.
     * @return void
     *
     * @throws Exception
     */
    public static function setClassName($registryClassName = 'Registry')
    {
        if (self::$_registry !== null) {
            throw new Exception('Registry is already initizlized.');
        }

        if (!is_string($registryClassName) || !$registryClassName) {
            throw new Exception('Argument is not a class name.');
        }

        self::$_registryClassName = $registryClassName;
    }

    /**
     * 清除当前的注册实例.
     *
     * @return void
     */
    public static function _unsetInstance()
    {
        self::$_registry = null;
    }

    /**
     * 将一个值保存到对应的键上.
     *
     * @param mixed $index 要保存值的键.
     * @param mixed $value 要保存的值.
     *
     * @throws Exception
     */
    public static function set($index, $value)
    {
        $instance = self::getInstance();
        $instance->offsetSet($index, $value);
    }

    /**
     * 获取指定键的值.
     *
     * @param mixed $index 存储的键的名字.
     * @return mixed|null 返回对应键的存储的值
     * @throws Exception
     */
    public static function get($index)
    {
        // 未注册返回空
        if (!self::isRegistered($index)) {
            return null;
        }

        $instance = self::getInstance();

        if (!$instance->offsetExists($index)) {
            throw new Exception("No entry is registered for key '$index'");
        }

        return $instance->offsetGet($index);
    }

    /**
     * 检查指定的键是否已经注册了.
     *
     * @param mixed $index 要检查的键的名字.
     * @return boolean
     */
    public static function isRegistered($index)
    {
        if (self::$_registry === null) {
            return false;
        }

        return self::$_registry->offsetExists($index);
    }
}