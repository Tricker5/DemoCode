<?php

namespace WSM;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Server{

    public $server;
    public $name;
    public $logger;
    public $open;
    public $message;
    public $task;
    public $finish;

    public function __construct(){

        if(!is_dir(Config::DIR))
            mkdir(Config::DIR, 0777, true);
        //@fopen(Config::DIR . '/swoole_log', 'a+');

        $this->server = new \swoole_websocket_server(Config::WSIP, Config::WSPORT);
        $this->open = new Open;
        $this->message = new Message;
        $this->close = new Close;
        $this->task = new Task;
        $this->finish = new Finish;

        $this->server->set([
            'worker_num' => 1,
            'task_worker_num' => 1,
            'log_file' => Config::DIR . '/swoole_log'
        ]);
        
        $this->server->on('managerstart', [$this, "onManagerStart"]);
        $this->server->on('managerstop', [$this, "onManagerStop"]);
        $this->server->on('workerstart', [$this, "onWorkerStart"]);
        $this->server->on('task', [$this->task, "onTask"]);
        $this->server->on('finish', [$this->finish, "onFinish"]);
        $this->server->on('open', [$this->open, "onOpen"]);
        $this->server->on('message', [$this->message, "onMessage"]);
        $this->server->on('close', [$this->close, "onClose"]);

    }

    function onManagerStart($server){
        $this->logger = Loggers::loggerReg("manager");
        $this->logger->debug('onManagerStart: ',array(
            "server" => $server
        ));
    }

    function onManagerStop($server){
        $this->logger->debug('onManagerStop: ', array(
            "server" => $server
        ));
    }

    function onWorkerStart($server,$worker_id){   
        if($server->taskworker){
            $task_worker_id = $worker_id - $server->setting['worker_num'];
            Task::$name = "task_worker_".$task_worker_id;
            $this->logger = Loggers::loggerReg("task_worker");
            $this->logger->debug('onTaskWorkerStart: ', array(
                'worker_pid' => $server->worker_pid,
                'worker_id' => $worker_id,
                'taskworker' => $server->taskworker,
                'name' => Task::$name
            ));
            Db::getNewDb();
        }else{
            $this->name = "worker_".$worker_id;
            $this->logger = Loggers::loggerReg("worker");
            $this->logger->debug('onWorkerStart: ', array(
                'worker_pid' => $server->worker_pid,
                'worker_id' => $worker_id,
                'taskworker' => $server->taskworker,
                'name' => $this->name
            ));
            if($worker_id === 0){
                echo "新进程开启！".PHP_EOL;
                $server->tick(1000, [$this, 'tickMonitor']);
            }

        }
    }

    /**
     * 定时监控函数
     * 向Task进程投递数据库任务
     */
    function tickMonitor(){
        foreach(ClientMgr::$clients as $client){
            $fd = $client->getFd();
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
            //投递任务
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
    
    function start(){
        $this->server->start();
    }
}


?>