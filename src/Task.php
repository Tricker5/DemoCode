<?php

namespace WSM;

class Task{
    public $name;

    function onTask($server, $task_id, $src_worker_id, $taskarr){
        $logger = Loggers::$loggers["task_worker"];
        //$tick_i = &$server->tick_i;

        $logger->debug('onTask: ', array(
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'src_worker_id' => $src_worker_id,
            'task_id' => $task_id,
            'name' => $this->name
        ));

        switch($taskarr["head"]){                
            //处理监控任务
            case MsgLabel::TASK_MONITOR:
                $moarr;
                $motype;
                $moid = $taskarr["body"]["moid"];
                $fds = $taskarr["body"]["fds"];
                if($moid <2000){
                    $motype = MsgLabel::TASK_MOLINE;
                    $moarr = Db::moarr(MsgLabel::TASK_MOLINE, $moid % 1000);
                }else{
                    $motype = MsgLabel::TASK_MOSTATION;
                    $moarr = Db::moarr(MsgLabel::TASK_MOSTATION, $moid % 1000);
                }
                if($moarr){
                    $bodyarr = array(
                        "time" => date("Y-m-d, H:i:s"),
                        "motype" => $motype,
                        "moarr" => $moarr
                    );
                    $readydata = json_encode(Utils::readyArr(MsgLabel::MOARR, $bodyarr));
                    foreach($fds as $fd){
                        if($server->client_table->exist($fd))//判断客户端是否在查询过程中断开，减少警告
                            $server->push($fd, $readydata);
                    }   
                }
                break;

            case MsgLabel::DBTEST:
                $testarr = Db::testArr();
                $readydata = json_encode(Utils::readyArr(MsgLabel::DBTEST, $testarr));
                $server->push($fd, $readydata);
                break;

            default:
                break;
        }
    }
}

?>