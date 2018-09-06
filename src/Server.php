<?php

namespace WSM;

class Server{

    public $server;
    public $db;
    public $clientmgr;

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
        $this->db = new Db;
        $this->clientmgr = new ClientMgr;
        if($server->taskworker){

        }
    }

    function onOpen($server, $request){
        $fd = $request->fd;
        echo "与 {$fd} 号客户端连接成功！".PHP_EOL;
        
        $conninfo = $server->getClientInfo($fd);
        
        //注册连接客户端
        $taskarr = array(
            "head" => MsgLabel::TASK_CLIENTREG,
            "body" => array(
                "fd" => $fd,
                "conninfo" => $conninfo
            )
        );
        $server->task($taskarr, 0);
        $this->clientmgr->clientReg($fd, $conninfo);

        echo "当前客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
        //print_r($this->clientmgr->clients[$fd]->getConnInfo());

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
            
            case MsgLabel::DBTEST:
                echo "收到 $fd 号客户端的测试请求...".PHP_EOL;
                $readydata = array(
                    "head" => MsgLabel::DBTEST,
                    "body" => $this->db->testFetch()
                );
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
            if(!$server->push($fd, json_encode($readydata)))
                echo "数据包发送失败！".PHP_EOL;
            
        }
    }

    function onTask($server, $task_id, $src_worker_id, $taskarr){
        switch($taskarr["head"]){
            case MsgLabel::TASK_CLIENTREG:
                $this->clientmgr->clientReg($taskarr["body"]["fd"], $taskarr["body"]["conninfo"]);
                //echo "当前TASK进程中客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
                break;
            
            case MsgLabel::TASK_CLIENTUNREG:
                unset($this->clientmgr->clients[$taskarr["body"]]);
                //echo "当前TASK进程客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
                break;

            default:
                break;
        }
    }

    function onFinish(){

    }


    function onClose($server, $fd){

        $taskarr = array(
            "head" => MsgLabel::TASK_CLIENTUNREG,
            "body" => $fd
        );
        $server->task($taskarr, 0);
        unset($this->clientmgr->clients[$fd]);

        echo "$fd 号客户端关闭连接！".PHP_EOL;
        echo "当前客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
    }
}


?>