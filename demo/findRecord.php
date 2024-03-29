<?php

use fmRESTor\fmRESTor;

session_start();
require_once dirname(__DIR__) . '/src/fmRESTor.php';

$fm = new fmRESTor("127.0.0.1", "fmRESTor", "php_user", "api", "api123456", array("allowInsecure" => true));

// Setting up parameters for find record
$findRecords = array(
    "query" => array(
        array(
            "email" => "==lawrence@lectus.ca"
            //"omit" => "false"
        )
    ),
    /*"sort" => array(
        array(
            "fieldName" => "Neal", "sortOrder" => "ascend"
        )
    )
    "_limit.USER_licence"=> 5,
    "_offset.USER_licence"=> 10*/
);

// Find the record with mail lawrence@lectus.ca
$result = $fm->findRecords($findRecords);
if(!$fm->isError($result)){
    echo "Request succeeded: ";
} else {
    echo "Request Failed: ";
}

$response = $fm->getResponse($result);
var_dump($response);
exit();