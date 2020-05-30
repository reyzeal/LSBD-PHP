<?php 

namespace reyzeal\LSBD;

use reyzeal\LSBD\PathHelper;
use reyzeal\LSBD\Meta;
use reyzeal\LSBD\Stack;
use reyzeal\LSBD\MetaCompression;
use Ramsey\Uuid\Uuid;

class LinkedList implements Meta{
    use PathHelper;
    use MetaCompression;
    protected $threshold = 0.9;
    public function __construct($path){
        $this->path = $path;
        $this->meta = [
            "total" => 0,
            "total_uncompressed" => 0,
            "list" => [],
            "history" => [],
            "hash" => []
        ];
        if(!is_dir($path)){
            mkdir($path);
        }
        if(!is_file($this->getpath("meta.lsbd"))){
            $this->update();
        }else{
            $this->meta = unserialize(file_get_contents($this->getpath("meta.lsbd")));
        }
    }
    
    public function add($file){
        $md5 = md5_file($file);
        $patches = [];
        foreach($this->meta['list'] as $list){
            if(md5_file($list->last()->name) == $md5){
                return [
                    'error' => true,
                    'stack' => $list
                ];
            }
            $files = $list->files();
            BinaryDelta::generate(end($files)->name, $file, $this->getpath($list->getName().".patch"));
            $patches[$list->getName()] = BinaryDelta::getSize($this->getpath($list->getName().".patch"));
        }
        $min = null;
        array_map(function($data) use ($patches,&$min, $file){
            if($min == null) $min = $data->getName();
            else{
                if($patches[$min] > $patches[$data->getName()] && BinaryDelta::getSize($file) > $patches[$min] ){
                    $min = $data->getName();
                }
            }
        }, $this->meta['list']);
        $stackTarget = null;
        if($min && $patches[$min]/$this->get($min)->getSize() < $this->threshold){
            $this->get($min)->push($file);
            $stackTarget = $this->get($min);
        }else{
            $uuid = Uuid::uuid4();
            $ustr = $this->getpath($uuid->toString());
            $newStack = new Stack($ustr);
            $newStack->push($file);
            $this->meta['list'][] = $newStack;
            $stackTarget = &$newStack;
        }
        $files = $stackTarget->files();
        $this->meta['history'][] = end($files)->name;
        $this->meta['hash'][] = $md5;
        $this->update();
        foreach($this->meta['list'] as $list){
            if(is_file($this->getpath($list->getName().".patch")))
                unlink($this->getpath($list->getName().".patch")); 
        }
        return [
            'stack' => $stackTarget->getName(),
            'file' => $stackTarget->last()->name,
            'size' => $stackTarget->getSize(),
            'md5' => $md5
        ];
    }
    public function removeAll(){
        foreach($this->meta['list'] as &$x){
            $x->setFlag(true);
        }
        $this->meta['list'] = [];
        $this->meta['history'] = [];
        $this->meta['hash'] = [];
        $this->meta['total'] = 0;
        $this->meta['total_uncompressed'] = 0;
        
        $this->update();
    }
    public function remove($uuid){
        $stack = $this->get($uuid);
        if($stack == null) return;
        $stack->restack($uuid);
        $x = array_search($uuid,$this->meta['history']);
        array_splice($this->meta['history'],$x,1);
        array_splice($this->meta['hash'],$x,1);
        $this->update();
    }
    public function revert($uuid){
        $stack = $this->get($uuid);
        if($stack == null) return;
        $reverted = $stack->revert($uuid);
        $history = $this->meta['history'];
        $indexed = [];
        array_map(function($data) use (&$indexed,$history){
            $x = array_search($data,$history);
            if($x>=0){
                $indexed[] = $x;
            }
        },$reverted);
        foreach($indexed as $x){
            array_splice($this->meta['history'],$x,1);
            array_splice($this->meta['hash'],$x,1);
        }
        if(!count($stack->files())){
            $rm = false;
            array_map(function($data,$i) use (&$rm,$stack){
                if($data->getName() == $stack->getName()){
                    $rm = $i;
                }
            },$this->meta['list']);
            array_splice($this->meta['list'],$rm,1);
        }
        $this->update();
    }
    public function retrieve($uuid){
        $result = null;
        foreach($this->meta['list'] as $list){
            if(($data = $list->get(basename($uuid))) && $data != false){
                $result = $data;
                break;
            }
        }
        return $result;
    }
    /**
     * @return object|null;
     */
    public function get($uuid){
        $result = null;
        foreach($this->meta['list'] as $list){
            if($uuid == $list->getName() || (($data = $list->verifyUuid(basename($uuid))) && $data != false)){
                $result = &$list;
                break;
            }
        }
        return $result;
    }
    public function update(){
        if(is_file($this->getpath("temp")));
        $total = [
            "total" => 0,
            "total_uncompressed" => 0
        ];
        array_map(function($data) use (&$total){
            $total['total'] += $data->getCompressed();
            $total['total_uncompressed'] += $data->getUncompressed();
        }, $this->meta['list']);
        if(is_file($this->getpath("uncompressed"))) {
            $total['total'] += BinaryDelta::getSize($this->getpath("uncompressed"));
            $total['total_uncompressed'] += BinaryDelta::getSize($this->getpath("uncompressed"));
        }
        $this->meta['total'] = $total['total'];
        $this->meta['total_uncompressed'] = $total['total_uncompressed'];
        if($total['total'] == 0) $this->meta['compression_ratio'] = 0;
        else $this->meta['compression_ratio'] = $total['total_uncompressed']/$total['total'];
        if($total['total_uncompressed'] == 0) $this->meta['space_saving'] = 1;
        else $this->meta['space_saving'] = 1-$total['total']/$total['total_uncompressed'];
        file_put_contents($this->getpath("meta.lsbd"),serialize($this->meta));
    }
    public function getHistory(){
        return $this->meta['history'];
    }
}