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

}


?>