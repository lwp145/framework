<?php
use \Lib\Util as LUtil;

class Controller_Test_A extends ViewController_Api
{
    public function action_A()
    {
        LUtil::response('1111');
    }
}