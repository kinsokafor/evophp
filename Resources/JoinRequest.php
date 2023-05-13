<?php

namespace EvoPhp\Resources;

use EvoPhp\Api\Operations;

Trait JoinRequest {
    
    private $joinRequests = [];

    private $joinResponse = [];

    public function joinUserAt($column, ...$responseColumns) {
        $this->joinRequests['user'] = [
            'column' => $column,
            'responseColumns' => $responseColumns
        ]; 
        return $this;
    }

    public function joinPostAt($column, ...$responseColumns) {
        $this->joinRequests['post'] = [
            'column' => $column,
            'responseColumns' => $responseColumns
        ];
        return $this;
    }

    public function joinAt($table, $column, ...$responseColumns) {
        if($table == 'users')
            return $this->joinUserAt($column, ...$responseColumns);
        if($table == 'post')
            return $this->joinPostAt($column, ...$responseColumns);
        $this->joinRequests[$table] = [
            'column' => $column,
            'responseColumns' => $responseColumns
        ];
        return $this;
    }

    public function hasJoinRequest() {
        return Operations::count($this->joinRequests) ? true : false;
    }

    public function patchJoinResponse($table, $columnValue, $result) {
        
    }

    public function processJoinRequest($result) {
        if(!$this->hasJoinRequest()) return $result;
        foreach($this->joinRequests as $table => $data) {
            if(!isset($result->{$data['column']})) {
                return $result;
            }
            if(isset($this->joinResponse[$table][$result->{$data['column']}])) {
                return $this->joinResponse[$table][$result->{$data['column']}];
            }
            switch ($table) {
                case 'post':
                    $post = new Post;
                    $res = $post->get($result->{$data['column']});
                    if($res) {
                        if(Operations::count($data['responseColumns'])) {
                            foreach($data['responseColumns'] as $column) {
                                if(isset($res->{$column}))
                                    $result->{$column} = $res->{$column};
                            } 
                        } else {
                            $result = (object) array_merge(
                                (array) $res, (array) $result);
                        }
                        return $result;
                    } else return $result;
                    break;

                case 'user':
                    $user = new User;
                    $res = $user->get($result->{$data['column']});
                    if($res) {
                        if(Operations::count($data['responseColumns'])) {
                            foreach($data['responseColumns'] as $column) {
                                if(isset($res->{$column}))
                                    $result->{$column} = $res->{$column};
                            } 
                        } else {
                            $result = (object) array_merge(
                                (array) $res, (array) $result);
                        }
                        return $result;
                    } else return $result;
                    break;
                
                default:
                    # code...
                    break;
            }
        }
    }
}