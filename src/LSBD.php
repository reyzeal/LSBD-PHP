<?php
namespace reyzeal;

use bzcompress;
use bzdecompress;
use finfo;
use reyzeal\LSBD\LinkedList;

class LSBD{
    private $path;
    private function getpath($file){
        return $this->path.DIRECTORY_SEPARATOR.$file;
    }
    private function decompress($str){
        return bzdecompress($str,9);
    }
    private function compress($str){
        return bzcompress($str,9);
    }
    public function update(){
        file_put_contents($this->getpath("lsbd.data"),serialize($this->lsbd));
    }
    public function __construct($path, $compressFlag = false){
        $this->path = $path;
        $this->lsbd = null;
        $this->compressFlag = $compressFlag;
        if(!is_dir($path)){
            mkdir($path,0777,true);
        }
        if(is_file($this->getpath("lsbd.data"))){
            $this->lsbd = unserialize(file_get_contents($this->getpath("lsbd.data")));
        }else{
            $this->lsbd = new LinkedList($this->path);
            $this->update();
        }
    }
    public function pop($uuid){
        $this->stack->revert($uuid);
        $this->update();
    }
    public function get($uuid = null){
        if(!$uuid){
            $history = $this->lsbd->getHistory();
            $data = $this->lsbd->retrieve(end($history));
        }
        else{
            $data = $this->lsbd->retrieve($uuid);
        }
        if($this->compressFlag){
            $data = $this->decompress($data);
        }
        return $data;
    }
    public function push($data){
        if(is_file($data)) $data = file_get_contents($data);
        else return;
        $temp = $this->getpath("data");
        if($this->compressFlag){
            $data = $this->compress($data);
        }
        file_put_contents($temp, $data);
        $x = $this->lsbd->add($temp);
        unlink($temp);
        $this->update();
        return $x;
    }
    public function getMeta(){
        return $this->lsbd->meta;
    }
}