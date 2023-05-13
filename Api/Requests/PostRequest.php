<?php 

namespace EvoPhp\Api\Requests;

use EvoPhp\Resources\Post;
use EvoPhp\Resources\User;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\Records;
use EvoPhp\Database\Query;
use EvoPhp\Api\Operations;
use EvoPhp\Database\DataType;

class PostRequest implements RequestInterface {
    
    use Datatype;

    public static function postTable($request)
    {
        if(!isset($request->data['type'])) {
            http_response_code(422);
            $request->response = null;
        } else {
            $request->setUniqueKeys();
            $post = new Post;
            $postId = $post->new($request->data['type'], $request->data, ...$request->uniqueKeys);
            if(!$postId) {
                http_response_code(422);
                $request->response = $post->error;
            } else {
                http_response_code(201);
                $request->response = $post->get($postId);
            }
        }
    }

    public static function usersTable($request) {
        $request->setUniqueKeys();
        $user = new User;
        $userId = $user->new($request->data, ...$request->uniqueKeys);
        if(!$userId) {
            http_response_code(422);
            $request->response = $user->error;
        } else {
            http_response_code(201);
            $request->response = $user->get($userId);
        }
    }

    public static function optionsTable($request) {
        if(!isset($request->data['key']) || !isset($request->data['value'])) {
            http_response_code(422);
            $request->response = null;
        } else {
            $options = new Options;
            $options->updateOption($request->data['key'], $request->data['value']);
            http_response_code(201);
            $request->response = $options->get($request->data['key']);
        }
    }

    public static function recordsTable($request) {
        if(!isset($request->data['key']) || !isset($request->data['value'])) {
            http_response_code(422);
            $request->response = null;
        } else {
            $records = new Records;
            $records->updateRecord($request->data['key'], $request->data['value']);
            http_response_code(201);
            $request->response = $records->get($request->data['key']);
        }
    }

    public static function evoActions($request) {
        if(!isset($request->data['error'])) {
            http_response_code(422);
            $request->response = NULL;
        }
        $request->response = $request->data['response'];
        http_response_code(200);
    }

    public static function dbTable($request) {
        if(Operations::count($request->data)) {
            $dataTypes = "";
            $instance = new self();
            $query = new Query;
            $request->setUniqueKeys();
            if(Operations::count($request->uniqueKeys)) {
                foreach($request->uniqueKeys as $uniqueKey) {
                    if(!isset($request->data[$uniqueKey])) continue;
                    $res = $query->select($request->tableName, "COUNT(*) as count")
                                ->where($uniqueKey, $request->data[$uniqueKey], $instance->evaluateData($request->data[$uniqueKey])->valueType)
                                ->execute()
                                ->rows("OBJECT");
                    if($res->count > 0) {
                        http_response_code(422);
                        $request->response = "Same \"".$uniqueKey."\" already exists in the database.";
                    }
                }
            }
            foreach($request->data as $key => $value) {
                $dataTypes .= $instance->evaluateData($value)->valueType;
            }
            $query->insert($request->tableName, $dataTypes, ...$request->data);
            if($query->connection->error !== "") {
                http_response_code(400);
                $request->response = "MySqli Error: ".$query->connection->error;
            } else {
                $request->data['id'] = $query->insert_id;
                http_response_code(201);
                $request->response = $request->data;
            }
        } else {
            http_response_code(400);
            $request->response = NULL;
        }
    }
}