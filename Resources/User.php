<?php 

namespace EvoPhp\Resources;

use EvoPhp\Database\Query;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Database\DataType;
use EvoPhp\Api\Auth;
use EvoPhp\Api\FileHandling\Files;

/**
 * summary
 */
class User
{
    use Auth;

    use JoinRequest;

    use DataType;
    /**
     * summary
     */
	private $firstInstall = true;

    private $args = [];

    private $resourceType = NULL;

    public $error = "";

    public $errorCode = 200;

    public $query;

    public $session;

    public $num_args;

    public $resultIds;

    public $result;

    private $init;

    public function __construct()
    {
        $this->query = new Query;
        $this->session = Session::getInstance();
        $this->firstInstall();
    }

    public function __destruct() {
        // $this->execute();
    }

    private function firstInstall() {
        if($this->firstInstall) return;
        Operations::replaceLine(
            realpath(dirname(__FILE__))."/User.php", 
            "private \$firstInstall = false;", 
            "\tprivate \$firstInstall = true;\n"
        );
        $this->createTable();
    }

    private function createTable() {
        if($this->query->checkTableExist("users")) {
            $this->maintainTable();
            return;
        }

        $statement = "CREATE TABLE IF NOT EXISTS users (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(30) NOT NULL,
                email VARCHAR(50) NOT NULL,
                password TEXT NOT NULL,
                date_created TEXT NOT NULL
            )";
        $this->query->query($statement)->execute();

