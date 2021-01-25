<?php

use fmRESTor\fmRESTor;
session_start();
require_once dirname(__DIR__) . '/src/fmRESTor.php';

$fm = new fmRESTor("127.0.0.1", "fmRESTor", "php_user", "api", "api123456", array("allowInsecure" => true));

// Setting up parameters for get records
$GetRecords= array(
    //"_offset.USER_licence"=> ,
    //"limit.USER_licence"=> ,
    "_limit"=>10,
    //"_sort" =>"",
    "script"=>"Log request",
    "script.param"=>"Parameter from fmRESTor - get records"
);

// Gets records with maximum display of 10 records 
$result = $fm->getRecords($GetRecords);
if(!$fm->isError($result)){
    echo "Request succeeded: ";
} else {
    echo "Request Failed: ";
}

$response = $fm->getResponse($result);
var_dump($response);
exit();