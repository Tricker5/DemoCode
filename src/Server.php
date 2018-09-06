<?php

namespace WSM;

class Server{

    public $server;
    public $db;

    public function __construct(){
        $this->server = new \swoole_websocket_server(Config::WSIP, Config::WSPORT);

        $this->server->set([
            'worker_num' => 1,
            //'task_worker_num' => 1
        ]);

        $this->server->on('open', [$this, "onOpen"]);
        $this->server->on('message', [$this, "onMessage"]);
        $this->server->on('close', [$this, "onClose"]);
        $this->server->on('workerstart', [$this, "onWorkerStart"]);

    }

    function onWorkerStart(){
        $db = new Db;
    }

    function onOpen($server, $request){
        echo "与 {$request->fd} 号客户端连接成功！".PHP_EOL;
    }
    function onMessage(){

    }
    function onClose(){

    }
}


?>