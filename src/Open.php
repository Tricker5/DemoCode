<?php

namespace WSM;

class Open{

    static function onOpen($server, $request){
        $fd = $request->fd;
        $logger = Loggers::$loggers["worker"];
        $logger->debug('onOpen: ', array('fd' => $fd));

        echo "与 {$fd} 号客户端连接成功！".PHP_EOL;
        
        $conninfo = $server->getClientInfo($fd);
        
        //注册连接客户端
        ClientMgr::clientReg($fd, $conninfo);

        echo "当前客户端连接数为： ".count(ClientMgr::$clients).PHP_EOL;
        //print_r($this->clientmgr->clients[$fd]->getConnInfo());
    }
}

?>