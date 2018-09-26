<?php

namespace WSM;

class Client{
    private $fd;
    private $motype;
    private $moline;
    private $mostation;

    function __construct($fd){
        $this->fd = $fd;
        $this->moline = null;
    }

/**
 * 获取client各项属性
 */
    function getFd(){
        return $this->fd;
    }

    function getMoType(){
        return $this->motype;
    }

    function setMoType($motype){
        $this->motype = $motype;
    }

    function getMoLine(){
        return $this->moline;
    }

    function setMoLine($lineid){
        $this->moline = $lineid;
    }
    
    function getMoStation(){
        return $this->mostation;
    }

    function setMoStation($stationid){
        $this->mostation = $stationid;
    }


}



?>