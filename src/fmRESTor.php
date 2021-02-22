<?php
/**
 * Author: 24U s.r.o.
 */

namespace fmRESTor;
/**
 * Class fmRESTor
 */
class fmRESTor
{
    /* --- Connection --- */
    private $host;
    private $db;
    private $layout;
    private $user;
    private $password;
    private $fmDataSource;

    /* --- Define another attributes --- */
    private $token;
    private $rowNumber;

    /* --- Default options --- */
    private $tokenExpireTime = 14;
    private $sessionName = "fm-api-token";
    private $logDir = __DIR__ . "/log/";
    private $logType = self::LOG_TYPE_DEBUG;
    private $allowInsecure = false;
    private $tokenStorage = self::TS_SESSION;
    private $autorelogin = true;
    private $tokenFilePath = "";
    private $curlOptions = [];

    /* --- Define log const --- */
    const LOG_TYPE_DEBUG = "debug";
    const LOG_TYPE_ERRORS = "errors";
    const LOG_TYPE_NONE = "none";

    const LS_ERROR = "error";
    const LS_SUCCESS = "success";
    const LS_INFO = "info";
    const LS_WARNING = "warning";

    const TS_FILE = "file";
    const TS_SESSION = "session";



    const ERROR_RESPONSE_CODE = [400, 401, 403, 404, 405, 415, 500];
    const ERROR_AUTH_RESPONSE_CODE = 401;

    /**
     * fmRESTor constructor.
     * @param string $host
     * @param string $db
     * @param string $layout
     * @param string $user
     * @param string $password
     * @option array $fmDataSource
     * @option array $options
     */
    public function __construct($host, $db, $layout, $user, $password, $options = null, $fmDataSource = null)
    {
        $this->host = $host;
        $this->db = $db;
        $this->layout = $layout;
        $this->user = $user;
        $this->password = $password;
        $this->fmDataSource = $fmDataSource;

        if ($options !== null) {
           $this->setOptions($options);
        }

        $this->setTimezone();
    }

    private function setLogRowNumber()
    {
        $this->rowNumber = rand(1000000, 9999999);
    }

    /**
     * @param array $options
     */
    private function setOptions($options)
    {
        /* --- Log type --- */
        if (isset($options["logType"])) {
            $logType = $options["logType"];

            if (in_array($logType, [self::LOG_TYPE_DEBUG, self::LOG_TYPE_ERRORS, self::LOG_TYPE_NONE]) && !empty($logType)) {
                $this->logType = $logType;
            } else {
                $this->response(-101);
            }
        }

        /* --- Log dir --- */
        if (isset($options["logDir"])) {
            $logDir = $options["logDir"];

            if (is_string($logDir) && !empty($logDir)) {
                $this->logDir = $logDir;
            } else {
                $this->response(-102);
            }
        }

        /* --- Session name --- */
        if (isset($options["sessionName"])) {
            $sessionName = $options["sessionName"];

            if (is_string($sessionName) && !empty($sessionName)) {
                $this->sessionName = $sessionName;
            } else {
                $this->response(-103);
            }
        }

        /* --- Token Expire Time ( In minutes ) --- */
        if (isset($options["tokenExpireTime"])) {
            $tokenExpireTime = $options["tokenExpireTime"];

            if (is_numeric($tokenExpireTime)) {
                $this->tokenExpireTime = $tokenExpireTime;
            } else {
                $this->response(-104);
            }
        }

        /* --- Allow Insecure --- */
        if (isset($options["allowInsecure"])) {
            $allowInsecure = $options["allowInsecure"];

            if (is_bool($allowInsecure)) {
                $this->allowInsecure = $allowInsecure;
            } else {
                $this->response(-105);
            }
        }

        /* --- Relogin --- */
        if (isset($options["autorelogin"])) {
            $autorelogin = $options["autorelogin"];

            if (is_bool($autorelogin)) {
                $this->autorelogin = $autorelogin;
            } else {
                $this->response(-112);
            }
        }

        /* --- Relogin --- */
        if (isset($options["curlOptions"])) {
            $curlOptions = $options["curlOptions"];

            if (is_array($curlOptions)) {
                $this->curlOptions = $curlOptions;
            } else {
                $this->response(-113);
            }
        }


        /* --- Save FileMaker Token to --- */
        if (isset($options["tokenStorage"])) {
            $tokenStorage = $options["tokenStorage"];

            if (in_array($tokenStorage, [self::TS_FILE, self::TS_SESSION])) {
                if($tokenStorage === self::TS_FILE){
                    if (isset($options["tokenFilePath"]) && !empty($options["tokenFilePath"]) && is_string($options["tokenFilePath"])) {
                        $this->tokenFilePath = $options["tokenFilePath"];
                    } else {
                        $this->response(-111);
                    }
                }
                $this->tokenStorage = $tokenStorage;
            } else {
                $this->response(-110);
            }
        }
    }

