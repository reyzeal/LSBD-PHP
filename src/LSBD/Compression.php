<?php

namespace reyzeal\LSBD;

class Compression{
    public static $GZIP = 1;
    public static $BZIP2 = 2;
    public static $XZ = 3;
    public function __construct($type, $level){
        $this->level = $level;
        $this->type = $type;
    }
    public function compress($data, $level=null){
        switch($this->type){
            case self::$GZIP:
                $result = gzcompress($data, $level);
            break;
            case self::$BZIP2:
                $result = bzcompress($data,$level);
            break;
        }
        return $result;
    }
    public function decompress($data){
        switch($this->type){
            case self::$GZIP:
                $result = gzuncompress($data);
            break;
            case self::$BZIP2:
                $result = bzdecompress($data);
            break;
        }
        return $result;
    }
}
