<?php

namespace EvoPhp\Api\FileHandling;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use EvoPhp\Api\Config;

class Files {

    /**
     * summary
     */
    private $fs;

    private $config;

    public function __construct()
    {
        $this->fs = new Filesystem();
        $this->config = new Config;
    }

    public function processFile(array $file) {
        if(!isset($file['data'])) return false;
        $processor = $file['processor'];
        if(method_exists($this, $processor)) {
            return $this->{$processor}($file['data'], $file['path'], $file['saveAs']);
        } else return false;
    }

    public function uploadBase64Image($data, $path, $saveAs = "image") {
        if($data != '') {
            $dataArr = explode(';', $data);
            if(!isset($dataArr[1])) return false;
            list($type, $data) = $dataArr;
            list(, $data)      = explode(',', $data);
            list(, $type) = explode(":", $type);
            list($saveAs, ) = explode(".", $saveAs);
            $data = base64_decode($data);
            $this->fs->mkdir(Path::canonicalize($path));
            $path = Path::canonicalize($path ."/". $saveAs . "." . $this->mimeToExtension($type));
            file_put_contents($path, $data);
            return $this->config->root."/".$path;
        } else return false;
    }

    public function mimeToExtension($mime) {
        $mime_map = require("MimeTypes.php");
        return isset($mime_map[$mime]) ? $mime_map[$mime] : $mime;
    }

    public function deleteDir($dirPath) {
        $instance = new self;
        $dirPath = Path::canonicalize($dirPath);
        if (! is_dir($dirPath)) {
            return;
            // throw new \InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = \glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $instance->deleteDir($file);
            } else {
                \unlink($file);
            }
        }
        \sleep(5);
        \rmdir($dirPath);
    }
}