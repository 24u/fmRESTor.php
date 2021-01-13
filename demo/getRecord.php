<?php

use fmRESTor\fmRESTor;
session_start();
require_once dirname(__DIR__) . '/src/fmRESTor.php';

$fm = new fmRESTor("127.0.0.1", "fmRESTor", "php_user", "api", "api123456", array("allowInsecure" => true));

// Setting up parameters for get record
$GetRecord= array(
    "script" => "Log request",
    "script.param" => "Parameter from fmRESTor - get record",
    "layout.response"=> "php_user",
    //"_limit.USER_licence"=> ,
    //"_offset.USER_licence"=> 
);

// Get the record with ID 11
$id = 11;
$result = $fm->getRecord($id, $GetRecord);
if(!$fm->isError($result)){
    echo "Request succeeded";
} else {
    echo "Request Failed";
}

$response = $fm->getResponse($result);
var_dump($response);
exit();