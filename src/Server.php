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
    public $client_table;

    public function __construct(){
        //$time = microtime(true);
        if(!is_dir(Config::LOG_DIR))
            mkdir(Config::LOG_DIR, 0777, true);

        $this->server = new \swoole_websocket_server(Config::WSIP, Config::WSPORT);
        $this->open = new Open;
        $this->message = new Message;
        $this->close = new Close;
        $this->task = new Task;
        $this->finish = new Finish;

        $this->server->on('managerstart', [$this, "onManagerStart"]);
        $this->server->on('managerstop', [$this, "onManagerStop"]);
        $this->server->on('workerstart', [$this, "onWorkerStart"]);
        $this->server->on('task', [$this->task, "onTask"]);
        $this->server->on('finish', [$this->finish, "onFinish"]);
        $this->server->on('open', [$this->open, "onOpen"]);
        $this->server->on('message', [$this->message, "onMessage"]);
        $this->server->on('close', [$this->close, "onClose"]);

        $this->server->set([
            'worker_num' => 2,
            'task_worker_num' => 4,
            'log_file' => Config::LOG_DIR . '/swoole_log',
        ]);

        $this->table = new Table($this->server);
        //echo "Server constructing takes: " . (microtime(true) - $time) . PHP_EOL;
    }

    function onManagerStart($server){
        //$time = microtime(true);
        $this->logger = Loggers::loggerReg("manager");
        $this->logger->debug('onManagerStart: ',array(
            "server" => $server
        ));
        //echo "manager staring takes: " . (microtime(true) - $time) . PHP_EOL;
    }

    function onManagerStop($server){
        $this->logger->debug('onManagerStop: ', array(
            "server" => $server
        ));
    }

    function onWorkerStart($server,$worker_id){
        //$time = microtime(true);   
        if($server->taskworker){
            $task_worker_id = $worker_id - $server->setting['worker_num'];
            $this->task->name = "task_worker_".$task_worker_id;
            $this->name = $this->task->name;
            $this->logger = Loggers::loggerReg("task_worker");
            $this->logger->debug('onTaskWorkerStart: ', array(
                'worker_pid' => $server->worker_pid,
                'worker_id' => $worker_id,
                'taskworker' => $server->taskworker,
                'name' => $this->task->name
            ));
        }else{
            $this->name = "worker_".$worker_id;
            $this->logger = Loggers::loggerReg("worker");
            $this->logger->debug('onWorkerStart: ', array(
                'worker_pid' => $server->worker_pid,
                'worker_id' => $worker_id,
                'taskworker' => $server->taskworker,
                'name' => $this->name
            ));
        }
        Db::getNewDb();

        if($worker_id === 0){
            echo "新进程开启！".PHP_EOL;
            $taskarr = Utils::readyArr(MsgLabel::TASK_PLACE_INIT);
            $server->task($taskarr);
            $server->tick(1000, [$this, 'tickTableUpdate']);//开启定时器
        }

        //echo "$this->name starting takes: " . (microtime(true) - $time) . PHP_EOL;
    }

    /**
     * 定时更新内存表
     */
    function tickTableUpdate(){
        $taskarr = Utils::readyArr(MsgLabel::TASK_TABLE_UPDATE);
        $this->server->task($taskarr);
    }


    /**
     * 定时监控函数
     * 向Task进程投递数据库任务
     */
    function tickMonitor(){
        $pushfds = [];
        foreach($this->server->client_table as $client){
            $fd = $client["fd"];
            $moid = null;
            switch($client["motype"]){
                case "line":
                    if($client["moline"]){
                        $moid = MsgLabel::TASK_MOLINE + $client["moline"];
                        $pushfds[$moid][] = $fd;
                    }      
                    break;
                case "station":
                    if($client["mostation"]){
                        $moid = MsgLabel::TASK_MOSTATION + $client["mostation"];
                        $pushfds[$moid][] = $fd;
                    }      
                    break;
                default:
                    break; 
            }
        }

        //分批投递任务，保证可以投递至多个Task进程
        while(list($moid, $fds) = each($pushfds)){
            //if(!empty($fds)){
            $taskarr = Utils::readyArr(MsgLabel::TASK_MONITOR, array("moid"=>$moid, "fds" => $fds));
            $this->server->task($taskarr);
            //}
        }   
    }

    function start(){
        $this->server->start();
    }
}


?>