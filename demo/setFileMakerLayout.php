<?php

use fmRESTor\fmRESTor;
session_start();
require_once dirname(__DIR__) . '/src/fmRESTor.php';

$fm = new fmRESTor("127.0.0.1", "fmRESTor", "php_user", "api", "api123456", array("allowInsecure" => true));

// Setting up the mandatory parameters for set FileMaker layout
$result = $fm->setFilemakerLayout("php_licence");
if(!$fm->isError($result)){
    echo "Request succeeded";
} else {
    echo "Request Failed";
}

$response = $fm->getResponse($result);
var_dump($response);
exit();