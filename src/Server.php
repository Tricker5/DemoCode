<?php

namespace WSM;

class Server{

    public $server;
    public $db;

    public function __construct(){
        $this->server = new \swoole_websocket_server(Config::WSIP, Config::WSPORT);

        $this->server->set([
            'worker_num' => 1,
            'task_worker_num' => 1
        ]);

        $this->server->on('open', [$this, "onOpen"]);
        $this->server->on('message', [$this, "onMessage"]);
        $this->server->on('close', [$this, "onClose"]);
        $this->server->on('workerstart', [$this, "onWorkerStart"]);
        $this->server->on('task', [$this, "onTask"]);
        $this->server->on('finish', [$this, "onFinish"]);

    }

    function onWorkerStart($server,$worker_id){
        $db = new Db;
    }

    function onOpen($server, $request){
        echo "与 {$request->fd} 号客户端连接成功！".PHP_EOL;
    }
    function onMessage($server, $frame){
        $fd = $frame->fd;
        $data = json_decode($frame->data, true);
        $readydata;

        switch ($data["head"]){
            case MsgLabel::NORMALSTR:
                if($data["body"]){
                    echo "收到来自 $fd 号客户端的信息：".$data["body"].PHP_EOL;
                    $readydata = array(
                        "head" => MsgLabel::NORMALSTR,
                        "body" => "已收到 ：".$data["body"]
                    );
                }else{
                    echo "收到来自 $fd 号客户端的空白信息".PHP_EOL;
                    $readydata = array(
                        "head" => MsgLabel::NORMALSTR,
                        "body" => "请输入有效内容！"
                    );
                }                
                break;
            

            default:
                echo "未能识别来自 $fd 号客户端的信息：".PHP_EOL;
                $readydata = array(
                    "head" => MsgLabel::NORMALSTR,
                    "body" => "信息无法识别！"
                );
                break;
        }
        if($readydata){
            $this->server->push($fd, json_encode($readydata));
        }
    }

    function onTask(){

    }

    function onFinish(){
        
    }


    function onClose($server, $fd){
        echo "$fd 号客户端关闭连接！".PHP_EOL;
    }
}


?>