    /**
     * Check if is set default timezone in PHP.ini
     */
    private function setTimezone()
    {
        if (ini_get('date.timezone') == "") {
            ini_set('date.timezone', 'Europe/London');
        }
    }

    /**
     * @return mixed
     */
    public function logout()
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to logout from database"
        ));

        if ($this->isLogged() === false) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "User is logged out - invalid token"
            ));

            $this->destroySessionToken();
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/sessions/" . rawurlencode($this->token),
            "method" => "DELETE",
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Logout was successfull",
                "data" => $result
            ));

            $this->destroySessionToken();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Logout was not successfull",
                "data" => $result
            ));

            $this->destroySessionToken();
        }

        return $result;

    }

    /**
     * @param string $scriptName
     * @param array $scriptPrameters
     * @return bool|mixed
     */
    public function runScript($scriptName, $scriptPrameters = null, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to run a script",
            "data" => $scriptPrameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $param = "";
        if ($scriptPrameters !== null) {
            $param = $this->convertParametersToString($scriptPrameters);
        }
        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/script/" . rawurlencode($scriptName) . "?" . $param,
            "method" => "GET",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )
        );

        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Script was successfully called",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Script was not successfully called",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->runScript($scriptName, $scriptPrameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @return bool|mixed
     */
    public function getDatabaseNames($relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get metadata - database names"
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/vLatest/databases",
            "method" => "GET",
            "headers" => array(
                "Authorization: Basic " . base64_encode($this->user . ":" . $this->password)
            )
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Information about database names was successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Information about database names was not successfully loaded",
                "data" => $result
            ));


            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getDatabaseNames($relogin = true);
            }
        }

        return $result;
    }

    /**
     * @return bool|mixed
     */
    public function getProductInformation($relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get metadata - product information"
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/vLatest/productInfo",
            "method" => "GET"
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Product Information was successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Product Information was not successfully loaded",
                "data" => $result
            ));


            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getProductInformation($relogin = true);
            }
        }

        return $result;
    }

    /**
     * @return bool|mixed
     */
    public function getScriptNames($relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get metadata - script names"
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/scripts",
            "method" => "GET",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )

        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Information about script names was successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Information about script names was not successfully loaded",
                "data" => $result
            ));


            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getScriptNames($relogin = true);
            }
        }

        return $result;
    }

    /**
     * @return bool|mixed
     */
    public function getLayoutNames($relogin = true)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get metadata - layout names"
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts",
            "method" => "GET",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )

        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Information about layout names was successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Information about layout names was not successfully loaded",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getLayoutNames($relogin = true);
            }
        }

        return $result;
    }

    /**
     * @return bool|mixed
     */
    public function getLayoutMetadata($relogin = true)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get metadata - layout information"
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout),
            "method" => "GET",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )

        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Information about layout was successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Information about layout was not successfully loaded",
                "data" => $result
            ));


            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getLayoutMetadata($relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param array $parameters
     * @return bool|mixed
     */
    public function createRecord($parameters, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to create a record",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records",
            "method" => "POST",
            "headers" => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->token
            )
        );

        $param = $this->convertParametersToJson($parameters);
        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Record was successfully created",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Record was not successfully created",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->createRecord($parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param integer $id
     * @param array $parameters
     * @return bool|mixed
     */
    public function deleteRecord($id, $parameters = null, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to delete a record",
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $param = "";
        if ($parameters !== null) {
            $param = $this->convertParametersToString($parameters);
        }
        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "?" . $param,
            "method" => "DELETE",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Record was successfully deleted",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Record was not successfully deleted",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->deleteRecord($id, $parameters,  $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param integer $id
     * @param array $parameters
     * @return bool|mixed
     */
    public function duplicateRecord($id, $parameters = null, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to edit a record",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id,
            "method" => "POST",
            "headers" => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->token
            )
        );

        $param = null;
        if ($parameters !== null) {
            $param = $this->convertParametersToJson($parameters);
        }

        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Record was successfully edited",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Record was not successfully edited",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->deleteRecord($id, $parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param integer $id
     * @param array $parameters
     * @return bool|mixed
     */
    public function editRecord($id, $parameters, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to edit a record",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id,
            "method" => "PATCH",
            "headers" => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->token
            )
        );

        $param = $this->convertParametersToJson($parameters);
        $result = $this->callURL($request, $param);
        $response = $result["result"];

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Record was successfully edited",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Record was not successfully edited",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->editRecord($id, $parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param integer $id
     * @option array $parameters
     * @return bool|mixed
     */
    public function getRecord($id, $parameters = null, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get a record from database",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $param = "";
        if ($parameters !== null) {
            $param = $this->convertParametersToString($parameters);
        }
        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "?" . $param,
            "method" => "GET",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Record was successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Record was not successfully loaded",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getRecord($id, $parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @option array $parameters
     * @return bool|mixed
     */
    public function getRecords($parameters = null, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to get records",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();

            if ($login !== true) {
                return $login;
            }
        }

        $param = "";
        if ($parameters !== null) {
            $param = $this->convertParametersToString($parameters);
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . "?" . $param,
            "method" => "GET",
            "headers" => array(
                "Authorization: Bearer " . $this->token
            )
        );

        $result = $this->callURL($request);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Records were successfully loaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Records were not successfully loaded",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->getRecords($parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param integer $id
     * @param string $containerFieldName
     * @param string containerFieldRepetition
     * @param array $file
     * @return bool|mixed
     */
    public function uploadFormDataToContainter($id, $containerFieldName, $containerFieldRepetition, $file, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to upload a file to a container",
            "data" => $file
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $param = array(
            "upload" => new \CURLFile($file["tmp_name"], $file["type"], $file["name"])
        );

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "/containers/" . $containerFieldName . "/" . $containerFieldRepetition,
            "method" => "POST",
            "headers" => array(
                'Content-Type: multipart/form-data',
                "Authorization: Bearer " . $this->token
            )
        );

        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "File was successfully uploaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "File was not successfully uploaded",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {

                return $this->uploadFormDataToContainter($id, $containerFieldName, $containerFieldRepetition, $file,  $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param integer $id
     * @param string $containerFieldName
     * @param integer $containerFieldRepetition
     * @param string $path
     * @return bool|mixed
     */
    public function uploadFileToContainter($id, $containerFieldName, $containerFieldRepetition, $path, $relogin = false)
    {
        $this->setLogRowNumber();

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        $filename = basename($path);

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to upload a file to a container",
            "data" => $path
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $param = array(
            "upload" => new \CURLFile($path, $mime, $filename)
        );

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/records/" . $id . "/containers/" . $containerFieldName . "/" . $containerFieldRepetition,
            "method" => "POST",
            "headers" => array(
                'Content-Type: multipart/form-data',
                "Authorization: Bearer " . $this->token
            )
        );

        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "File was successfully uploaded",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "File was not successfully uploaded",

                "data" => $result
            ));


            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->uploadFileToContainter($id, $containerFieldName, $containerFieldRepetition, $path, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param array $parameters
     * @return bool|mixed
     */
    public function findRecords($parameters, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to find records",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/layouts/" . rawurlencode($this->layout) . "/_find",
            "method" => "POST",
            "headers" => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->token
            )
        );

        $param = $this->convertParametersToJson($parameters);
        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Records was successfully found",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Records was not found",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {

                return $this->findRecords($parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param array $parameters
     * @return bool|mixed
     */
    public function setGlobalField($parameters, $relogin = false)
    {
        $this->setLogRowNumber();

        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to set global fields",
            "data" => $parameters
        ));

        if ($this->isLogged() === false || $relogin === true) {
            $login = $this->login();
            if ($login !== true) {
                return $login;
            }
        }

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/globals/",
            "method" => "PATCH",
            "headers" => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->token
            )
        );

        $param = $this->convertParametersToJson($parameters);
        $result = $this->callURL($request, $param);

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Global fields was successfully set",
                "data" => $result
            ));

            $this->extendTokenExpiration();
        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Global fields was not successfully set",
                "data" => $result
            ));

            if ($this->autorelogin === true && $relogin === false && isset($result["status"]["http_code"]) && $result["status"]["http_code"] === self::ERROR_AUTH_RESPONSE_CODE) {
                return $this->setGlobalField($parameters, $relogin = true);
            }
        }

        return $result;
    }

    /**
     * @param string $layout
     * @return bool
     */
    public function setFilemakerLayout($layout)
    {
        if (is_string($layout)) {
            $this->layout = $layout;
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param array $parameters
     * @return null|string
     */
    private function convertParametersToJson($parameters)
    {
        if (is_array($parameters)) {
            if (!empty($parameters)) {
                return json_encode($parameters);
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * @param array $parameters
     * @return string
     */
    private function convertParametersToString($parameters)
    {
        if (is_array($parameters)) {
            if (!empty($parameters)) {
                return http_build_query($parameters);
            } else {
                return "";
            }
        }
        return "";
    }

    /**
     * @param array $requestSettings
     * @option array $data
     * @return mixed
     */
    private function callURL($requestSettings, $data = null)
    {
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "cURL request sending"
        ));

        $headers = (isset($requestSettings["headers"]) ? $requestSettings["headers"] : null);
        $method = $requestSettings["method"];
        $url = $requestSettings["url"];

        /* --- Init CURL --- */
        $ch = curl_init();

        /* --- Allow redirects --- */
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        /* --- Return response --- */
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        /* --- Settings control CURL is strict about identify verification --- */
        if ($this->allowInsecure == true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        /* --- Return the transfer as a string --- */
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);

        /* --- Set headers --- */
        if ($headers !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        /* --- Set post data --- */
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($data === null ? "" : $data));

        /* --- Set request method --- */
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        /* --- Set request URL --- */
        curl_setopt($ch, CURLOPT_URL, "https://" . $this->host . $url);

        /* --- Additional custom options --- */
        foreach($this->curlOptions as $keyOption => $optionValue){
            curl_setopt($ch, $keyOption, $optionValue);
        }

        /* --- Output--- */

        $result = curl_exec($ch);
        $errors = curl_error($ch);

        if (!empty($errors)) {
            return [
                "status" => curl_getinfo($ch),
                "result" => $errors
            ];
        } else {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_INFO,
                "message" => "cURL request sent"
            ));

            $response = json_decode($result, true);

            return [
                "status" => curl_getinfo($ch),
                "result" => ($response !== null ?  $response : $result)
            ];
        }
    }

    /**
     * @return bool|mixed
     */
    private function login()
    {
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Attempt to create new token"
        ));

        $request = array(
            "url" => "/fmi/data/v1/databases/" . rawurlencode($this->db) . "/sessions",
            "method" => "POST",
            "headers" => array(
                "Content-Type: application/json",
                "Authorization: Basic " . base64_encode($this->user . ":" . $this->password)
            )
        );

        $param = "";
        if ($this->fmDataSource !== null) {
            $prepareParam = array(
                "fmDataSource" => $this->fmDataSource
            );

            $param = $this->convertParametersToJson($prepareParam);
        }

        $result = $this->callURL($request, $param);
        $response = $result["result"];

        try {
            $this->isError($result, true);

            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_SUCCESS,
                "message" => "Token was sucessfully created",
                "data" => $response
            ));

            $this->setFileMakerTokenProps(["token" => $response["response"]["token"]]);
            $this->extendTokenExpiration();

            return true;

        } catch (\Exception $e) {
            $this->log(array(
                "line" => __LINE__,
                "method" => __METHOD__,
                "type" => self::LS_ERROR,
                "message" => "Token was not sucessfully created",
                "data" => $response
            ));

            return $result;
        }
    }

    /**
     * @return bool
     */
    private function isLogged()
    {
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Checking if user is logged into the database"
        ));
        $tokenProps = $this->getCurrentFileMakerTokenProps();
        if (!empty($tokenProps)) {

            $currentTime = new \DateTime();
            $tokenExpire = \DateTime::createFromFormat("Y-m-d H:i:s", $tokenProps["expire"]);
            if ($tokenExpire === false) {
                return false;
            }

            if ($currentTime >= $tokenExpire) {
                $this->log(array(
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_WARNING,
                    "message" => "User is not logged into the database"
                ));
                return false;
            } else {
                $this->log(array(
                    "line" => __LINE__,
                    "method" => __METHOD__,
                    "type" => self::LS_SUCCESS,
                    "message" => "User is logged into the database"
                ));
                $this->setToken($tokenProps["token"]);
                return true;
            }
        } else {
            return false;
        }
    }

    private function extendTokenExpiration()
    {
        $this->log(array(
            "line" => __LINE__,
            "method" => __METHOD__,
            "type" => self::LS_INFO,
            "message" => "Token expiration time extending"
        ));

        $currentTime = new \DateTime();
        $tokenExpire = $currentTime->modify("+" . $this->tokenExpireTime . "minutes");
        $this->setFileMakerTokenProps(["expire" => $tokenExpire->format("Y-m-d H:i:s")]);
    }

    private function getCurrentFileMakerTokenProps(){
        $data = null;

        if($this->tokenStorage === self::TS_FILE){
            if(file_exists($this->tokenFilePath)){
               $dataRawArray = json_decode(file_get_contents($this->tokenFilePath), true);
               if(!empty($dataRawArray)){
                   $data = $dataRawArray;
               }
            }
        } elseif ($this->tokenStorage === self::TS_SESSION){
            if(isset($_SESSION[$this->sessionName])){
                $dataRawArray = json_decode($_SESSION[$this->sessionName], true);
                if(!empty($dataRawArray)){
                    $data = $dataRawArray;
                }
            }
        }

        return $data;
    }

    private function setFileMakerTokenProps($data)
    {
        $currentData = $this->getCurrentFileMakerTokenProps();

        if(empty($currentData)){
            $currentData = [
                "token" => "",
                "expire" => ""
            ];
        }

        // UPDATE DATA
        if (isset($data["token"])) {
            $currentData["token"] = $data["token"];
            $this->setToken($data["token"]);
        }
        if (isset($data["expire"])) {
            $currentData["expire"] = $data["expire"];
        }

        $dataJson = json_encode($currentData);

        if($this->tokenStorage === self::TS_FILE) {
            file_put_contents($this->tokenFilePath, $dataJson);
        } elseif($this->tokenStorage === self::TS_SESSION){
            $_SESSION[$this->sessionName] = $dataJson;
        }
    }

    private function setToken($token)
    {
        $this->token = $token;
    }

    private function destroySessionToken()
    {
        if (isset($_SESSION[$this->sessionName])) {
            unset($_SESSION[$this->sessionName]);
        }
        if (is_writable($this->tokenFilePath)) {
            file_put_contents($this->tokenFilePath, "");
        }
    }

    /**
     * @param array $log
     */
    private function log($log)
    {
        $type = null;
        if (isset($log["type"])) {
            $type = $log["type"];
        }

        $message = null;
        if (isset($log["message"])) {
            $message = $log["message"];
        }

        $section = null;
        if (isset($log["method"])) {
            $section = $log["method"];
        }

        $data = null;
        if (isset($log["data"])) {
            $data = $log["data"];
        }

        if ($this->logType !== self::LOG_TYPE_NONE) {
            if ($this->logType == self::LOG_TYPE_ERRORS && $type === self::LS_ERROR || $this->logType == self::LOG_TYPE_DEBUG) {

                /* --- Define basic variable needed for log function --- */
                $log_message = "";
                $split_string = "\t";

                /* --- Row number --- */
                $log_message .= $this->rowNumber . $split_string;

                /* --- Date & Time --- */
                $log_message .= date("Y-m-d H:i:s") . $split_string;

                /* --- Section name --- */
                if (!empty($section)) {
                    $log_message .= $section . $split_string;
                } else {
                    $log_message .= "" . $split_string;
                }

                /* --- Type--- */
                $log_message .= strtoupper($type) . $split_string;

                /* --- Data --- */
                if (!empty($data)) {
                    if (is_array($data)) {
                        $log_message .= json_encode($data) . $split_string;
                    } else {
                        $log_message .= $data . $split_string;
                    }
                } else {
                    $log_message .= "";
                }

                /* --- Message --- */
                if (!empty($message)) {
                    $log_message .= $message;
                } else {
                    $log_message .= "";
                }

                $log_message .= "\n";

                /* --- Save log to file --- */
                $pathDir = $this->logDir;
                $file = "fm-api-log_" . date("d.m.Y") . ".txt";
                if (is_dir($pathDir) or is_writable($pathDir)) {
                    file_put_contents($pathDir . $file, $log_message, FILE_APPEND);
                }
            }
        }
    }

    /**
     * @param integer $code
     */
    private function response($code)
    {
        echo $code;
        exit();
    }

    public function isError($result, $throwException = false)
    {
        if (isset($result["status"]["http_code"]) && in_array($result["status"]["http_code"], self::ERROR_RESPONSE_CODE) || !is_array($result["result"])) {
            if($throwException === true){
                throw new \Exception(json_encode($result["result"]), $result["status"]["http_code"]);
            }
            return true;
        }
        return false;
    }

    public function isRecordExist($result){
        $recordMissingErrorCode = 401;

        if(isset($result["result"]["messages"][0]["code"]) && intval($result["result"]["messages"][0]["code"]) === $recordMissingErrorCode){
            return false;
        }
        return true;
    }

    public function getResponse($result){
        return $result["result"];
    }
}
