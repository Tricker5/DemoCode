<?php

namespace WSM;

class Close{
    static function onClose($server, $fd){
        $logger = Loggers::$loggers["worker"];
        $logger->debug('onClose: ', array('fd' => $fd));

        ClientMgr::clientUnreg($fd);
        
        echo "$fd 号客户端关闭连接！".PHP_EOL;
        echo "当前客户端连接数为： ".count(ClientMgr::$clients).PHP_EOL;
    }
}

?>