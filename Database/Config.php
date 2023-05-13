<?php 

namespace EvoPhp\Database;

use EvoPhp\Api\Config as EvoConfig;

/**
 * Configuration file
 * Do all your api configuration in this file
 */
class Config
{
    /*
    * Database host
    */
    private $db_host = "localhost";

    /*
    * Database name
    */
    private $db_name = "ugotech";

    /*
    * Array of Database users & passwords
    */
    private $db_users = [
    	["username" => "root", "password" => ""]
    ];

    /*
    * Change to false if you want to go live
    */
    private $devmode = true;

    public $evoConfig;


    public function __construct(){
        $this->evoConfig = new EvoConfig;
    }

    public function __get($prop) {
    	return $this->$prop;
    }
    
}