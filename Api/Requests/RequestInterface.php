<?php

namespace EvoPhp\Api\Requests;

interface RequestInterface {
    public static function postTable($request);
    public static function usersTable($request);
    public static function optionsTable($request);
    public static function recordsTable($request);
}