        $statement = "CREATE TABLE IF NOT EXISTS user_meta (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT(20) NOT NULL,
                meta_name TEXT NOT NULL,
                meta_value LONGTEXT NOT NULL,
                data_type VARCHAR(6) DEFAULT 'string',
                meta_int BIGINT(20) NOT NULL,
                meta_double DOUBLE(20,4) NOT NULL,
                meta_blob BLOB NOT NULL
                )";
        $this->query->query($statement)->execute();
    }

    private function maintainTable() {
        $statement = "ALTER TABLE user_meta ADD 
                        (
                            data_type VARCHAR(6) DEFAULT 'string',
                            meta_int BIGINT(20) NOT NULL,
                            meta_double DOUBLE(20,4) NOT NULL,
                            meta_blob BLOB NOT NULL
                        )";
        $this->query->query($statement)->execute();
    }

    public function execute() {
        if($this->num_args == 0) {
            $this->where("status", ["active", "inactive"]);
        }
        if ($this->resourceType === NULL) return;

        $callback = $this->resourceType;
        if(method_exists($this, $callback)) {
            return $this->$callback();
        }
    }

    private function addArgument($meta_name, $meta_value) {
        $this->args[$meta_name] = $meta_value;
        $this->num_args = Operations::count($this->args);
    }

    private function resetQuery() {
        $this->args = [];
        $this->num_args = 0;
    }

    public function new($meta = array(), ...$unique_keys) {
        $this->resetQuery();

        if(is_object($meta)) {
            $meta = (array) $meta;
        }

        // preactivate account
        if(isset($meta['preactivate_account'])) {
			$preactivate = (bool) $meta['preactivate_account'];
			unset($meta['preactivate_account']);
		} else {
			$preactivate = Options::get('preactivate_all_registration');
		}

        // use unverified role
		if(isset($meta['unverified_role'])) {
			$unverified = (bool) $meta['unverified_role'];
			unset($meta['unverified_role']);
		} else {
			$unverified = Options::get('use_unverified_role');
		}

        $generatedUserName = $this->generateUsername($meta);
        $default = [
            "role" => ($unverified) ? "unverified" : Options::get("default_user_role"),
            "temp_role" => $meta['role'] ?? Options::get("default_user_role"),
            "status" => ($preactivate || $unverified) ? 'active' : 'inactive',
            "username" => $generatedUserName,
            "password" => $generatedUserName,
            "surname" => "",
            "other_names" => "",
            "email" => ""
        ];

        $meta = array_merge($default, $meta);
        $meta['phone'] = Operations::internationalizePhoneNumber($meta['phone'] ?? '', $meta['country_code'] ?? '');
        $meta['password'] = self::encrypt($meta['password']);

        if(Operations::count($unique_keys)) {
            $userObj = $this->getCount();
            foreach ($unique_keys as $meta_name) {
                if(!isset($meta[$meta_name])) continue;
                $userObj->where($meta_name, $meta[$meta_name]);
            }
            $res = $userObj->execute();
            if($res > 0) {
                $this->error = "User instance already exists";
                $this->errorCode = 101;
                return false;
            }
        }

        if ($meta['email'] !== "") {
            if($this->get($meta['email'])) {
                $this->error = "Sorry, email \"".$meta['email']."\" is already in use by another user.";
                return false;
            }
		}
		if ($this->get($meta['username'])) {
			$this->error = 'Sorry, username "'.$meta['username'].'" is already existing or created in error.';
			return false;
		}

        $user_id = $this->query->insert('users', 'sssi', 
            [
                'username' => (string) $meta['username'], 
                'email' => $meta['email'], 
                'password' => $meta['password'], 
                'date_created' => time()
            ]
        )->execute();

        unset($meta['username']);
        unset($meta['password']);

        if($user_id) {
            $meta = $this->processFiles($user_id, $meta);
            $this->addMeta($user_id, $meta);
            return $user_id;
        }
        $this->error = ""; //set error message
        return false;

    }

    public function addMeta($user_id, $meta) {
        if(!Operations::count($meta)) {
            return false;
        }
        foreach ($meta as $key => $value) {
            switch (gettype($value)) {
                case "boolean":
                        $value = $value ? 1 : 0;
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "meta_int" => $value, "data_type" => "boolean"];
                        $types = "issis";
                    break;

                case "integer":
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "meta_int" => $value, "data_type" => "int"];
                        $types = "issis";
                    break;

                case "double":
                case "float":
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "meta_double" => $value, "data_type" => "double"];
                        $types = "issds";
                    break;

                case "array":
                        $value = Operations::serialize($value);
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "data_type" => "array"];
                        $types = "isss";
                    break;

                case "object":
                        $value = (array) $value;
                        $value = Operations::serialize($value);
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "data_type" => "object"];
                        $types = "isss";
                    break;

                case "blob":
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "meta_blob" => $value, "data_type" => "blob"];
                        $types = "issbs";
                    break;

                default:
                        $value = ["user_id" => $user_id, "meta_name" => $key, "meta_value" => (string) $value, "data_type" => "string"];
                        $types = "isss";
                    break;
            }
            $this->query->insert('user_meta', $types, $value)->execute();
        }
    }

    public function update($selector, $meta, ...$unique_keys) {
        $this->resetQuery();
        
        if(is_object($meta)) {
            $meta = (array) $meta;
        }
        if(!$existing = $this->get($selector)) {
            return false;
        }
        if(Operations::count($unique_keys)) {
            $instance = $this->getUser();
            foreach($unique_keys as $unique_key) {
                if(!isset($meta[$unique_key])) continue;
                $instance->where($unique_key, $meta[$unique_key]);
            }
            $rows = $instance->execute();
            if(Operations::count($rows)) {
                foreach($rows as $row) {
                    if($row->id !== $existing->id) {
                        $this->error = "User instance already exists";
                        $this->errorCode = 101;
                        return false;
                    }
                }
            }
        }
        unset($meta['uniqueKeys']);
        unset($meta['id']);
        if(isset($meta['email']) && ($test = $this->get($meta['email']))) {
            if($test->id !== $existing->id) {
                $this->error = "Sorry, email \"".$meta['email']."\" is already in use by another user.";
                return false;
            }
        }
        $this->query->update("users");
        $toUpdate = false;
        if(isset($meta['email'])) {
            $this->query->set("email", $meta['email'], "s");
            $toUpdate = true;
            unset($meta['email']);
        }
        if(isset($meta['password'])) {
            $password = self::encrypt($meta['password']);
            $this->query->set("password", $password, "s");
            $toUpdate = true;
            unset($meta['password']);
        }
        if($toUpdate) {
            $this->query->where("id", $existing->id, "i")->execute();
        }

		if (isset($meta['user_id'])) unset($meta['user_id']);

        $meta = $this->processFiles($existing->id, $meta);

        foreach($meta as $key => $value) {
            $res = $this->query->select("user_meta", "COUNT(*) AS count")
                    ->where("meta_name", $key)
                    ->where("user_id", $existing->id, "i")
                    ->execute()->rows();
            if($res[0]->count > 0) {
                $this->updateMeta($existing->id, $key, $value);
            } else {
                $this->insertMeta($existing->id, $key, $value);
            }
        }
        return true;
    }

    private function insertMeta($id, $meta, $value) {
        $this->addMeta($id, [$meta => $value]);
    }

    private function updateMeta($id, $meta, $value) {
        $this->query = new Query;
        $query = $this->query->update("user_meta");
        switch (gettype($value)) {
            case "boolean":
                    $value = $value ? 1 : 0;
                    $query->set("meta_value", (string) $value)->set("data_type", "boolean")->set("meta_int", $value, "i");
                break;

            case "integer":
                    $query->set("meta_value", (string) $value)->set("data_type", "int")->set("meta_int", $value, "i");
                break;

            case "double":
            case "float":
                    $query->set("meta_value", (string) $value)->set("data_type", "double")->set("meta_double", $value, "d");
                break;

            case "array":
                    $value = Operations::serialize($value);
                    $query->set("meta_value", (string) $value)->set("data_type", "array");
                break;

            case "object":
                    $value = (array) $value;
                    $value = Operations::serialize($value);
                    $query->set("meta_value", (string) $value)->set("data_type", "object");
                break;

            case "blob":
                    $query->set("meta_value", (string) $value)->set("data_type", "blob")->set("meta_blob", $value, "b");
                break;

            default:
                    $query->set("meta_value", (string) $value)->set("data_type", "string");
                break;
        }
        $query->where('user_id', $id, "i")->where("meta_name", $meta)->execute();
    }

    private function processFiles($id, $meta) {
        if(!isset($meta['file_attachments'])) return $meta;
        foreach ($meta['file_attachments'] as $key => $file) {
            $default = [
                "processor" => "uploadBase64Image",
                "path" => "Uploads/$id",
                "saveAs" => $key
            ];
            $file = array_merge($default, (array) $file);
            $res = (new Files)->processFile($file);
            if($res) {
                $meta[$key] = $res;
            }
            unset($meta['file_attachments']);
        }
        return $meta;
    }

    public function generateUsername(array $meta = []) {
    
        if(isset($meta['username'])) return $meta['username'];
    
        if(isset($meta['user_name'])) return $meta['user_name'];
    
        if($option = Options::get('registration_use_field_for_username')) {
            if(isset($meta[$option]) && $meta[$option] != '' && $meta[$option] != null) {
                return Operations::applyFilters("username_filter", $meta[$option]);
            }
        }
        $prefix = ($option = Options::get('username_prefix')) ? trim($option) : "";
        do {
            $user_name = ($prefix !== "") ? $prefix.rand(10000, 99999).rand(10000, 99999) : rand(10000, 99999).rand(10000, 99999).'-'.rand(100, 999);
        } while ($this->get($user_name));
        return Operations::applyFilters("username_filter", $user_name);
    }

    public function get($selector) {

        if(gettype($selector) === "integer") {
            $selectColumn = "user_id";
            $selector = $selector;
            $selectorType = "i";
        } else {
            if(filter_var($selector, FILTER_VALIDATE_EMAIL)) {
                $selectColumn = "email";
                $selector = (string) $selector;
                $selectorType = "s";
            } else {
                $selectColumn = "username";
                $selector = (string) $selector;
                $selectorType = "s";
            }
        }
        
        
        $stmt = "SELECT DISTINCT meta_name, meta_value, data_type, meta_int, meta_double, meta_blob, username, email, users.id, password, date_created
            FROM user_meta 
            LEFT JOIN users ON users.id = user_meta.user_id
            WHERE user_id IN (SELECT id FROM users WHERE $selectColumn = ?)
            ORDER BY meta_value ASC";

        $res = $this->query->query($stmt, $selectorType, $selector)->execute()->rows("OBJECT_K");
        if(empty($res)) {
            $this->error = "User not found";
            $this->errorCode = 500;
            return false;
        }
        $meta = array_map(function($v){
            switch ($v->data_type) {
                case "int":
                case "intege":
                case "integer":
                case "number":
                    return $v->meta_int;
                    break;

                case "boolean":
                case "boolea":
                    return ($v->meta_int === 0) ? false : true;
                    break;

                case "double":
                case "float":
                    return $v->meta_double;
                    break;

                case "blob":
                    return $v->meta_blob;
                    break;

                case "array":
                    return Operations::unserialize($v->meta_value);
                    break;

                case "object":
                    return (object) Operations::unserialize($v->meta_value);
                    break;

                default:
                    $meta_value = htmlspecialchars_decode($v->meta_value);
                    return Operations::removeslashes($meta_value); 
                    break;
            }
            
        }, $res);
        $meta['email'] = $res['role']->email;
        $meta['username'] = $res['role']->username;
        $meta['password'] = $res['role']->password;
        $meta['date_created'] = $res['role']->date_created;
        $meta['id'] = $res['role']->id;
        $meta = (object) $meta;
        return $this->processJoinRequest($meta);
    }

    public function delete($userId) {
        $this->resetQuery();
        $this->query->delete("users")->where("id", $userId, "i")->execute();
        $this->query->delete("user_meta")->where("user_id", $userId, "i")->execute();
        $files = new Files;
        $files->deleteDir("Uploads/$userId");
    }

    public function deleteUser() {
        $this->resetQuery();
        $this->getUser();
        $this->resourceType = "deleteUserByMetaData";
        return $this;
    }

    public function getUser() {
        $this->resetQuery();
        $this->resourceType = "getUserByMetaData";
        $this->query->select("user_meta", "user_id");
        $this->query->statement .= " WHERE";
        $this->query->hasWhere = true;
        $this->query->ready = true;
        $this->init = true;
        return $this;
    }

    public function getCount() {
        $this->resetQuery();
        $this->getUser();
        $this->resourceType = "getUserCountByMetaData";
        return $this;
    }

    public function getIds() {
        $this->resetQuery();
        $this->getUser();
        $this->resourceType = "getUserIdsByMetaData";
        return $this;
    }

    public function where($meta_name, $meta_value, $type = "s", $rel = "LIKE") {
        if(!$this->init) {
            $this->query->or();
        } else $this->init = false;
        $this->query->openGroup()->where("meta_name", $meta_name);
        if(is_array($meta_value)) {
           if(strstr( $rel, 'NOT' )) {
                $this->query->whereNotIn("meta_value", $type, ...$meta_value);
            } else {
                $this->query->whereIn("meta_value", $type, ...$meta_value); 
            }
        } else {
           $this->query->where("meta_value", $meta_value, $type, $rel);
        }
        $this->query->closeGroup();
        $this->addArgument($meta_name, $meta_value);
        $this->query->ready = false;
        return $this;
    }

    public function whereGroup(array $meta = [], string | array $rel = "LIKE") {
        if(!Operations::count($meta)) return $this;
        foreach ($meta as $meta_name => $meta_value) {
            $rel = (is_array($rel) && isset($rel[$meta_name])) ? $rel[$meta_name] : $rel;
            $this->where(
                $meta_name, 
                $meta_value, 
                $this->evaluateData($meta_value)->valueType, 
                $rel
            );
        }
        return $this;
    }

    public function orderBy($column, $order = 'ASC') {
        $this->query->orderBy($column, $order);
        return $this;
    }

    public function limit($limit) {
        $this->query->limit($limit);
        return $this;
    }

    public function offset($offset) {
        $this->query->offset($offset);
        return $this;
    }

    private function getUserByMetaData() {
        $this->query->groupBy("user_id")->having("COUNT(user_id) = ?", "i", $this->num_args);
        $this->query->ready = true;
        $this->resultIds = $this->query->execute()->rows();
        $this->result = array_map(function($v){
            return $this->get($v->user_id);
        }, $this->resultIds);
        return $this->result;
    }

    private function getUserCountByMetaData() {
        $this->query->groupBy("user_id")->having("COUNT(user_id) = ?", "i", $this->num_args);
        $this->query->ready = true;
        $this->resultIds = $this->query->execute()->rows();
        return Operations::count($this->resultIds);
    }

    private function getUserIdsByMetaData() {
        $this->query->groupBy("user_id")->having("COUNT(user_id) = ?", "i", $this->num_args);
        $this->query->ready = true;
        $this->resultIds = $this->query->execute()->rows();
        $this->result = array_map(function($v){
            return $v->user_id;
        }, $this->resultIds);
        return $this->result;
    }

    public function deleteUserByMetaData() {
        $ids = $this->getUserIdsByMetaData();
        if(Operations::count($ids)) {
            foreach ($ids as $userId) {
                $this->delete($userId);
            }
        }
    }

    public function changePassword($data) {
        $session = Session::getInstance();
        $tokenObj = $this->getTokenObject($session->accesstoken);
        if(!$tokenObj) {
            return [
                "status" => false,
                "message" => "Invalid session. Please sign in again."
            ];
        }
        $user = $this->get($tokenObj->user_id);
        if(!$user) {
            return [
                "status" => false,
                "message" => "Something went wrong. Please sign in again."
            ];
        }
        if($this->encrypt($data['oldPassword']) !== $user->password) {
            return [
                "status" => false,
                "message" => "Current password is incorrect."
            ];
        }
        $this->update($tokenObj->user_id, ['password' => $data['password']]);
        return [
            "status" => true,
            "message" => "Done"
        ];
    }

    public function changeUserPassword($data) {
        $session = Session::getInstance();
        $tokenObj = $this->getTokenObject($session->accesstoken);
        if(!$tokenObj) {
            return [
                "status" => false,
                "message" => "Invalid session. Please sign in again."
            ];
        }
        $user = $this->get($tokenObj->user_id);
        if(!$user) {
            return [
                "status" => false,
                "message" => "Something went wrong. Please sign in again."
            ];
        }
        if($this->encrypt($data['yourPassword']) !== $user->password) {
            return [
                "status" => false,
                "message" => "Your password is incorrect."
            ];
        }
        $this->update($data['user_id'], ['password' => $data['password']]);
        return [
            "status" => true,
            "message" => "Done"
        ];
    }

}