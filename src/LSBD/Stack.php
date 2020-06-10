<?php
namespace reyzeal\LSBD;

use reyzeal\LSBD\MetaCompression;
use Ramsey\Uuid\Uuid;
use reyzeal\LSBD\BinaryDelta;
use reyzeal\LSBD\PathHelper;
use reyzeal\LSBD\Compression;
class Container{
    public $size = 0;
    public $compressed_size = 0;
    public $name = "";
    public function __construct($name, $size, $csize){
        $this->size = $size;
        $this->compressed_size = $csize;
        $this->name = $name;
    }
}

class Stack implements Meta{
    use PathHelper;
    use MetaCompression;
    public function update(){
        if(is_file($this->getpath("temp")));
        $total = [
            "total" => 0,
            "total_uncompressed" => 0
        ];
        array_map(function(&$data) use (&$total){
            $data->compressed_size = BinaryDelta::getSize($data->name);
            $total['total'] += $data->compressed_size;
            $total['total_uncompressed'] += $data->size;
        }, $this->meta['stack']);
        $this->meta['total'] = $total['total'];
        $this->meta['total_uncompressed'] = $total['total_uncompressed'];
        if($total['total'] == 0) $this->meta['compression_ratio'] = 0;
        else $this->meta['compression_ratio'] = $total['total_uncompressed']/$total['total'];
        if($total['total_uncompressed'] == 0) $this->meta['space_saving'] = 1;
        else $this->meta['space_saving'] = 1-$total['total']/$total['total_uncompressed'];
        file_put_contents($this->getpath("meta.lsbd"),serialize($this->meta));
    }
    public function getName(){
        return $this->name;
    }
    public function __construct($path){
        $this->archiver = new Compression(1,9);
        $this->name = basename($path);
        $this->path = $path;
        $this->meta = [
            "total" => 0,
            "total_uncompressed" => 0,
            "stack" => []
        ];
        if(!is_dir($path)){
            mkdir($path);
        }
        if(!is_file($this->getpath("meta.lsbd"))){
            $this->update();
        }else{
            $this->meta = unserialize(file_get_contents($this->getpath("meta.lsbd")));
        }
        $this->flag = false;
    }
    public function setFlag(bool $data){
        $this->flag = $data;
    }
    public function __destruct(){
        if($this->flag){
            foreach($this->meta['stack'] as $x){
                if(is_file($x->name))unlink($x->name);
            }
            unlink($this->getpath("meta.lsbd"));
            rmdir($this->path);
        }
    }
    public function verifyUuid($uuid){
        $uuid = basename($uuid);
        $hash = [];
        array_map(function($data) use (&$hash){
            $hash[] = basename($data->name);
        }, array_reverse($this->meta["stack"]));
        return array_search($uuid, $hash);
    }
    public function restack($uuid){
        $target = $this->verifyUuid($uuid);
        $temp = $this->getpath("temp");
        if(!is_bool($target) && $target == count($this->files())-1){
            unlink($this->meta['stack'][0]->name);
            array_splice($this->meta['stack'],0,1);
        }else if(!is_bool($target) && $target == 0){
            $r = $this->meta['stack'];
            $base = array_pop($r)->name;
            $patch = array_pop($r);
            BinaryDelta::patch($base,$patch->name,$temp);
            copy($temp, $patch->name);
            unlink($base);
            array_splice($this->meta['stack'],count($this->files())-1,1);
        }else if(!is_bool($target)){
            $r = $this->meta['stack'];
            $base = array_pop($r)->name;
            $unpack = $target;
            $target = count($r)-$unpack;
            $unpack++;
            while($unpack){
                $patch = array_pop($r);
                BinaryDelta::patch($base,$patch->name,"$base.temp");
                $base = $temp;
                $unpack--;
            }
            $s = $this->meta['stack'];
            $i = $target - 1;
            $base = $s[$i]->name;
            while($i < count($s)-1){
                $i++;
                $patch = $s[$i]->name;
                if($i != $target){
                    BinaryDelta::generate("$base.temp","$patch.temp",$base);
                    unlink("$base.temp");
                    $base = $patch;
                }
                else{
                    unlink("$patch.temp");
                    unlink($patch);
                }
            }
            array_splice($this->meta['stack'],$target,1);
        }
        $this->update();
    }
    public function pop(){
        if(count($this->meta['stack']) == 1){
            $name = array_pop($this->meta['stack'])->name;
            $data = file_get_contents($name);
            unlink($name);
            $this->update();
            return $name;
        }
        $new = array_pop($this->meta['stack'])->name;
        $patch = end($this->meta["stack"])->name;
        BinaryDelta::patch($new, $patch, $patch);
        unlink($new);
        $this->update();
        return $new;
    }
    public function push($file){
        if(count($this->meta['stack']) == 0){
            $uuid = Uuid::uuid4();
            $ustr = $this->getpath($uuid->toString());
            $data = file_get_contents($file);
            file_put_contents($ustr,$data);
            $this->meta["stack"][] = new Container($ustr,BinaryDelta::getSize($ustr),BinaryDelta::getSize($ustr));
            $data = $this->archiver->compress($data,9);
            file_put_contents($ustr,$data);
            
            $this->update();
            return is_file($ustr);
        }
        $latest = end($this->meta['stack']);

        $get = file_get_contents($latest->name);
        $get = $this->archiver->decompress($get);
        file_put_contents($latest->name, $get);

        $uuid = Uuid::uuid4();
        $ustr = $this->getpath($uuid->toString());
        $actualSize = BinaryDelta::getSize($latest->name);
        BinaryDelta::generate($file,$latest->name,"$latest->name.patch");
        $data = file_get_contents($file);
        file_put_contents($ustr,$data);
        $actual = BinaryDelta::getSize($ustr);
        $data = $this->archiver->compress($data,9);
        file_put_contents($ustr,$data);
        $latest->size = $actualSize;
        $latest->compressed_size = BinaryDelta::getSize("$latest->name.patch");
        rename("$latest->name.patch",$latest->name);
        $this->meta['stack'][count($this->meta['stack'])-1] = $latest;
        $this->meta["stack"][] = new Container($ustr,$actual,$actual);
        $this->update();
        return is_file($ustr);
    }
    public function get($uuid=null){
        if($uuid == null){
            if(count($this->meta['stack']) > 0){
                $data = file_get_contents(end($this->meta['stack'])->name);
                $dcompress = $this->archiver->decompress($data);
                return $data;
            }
            return null;
        }
        $uuid = $this->verifyUuid($uuid);
        if(!is_bool($uuid) && $uuid>=0){
            $r = $this->meta['stack'];
            $base = array_pop($r)->name;
            
            $get = file_get_contents($base);
            $get = $this->archiver->decompress($get);
            file_put_contents($base, $get);
            $revert = $base;
            
            $temp = $this->getpath("temp");
            copy($base,$temp);
            while($uuid){
                $patch = array_pop($r);
                BinaryDelta::patch($base,$patch->name,$temp);
                $base = $temp;
                $uuid--;
            }
            file_put_contents($revert, $this->archiver->compress($get,9));
            $data = file_get_contents($temp);
            unlink($temp);
            return $data;
        }
        return null; 
    }
    public function revert($uuid){
        $target = $this->verifyUuid($uuid);
        $reverted = [];
        if(!is_bool($target) && $target>=0){
            $target--;
            while($target>=0){
                $reverted[] = $this->pop();
                $target--;
            }
        }
        return $reverted;
    }
    public function getSize($uuid=null){
        $data = $this->get($uuid);
        return strlen($data);
    }
    public function patch_files(){
        return array_slice($this->meta['stack'],0,count($this->meta['stack'])-2);
    }
    public function files(){
        return $this->meta["stack"];
    }
    public function getMeta(){
        return $this->meta;
    }
    public function last(){
        return end($this->meta['stack']);
    }
}