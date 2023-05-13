<?php 

namespace EvoPhp\Api;

use Inhere\Route\Router;

class EvoRouter
{
    private $request;

    function __construct()
    {
        $this->router = new Router();
        $this->router->config([
            'ignoreLastSlash' => true,
            // 'actionExecutor' => 'run',
            
            // enable autoRoute, work like yii framework
            // you can access '/demo' '/admin/user/info', Don't need to configure any route
            'autoRoute' => 1,
            // 'controllerNamespace' => 'Example\\controllers',
            // 'controllerSuffix' => 'Controller',
        ]);
    }

    function __call($name, $args)
    {
        $name = strtolower($name);
        list($route, $method) = $args;
        $this->router->$name($route, $method);
    }

    function __destruct()
    {
        $this->router->dispatch();
    }
}