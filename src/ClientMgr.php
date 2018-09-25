<?php

namespace WSM;

class ClientMgr{
    public static $clients = [];
    
    /**
    * 向clients[]数组注册连接的客户端
    */
    public static function clientReg($fd, $conninfo){
        static::$clients[$fd] = new Client($fd, $conninfo);
    }

    /**
    * 将clients[]数组中断线的客户端注销
    */
    public static function clientUnreg($fd){
        unset(static::$clients[$fd]);
    }

    /**
    * 设置client监控类型
    */
    public static function setMoType($fd, $motype){
        static::$clients[$fd]->setMoType($motype);
    }

    /**
     * 设置client监控的线体ID
     */
    public static function setMoLine($fd, $lineid){
        static::$clients[$fd]->setMoLine($lineid);
    }

    /**
     * 设置client监控的工位ID
     */
    public static function setMoStation($fd, $stationid){
        static::$clients[$fd]->setMoStation($stationid);
    }

}


?>