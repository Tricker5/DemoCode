<?php

namespace WSM;

class Task{
    //static $tick_i = 0;
    public $name;

    function onTask($server, $task_id, $src_worker_id, $taskarr){
        $fd = $taskarr["body"]["fd"] ?: null;
        $logger = Loggers::$loggers["task_worker"];

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
                //判断监控类型
                switch($taskarr["body"]["motype"]){
                    case MsgLabel::TASK_MOLINE:
                        //++static::$tick_i;
                        //echo "客户端ID：{$fd}；\t线体ID：{$taskarr["body"]["moid"]}；\t推送次数： ".static::$tick_i."; ".PHP_EOL;
                        $moarr = Db::moarr(MsgLabel::TASK_MOLINE, $taskarr["body"]["moid"]);
                        break;
                    case MsgLabel::TASK_MOSTATION:
                        //++static::$tick_i;
                        //echo "客户端ID：{$fd}；\t工位ID：{$taskarr["body"]["moid"]}；\t推送次数： ".static::$tick_i."; ".PHP_EOL;
                        $moarr = Db::moarr(MsgLabel::TASK_MOSTATION, $taskarr["body"]["moid"]);
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
                    $server->push($fd, $readydata);
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