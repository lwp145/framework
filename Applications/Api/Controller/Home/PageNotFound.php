<?php
/**
 * 用户信息.
 *
 * @author dzy<dzy@jumei.com>
 */

use \Lib\Exception;

/**
 * 用户信息.
 */
class Controller_Home_PageNotFound extends ViewController_Api
{

    /**
     * 默认控制器，访问api.jumei.com要报错.
     *
     * @return void
     *
     * @throws Exception JMException.
     */
    public function action_PageNotFound()
    {
        // 无效的接口请求.
        throw new JMException(10000);
        die;
    }

}
