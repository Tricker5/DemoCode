<?php

namespace WSM;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Server{

    public $server;
    public $db;
    public $clientmgr;
    public $tick_i;
    public $wtname;
    public $logger;

    public function __construct(){

        if(!is_dir(Config::DIR))
            mkdir(Config::DIR, 0777, true);
        //@fopen(Config::DIR . '/swoole_log', 'a+');

        $this->server = new \swoole_websocket_server(Config::WSIP, Config::WSPORT);

        $this->server->set([
            'worker_num' => 1,
            'task_worker_num' => 1,
            'log_file' => Config::DIR . '/swoole_log'
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
        
        $stream = new StreamHandler(Config::DIR . "/{$this->wtname}", Config::LOGGER_LEVEL);
        $this->logger = new Logger('main');
        $this->logger->pushHandler($stream);

        $this->logger->debug('onWorkerStart: ', array(
            'worker_pid' => $server->worker_pid,
            'worker_id' => $worker_id,
            'taskworker' => $server->taskworker
        ));

        $this->clientmgr = new ClientMgr;
        $this->db = new Db;
        $this->tick_i = 0;
        if(!$server->taskworker && $worker_id === 0 ){
            echo "新进程开启！".PHP_EOL;
            $server->tick(1000, [$this, 'tickMonitor']);
        }
    }

    function onOpen($server, $request){
        $fd = $request->fd;
        $this->logger->debug('onOpen: ', array('fd' => $fd));

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

        $this->logger->debug('onMessage: ', array(
            'fd' => $fd,
            'data' => $data
        ));

        //判断message数据头部分head类型并处理
        switch ($data["head"]){
            case MsgLabel::NORMALSTR:
                if($data["body"]){
                    echo "收到来自 $fd 号客户端的信息：".$data["body"].PHP_EOL;
                    $readyarr = Utils::readyArr(MsgLabel::NORMALSTR,  "已收到 ：".$data["body"]);
                }else{
                    echo "收到来自 $fd 号客户端的空白信息".PHP_EOL;
                    $readyarr = Utils::readyArr(MsgLabel::NORMALSTR, "请输入有效内容！");
                }                
                break;

            case MsgLabel::DBTEST:
                echo "收到 $fd 号客户端的测试请求...".PHP_EOL;
                $readyarr = Utils::readyArr(MsgLabel::DBTEST, $this->db->testFetch());
                break;

            case MsgLabel::MOTYPESET:
                echo "配置客户端监控类型...".PHP_EOL;
                $taskarr = Utils::readyArr(MsgLabel::MOTYPESET, array("fd" => $fd, "motype" => $data["body"]));
                $server->task($taskarr, 0);
                $this->clientmgr->setMoType($fd, $data["body"]);
                echo "已将 $fd 号客户端监控类型配置为：".$this->clientmgr->clients[$fd]->getMoType().PHP_EOL;
                break;

            case MsgLabel::MOLINESET:
                echo "配置客户端监控线体...".PHP_EOL;
                $taskarr = Utils::readyArr(MsgLabel::MOLINESET, array("fd" => $fd, "lineid" => $data["body"]));
                $server->task($taskarr, 0);
                $this->clientmgr->setMoLine($fd, $data["body"]);
                echo "已将 $fd 号客户端监控线体ID配置为：".$this->clientmgr->clients[$fd]->getMoLine().PHP_EOL;
                break;
            
            case MsgLabel::MOSTATIONSET:
                echo "配置客户端监控工位...".PHP_EOL;
                $taskarr = Utils::readyArr(MsgLabel::MOSTATIONSET, array("fd" => $fd, "stationid" => $data["body"]));
                $server->task($taskarr, 0);
                $this->clientmgr->setMoStation($fd, $data["body"]);
                echo "已将 $fd 号客户端监控工位ID配置为：".$this->clientmgr->clients[$fd]->getMoStation().PHP_EOL;
                break;

            default:
                echo "未能识别来自 $fd 号客户端的信息：".PHP_EOL;
                $readyarr = Utils::readyArr(MsgLabel::NORMALSTR, "信息无法识别！");
                break;
        }
        if($readyarr){
            if(!$server->push($fd, json_encode($readyarr)))
                echo "数据包发送失败！".PHP_EOL;
            
        }
    }

    function onTask($server, $task_id, $src_worker_id, $taskarr){
        $fd = $taskarr["body"]["fd"] ?: null;

        $this->logger->debug('onTask: ', array(
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'src_worker_id' => $src_worker_id,
            'task_id' => $task_id
        ));

        switch($taskarr["head"]){
            case MsgLabel::TASK_CLIENTREG:
                $this->clientmgr->clientReg($fd, $taskarr["body"]["conninfo"]);
                //echo "当前TASK进程中客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
                break;
            
            case MsgLabel::TASK_CLIENTUNREG:
                $this->clientmgr->clientUnreg($taskarr["body"]);
                //echo "当前TASK进程客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
                break;

            case MsgLabel::MOTYPESET:
                $this->clientmgr->setMoType($fd, $taskarr["body"]["motype"]);
                break;

            case MsgLabel::MOLINESET:
                $this->clientmgr->setMoLine($fd, $taskarr["body"]["lineid"]);
                break;
            
            case MsgLabel::MOSTATIONSET:
                $this->clientmgr->setMoStation($fd, $taskarr["body"]["stationid"]);
                break;

            case MsgLabel::TASK_MONITOR:
                $moarr;
                switch($taskarr["body"]["motype"]){
                    case MsgLabel::TASK_MOLINE:
                        ++$this->tick_i;
                        echo "客户端ID：{$fd}；\t线体ID：{$taskarr["body"]["moid"]}；\t推送次数： {$this->tick_i}; ".PHP_EOL;
                        $moarr = $this->db->moarr(MsgLabel::TASK_MOLINE, $taskarr["body"]["moid"]);
                        break;
                    case MsgLabel::TASK_MOSTATION:
                        ++$this->tick_i;
                        echo "客户端ID：{$fd}；\t工位ID：{$taskarr["body"]["moid"]}；\t推送次数： {$this->tick_i}; ".PHP_EOL;
                        $moarr = $this->db->moarr(MsgLabel::TASK_MOSTATION, $taskarr["body"]["moid"]);
                        break;
                    default:
                        break;    
                }
                if($moarr){
                    $bodyarr = array(
                        "time" => date("Y-m-d, H:i:s"),
                        "motype" => $taskarr["body"]["motype"],
                        "moarr" => $moarr
                    );
                    $readydata = json_encode(Utils::readyArr(MsgLabel::MOARR, $bodyarr));
                    $this->server->push($fd, $readydata);
                }
                break;

            default:
                break;
        }
    }

    function onFinish($server, $task_id, $data){
        $this->logger->debug('onFinish: ', array(
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'task_id' => $task_id
        ));
    }


    function onClose($server, $fd){

        $this->logger->debug('onClose: ', array('fd' => $fd));

        $taskarr = Utils::readyArr(MsgLabel::TASK_CLIENTUNREG, $fd);
        $server->task($taskarr, 0);
        $this->clientmgr->clientUnreg($fd);
        
        echo "$fd 号客户端关闭连接！".PHP_EOL;
        echo "当前客户端连接数为： ".count($this->clientmgr->clients).PHP_EOL;
    }

    function tickMonitor(){
        foreach($this->clientmgr->clients as $client){
            $fd = $client->getFd();
            //echo $fd.PHP_EOL;
            $motype = null;
            $moid = null;
            switch($client->getMoType()){
                case "line":
                    $motype = MsgLabel::TASK_MOLINE;
                    $moid = $client->getMoLine() ?: null;//若$client->getMoLine()有值，则赋值，否则为null
                    break;
                case "station":
                    $motype = MsgLabel::TASK_MOSTATION;
                    $moid = $client->getMoStation() ?: null;
                    break;
                default:
                    break; 
            }
//            echo "motype: ".$motype."; moid: ".$moid.PHP_EOL;
            if($motype !== null && $moid !== null){
                $moarray = array(
                    "fd" => $fd,
                    "motype" => $motype,
                    "moid" => $moid
                );
                $taskarr = Utils::readyArr(MsgLabel::TASK_MONITOR, $moarray);
                $this->server->task($taskarr, 0);
            }
        }
    }
    
}


?>