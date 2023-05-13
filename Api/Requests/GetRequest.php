<?php 

namespace EvoPhp\Api\Requests;

use EvoPhp\Resources\Post;
use EvoPhp\Resources\User;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\Records;
use EvoPhp\Database\Query;

class GetRequest implements RequestInterface {

    public static function postTable($request)
    {
        $post = new Post;
        if(isset($request->data['id'])) {
            $request->response = $post->get($request->data['id']);
            if($request->response) {
                http_response_code(200);
            } else http_response_code(404);
        } 
        else if(isset($request->data['type'])) {
            $type = $request->data['type'];
            unset($request->data['type']);
            if($request->isCount) {
                $post->getCount($type);
            } else $post->getPost($type);
            $post->whereGroup($request->data);
            if($request->limit) $post->limit($request->limit);
            if($request->offset) $post->offset($request->offset);
            http_response_code(200);
            $request->response = $post->execute();
        }
        else {
            http_response_code(422);
            $request->response = null;
        }
    }

    public static function usersTable($request) {
        $user = new User;
        if(isset($request->data['id'])) {
            $request->response = $user->get((int) $request->data['id']);
            if($request->response) {
                http_response_code(200);
            } else http_response_code(404);
        } 
        else if(isset($request->data['email'])) {
            $request->response = $user->get($request->data['email']);
            if($request->response) {
                http_response_code(200);
            } else http_response_code(404);
        } 
        else if(isset($request->data['username'])) {
            $request->response = $user->get($request->data['username']);
            if($request->response) {
                http_response_code(200);
            } else http_response_code(404);
        }
        else if(isset($request->data['selector'])) {
            $request->response = $user->get($request->data['selector']);
            if($request->response) {
                http_response_code(200);
            } else http_response_code(404);
        } 
        else {
            if($request->isCount) {
                $user->getCount();
            } else $user->getUser();
            $user->whereGroup($request->data);
            if($request->limit) $user->limit($request->limit);
            if($request->offset) $user->offset($request->offset);
            http_response_code(200);
            $request->response = $user->execute();
        }
    }

    public static function optionsTable($request) {
        $option = new Options;
        if(isset($request->data['key'])) {
            $request->response = $option->getOption($request->data['key'], $request->cache, $request->cacheExpiry);
            if($request->response !== NULL) {
                http_response_code(200);
            } else http_response_code(404);
        } else {
            http_response_code(422);
            $request->response = null;
        }
    }

    public static function recordsTable($request) {
        $record = new Records;
        if(isset($request->data['key'])) {
            $request->response = $record->getRecord($request->data['key'], $request->cache, $request->cacheExpiry);
            if($request->response) {
                http_response_code(200);
            } else http_response_code(404);
        } else {
            http_response_code(422);
            $request->response = null;
        }
    }

    public static function dbTable($request) {
        $query = new Query;
        $selection = $request->isCount ? "COUNT(*) AS count" : "*";
        $query->select($request->tableName, $selection)->whereGroup($request->data);
        if($request->limit) $query->limit($request->limit);
        if($request->offset) $query->offset($request->offset);
        http_response_code(200);
        $request->response = $query->execute()->rows();
    }
}