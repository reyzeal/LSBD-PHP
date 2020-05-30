<?php 
namespace reyzeal\LSBD;

trait PathHelper{
    protected $path;
    public function getpath($file){
        return $this->path.DIRECTORY_SEPARATOR.$file;
    }
}