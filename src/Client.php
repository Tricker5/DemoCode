<?php

namespace WSM;

class Client{
    private $fd;
    private $conninfo;

    function __construct($fd, $conninfo){
        $this->fd = $fd;
        $this->conninfo = $conninfo;
    }

    function getFd(){
        return $this->fd;
    }
    
    function getConnInfo(){
        return $this->conninfo;
    }
    


}



?>