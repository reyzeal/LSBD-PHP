<?php
namespace reyzeal;

class LSBD{
    private $path;
    private function getpath($file){
        return $this->path.DIRECTORY_SEPARATOR.$file;
    }
    public function __construct($path){
        $this->path = $path;
        $this->meta = [
            "linked_stack" => []
            
        ];
        if(!is_dir($path)){
            mkdir($path);
        }
        if(!is_file($this->getpath("meta.json"))){
            file_put_contents();
        }
    }
    public function pop(){

    }
    public function get(){

    }
    public function push(){

    }
}