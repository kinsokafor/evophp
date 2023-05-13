<?php 

namespace EvoPhp\Api\Requests;

use EvoPhp\Api\Operations;
use function getallheaders;
// use EvoPhp\Resources\Post;
// use EvoPhp\Resources\User;
// use EvoPhp\Resources\Options;
// use EvoPhp\Resources\Records;
// use EvoPhp\Api\Config;

class Requests
{
    use \Evophp\Api\Auth;

	public $response;

    public $requestHeaders;

    public string $tableName;

    public bool $isCount = false;

    public int | bool $limit = false;

    public int | bool $offset = false;

    public bool $cache = true;

    public int $cacheExpiry = 300;

    public array $data = [];

    public array $uniqueKeys = [];

    public $protocol;

    protected $verified = false;

    public array $reservedMethods = [
        "auth",
        "setData",
        "execute"
    ];

	public function __construct()
    {
        $this->setRequestHeaders();
        $this->setResponseHeaders();
    }

    public function __call($name, $args) {
        if(in_array($name, $this->reservedMethods)) :
            $this->$name(...$args);
            return $this;
        endif;
        $this->tableName = $name;
        if($this->requestHeaders->requestMethod == 'get' && Operations::count($_GET)) {
            $this->data = array_merge($this->data, $_GET);
        }
        else if($this->requestHeaders->requestMethod == 'delete' && Operations::count($_GET)) {
            $this->data = array_merge($this->data, $_GET);
        }
        else if($this->requestHeaders->requestMethod == 'post' && Operations::count($_POST)) {
            $this->data = array_merge($this->data, (array) json_decode(file_get_contents("php://input"), true));
        }
        if(isset($args[0])) {
        	$this->data = array_merge($this->data, $args[0]);
        }
        return $this;
    }

    protected function setData($data) {
        if(Operations::count($data)) {
            $this->data = array_merge($this->data, $data);
        }
    }

    protected function auth(...$accessLevel) {
        $this->accessLevel = $accessLevel;
        $this->accessType = Operations::count($accessLevel) ? "protected" : "public";
    }

    private function execute($callable) {
        if($this->verifyClient()) {
            $this->data['response'] = $callable();
        }
    }

    private function get() {                        
        if(isset($this->data['limit'])) {
            $this->limit = (int) $this->data['limit'];
            unset($this->data['limit']);
        }
        if(isset($this->data['offset'])) {
            $this->offset = (int) $this->data['offset'];
            unset($this->data['offset']);
        }
        if(isset($this->data['iscount'])) {
            $this->isCount = true;
            unset($this->data['iscount']);
        }
        switch ($this->tableName) {
            case 'post':
                GetRequest::postTable($this);
                break;

            case 'user':
                GetRequest::usersTable($this);
                break;

            case 'options':
                GetRequest::optionsTable($this);
                break;

            case 'records':
                GetRequest::recordsTable($this);
                break;
            
            default:
                GetRequest::dbTable($this);
                break;
        }
    }

    private function delete() {                        
        if(isset($this->data['limit'])) {
            $this->limit = (int) $this->data['limit'];
            unset($this->data['limit']);
        }
        if(isset($this->data['offset'])) {
            $this->offset = (int) $this->data['offset'];
            unset($this->data['offset']);
        }
        if(isset($this->data['iscount'])) {
            $this->isCount = true;
            unset($this->data['iscount']);
        }
        switch ($this->tableName) {
            case 'post':
                DeleteRequest::postTable($this);
                break;

            case 'user':
                DeleteRequest::usersTable($this);
                break;

            case 'options':
                DeleteRequest::optionsTable($this);
                break;

            case 'records':
                DeleteRequest::recordsTable($this);
                break;
            
            default:
                DeleteRequest::dbTable($this);
                break;
        }
    }

