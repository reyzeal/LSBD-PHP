<?php
namespace reyzeal\LSBD;

trait MetaCompression{
    public function getCompressionRate(){
        return $this->meta['compression_ratio'];
    }
    public function getCompressed(){
        return $this->meta['total'];
    }
    public function getUncompressed(){
        return $this->meta['total_uncompressed'];
    }
    public function getSpaceSaving(){
        return $this->meta['space_saving'];
    }
}