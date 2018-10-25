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
            'daemonize' => true,
            'pid_file' => __DIR__ . "/../pid/idas_mo.pid",
            'worker_num' => 2,
            'task_worker_num' => 6,
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

        Db::getNewDb();//建立数据库连接

        if($worker_id === 0){
            echo "新进程开启！".PHP_EOL;
            //初始化内存表
            $server->task(Utils::readyArr(MsgLabel::TASK_PLACE_INIT));
            $server->task(Utils::readyArr(MsgLabel::TASK_TABLE_UPDATE));
            $server->tick(1000, [$this, 'tickTableUpdate']);//开启内存表更新定时器
        }

        if($worker_id === 1){
            $server->tick(30000, [$this, 'tickTableClean']);//开启内存表清理定时器
        }
        //echo "$this->name starting takes: " . (microtime(true) - $time) . PHP_EOL;
    }

    /**
     * 定时更新内存表
     */
    function tickTableUpdate(){
        $this->server->task(Utils::readyArr(MsgLabel::TASK_TABLE_UPDATE));
    }
    /**
     * 定时清理内存表
     */
    function tickTableClean(){
        $check_seq = $this->server->var_table->get("table_seq", "int_value") - 5;//缓存流水号落后5认为该数据已过期
        $device_table = $this->server->device_table;
        $channel_table = $this->server->channel_table;
        foreach($device_table as $d_table){
            if($d_table["d_table_seq"] <= $check_seq)
                $device_table->del($device_table->key());
        }
        foreach($channel_table as $ch_table){
            if($ch_table["ch_table_seq"] <= $check_seq)
                $channel_table->del($channel_table->key());
        }
    }

    function start(){
        $this->server->start();
    }
}


?>