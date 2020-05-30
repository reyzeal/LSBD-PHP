<?php
namespace reyzeal\LSBD;

class BinaryDelta{
    
    public static function patch($old, $patch, $new){
        return bsdiff_patch($old, $patch, $new);
    }
    public static function generate($old, $new, $patch){
        return bsdiff_diff($old, $new, $patch);
    }
    public static function getSize($file){
        return filesize($file);
    }
    public static function threshold($new, $old, $limit=0.5){
        return self::getSize($new)/self::getSize($old) < $limit;
    }
    public static function totalSize(Array $files){
        $total = 0;
        array_map(function($data) use (&$total){
            $total += self::getSize($data);
        },$files);
        return $total;
    }
}