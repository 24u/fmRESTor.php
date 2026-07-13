<?php

use fmRESTor\fmRESTor;
session_start();
require_once dirname(__DIR__) . '/src/fmRESTor.php';

// Extend fmRESTor and override fmRESTor_log to send log data to your own
// logging system, instead of or in addition to the default file logging.
class MyFmRESTor extends fmRESTor
{
    protected function fmRESTor_log($log)
    {
        // send $log to your own logger here
        if ($this->fmRESTor_logType !== self::LOG_TYPE_NONE) {
            echo ($log["message"] ?? "") . "\n";
        }

        parent::fmRESTor_log($log); // optional: keep the default file logging too
    }
}

// Connect to the server
$fm = new MyFmRESTor("127.0.0.1", "fmRESTor", "php_user", "api", "api123456", array("allowInsecure" => true));

// Any regular call now also runs through the overridden fmRESTor_log
$result = $fm->logout();
if(!$fm->isError($result)){
    echo "Request succeeded: ";
} else {
    echo "Request Failed: ";
}

$response = $fm->getResponse($result);
var_dump($response);
exit();
