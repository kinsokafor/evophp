<?php 

namespace EvoPhp\Api;

use EvoPhp\Api\Operations;
use Josantonius\Json\Json;

/**
 * summary
 */
class Config
{
    /**
     * summary
     */
    public $configFile = "./evo.config.json";

    public $json;

    public function __construct(string $file = "./evo.config.json")
    {
        $this->json = new Json($file);
        $this->reload();
    }

    private function reload() {
        if ($this->json->exists()) {
            $config = $this->json->get();

            if(Operations::count($config)) {
                foreach ($config as $key => $value) {
                    $this->$key = $value;
                }
            }
        }
    }

    private function parseKey($key) {
        // convert array to dot notation
        $key = str_replace("]", "", $key);
        $key = str_replace("\"", "", $key);
        $key = str_replace("'", "", $key);
        $key = str_replace("[", ".", $key);
        return $key;
    }

    public function set($key, $value) {
        $key = $this->parseKey($key);
        $this->json->set($value, $key);
        $this->reload();
        return $this;
    }

    public function merge($key, $value) {
        $key = $this->parseKey($key);
        $this->json->merge($value, $key);
        return $this;
    }

    public function delete($key) {
        $this->json->unset($key);
        $this->reload();
        return $this;
    }

    public function setMultiple($params) {
        $ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($params));
        $result = array();
        foreach ($ritit as $leafValue) {
            $keys = array();
            foreach (range(0, $ritit->getDepth()) as $depth) {
                $keys[] = $ritit->getSubIterator($depth)->key();
            }
            if(gettype($params[$keys[0]]) != gettype($this->{$keys[0]})) {
                $this->delete($keys[0]);
            }
            $result[ join('.', $keys) ] = $leafValue;
        }
        foreach ($result as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

}