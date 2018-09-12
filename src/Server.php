<?php

namespace WSM;

class Server{

    public $server;
    public $db;
    public $clientmgr;
    public $tick_i;
    public $wtname;

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
            $this->wtname = "task_worker_".$worker_id;
        else
            $this->wtname = "worker_".$worker_id;
        //echo $this->wtname.PHP_EOL;

        
        //if($this->db->getNewDb() === MsgLabel::DB_CONN_ERROR)
            //$this->server->shutdown();
        $this->clientmgr = new ClientMgr;
        $this->db = new Db;
        $this->tick_i = 0;
        if(!$server->taskworker&&$worker_id === 0 ){
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
        $readyarr = null;

        //判断message数据头部分head类型并处理
        switch ($data["head"]){
            case MsgLabel::NORMALSTR:
                if($data["body"]){
                    echo "收到来自 $fd 号客户端的信息：".$data["body"].PHP_EOL;
                    $readyarr = $this->readyArr(MsgLabel::NORMALSTR,  "已收到 ：".$data["body"]);
                }else{
                    echo "收到来自 $fd 号客户端的空白信息".PHP_EOL;
                    $readyarr = $this->readyArr(MsgLabel::NORMALSTR, "请输入有效内容！");
                }                
                break;

            case MsgLabel::DBTEST:
                echo "收到 $fd 号客户端的测试请求...".PHP_EOL;
                $readyarr = $this->readyArr(MsgLabel::DBTEST, $this->db->testFetch());
                break;

            case MsgLabel::MOTYPESET:
                echo "配置客户端监控类型...".PHP_EOL;
                $taskarr = $this->readyArr(MsgLabel::MOTYPESET, array("fd" => $fd, "motype" => $data["body"]));
                $server->task($taskarr, 0);
                $this->clientmgr->setMoType($fd, $data["body"]);
                echo "已将 $fd 号客户端监控类型配置为：".$this->clientmgr->clients[$fd]->getMoType().PHP_EOL;
                break;

            case MsgLabel::MOLINESET:
                echo "配置客户端监控线体...".PHP_EOL;
                $taskarr = $this->readyArr(MsgLabel::MOLINESET, array("fd" => $fd, "lineid" => $data["body"]));
                $server->task($taskarr, 0);
                $this->clientmgr->setMoLine($fd, $data["body"]);
                echo "已将 $fd 号客户端监控线体ID配置为：".$this->clientmgr->clients[$fd]->getMoLine().PHP_EOL;
                break;

            default:
                echo "未能识别来自 $fd 号客户端的信息：".PHP_EOL;
                $readyarr = $this->readyArr(MsgLabel::NORMALSTR, "信息无法识别！");
                break;
        }
        if($readyarr){
            if(!$server->push($fd, json_encode($readyarr)))
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

            case MsgLabel::MOTYPESET:
                $this->clientmgr->setMoType($taskarr["body"]["fd"], $taskarr["body"]["motype"]);
                break;

            case MsgLabel::MOLINESET:
                $this->clientmgr->setMoLine($taskarr["body"]["fd"], $taskarr["body"]["lineid"]);
                break;

            case MsgLabel::TASK_MOLINE:
                foreach ($this->clientmgr->clients as $client){
                    if(isset($client)){
                        //echo $client->getMoType().PHP_EOL;
                        if($client->getMoType()=="line"){
                            $lineid = $client->getMoLine();
                            $fd = $client->getFd(); 
                            //echo $client->getMoLine().PHP_EOL;  
                            if($lineid){
                                ++$this->tick_i;
                                echo "客户端ID：{$client->getFd()}；\t线体ID：{$lineid}；\t推送次数： {$this->tick_i}; ".PHP_EOL;
                                $moarr = $this->db->molinearr($lineid);
                                if($moarr){
                                    $bodyarr = array(
                                        "time" => date("Y-m-d, H:i:s"),
                                        "moarr" => $moarr
                                    );
                                    $readydata = json_encode($this->readyArr(MsgLabel::MOLINEARR, $bodyarr));
                                    $this->server->push($fd, $readydata);
                                }
                            }
                        }
                    }
                }
                break;

            default:
                break;
        }
    }

    function onFinish(){
        //log;
    }


    function onClose($server, $fd){
//        echo "????????UNREG!!!!!!!!!!!!".PHP_EOL;
        $taskarr = $this->readyArr(MsgLabel::TASK_CLIENTUNREG, $fd);
        $server->task($taskarr, 0);
        $this->clientmgr->clientUnreg($fd);
        
        echo "$fd 号客户端关闭连接！".PHP_EOL;
        echo "当前客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
    }

    function tickMonitor(){
        $taskarr = $this->readyArr(MsgLabel::TASK_MOLINE);
        $this->server->task($taskarr, 0);
        //echo "定时监控...".PHP_EOL;
        //echo count($this->clientmgr->clients).PHP_EOL;
//        $this->db->testInsert(++$this->tick_i);
    }

    function readyArr($head = null, $body = null){
        $readyarr = array(
            "head" => $head,
            "body" => $body
        );
//        $readydata = json_encode($readydata);
        return $readyarr;
    }
    
}


?>