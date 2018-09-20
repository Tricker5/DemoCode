<?php

namespace WSM;

class ClientMgr{
    public $clients;
    public function __construct(){
        $this->clients = array();
    }

    /**
    * 向clients[]数组注册连接的客户端
    */
    public function clientReg($fd, $conninfo){
        $this->clients[$fd] = new Client($fd, $conninfo);
    }

    /**
    * 将clients[]数组中断线的客户端注销
    */
    public function clientUnreg($fd){
//        echo "????????UNSET!!!!!!!!!!!!".PHP_EOL;
        unset($this->clients[$fd]);
    }

    /**
    * 设置client监控类型
    */
    public function setMoType($fd, $motype){
        $this->clients[$fd]->setMoType($motype);
    }

    /**
     * 设置client监控的线体ID
     */
    public function setMoLine($fd, $lineid){
//        echo "clientmgr.fd: ".$fd.PHP_EOL;
//        echo "clientmgr.lineid". $lineid.PHP_EOL;
        $this->clients[$fd]->setMoLine($lineid);
    }

    /**
     * 设置client监控的工位ID
     */
    public function setMoStation($fd, $stationid){
        $this->clients[$fd]->setMoStation($stationid);
    }

}


?>