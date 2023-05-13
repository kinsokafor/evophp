<?php

namespace EvoPhp\Api;

Trait ControllerTrait {
    public function getData($data) {
        if(method_exists($this, $this->dataMethod)) {
            $callback = $this->dataMethod;
            return $this->$callback($data);
        }
        return false;
    }

    public function addResources() {
        if(method_exists($this, $this->resourceMethod)) {
            $callback = $this->resourceMethod;
            return $this->$callback();
        }
        return false;
    }
}
?>