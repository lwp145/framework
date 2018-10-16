<?php
/**
 * Response class file.
 */

/**
 * This class provides a series of methods to handle responses to clients.
 */
class Response
{

    public static function json($data,$callback = null)
    {
        $data = json_encode($data);
        if ($callback) {
            $data = $callback . '(' . $data . ');';
            $header = 'Content-type: text/javascript; utf-8;';
        } else {
            $header = 'Content-type: application/json; utf-8;';
        }

        header($header);
        echo $data;
        // 全链路监控 http://xwiki.int.jumei.com/bin/view/monitor/PHP全链路监控
        /*
        if (is_callable(array('MNLogger\TraceLogger', 'flush'))) {
            MNLogger\TraceLogger::instance('trace')->HTTP_SS('SUCCESS', 0);
            MNLogger\TraceLogger::flush();
        }
        */
        exit();
    }
}