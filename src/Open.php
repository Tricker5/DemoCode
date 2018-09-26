<?php

namespace WSM;

class Open{

    function onOpen($server, $request){
        $fd = $request->fd;
        $logger = Loggers::$loggers["worker"];
        $logger->debug('onOpen: ', array('fd' => $fd));

        echo "与 {$fd} 号客户端连接成功！".PHP_EOL;
        
        //注册连接客户端
        $server->client_table->set("$fd", ["fd" => $fd]);
        echo "当前客户端连接数为： ".$server->client_table->count().PHP_EOL;
    }
}

?>