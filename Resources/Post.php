<?php 

namespace EvoPhp\Resources;

use EvoPhp\Database\Query;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Database\DataType;
use EvoPhp\Api\FileHandling\Files;

/**
 * summary
 */
class Post
{
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
            realpath(dirname(__FILE__))."/Post.php", 
            "private \$firstInstall = false;", 
            "\tprivate \$firstInstall = true;\n"
        );
        $this->createTable();
    }

    private function createTable() {
        if($this->query->checkTableExist("post")) {
            $this->maintainTable();
            return;
        }

        $statement = "CREATE TABLE IF NOT EXISTS post (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title TEXT NOT NULL,
                permalink TEXT NOT NULL
                )";
        $this->query->query($statement)->execute();

        $statement = "CREATE TABLE IF NOT EXISTS post_meta (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id BIGINT(20) NOT NULL,
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
        $statement = "ALTER TABLE post_meta ADD 
                        (
                            data_type VARCHAR(6) DEFAULT 'string',
                            meta_int BIGINT(20) NOT NULL,
                            meta_double DOUBLE(20,4) NOT NULL,
                            meta_blob BLOB NOT NULL
                        )";
        $this->query->query($statement)->execute();
    }

    public function execute() {
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

    public function new($postType, $meta = array(), ...$unique_keys) {
        $this->resetQuery();

        if(is_object($meta)) {
            $meta = (array) $meta;
        }

        $default = [
            "title" => "New ".ucwords($postType),
            "type" => $postType,
            "posted_on" => time(),
            "posted_by" => $this->session->getResourceOwner()->user_id ?? "",
            'db_section_id' => -1
        ];

        $meta = array_merge($default, $meta);
        $meta = Operations::sanitize($meta);

        if($meta['type'] === "page") {
            if(!isset($meta['permalink'])) {
                $meta['permalink'] = $this->generatePermalink($meta['title']);
            } else {
                $meta['permalink'] = $this->generatePermalink($meta['permalink']);
            }
        } else {
            $meta['permalink'] = "";
        }

        if(Operations::count($unique_keys)) {
            $postObj = $this->getCount($postType);
            foreach ($unique_keys as $meta_name) {
                if(!isset($meta[$meta_name])) continue;
                $postObj->where($meta_name, $meta[$meta_name]);
            }
            $res = $postObj->execute();
            if($res > 0) {
                $this->error = "Post instance already exists";
                $this->errorCode = 101;
                return false;
            }
        }

        $post_id = $this->query->insert('post', 'ss', 
            ['title' => $meta['title'], 'permalink' => $meta['permalink']]
        )->execute();

        if($post_id) {
            $meta = $this->processFiles($post_id, $postType, $meta);
            $this->addMeta($post_id, $meta);
            return $post_id;
        }
        $this->error = ""; //set error message
        return false;

    }

    public function addMeta($post_id, $meta) {
        if(!Operations::count($meta)) {
            return false;
        }
        foreach ($meta as $key => $value) {
            $ev = $this->evaluateData($value);
            $value = ["post_id" => $post_id, "meta_name" => $key, "meta_value" => (string) $ev->value, "data_type" => $ev->realType];
            $types = "isss";
            if($ev->field) {
                $value['meta_'.$ev->field] = $ev->value;
                $types .= $ev->valueType;
            }
            $this->query->insert('post_meta', $types, $value)->execute();
        }
    }

    public function update($postId, $meta, ...$unique_keys) {
        $this->resetQuery();
        
        if(is_object($meta)) {
            $meta = (array) $meta;
        }
        if(!$existing = $this->get($postId)) {
            return false;
        }
        if(Operations::count($unique_keys)) {
            $instance = $this->getPost($existing->type);
            foreach($unique_keys as $unique_key) {
                if(!isset($meta[$unique_key])) continue;
                $instance->where($unique_key, $meta[$unique_key]);
            }
            $rows = $instance->execute();
            if(Operations::count($rows)) {
                foreach($rows as $row) {
                    if($row->id !== $postId) {
                        $this->error = "Post instance already exists";
                        $this->errorCode = 101;
                        return false;
                    }
                }
            }
        }
        $meta = $this->processFiles($postId, $existing->type, $meta);
        foreach($meta as $key => $value) {
            $res = $this->query->select("post_meta", "COUNT(*) AS count")
                    ->where("meta_name", $key)
                    ->where("post_id", $existing->id, "i")
                    ->execute()->rows();
            if($res[0]->count > 0) {
                $this->updateMeta($existing->id, $key, $value);
            } else {
                $this->insertMeta($existing->id, $key, $value);
            }
        }
    }

    private function insertMeta($id, $meta, $value) {
        $this->addMeta($id, [$meta => $value]);
    }

    private function updateMeta($id, $meta, $value) {
        $ev = $this->evaluateData($value);
        $query = $this->query->update("post_meta")->set("meta_value", (string) $ev->value)->set("data_type", $ev->realType);
        if($ev->field) {
            $query->set("meta_".$ev->field, $ev->value, $ev->valueType);
        }
        $query->where('post_id', $id, "i")->where("meta_name", $meta)->execute();
    }

    private function processFiles($id, $type, $meta) {
        if(!isset($meta['file_attachments'])) return $meta;
        foreach ($meta['file_attachments'] as $key => $file) {
            $default = [
                "processor" => "uploadBase64Image",
                "path" => "Uploads/$type/$id",
                "saveAs" => $key
            ];
            $file = array_merge($default, $file);
            $res = (new Files)->processFile($file);
            if($res) {
                $meta[$key] = $res;
            }
            unset($meta['file_attachments']);
        }
        return $meta;
    }

    public function get($postId) {
        $this->resetQuery();
        
        $stmt = "SELECT DISTINCT meta_name, meta_value, data_type, meta_int, meta_double, meta_blob, title, permalink
            FROM post_meta 
            LEFT JOIN post ON post.id = ?
            WHERE post_id = ?
            ORDER BY meta_value ASC";
        $res = $this->query->query($stmt, "ii", $postId, $postId)->execute()->rows("OBJECT_K");
        if(empty($res)) {
            $this->error = "Post ID not found";
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
        $meta['permalink'] = $res['posted_on']->permalink;
        $meta['title'] = $res['posted_on']->title;
        $meta['id'] = $postId;
        $meta = (object) $meta;
        return $this->processJoinRequest($meta);
    }

    public function delete($postId) {
        $this->resetQuery();
        $this->query->delete("post")->where("id", $postId, "i")->execute();
        $this->query->delete("post_meta")->where("post_id", $postId, "i")->execute();
    }

    public function deletePost($postType) {
        $this->resetQuery();
        $this->getPost($postType);
        $this->resourceType = "deletePostByMetaData";
        return $this;
    }

    public function getPage($permalink) {
        $this->resetQuery();
        $result = $this->query->select('post')->where('permalink', $permalink)->execute()->rows();
        if(!Operations::count($result))
            return false;
        return $result[0]->id;
    }

    public function getPost($postType) {
        $this->resetQuery();
        $this->resourceType = "getPostByMetaData";
        $this->query->select("post_meta", "post_id");
        $this->query->statement .= " WHERE";
        $this->query->hasWhere = true;
        $this->query->openGroup()->where("meta_name", "type")->where("meta_value", $postType)->closeGroup();
        $this->addArgument("type", $postType);
        $this->query->ready = false;
        return $this;
    }

    public function getCount($postType) {
        $this->resetQuery();
        $this->getPost($postType);
        $this->resourceType = "getPostCountByMetaData";
        return $this;
    }

    public function getIds($postType) {
        $this->resetQuery();
        $this->getPost($postType);
        $this->resourceType = "getPostIdsByMetaData";
        return $this;
    }

    public function where($meta_name, $meta_value, $type = "s", $rel = "LIKE") {
        $this->query->or()->openGroup()->where("meta_name", $meta_name);
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

    private function getPostByMetaData() {
        $this->query->groupBy("post_id")->having("COUNT(post_id) = ?", "i", $this->num_args);
        $this->query->ready = true;
        $this->resultIds = $this->query->execute()->rows();
        $this->result = array_map(function($v){
            return $this->get($v->post_id);
        }, $this->resultIds);
        return $this->result;
    }

    private function getPostCountByMetaData() {
        $this->query->groupBy("post_id")->having("COUNT(post_id) = ?", "i", $this->num_args);
        $this->query->ready = true;
        $this->resultIds = $this->query->execute()->rows();
        return Operations::count($this->resultIds);
    }

    private function getPostIdsByMetaData() {
        $this->query->groupBy("post_id")->having("COUNT(post_id) = ?", "i", $this->num_args);
        $this->query->ready = true;
        $this->resultIds = $this->query->execute()->rows();
        $this->result = array_map(function($v){
            return $v->post_id;
        }, $this->resultIds);
        return $this->result;
    }

    public function deletePostByMetaData() {
        $ids = $this->getPostIdsByMetaData();
        if(Operations::count($ids)) {
            foreach ($ids as $postId) {
                $this->delete($postId);
            }
        }
    }

    public function generatePermalink($title_original) {
        $title = strtolower($title_original);
        $strip_words = array(' is ', ' and ', ' to ', ' for ', ' are ', ' a ', ' with ', ' i ', ' the ', ' my ', '(', ')', '[', ']', '{', '}', '&', '/', '|', '%');
        $title = str_replace($strip_words, '', $title);
        $sample_link = preg_replace('/\s+/', '-', $title);
        
        if($this->getPage($sample_link)) {
            $i = $this->getNextLinkSerial($sample_link, $title_original);
            $final_link = $sample_link.'-'.$i;
        } else return $sample_link;
        while($this->getPage($final_link)) {
            $i++;
            $final_link = $sample_link.'-'.$i;
        }
        return $final_link;
    }

    public function getNextLinkSerial($sample_link, $title) {
        $link = $this->query->select('post')->where('permalink', $sample_link."-%")->where('title', $title)->orderBy('id', 'DESC')->limit(1)->rows("OBJECT_K");
        if(Operations::count($link)) {
            $link = array_values($link);
            $permalink_arr = explode("-", $link[0]->permalink);
            $serial = (int) end($permalink_arr);
            return $serial + 1;
        } else return 1;
    }

}