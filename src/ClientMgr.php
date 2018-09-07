<?php

namespace WSM;

class ClientMgr{
    public $clients;
    public function __construct(){
        $this->clients = [];
    }

    public function clientReg($fd, $conninfo){
        $this->clients[$fd] = new Client($fd, $conninfo);
    }

    public function clientUnreg($fd){
        unset($this->clients[$fd]);
    }

    public function setMoLine($fd, $lineid){
        $this->clients[$fd]->setMoLine($lineid);
    }

}


?>