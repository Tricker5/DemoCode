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
        if($server->taskworker)
            $wtname = "task_worker_".$worker_id;
        else
            $wtname = "worker_".$worker_id;
        //echo $wtname.PHP_EOL;

        $this->db = new Db;
        $this->clientmgr = new ClientMgr;
        if($server->taskworker && $wtname === "task_worker_1"){
            $server->tick(1000, [$this, 'tickMonitor']);
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
        $readydata = null;

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
            case MsgLabel::MOLINESET:
                echo "配置客户端监控线体...".PHP_EOL;
                $taskarr = array(
                    "head" => MsgLabel::MOLINESET,
                    "body" => array(
                        "fd" => $fd,
                        "lineid" => $data["body"]
                    )
                );
                $server->task($taskarr, 0);
                $this->clientmgr->setMoLine($fd, $data["body"]);
                echo "已将 $fd 号客户端监控线体ID配置为：".$this->clientmgr->clients[$fd]->getMoLine().PHP_EOL;
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
                $this->clientmgr->clientUnreg($taskarr["body"]);
                //echo "当前TASK进程客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
                break;
            case MsgLabel::MOLINESET:
                $this->clientmgr->setMoLine($taskarr["body"]["fd"], $taskarr["body"]["lineid"]);
                break;
            default:
                break;
        }
    }

    function onFinish(){
        //log;
    }


    function onClose($server, $fd){
        echo "????????UNREG!!!!!!!!!!!!".PHP_EOL;
        $taskarr = array(
            "head" => MsgLabel::TASK_CLIENTUNREG,
            "body" => $fd
        );
        $server->task($taskarr, 0);
        $this->clientmgr->clientUnreg($fd);
        
        echo "$fd 号客户端关闭连接！".PHP_EOL;
        echo "当前客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
    }

    function tickMonitor(){
        //echo "定时监控...".PHP_EOL;
        foreach ($this->clientmgr->clients as $client){
            $lineid = $client->getMoLine();
            $fd = $client->getFd();
            if($lineid){
                echo $lineid.' ';
                $readydata = $this->readyData(MsgLabel::MOLINEARR, $this->db->molinearr($lineid));
                $this->server->push($fd, $readydata);
            }
        }
    }

    function readyData($head, $body){
        $readydata = array(
            "head" => $head,
            "body" => $body
        );
        $readydata = json_encode($readydata);
        return $readydata;
    }
    
}


?>