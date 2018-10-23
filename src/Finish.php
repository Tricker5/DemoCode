<?php

namespace WSM;

class Finish{
    static function onFinish($server, $task_id, $data){
        $logger = Loggers::$loggers["worker"];
        $logger->debug('onFinish: ', array(
            "task" => $data["head"],
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'task_id' => $task_id
        ));

        switch($data["head"]){
            case MsgLabel::FINISH_CLIENT_CLASSIFY;
                while(list($monitor_type, $monitor_id_fd) = each($data["body"])){
                    if(count($monitor_id_fd) != 0){
                        $body = array("monitor_type" => $monitor_type, "monitor_id_fd" => $monitor_id_fd, "monitor_need" => false);
                        $server->task(Utils::readyArr(MsgLabel::TASK_PUSH, $body));
                    }
                }
                break;
            case MsgLabel::FINISH_TABLE_UPDATE:
                $server->task(Utils::readyArr(MsgLabel::TASK_CLIENT_CLASSIFY));
                break;
            default:
                break;
        }
    }
}

?>