<?php

namespace WSM;

class Finish{
    static function onFinish($server, $task_id, $data){
        $logger = Loggers::$loggers["worker"];
        $logger->debug('onFinish: ', array(
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'task_id' => $task_id
        ));

        switch($data){
            case MsgLabel::FINISH_TABLE_UPDATE:
                $server->task(Utils::readyArr(MsgLabel::TASK_PUSH));
                break;
            default:
                break;
        }
    }
}

?>