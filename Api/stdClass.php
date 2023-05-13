<?php 

namespace EvoPhp\Api;
/**
 * summary
 */
class stdClass
{
    /**
     * summary
     */
    public function __construct()
    {
        
    }

    public function __set($property, $value) {
    	$this->$property = $value;
    }

    /**
     * getting the property of the api
     */
    public function __get($property) {
    	return $this->$property;
    }
}