<?php

namespace EvoPhp\Themes;
use Jenssegers\Blade\Blade;
use function file_exists, array_merge;
use EvoPhp\Api\Config;

/**
 * Themes
 * This is the main theme class which is used to set themes global data and load resources.
 */
class Themes {

    public $data = [];

    public $viewPath;
    
    public $bladeTemplate = "index";
    /**
     * __construct
     * Accepts template name as argument or use index as the default template
     * To set the template name use controllerInstance->template(template_name)
     * @param  string $template 
     * @param  array $data 
     * used to trasfer data from modules to template and themes files
     * @return void
     */
    public function __construct($template = "", $data = [])
    {
        $this->data = $data;
        $config = new Config;
        $this->viewPath = "./Public/Themes/".$config->currentTheme."/Views";
        if(
            file_exists($this->viewPath."/".$template.".blade.php") 
            || file_exists($this->viewPath."/".$template.".php")
        ) {
            $this->bladeTemplate = $template;
        } else $this->bladeTemplate = "index";
        $this->getData();
    }

    private function getData() {
        $config = new Config;
        $file = "./Public/Themes/".$config->currentTheme."/Data/".$this->bladeTemplate.".data.php";
        if(file_exists($file)) {
            $themeData = require $file;
            $this->data = array_merge($this->data, $themeData);
        }
    }

    public function getView($content = "") {
        $this->data['content'] = $content;
        $blade = new Blade($this->viewPath, $this->viewPath.'/cache');
        return $blade->make($this->bladeTemplate, $this->data)->render();
    }

}

?>