    private function post() {
        switch ($this->tableName) {
            case 'post':
                PostRequest::postTable($this);
                break;

            case 'user':
                PostRequest::usersTable($this);
                break;

            case 'options':
                PostRequest::optionsTable($this);
                break;

            case 'records':
                PostRequest::recordsTable($this);
                break;

            case 'evoAction':
                PostRequest::evoActions($this);
                break;
            
            default:
                PostRequest::dbTable($this);
                break;
        }
    }

    private function put() {
        switch ($this->tableName) {
            case 'post':
                PutRequest::postTable($this);
                break;

            case 'user':
                PutRequest::usersTable($this);
                break;

            case 'options':
                PutRequest::optionsTable($this);
                break;

            case 'records':
                PutRequest::recordsTable($this);
                break;
            
            default:
                PutRequest::dbTable($this);
                break;
        }
    }

    private function setUniqueKeys() {
        if(isset($this->data['uniqueKeys'])) {
            switch (gettype($this->data['uniqueKeys'])) {
                case 'string':
                    $this->uniqueKeys = explode(",", $this->data['uniqueKeys']);
                    break;
                
                case 'array':
                    $this->uniqueKeys = $this->data['uniqueKeys'];
                    break;

                case 'object':
                    $this->uniqueKeys = (array) $this->data['uniqueKeys'];
                    $this->uniqueKeys = array_values($this->uniqueKeys);
                    break;
                
                default:
                    # code...
                    break;
            }
            unset($this->data['uniqueKeys']);
        }
    }

    public function setRequestHeaders() {
        $this->requestHeaders = (object) getallheaders();
        $this->requestHeaders->requestMethod = strtolower($_SERVER['REQUEST_METHOD']);
        $this->protocol = $_SERVER['SERVER_PROTOCOL'];
    }

    private function setResponseHeaders() {
        \Delight\Http\ResponseHeader::set('Access-Control-Allow-Origin', '*');
        \Delight\Http\ResponseHeader::set('Content-Type', 'application/json; charset=UTF-8');
        \Delight\Http\ResponseHeader::set('Access-Control-Allow-Methods', 'OPTIONS,GET,POST,PUT,DELETE');
        \Delight\Http\ResponseHeader::set('Access-Control-Max-Age', '3600');
        \Delight\Http\ResponseHeader::set('Access-Control-Allow-Headers', 'Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    }

    protected function verifyClient() {
        if($this->verified) return true;
        if(isset($_SERVER[ 'HTTP_AUTHORIZATION' ])) {
            $nonce = str_replace('Bearer ', '', $_SERVER[ 'HTTP_AUTHORIZATION' ]);
            $res = $this->verifyNonce($nonce);
            if($res) {
                $this->verified = true;
            }
            return $res;
        }

        // $auth = (new config())->Auth;
        // $clients = $auth['clients'];
        // $clients = array_column($clients, NULL, 'client_id');
        // $client = $_SERVER[ 'PHP_AUTH_USER' ];
        // if(!isset($clients[$client])) {
        //     return false;
        // }
        // else if($clients[$client]['secret'] != $_SERVER[ 'PHP_AUTH_PW' ]) {
        //     return false;
        // }
        // else {
        //     $clientDomain = $clients[$client]['domain'];
        //     $protocol = $clients[$client]['protocol'];
        //     \Delight\Http\ResponseHeader::set('Content-Security-Policy', 
        //         "frame-ancestors 'self' $protocol://*.$clientDomain/ $protocol://$clientDomain/"
        //     );
        //     return true;
        // }
        return false;
    }

    protected function getResponse() {
        if($this->verifyClient()) {
            if($this->accessType == "public" || $this->getAuthorization()) {
                if(method_exists($this, $this->requestHeaders->requestMethod)) {
                    $method = $this->requestHeaders->requestMethod;
                    $this->$method();
                } else {
                    http_response_code(405);
                    $this->response = NULL;
                }
            } else {
                http_response_code(403);
                $this->response = NULL;
            }
        } else {
            http_response_code(401);
            $this->response = NULL;
        }
    	echo json_encode($this->response);
    }

    public function __destruct()
    {
        $this->getResponse();
    }
}