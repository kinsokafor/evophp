<?php

namespace EvoPhp\Api;

use EvoPhp\Api\Config;
use Evophp\Database\Query;
use EvoPhp\Resources\User;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use Delight\Cookie\Cookie;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

Trait Auth {

	private $_firstInstall = false;

    public $accessToken;

    public $resourceOwner;

    public $authorizationState = false;

    protected string $accessType = "protected";

    protected array $accessLevel;

    private function _firstInstall() {
        if($this->_firstInstall) return;
        Operations::replaceLine(
            realpath(dirname(__FILE__))."/Auth.php", 
            "private \$firstInstall = false;", 
            "\tprivate \$firstInstall = true;\n"
        );
        $this->createTable();
    }

    private function createTable() {
        $query = new Query;
        $statement = "CREATE TABLE IF NOT EXISTS token (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT(20) NOT NULL,
                name TEXT NOT NULL,
                token TEXT NOT NULL,
                expiry BIGINT(20) NOT NULL,
                scope TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'alive'
                )";
        $query->query($statement)->execute();
    }

    static public function encrypt($verb) {
        $config = new Config;
        return SHA1(md5($verb.$config->salt.$verb));
    }

    public static function isSignedIn() {
        $obj = new self;
        $session = Session::getInstance();
        if(!isset($session->accesstoken)) return ["loginStatus" => false];
        if($tokenMeta = $obj->getTokenObject($session->accesstoken)) {
            $user = new User;
            return [
                "loginStatus" => true, 
                "currentUser" => $user->get($tokenMeta->user_id),
                "userScope" => Operations::unserialize($tokenMeta->scope),
                "token" => $session->accesstoken,
                "expiry" => $tokenMeta->expiry
            ];
        }
        return ["loginStatus" => false];
    }

    public static function signIn($selector, $password) {
        $user = new User;
        $meta = $user->get($selector);
        $session = Session::getInstance();
        if(!$meta) :
            $session->increment("failedSignInAttempts");
            return ['loginStatus' => false, 'msg' => 'Incorrect Username'];
        endif;
        $password = self::encrypt($password);
        if($meta->password !== $password) :
            $session->increment("failedSignInAttempts");
            return ['loginStatus' => false, 'msg' => 'Incorrect Password'];
        endif;
        if($meta->status !== "active") :
            return ['loginStatus' => false, 'msg' => 'Sign in disallowed for '.$meta->status.' account'];
        endif;
        $session->failedSignInAttempts = 0;
        $instance = new self;
        $config = new Config;
        $instance->resourceOwner = $meta;
        $instance->authorizationState = true;
        $roles = $config->Auth["roles"];
        $scope = $roles[$meta->role] ?? $roles[$config->Auth["defaultRole"]];
        return $instance->createToken(Operations::getFullname($meta).": SignIn", $scope['capacity']);
    }

    public static function signOut() {
        $query = new Query;
        $session = Session::getInstance();
        $existing = $query->select("token")
            ->where("token", $session->accesstoken, "s")
            ->execute()
            ->rows();
        $query->delete("token")
            ->where("token", $session->accesstoken, "s")
            ->execute();
        $cookie = new Cookie("nonce");
        $cookie->delete();
        return $existing;
    }

    private function createToken($token_name, $scope = []) {
        $this->_firstInstall();
        $query = new Query;
        $session = Session::getInstance();
        $token = Operations::randomString();
        $config = new Config;
        $expiry = \time() + $config->Auth["tokenLifetime"];
        $scope = Operations::serialize($scope);
        $query->delete("token")
        ->where("user_id", $this->resourceOwner->id, "i")
        ->where("expiry", time(), "i", "<")
        ->execute();
        $query->insert("token", "ississ", [
            "user_id" => $this->resourceOwner->id,
            "name" => $token_name,
            "token" => $token,
            "expiry" => $expiry,
            "scope" => $scope,
            "status" => "active"
        ])->execute();
        $nonce = $this->createNonce($token);
        $cookie = new Cookie("nonce");
        $cookie->setValue($nonce)->setMaxAge($config->Auth["tokenLifetime"])->save();
        $session->accesstoken = $token;
        return ['loginStatus' => true, 'token' => $token, 'msg' => 'Login Successful'];
    }

    protected function getTokenObject($token) {
        $this->_firstInstall();
        $query = new Query;
        $rows = $query->select("token")
                    ->where("token", $token)
                    ->where("status", "active")
                    ->where("expiry", time(), "i", ">")
                    ->execute()->rows();
        if(Operations::count($rows)) {
            return $rows[0];
        } else return false;
    }

    protected function getAuthorization() {
        if(!\Delight\Cookie\Cookie::exists('nonce')) :
            return false;
        endif;

        if(!$this->verifyNonce(\Delight\Cookie\Cookie::get('nonce'))) :
            return false;
        endif;
        
        $session = Session::getInstance();
        
        $tokenObj = $this->getTokenObject($session->accesstoken);

        $scope = Operations::unserialize($tokenObj->scope);

        if(count($this->accessLevel) < 1) return true;

        if(!Operations::count(array_intersect($scope, $this->accessLevel))) :
            return false;
        endif;

        return true;
    }

    protected function getKey() {
        $session = Session::getInstance();
        if(isset($session->accesstoken)) {
            if($tokenMeta = $this->getTokenObject($session->accesstoken)) {
                return $tokenMeta->token;
            }
        }
        return 'public_key';
    }

    protected function getNonce() {
        if(\Delight\Cookie\Cookie::exists('nonce')) {
            return \Delight\Cookie\Cookie::get('nonce');
        }
        return $this->createNonce();
    }

    protected function createNonce($key = false) {
        if(!$key)
            $key = $this->getKey();
        $config = new Config;
        $payload = [
            'iss' => $config->root,
            'aud' => $config->root,
            'iat' => time(),
            'nbf' => 1357000000
        ];
        return JWT::encode($payload, $key, 'HS256');
    }

    protected function verifyNonce($jwt) {
        $key = $this->getKey();
        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
            return true;
        }
        catch(\Firebase\JWT\ExpiredException $e) {
            return false;
        }
        catch (\Firebase\JWT\SignatureInvalidException $e) {
            return false;
        }
    }

}

?>