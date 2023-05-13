<?php 

namespace EvoPhp\Api\Requests;

use EvoPhp\Resources\Post;
use EvoPhp\Resources\User;
use EvoPhp\Database\Query;
use EvoPhp\Api\Operations;
use EvoPhp\Database\DataType;

class PutRequest implements RequestInterface {

    use DataType;

    public static function postTable($request) {
        $post = new Post;
        if(!isset($request->data['id'])) {
            http_response_code(422);
            $request->response = null;
        }
        else {
            $request->setUniqueKeys();
            $post->update($request->data['id'], $request->data, ...$request->uniqueKeys);
            if($post->error !== "") {
                http_response_code(422);
                $request->response = $post->error;
            } else {
                http_response_code(200);
                $request->response = $post->get($request->data['id']);
            }
        }
    }

    public static function usersTable($request) {
        $user = new User;
        if(!isset($request->data['id'])) {
            http_response_code(422);
            $request->response = null;
        }
        else {
            $request->setUniqueKeys();
            $user->update($request->data['id'], $request->data, ...$request->uniqueKeys);
            if($user->error !== "") {
                http_response_code(422);
                $request->response = $user->error;
            } else {
                http_response_code(200);
                $request->response = $user->get($request->data['id']);
            }
        }
    }

    public static function optionsTable($request) {
        PostRequest::optionsTable($request);
    }

    public static function recordsTable($request) {
        PostRequest::recordsTable($request);
    }

    public static function dbTable($request) {
        if(Operations::count($request->data)) {
            $id = $request->data['id'];
            $instance = new self();
            $query = new Query;
            $request->setUniqueKeys();
            if(Operations::count($request->uniqueKeys)) {
                foreach($request->uniqueKeys as $uniqueKey) {
                    if(!isset($request->data[$uniqueKey])) continue;
                    $res = $query->select($request->tableName, "COUNT(*) as count, id")
                                ->where($uniqueKey, $request->data[$uniqueKey], $instance->evaluateData($request->data[$uniqueKey])->valueType)
                                ->execute()
                                ->rows("OBJECT");
                    if(($res->count > 0) && ($res->id != $id)) {
                        http_response_code(422);
                        $request->response = "Same \"".$uniqueKey."\" already exists in the database.";
                    }
                }
            }
            $query->update($request->tableName);
            foreach($request->data as $key => $value) {
                if($key == 'id') continue;
                $query->set($key, $value, $instance->evaluateData($value)->valueType);
            }

            $query->where("id", $id, "i")->execute();
            
            if($query->connection->error !== "") {
                http_response_code(400);
                $request->response = "MySqli Error: ".$query->connection->error;
            } else {
                http_response_code(200);
                $request->response = $query->select($request->tableName)
                                        ->where("id", $id, "i")
                                        ->execute()->rows();
            }
        } else {
            http_response_code(400);
            $request->response = NULL;
        }
    }

}