<?php

namespace WSM;

class ClientMgr{
    public $clients;
    public function __construct(){
        $this->clients = array();
    }

    public function clientReg($fd, $conninfo){
        $this->clients[$fd] = new Client($fd, $conninfo);
    }

    public function clientUnreg($fd){
//        echo "????????UNSET!!!!!!!!!!!!".PHP_EOL;
        unset($this->clients[$fd]);
    }

    public function setMoLine($fd, $lineid){
//        echo "clientmgr.fd: ".$fd.PHP_EOL;
//        echo "clientmgr.lineid". $lineid.PHP_EOL;
        $this->clients[$fd]->setMoLine($lineid);
    }

}


?>